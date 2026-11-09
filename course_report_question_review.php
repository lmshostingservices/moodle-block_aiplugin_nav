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
 * Ask the LMS Labs service for a teacher-only question-quality suggestion.
 *
 * @package block_aiplugin_nav
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/aicoursebuilder/classes/local/api_client.php');

$courseid = required_param('id', PARAM_INT);
$cmid = required_param('cmid', PARAM_INT);
$questionid = required_param('questionid', PARAM_INT);
$source = optional_param('source', 'knowledgecheck', PARAM_ALPHA);
require_sesskey();

if (!in_array($source, ['knowledgecheck', 'quiz'], true)) {
    throw new invalid_parameter_exception('Invalid question source.');
}
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$coursecontext = context_course::instance($courseid);
require_capability('moodle/course:manageactivities', $coursecontext);

$cm = get_coursemodule_from_id($source === 'quiz' ? 'quiz' : 'aiknowledgecheck', $cmid, $courseid, false, MUST_EXIST);
$questiontext = '';
$options = [];
$correctindex = null;
$subject = format_string($course->fullname);
$learnerlevel = '';

if ($source === 'knowledgecheck') {
    $question = $DB->get_record('aiknowledgecheck_questions', [
        'id' => $questionid,
        'aiknowledgecheckid' => $cm->instance,
    ], '*', MUST_EXIST);
    if (($question->questiontype ?? 'scale') === 'freetext') {
        throw new moodle_exception('Question review is available only for auto-scored choices.');
    }
    $questiontext = trim(strip_tags((string)$question->questiontext));
    for ($index = 0; $index < 5; $index++) {
        $value = trim(strip_tags((string)($question->{'answer' . ($index + 1)} ?? '')));
        if ($value === '') {
            continue;
        }
        if ($index === (int)$question->correctanswer) {
            $correctindex = count($options);
        }
        $options[] = $value;
    }
} else {
    $belongs = \block_aiplugin_nav\local\course_report\moodle_quiz_stats::quiz_contains_question(
        (int)$cm->instance,
        $questionid
    );
    if (!$belongs) {
        throw new moodle_exception('Question is not part of this quiz.');
    }
    $question = $DB->get_record('question', ['id' => $questionid], '*', MUST_EXIST);
    if (!\block_aiplugin_nav\local\course_report\moodle_quiz_stats::is_reviewable_question(
        $questionid,
        (string)$question->qtype
    )) {
        throw new moodle_exception('Question review is available only for single-answer choice questions.');
    }
    $questiontext = trim(strip_tags((string)$question->questiontext));
    $answers = $DB->get_records('question_answers', ['question' => $questionid], 'id ASC');
    $maxfraction = null;
    foreach ($answers as $answer) {
        $maxfraction = $maxfraction === null
            ? (float)$answer->fraction
            : max($maxfraction, (float)$answer->fraction);
    }
    $correctids = [];
    foreach ($answers as $answer) {
        $options[] = trim(strip_tags((string)$answer->answer));
        if ($maxfraction !== null && (float)$answer->fraction === $maxfraction) {
            $correctids[] = count($options) - 1;
        }
    }
    if (count($correctids) !== 1) {
        throw new moodle_exception('AI review requires one keyed answer; this question has multiple or no keyed answers.');
    }
    $correctindex = $correctids[0];
}

if (count($options) < 2 || $correctindex === null || $correctindex >= count($options)) {
    throw new moodle_exception('The question needs at least two answer choices and one keyed answer.');
}

$payload = \local_aicoursebuilder\local\api_client::request_json(
    'POST',
    '/api/moodle/course-report/question-review',
    [
        'question' => $questiontext,
        'options' => $options,
        'correctIndex' => $correctindex,
        'subject' => $subject,
        'learnerLevel' => $learnerlevel,
    ],
    45
);
if (empty($payload['success']) || empty($payload['review']['fault']) || empty($payload['review']['rewrite'])) {
    throw new moodle_exception('The AI review did not return a usable suggestion.');
}

$table = new xmldb_table('block_aiplugin_nav_qreview');
if (!$DB->get_manager()->table_exists($table)) {
    throw new coding_exception('Course Report review table is missing; complete the block upgrade first.');
}
$existing = $DB->get_record('block_aiplugin_nav_qreview', [
    'courseid' => $courseid,
    'cmid' => $cmid,
    'questionid' => $questionid,
]);
$now = time();
if ($existing) {
    $existing->fault = clean_param((string)$payload['review']['fault'], PARAM_ALPHANUMEXT);
    $existing->rationale = clean_param((string)$payload['review']['rationale'], PARAM_TEXT);
    $existing->rewrite = clean_param((string)$payload['review']['rewrite'], PARAM_TEXT);
    $existing->teacheredit = null;
    $existing->reviewstatus = 'suggested';
    $existing->suggestedby = $USER->id;
    $existing->suggestedat = $now;
    $existing->reviewedby = 0;
    $existing->reviewedat = 0;
    $DB->update_record('block_aiplugin_nav_qreview', $existing);
} else {
    $DB->insert_record('block_aiplugin_nav_qreview', (object)[
        'courseid' => $courseid,
        'cmid' => $cmid,
        'questionid' => $questionid,
        'reviewedby' => 0,
        'reviewedat' => 0,
        'reviewstatus' => 'suggested',
        'fault' => clean_param((string)$payload['review']['fault'], PARAM_ALPHANUMEXT),
        'rationale' => clean_param((string)$payload['review']['rationale'], PARAM_TEXT),
        'rewrite' => clean_param((string)$payload['review']['rewrite'], PARAM_TEXT),
        'teacheredit' => null,
        'suggestedby' => $USER->id,
        'suggestedat' => $now,
    ]);
}

$returnurl = new moodle_url('/blocks/aiplugin_nav/course_report.php', [
    'id' => $courseid,
    'tab' => 'questions',
]);
redirect($returnurl, get_string('coursereport_review_generated', 'block_aiplugin_nav'),
    null, \core\output\notification::NOTIFY_SUCCESS);
