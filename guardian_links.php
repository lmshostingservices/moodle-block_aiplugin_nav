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
 * Manage course guardian contacts, consent and weekly report preferences.
 *
 * @package block_aiplugin_nav
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);
$PAGE->set_url(new moodle_url('/blocks/aiplugin_nav/guardian_links.php', ['courseid' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('coursereport_manage_guardians', 'block_aiplugin_nav'));
$PAGE->set_heading(format_string($course->fullname));

$progress = \block_aiplugin_nav\local\course_report\progress::for_course($courseid);
$returnurl = new moodle_url('/blocks/aiplugin_nav/guardian_links.php', ['courseid' => $courseid]);
$action = optional_param('action', '', PARAM_ALPHA);
if ($action !== '') {
    require_sesskey();
    if ($action === 'add') {
        $studentid = required_param('studentid', PARAM_INT);
        $firstname = trim(required_param('firstname', PARAM_TEXT));
        $lastname = trim(required_param('lastname', PARAM_TEXT));
        $email = clean_param(required_param('email', PARAM_EMAIL), PARAM_EMAIL);
        $relationship = trim(required_param('relationship', PARAM_TEXT));
        $consent = optional_param('consent', 0, PARAM_BOOL);
        $studentids = array_map(static fn(array $student): int => $student['id'], $progress['students']);
        if (!$consent || !in_array($studentid, $studentids, true) || $firstname === '' || $email === '') {
            throw new invalid_parameter_exception(get_string('guardian_required_consent', 'block_aiplugin_nav'));
        }
        $guardian = $DB->get_record_sql(
            'SELECT * FROM {block_aiplugin_nav_guardian} WHERE LOWER(email) = :email',
            ['email' => core_text::strtolower($email)]
        );
        if (!$guardian) {
            $guardianid = $DB->insert_record('block_aiplugin_nav_guardian', (object)[
                'firstname' => $firstname,
                'lastname' => $lastname,
                'email' => $email,
                'language' => current_language(),
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
            $guardian = $DB->get_record('block_aiplugin_nav_guardian', ['id' => $guardianid], '*', MUST_EXIST);
        } else {
            $guardian->firstname = $firstname;
            $guardian->lastname = $lastname;
            $guardian->timemodified = time();
            $DB->update_record('block_aiplugin_nav_guardian', $guardian);
        }
        $link = $DB->get_record('block_aiplugin_nav_guardlink', [
            'guardianid' => $guardian->id,
            'studentid' => $studentid,
            'courseid' => $courseid,
        ]);
        if (!$link) {
            $link = (object)[
                'guardianid' => (int)$guardian->id,
                'studentid' => $studentid,
                'courseid' => $courseid,
                'relationship' => $relationship,
                'consentedby' => (int)$USER->id,
                'consentedat' => time(),
                'confirmedat' => 0,
                'frequency' => 'off',
                'nextsend' => 0,
                'status' => 'pending',
                'timecreated' => time(),
                'timemodified' => time(),
            ];
            $link->id = $DB->insert_record('block_aiplugin_nav_guardlink', $link);
        } else {
            \block_aiplugin_nav\local\course_report\guardian_reports::revoke_link_tokens((int)$link->id);
            $link->relationship = $relationship;
            $link->consentedby = (int)$USER->id;
            $link->consentedat = time();
            $link->confirmedat = 0;
            $link->frequency = 'off';
            $link->nextsend = 0;
            $link->weeklysummary = null;
            $link->summaryhash = null;
            $link->summaryupdatedby = null;
            $link->summaryupdatedat = 0;
            $link->status = 'pending';
            $link->timemodified = time();
            $DB->update_record('block_aiplugin_nav_guardlink', $link);
        }
        $sent = \block_aiplugin_nav\local\course_report\guardian_reports::send_confirmation(
            $guardian,
            $link,
            $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST),
            $course
        );
        redirect(
            $returnurl,
            get_string($sent ? 'guardian_confirmation_sent' : 'guardian_confirmation_failed', 'block_aiplugin_nav'),
            null,
            $sent ? \core\output\notification::NOTIFY_SUCCESS : \core\output\notification::NOTIFY_ERROR
        );
    }
    if ($action === 'preference') {
        $linkid = required_param('linkid', PARAM_INT);
        $frequency = required_param('frequency', PARAM_ALPHA);
        $link = $DB->get_record('block_aiplugin_nav_guardlink', [
            'id' => $linkid,
            'courseid' => $courseid,
        ], '*', MUST_EXIST);
        if (!in_array($frequency, ['off', 'weekly', 'fortnightly', 'monthly', 'revoke'], true)) {
            throw new invalid_parameter_exception('Invalid report frequency.');
        }
        if ($frequency === 'revoke') {
            $link->frequency = 'off';
            $link->nextsend = 0;
            $link->weeklysummary = null;
            $link->summaryhash = null;
            $link->summaryupdatedby = null;
            $link->summaryupdatedat = 0;
            $link->status = 'revoked';
            \block_aiplugin_nav\local\course_report\guardian_reports::revoke_link_tokens((int)$link->id);
        } else if (in_array($frequency, ['weekly', 'fortnightly', 'monthly'], true)) {
            if (empty($link->confirmedat) || $link->status !== 'active' || empty($link->consentedat)) {
                throw new moodle_exception('Guardian confirmation and consent are required before reports.');
            }
            $link->frequency = $frequency;
            $link->nextsend = \block_aiplugin_nav\local\course_report\guardian_reports::next_send_time(
                $frequency,
                time()
            );
        } else {
            $link->frequency = 'off';
            $link->nextsend = 0;
        }
        $link->timemodified = time();
        $DB->update_record('block_aiplugin_nav_guardlink', $link);
        redirect($returnurl, get_string('guardian_preference_saved', 'block_aiplugin_nav'),
            null, \core\output\notification::NOTIFY_SUCCESS);
    }
    throw new invalid_parameter_exception('Invalid guardian action.');
}

$links = $DB->get_records('block_aiplugin_nav_guardlink', ['courseid' => $courseid], 'timemodified DESC');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coursereport_manage_guardians', 'block_aiplugin_nav'));
echo $OUTPUT->notification(
    get_string('guardian_privacy_notice', 'block_aiplugin_nav'),
    \core\output\notification::NOTIFY_INFO
);

$form = html_writer::start_tag('form', ['method' => 'post', 'action' => $returnurl, 'class' => 'card card-body mb-4']);
$form .= html_writer::tag('h3', get_string('guardian_add_contact', 'block_aiplugin_nav'));
$form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'add']);
$form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
$form .= html_writer::tag('label', get_string('guardian_student', 'block_aiplugin_nav'), ['for' => 'studentid']);
$studentoptions = [0 => get_string('choose')];
foreach ($progress['students'] as $student) {
    $studentoptions[$student['id']] = $student['name'];
}
$form .= html_writer::select($studentoptions, 'studentid', '', false, ['id' => 'studentid', 'class' => 'form-control']);
foreach (['firstname' => get_string('guardian_firstname', 'block_aiplugin_nav'),
          'lastname' => get_string('guardian_lastname', 'block_aiplugin_nav'),
          'email' => get_string('guardian_email', 'block_aiplugin_nav'),
          'relationship' => get_string('guardian_relationship', 'block_aiplugin_nav')] as $name => $label) {
    $form .= html_writer::tag('label', $label, ['for' => $name, 'class' => 'mt-2']);
    $form .= html_writer::empty_tag('input', [
        'type' => $name === 'email' ? 'email' : 'text',
        'id' => $name,
        'name' => $name,
        'required' => 'required',
        'maxlength' => $name === 'email' ? 255 : 100,
        'class' => 'form-control',
    ]);
}
$form .= html_writer::tag('label',
    html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'consent', 'value' => 1, 'required' => 'required'])
    . ' ' . s(get_string('guardian_consent_attestation', 'block_aiplugin_nav')),
    ['class' => 'mt-3']);
