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
 * Teacher Course Report: overview, questions, students and activities.
 *
 * @package    block_aiplugin_nav
 * @copyright 2026 LMS Labs
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Teacher-only course analytics.
 *
 * @package block_aiplugin_nav
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('id', PARAM_INT);
$tab = optional_param('tab', 'overview', PARAM_ALPHA);
if (!in_array($tab, ['overview', 'questions', 'students', 'activities'], true)) {
    throw new invalid_parameter_exception('Invalid Course Report tab');
}
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url(new moodle_url('/blocks/aiplugin_nav/course_report.php', ['id' => $courseid, 'tab' => $tab]));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('coursereport', 'block_aiplugin_nav'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo html_writer::start_div('cr-page');
echo html_writer::div(
    html_writer::tag('h2', get_string('coursereport', 'block_aiplugin_nav'), ['class' => 'cr-page-title']) .
    html_writer::span(get_string('cr_teacheronly', 'block_aiplugin_nav'), 'cr-badge'),
    'cr-page-head'
);

$tabs = [
    html_writer::link(
        new moodle_url('/blocks/aiplugin_nav/course_report.php', ['id' => $courseid, 'tab' => 'overview']),
        get_string('coursereport_overview', 'block_aiplugin_nav'),
        ['class' => 'nav-link' . ($tab === 'overview' ? ' active' : ''), 'aria-current' => $tab === 'overview' ? 'page' : null]
    ),
    html_writer::link(
        new moodle_url('/blocks/aiplugin_nav/course_report.php', ['id' => $courseid, 'tab' => 'questions']),
        get_string('coursereport_questions', 'block_aiplugin_nav'),
        ['class' => 'nav-link' . ($tab === 'questions' ? ' active' : ''), 'aria-current' => $tab === 'questions' ? 'page' : null]
    ),
    html_writer::link(
        new moodle_url('/blocks/aiplugin_nav/course_report.php', ['id' => $courseid, 'tab' => 'students']),
        get_string('coursereport_students', 'block_aiplugin_nav'),
        ['class' => 'nav-link' . ($tab === 'students' ? ' active' : ''), 'aria-current' => $tab === 'students' ? 'page' : null]
    ),
    html_writer::link(
        new moodle_url('/blocks/aiplugin_nav/course_report.php', ['id' => $courseid, 'tab' => 'activities']),
        get_string('coursereport_activities', 'block_aiplugin_nav'),
        ['class' => 'nav-link' . ($tab === 'activities' ? ' active' : ''), 'aria-current' => $tab === 'activities' ? 'page' : null]
    ),
];
echo html_writer::tag('nav', html_writer::tag('div', implode('', array_map(
    static fn(string $link): string => html_writer::tag('div', $link, ['class' => 'nav-item']),
    $tabs
)), ['class' => 'nav nav-tabs mb-3']), ['aria-label' => get_string('coursereport', 'block_aiplugin_nav')]);

$questions = \block_aiplugin_nav\local\course_report\question_stats::for_course($courseid);
$atrisk = count(array_filter($questions, static fn(array $question): bool => $question['verdict'] === 'Check the question'));
$attemptcount = array_sum(array_column($questions, 'attempts'));
$scored = array_values(array_filter($questions, static fn(array $question): bool => $question['firstcorrectpercent'] !== null));
$averagefirsttry = $scored
    ? array_sum(array_column($scored, 'firstcorrectpercent')) / count($scored)
    : null;

if ($tab === 'overview') {
    $progress = \block_aiplugin_nav\local\course_report\progress::for_course($courseid);
    echo \block_aiplugin_nav\local\course_report\view::overview($course, $progress, $questions);
}

if ($tab === 'questions') {
    echo $OUTPUT->notification(
        get_string('coursereport_question_data_notice', 'block_aiplugin_nav'),
        \core\output\notification::NOTIFY_INFO
    );
    $dbman = $DB->get_manager();
    $reviewtable = new xmldb_table('block_aiplugin_nav_qreview');
    $reviewed = [];
    if ($dbman->table_exists($reviewtable)) {
        foreach ($DB->get_records('block_aiplugin_nav_qreview', ['courseid' => $courseid]) as $record) {
            $reviewed[$record->cmid . ':' . $record->questionid] = $record;
        }
    }

    if (!$questions) {
        echo $OUTPUT->notification(get_string('coursereport_no_questions', 'block_aiplugin_nav'), 'info');
    } else {
        $table = new html_table();
        $table->attributes['class'] = 'generaltable table table-striped';
        $table->head = [
        get_string('coursereport_activity', 'block_aiplugin_nav'),
        get_string('coursereport_question', 'block_aiplugin_nav'),
        get_string('coursereport_attempts', 'block_aiplugin_nav'),
        get_string('coursereport_correct', 'block_aiplugin_nav'),
        get_string('coursereport_firsttry', 'block_aiplugin_nav'),
        get_string('coursereport_discrimination', 'block_aiplugin_nav'),
        get_string('coursereport_remedial', 'block_aiplugin_nav'),
        get_string('coursereport_outcome', 'block_aiplugin_nav'),
        get_string('coursereport_options', 'block_aiplugin_nav'),
        get_string('coursereport_trend', 'block_aiplugin_nav'),
        get_string('coursereport_verdict', 'block_aiplugin_nav'),
        get_string('actions'),
        ];
        $cards = [];
        $priority = ['Check the question' => 0, 'Learning gap' => 1, 'Working well' => 2, 'Too easy' => 3];
        usort($questions, static function (array $a, array $b) use ($priority): int {
            return [$priority[$a['verdict']] ?? 9, $a['correctpercent'] ?? 101] <=> [$priority[$b['verdict']] ?? 9, $b['correctpercent'] ?? 101];
        });
        foreach ($questions as $question) {
            $questionkey = $question['cmid'] . ':' . $question['questionid'];
            $review = $reviewed[$questionkey] ?? null;
            $correct = $question['correctpercent'] === null
            ? '—' : format_float($question['correctpercent'], 1) . '%';
            $first = $question['firstcorrectpercent'] === null
            ? '—' : format_float($question['firstcorrectpercent'], 1) . '%';
            $discrimination = $question['discrimination'] === null
            ? '—' : format_float($question['discrimination'], 2);
            $remedial = $question['remedialpass'] === null
            ? '—'
            : format_float($question['remedialpass'], 1) . '%';

            $distributionitems = [];
            foreach ($question['distribution'] as $option) {
                $distributionitems[] = html_writer::tag(
                    'li',
                    format_string($option['label'] !== '' ? $option['label'] : '—') . ': ' . (int)$option['count']
                );
            }
            $distribution = $question['questiontype'] === 'freetext'
            ? '—' : html_writer::tag('ul', implode('', $distributionitems), ['class' => 'mb-0']);
            $trenditems = [];
            foreach ($question['weekly'] as $trend) {
                $trenditems[] = html_writer::tag(
                    'li',
                    s($trend['week']) . ': ' . format_float($trend['correctpercent'], 1) . '%'
                );
            }
            $trend = $trenditems ? html_writer::tag('ul', implode('', $trenditems), ['class' => 'mb-0'])
            : get_string('coursereport_not_available', 'block_aiplugin_nav');
            $mapping = $question['lesson'];
            if (!empty($question['outcomes'])) {
                $mapping .= ($mapping === '' ? '' : html_writer::empty_tag('br'))
                . s(implode(', ', $question['outcomes']));
            }
            if ($mapping === '') {
                $mapping = '—';
            }

            $source = $question['source'] ?? 'knowledgecheck';
            $activityurl = $source === 'quiz'
            ? new moodle_url('/mod/quiz/view.php', ['id' => $question['cmid']])
            : new moodle_url('/mod/aiknowledgecheck/view.php', ['id' => $question['cmid']]);
            $actions = [
            html_writer::link(
                $activityurl,
                get_string('coursereport_open_activity', 'block_aiplugin_nav'),
                ['class' => 'btn btn-sm btn-outline-secondary']
            ),
                html_writer::link(
                    new moodle_url('/course/view.php', ['id' => $courseid], 'section-' . $question['sectionnumber']),
                    get_string('coursereport_reteach', 'block_aiplugin_nav'),
                    ['class' => 'btn btn-sm btn-outline-secondary']
                ),
            ];
            if ($review && !empty($review->fault)) {
                $fault = str_replace('_', ' ', (string)$review->fault);
                $actions[] = html_writer::div(
                    html_writer::tag('strong', get_string('coursereport_ai_fault', 'block_aiplugin_nav') . ': ')
                    . s($fault)
                    . (!empty($review->rationale) ? html_writer::tag('p', s((string)$review->rationale), ['class' => 'mb-1']) : ''),
                    'alert alert-info mt-2'
                );
                if (in_array($review->reviewstatus, ['suggested', 'accepted', 'edited'], true)) {
                    $form = html_writer::start_tag('form', [
                        'method' => 'post',
                        'action' => new moodle_url('/blocks/aiplugin_nav/course_report_review.php'),
                        'class' => 'mt-2',
                    ]);
                    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
                    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'cmid', 'value' => $question['cmid']]);
                    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'questionid',
                        'value' => $question['questionid']]);
                    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'source', 'value' => $source]);
                    $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
                    $form .= html_writer::tag(
                        'label',
                        get_string('coursereport_teacher_rewrite', 'block_aiplugin_nav'),
                        ['for' => 'rewrite-' . $question['cmid'] . '-' . $question['questionid']]
                    );
                    $form .= html_writer::tag('textarea', s((string)($review->teacheredit ?: $review->rewrite)), [
                        'id' => 'rewrite-' . $question['cmid'] . '-' . $question['questionid'],
                        'name' => 'teacheredit',
                        'rows' => 3,
                        'class' => 'form-control',
                    ]);
                    foreach (['accept', 'edit', 'dismiss'] as $decision) {
                            $form .= html_writer::tag('button', get_string('coursereport_review_' . $decision, 'block_aiplugin_nav'), [
                                'type' => 'submit',
                                'name' => 'reviewaction',
                                'value' => $decision,
                                'class' => 'btn btn-sm btn-outline-primary mt-1 mr-1',
                            ]);
                    }
                    $form .= html_writer::end_tag('form');
                    if ($source === 'knowledgecheck') {
                        $actions[] = html_writer::link(
                            new moodle_url('/mod/aiknowledgecheck/edit_question.php', [
                            'cmid' => $question['cmid'],
                            'questionid' => $question['questionid'],
                            ]),
                            get_string('coursereport_open_editor', 'block_aiplugin_nav'),
                            ['class' => 'btn btn-sm btn-primary mt-1']
                        );
                    } else {
                        $actions[] = html_writer::link(
                            new moodle_url('/question/bank/editquestion/question.php', [
                            'id' => $question['questionid'],
                            'cmid' => $question['cmid'],
                            ]),
                            get_string('coursereport_open_editor', 'block_aiplugin_nav'),
                            ['class' => 'btn btn-sm btn-primary mt-1']
                        );
                    }
                    $actions[] = $form;
                } else if ($review->reviewstatus === 'dismissed') {
                    $actions[] = html_writer::tag(
                        'span',
                        get_string('coursereport_review_dismissed', 'block_aiplugin_nav'),
                        ['class' => 'badge badge-secondary']
                    );
                }
            } else if (($question['verdict'] ?? '') === 'Check the question' && !empty($question['reviewable'])) {
                $form = html_writer::start_tag('form', [
                'method' => 'post',
                'action' => new moodle_url('/blocks/aiplugin_nav/course_report_question_review.php'),
                'class' => 'mt-2',
                ]);
                $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
                $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'cmid', 'value' => $question['cmid']]);
                $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'questionid', 'value' => $question['questionid']]);
                $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'source', 'value' => $source]);
                $form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
                $form .= html_writer::tag(
                    'button',
                    get_string('coursereport_generate_review', 'block_aiplugin_nav'),
                    ['type' => 'submit', 'class' => 'btn btn-sm btn-primary']
                );
                $form .= html_writer::end_tag('form');
                $actions[] = $form;
            }
            if ($review && !empty($review->reviewedby)) {
                $actions[] = html_writer::tag(
                    'span',
                    get_string('coursereport_reviewed', 'block_aiplugin_nav'),
                    ['class' => 'badge badge-success ml-2']
                );
            }
            $markform = html_writer::start_tag('form', [
            'method' => 'post',
            'action' => new moodle_url('/blocks/aiplugin_nav/course_report_review.php'),
            'class' => 'd-inline-block',
            ]);
            $markform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $courseid]);
            $markform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'cmid', 'value' => $question['cmid']]);
            $markform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'questionid', 'value' => $question['questionid']]);
            $markform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'source', 'value' => $source]);
            $markform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'reviewaction', 'value' => 'mark']);
            $markform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
            $markform .= html_writer::tag(
                'button',
                get_string('coursereport_mark_reviewed', 'block_aiplugin_nav'),
                ['type' => 'submit', 'class' => 'btn btn-sm btn-outline-secondary']
            );
            $markform .= html_writer::end_tag('form');
            $actions[] = $markform;

            $verdict = (string)($question['verdict'] ?? '');
            $tone = [
                'Check the question' => 'check', 'Learning gap' => 'gap', 'Working well' => 'good',
                'Too easy' => 'easy', 'Too few answers yet' => 'few', 'Not auto-scored' => 'few',
            ][$verdict] ?? 'few';
            $bars = '';
            if ($question['questiontype'] !== 'freetext') {
                $max = max(1, array_sum(array_column($question['distribution'], 'count')));
                foreach ($question['distribution'] as $index => $option) {
                    if (trim((string)$option['label']) === '') {
                        continue;
                    }
                    $iskey = isset($question['correctoption']) && (int)$question['correctoption'] === (int)$index;
                    $pct = (int)round((int)$option['count'] / $max * 100);
                    $bars .= html_writer::div(
                        html_writer::div(
                            html_writer::span($iskey ? '✓' : chr(65 + $index), 'crq-opt-key') .
                            html_writer::span(format_string($option['label']), 'crq-opt-label') .
                            html_writer::span((int)$option['count'], 'crq-opt-count'),
                            'crq-opt-text'
                        ) .
                        html_writer::div(html_writer::div('', 'crq-opt-fill', ['style' => 'width:' . $pct . '%']), 'crq-opt-bar'),
                        'crq-opt' . ($iskey ? ' is-key' : '')
                    );
                }
            }
            $stat = static fn(string $value, string $label): string =>
            html_writer::div(html_writer::div($value, 'crq-stat-value') . html_writer::div($label, 'crq-stat-label'), 'crq-stat');
            $trendtext = [];
            foreach ($question['weekly'] as $trendrow) {
                $trendtext[] = s($trendrow['week']) . ': ' . format_float($trendrow['correctpercent'], 0) . '%';
            }
            $cards[] = html_writer::div(
                html_writer::div(
                    html_writer::span(s($verdict), 'crq-verdict crq-verdict-' . $tone) .
                    html_writer::span(format_string($question['activity']) .
                    ($question['lesson'] !== '' ? ' · ' . $question['lesson'] : ''), 'crq-where'),
                    'crq-head'
                ) .
                html_writer::div($question['question'], 'crq-question', ['id' => 'q-' . $question['cmid'] . '-' . $question['questionid']]) .
                html_writer::div(
                    html_writer::div(
                        $stat($correct, get_string('coursereport_correct', 'block_aiplugin_nav')) .
                        $stat($first, get_string('coursereport_firsttry', 'block_aiplugin_nav')) .
                        $stat((string)(int)$question['attempts'], get_string('coursereport_attempts', 'block_aiplugin_nav')) .
                        $stat($discrimination, get_string('coursereport_discrimination', 'block_aiplugin_nav')) .
                        $stat($remedial, get_string('coursereport_remedial', 'block_aiplugin_nav')),
                        'crq-stats'
                    ) .
                    ($bars ? html_writer::div(
                        html_writer::div(get_string('coursereport_options', 'block_aiplugin_nav'), 'crq-sub') . $bars,
                        'crq-opts'
                    ) : ''),
                    'crq-body'
                ) .
                ($trendtext ? html_writer::div(
                    get_string('coursereport_trend', 'block_aiplugin_nav') . ': ' . implode(' · ', $trendtext),
                    'crq-trend'
                ) : '') .
                html_writer::div(implode(' ', $actions), 'crq-actions'),
                'crq-card crq-card-' . $tone
            );
        }
        echo html_writer::div(implode('', $cards), 'crq-list');
    }
}

