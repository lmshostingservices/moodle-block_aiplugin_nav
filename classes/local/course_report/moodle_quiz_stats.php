<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Course Report helper for LMS Labs plugins.
 *
 * @copyright 2026 LMS Labs
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_aiplugin_nav\local\course_report;

defined('MOODLE_INTERNAL') || die();

/**
 * Read-only per-question statistics for standard Moodle quizzes.
 *
 * Responses are read from Moodle's question-engine attempt tables. This keeps
 * the report independent of the quiz statistics cache, which may not have been
 * generated yet.
 *
 * @package block_aiplugin_nav
 */
final class moodle_quiz_stats {
    /**
     * Helper.
     *
     * @param int $courseid
     * @return array
     */
    public static function for_course(int $courseid): array {
        global $DB;
        $dbman = $DB->get_manager();
        foreach (
            [
            'quiz', 'quiz_slots', 'quiz_attempts', 'question', 'question_answers',
            'question_usages', 'question_attempts', 'question_attempt_steps',
            'question_attempt_step_data',
            ] as $table
        ) {
            if (!$dbman->table_exists(new \xmldb_table($table))) {
                return [];
            }
        }
        $quizmodule = $DB->get_record('modules', ['name' => 'quiz'], 'id');
        if (!$quizmodule) {
            return [];
        }
        $slotjoin = self::slot_question_join();
        if ($slotjoin === null) {
            return [];
        }
        $quizzes = $DB->get_records_sql(
            "SELECT cm.id AS cmid, cm.instance AS quizid, cs.section AS sectionnumber,
                    cs.name AS sectionname, qz.name
               FROM {course_modules} cm
               JOIN {course_sections} cs ON cs.id = cm.section
               JOIN {quiz} qz ON qz.id = cm.instance
              WHERE cm.course = :courseid AND cm.module = :moduleid AND cm.visible = 1
           ORDER BY cm.id ASC",
            ['courseid' => $courseid, 'moduleid' => $quizmodule->id]
        );
        $result = [];
        foreach ($quizzes as $quiz) {
            $questions = $DB->get_records_sql(
                "SELECT qs.slot, q.id AS questionid, q.name AS questionname, q.questiontext, q.qtype
                   FROM {quiz_slots} qs
                   {$slotjoin}
                  WHERE qs.quizid = :quizid
               ORDER BY qs.slot ASC",
                ['quizid' => $quiz->quizid]
            );
            if (!$questions) {
                continue;
            }
            $ids = array_map(static fn(\stdClass $row): int => (int)$row->questionid, $questions);
            [$qsql, $qparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'qid');
            $answers = $DB->get_records_sql(
                "SELECT id, question, answer, fraction
                   FROM {question_answers}
                  WHERE question $qsql
               ORDER BY question ASC, id ASC",
                $qparams
            );
            $answerrows = [];
            foreach ($answers as $answer) {
                $answerrows[(int)$answer->question][] = $answer;
            }
            $attemptrows = $DB->get_recordset_sql(
                "SELECT qatt.id AS qattid, qatt.questionid, qatt.slot, qatt.maxmark,
                        qz.userid, qz.attempt AS quizattempt, qz.sumgrades, qz.timefinish,
                        qas.sequencenumber, qas.fraction, qas.timecreated,
                        qasd.name AS responsename, qasd.value AS responsevalue
                   FROM {quiz_attempts} qz
                   JOIN {question_usages} qu ON qu.id = qz.uniqueid
                   JOIN {question_attempts} qatt ON qatt.questionusageid = qu.id
                   JOIN {question_attempt_steps} qas ON qas.questionattemptid = qatt.id
              LEFT JOIN {question_attempt_step_data} qasd
                     ON qasd.attemptstepid = qas.id
                    AND (qasd.name = 'answer' OR qasd.name LIKE 'choice%')
                  WHERE qz.quiz = :quizid AND qz.state = :finished
               ORDER BY qz.userid ASC, qz.attempt ASC, qatt.slot ASC, qas.sequencenumber ASC",
                ['quizid' => $quiz->quizid, 'finished' => 'finished']
            );
            $attempts = [];
            foreach ($attemptrows as $row) {
                $id = (int)$row->qattid;
                if (!isset($attempts[$id])) {
                    $attempts[$id] = [
                        'questionid' => (int)$row->questionid,
                        'userid' => (int)$row->userid,
                        'quizattempt' => (int)$row->quizattempt,
                        'sumgrades' => (float)$row->sumgrades,
                        'timefinish' => (int)$row->timefinish,
                        'maxmark' => (float)$row->maxmark,
                        'firstresponse' => null,
                        'firstfraction' => null,
                        'finalfraction' => null,
                        'lastsequence' => -1,
                    ];
                }
                $sequence = (int)$row->sequencenumber;
                if ($row->fraction !== null && $sequence >= $attempts[$id]['lastsequence']) {
                    $attempts[$id]['finalfraction'] = (float)$row->fraction;
                    $attempts[$id]['lastsequence'] = $sequence;
                }
                if ($row->fraction !== null && $attempts[$id]['firstfraction'] === null) {
                    $attempts[$id]['firstfraction'] = (float)$row->fraction;
                }
                if ($row->responsename !== null && $attempts[$id]['firstresponse'] === null) {
                    $attempts[$id]['firstresponse'] = [
                        'name' => (string)$row->responsename,
                        'value' => (string)$row->responsevalue,
                    ];
                }
            }
            $attemptrows->close();
            $byquestion = [];
            foreach ($attempts as $attempt) {
                $byquestion[$attempt['questionid']][] = $attempt;
            }
            $firstquizscore = [];
            foreach ($attempts as $attempt) {
                if ($attempt['quizattempt'] === 1) {
                    $firstquizscore[$attempt['userid']] = $attempt['sumgrades'];
                }
            }
            asort($firstquizscore, SORT_NUMERIC);
            $rankedusers = array_keys($firstquizscore);
            $band = max(1, (int)ceil(count($rankedusers) * 0.27));
            $bottomusers = array_fill_keys(array_slice($rankedusers, 0, $band), true);
            $topusers = array_fill_keys(array_slice($rankedusers, -$band), true);

            foreach ($questions as $question) {
                $qid = (int)$question->questionid;
                $optionrows = $answerrows[$qid] ?? [];
                $maxfraction = null;
                foreach ($optionrows as $option) {
                    $maxfraction = $maxfraction === null
                        ? (float)$option->fraction
                        : max($maxfraction, (float)$option->fraction);
                }
                $keys = [];
                $distribution = [];
                foreach ($optionrows as $option) {
                    $index = (int)$option->id;
                    $iskey = $maxfraction !== null && (float)$option->fraction === $maxfraction;
                    if ($iskey) {
                        $keys[$index] = true;
                    }
                    $distribution[$index] = [
                        'label' => format_text((string)$option->answer, FORMAT_MOODLE, ['filter' => false]),
                        'count' => 0,
                        'correct' => $iskey,
                    ];
                }
                $rowattempts = $byquestion[$qid] ?? [];
                $correctcount = 0;
                $firstcount = 0;
                $firstcorrect = 0;
                $top = [];
                $bottom = [];
                $weekly = [];
                foreach ($rowattempts as $attempt) {
                    $finalcorrect = $attempt['finalfraction'] !== null && $attempt['finalfraction'] >= 0.999999;
                    $correctcount += $finalcorrect ? 1 : 0;
                    $firstcorrectanswer = self::response_is_key($attempt['firstresponse'], $keys);
                    if ($firstcorrectanswer === null && $attempt['firstfraction'] !== null) {
                        $firstcorrectanswer = $attempt['firstfraction'] >= 0.999999;
                    }
                    if ($firstcorrectanswer !== null) {
                        $firstcount++;
                        $firstcorrect += $firstcorrectanswer ? 1 : 0;
                    }
                    if ($attempt['quizattempt'] === 1) {
                        if (isset($topusers[$attempt['userid']])) {
                            $top[] = $finalcorrect ? 1 : 0;
                        }
                        if (isset($bottomusers[$attempt['userid']])) {
                            $bottom[] = $finalcorrect ? 1 : 0;
                        }
                    }
                    $response = $attempt['firstresponse'];
                    if ($response !== null && $response['name'] === 'answer') {
                        foreach (self::response_ids((string)$response['value']) as $answerid) {
                            if (isset($distribution[$answerid])) {
                                $distribution[$answerid]['count']++;
                            }
                        }
                    }
                    if ($attempt['timefinish'] > 0) {
                        $week = date('o-W', $attempt['timefinish']);
                        $weekly[$week] ??= ['attempts' => 0, 'correct' => 0];
                        $weekly[$week]['attempts']++;
                        $weekly[$week]['correct'] += $finalcorrect ? 1 : 0;
                    }
                }
                $wrongoptionbeatskey = false;
                $keycount = 0;
                foreach ($distribution as $option) {
                    if ($option['correct']) {
                        $keycount += $option['count'];
                    }
                }
                foreach ($distribution as $option) {
                    if (!$option['correct'] && $option['count'] > $keycount) {
                        $wrongoptionbeatskey = true;
                    }
                }
                ksort($weekly);
                $weekly = array_slice($weekly, -8, null, true);
                foreach ($weekly as $week => &$trend) {
                    $trend['week'] = $week;
                    $trend['correctpercent'] = 100 * $trend['correct'] / max(1, $trend['attempts']);
                }
                unset($trend);
                $mapping = question_stats::lesson_mapping($courseid, (int)$DB->get_field(
                    'course_sections',
                    'id',
                    ['course' => $courseid, 'section' => $quiz->sectionnumber]
                ));
                $correctpercent = count($rowattempts) ? 100 * $correctcount / count($rowattempts) : null;
                $firstpercent = $firstcount ? 100 * $firstcorrect / $firstcount : null;
                $discrimination = $top && $bottom
                    ? array_sum($top) / count($top) - array_sum($bottom) / count($bottom)
                    : null;
                $verdict = self::verdict(
                    count($rowattempts),
                    $correctpercent,
                    $discrimination,
                    $wrongoptionbeatskey,
                    false
                );
                $result[] = [
                    'source' => 'quiz',
                    'questionid' => $qid,
                    'cmid' => (int)$quiz->cmid,
                    'sectionnumber' => (int)$quiz->sectionnumber,
                    'activity' => format_string((string)$quiz->name),
                    'question' => format_text((string)$question->questiontext, FORMAT_HTML, ['filter' => false]),
                    'questiontype' => (string)$question->qtype,
                    'correctoption' => null,
                    'options' => array_column($optionrows, 'answer'),
                    'reviewable' => self::is_reviewable_question(
                        $qid,
                        (string)$question->qtype
                    ) && count($keys) === 1,
                    'attempts' => count($rowattempts),
                    'correct' => $correctcount,
                    'firstattempts' => $firstcount,
                    'firstcorrect' => $firstcorrect,
                    'correctpercent' => $correctpercent,
                    'firstcorrectpercent' => $firstpercent,
                    'discrimination' => $discrimination,
                    'wrongoptionbeatskey' => $wrongoptionbeatskey,
                    'distribution' => $distribution,
                    'weekly' => $weekly,
                    'lesson' => $mapping['lesson'],
                    'outcomes' => $mapping['outcomes'],
                    'remedialpass' => null,
                    'verdict' => $verdict,
                ];
            }
        }
        $lowcounts = [];
        foreach ($result as $row) {
            if ($row['attempts'] >= 10 && $row['correctpercent'] !== null && $row['correctpercent'] < 50) {
                foreach (array_unique($row['outcomes']) as $outcome) {
                    $lowcounts[$outcome] = ($lowcounts[$outcome] ?? 0) + 1;
                }
            }
        }
        foreach ($result as &$row) {
            $learninggap = (bool)array_filter(
                $row['outcomes'],
                static fn(string $outcome): bool => ($lowcounts[$outcome] ?? 0) >= 2
            );
            $row['verdict'] = self::verdict(
                $row['attempts'],
                $row['correctpercent'],
                $row['discrimination'],
                $row['wrongoptionbeatskey'],
                $learninggap
            );
        }
        unset($row);
        return $result;
    }

