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
 * Class progress from native activity completion.
 *
 * @package    block_aiplugin_nav
 * @copyright 2026 LMS Labs
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_aiplugin_nav\local\course_report;

defined('MOODLE_INTERNAL') || die();

/**
 * Teacher and learner progress from Moodle's native activity completion records.
 *
 * Generated lesson activities opt into view completion only when their activity
 * module advertises FEATURE_COMPLETION_TRACKS_VIEWS. Course-level setup activities
 * are deliberately excluded from learner progress.
 *
 * @package block_aiplugin_nav
 */
final class progress {
    /** @var string[] Activity module names used by AI Course Builder lessons. */
    private const LESSON_MODULES = [
        'visuallearning', 'aiknowledgecheck', 'aiactivities', 'courseinfo',
        'learningmapping', 'contentcreator', 'slides', 'aisoftskills',
        'aibranchedscenario', 'aisafetyhazards', 'aianatomy',
    ];

    /**
     * Return a teacher's class grid with a bounded number of database reads.
     *
     * @param int $courseid
     * @return array{students:array, lessons:array, summary:array}
     */
    public static function for_course(int $courseid): array {
        global $DB;

        $course = get_course($courseid);
        $context = \context_course::instance($courseid);
        $allusers = get_enrolled_users(
            $context,
            '',
            0,
            'u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename',
            'u.lastname, u.firstname'
        );
        $teachers = get_enrolled_users($context, 'moodle/course:update', 0, 'u.id');
        $teacherids = array_fill_keys(array_keys($teachers), true);
        $students = array_values(array_filter(
            $allusers,
            static fn(\stdClass $user): bool => !isset($teacherids[$user->id])
        ));

        $moduleids = [];
        foreach (self::LESSON_MODULES as $modname) {
            $moduleid = $DB->get_field('modules', 'id', ['name' => $modname]);
            if ($moduleid) {
                $moduleids[] = (int)$moduleid;
            }
        }
        if (!$moduleids) {
            return ['students' => [], 'lessons' => [], 'summary' => self::empty_summary()];
        }
        [$modulesql, $moduleparams] = $DB->get_in_or_equal($moduleids, SQL_PARAMS_NAMED, 'mod');
        $activities = $DB->get_records_sql(
            "SELECT cm.id AS cmid, cm.completion, cm.completionview, cm.instance,
                    m.name AS modname, cs.section AS sectionnumber, cs.name AS sectionname
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
               JOIN {course_sections} cs ON cs.id = cm.section
              WHERE cm.course = :courseid AND cm.module $modulesql AND cm.visible = 1
           ORDER BY cs.section ASC, cm.id ASC",
            ['courseid' => $courseid] + $moduleparams
        );
        $modinfo = get_fast_modinfo($courseid);
        $lessons = [];
        $cmids = [];
        foreach ($activities as $activity) {
            $cmdata = $modinfo->get_cm((int)$activity->cmid);
            if (!$cmdata) {
                continue;
            }
            $sectionname = trim((string)$activity->sectionname);
            if (!preg_match('/^Lesson\s+\d+\b/i', $sectionname)) {
                continue;
            }
            if (!isset($lessons[(int)$activity->sectionnumber])) {
                $lessons[(int)$activity->sectionnumber] = [
                    'sectionnumber' => (int)$activity->sectionnumber,
                    'title' => format_string($sectionname),
                    'activities' => [],
                ];
            }
            $activity->trackable = (int)$activity->completion !== COMPLETION_TRACKING_NONE;
            $activity->activityname = $cmdata->name;
            $activity->url = $cmdata->url ? $cmdata->url->out(false) : '';
            $lessons[(int)$activity->sectionnumber]['activities'][] = $activity;
            if ($activity->trackable) {
                $cmids[] = (int)$activity->cmid;
            }
        }
        $lessons = array_values($lessons);

        $userids = array_map(static fn(\stdClass $user): int => (int)$user->id, $students);
        $states = self::completion_states($cmids, $userids);
        $knowledgechecks = knowledge_check_summary::for_course($courseid);
        $studentsout = [];
        $totals = ['complete' => 0, 'inprogress' => 0, 'notstarted' => 0];
        foreach ($students as $student) {
            $completed = 0;
            $trackablecount = 0;
            $activitystates = [];
            $weekstart = strtotime('monday this week 00:00');
            $completedthisweek = 0;
            foreach ($lessons as $lesson) {
                foreach ($lesson['activities'] as $activity) {
                    $cmid = (int)$activity->cmid;
                    $state = $activity->trackable
                        ? (int)($states[$student->id][$cmid]['state'] ?? COMPLETION_INCOMPLETE)
                        : null;
                    $iscomplete = in_array($state, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true);
                    if ($activity->trackable) {
                        $trackablecount++;
                        $completed += $iscomplete ? 1 : 0;
                        if ($iscomplete && (int)($states[$student->id][$cmid]['modified'] ?? 0) >= $weekstart) {
                            $completedthisweek++;
                        }
                    }
                    $activitystates[$cmid] = [
                        'state' => $state,
                        'complete' => $iscomplete,
                        'failed' => $state === COMPLETION_COMPLETE_FAIL,
                    ];
                }
            }
            $percent = self::completion_percent($completed, $trackablecount);
            $latestcheck = $knowledgechecks[(int)$student->id] ?? null;
            $status = $percent === null ? 'nottracked'
                : ($percent === 100 ? 'complete' : ($percent > 0 ? 'inprogress' : 'notstarted'));
            if ($status !== 'nottracked') {
                $totals[$status === 'complete' ? 'complete' : ($status === 'inprogress' ? 'inprogress' : 'notstarted')]++;
            }
            $studentsout[] = [
                'id' => (int)$student->id,
                'name' => fullname($student),
                'percent' => $percent,
                'completed' => $completed,
                'trackable' => $trackablecount,
                'status' => $status,
                'completedthisweek' => $completedthisweek,
                'activitystates' => $activitystates,
                'knowledgecheck' => $latestcheck,
            ];
        }
        return [
            'students' => $studentsout,
            'lessons' => $lessons,
            'summary' => $totals,
            'course' => $course,
        ];
    }

