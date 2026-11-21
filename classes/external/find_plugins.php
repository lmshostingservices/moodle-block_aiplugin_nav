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
 * External service behind the Plugin Finder.
 *
 * @package    block_aiplugin_nav
 * @copyright  2026 LMS Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_aiplugin_nav\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use block_aiplugin_nav\local\plugin_finder;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

/**
 * Recommend LMS Labs plugins for a described need.
 */
class find_plugins extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'query' => new external_value(PARAM_TEXT, 'What the user needs, in their own words', VALUE_DEFAULT, ''),
            'topic' => new external_value(PARAM_ALPHA, 'Quick-start topic key', VALUE_DEFAULT, ''),
            'mode' => new external_value(PARAM_ALPHA, 'match (instant) or ai', VALUE_DEFAULT, 'match'),
            'acceptpolicy' => new external_value(PARAM_BOOL, 'Accept the site AI policy first', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Find plugins.
     *
     * @param string $query
     * @param string $topic
     * @param string $mode
     * @param bool $acceptpolicy
     * @return array
     */
    public static function execute($query = '', $topic = '', $mode = 'match', $acceptpolicy = false) {
        global $CFG, $PAGE, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'query' => $query, 'topic' => $topic, 'mode' => $mode, 'acceptpolicy' => $acceptpolicy,
        ]);
        $context = \context_system::instance();
        self::validate_context($context);

        if (!self::allowed()) {
            throw new \required_capability_exception($context, 'moodle/site:configview', 'nopermissions', '');
        }

        // The spotlight approval list lives on the payload class, which is not autoloaded.
        require_once($CFG->dirroot . '/blocks/aiplugin_nav/classes/payload.php');

        $query = trim(\core_text::substr(trim((string) $params['query']), 0, plugin_finder::MAX_QUERY));
        $topic = (string) $params['topic'];
        $isadmin = has_capability('moodle/site:config', $context);
        $output = $PAGE->get_renderer('core');

        $aistatus = plugin_finder::ai_status((int) $USER->id);
        if ($params['acceptpolicy'] && $aistatus === 'policy') {
            plugin_finder::accept_policy((int) $USER->id, $context->id);
            $aistatus = plugin_finder::ai_status((int) $USER->id);
        }

        $summary = '';
        $used = 'match';
        $rows = [];
        if ($params['mode'] === 'ai' && $query !== '' && $aistatus === 'ok') {
            // The AI call can take a while; never hold the user's session lock during it.
            \core\session\manager::write_close();
            $picked = plugin_finder::ai_pick($query, (int) $USER->id, $context->id);
            if ($picked !== null) {
                $rows = $picked['results'];
                $summary = $picked['summary'];
                $used = 'ai';
            } else {
                $aistatus = 'failed';
            }
        }
        if ($used === 'match') {
            $matched = plugin_finder::match($query, $topic, $isadmin);
            $rows = $matched['results'];
        }

        return [
            'mode' => $used,
            'aistatus' => $aistatus,
            'summary' => $summary,
            'results' => plugin_finder::cards($rows, $output),
        ];
    }

    /**
     * The finder is for the same people as the spotlight: admins, managers and teachers.
     *
     * @return bool
     */
    public static function allowed(): bool {
        global $DB, $USER;
        if ((string) get_config('block_aiplugin_nav', 'finder_enabled') === '0') {
            return false;
        }
        $context = \context_system::instance();
        if (is_siteadmin() || has_capability('moodle/site:configview', $context)) {
            return true;
        }
        return $DB->record_exists_sql(
            "SELECT ra.id
               FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid
              WHERE ra.userid = :userid
                AND r.shortname IN ('editingteacher', 'teacher', 'manager', 'coursecreator',
                                    'lmshsadmin', 'lmshostingadmin')",
            ['userid' => $USER->id]
        );
    }

    /**
     * Result structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'mode' => new external_value(PARAM_ALPHA, 'match or ai: how these results were chosen'),
            'aistatus' => new external_value(PARAM_ALPHA, 'ok, off, unavailable, policy or failed'),
            'summary' => new external_value(PARAM_TEXT, 'AI summary of the request, or empty'),
            'results' => new external_multiple_structure(new external_single_structure([
                'component' => new external_value(PARAM_COMPONENT, 'Frankenstyle component'),
                'name' => new external_value(PARAM_TEXT, 'Plugin name'),
                'type' => new external_value(PARAM_TEXT, 'Plugin type label'),
                'cat' => new external_value(PARAM_ALPHA, 'Category key'),
                'catlabel' => new external_value(PARAM_TEXT, 'Category label'),
                'accent' => new external_value(PARAM_TEXT, 'Accent colour'),
                'accent2' => new external_value(PARAM_TEXT, 'Second accent colour'),
                'pitch' => new external_value(PARAM_TEXT, 'Short summary'),
                'desc' => new external_value(PARAM_TEXT, 'Full description'),
                'features' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Feature')),
                'usage' => new external_value(PARAM_TEXT, 'AI credit usage note'),
                'docs' => new external_value(PARAM_URL, 'Documentation URL'),
                'image' => new external_value(PARAM_URL, 'Screenshot URL'),
                'video' => new external_value(PARAM_URL, 'YouTube playlist or channel search URL'),
                'videokind' => new external_value(PARAM_ALPHA, 'playlist or search'),
                'installed' => new external_value(PARAM_BOOL, 'Installed on this site'),
                'courseaddable' => new external_value(PARAM_BOOL, 'Added to courses as an activity'),
                'audience' => new external_value(PARAM_ALPHA, 'teacher, admin or both'),
                'why' => new external_value(PARAM_TEXT, 'Why it fits'),
                'score' => new external_value(PARAM_INT, 'Relative fit, 0-100'),
                'rank' => new external_value(PARAM_INT, 'Position, 1 = best'),
            ])),
        ]);
    }
}
