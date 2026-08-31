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
 * External functions for custom links management.
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

class custom_links extends external_api {
    /**
     * Returns description of save_custom_link parameters.
     */
    public static function save_custom_link_parameters() {
        return new external_function_parameters(array(
            'name' => new external_value(PARAM_TEXT, 'Link name'),
            'url' => new external_value(PARAM_URL, 'Link URL'),
            'icon' => new external_value(PARAM_ALPHANUMEXT, 'Icon identifier'),
        ));
    }

    /**
     * Save a custom link.
     */
    public static function save_custom_link($name, $url, $icon) {
        global $USER;
        self::validate_context(\context_system::instance());

        $params = self::validate_parameters(self::save_custom_link_parameters(), array(
            'name' => $name,
            'url' => $url,
            'icon' => $icon,
        ));

        // Sanitize inputs
        $name = clean_param($params['name'], PARAM_TEXT);
        $url = clean_param($params['url'], PARAM_URL);
        $icon = clean_param($params['icon'], PARAM_ALPHANUMEXT);

        // Validate URL
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \moodle_exception('invalidurl', 'error');
        }

        // Get existing links
        $links_json = get_user_preferences('block_aiplugin_nav_custom_links', '[]', $USER->id);
        $links = json_decode($links_json, true);
        if (!is_array($links)) {
            $links = array();
        }

        // Limit to 20 custom links
        if (count($links) >= 20) {
            throw new \moodle_exception('Too many custom links. Maximum is 20.');
        }

        // Add new link
        $links[] = array(
            'name' => substr($name, 0, 50),
            'url' => $url,
            'icon' => $icon,
        );

        // Save
        set_user_preference('block_aiplugin_nav_custom_links', json_encode($links), $USER->id);

        return array('success' => true);
    }

    /**
     * Returns description of save_custom_link return value.
     */
    public static function save_custom_link_returns() {
        return new external_single_structure(array(
            'success' => new external_value(PARAM_BOOL, 'Success status'),
        ));
    }

    /**
     * Returns description of delete_custom_link parameters.
     */
    public static function delete_custom_link_parameters() {
        return new external_function_parameters(array(
            'index' => new external_value(PARAM_INT, 'Link index to delete'),
        ));
    }

    /**
     * Delete a custom link.
     */
    public static function delete_custom_link($index) {
        global $USER;
        self::validate_context(\context_system::instance());

        $params = self::validate_parameters(self::delete_custom_link_parameters(), array(
            'index' => $index,
        ));

        // Get existing links
        $links_json = get_user_preferences('block_aiplugin_nav_custom_links', '[]', $USER->id);
        $links = json_decode($links_json, true);
        if (!is_array($links)) {
            $links = array();
        }

        // Remove link at index
        if (isset($links[$params['index']])) {
            array_splice($links, $params['index'], 1);
            set_user_preference('block_aiplugin_nav_custom_links', json_encode($links), $USER->id);
        }

        return array('success' => true);
    }

    /**
     * Returns description of delete_custom_link return value.
     */
    public static function delete_custom_link_returns() {
        return new external_single_structure(array(
            'success' => new external_value(PARAM_BOOL, 'Success status'),
        ));
    }

    /**
     * Returns description of save_custom_report parameters.
     */
    public static function save_custom_report_parameters() {
        return new external_function_parameters(array(
            'name' => new external_value(PARAM_TEXT, 'Report name'),
            'url' => new external_value(PARAM_URL, 'Report URL'),
            'icon' => new external_value(PARAM_ALPHANUMEXT, 'Icon identifier'),
        ));
    }

    /**
     * Save a custom report.
     */
    public static function save_custom_report($name, $url, $icon) {
        global $USER;
        self::validate_context(\context_system::instance());

        $params = self::validate_parameters(self::save_custom_report_parameters(), array(
            'name' => $name,
            'url' => $url,
            'icon' => $icon,
        ));

        // Sanitize inputs
        $name = clean_param($params['name'], PARAM_TEXT);
        $url = clean_param($params['url'], PARAM_URL);
        $icon = clean_param($params['icon'], PARAM_ALPHANUMEXT);

        // Validate URL
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \moodle_exception('invalidurl', 'error');
        }

        // Get existing reports
        $reports_json = get_user_preferences('block_aiplugin_nav_custom_reports', '[]', $USER->id);
        $reports = json_decode($reports_json, true);
        if (!is_array($reports)) {
            $reports = array();
        }

        // Limit to 20 custom reports
        if (count($reports) >= 20) {
            throw new \moodle_exception('Too many custom reports. Maximum is 20.');
        }

        // Add new report
        $reports[] = array(
            'name' => substr($name, 0, 50),
            'url' => $url,
            'icon' => $icon,
        );

        // Save
        set_user_preference('block_aiplugin_nav_custom_reports', json_encode($reports), $USER->id);

        return array('success' => true);
    }

    /**
     * Returns description of save_custom_report return value.
     */
    public static function save_custom_report_returns() {
        return new external_single_structure(array(
            'success' => new external_value(PARAM_BOOL, 'Success status'),
        ));
    }

    /**
     * Returns description of delete_custom_report parameters.
     */
    public static function delete_custom_report_parameters() {
        return new external_function_parameters(array(
            'index' => new external_value(PARAM_INT, 'Report index to delete'),
        ));
    }

    /**
     * Delete a custom report.
     */
    public static function delete_custom_report($index) {
        global $USER;
        self::validate_context(\context_system::instance());

        $params = self::validate_parameters(self::delete_custom_report_parameters(), array(
            'index' => $index,
        ));

        // Get existing reports
        $reports_json = get_user_preferences('block_aiplugin_nav_custom_reports', '[]', $USER->id);
        $reports = json_decode($reports_json, true);
        if (!is_array($reports)) {
            $reports = array();
        }

        // Remove report at index
        if (isset($reports[$params['index']])) {
            array_splice($reports, $params['index'], 1);
            set_user_preference('block_aiplugin_nav_custom_reports', json_encode($reports), $USER->id);
        }

        return array('success' => true);
    }

    /**
     * Returns description of delete_custom_report return value.
     */
    public static function delete_custom_report_returns() {
        return new external_single_structure(array(
            'success' => new external_value(PARAM_BOOL, 'Success status'),
        ));
    }
}