    /**
     * Helper.
     *
     * Helper.
     *
     * Check a question belongs to a quiz across legacy and question-bank slot schemas.
     *
     * @param int $quizid
     * @param int $questionid
     * @return bool
     */
    public static function quiz_contains_question(int $quizid, int $questionid): bool {
        global $DB;
        $dbman = $DB->get_manager();
        $slots = new \xmldb_table('quiz_slots');
        if (!$dbman->table_exists($slots)) {
            return false;
        }
        if ($dbman->field_exists($slots, new \xmldb_field('questionid'))) {
            return $DB->record_exists('quiz_slots', [
                'quizid' => $quizid,
                'questionid' => $questionid,
            ]);
        }
        if (
            !$dbman->field_exists($slots, new \xmldb_field('questionbankentryid'))
            || !$dbman->field_exists($slots, new \xmldb_field('version'))
            || !$dbman->table_exists(new \xmldb_table('question_versions'))
        ) {
            return false;
        }
        return (bool)$DB->record_exists_sql(
            "SELECT 1
               FROM {quiz_slots} qs
               JOIN {question_versions} qv
                 ON qv.questionbankentryid = qs.questionbankentryid
                AND qv.version = qs.version
              WHERE qs.quizid = :quizid AND qv.questionid = :questionid",
            ['quizid' => $quizid, 'questionid' => $questionid]
        );
    }

