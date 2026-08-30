<?php
// This file is part of Moodle - https://moodle.org/
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
 * External service to get cache purge status.
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
 * External service to get cache purge status.
 */
class get_purge_status extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([]);
    }

    /**
     * Get cache purge status.
     *
     * @return array
     */
    public static function execute() {
        global $DB;

        // Check permissions.
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        // Get last manual purge.
        $lastmanual = $DB->get_record_sql(
            "SELECT * FROM {block_aiplugin_nav_purge} WHERE purge_type = 'manual' ORDER BY purged_at DESC",
            null,
            IGNORE_MULTIPLE
        );

        // Get last scheduled purge.
        $lastscheduled = $DB->get_record_sql(
            "SELECT * FROM {block_aiplugin_nav_purge} WHERE purge_type = 'scheduled' ORDER BY purged_at DESC",
            null,
            IGNORE_MULTIPLE
        );

        // Get schedule settings.
        $scheduleenabled = get_config('block_aiplugin_nav', 'purge_schedule_enabled');
        $scheduletype = get_config('block_aiplugin_nav', 'purge_schedule_type');
        $scheduletime = get_config('block_aiplugin_nav', 'purge_schedule_time');

        return [
            'last_manual_purge' => $lastmanual ? $lastmanual->purged_at : 0,
            'last_scheduled_purge' => $lastscheduled ? $lastscheduled->purged_at : 0,
            'schedule_enabled' => (bool) $scheduleenabled,
            'schedule_type' => $scheduletype ?: 'daily',
            'schedule_time' => $scheduletime ?: '03:00',
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'last_manual_purge' => new external_value(PARAM_INT, 'Timestamp of last manual purge'),
            'last_scheduled_purge' => new external_value(PARAM_INT, 'Timestamp of last scheduled purge'),
            'schedule_enabled' => new external_value(PARAM_BOOL, 'Whether scheduled purge is enabled'),
            'schedule_type' => new external_value(PARAM_TEXT, 'Schedule type (daily, weekly)'),
            'schedule_time' => new external_value(PARAM_TEXT, 'Scheduled time (HH:MM)'),
        ]);
    }
}
