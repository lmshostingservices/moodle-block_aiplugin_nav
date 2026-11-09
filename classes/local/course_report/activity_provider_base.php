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

namespace block_aiplugin_nav\local\course_report;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared read-only lookups for plugin-specific Course Report providers.
 *
 * @package    block_aiplugin_nav
 * @copyright  2026 LMS Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class activity_provider_base implements activity_provider {
    /**
     * Read the Moodle gradebook result as percentages.
     *
     * @param string $module The Moodle activity module name, without the mod_ prefix.
     * @param int $courseid
     * @param int $instanceid
     * @param int $userid
     * @return array{score:?float,pass_mark:?float}
     */
    protected static function gradebook_percent(string $module, int $courseid, int $instanceid, int $userid): array {
        global $DB;

        $record = $DB->get_record_sql(
            "SELECT gg.finalgrade, gi.gradepass, gi.grademax
               FROM {grade_items} gi
          LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :userid
              WHERE gi.courseid = :courseid
                AND gi.itemtype = 'mod'
                AND gi.itemmodule = :module
                AND gi.iteminstance = :instanceid
                AND gi.itemnumber = 0",
            [
                'userid' => $userid,
                'courseid' => $courseid,
                'module' => $module,
                'instanceid' => $instanceid,
            ],
            IGNORE_MISSING
        );
        if (!$record || (float)$record->grademax <= 0) {
            return ['score' => null, 'pass_mark' => null];
        }

        $score = $record->finalgrade === null
            ? null
            : round((float)$record->finalgrade / (float)$record->grademax * 100, 2);
        $passmark = (float)$record->gradepass > 0
            ? round((float)$record->gradepass / (float)$record->grademax * 100, 2)
            : null;
        return ['score' => $score, 'pass_mark' => $passmark];
    }

    /**
     * Resolve the builder outcome ids attached to an activity, if the builder is installed.
     *
     * @param int $cmid
     * @param string $component
     * @return int[]
     */
    protected static function outcome_ids(int $cmid, string $component): array {
        global $DB;

        $manager = $DB->get_manager();
        foreach (['local_aicoursebuilder_job', 'local_aicoursebuilder_lesson', 'local_aicoursebuilder_outcome'] as $table) {
            if (!$manager->table_exists(new \xmldb_table($table))) {
                return [];
            }
        }

        $lesson = $DB->get_record_sql(
            "SELECT l.programid, l.outcomesjson
               FROM {local_aicoursebuilder_job} j
               JOIN {local_aicoursebuilder_lesson} l ON l.id = j.lessonid
              WHERE j.cmid = :cmid AND j.component = :component
           ORDER BY j.id DESC",
            ['cmid' => $cmid, 'component' => $component],
            IGNORE_MISSING
        );
        if (!$lesson) {
            return [];
        }

        $references = json_decode($lesson->outcomesjson, true);
        if (!is_array($references) || !$references) {
            return [];
        }
        $rows = $DB->get_records('local_aicoursebuilder_outcome', ['programid' => $lesson->programid]);
        $ids = [];
        foreach ($rows as $outcome) {
            $identifiers = [];
            if (!empty($outcome->code)) {
                $identifiers[] = (string)$outcome->code;
            }
            $identifiers[] = (string)$outcome->outcometext;
            foreach ($references as $reference) {
                if (is_array($reference)) {
                    $reference = $reference['code'] ?? $reference['text'] ?? '';
                }
                if (in_array((string)$reference, $identifiers, true)) {
                    $ids[] = (int)$outcome->id;
                    break;
                }
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * Standard return shape for the Course Report.
     *
     * @param string $status
     * @param float|null $score
     * @param float|null $passmark
     * @param int $attempts
     * @param int $timeseconds
     * @param int|null $lastactivity
     * @param int[] $outcomeids
     * @param string $reporturl
     * @return array
     */
    protected static function result(
        string $status,
        ?float $score,
        ?float $passmark,
        int $attempts,
        int $timeseconds,
        ?int $lastactivity,
        array $outcomeids,
        string $reporturl
    ): array {
        return [
            'status' => $status,
            'score' => $score,
            'pass_mark' => $passmark,
            'attempts' => $attempts,
            'time_seconds' => max(0, $timeseconds),
            'last_activity' => $lastactivity,
            'outcome_ids' => array_values(array_unique(array_map('intval', $outcomeids))),
            'report_url' => $reporturl,
        ];
    }
}