if ($tab === 'students') {
    $progress = \block_aiplugin_nav\local\course_report\progress::for_course($courseid);
    $summary = $progress['summary'];
    echo html_writer::div(
        html_writer::link(
            new moodle_url('/blocks/aiplugin_nav/guardian_links.php', ['courseid' => $courseid]),
            get_string('coursereport_manage_guardians', 'block_aiplugin_nav'),
            ['class' => 'btn btn-outline-primary mb-3']
        )
    );
    echo $OUTPUT->notification(
        get_string('coursereport_completion_notice', 'block_aiplugin_nav'),
        \core\output\notification::NOTIFY_INFO
    );
    $table = new html_table();
    $table->attributes['class'] = 'generaltable table table-striped';
    $table->head = [
        get_string('coursereport_student', 'block_aiplugin_nav'),
        get_string('coursereport_progress', 'block_aiplugin_nav'),
        get_string('coursereport_latest_knowledgechecks', 'block_aiplugin_nav'),
        get_string('coursereport_completed', 'block_aiplugin_nav'),
        get_string('coursereport_this_week', 'block_aiplugin_nav'),
        get_string('coursereport_status', 'block_aiplugin_nav'),
        get_string('actions'),
    ];
    foreach ($progress['students'] as $student) {
        $statusclass = $student['status'] === 'complete' ? 'badge badge-success'
            : ($student['status'] === 'inprogress' ? 'badge badge-warning' : 'badge badge-secondary');
        $table->data[] = [
            s($student['name']),
            $student['percent'] === null ? get_string('coursereport_not_tracked', 'block_aiplugin_nav') : $student['percent'] . '%',
            $student['knowledgecheck']
                ? $student['knowledgecheck']['correct'] . '/' . $student['knowledgecheck']['total']
                    . ' (' . format_float((float)$student['knowledgecheck']['percent'], 0) . '%)'
                : get_string('coursereport_no_knowledgechecks', 'block_aiplugin_nav'),
            $student['completed'] . ' / ' . $student['trackable'],
            (int)$student['completedthisweek'],
            html_writer::tag(
                'span',
                get_string('coursereport_status_' . $student['status'], 'block_aiplugin_nav'),
                ['class' => $statusclass]
            ),
            html_writer::link(
                new moodle_url('/blocks/aiplugin_nav/student_report.php', [
                    'courseid' => $courseid,
                    'studentid' => $student['id'],
                ]),
                get_string('coursereport_open_student', 'block_aiplugin_nav')
            ),
        ];
    }
    if (!$table->data) {
        echo $OUTPUT->notification(get_string('coursereport_no_students', 'block_aiplugin_nav'), 'info');
    } else {
        echo html_writer::table($table);
    }
}

