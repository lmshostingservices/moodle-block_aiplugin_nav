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
 * External functions and service definitions for AI Plugin Navigation block.
 *
 * @package    block_aiplugin_nav
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_aiplugin_nav_save_custom_link' => [
        'classname'     => 'block_aiplugin_nav\external\custom_links',
        'methodname'    => 'save_custom_link',
        'description'   => 'Save a custom link for the user',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],
    'block_aiplugin_nav_delete_custom_link' => [
        'classname'     => 'block_aiplugin_nav\external\custom_links',
        'methodname'    => 'delete_custom_link',
        'description'   => 'Delete a custom link for the user',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],
    'block_aiplugin_nav_save_custom_report' => [
        'classname'     => 'block_aiplugin_nav\external\custom_links',
        'methodname'    => 'save_custom_report',
        'description'   => 'Save a custom report for the user',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],
    'block_aiplugin_nav_delete_custom_report' => [
        'classname'     => 'block_aiplugin_nav\external\custom_links',
        'methodname'    => 'delete_custom_report',
        'description'   => 'Delete a custom report for the user',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
    ],
    'block_aiplugin_nav_auto_update_plugin' => [
        'classname'     => 'block_aiplugin_nav\external\plugin_updater',
        'methodname'    => 'auto_update_plugin',
        'description'   => 'Auto-update an AI plugin by downloading and installing',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'moodle/site:config',
    ],
    'block_aiplugin_nav_auto_install_plugin' => [
        'classname'     => 'block_aiplugin_nav\external\plugin_updater',
        'methodname'    => 'auto_install_plugin',
        'description'   => 'Auto-install a NEW plugin that is not yet installed',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'moodle/site:config',
    ],
    'block_aiplugin_nav_run_upgrade' => [
        'classname'     => 'block_aiplugin_nav\external\plugin_updater',
        'methodname'    => 'run_upgrade',
        'description'   => 'Run Moodle database upgrade after plugin update',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'moodle/site:config',
    ],
    'block_aiplugin_nav_purge_caches' => [
        'classname'     => 'block_aiplugin_nav\external\purge_caches',
        'methodname'    => 'execute',
        'description'   => 'Purge all Moodle caches',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'moodle/site:config',
    ],
    'block_aiplugin_nav_get_purge_status' => [
        'classname'     => 'block_aiplugin_nav\external\get_purge_status',
        'methodname'    => 'execute',
        'description'   => 'Get cache purge status and schedule',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'moodle/site:config',
    ],
    'block_aiplugin_nav_get_credits' => [
        'classname'     => 'block_aiplugin_nav\external\get_credits',
        'methodname'    => 'execute',
        'description'   => 'Fetch credits balance from lms-labs.com (5-minute server-side cache)',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
    ],
    'block_aiplugin_nav_save_purge_schedule' => [
        'classname'     => 'block_aiplugin_nav\external\save_purge_schedule',
        'methodname'    => 'execute',
        'description'   => 'Save cache purge schedule settings',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'moodle/site:config',
    ],
    'block_aiplugin_nav_plugin_unlock' => [
        'classname'     => 'block_aiplugin_nav\external\plugin_unlock',
        'methodname'    => 'execute',
        'description'   => 'Unlock a credit-gated Time Saving Plugin by consuming the required credits via lms-labs.com/api/plu' .
            'gin-unlock',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'moodle/site:config',
    ],
];