$form .= html_writer::tag('button', get_string('guardian_request_confirmation', 'block_aiplugin_nav'),
    ['type' => 'submit', 'class' => 'btn btn-primary mt-2']);
$form .= html_writer::end_tag('form');
echo $form;

$table = new html_table();
$table->attributes['class'] = 'generaltable table table-striped';
$table->head = [
    get_string('guardian_contact', 'block_aiplugin_nav'),
    get_string('guardian_student', 'block_aiplugin_nav'),
    get_string('guardian_consent', 'block_aiplugin_nav'),
    get_string('guardian_confirmation', 'block_aiplugin_nav'),
    get_string('guardian_frequency', 'block_aiplugin_nav'),
    get_string('guardian_status', 'block_aiplugin_nav'),
    get_string('actions'),
];
foreach ($links as $link) {
    $guardian = $DB->get_record('block_aiplugin_nav_guardian', ['id' => $link->guardianid]);
    $student = $DB->get_record('user', ['id' => $link->studentid]);
    if (!$guardian || !$student) {
        continue;
    }
    $consenter = $link->consentedby ? $DB->get_record('user', ['id' => $link->consentedby]) : null;
    $frequencyform = html_writer::start_tag('form', ['method' => 'post', 'action' => $returnurl]);
    $frequencyform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'preference']);
    $frequencyform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'linkid', 'value' => $link->id]);
    $frequencyform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    $frequencyform .= html_writer::select([
        'off' => get_string('guardian_frequency_off', 'block_aiplugin_nav'),
        'weekly' => get_string('guardian_frequency_weekly', 'block_aiplugin_nav'),
        'fortnightly' => get_string('guardian_frequency_fortnightly', 'block_aiplugin_nav'),
        'monthly' => get_string('guardian_frequency_monthly', 'block_aiplugin_nav'),
        'revoke' => get_string('guardian_revoke', 'block_aiplugin_nav'),
    ], 'frequency', $link->frequency, false, ['class' => 'form-control']);
    $frequencyform .= html_writer::tag('button', get_string('savechanges'), [
        'type' => 'submit', 'class' => 'btn btn-sm btn-outline-primary mt-1',
    ]);
    $frequencyform .= html_writer::end_tag('form');
    $summaryaction = '';
    if ($link->status === 'active' && !empty($link->confirmedat) && !empty($link->consentedat)) {
        $summaryaction = html_writer::link(new moodle_url('/blocks/aiplugin_nav/guardian_summary.php', [
            'linkid' => $link->id,
        ]), get_string('guardian_summary', 'block_aiplugin_nav'), [
            'class' => 'btn btn-sm btn-outline-primary mb-1',
        ]) . html_writer::tag('br', '');
    }
    $table->data[] = [
        s(trim($guardian->firstname . ' ' . $guardian->lastname)) . html_writer::tag('br', '')
            . html_writer::tag('small', s($guardian->email)),
        s(fullname($student)),
        $consenter ? s(fullname($consenter)) . html_writer::tag('br', '')
            . userdate((int)$link->consentedat) : get_string('guardian_no_consent', 'block_aiplugin_nav'),
        !empty($link->confirmedat) ? userdate((int)$link->confirmedat) : get_string('guardian_pending', 'block_aiplugin_nav'),
        $frequencyform,
        s(get_string('guardian_status_' . $link->status, 'block_aiplugin_nav')),
        $summaryaction . html_writer::link(new moodle_url('/blocks/aiplugin_nav/guardian_mail_log.php', [
            'courseid' => $courseid,
            'guardianid' => $guardian->id,
        ]), get_string('guardian_email_log', 'block_aiplugin_nav')),
    ];
}
if ($table->data) {
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('guardian_none', 'block_aiplugin_nav'), 'info');
}
echo $OUTPUT->footer();