if ($tab === 'activities') {
    $diagnostics = \block_aiplugin_nav\local\course_report\learning_activity_diagnostics::for_course($courseid);
    $sections = [
        'wrongitems' => get_string('coursereport_wrong_items', 'block_aiplugin_nav'),
        'abandoned' => get_string('coursereport_abandoned', 'block_aiplugin_nav'),
        'longsessions' => get_string('coursereport_long_sessions', 'block_aiplugin_nav'),
        'worstendings' => get_string('coursereport_worst_endings', 'block_aiplugin_nav'),
    ];
    foreach ($sections as $key => $title) {
        echo $OUTPUT->heading($title, 3);
        if (empty($diagnostics[$key])) {
            echo $OUTPUT->notification(get_string('coursereport_no_activity_findings', 'block_aiplugin_nav'), 'info');
            continue;
        }
        $table = new html_table();
        $table->attributes['class'] = 'generaltable table table-striped';
        $table->head = [
            get_string('coursereport_activity', 'block_aiplugin_nav'),
            get_string('coursereport_lesson', 'block_aiplugin_nav'),
            get_string('coursereport_evidence', 'block_aiplugin_nav'),
            get_string('actions'),
        ];
        foreach ($diagnostics[$key] as $item) {
            if ($key === 'wrongitems') {
                $evidence = s($item['title']) . ': ' . $item['wrong'] . ' / ' . $item['responses']
                    . ' wrong (' . $item['wrongpercent'] . '%)';
            } else if ($key === 'longsessions') {
                $evidence = get_string('coursereport_long_session_detail', 'block_aiplugin_nav', [
                    'average' => $item['averageminutes'],
                    'expected' => $item['expectedminutes'],
                    'count' => $item['completedattempts'],
                ]);
            } else if ($key === 'worstendings') {
                $evidence = s($item['decision'] . ' → ' . $item['choice'])
                    . ' · ' . (int)$item['count'];
            } else {
                $evidence = (int)$item['count'];
            }
            $table->data[] = [
                s($item['activity']),
                s($item['section']),
                $evidence,
                html_writer::link(
                    new moodle_url('/mod/' . $item['component'] . '/view.php', ['id' => $item['cmid']]),
                    get_string('coursereport_open_activity', 'block_aiplugin_nav')
                ),
            ];
        }
        echo html_writer::table($table);
    }
}

echo html_writer::end_div();
echo $OUTPUT->footer();
