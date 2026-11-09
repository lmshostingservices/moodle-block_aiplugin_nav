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
 * Course Report helper for LMS Labs plugins.
 *
 * @copyright 2026 LMS Labs
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_aiplugin_nav\local\course_report;

defined('MOODLE_INTERNAL') || die();

/**
 * Read-only diagnostics for AI Learning Activities and Branched Scenario.
 *
 * @package block_aiplugin_nav
 */
final class learning_activity_diagnostics {
    /**
     * Summarise wrong Learning Activities items, abandoned attempts, long sessions,
     * and Branched Scenario attempts ending in the high-risk band.
     *
     * @param int $courseid
     * @return array
     */
    public static function for_course(int $courseid): array {
        global $DB;
        $result = [
            'wrongitems' => [],
            'abandoned' => [],
            'longsessions' => [],
            'worstendings' => [],
        ];
        $dbman = $DB->get_manager();
        $module = $DB->get_record('modules', ['name' => 'aiactivities'], 'id');
        if ($module && $dbman->table_exists(new \xmldb_table('aiactivities_attempts'))) {
            self::learning_activities($courseid, (int)$module->id, $result);
        }
        $scenario = $DB->get_record('modules', ['name' => 'aibranchedscenario'], 'id');
        if ($scenario && $dbman->table_exists(new \xmldb_table('aibranchedscenario_attempts'))) {
            self::branched_scenarios($courseid, (int)$scenario->id, $result);
        }
        return $result;
    }

    /**
     * Helper.
     *
     * @param int $courseid
     * @param int $moduleid
     * @param array $result
     * @return void
     */
    private static function learning_activities(int $courseid, int $moduleid, array &$result): void {
        global $DB;
        $activities = $DB->get_records_sql(
            "SELECT cm.id AS cmid, a.id AS instanceid, a.name, a.activitiesjson, cs.name AS sectionname
               FROM {course_modules} cm
               JOIN {aiactivities} a ON a.id = cm.instance
               JOIN {course_sections} cs ON cs.id = cm.section
              WHERE cm.course = :courseid AND cm.module = :moduleid AND cm.visible = 1",
            ['courseid' => $courseid, 'moduleid' => $moduleid]
        );
        $minutes = [];
        $minutetable = new \xmldb_table('format_aicourse_actminutes');
        if ($DB->get_manager()->table_exists($minutetable)) {
            foreach ($activities as $activity) {
                $minutes[(int)$activity->cmid] = (int)$DB->get_field(
                    'format_aicourse_actminutes',
                    'minutes',
                    ['cmid' => $activity->cmid, 'courseid' => $courseid]
                );
            }
        }
        foreach ($activities as $activity) {
            $manifest = [];
            if (class_exists('\\mod_aiactivities\\manifest_storage')) {
                $decoded = json_decode(
                    \mod_aiactivities\manifest_storage::decompress((string)$activity->activitiesjson),
                    true
                );
                $manifest = is_array($decoded) ? $decoded : [];
            }
            $attempts = $DB->get_records_sql(
                "SELECT id, userid, progressjson, answerstatsjson, completedcount, totalcount,
                        status, timecreated, timemodified
                   FROM {aiactivities_attempts}
                  WHERE aiactivitiesid = :activityid
               ORDER BY timemodified DESC, id DESC",
                ['activityid' => $activity->instanceid],
                0,
                5000
            );
            $itemstats = [];
            $abandoned = 0;
            $completecount = 0;
            $completeseconds = [];
            foreach ($attempts as $attempt) {
                $duration = max(0, (int)$attempt->timemodified - (int)$attempt->timecreated);
                if ((int)$attempt->status === 1) {
                    $completecount++;
                    $completeseconds[] = $duration;
                } else if ((int)$attempt->timemodified < time() - HOURSECS) {
                    $abandoned++;
                }
                $stats = json_decode((string)($attempt->answerstatsjson ?? ''), true);
                if (!is_array($stats)) {
                    continue;
                }
                foreach ($stats as $index => $row) {
                    if (!is_numeric($index) || !is_array($row)) {
                        continue;
                    }
                    $index = (int)$index;
                    $wrong = max(0, (int)($row['wrong'] ?? 0));
                    $correct = max(0, (int)($row['correct'] ?? 0));
                    if (!isset($itemstats[$index])) {
                        $item = $manifest[$index] ?? [];
                        $itemstats[$index] = [
                            'index' => $index + 1,
                            'title' => format_string((string)($item['title'] ?? $item['question'] ?? ('Item ' . ($index + 1)))),
                            'wrong' => 0,
                            'responses' => 0,
                        ];
                    }
                    $itemstats[$index]['wrong'] += $wrong;
                    $itemstats[$index]['responses'] += $wrong + $correct;
                }
            }
            foreach ($itemstats as &$itemstat) {
                $itemstat['wrongpercent'] = $itemstat['responses']
                    ? round($itemstat['wrong'] / $itemstat['responses'] * 100, 1)
                    : 0;
                $itemstat['activity'] = format_string((string)$activity->name);
                $itemstat['section'] = format_string((string)$activity->sectionname);
                $itemstat['cmid'] = (int)$activity->cmid;
                $itemstat['component'] = 'aiactivities';
            }
            unset($itemstat);
            array_push($result['wrongitems'], ...array_values($itemstats));
            if ($abandoned > 0) {
                $result['abandoned'][] = [
                    'activity' => format_string((string)$activity->name),
                    'section' => format_string((string)$activity->sectionname),
                    'cmid' => (int)$activity->cmid,
                    'count' => $abandoned,
                    'component' => 'aiactivities',
                ];
            }
            $expected = (int)($minutes[(int)$activity->cmid] ?? 0);
            if ($expected > 0 && $completeseconds) {
                $average = array_sum($completeseconds) / count($completeseconds);
                if ($average > $expected * 60 * 1.5) {
                    $result['longsessions'][] = [
                        'activity' => format_string((string)$activity->name),
                        'section' => format_string((string)$activity->sectionname),
                        'cmid' => (int)$activity->cmid,
                        'component' => 'aiactivities',
                        'expectedminutes' => $expected,
                        'averageminutes' => round($average / 60, 1),
                        'completedattempts' => $completecount,
                    ];
                }
            }
        }
        usort($result['wrongitems'], static fn(array $a, array $b): int =>
            $b['wrong'] <=> $a['wrong'] ?: $b['wrongpercent'] <=> $a['wrongpercent']);
    }

