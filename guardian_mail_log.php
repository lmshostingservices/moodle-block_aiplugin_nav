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
 * Teacher-only delivery history for guardian confirmation and weekly emails.
 *
 * @package block_aiplugin_nav
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$guardianid = required_param('guardianid', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
require_capability('moodle/course:manageactivities', context_course::instance($courseid));
$guardian = $DB->get_record('block_aiplugin_nav_guardian', ['id' => $guardianid], '*', MUST_EXIST);
$linkids = $DB->get_fieldset_select(
    'block_aiplugin_nav_guardlink',
    'id',
    'courseid = :courseid AND guardianid = :guardianid',
    ['courseid' => $courseid, 'guardianid' => $guardianid]
);
$PAGE->set_url(new moodle_url('/blocks/aiplugin_nav/guardian_mail_log.php', [
    'courseid' => $courseid,
    'guardianid' => $guardianid,
]));
$PAGE->set_context(context_course::instance($courseid));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('guardian_email_log', 'block_aiplugin_nav'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('guardian_email_log', 'block_aiplugin_nav'));
echo html_writer::tag('p', s(trim($guardian->firstname . ' ' . $guardian->lastname) . ' · ' . $guardian->email));
$table = new html_table();
$table->attributes['class'] = 'generaltable table table-striped';
$table->head = [
    get_string('guardian_sent_at', 'block_aiplugin_nav'),
    get_string('guardian_delivery_status', 'block_aiplugin_nav'),
    get_string('guardian_safe_error', 'block_aiplugin_nav'),
];
if ($linkids) {
    [$insql, $params] = $DB->get_in_or_equal($linkids, SQL_PARAMS_NAMED, 'link');
    $logs = $DB->get_records_select(
        'block_aiplugin_nav_gmail',
        "guardianid = :guardianid AND (linkid $insql OR linkid IS NULL)",
        ['guardianid' => $guardianid] + $params,
        'timecreated DESC',
        '*',
        0,
        200
    );
    foreach ($logs as $log) {
        $table->data[] = [
            userdate((int)$log->timecreated),
            s($log->status),
            s((string)$log->error),
        ];
    }
}
if ($table->data) {
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('guardian_no_email_log', 'block_aiplugin_nav'), 'info');
}
echo html_writer::div(html_writer::link(
    new moodle_url('/blocks/aiplugin_nav/guardian_links.php', ['courseid' => $courseid]),
    get_string('back')
), 'mt-3');
echo $OUTPUT->footer();
