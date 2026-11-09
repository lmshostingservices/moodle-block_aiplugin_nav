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
 * Regression coverage for the distinction between course completion and assessment score.
 *
 * @package block_aiplugin_nav
 * @covers \block_aiplugin_nav\local\course_report\progress
 * @covers \block_aiplugin_nav\local\course_report\knowledge_check_summary
 */
final class course_report_metrics_test extends \advanced_testcase {
    /**
     * Completion records must not collapse when multiple modules share a learner.
     *
     * @return void
     */
    public function test_completion_states_preserves_every_module_for_one_learner(): void {
        global $DB, $CFG;
        require_once($CFG->libdir . '/completionlib.php');
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['enablecompletion' => 1]);
        $learner = $generator->create_user();
        $generator->enrol_user($learner->id, $course->id);
        $first = $generator->create_module('forum', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $second = $generator->create_module('forum', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        foreach ([$first->cmid, $second->cmid] as $cmid) {
            $DB->insert_record('course_modules_completion', (object)[
                'coursemoduleid' => $cmid,
                'userid' => $learner->id,
                'completionstate' => COMPLETION_COMPLETE,
                'timemodified' => time(),
            ]);
        }
        $method = new \ReflectionMethod(
            \block_aiplugin_nav\local\course_report\progress::class,
            'completion_states'
        );
        $states = $method->invoke(null, [$first->cmid, $second->cmid], [$learner->id]);
        $this->assertCount(2, $states[$learner->id]);
        $this->assertSame(COMPLETION_COMPLETE, (int)$states[$learner->id][$first->cmid]['state']);
        $this->assertSame(COMPLETION_COMPLETE, (int)$states[$learner->id][$second->cmid]['state']);
    }

    /**
     * A 5/6 completion percentage is not the same metric as a latest 5/5 check.
     *
     * @return void
     */
    public function test_completion_and_latest_knowledge_check_score_are_separate(): void {
        $attempts = [
            (object)[
                'id' => 6,
                'userid' => 4,
                'cmid' => 7,
                'correctcount' => 4,
                'totalcount' => 5,
                'timeended' => 100,
            ],
            (object)[
                'id' => 7,
                'userid' => 4,
                'cmid' => 7,
                'correctcount' => 5,
                'totalcount' => 5,
                'timeended' => 200,
            ],
        ];

        $scores = \block_aiplugin_nav\local\course_report\knowledge_check_summary::summarize_attempts($attempts);

        $this->assertSame(83, \block_aiplugin_nav\local\course_report\progress::completion_percent(5, 6));
        $this->assertSame(5, $scores[4]['correct']);
        $this->assertSame(5, $scores[4]['total']);
        $this->assertSame(100.0, $scores[4]['percent']);
    }

    /**
     * Started, incomplete learners are overdue only after the course end date;
     * learners who have not started remain distinct.
     *
     * @return void
     */
    public function test_class_progress_status_uses_course_end_date(): void {
        $now = 2000000000;
        $progress = \block_aiplugin_nav\local\course_report\progress::class;

        $this->assertSame('complete', $progress::status_for_completion(100, 100, $now));
        $this->assertSame('overdue', $progress::status_for_completion(25, 1999999999, $now));
        $this->assertSame('notstarted', $progress::status_for_completion(0, 1999999999, $now));
        $this->assertSame('inprogress', $progress::status_for_completion(25, 2000000001, $now));
        $this->assertSame('nottracked', $progress::status_for_completion(null, 0, $now));
    }

    /**
     * The production threshold remains ten but test sites can lower it.
     *
     * @return void
     */
    public function test_verdict_minimum_attempts_defaults_to_ten_and_can_be_lowered(): void {
        $this->resetAfterTest();

        $this->assertSame(10, \block_aiplugin_nav\local\course_report\question_stats::minimum_attempts());
        set_config('question_min_attempts', 2, 'block_aiplugin_nav');
        $this->assertSame(2, \block_aiplugin_nav\local\course_report\question_stats::minimum_attempts());
    }

    /**
     * All teacher-facing verdicts follow the approved thresholds and ordering.
     *
     * The classifier is private because it is an implementation detail; use
     * reflection here so the report's public data contract stays unchanged.
     *
     * @return void
     */
    public function test_question_verdict_rules_cover_all_outcomes(): void {
        $this->resetAfterTest();
        set_config('question_min_attempts', 10, 'block_aiplugin_nav');
        $classifier = new \ReflectionMethod(
            \block_aiplugin_nav\local\course_report\question_stats::class,
            'verdict'
        );
        $classifier->setAccessible(true);
        $classify = static fn(array $row, bool $learninggap = false): string => $classifier->invoke(
            null,
            $row,
            $learninggap
        );
        $base = [
            'attempts' => 10,
            'correctpercent' => 70.0,
            'discrimination' => 0.3,
            'remedialpass' => null,
            'wrongoptionbeatskey' => false,
        ];

        $this->assertSame('Too few answers yet', $classify(array_merge($base, ['attempts' => 9])));
        $this->assertSame('Check the question', $classify(array_merge($base, [
            'correctpercent' => 40.0,
            'discrimination' => 0.1,
        ])));
        $this->assertSame('Check the question', $classify(array_merge($base, [
            'correctpercent' => 40.0,
            'wrongoptionbeatskey' => true,
        ])));
        $this->assertSame('Learning gap', $classify(array_merge($base, [
            'correctpercent' => 40.0,
            'discrimination' => 0.2,
        ])));
        $this->assertSame('Learning gap', $classify($base, true));
        $this->assertSame('Too easy', $classify(array_merge($base, [
            'correctpercent' => 95.0,
            'discrimination' => 0.1,
        ])));
        $this->assertSame('Working well', $classify($base));
    }
}
