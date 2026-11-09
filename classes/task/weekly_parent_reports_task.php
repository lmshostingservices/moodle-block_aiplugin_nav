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

namespace block_aiplugin_nav\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Send consented weekly student-progress reports to confirmed external guardians.
 *
 * @package block_aiplugin_nav
 */
final class weekly_parent_reports_task extends \core\task\scheduled_task {
    /** @return string */
    public function get_name(): string {
        return get_string('task_weekly_parent_reports', 'block_aiplugin_nav');
    }

    /** @return void */
    public function execute(): void {
        global $DB;
        $now = time();
        $dueLinks = $DB->get_records_select(
            'block_aiplugin_nav_guardlink',
            "status = :status AND frequency IN ('weekly', 'fortnightly', 'monthly') AND confirmedat > 0
             AND consentedby IS NOT NULL AND nextsend <= :now",
            ['status' => 'active', 'now' => $now],
            'nextsend ASC, id ASC',
            '*'
        );
        $dueByGuardian = [];
        foreach ($dueLinks as $link) {
            $dueByGuardian[(int)$link->guardianid][(int)$link->id] = $link;
        }

        foreach ($dueByGuardian as $guardianid => $guardianDueLinks) {
            $guardian = $DB->get_record('block_aiplugin_nav_guardian', ['id' => $guardianid]);
            if (!$guardian) {
                foreach ($guardianDueLinks as $link) {
                    self::revoke_link($link, $now);
                }
                continue;
            }

            // A send is triggered by any due subscription, but the message covers
            // every currently active subscription for this guardian.
            $activeLinks = $DB->get_records_select(
                'block_aiplugin_nav_guardlink',
                "guardianid = :guardianid AND status = :status
                 AND frequency IN ('weekly', 'fortnightly', 'monthly')
                 AND confirmedat > 0 AND consentedby IS NOT NULL",
                ['guardianid' => $guardianid, 'status' => 'active'],
                'courseid ASC, studentid ASC, id ASC'
            );

            $entries = [];
            $missingprogress = false;
            $missingdueids = [];
            foreach ($activeLinks as $link) {
                $student = $DB->get_record('user', ['id' => $link->studentid, 'deleted' => 0, 'suspended' => 0]);
                $course = $DB->get_record('course', ['id' => $link->courseid]);
                $consenter = $DB->get_record('user', ['id' => $link->consentedby, 'deleted' => 0]);
                if (!$student || !$course || !$consenter
                    || !is_enrolled(\context_course::instance((int)$course->id), $student, '', true)) {
                    self::revoke_link($link, $now);
                    continue;
                }
                $report = \block_aiplugin_nav\local\course_report\progress::for_student(
                    (int)$course->id,
                    (int)$student->id
                );
                if (!$report) {
                    $missingprogress = true;
                    if (isset($guardianDueLinks[(int)$link->id])) {
                        $missingdueids[(int)$link->id] = true;
                        self::log_skipped($guardianid, (int)$link->id, 'student_progress_unavailable', $now);
                    }
                    continue;
                }
                $entries[] = [
                    'link' => $link,
                    'progress' => [
                    'user' => $student,
                    'report' => $report,
                    'course' => $course,
                    ],
                ];
            }
            if ($missingprogress) {
                // Do not send a partial digest. Retry all due links together once
                // every active subscription can produce its own report.
                foreach ($guardianDueLinks as $link) {
                    $current = $DB->get_record('block_aiplugin_nav_guardlink', ['id' => $link->id]);
                    if (!$current || $current->status !== 'active') {
                        continue;
                    }
                    if (!isset($missingdueids[(int)$link->id])) {
                        self::log_skipped(
                            $guardianid,
                            (int)$link->id,
                            'guardian_digest_waiting_for_course_progress',
                            $now
                        );
                    }
                    $current->nextsend = $now + HOURSECS;
                    $current->timemodified = $now;
                    $DB->update_record('block_aiplugin_nav_guardlink', $current);
                }
                continue;
            }
            if (!$entries) {
                continue;
            }
            $entriesById = [];
            foreach ($entries as $entry) {
                $entriesById[(int)$entry['link']->id] = true;
            }

            $message = \block_aiplugin_nav\local\course_report\guardian_reports::aggregate_weekly_message(
                $guardian,
                $entries
            );
            $sent = \block_aiplugin_nav\local\course_report\guardian_reports::send_mail(
                $guardian,
                null,
                $message['subject'],
                $message['text'],
                $message['html']
            );

            foreach ($guardianDueLinks as $link) {
                $current = $DB->get_record('block_aiplugin_nav_guardlink', ['id' => $link->id]);
                if (!$current || $current->status !== 'active' || !isset($entriesById[$current->id])) {
                    continue;
                }
                $current->nextsend = $sent
                    ? \block_aiplugin_nav\local\course_report\guardian_reports::next_send_time(
                        (string)$current->frequency,
                        $now,
                        true
                    )
                    : $now + HOURSECS;
                $current->timemodified = $now;
                $DB->update_record('block_aiplugin_nav_guardlink', $current);
            }
        }
    }

    /**
     * Revoke a subscription that no longer has a valid learner, course or consent record.
     *
     * @param \stdClass $link
     * @param int $now
     * @return void
     */
    private static function revoke_link(\stdClass $link, int $now): void {
        global $DB;
        $link->status = 'revoked';
        $link->frequency = 'off';
        $link->nextsend = 0;
        $link->weeklysummary = null;
        $link->summaryhash = null;
        $link->summaryupdatedby = null;
        $link->summaryupdatedat = 0;
        $link->timemodified = $now;
        $DB->update_record('block_aiplugin_nav_guardlink', $link);
        \block_aiplugin_nav\local\course_report\guardian_reports::revoke_link_tokens((int)$link->id);
    }

    /**
     * Log a due course report that could not be assembled.
     *
     * @param int $guardianid
     * @param int $linkid
     * @param string $reason
     * @param int $now
     * @return void
     */
    private static function log_skipped(int $guardianid, int $linkid, string $reason, int $now): void {
        global $DB;
        $DB->insert_record('block_aiplugin_nav_gmail', (object)[
            'guardianid' => $guardianid,
            'linkid' => $linkid,
            'status' => 'skipped',
            'error' => $reason,
            'sentat' => 0,
            'timecreated' => $now,
        ]);
    }
}
