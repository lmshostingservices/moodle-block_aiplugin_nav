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

namespace block_aiplugin_nav;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests consent-bound guardian report email construction.
 *
 * @package block_aiplugin_nav
 * @covers \block_aiplugin_nav\local\course_report\guardian_reports
 */
final class guardian_reports_test extends \advanced_testcase {
    /**
     * A parent digest includes all subscribed courses and valid signed links.
     *
     * @return void
     */
    public function test_aggregate_email_has_one_report_and_unsubscribe_link_per_course(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $student = $generator->create_user();
        $teacher = $generator->create_user();
        $courseone = $generator->create_course(['fullname' => 'Water Cycle Part One']);
        $coursetwo = $generator->create_course(['fullname' => 'Water Cycle Part Two']);
        $now = time();
        $guardianid = $DB->insert_record('block_aiplugin_nav_guardian', (object)[
            'firstname' => 'Test',
            'lastname' => 'Guardian',
            'email' => 'guardian@example.test',
            'language' => 'en',
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $guardian = $DB->get_record('block_aiplugin_nav_guardian', ['id' => $guardianid], '*', MUST_EXIST);
        $entries = [];

        foreach ([[$courseone, 55], [$coursetwo, 80]] as [$course, $percent]) {
            $progress = [
                'user' => $student,
                'course' => $course,
                'report' => ['percent' => $percent],
            ];
            $metrics = \block_aiplugin_nav\local\course_report\guardian_reports::summary_metrics($course, $progress);
            $link = (object)[
                'guardianid' => $guardianid,
                'studentid' => (int)$student->id,
                'courseid' => (int)$course->id,
                'relationship' => 'Parent',
                'consentedby' => (int)$teacher->id,
                'consentedat' => $now,
                'confirmedat' => $now,
                'frequency' => 'weekly',
                'nextsend' => $now,
                'weeklysummary' => 'Your child has made progress in this course.',
                'summaryhash' => \block_aiplugin_nav\local\course_report\guardian_reports::summary_hash($metrics),
                'summaryupdatedby' => (int)$teacher->id,
                'summaryupdatedat' => $now,
                'status' => 'active',
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $link->id = $DB->insert_record('block_aiplugin_nav_guardlink', $link);
            $entries[] = ['link' => $link, 'progress' => $progress];
        }

        $message = \block_aiplugin_nav\local\course_report\guardian_reports::aggregate_weekly_message(
            $guardian,
            $entries
        );
        $this->assertSame(2, substr_count($message['text'], 'guardian_report.php?token='));
        $this->assertSame(2, substr_count($message['text'], 'guardian_unsubscribe.php?token='));
        $this->assertStringContainsString('Water Cycle Part One', $message['text']);
        $this->assertStringContainsString('Water Cycle Part Two', $message['text']);
        $this->assertSame(2, substr_count($message['text'], 'Your child has made progress in this course.'));

        preg_match_all('/guardian_report\.php\?token=([a-f0-9]{64})/', $message['text'], $matches);
        foreach ($matches[1] as $token) {
            $this->assertNotNull(
                \block_aiplugin_nav\local\course_report\guardian_reports::lookup_token($token, 'report')
            );
        }
    }

    /**
     * Do not reuse an approved summary when the current course has no progress data.
     *
     * @return void
     */
    public function test_untracked_course_does_not_include_a_progress_summary(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $student = $generator->create_user();
        $course = $generator->create_course(['fullname' => 'Untracked Course']);
        $guardian = (object)[
            'id' => 1,
            'firstname' => 'Test',
            'lastname' => 'Guardian',
            'email' => 'guardian@example.test',
            'language' => 'en',
        ];
        $progress = [
            'user' => $student,
            'course' => $course,
            'report' => ['percent' => null],
        ];
        $metrics = \block_aiplugin_nav\local\course_report\guardian_reports::summary_metrics($course, $progress);
        $link = (object)[
            'id' => 987654,
            'weeklysummary' => 'This stale summary must not be sent.',
            'summaryhash' => \block_aiplugin_nav\local\course_report\guardian_reports::summary_hash($metrics),
        ];
        $message = \block_aiplugin_nav\local\course_report\guardian_reports::aggregate_weekly_message(
            $guardian,
            [['link' => $link, 'progress' => $progress]]
        );
        $this->assertStringNotContainsString('This stale summary must not be sent.', $message['text']);
        $this->assertStringContainsString(get_string('guardian_not_tracked', 'block_aiplugin_nav'), $message['text']);
    }
}
