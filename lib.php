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
 * Library functions for block_aiplugin_nav.
 *
 * @package    block_aiplugin_nav
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Declare the user preferences this block writes from JavaScript.
 *
 * This is the supported replacement for user_preference_allow_ajax_update(), which is
 * deprecated. Declaring them here is what allows the core_user/repository AMD module to
 * write them through core_user_update_user_preferences; without it every write is
 * rejected and nothing the user personalises survives a page load.
 *
 * Every preference stores a small JSON document or a single flag, is scoped to the user
 * who owns it, and is only ever writable by that user — core_user::is_current_user is the
 * permission callback, so one user can never write another's preferences.
 *
 * @return array The preference definitions, keyed by preference name.
 */
function block_aiplugin_nav_user_preferences(): array {
    return [
        // JSON array of favourited item names.
        'block_aiplugin_nav_faves' => [
            'type'               => PARAM_RAW, // Pipeline-ignore: PARAM_RAW — JSON array, json_decode()'d on read.
            'null'               => NULL_NOT_ALLOWED,
            'default'            => '[]',
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        // JSON object of saved per-panel layouts: filters, sort and open categories.
        'block_aiplugin_nav_layout' => [
            'type'               => PARAM_RAW, // Pipeline-ignore: PARAM_RAW — JSON object, json_decode()'d on read.
            'null'               => NULL_NOT_ALLOWED,
            'default'            => '{}',
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        // Flag "1" or "0" — whether the hover help cards are shown.
        'block_aiplugin_nav_help' => [
            'type'               => PARAM_INT,
            'null'               => NULL_NOT_ALLOWED,
            'default'            => 1,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        // JSON array of recent installs and what each cost, for the install receipt.
        'block_aiplugin_nav_spend' => [
            'type'               => PARAM_RAW, // Pipeline-ignore: PARAM_RAW — JSON array, json_decode()'d on read.
            'null'               => NULL_NOT_ALLOWED,
            'default'            => '[]',
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
        // JSON array of components whose featured row this user has dismissed.
        'block_aiplugin_nav_dismissed' => [
            'type'               => PARAM_RAW, // Pipeline-ignore: PARAM_RAW — JSON array, json_decode()'d on read.
            'null'               => NULL_NOT_ALLOWED,
            'default'            => '[]',
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
    ];
}
