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
 * Signed, short-lived guardian progress report. No question-level analytics are exposed.
 *
 * @package block_aiplugin_nav
 */

// pipeline-no-login: Short-lived signed guardian token is validated below.
require_once(__DIR__ . '/../../config.php');

$token = required_param('token', PARAM_ALPHANUMEXT);
$details = \block_aiplugin_nav\local\course_report\guardian_reports::lookup_token($token, 'report');
$PAGE->set_url(new moodle_url('/blocks/aiplugin_nav/guardian_report.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('guardian_report_title', 'block_aiplugin_nav'));
if (!$details || $details['link']->status !== 'active' || empty($details['link']->confirmedat)) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('guardian_link_invalid', 'block_aiplugin_nav'), 'error');
    echo $OUTPUT->footer();
    exit;
}
$progress = \block_aiplugin_nav\local\course_report\progress::for_student(
    (int)$details['course']->id,
    (int)$details['student']->id
);
if (!$progress) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('guardian_report_unavailable', 'block_aiplugin_nav'), 'error');
    echo $OUTPUT->footer();
    exit;
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('guardian_report_title', 'block_aiplugin_nav'));
echo html_writer::tag('p', s(fullname($details['student']) . ' · ' . format_string($details['course']->fullname)));
echo html_writer::tag('p', get_string('guardian_progress_label', 'block_aiplugin_nav') . ': '
    . ($progress['percent'] === null ? get_string('guardian_not_tracked', 'block_aiplugin_nav') : $progress['percent'] . '%'));
foreach ($progress['lessons'] as $lesson) {
    echo $OUTPUT->heading($lesson['title'], 3);
    $table = new html_table();
    $table->attributes['class'] = 'generaltable table table-striped';
    $table->head = [
        get_string('coursereport_activity', 'block_aiplugin_nav'),
        get_string('coursereport_status', 'block_aiplugin_nav'),
    ];
    foreach ($lesson['activities'] as $activity) {
        $status = !$activity['tracked']
            ? get_string('coursereport_not_tracked', 'block_aiplugin_nav')
            : ($activity['complete']
                ? get_string('coursereport_status_complete', 'block_aiplugin_nav')
                : get_string('coursereport_status_notstarted', 'block_aiplugin_nav'));
        $table->data[] = [s($activity['name']), $status];
    }
    echo html_writer::table($table);
}
echo html_writer::tag('p', get_string('guardian_report_expiry', 'block_aiplugin_nav'), ['class' => 'text-muted']);
echo $OUTPUT->footer();
