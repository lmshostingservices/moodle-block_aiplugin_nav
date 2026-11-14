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
 * External service that saves the current user's plugin spotlight state.
 *
 * @package    block_aiplugin_nav
 * @copyright  2026 LMS Labs
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
 * Saves open, collapsed or off for the current user.
 *
 * The block's own service is used rather than core_user_set_user_preferences so the choice
 * is stored on every supported Moodle version (4.0 to 5.x) and a failed save can be retried.
 * It only ever writes the calling user's own preference.
 */
class set_spotlight_state extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'state' => new external_value(PARAM_ALPHA, 'open, collapsed or off'),
        ]);
    }

    /**
     * Save the state.
     *
     * @param string $state
     * @return array
     */
    public static function execute($state) {
        $params = self::validate_parameters(self::execute_parameters(), ['state' => $state]);
        self::validate_context(\context_system::instance());
        $saved = \block_aiplugin_nav\local\spotlight::save_user_state($params['state']);
        return ['state' => $saved];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'state' => new external_value(PARAM_ALPHA, 'The state saved'),
        ]);
    }
}
