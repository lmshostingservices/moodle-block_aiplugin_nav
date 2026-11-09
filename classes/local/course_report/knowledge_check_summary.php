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
 * Latest Knowledge Check scores per student.
 *
 * @copyright 2026 LMS Labs
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_aiplugin_nav\local\course_report;

defined('MOODLE_INTERNAL') || die();

/**
 * Latest completed AI Knowledge Check scores for each learner in a course.
 *
 * @package block_aiplugin_nav
 */
final class knowledge_check_summary {
    /**
     * Read the latest completed attempt per learner and activity, then aggregate scores.
     *
     * @param int $courseid
     * @return array<int, array{correct:int,total:int,percent:float}>
     */
    public static function for_course(int $courseid): array {
        global $DB;

        $manager = $DB->get_manager();
        foreach (['aiknowledgecheck', 'aiknowledgecheck_attempts'] as $tablename) {
            if (!$manager->table_exists(new \xmldb_table($tablename))) {
                return [];
            }
        }

        $attempts = $DB->get_records_sql(
            "SELECT a.id, a.userid, a.correctcount, a.totalcount, a.timeended,
                    cm.id AS cmid
               FROM {aiknowledgecheck_attempts} a
               JOIN {aiknowledgecheck} kc ON kc.id = a.aiknowledgecheckid
               JOIN {course_modules} cm ON cm.instance = kc.id
               JOIN {modules} m ON m.id = cm.module AND m.name = 'aiknowledgecheck'
              WHERE cm.course = :courseid AND cm.visible = 1 AND a.timeended > 0
           ORDER BY a.userid ASC, cm.id ASC, a.timeended DESC, a.id DESC",
            ['courseid' => $courseid]
        );

        return self::summarize_attempts($attempts);
    }

    /**
     * Keep only the newest completed attempt for each learner and activity.
     *
     * @param array $attempts Objects with id, userid, cmid, correctcount, totalcount, timeended.
     * @return array<int, array{correct:int,total:int,percent:float}>
     */
    public static function summarize_attempts(array $attempts): array {
        $latest = [];
        foreach ($attempts as $attempt) {
            $userid = (int)$attempt->userid;
            $cmid = (int)$attempt->cmid;
            if ($userid <= 0 || $cmid <= 0) {
                continue;
            }
            $current = $latest[$userid][$cmid] ?? null;
            $isnewer = !$current
                || (int)$attempt->timeended > (int)$current->timeended
                || ((int)$attempt->timeended === (int)$current->timeended
                    && (int)$attempt->id > (int)$current->id);
            if ($isnewer) {
                $latest[$userid][$cmid] = $attempt;
            }
        }

        $summaries = [];
        foreach ($latest as $userid => $activities) {
            $correct = 0;
            $total = 0;
            foreach ($activities as $attempt) {
                $attempttotal = max(0, (int)$attempt->totalcount);
                $attemptcorrect = max(0, min((int)$attempt->correctcount, $attempttotal));
                $correct += $attemptcorrect;
                $total += $attempttotal;
            }
            if ($total > 0) {
                $summaries[(int)$userid] = [
                    'correct' => $correct,
                    'total' => $total,
                    'percent' => round($correct / $total * 100, 1),
                ];
            }
        }
        return $summaries;
    }
}
