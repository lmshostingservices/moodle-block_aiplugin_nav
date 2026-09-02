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
 * Privacy Subsystem implementation for block_aiplugin_nav.
 *
 * Stores: (1) block_aiplugin_nav_purge.purged_by — the admin who ran a cache purge;
 *         (2) user preferences block_aiplugin_nav_custom_links / _custom_reports.
 *
 * @package    block_aiplugin_nav
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

namespace block_aiplugin_nav\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for the AI Dashboard Quick Links block.
 *
 * @package    block_aiplugin_nav
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\user_preference_provider {
    /**
     * Every user preference this plugin writes.
     *
     * Exported and deleted from one list so that adding a preference in lib.php and
     * forgetting it here cannot happen silently — which is how faves, layout, help and
     * spend went undeclared through every release up to this one.
     */
    private const PREFERENCES = [
        'block_aiplugin_nav_custom_links',
        'block_aiplugin_nav_custom_reports',
        'block_aiplugin_nav_faves',
        'block_aiplugin_nav_layout',
        'block_aiplugin_nav_help',
        'block_aiplugin_nav_spend',
        'block_aiplugin_nav_dismissed',
    ];

    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection The collection to add the metadata to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('block_aiplugin_nav_purge', [
            'purged_by'  => 'privacy:metadata:block_aiplugin_nav_purge:purged_by',
            'purge_type' => 'privacy:metadata:block_aiplugin_nav_purge:purge_type',
            'purged_at'  => 'privacy:metadata:block_aiplugin_nav_purge:purged_at',
        ], 'privacy:metadata:block_aiplugin_nav_purge');

        $collection->add_user_preference(
            'block_aiplugin_nav_custom_links',
            'privacy:metadata:preference:block_aiplugin_nav_custom_links'
        );
        $collection->add_user_preference(
            'block_aiplugin_nav_custom_reports',
            'privacy:metadata:preference:block_aiplugin_nav_custom_reports'
        );
        // These four were written by the block from the first release but never declared,
        // so they were absent from the metadata, from exports and from deletion. Every
        // preference the plugin writes has to appear here.
        $collection->add_user_preference(
            'block_aiplugin_nav_faves',
            'privacy:metadata:preference:block_aiplugin_nav_faves'
        );
        $collection->add_user_preference(
            'block_aiplugin_nav_layout',
            'privacy:metadata:preference:block_aiplugin_nav_layout'
        );
        $collection->add_user_preference(
            'block_aiplugin_nav_help',
            'privacy:metadata:preference:block_aiplugin_nav_help'
        );
        $collection->add_user_preference(
            'block_aiplugin_nav_spend',
            'privacy:metadata:preference:block_aiplugin_nav_spend'
        );
        $collection->add_user_preference(
            'block_aiplugin_nav_dismissed',
            'privacy:metadata:preference:block_aiplugin_nav_dismissed'
        );

        return $collection;
    }

    /**
     * Contexts containing user data: purge records are system-level.
     *
     * @param int $userid The user to look up.
     * @return contextlist The contexts holding this user's data.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists('block_aiplugin_nav_purge', ['purged_by' => $userid])) {
            $contextlist->add_from_sql(
                'SELECT id FROM {context} WHERE contextlevel = :level',
                ['level' => CONTEXT_SYSTEM]
            );
        }
        return $contextlist;
    }

    /**
     * Users within a context who have data.
     *
     * @param userlist $userlist The userlist to add matching users to.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        $userlist->add_from_sql(
            'purged_by',
            'SELECT purged_by FROM {block_aiplugin_nav_purge} WHERE purged_by IS NOT NULL',
            []
        );
    }

    /**
     * Export the cache-purge audit rows attributed to the user.
     *
     * @param approved_contextlist $contextlist The approved contexts to export from.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_SYSTEM) {
                continue;
            }
            $records = $DB->get_records('block_aiplugin_nav_purge', ['purged_by' => $user->id]);
            if (!$records) {
                continue;
            }
            $purges = [];
            foreach ($records as $r) {
                $purges[] = [
                    'purge_type' => $r->purge_type,
                    'purged_at'  => transform::datetime($r->purged_at),
                ];
            }
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'block_aiplugin_nav'), 'cache_purges'],
                (object) ['purges' => $purges]
            );
        }
    }

    /**
     * Export the user's stored preferences.
     *
     * @param int $userid The user whose preferences are exported.
     * @return void
     */
    public static function export_user_preferences(int $userid) {
        foreach (self::PREFERENCES as $name) {
            $value = get_user_preferences($name, null, $userid);
            if ($value === null) {
                continue;
            }
            writer::export_user_preference(
                'block_aiplugin_nav',
                $name,
                $value,
                get_string('privacy:metadata:preference:' . $name, 'block_aiplugin_nav')
            );
        }
    }

    /**
     * Delete all users' data in a context. The purge rows are an admin audit trail,
     * so we anonymise (null the userid) rather than destroy the history.
     *
     * @param \context $context The context to delete data for.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        $DB->set_field('block_aiplugin_nav_purge', 'purged_by', null, []);
    }

    /**
     * Delete data for one user across the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to delete from.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_SYSTEM) {
                continue;
            }
            $DB->set_field('block_aiplugin_nav_purge', 'purged_by', null, ['purged_by' => $user->id]);
        }
        foreach (self::PREFERENCES as $name) {
            unset_user_preference($name, $user->id);
        }
    }

    /**
     * Delete data for a set of users in a context.
     *
     * @param approved_userlist $userlist The approved users to delete data for.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->set_field_select('block_aiplugin_nav_purge', 'purged_by', null, "purged_by $insql", $inparams);
        foreach ($userids as $uid) {
            foreach (self::PREFERENCES as $name) {
                unset_user_preference($name, $uid);
            }
        }
    }
}
