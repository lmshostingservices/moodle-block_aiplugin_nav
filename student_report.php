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
 * Student progress report for teachers, learners and guardians.
 *
 * @package    block_aiplugin_nav
 * @copyright 2026 LMS Labs
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Student-safe course progress report; deliberately excludes class comparisons and question data.
 *
 * @package block_aiplugin_nav
 */

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$studentid = optional_param('studentid', 0, PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$studentid = $studentid ?: (int)$USER->id;
$context = context_course::instance($courseid);
$isteacher = has_capability('moodle/course:manageactivities', $context);
if ($studentid !== (int)$USER->id && !$isteacher) {
    throw new required_capability_exception($context, 'moodle/course:manageactivities', 'nopermissions', '');
}

$student = \block_aiplugin_nav\local\course_report\progress::for_student($courseid, $studentid);
if (!$student) {
    throw new moodle_exception('Student is not enrolled in this course.');
}
$PAGE->set_url(new moodle_url('/blocks/aiplugin_nav/student_report.php', [
    'courseid' => $courseid,
    'studentid' => $studentid,
]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('coursereport_student_report', 'block_aiplugin_nav'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
$report = \block_aiplugin_nav\local\course_report\progress::for_course($courseid);
$full = null;
foreach ($report['students'] as $row) {
    if ($row['id'] === $studentid) {
        $full = $row;
    }
}
echo \block_aiplugin_nav\local\course_report\view::student(
    $course,
    $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST),
    $full,
    $report['lessons'],
    $isteacher
);
echo $OUTPUT->footer();
