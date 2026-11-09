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
 * Stores cache-purge audit data, user preferences, and consent-based guardian report details.
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
        'block_aiplugin_nav_spotlight',
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

        $collection->add_database_table('block_aiplugin_nav_guardian', [
            'firstname' => 'privacy:metadata:guardian:first',
            'lastname' => 'privacy:metadata:guardian:last',
            'email' => 'privacy:metadata:guardian:email',
            'language' => 'privacy:metadata:guardian:language',
            'timecreated' => 'privacy:metadata:guardian:timecreated',
        ], 'privacy:metadata:guardian');
        $collection->add_database_table('block_aiplugin_nav_qreview', [
            'courseid' => 'privacy:metadata:qreview:course',
            'cmid' => 'privacy:metadata:qreview:activity',
            'questionid' => 'privacy:metadata:qreview:question',
            'reviewedby' => 'privacy:metadata:qreview:reviewedby',
            'reviewedat' => 'privacy:metadata:qreview:reviewedat',
            'reviewstatus' => 'privacy:metadata:qreview:status',
            'fault' => 'privacy:metadata:qreview:fault',
            'rationale' => 'privacy:metadata:qreview:rationale',
            'rewrite' => 'privacy:metadata:qreview:rewrite',
            'teacheredit' => 'privacy:metadata:qreview:teacheredit',
            'suggestedby' => 'privacy:metadata:qreview:suggestedby',
            'suggestedat' => 'privacy:metadata:qreview:suggestedat',
        ], 'privacy:metadata:qreview');
        $collection->add_database_table('block_aiplugin_nav_guardlink', [
            'guardianid' => 'privacy:metadata:guardlink:guardian',
            'studentid' => 'privacy:metadata:guardlink:student',
            'courseid' => 'privacy:metadata:guardlink:course',
            'relationship' => 'privacy:metadata:guardlink:relationship',
            'consentedby' => 'privacy:metadata:guardlink:consentedby',
            'consentedat' => 'privacy:metadata:guardlink:consentedat',
            'confirmedat' => 'privacy:metadata:guardlink:confirmedat',
            'frequency' => 'privacy:metadata:guardlink:frequency',
            'weeklysummary' => 'privacy:metadata:guardlink:weeklysummary',
            'summaryhash' => 'privacy:metadata:guardlink:summaryhash',
            'summaryupdatedby' => 'privacy:metadata:guardlink:summaryupdatedby',
            'summaryupdatedat' => 'privacy:metadata:guardlink:summaryupdatedat',
            'status' => 'privacy:metadata:guardlink:status',
        ], 'privacy:metadata:guardlink');
        $collection->add_database_table('block_aiplugin_nav_gtoken', [
            'linkid' => 'privacy:metadata:gtoken:linkid',
            'purpose' => 'privacy:metadata:gtoken:purpose',
            'expires' => 'privacy:metadata:gtoken:expires',
            'revoked' => 'privacy:metadata:gtoken:revoked',
        ], 'privacy:metadata:gtoken');
        $collection->add_database_table('block_aiplugin_nav_gmail', [
            'guardianid' => 'privacy:metadata:gmail:guardianid',
            'linkid' => 'privacy:metadata:gmail:linkid',
            'status' => 'privacy:metadata:gmail:status',
            'error' => 'privacy:metadata:gmail:error',
            'sentat' => 'privacy:metadata:gmail:sentat',
        ], 'privacy:metadata:gmail');

        $collection->add_external_location_link('lmslabs', [
            'siteid'  => 'privacy:metadata:lmslabs:siteid',
            'apikey'  => 'privacy:metadata:lmslabs:apikey',
            'siteurl' => 'privacy:metadata:lmslabs:siteurl',
        ], 'privacy:metadata:lmslabs');
        $collection->add_external_location_link('lmslabs_question_review', [
            'question' => 'privacy:metadata:questionreview:question',
            'options' => 'privacy:metadata:questionreview:options',
            'subject' => 'privacy:metadata:questionreview:subject',
        ], 'privacy:metadata:questionreview');
        $collection->add_external_location_link('lmslabs_guardian_summary', [
            'courseName' => 'privacy:metadata:guardiansummary:course',
            'progressPercent' => 'privacy:metadata:guardiansummary:progress',
        ], 'privacy:metadata:guardiansummary');

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
        $collection->add_user_preference(
            'block_aiplugin_nav_spotlight',
            'privacy:metadata:preference:block_aiplugin_nav_spotlight'
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
        if (self::table_exists('block_aiplugin_nav_guardlink')) {
            $contextlist->add_from_sql(
                'SELECT id
                   FROM {context}
                  WHERE contextlevel = :level
                    AND instanceid IN (
                        SELECT courseid
                          FROM {block_aiplugin_nav_guardlink}
                         WHERE studentid = :studentid OR consentedby = :consentedby
                    )',
                [
                    'level' => CONTEXT_COURSE,
                    'studentid' => $userid,
                    'consentedby' => $userid,
                ]
            );
        }
        if (self::table_exists('block_aiplugin_nav_qreview')) {
            $contextlist->add_from_sql(
                'SELECT id
                   FROM {context}
                  WHERE contextlevel = :level
                    AND instanceid IN (
                        SELECT courseid
                          FROM {block_aiplugin_nav_qreview}
                         WHERE reviewedby = :reviewedby OR suggestedby = :suggestedby
                    )',
                [
                    'level' => CONTEXT_COURSE,
                    'reviewedby' => $userid,
                    'suggestedby' => $userid,
                ]
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
            if ($context->contextlevel === CONTEXT_COURSE) {
                if (self::table_exists('block_aiplugin_nav_guardlink')) {
                    $userlist->add_from_sql(
                        'studentid',
                        'SELECT studentid FROM {block_aiplugin_nav_guardlink} WHERE courseid = :courseid',
                        ['courseid' => $context->instanceid]
                    );
                    $userlist->add_from_sql(
                        'consentedby',
                        'SELECT consentedby FROM {block_aiplugin_nav_guardlink}
                          WHERE courseid = :courseid AND consentedby IS NOT NULL',
                        ['courseid' => $context->instanceid]
                    );
                }
                if (self::table_exists('block_aiplugin_nav_qreview')) {
                    $userlist->add_from_sql(
                        'reviewedby',
                        'SELECT reviewedby FROM {block_aiplugin_nav_qreview} WHERE courseid = :courseid AND reviewedby > 0',
                        ['courseid' => $context->instanceid]
                    );
                    $userlist->add_from_sql(
                        'suggestedby',
                        'SELECT suggestedby FROM {block_aiplugin_nav_qreview} WHERE courseid = :courseid AND suggestedby IS NOT NULL',
                        ['courseid' => $context->instanceid]
                    );
                }
            }
            return;
        }
        $userlist->add_from_sql(
            'purged_by',
            'SELECT purged_by FROM {block_aiplugin_nav_purge} WHERE purged_by IS NOT NULL',
            []
        );
    }

    /**
     * Export purge audit and guardian-report data for the user in approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export from.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_SYSTEM) {
                if ($context->contextlevel !== CONTEXT_COURSE) {
                    continue;
                }
                $links = self::table_exists('block_aiplugin_nav_guardlink')
                    && self::table_exists('block_aiplugin_nav_guardian')
                    ? $DB->get_records_sql(
                    "SELECT gl.*, g.firstname AS guardianfirstname, g.lastname AS guardianlastname, g.email
                       FROM {block_aiplugin_nav_guardlink} gl
                       JOIN {block_aiplugin_nav_guardian} g ON g.id = gl.guardianid
                      WHERE gl.courseid = :courseid AND (gl.studentid = :studentid OR gl.consentedby = :consentedby)",
                    [
                        'courseid' => $context->instanceid,
                        'studentid' => $user->id,
                        'consentedby' => $user->id,
                    ]
                    ) : [];
                $reviews = self::table_exists('block_aiplugin_nav_qreview')
                    ? $DB->get_records_select(
                    'block_aiplugin_nav_qreview',
                    'courseid = :courseid AND (reviewedby = :reviewedby OR suggestedby = :suggestedby)',
                    [
                        'courseid' => $context->instanceid,
                        'reviewedby' => $user->id,
                        'suggestedby' => $user->id,
                    ]
                    ) : [];
                if ($links) {
                    $exportlinks = [];
                    foreach ($links as $link) {
                        $mailhistory = [];
                        if (self::table_exists('block_aiplugin_nav_gmail')) {
                            foreach ($DB->get_records('block_aiplugin_nav_gmail', ['linkid' => $link->id], 'timecreated ASC') as $mail) {
                                $mailhistory[] = [
                                    'status' => $mail->status,
                                    'error_code' => $mail->error,
                                    'sent_at' => $mail->sentat ? transform::datetime($mail->sentat) : null,
                                    'created_at' => transform::datetime($mail->timecreated),
                                ];
                            }
                        }
                        $exportlinks[] = [
                            'guardian' => trim($link->guardianfirstname . ' ' . $link->guardianlastname),
                            'guardian_email' => $link->email,
                            'relationship' => $link->relationship,
                            'consented_at' => transform::datetime($link->consentedat),
                            'confirmed_at' => $link->confirmedat ? transform::datetime($link->confirmedat) : null,
                            'frequency' => $link->frequency,
                            'status' => $link->status,
                            'email_history' => $mailhistory,
                        ];
                    }
                    writer::with_context($context)->export_data(
                        [get_string('pluginname', 'block_aiplugin_nav'), 'guardian_reports'],
                        (object)['links' => $exportlinks]
                    );
                }
                if ($reviews) {
                    writer::with_context($context)->export_data(
                        [get_string('pluginname', 'block_aiplugin_nav'), 'question_reviews'],
                        (object)['reviews' => array_values(array_map(static function (\stdClass $review): array {
                            return [
                                'activity_id' => $review->cmid,
                                'question_id' => $review->questionid,
                                'status' => $review->reviewstatus,
                                'likely_fault' => $review->fault,
                                'rationale' => $review->rationale,
                                'suggestion' => $review->rewrite,
                                'teacher_edit' => $review->teacheredit,
                                'reviewed_at' => $review->reviewedat ? transform::datetime($review->reviewedat) : null,
                                'suggested_at' => $review->suggestedat ? transform::datetime($review->suggestedat) : null,
                            ];
                        }, $reviews))]
                    );
                }
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
        if ($context->contextlevel === CONTEXT_COURSE) {
            if (self::table_exists('block_aiplugin_nav_guardlink')) {
                self::delete_guardian_links('courseid = :courseid', ['courseid' => $context->instanceid]);
            }
            return;
        }
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
            if ($context->contextlevel === CONTEXT_COURSE) {
                if (self::table_exists('block_aiplugin_nav_qreview')) {
                    $DB->set_field_select(
                        'block_aiplugin_nav_qreview',
                        'reviewedby',
                        0,
                        'courseid = :courseid AND reviewedby = :userid',
                        ['courseid' => $context->instanceid, 'userid' => $user->id]
                    );
                    $DB->set_field_select(
                        'block_aiplugin_nav_qreview',
                        'suggestedby',
                        null,
                        'courseid = :courseid AND suggestedby = :userid',
                        ['courseid' => $context->instanceid, 'userid' => $user->id]
                    );
                }
                if (self::table_exists('block_aiplugin_nav_guardlink')) {
                    $guardlink = new \xmldb_table('block_aiplugin_nav_guardlink');
                    $summaryauthor = new \xmldb_field('summaryupdatedby');
                    if ($DB->get_manager()->field_exists($guardlink, $summaryauthor)) {
                        $DB->set_field('block_aiplugin_nav_guardlink', 'summaryupdatedby', null, [
                            'courseid' => $context->instanceid,
                            'summaryupdatedby' => $user->id,
                        ]);
                    }
                    self::delete_guardian_links(
                        '(studentid = :userid OR consentedby = :userid) AND courseid = :courseid',
                        ['userid' => $user->id, 'courseid' => $context->instanceid],
                        (int)$user->id
                    );
                }
            } else if ($context->contextlevel == CONTEXT_SYSTEM) {
                $DB->set_field('block_aiplugin_nav_purge', 'purged_by', null, ['purged_by' => $user->id]);
                if (self::table_exists('block_aiplugin_nav_guardlink')) {
                    $guardlink = new \xmldb_table('block_aiplugin_nav_guardlink');
                    $summaryauthor = new \xmldb_field('summaryupdatedby');
                    if ($DB->get_manager()->field_exists($guardlink, $summaryauthor)) {
                        $DB->set_field('block_aiplugin_nav_guardlink', 'summaryupdatedby', null, [
                            'summaryupdatedby' => $user->id,
                        ]);
                    }
                }
            }
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
        if ($context->contextlevel === CONTEXT_COURSE) {
            $userids = $userlist->get_userids();
            if ($userids) {
                if (self::table_exists('block_aiplugin_nav_qreview')) {
                    [$reviewedbysql, $reviewedbyparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'revieweruser');
                    [$suggestedbysql, $suggestedbyparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'suggestionuser');
                    $DB->set_field_select(
                        'block_aiplugin_nav_qreview',
                        'reviewedby',
                        0,
                        "courseid = :courseid AND reviewedby $reviewedbysql",
                        ['courseid' => $context->instanceid] + $reviewedbyparams
                    );
                    $DB->set_field_select(
                        'block_aiplugin_nav_qreview',
                        'suggestedby',
                        null,
                        "courseid = :courseid AND suggestedby $suggestedbysql",
                        ['courseid' => $context->instanceid] + $suggestedbyparams
                    );
                }
                if (self::table_exists('block_aiplugin_nav_guardlink')) {
                    [$studentsql, $studentparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'studentuser');
                    [$consentersql, $consenterparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'consenteruser');
                    $guardlink = new \xmldb_table('block_aiplugin_nav_guardlink');
                    $summaryauthor = new \xmldb_field('summaryupdatedby');
                    if ($DB->get_manager()->field_exists($guardlink, $summaryauthor)) {
                        [$summaryauthorsql, $summaryauthorparams] = $DB->get_in_or_equal(
                            $userids,
                            SQL_PARAMS_NAMED,
                            'summaryauthor'
                        );
                        $DB->set_field_select(
                            'block_aiplugin_nav_guardlink',
                            'summaryupdatedby',
                            null,
                            "courseid = :courseid AND summaryupdatedby $summaryauthorsql",
                            ['courseid' => $context->instanceid] + $summaryauthorparams
                        );
                    }
                    self::delete_guardian_links(
                        "courseid = :courseid AND (studentid $studentsql OR consentedby $consentersql)",
                        ['courseid' => $context->instanceid] + $studentparams + $consenterparams
                    );
                }
            }
            return;
        }
        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->set_field_select('block_aiplugin_nav_purge', 'purged_by', null, "purged_by $insql", $inparams);
        if (self::table_exists('block_aiplugin_nav_guardlink')) {
            $guardlink = new \xmldb_table('block_aiplugin_nav_guardlink');
            $summaryauthor = new \xmldb_field('summaryupdatedby');
            if ($DB->get_manager()->field_exists($guardlink, $summaryauthor)) {
                $DB->set_field_select(
                    'block_aiplugin_nav_guardlink',
                    'summaryupdatedby',
                    null,
                    "summaryupdatedby $insql",
                    $inparams
                );
            }
        }
        foreach ($userids as $uid) {
            foreach (self::PREFERENCES as $name) {
                unset_user_preference($name, $uid);
            }
        }
    }

    /**
     * Remove or revoke guardian links when the learner or consent recorder is erased.
     *
     * @param string $where
     * @param array $params
     * @param int|null $userbeingdeleted
     * @return void
     */
    private static function delete_guardian_links(string $where, array $params, ?int $userbeingdeleted = null): void {
        global $DB;
        $links = $DB->get_records_select('block_aiplugin_nav_guardlink', $where, $params);
        foreach ($links as $link) {
            if ($userbeingdeleted && (int)$link->studentid !== $userbeingdeleted) {
                $link->consentedby = null;
                $link->consentedat = 0;
                $link->frequency = 'off';
                $link->nextsend = 0;
                $link->weeklysummary = null;
                $link->summaryhash = null;
                $link->summaryupdatedby = null;
                $link->summaryupdatedat = 0;
                $link->status = 'revoked';
                $link->timemodified = time();
                $DB->update_record('block_aiplugin_nav_guardlink', $link);
                \block_aiplugin_nav\local\course_report\guardian_reports::revoke_link_tokens((int)$link->id);
                continue;
            }
            if (self::table_exists('block_aiplugin_nav_gtoken')) {
                $DB->delete_records('block_aiplugin_nav_gtoken', ['linkid' => $link->id]);
            }
            if (self::table_exists('block_aiplugin_nav_gmail')) {
                $DB->delete_records('block_aiplugin_nav_gmail', ['linkid' => $link->id]);
            }
            $DB->delete_records('block_aiplugin_nav_guardlink', ['id' => $link->id]);
            if (!$DB->record_exists('block_aiplugin_nav_guardlink', ['guardianid' => $link->guardianid])
                && self::table_exists('block_aiplugin_nav_guardian')) {
                $DB->delete_records('block_aiplugin_nav_guardian', ['id' => $link->guardianid]);
            }
        }
    }

    /**
     * Check whether a plugin table exists before a privacy request queries it.
     *
     * @param string $name
     * @return bool
     */
    private static function table_exists(string $name): bool {
        global $DB;
        return $DB->get_manager()->table_exists(new \xmldb_table($name));
    }
}