    /**
     * Calculate activity completion independently of assessment grades.
     *
     * @param int $completed
     * @param int $trackable
     * @return int|null
     */
    public static function completion_percent(int $completed, int $trackable): ?int {
        if ($trackable <= 0) {
            return null;
        }
        return (int)round(max(0, min($completed, $trackable)) / $trackable * 100);
    }

    /**
     * Return one learner's view without question or class-comparison data.
     *
     * @param int $courseid
     * @param int $studentid
     * @return array|null
     */
    public static function for_student(int $courseid, int $studentid): ?array {
        $report = self::for_course($courseid);
        foreach ($report['students'] as $student) {
            if ($student['id'] === $studentid) {
                $student['lessons'] = [];
                foreach ($report['lessons'] as $lesson) {
                    $activities = [];
                    foreach ($lesson['activities'] as $activity) {
                        $state = $student['activitystates'][(int)$activity->cmid] ?? null;
                        $activities[] = [
                            'name' => format_string((string)$activity->activityname),
                            'component' => (string)$activity->modname,
                            'url' => $activity->url,
                            'tracked' => (bool)$activity->trackable,
                            'complete' => (bool)($state['complete'] ?? false),
                            'failed' => (bool)($state['failed'] ?? false),
                        ];
                    }
                    $trackable = array_values(array_filter($activities, static fn(array $a): bool => $a['tracked']));
                    $completecount = count(array_filter($trackable, static fn(array $a): bool => $a['complete']));
                    $student['lessons'][] = [
                        'title' => $lesson['title'],
                        'activities' => $activities,
                        'percent' => count($trackable) ? (int)round($completecount / count($trackable) * 100) : null,
                    ];
                }
                unset($student['activitystates']);
                return $student;
            }
        }
        return null;
    }

    /**
     * Bulk-load completion records once for the course grid.
     *
     * @param int[] $cmids
     * @param int[] $userids
     * @return array
     */
    private static function completion_states(array $cmids, array $userids): array {
        global $DB;
        if (!$cmids || !$userids) {
            return [];
        }
        [$cmsql, $cmparams] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED, 'cm');
        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'user');
        $rows = $DB->get_records_sql(
            "SELECT id, userid, coursemoduleid, completionstate, timemodified
               FROM {course_modules_completion}
              WHERE coursemoduleid $cmsql AND userid $usersql",
            $cmparams + $userparams
        );
        $states = [];
        foreach ($rows as $row) {
            $states[(int)$row->userid][(int)$row->coursemoduleid] = [
                'state' => (int)$row->completionstate,
                'modified' => (int)$row->timemodified,
            ];
        }
        return $states;
    }

    /**
     * Empty totals.
     *
     * @return array
     */
    private static function empty_summary(): array {
        return ['complete' => 0, 'inprogress' => 0, 'notstarted' => 0];
    }
}