    /**
     * Resolve the active question for either the legacy quiz_slots.questionid field
     * or the question-bank entry/version fields used by newer Moodle releases.
     *
     * @return string|null SQL join fragment.
     */
    private static function slot_question_join(): ?string {
        global $DB;
        $dbman = $DB->get_manager();
        $slots = new \xmldb_table('quiz_slots');
        if ($dbman->field_exists($slots, new \xmldb_field('questionid'))) {
            return 'JOIN {question} q ON q.id = qs.questionid';
        }
        if (
            $dbman->field_exists($slots, new \xmldb_field('questionbankentryid'))
            && $dbman->field_exists($slots, new \xmldb_field('version'))
            && $dbman->table_exists(new \xmldb_table('question_versions'))
        ) {
            return 'JOIN {question_versions} qv
                      ON qv.questionbankentryid = qs.questionbankentryid AND qv.version = qs.version
                    JOIN {question} q ON q.id = qv.questionid';
        }
        return null;
    }

    /**
     * Only allow automated review for true/false or explicitly single-answer choices.
     *
     * @param int $questionid
     * @param string $qtype
     * @return bool
     */
    public static function is_reviewable_question(int $questionid, string $qtype): bool {
        global $DB;
        if ($qtype === 'truefalse') {
            return true;
        }
        if ($qtype !== 'multichoice') {
            return false;
        }
        $table = new \xmldb_table('question_multichoice');
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists($table) || !$dbman->field_exists($table, new \xmldb_field('single'))) {
            return false;
        }
        return (int)$DB->get_field('question_multichoice', 'single', ['question' => $questionid]) === 1;
    }

    /**
     * Helper.
     *
     * @param array|null $response
     * @param array $keys
     * @return bool|null
     */
    private static function response_is_key(?array $response, array $keys): ?bool {
        if ($response === null || $response['name'] !== 'answer' || !$keys) {
            return null;
        }
        $selected = self::response_ids($response['value']);
        if (!$selected) {
            return false;
        }
        $keyids = array_keys($keys);
        sort($selected);
        sort($keyids);
        return $selected === $keyids;
    }

    /**
     * Helper.
     *
     * Helper.
     *
     * Moodle multichoice stores the selected answer ID, or a comma-separated set.
     *
     * @param string $value
     * @return int[]
     */
    private static function response_ids(string $value): array {
        $ids = [];
        foreach (preg_split('/[,;]/', $value) as $part) {
            if (ctype_digit(trim($part))) {
                $ids[] = (int)trim($part);
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * Helper.
     *
     * @param int $attempts
     * @param float|null $correct
     * @param float|null $discrimination
     * @param bool $wrongoption
     * @param bool $learninggap
     * @return string
     */
    private static function verdict(
        int $attempts,
        ?float $correct,
        ?float $discrimination,
        bool $wrongoption,
        bool $learninggap
    ): string {
        if ($attempts < question_stats::minimum_attempts()) {
            return 'Too few answers yet';
        }
        $correct ??= 0;
        if (
            ($correct < 50 && $discrimination !== null && $discrimination < 0.2)
            || $wrongoption
        ) {
            return 'Check the question';
        }
        if (($correct < 50 && $discrimination !== null && $discrimination >= 0.2) || $learninggap) {
            return 'Learning gap';
        }
        if ($correct > 90 && $discrimination !== null && $discrimination < 0.2) {
            return 'Too easy';
        }
        return 'Working well';
    }
}
