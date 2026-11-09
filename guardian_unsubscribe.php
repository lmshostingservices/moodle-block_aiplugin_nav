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
 * Confirm one-click unsubscribe for weekly progress email.
 *
 * @package block_aiplugin_nav
 */

// pipeline-no-login: Short-lived signed guardian token is validated below.
require_once(__DIR__ . '/../../config.php');

$token = required_param('token', PARAM_ALPHANUMEXT);
$action = optional_param('action', '', PARAM_ALPHA);
$details = \block_aiplugin_nav\local\course_report\guardian_reports::lookup_token($token, 'unsubscribe');
$PAGE->set_url(new moodle_url('/blocks/aiplugin_nav/guardian_unsubscribe.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('guardian_unsubscribe', 'block_aiplugin_nav'));
if (!$details || $details['link']->status !== 'active') {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('guardian_link_invalid', 'block_aiplugin_nav'), 'error');
    echo $OUTPUT->footer();
    exit;
}
if ($action === 'unsubscribe') {
    require_sesskey();
    $link = $details['link'];
    $link->frequency = 'off';
    $link->nextsend = 0;
    $link->status = 'unsubscribed';
    $link->weeklysummary = null;
    $link->summaryhash = null;
    $link->summaryupdatedby = null;
    $link->summaryupdatedat = 0;
    $link->timemodified = time();
    $DB->update_record('block_aiplugin_nav_guardlink', $link);
    \block_aiplugin_nav\local\course_report\guardian_reports::revoke_link_tokens((int)$link->id);
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('guardian_unsubscribed', 'block_aiplugin_nav'),
        \core\output\notification::NOTIFY_SUCCESS);
    echo $OUTPUT->footer();
    exit;
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('guardian_unsubscribe', 'block_aiplugin_nav'));
echo $OUTPUT->notification(get_string('guardian_unsubscribe_confirm', 'block_aiplugin_nav'), 'info');
echo html_writer::start_tag('form', ['method' => 'post']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'token', 'value' => $token]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'unsubscribe']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::tag('button', get_string('guardian_unsubscribe', 'block_aiplugin_nav'), [
    'type' => 'submit',
    'class' => 'btn btn-danger',
]);
echo html_writer::end_tag('form');
echo $OUTPUT->footer();
