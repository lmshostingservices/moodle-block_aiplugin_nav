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

namespace block_aiplugin_nav\local\course_report\providers;

use block_aiplugin_nav\local\course_report\activity_provider;
use block_aiplugin_nav\local\course_report\activity_provider_base;

defined('MOODLE_INTERNAL') || die();

/**
 * Course Report data provider for AI Safety Hazards.
 *
 * @package    block_aiplugin_nav
 * @copyright  2026 LMS Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ai_safety_hazards extends activity_provider_base implements activity_provider {
    /**
     * Component this provider reports on.
     *
     * @return string
     */
    public static function component(): string {
        return 'mod_aisafetyhazards';
    }

    /**
     * Metrics for the report.
     *
     * @return array
     */
    public static function metrics(int $cmid, int $userid): ?array {
        global $DB;

        $cm = get_coursemodule_from_id('aisafetyhazards', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return null;
        }
        $instance = $DB->get_record(
            'aisafetyhazards',
            ['id' => $cm->instance, 'course' => $cm->course],
            '*',
            IGNORE_MISSING
        );
        if (!$instance) {
            return null;
        }

        $summary = $DB->get_record_sql(
            "SELECT COUNT(1) AS attempts,
                    MAX(timemodified) AS lastactivity,
                    SUM(CASE WHEN timefinish > timestart THEN timefinish - timestart ELSE 0 END) AS timeseconds,
                    SUM(CASE WHEN state = :finished THEN 1 ELSE 0 END) AS completed
               FROM {aisafetyhazards_attempt}
              WHERE aisafetyhazardsid = :instanceid AND userid = :userid",
            [
                'finished' => 'finished',
                'instanceid' => $instance->id,
                'userid' => $userid,
            ]
        );
        $mode = \mod_aisafetyhazards\local\learning::graded_mode($instance);
        $latest = null;
        if ($mode !== \mod_aisafetyhazards\local\learning::MODE_STUDY) {
            $latest = $DB->get_record_select(
                'aisafetyhazards_attempt',
                'aisafetyhazardsid = :instanceid AND userid = :userid AND state = :finished AND playmode = :mode',
                [
                    'instanceid' => $instance->id,
                    'userid' => $userid,
                    'finished' => 'finished',
                    'mode' => $mode,
                ],
                'timefinish DESC, id DESC',
                '*',
                IGNORE_MISSING
            );
        }
        $grade = self::gradebook_percent('aisafetyhazards', (int)$cm->course, (int)$instance->id, $userid);
        $score = $grade['score'] ?? ($latest && $latest->score !== null ? (float)$latest->score : null);
        $passmark = $mode === \mod_aisafetyhazards\local\learning::MODE_TEST && (int)$instance->passmark > 0
            ? (float)$instance->passmark
            : $grade['pass_mark'];
        $attempts = (int)($summary->attempts ?? 0);
        $completed = (int)($summary->completed ?? 0);
        $status = $completed > 0 ? 'complete' : ($attempts > 0 ? 'in_progress' : 'not_started');

        return self::result(
            $status,
            $score === null ? null : round((float)$score, 2),
            $passmark === null ? null : round((float)$passmark, 2),
            $attempts,
            (int)($summary->timeseconds ?? 0),
            empty($summary->lastactivity) ? null : (int)$summary->lastactivity,
            self::outcome_ids($cmid, self::component()),
            (new \moodle_url('/mod/aisafetyhazards/report.php', ['id' => $cmid]))->out(false)
        );
    }
}
