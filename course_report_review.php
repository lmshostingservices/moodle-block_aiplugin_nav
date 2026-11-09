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
 * Save a teacher's review decision for a Course Report question.
 *
 * @package block_aiplugin_nav
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('id', PARAM_INT);
$cmid = required_param('cmid', PARAM_INT);
$questionid = required_param('questionid', PARAM_INT);
$source = optional_param('source', 'knowledgecheck', PARAM_ALPHA);
$action = required_param('reviewaction', PARAM_ALPHA);
$teacheredit = optional_param('teacheredit', '', PARAM_TEXT);
require_sesskey();

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$context = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $context);

if (!in_array($source, ['knowledgecheck', 'quiz'], true)
    || !in_array($action, ['accept', 'edit', 'dismiss', 'mark'], true)) {
    throw new invalid_parameter_exception('Invalid question review action.');
}
$cm = get_coursemodule_from_id($source === 'quiz' ? 'quiz' : 'aiknowledgecheck', $cmid, $courseid, false, MUST_EXIST);
if ($source === 'knowledgecheck') {
    $DB->get_record('aiknowledgecheck_questions', [
        'id' => $questionid,
        'aiknowledgecheckid' => $cm->instance,
    ], 'id', MUST_EXIST);
} else if (!\block_aiplugin_nav\local\course_report\moodle_quiz_stats::quiz_contains_question(
    (int)$cm->instance,
    $questionid
)) {
    throw new moodle_exception('Question is not part of this quiz.');
}
$table = new xmldb_table('block_aiplugin_nav_qreview');
if (!$DB->get_manager()->table_exists($table)) {
    throw new coding_exception('Course Report review table is missing; complete the block upgrade before marking questions reviewed.');
}

$existing = $DB->get_record('block_aiplugin_nav_qreview', [
    'courseid' => $courseid,
    'cmid' => $cmid,
    'questionid' => $questionid,
]);
if (!$existing) {
    $existing = (object)[
        'courseid' => $courseid,
        'cmid' => $cmid,
        'questionid' => $questionid,
        'reviewedby' => 0,
        'reviewedat' => 0,
        'reviewstatus' => 'none',
        'fault' => null,
        'rewrite' => null,
        'teacheredit' => null,
        'suggestedby' => null,
        'suggestedat' => null,
    ];
}
if (in_array($action, ['accept', 'edit'], true)) {
    $teacheredit = trim($teacheredit);
    if ($teacheredit === '') {
        throw new invalid_parameter_exception('Enter or keep a suggested rewrite before saving it.');
    }
    $existing->teacheredit = $teacheredit;
    $existing->reviewstatus = $action === 'accept' ? 'accepted' : 'edited';
} else if ($action === 'dismiss') {
    $existing->reviewstatus = 'dismissed';
} else {
    $existing->reviewstatus = 'reviewed';
}
$existing->reviewedby = $USER->id;
$existing->reviewedat = time();
if (!empty($existing->id)) {
    $DB->update_record('block_aiplugin_nav_qreview', $existing);
} else {
    $DB->insert_record('block_aiplugin_nav_qreview', $existing);
}

$message = get_string('coursereport_review_saved', 'block_aiplugin_nav');
redirect(
    new moodle_url('/blocks/aiplugin_nav/course_report.php', ['id' => $courseid, 'tab' => 'questions']),
    $message,
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
