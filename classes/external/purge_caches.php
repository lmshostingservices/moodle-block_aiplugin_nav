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
 * External service to purge all caches.
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
 * External service to purge all caches.
 */
class purge_caches extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([]);
    }

    /**
     * Purge all caches.
     *
     * @return array
     */
    public static function execute() {
        global $DB, $USER;

        // Check permissions.
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        // Purge all caches — use the same complete purge Moodle's own admin page uses.
        // Purge_all_caches() resets MUC caches, theme/CSS revisions, language pack cache,
        // JS/template caches and more. cache_helper::purge_all() only does MUC caches.
        purge_all_caches();

        // Log the purge.
        $record = new \stdClass();
        $record->purge_type = 'manual';
        $record->purged_at = time();
        $record->purged_by = $USER->id;
        $DB->insert_record('block_aiplugin_nav_purge', $record);

        // Format time using Moodle's locale settings.
        $formattedtime = userdate($record->purged_at, get_string('strftimedatetimeshort', 'langconfig'));

        return [
            'success' => true,
            'message' => get_string('purge_success', 'block_aiplugin_nav'),
            'purged_at' => $record->purged_at,
            'formatted_time' => $formattedtime,
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the purge was successful'),
            'message' => new external_value(PARAM_TEXT, 'Status message'),
            'purged_at' => new external_value(PARAM_INT, 'Timestamp when caches were purged'),
            'formatted_time' => new external_value(PARAM_TEXT, 'Formatted purge time'),
        ]);
    }
}
