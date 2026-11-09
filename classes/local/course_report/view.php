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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Visual Course Report: class grid and the student report.
 *
 * @package    block_aiplugin_nav
 * @copyright  2026 LMS Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_aiplugin_nav\local\course_report;

use html_writer;
use moodle_url;

/**
 * Visual Course Report: class grid and the student report.
 */
final class view {
    /**
     * Due time for each lesson, from the AI Course Builder schedule when the course came from it.
     *
     * Lesson n is due at the end of the week it is taught in, counted from the course start date.
     *
     * @param \stdClass $course
     * @param array $lessons lessons from progress::for_course()
     * @return int[] due timestamps keyed by lesson index; empty when no schedule is known
     */
    public static function due_dates(\stdClass $course, array $lessons): array {
        global $DB;
        if (empty($course->startdate) || !$lessons) {
            return [];
        }
        $perweek = 0;
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('local_aicoursebuilder_unit')) {
            $unit = $DB->get_record('local_aicoursebuilder_unit', ['courseid' => $course->id], 'id, programid', IGNORE_MULTIPLE);
            if ($unit) {
                $delivery = json_decode((string)$DB->get_field(
                    'local_aicoursebuilder_program',
                    'deliveryjson',
                    ['id' => $unit->programid]
                ), true) ?: [];
                $perweek = max(0, (int)($delivery['sessionsPerWeek'] ?? 0));
                if (!empty($delivery['selfPaced'])) {
                    $perweek = 0;
                }
            }
        }
        if ($perweek < 1) {
            return [];
        }
        $due = [];
        foreach (array_values($lessons) as $index => $lesson) {
            $week = (int)ceil(($index + 1) / $perweek);
            $due[$index] = (int)$course->startdate + $week * WEEKSECS;
        }
        return $due;
    }

    /**
     * State of one lesson for one student.
     *
     * @param array $student
     * @param array $lesson
     * @param int|null $due
     * @return string done|overdue|inprogress|notdue|untracked
     */
    public static function lesson_state(array $student, array $lesson, ?int $due): string {
        $tracked = 0;
        $done = 0;
        foreach ($lesson['activities'] as $activity) {
            if (empty($activity->trackable)) {
                continue;
            }
            $tracked++;
            $done += !empty($student['activitystates'][(int)$activity->cmid]['complete']) ? 1 : 0;
        }
        if ($tracked === 0) {
            return 'untracked';
        }
        if ($done === $tracked) {
            return 'done';
        }
        if ($due !== null && time() > $due) {
            return 'overdue';
        }
        return $done > 0 ? 'inprogress' : 'notdue';
    }

    /**
     * Cell for the class grid.
     *
     * @param string $state
     * @param string $label
     * @return string
     */
    public static function cell(string $state, string $label): string {
        $symbols = ['done' => '✓', 'overdue' => '!', 'inprogress' => '◐', 'notdue' => '', 'untracked' => '–'];
        return html_writer::span(
            html_writer::span($symbols[$state] ?? '', 'cr-cell-symbol', ['aria-hidden' => 'true']) .
            html_writer::span($label, 'visually-hidden'),
            'cr-cell cr-cell-' . $state,
            ['title' => $label]
        );
    }

    /**
     * The teacher overview: tiles, legend and class grid.
     *
     * @param \stdClass $course
     * @param array $progress
     * @param array $questions
     * @return string
     */
    public static function overview(\stdClass $course, array $progress, array $questions): string {
        $str = static fn(string $k, $a = null): string => get_string($k, 'block_aiplugin_nav', $a);
        $lessons = array_values($progress['lessons']);
        $due = self::due_dates($course, $lessons);
        $rows = [];
        $overduecount = 0;
        $ontrack = 0;
        $scores = [];
        foreach ($progress['students'] as $student) {
            $states = [];
            foreach ($lessons as $index => $lesson) {
                $states[$index] = self::lesson_state($student, $lesson, $due[$index] ?? null);
            }
            $overdue = count(array_keys($states, 'overdue', true));
            $overduecount += $overdue;
            $ontrack += $overdue === 0 ? 1 : 0;
            $kc = $student['knowledgecheck'] ?? null;
            if ($kc) {
                $scores[] = (float)$kc['percent'];
            }
            $rows[] = ['student' => $student, 'states' => $states, 'overdue' => $overdue,
                'kc' => $kc ? (float)$kc['percent'] : null];
        }
        // Students needing help first: most overdue lessons, then lowest check score.
        usort($rows, static function (array $a, array $b): int {
            return [$b['overdue'], $a['kc'] ?? 101, $a['student']['name']] <=> [$a['overdue'], $b['kc'] ?? 101, $b['student']['name']];
        });
        $toreview = count(array_filter($questions, static fn(array $q): bool => $q['verdict'] === 'Check the question'));
        $gaps = count(array_filter($questions, static fn(array $q): bool => $q['verdict'] === 'Learning gap'));

        $out = html_writer::start_div('cr-tiles');
        $tile = static fn(string $value, string $label, string $tone = ''): string =>
            html_writer::div(
                html_writer::div($value, 'cr-tile-value') . html_writer::div($label, 'cr-tile-label'),
                'cr-tile' . ($tone ? ' cr-tile-' . $tone : '')
            );
        $out .= $tile((string)count($rows), $str('coursereport_students'));
        $out .= $tile((string)$ontrack, $str('cr_ontrack'), 'done');
        $out .= $tile((string)(count($rows) - $ontrack), $str('cr_behind'), 'overdue');
        $out .= $tile($scores ? round(array_sum($scores) / count($scores)) . '%' : '—', $str('cr_avgcheck'));
        $out .= $tile((string)$toreview, $str('coursereport_at_risk'), $toreview ? 'attention' : '');
        $out .= $tile((string)$gaps, $str('cr_gaps'), $gaps ? 'attention' : '');
        $out .= html_writer::end_div();

        if (!$rows) {
            return $out . html_writer::div($str('coursereport_no_students'), 'cr-empty');
        }

        $legend = '';
        foreach (['done', 'overdue', 'inprogress', 'notdue'] as $state) {
            $legend .= html_writer::span(self::cell($state, '') . $str('cr_state_' . $state), 'cr-legend-item');
        }
        $out .= html_writer::start_div('cr-card');
        $out .= html_writer::div(
            html_writer::tag('h3', $str('cr_classgrid'), ['class' => 'cr-card-title']) .
            html_writer::div($legend, 'cr-legend'),
            'cr-card-head'
        );
        $head = html_writer::tag('th', $str('coursereport_student'), ['class' => 'cr-col-student', 'scope' => 'col']);
        foreach ($lessons as $index => $lesson) {
            $num = preg_match('/^Lesson\s+(\d+)/i', $lesson['title'], $m) ? $m[1] : $index + 1;
            $title = trim(preg_replace('/^Lesson\s+\d+\s*[·:\-–]\s*/u', '', $lesson['title']));
            $duelabel = isset($due[$index]) ? $str('cr_due', userdate($due[$index], get_string('strftimedateshort', 'langconfig'))) : '';
            $head .= html_writer::tag(
                'th',
                html_writer::div($str('coursereport_lesson') . ' ' . $num, 'cr-lesson-num') .
                html_writer::div(s($title), 'cr-lesson-title') .
                ($duelabel ? html_writer::div($duelabel, 'cr-lesson-due') : ''),
                ['scope' => 'col', 'class' => 'cr-col-lesson']
            );
        }
        $head .= html_writer::tag('th', $str('cr_checkscore'), ['scope' => 'col', 'class' => 'cr-col-score']);
        $head .= html_writer::tag('th', '', ['scope' => 'col']);
        $body = '';
        foreach ($rows as $row) {
            $student = $row['student'];
            $url = new moodle_url('/blocks/aiplugin_nav/student_report.php', ['courseid' => $course->id,
                'studentid' => $student['id']]);
            $cells = html_writer::tag('th', html_writer::link($url, s($student['name'])), ['scope' => 'row', 'class' => 'cr-col-student']);
            foreach ($row['states'] as $index => $state) {
                $cells .= html_writer::tag('td', self::cell($state, $str('cr_state_' . $state)), ['class' => 'cr-col-lesson']);
            }
            $score = $row['kc'] === null ? html_writer::span('—', 'cr-muted')
                : html_writer::span(round($row['kc']) . '%', 'cr-score ' . ($row['kc'] >= 70 ? 'cr-score-good'
                    : ($row['kc'] >= 50 ? 'cr-score-mid' : 'cr-score-low')));
            $cells .= html_writer::tag('td', $score, ['class' => 'cr-col-score']);
            $cells .= html_writer::tag('td', html_writer::link($url, $str('cr_open')), ['class' => 'cr-col-open']);
            $body .= html_writer::tag('tr', $cells, ['class' => $row['overdue'] ? 'cr-row-help' : '']);
        }
        $out .= html_writer::tag(
            'table',
            html_writer::tag('thead', html_writer::tag('tr', $head)) . html_writer::tag('tbody', $body),
            ['class' => 'cr-grid']
        );
        $out .= html_writer::end_div();
        return $out;
    }

    /**
     * Learner and parent friendly report.
     *
     * @param \stdClass $course
     * @param \stdClass $user
     * @param array $student from progress::for_course()
     * @param array $lessons from progress::for_course()
     * @param bool $isteacher
     * @return string
     */
    public static function student(\stdClass $course, \stdClass $user, array $student, array $lessons, bool $isteacher): string {
        global $DB, $OUTPUT;
        $str = static fn(string $k, $a = null): string => get_string($k, 'block_aiplugin_nav', $a);
        $lessons = array_values($lessons);
        $due = self::due_dates($course, $lessons);
        $states = [];
        foreach ($lessons as $index => $lesson) {
            $states[$index] = self::lesson_state($student, $lesson, $due[$index] ?? null);
        }
        $done = count(array_keys($states, 'done', true));
        $overdue = count(array_keys($states, 'overdue', true));
        $total = count($lessons);
        $percent = $total ? (int)round($done / $total * 100) : 0;
        $kc = $student['knowledgecheck'] ?? null;
        $headline = $overdue ? $str($overdue === 1 ? 'cr_headline_behind_one' : 'cr_headline_behind', ['name' => $user->firstname, 'count' => $overdue])
            : ($done === $total && $total ? $str('cr_headline_done', $user->firstname) : $str('cr_headline_ontrack', $user->firstname));
        $tone = $overdue ? 'overdue' : 'done';

        $out = html_writer::start_div('cr-student');
        $out .= html_writer::start_div('cr-card cr-hero cr-hero-' . $tone);
        $out .= html_writer::div($OUTPUT->user_picture($user, ['size' => 64, 'link' => false]), 'cr-hero-pic');
        $out .= html_writer::start_div('cr-hero-text');
        $out .= html_writer::div(s(fullname($user)) . ' · ' . format_string($course->fullname), 'cr-hero-kicker');
        $out .= html_writer::tag('h3', $headline, ['class' => 'cr-hero-title']);
        $out .= html_writer::div(html_writer::div('', 'cr-bar-fill', ['style' => 'width:' . $percent . '%']), 'cr-bar', [
            'role' => 'progressbar', 'aria-valuenow' => $percent, 'aria-valuemin' => 0, 'aria-valuemax' => 100]);
        $out .= html_writer::div($str('cr_lessonsdone', ['done' => $done, 'total' => $total]), 'cr-hero-caption');
        $out .= html_writer::end_div();
        $out .= html_writer::end_div();

        // Tiles.
        $out .= html_writer::start_div('cr-tiles cr-tiles-4');
        $tile = static fn(string $value, string $label, string $tone = ''): string =>
            html_writer::div(
                html_writer::div($value, 'cr-tile-value') . html_writer::div($label, 'cr-tile-label'),
                'cr-tile' . ($tone ? ' cr-tile-' . $tone : '')
            );
        $out .= $tile($done . ' / ' . $total, $str('cr_lessons_complete'), 'done');
        $out .= $tile($kc ? round((float)$kc['percent']) . '%' : '—', $str('cr_checkscore'));
        $out .= $tile((string)(int)$student['completedthisweek'], $str('coursereport_this_week'));
        $out .= $tile((string)$overdue, $str('cr_overdue_lessons'), $overdue ? 'overdue' : '');
        $out .= html_writer::end_div();

        // Lesson by lesson.
        $squares = '';
        $next = null;
        foreach ($lessons as $index => $lesson) {
            $state = $states[$index];
            if ($next === null && !in_array($state, ['done', 'overdue'], true)) {
                $next = $index;
            }
            $num = preg_match('/^Lesson\s+(\d+)/i', $lesson['title'], $m) ? $m[1] : $index + 1;
            $title = trim(preg_replace('/^Lesson\s+\d+\s*[·:\-–]\s*/u', '', $lesson['title']));
            $squares .= html_writer::div(
                self::cell($state, $str('cr_state_' . $state)) .
                html_writer::div(
                    html_writer::div($str('coursereport_lesson') . ' ' . $num, 'cr-lesson-num') .
                    html_writer::div(s($title), 'cr-lesson-title') .
                    html_writer::div(
                        $str('cr_state_' . $state) . (isset($due[$index]) && $state !== 'done'
                        ? ' · ' . $str('cr_due', userdate($due[$index], get_string('strftimedateshort', 'langconfig'))) : ''),
                        'cr-lesson-due'
                    ),
                    'cr-lesson-text'
                ),
                'cr-lesson-row cr-lesson-' . $state
            );
        }

        // Doing well and focus, from Knowledge Check answers by question topic.
        [$strong, $focus] = self::topics_for($course->id, (int)$user->id);
        $list = static function (array $items, string $empty): string {
            if (!$items) {
                return html_writer::div($empty, 'cr-muted');
            }
            return html_writer::tag(
                'ul',
                implode('', array_map(static fn($t) => html_writer::tag('li', s($t)), $items)),
                ['class' => 'cr-list']
            );
        };
        $out .= html_writer::start_div('cr-two');
        $out .= html_writer::div(html_writer::tag('h3', $str('cr_lessonbylesson'), ['class' => 'cr-card-title']) . $squares, 'cr-card');
        $out .= html_writer::start_div('cr-stack');
        $out .= html_writer::div(html_writer::tag('h3', $str('cr_doingwell'), ['class' => 'cr-card-title cr-title-good']) .
            $list($strong, $str('cr_nothing_yet')), 'cr-card');
        $out .= html_writer::div(html_writer::tag('h3', $str('cr_focus'), ['class' => 'cr-card-title cr-title-focus']) .
            $list($focus, $str('cr_nothing_focus')), 'cr-card');
        if ($next !== null) {
            $title = trim(preg_replace('/^Lesson\s+\d+\s*[·:\-–]\s*/u', '', $lessons[$next]['title']));
            $out .= html_writer::div(html_writer::tag('h3', $str('cr_comingup'), ['class' => 'cr-card-title']) .
                html_writer::div(s($title), 'cr-next-title') .
                (isset($due[$next]) ? html_writer::div($str('cr_due', userdate($due[$next], get_string(
                    'strftimedaydate',
                    'langconfig'
                ))), 'cr-muted') : ''), 'cr-card');
        }
        $out .= html_writer::end_div();
        $out .= html_writer::end_div();

        if ($isteacher) {
            $out .= html_writer::div(
                html_writer::link(
                    new moodle_url('/blocks/aiplugin_nav/course_report.php', ['id' => $course->id]),
                    '← ' . $str('coursereport'),
                    ['class' => 'btn btn-outline-secondary']
                ) . ' ' .
                html_writer::link(
                    new moodle_url('/blocks/aiplugin_nav/guardian_links.php', ['courseid' => $course->id]),
                    $str('coursereport_manage_guardians'),
                    ['class' => 'btn btn-outline-primary']
                ),
                'cr-actions'
            );
        }
        $out .= html_writer::end_div();
        return $out;
    }

    /**
     * Topics the student answered well or poorly in Knowledge Checks.
     *
     * @param int $courseid
     * @param int $userid
     * @return array [strong topics, focus topics]
     */
    public static function topics_for(int $courseid, int $userid): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('aiknowledgecheck_attempts')) {
            return [[], []];
        }
        $attempts = $DB->get_records_sql(
            "SELECT a.id, a.answers, a.aiknowledgecheckid
               FROM {aiknowledgecheck_attempts} a
               JOIN {aiknowledgecheck} k ON k.id = a.aiknowledgecheckid
              WHERE k.course = :courseid AND a.userid = :userid AND a.status = 1
           ORDER BY a.timemodified ASC",
            ['courseid' => $courseid, 'userid' => $userid]
        );
        $latest = [];
        foreach ($attempts as $attempt) {
            $latest[(int)$attempt->aiknowledgecheckid] = $attempt;
        }
        $bytopic = [];
        foreach ($latest as $attempt) {
            $answers = json_decode((string)$attempt->answers, true) ?: [];
            if (!$answers) {
                continue;
            }
            $questions = $DB->get_records_list(
                'aiknowledgecheck_questions',
                'id',
                array_keys($answers),
                '',
                'id, questiontext, mappingtopic'
            );
            foreach ($answers as $qid => $answer) {
                $q = $questions[$qid] ?? null;
                if (!$q) {
                    continue;
                }
                $topic = trim((string)$q->mappingtopic) ?: shorten_text(strip_tags((string)$q->questiontext), 60);
                $bytopic[$topic][] = !empty($answer['iscorrect']) ? 1 : 0;
            }
        }
        $strong = [];
        $focus = [];
        foreach ($bytopic as $topic => $marks) {
            $rate = array_sum($marks) / count($marks);
            if ($rate >= 0.8) {
                $strong[] = $topic;
            } else if ($rate < 0.6) {
                $focus[] = $topic;
            }
        }
        return [array_slice($strong, 0, 4), array_slice($focus, 0, 4)];
    }
}