    /**
     * Helper.
     *
     * Helper.
     *
     * @param int $courseid
     * @param int $moduleid
     * @param array $result
     * @return void
     */
    private static function branched_scenarios(int $courseid, int $moduleid, array &$result): void {
        global $DB;
        $rows = $DB->get_recordset_sql(
            "SELECT cm.id AS cmid, s.id AS scenarioid, s.name,
                    cs.name AS sectionname, e.nodeid, e.choiceid,
                    COUNT(e.id) AS worstcount, MAX(a.timefinished) AS lastfinished
               FROM {course_modules} cm
               JOIN {aibranchedscenario} s ON s.id = cm.instance
               JOIN {course_sections} cs ON cs.id = cm.section
               JOIN {aibranchedscenario_attempts} a
                 ON a.scenarioid = s.id AND a.status = :finished AND a.outcome = :worst
               JOIN {aibranchedscenario_events} e ON e.attemptid = a.id
              WHERE cm.course = :courseid AND cm.module = :moduleid AND cm.visible = 1
           GROUP BY cm.id, s.id, s.name, cs.name, e.nodeid, e.choiceid
           ORDER BY worstcount DESC, lastfinished DESC",
            [
                'finished' => 'finished',
                'worst' => 'highrisk',
                'courseid' => $courseid,
                'moduleid' => $moduleid,
            ]
        );
        $labels = [];
        foreach ($rows as $row) {
            if (!isset($labels[(int)$row->scenarioid])) {
                $scenariojson = $DB->get_field('aibranchedscenario', 'scenariojson', ['id' => $row->scenarioid]);
                $definition = json_decode((string)$scenariojson, true);
                foreach (($definition['nodes'] ?? []) as $node) {
                    if (!is_array($node) || empty($node['id'])) {
                        continue;
                    }
                    $labels[(int)$row->scenarioid][(string)$node['id']] = [
                        'title' => (string)($node['title'] ?? $node['summary'] ?? $node['id']),
                        'choices' => [],
                    ];
                    foreach (($node['choices'] ?? []) as $choice) {
                        if (is_array($choice) && !empty($choice['id'])) {
                            $labels[(int)$row->scenarioid][(string)$node['id']]['choices'][(string)$choice['id']]
                                = (string)($choice['text'] ?? $choice['label'] ?? $choice['id']);
                        }
                    }
                }
            }
            $node = $labels[(int)$row->scenarioid][(string)$row->nodeid] ?? [];
            $choice = $node['choices'][(string)$row->choiceid] ?? (string)$row->choiceid;
            $result['worstendings'][] = [
                'activity' => format_string((string)$row->name),
                'section' => format_string((string)$row->sectionname),
                'cmid' => (int)$row->cmid,
                'count' => (int)$row->worstcount,
                'lastfinished' => (int)$row->lastfinished,
                'component' => 'aibranchedscenario',
                'decision' => format_string((string)($node['title'] ?? $row->nodeid)),
                'choice' => format_string((string)$choice),
            ];
        }
        $rows->close();
    }
}
