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
 * Question statistics and verdicts for Knowledge Check and Moodle quiz.
 *
 * @copyright 2026 LMS Labs
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_aiplugin_nav\local\course_report;

defined('MOODLE_INTERNAL') || die();

/**
 * Read-only Knowledge Check question statistics for teachers.
 *
 * @package block_aiplugin_nav
 */
final class question_stats {
    /**
     * Return every question in a course's Knowledge Check activities with
     * attempt, response-distribution and item-discrimination metrics.
     *
     * @param int $courseid
     * @return array
     */
    public static function for_course(int $courseid): array {
        global $DB;
        $dbman = $DB->get_manager();
        foreach (['aiknowledgecheck', 'aiknowledgecheck_questions', 'aiknowledgecheck_attempts'] as $tablename) {
            if (!$dbman->table_exists(new \xmldb_table($tablename))) {
                return moodle_quiz_stats::for_course($courseid);
            }
        }
        $module = $DB->get_record('modules', ['name' => 'aiknowledgecheck'], 'id');
        if (!$module) {
            return moodle_quiz_stats::for_course($courseid);
        }
        $kctable = new \xmldb_table('aiknowledgecheck');
        $surveymodefield = new \xmldb_field('surveymode');
        $surveymodecolumn = $dbman->field_exists($kctable, $surveymodefield) ? 'kc.surveymode' : '0';
        $activities = $DB->get_records_sql(
            "SELECT cm.id AS cmid, cm.section, cs.section AS sectionnumber,
                    cm.instance AS instanceid, kc.name, {$surveymodecolumn} AS surveymode
               FROM {course_modules} cm
               JOIN {course_sections} cs ON cs.id = cm.section
               JOIN {aiknowledgecheck} kc ON kc.id = cm.instance
              WHERE cm.course = :courseid AND cm.module = :moduleid
           ORDER BY cm.id ASC",
            ['courseid' => $courseid, 'moduleid' => $module->id]
        );

        $rows = [];
        foreach ($activities as $activity) {
            $questions = $DB->get_records(
                'aiknowledgecheck_questions',
                ['aiknowledgecheckid' => $activity->instanceid],
                'questionnumber ASC, id ASC'
            );
            $attempts = $DB->get_records_sql(
                "SELECT id, userid, answers, correctcount, totalcount, timestarted, timeended
                   FROM {aiknowledgecheck_attempts}
                  WHERE aiknowledgecheckid = :kcid AND status = 1
               ORDER BY userid ASC, timestarted ASC, id ASC",
                ['kcid' => $activity->instanceid]
            );
            $firstbyuser = [];
            $questiondata = [];
            foreach ($questions as $question) {
                $questiondata[$question->id] = [
                    'source' => 'knowledgecheck',
                    'questionid' => (int)$question->id,
                    'cmid' => (int)$activity->cmid,
                    'sectionnumber' => (int)$activity->sectionnumber,
                    'activity' => (string)$activity->name,
                    'question' => format_text($question->questiontext, FORMAT_HTML, ['filter' => false]),
                    'questiontype' => !empty($activity->surveymode) ? 'survey' : ($question->questiontype ?? 'scale'),
                    'reviewable' => empty($activity->surveymode)
                        && ($question->questiontype ?? 'scale') !== 'freetext',
                    'correctoption' => (int)$question->correctanswer,
                    'options' => [
                        (string)($question->answer1 ?? ''),
                        (string)($question->answer2 ?? ''),
                        (string)($question->answer3 ?? ''),
                        (string)($question->answer4 ?? ''),
                        (string)($question->answer5 ?? ''),
                    ],
                    'attempts' => 0,
                    'correct' => 0,
                    'firstattempts' => 0,
                    'firstcorrect' => 0,
                    'distribution' => [],
                    'weekly' => [],
                    'firstresponses' => [],
                    'topcorrect' => [],
                    'bottomcorrect' => [],
                    'lesson' => '',
                    'outcomes' => [],
                    'remedialpass' => null,
                ];
                foreach ($questiondata[$question->id]['options'] as $index => $option) {
                    $questiondata[$question->id]['distribution'][$index] = [
                        'label' => $option,
                        'count' => 0,
                    ];
                }
            }

            foreach ($attempts as $attempt) {
                $answers = json_decode((string)$attempt->answers, true);
                if (!is_array($answers)) {
                    continue;
                }
                if (!isset($firstbyuser[$attempt->userid])) {
                    $firstbyuser[$attempt->userid] = [
                        'score' => (int)$attempt->totalcount > 0
                            ? (int)$attempt->correctcount / (int)$attempt->totalcount
                            : 0.0,
                        'attemptid' => (int)$attempt->id,
                    ];
                }
                foreach ($questiondata as $questionid => &$row) {
                    if (!array_key_exists($questionid, $answers)) {
                        continue;
                    }
                    $answer = $answers[$questionid];
                    $selected = is_array($answer) ? ($answer['answer'] ?? null) : $answer;
                    if ($row['questiontype'] === 'freetext') {
                        if (is_array($answer) && !empty($answer['freetext'])) {
                            $row['attempts']++;
                        }
                        continue;
                    }
                    if (!is_numeric($selected)) {
                        continue;
                    }
                    $selected = (int)$selected;
                    if ($row['questiontype'] === 'survey') {
                        $row['attempts']++;
                        if (isset($row['distribution'][$selected])) {
                            $row['distribution'][$selected]['count']++;
                        }
                        continue;
                    }
                    // AI Knowledge Check stores both answer indices and correctanswer as zero-based.
                    $keyed = $row['correctoption'];
                    $correct = $selected === $keyed;
                    $row['attempts']++;
                    $row['correct'] += $correct ? 1 : 0;
                    if (isset($row['distribution'][$selected])) {
                        $row['distribution'][$selected]['count']++;
                    }
                    if (!array_key_exists($attempt->userid, $row['firstresponses'])) {
                        $row['firstresponses'][$attempt->userid] = $correct;
                        $row['firstattempts']++;
                        $row['firstcorrect'] += $correct ? 1 : 0;
                    }
                    $week = $attempt->timeended ? date('o-W', (int)$attempt->timeended) : '';
                    if ($week !== '') {
                        if (!isset($row['weekly'][$week])) {
                            $row['weekly'][$week] = ['attempts' => 0, 'correct' => 0];
                        }
                        $row['weekly'][$week]['attempts']++;
                        $row['weekly'][$week]['correct'] += $correct ? 1 : 0;
                    }
                }
                unset($row);
            }

            if (count($firstbyuser) >= 4) {
                uasort($firstbyuser, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
                $rankedusers = array_keys($firstbyuser);
                $band = max(1, (int)ceil(count($rankedusers) * 0.27));
                $topusers = array_fill_keys(array_slice($rankedusers, 0, $band), true);
                $bottomusers = array_fill_keys(array_slice($rankedusers, -$band), true);
                foreach ($questiondata as &$row) {
                    foreach ($row['firstresponses'] as $userid => $correct) {
                        if (isset($topusers[$userid])) {
                            $row['topcorrect'][] = $correct ? 1 : 0;
                        }
                        if (isset($bottomusers[$userid])) {
                            $row['bottomcorrect'][] = $correct ? 1 : 0;
                        }
                    }
                }
                unset($row);
            }

            $remedialrates = self::remedial_pass_rates((int)$activity->instanceid);
            foreach ($questiondata as &$row) {
                if (in_array($row['questiontype'], ['freetext', 'survey'], true)) {
                    $row['correctpercent'] = null;
                    $row['firstcorrectpercent'] = null;
                    $row['discrimination'] = null;
                    $row['wrongoptionbeatskey'] = false;
                    $row['verdict'] = 'Not auto-scored';
                    $mapping = self::lesson_mapping($courseid, (int)$activity->section);
                    $row['lesson'] = $mapping['lesson'];
                    $row['outcomes'] = $mapping['outcomes'];
                    $row['remedialpass'] = null;
                    $rows[] = $row;
                    continue;
                }
                $row['correctpercent'] = $row['attempts'] ? 100 * $row['correct'] / $row['attempts'] : null;
                $row['firstcorrectpercent'] = $row['firstattempts']
                    ? 100 * $row['firstcorrect'] / $row['firstattempts'] : null;
                $row['discrimination'] = $row['topcorrect'] && $row['bottomcorrect']
                    ? (array_sum($row['topcorrect']) / count($row['topcorrect']))
                        - (array_sum($row['bottomcorrect']) / count($row['bottomcorrect']))
                    : null;
                $row['wrongoptionbeatskey'] = false;
                $keyedcount = $row['distribution'][$row['correctoption']]['count'] ?? 0;
                foreach ($row['distribution'] as $index => $option) {
                    if ($index !== $row['correctoption'] && $option['count'] > $keyedcount) {
                        $row['wrongoptionbeatskey'] = true;
                    }
                }
                ksort($row['weekly']);
                $row['weekly'] = array_slice($row['weekly'], -8, null, true);
                foreach ($row['weekly'] as $week => &$trend) {
                    $trend['week'] = $week;
                    $trend['correctpercent'] = 100 * $trend['correct'] / max(1, $trend['attempts']);
                }
                unset($trend);
                $mapping = self::lesson_mapping($courseid, (int)$activity->section);
                $row['lesson'] = $mapping['lesson'];
                $row['outcomes'] = $mapping['outcomes'];
                $row['remedialpass'] = $remedialrates[$row['questionid']] ?? null;
                $rows[] = $row;
            }
            unset($row);
        }

        array_push($rows, ...moodle_quiz_stats::for_course($courseid));
        $minimumattempts = self::minimum_attempts();
        $lowcounts = [];
        foreach ($rows as $row) {
            if (
                $row['attempts'] >= $minimumattempts
                && $row['correctpercent'] !== null
                && $row['correctpercent'] < 50
            ) {
                foreach (array_unique($row['outcomes']) as $outcome) {
                    $lowcounts[$outcome] = ($lowcounts[$outcome] ?? 0) + 1;
                }
            }
        }
        foreach ($rows as &$row) {
            $lowoutcome = (bool)array_filter(
                $row['outcomes'],
                static fn(string $outcome): bool => ($lowcounts[$outcome] ?? 0) >= 2
            );
            $row['verdict'] = self::verdict($row, $lowoutcome);
        }
        unset($row);
        usort($rows, static function (array $a, array $b): int {
            if ($a['correctpercent'] === null) {
                return $b['correctpercent'] === null ? 0 : 1;
            }
            if ($b['correctpercent'] === null) {
                return -1;
            }
            return $a['correctpercent'] <=> $b['correctpercent'];
        });
        return $rows;
    }

    /**
     * Apply the approved question verdict order.
     *
     * @param array $row
     * @param bool $learninggap
     * @return string
     */
    private static function verdict(array $row, bool $learninggap): string {
        if (($row['attempts'] ?? 0) < self::minimum_attempts()) {
            return 'Too few answers yet';
        }
        $correct = (float)($row['correctpercent'] ?? 0);
        $discrimination = $row['discrimination'];
        $remedial = $row['remedialpass'];
        if (
            ($correct < 50 && $discrimination !== null && $discrimination < 0.2)
            || !empty($row['wrongoptionbeatskey'])
            || ($correct < 50 && $remedial !== null && $remedial > 80)
        ) {
            return 'Check the question';
        }
        // An outcome-wide gap only marks questions that most learners also miss.
        if (($correct < 50 && $discrimination !== null && $discrimination >= 0.2) || ($learninggap && $correct < 70)) {
            return 'Learning gap';
        }
        if ($correct > 90 && $discrimination !== null && $discrimination < 0.2) {
            return 'Too easy';
        }
        return 'Working well';
    }

    /**
     * Minimum response count before the report assigns a question verdict.
     *
     * The release default remains ten; isolated acceptance sites may lower it
     * temporarily through the site-admin setting.
     *
     * @return int
     */
    public static function minimum_attempts(): int {
        $configured = get_config('block_aiplugin_nav', 'question_min_attempts');
        if ($configured === false || $configured === null || $configured === '') {
            return 10;
        }
        return max(1, min(1000, (int)$configured));
    }

    /**
     * Match a Knowledge Check section to its AI Course Builder lesson/outcomes.
     *
     * @param int $courseid
     * @param int $sectionid
     * @return array
     */
    public static function lesson_mapping(int $courseid, int $sectionid): array {
        global $DB;
        $dbman = $DB->get_manager();
        foreach (['local_aicoursebuilder_lesson', 'local_aicoursebuilder_unit'] as $tablename) {
            if (!$dbman->table_exists(new \xmldb_table($tablename))) {
                return ['lesson' => '', 'outcomes' => []];
            }
        }
        $lesson = $DB->get_record_sql(
            "SELECT l.id, l.title, l.outcomesjson, l.programid
               FROM {local_aicoursebuilder_lesson} l
               JOIN {local_aicoursebuilder_unit} u ON u.id = l.unitid
              WHERE u.courseid = :courseid AND l.sectionid = :sectionid",
            ['courseid' => $courseid, 'sectionid' => $sectionid]
        );
        if (!$lesson) {
            return ['lesson' => '', 'outcomes' => []];
        }
        $identifiers = json_decode((string)$lesson->outcomesjson, true);
        $identifiers = is_array($identifiers) ? $identifiers : [];
        $outcomes = [];
        if ($dbman->table_exists(new \xmldb_table('local_aicoursebuilder_outcome'))) {
            foreach ($DB->get_records('local_aicoursebuilder_outcome', ['programid' => $lesson->programid]) as $outcome) {
                $identifier = $outcome->code ?: $outcome->outcometext;
                if (in_array($identifier, $identifiers, true)) {
                    $outcomes[] = format_string($outcome->code ?: $outcome->outcometext);
                }
            }
        }
        return ['lesson' => format_string($lesson->title), 'outcomes' => $outcomes ?: $identifiers];
    }

    /**
     * Return the share of linked remedial checks answered correctly.
     *
     * @param int $kcid
     * @param int $questionid
     * @return float|null
     */
    private static function remedial_pass_rates(int $kcid): array {
        global $DB;
        $dbman = $DB->get_manager();
        foreach (['local_aiqr_job', 'local_aiqr_module', 'local_aiqr_completion'] as $tablename) {
            if (!$dbman->table_exists(new \xmldb_table($tablename))) {
                return [];
            }
        }
        $completiontable = new \xmldb_table('local_aiqr_completion');
        $correctfield = new \xmldb_field('checkcorrect');
        if (!$dbman->field_exists($completiontable, $correctfield)) {
            return [];
        }
        $counts = $DB->get_records_sql(
            "SELECT j.questionid, COUNT(c.id) AS total, SUM(c.checkcorrect) AS correct
               FROM {local_aiqr_job} j
               JOIN {local_aiqr_module} m ON m.jobid = j.id
               JOIN {local_aiqr_completion} c ON c.moduleid = m.id
              WHERE j.sourcetype = :sourcetype
                AND j.kcid = :kcid
                AND c.checkcorrect IS NOT NULL
           GROUP BY j.questionid",
            ['sourcetype' => 'knowledgecheck', 'kcid' => $kcid]
        );
        $rates = [];
        foreach ($counts as $count) {
            if ((int)$count->total > 0) {
                $rates[(int)$count->questionid] = 100 * (int)$count->correct / (int)$count->total;
            }
        }
        return $rates;
    }
}
