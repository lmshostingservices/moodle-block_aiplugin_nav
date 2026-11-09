<?php
/**
 * @copyright 2026 LMS Labs
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Generate and approve a privacy-minimised guardian progress summary.
 *
 * @package block_aiplugin_nav
 */

require_once(__DIR__ . '/../../config.php');

$linkid = required_param('linkid', PARAM_INT);
$link = $DB->get_record('block_aiplugin_nav_guardlink', ['id' => $linkid], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $link->courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance((int)$course->id);
require_capability('moodle/course:manageactivities', $context);

$guardian = $DB->get_record('block_aiplugin_nav_guardian', ['id' => $link->guardianid], '*', MUST_EXIST);
$student = $DB->get_record('user', ['id' => $link->studentid, 'deleted' => 0, 'suspended' => 0], '*', MUST_EXIST);
if ($link->status !== 'active' || empty($link->consentedat) || empty($link->confirmedat)
    || !is_enrolled($context, $student, '', true)) {
    throw new moodle_exception('guardian_summary_requires_confirmation', 'block_aiplugin_nav');
}

$returnurl = new moodle_url('/blocks/aiplugin_nav/guardian_summary.php', ['linkid' => $linkid]);
$PAGE->set_url($returnurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('guardian_summary', 'block_aiplugin_nav'));
$PAGE->set_heading(format_string($course->fullname));

$report = \block_aiplugin_nav\local\course_report\progress::for_student((int)$course->id, (int)$student->id);
if (!$report) {
    throw new moodle_exception('guardian_summary_progress_unavailable', 'block_aiplugin_nav');
}
$summaryprogress = ['report' => $report];
$metrics = \block_aiplugin_nav\local\course_report\guardian_reports::summary_metrics($course, $summaryprogress);
$summaryhash = \block_aiplugin_nav\local\course_report\guardian_reports::summary_hash($metrics);
$cansummarize = $metrics['progressPercent'] !== null;
$action = optional_param('action', '', PARAM_ALPHA);
$draft = (string)($link->weeklysummary ?? '');
$notice = '';

if ($action !== '') {
    require_sesskey();
    if (!$cansummarize) {
        // Leave the page read-only when there is no measurable progress.
    } else if ($action === 'generate') {
        try {
            $draft = \block_aiplugin_nav\local\course_report\guardian_reports::generate_summary(
                $course,
                $summaryprogress
            );
        } catch (\Throwable $e) {
            $draft = '';
            $notice = get_string('guardian_summary_unavailable', 'block_aiplugin_nav');
        }
    } else if ($action === 'save') {
        $draft = trim(required_param('summary', PARAM_TEXT));
        if ($draft === '' || core_text::strlen($draft) > 900) {
            throw new invalid_parameter_exception('Enter a parent summary of 1 to 900 characters.');
        }
        $link->weeklysummary = $draft;
        $link->summaryhash = $summaryhash;
        $link->summaryupdatedby = (int)$USER->id;
        $link->summaryupdatedat = time();
        $link->timemodified = time();
        $DB->update_record('block_aiplugin_nav_guardlink', $link);
        redirect($returnurl, get_string('guardian_summary_saved', 'block_aiplugin_nav'),
            null, \core\output\notification::NOTIFY_SUCCESS);
    } else if ($action === 'clear') {
        $link->weeklysummary = null;
        $link->summaryhash = null;
        $link->summaryupdatedby = null;
        $link->summaryupdatedat = 0;
        $link->timemodified = time();
        $DB->update_record('block_aiplugin_nav_guardlink', $link);
        redirect($returnurl, get_string('guardian_summary_cleared', 'block_aiplugin_nav'),
            null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        throw new invalid_parameter_exception('Invalid parent-summary action.');
    }
}

$savedsummary = trim((string)($link->weeklysummary ?? ''));
$savediscurrent = $savedsummary !== '' && !empty($link->summaryhash)
    && hash_equals($summaryhash, (string)$link->summaryhash);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('guardian_summary', 'block_aiplugin_nav'));
echo $OUTPUT->notification(
    get_string('guardian_summary_privacy', 'block_aiplugin_nav'),
    \core\output\notification::NOTIFY_INFO
);
echo $OUTPUT->notification(
    s(trim($guardian->firstname . ' ' . $guardian->lastname) . ' · ' . fullname($student)
        . ' · ' . format_string($course->fullname)),
    \core\output\notification::NOTIFY_INFO
);
if ($notice !== '') {
    echo $OUTPUT->notification($notice, \core\output\notification::NOTIFY_ERROR);
}
if (!$cansummarize) {
    echo $OUTPUT->notification(
        get_string('guardian_summary_progress_unavailable', 'block_aiplugin_nav'),
        \core\output\notification::NOTIFY_WARNING
    );
    echo html_writer::link(
        new moodle_url('/blocks/aiplugin_nav/guardian_links.php', ['courseid' => $course->id]),
        get_string('back')
    );
    echo $OUTPUT->footer();
    exit;
}
if ($savedsummary !== '' && !$savediscurrent) {
    echo $OUTPUT->notification(
        get_string('guardian_summary_stale', 'block_aiplugin_nav'),
        \core\output\notification::NOTIFY_WARNING
    );
}
if ($savedsummary === '') {
    echo $OUTPUT->notification(
        get_string('guardian_summary_none', 'block_aiplugin_nav'),
        \core\output\notification::NOTIFY_INFO
    );
}

$displaydraft = $action === 'generate' ? $draft : $savedsummary;
$generateform = html_writer::start_tag('form', ['method' => 'post', 'action' => $returnurl, 'class' => 'mb-3']);
$generateform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
$generateform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'generate']);
$generateform .= html_writer::tag('button', get_string('guardian_summary_generate', 'block_aiplugin_nav'), [
    'type' => 'submit',
    'class' => 'btn btn-outline-primary',
]);
$generateform .= html_writer::end_tag('form');
echo $generateform;

$saveform = html_writer::start_tag('form', ['method' => 'post', 'action' => $returnurl, 'class' => 'card card-body']);
$saveform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
$saveform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'save']);
$saveform .= html_writer::tag('label', get_string('guardian_summary_label', 'block_aiplugin_nav'), [
    'for' => 'summary',
]);
$saveform .= html_writer::tag('textarea', s($displaydraft), [
    'id' => 'summary',
    'name' => 'summary',
    'rows' => 5,
    'maxlength' => 900,
    'class' => 'form-control',
    'required' => 'required',
]);
$saveform .= html_writer::tag('button', get_string('guardian_summary_save', 'block_aiplugin_nav'), [
    'type' => 'submit',
    'class' => 'btn btn-primary mt-3',
]);
$saveform .= html_writer::end_tag('form');
echo $saveform;

if ($savedsummary !== '') {
    $clearform = html_writer::start_tag('form', ['method' => 'post', 'action' => $returnurl, 'class' => 'mt-2']);
    $clearform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $clearform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'clear']);
    $clearform .= html_writer::tag('button', get_string('guardian_summary_clear', 'block_aiplugin_nav'), [
        'type' => 'submit',
        'class' => 'btn btn-outline-secondary',
    ]);
    $clearform .= html_writer::end_tag('form');
    echo $clearform;
}

echo html_writer::link(
    new moodle_url('/blocks/aiplugin_nav/guardian_links.php', ['courseid' => $course->id]),
    get_string('back')
);
echo $OUTPUT->footer();
