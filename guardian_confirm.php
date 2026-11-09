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
 * Confirm an external guardian's access to one learner's progress report.
 *
 * @package block_aiplugin_nav
 */

// pipeline-no-login: Short-lived signed guardian token is validated below.
require_once(__DIR__ . '/../../config.php');

$token = required_param('token', PARAM_ALPHANUMEXT);
$action = optional_param('action', '', PARAM_ALPHA);
$details = \block_aiplugin_nav\local\course_report\guardian_reports::lookup_token($token, 'confirm');
$PAGE->set_url(new moodle_url('/blocks/aiplugin_nav/guardian_confirm.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('guardian_confirmation_title', 'block_aiplugin_nav'));

if (!$details || $details['link']->status !== 'pending') {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('guardian_link_invalid', 'block_aiplugin_nav'), 'error');
    echo $OUTPUT->footer();
    exit;
}
if ($action === 'confirm') {
    require_sesskey();
    $link = $details['link'];
    if (empty($link->consentedat) || empty($link->consentedby)) {
        throw new moodle_exception('Guardian report consent is not recorded.');
    }
    $link->confirmedat = time();
    $link->status = 'active';
    $link->timemodified = time();
    $DB->update_record('block_aiplugin_nav_guardlink', $link);
    \block_aiplugin_nav\local\course_report\guardian_reports::consume_token((int)$details['token']->id);
    $reporttoken = \block_aiplugin_nav\local\course_report\guardian_reports::create_token(
        (int)$link->id,
        'report',
        604800
    );
    redirect(new moodle_url('/blocks/aiplugin_nav/guardian_report.php', ['token' => $reporttoken]));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('guardian_confirmation_title', 'block_aiplugin_nav'));
echo $OUTPUT->notification(
    get_string('guardian_confirmation_privacy', 'block_aiplugin_nav', [
        'student' => fullname($details['student']),
        'course' => format_string($details['course']->fullname),
    ]),
    \core\output\notification::NOTIFY_INFO
);
echo html_writer::start_tag('form', ['method' => 'post']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'token', 'value' => $token]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'confirm']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::tag('button', get_string('guardian_confirm_button', 'block_aiplugin_nav'), [
    'type' => 'submit',
    'class' => 'btn btn-primary',
]);
echo html_writer::end_tag('form');
echo $OUTPUT->footer();
