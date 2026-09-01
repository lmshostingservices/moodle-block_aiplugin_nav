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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * External service to save cache purge schedule.
 *
 * @package    block_aiplugin_nav
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_aiplugin_nav\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;

/**
 * External service to save cache purge schedule.
 */
class save_purge_schedule extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'enabled' => new external_value(PARAM_BOOL, 'Whether scheduled purge is enabled'),
            'schedule_type' => new external_value(PARAM_TEXT, 'Schedule type (daily, weekly)'),
            'schedule_time' => new external_value(PARAM_TEXT, 'Scheduled time (HH:MM)'),
            'schedule_day' => new external_value(PARAM_INT, 'Day of week for weekly (0=Sunday, 6=Saturday)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Save cache purge schedule.
     *
     * @param bool $enabled Whether scheduled purge is enabled
     * @param string $scheduletype Schedule type (daily, weekly)
     * @param string $scheduletime Scheduled time (HH:MM)
     * @param int $scheduleday Day of week for weekly schedule
     * @return array
     */
    public static function execute($enabled, $scheduletype, $scheduletime, $scheduleday = 0) {
        global $DB;

        // Check permissions.
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'enabled' => $enabled,
            'schedule_type' => $scheduletype,
            'schedule_time' => $scheduletime,
            'schedule_day' => $scheduleday,
        ]);

        // Save settings.
        set_config('purge_schedule_enabled', $params['enabled'] ? 1 : 0, 'block_aiplugin_nav');
        set_config('purge_schedule_type', $params['schedule_type'], 'block_aiplugin_nav');
        set_config('purge_schedule_time', $params['schedule_time'], 'block_aiplugin_nav');
        set_config('purge_schedule_day', $params['schedule_day'], 'block_aiplugin_nav');

        // Update the scheduled task timing.
        $task = \core\task\manager::get_scheduled_task('\\block_aiplugin_nav\\task\\purge_caches_task');
        if ($task) {
            // Parse time.
            $parts = explode(':', $params['schedule_time']);
            $hour = isset($parts[0]) ? (int)$parts[0] : 3;
            $minute = isset($parts[1]) ? (int)$parts[1] : 0;
            if ($hour < 0 || $hour > 23) {
                $hour = 3;
            }
            if ($minute < 0 || $minute > 59) {
                $minute = 0;
            }

            // Set schedule based on type.
            if ($params['schedule_type'] === 'weekly') {
                $task->set_minute($minute);
                $task->set_hour($hour);
                $task->set_day('*');
                $task->set_month('*');
                $task->set_day_of_week($params['schedule_day']);
            } else {
                // Daily.
                $task->set_minute($minute);
                $task->set_hour($hour);
                $task->set_day('*');
                $task->set_month('*');
                $task->set_day_of_week('*');
            }

            $task->set_disabled(!$params['enabled']);
            \core\task\manager::configure_scheduled_task($task);
        }

        return [
            'success' => true,
            'message' => get_string('schedule_saved', 'block_aiplugin_nav'),
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the save was successful'),
            'message' => new external_value(PARAM_TEXT, 'Status message'),
        ]);
    }
}
