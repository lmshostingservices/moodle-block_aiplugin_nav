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
 * block_aiplugin_nav upgrade steps.
 *
 * @package    block_aiplugin_nav
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

/**
 * Run the block's database upgrade steps.
 *
 * @param int $oldversion The version the site is upgrading from.
 * @return bool True on success.
 */
function xmldb_block_aiplugin_nav_upgrade($oldversion) {
    global $DB;

    if ($oldversion < 2026081100) {
        // BASELINE-10DIGIT (v2.4.50): Collapsed savepoint for sites upgrading
        // from the baseline reset. No DB schema changes.
        upgrade_block_savepoint(true, 2026081100, 'aiplugin_nav');
    }

    if ($oldversion < 2026081101) {
        // NEW-DESIGN (v2.4.51): Category layout redesign.
        // No DB schema changes.
        upgrade_block_savepoint(true, 2026081101, 'aiplugin_nav');
    }

    if ($oldversion < 2026081201) {
        // V2.4.52 (13-digit scheme restored): layout render fixes, curl/global fixes,
        // version-scheme correction. No DB schema changes.
        upgrade_block_savepoint(true, 2026081201, 'aiplugin_nav');
    }

    if ($oldversion < 2026081202) {
        // V2.4.53: registry (tiny_aipagetemplates), check_versions capability, coding-style
        // and packaging bump for Moodle upload. No DB schema changes.
        upgrade_block_savepoint(true, 2026081202, 'aiplugin_nav');
    }

    if ($oldversion < 2026081203) {
        // V2.4.54: version bump for pipeline promotion (strictly > promoted 2026081202).
        // No DB schema changes.
        upgrade_block_savepoint(true, 2026081203, 'aiplugin_nav');
    }

    if ($oldversion < 2026081204) {
        // V2.4.55: version bump for pipeline promotion (strictly > promoted 2026081203).
        // No DB schema changes.
        upgrade_block_savepoint(true, 2026081204, 'aiplugin_nav');
    }

    if ($oldversion < 2026081205) {
        // V2.4.56: world-class plugin finder (search / sort / filter / Sections-Grid-List views)
        // integrated into the Plugin Manager. No DB schema changes.
        upgrade_block_savepoint(true, 2026081205, 'aiplugin_nav');
    }

    if ($oldversion < 2026081206) {
        // V2.4.57: theme-proof install icon (filled white download glyph, forced fill).
        // No DB schema changes.
        upgrade_block_savepoint(true, 2026081206, 'aiplugin_nav');
    }

    if ($oldversion < 2026081207) {
        // V2.4.58: per-plugin credit pricing (500 credits / 2000 for RTO Compliance) shown on
        // finder cards; install button wired to the existing credit-unlock gate. No DB schema changes.
        upgrade_block_savepoint(true, 2026081207, 'aiplugin_nav');
    }

    if ($oldversion < 2026081208) {
        // V2.4.59: finder now renders on its own light canvas with card shadows so it is legible
        // on white Moodle themes (was white-on-white). No DB schema changes.
        upgrade_block_savepoint(true, 2026081208, 'aiplugin_nav');
    }

    if ($oldversion < 2026081209) {
        // V2.4.60: version bump for pipeline promotion (strictly > promoted 2026081208).
        // No DB schema changes.
        upgrade_block_savepoint(true, 2026081209, 'aiplugin_nav');
    }

    if ($oldversion < 2026083000) {
        // V2.4.67: add Course Recertification and Completion Auto-Suspend to
        // the Settings, Manage, and Reports Quicklinks registries.
        // No DB schema changes.
        upgrade_block_savepoint(true, 2026083000, 'aiplugin_nav');
    }

    if ($oldversion < 2026083001) {
        // V2.4.68: keep long dropdowns within the viewport and make them scrollable.
        // No DB schema changes.
        upgrade_block_savepoint(true, 2026083001, 'aiplugin_nav');
    }

    if ($oldversion < 2026091000) {
        // V2.5.31: publish AI Branched Scenario as disabled testing metadata in
        // Quick Links. No install or download action is exposed. No DB changes.
        upgrade_block_savepoint(true, 2026091000, 'aiplugin_nav');
    }

    if ($oldversion < 2026091001) {
        // V2.5.32: compliance-only follow-up for generated registry and bootstrap
        // file headers, plus exact reviewer registry evidence. No DB changes.
        upgrade_block_savepoint(true, 2026091001, 'aiplugin_nav');
    }

    if ($oldversion < 2026091002) {
        // V2.5.33: preserve the explicitly approved public-testing catalogue row
        // after the asynchronous version refresh. No DB changes.
        upgrade_block_savepoint(true, 2026091002, 'aiplugin_nav');
    }

    if ($oldversion < 2026091003) {
        // V2.5.34: expose the now-ready AI Branched Scenario catalogue and
        // installed-plugin settings rows. No DB changes.
        upgrade_block_savepoint(true, 2026091003, 'aiplugin_nav');
    }

    if ($oldversion < 2026092900) {
        // V2.5.44: discard the unscoped legacy credit cache. No schema changes.
        unset_config('credits_cache', 'block_aiplugin_nav');
        unset_config('credits_cached_at', 'block_aiplugin_nav');
        upgrade_block_savepoint(true, 2026092900, 'aiplugin_nav');
    }

    if ($oldversion < 2026110600) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('block_aiplugin_nav_qreview');
        if (!$dbman->table_exists($table)) {
            $table->addField(new xmldb_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE));
            $table->addField(new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL));
            $table->addField(new xmldb_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL));
            $table->addField(new xmldb_field('questionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL));
            $table->addField(new xmldb_field('reviewedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL));
            $table->addField(new xmldb_field('reviewedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL));
            $table->addKey(new xmldb_key('primary', XMLDB_KEY_PRIMARY, ['id']));
            $table->addIndex(new xmldb_index('course_cm_question', XMLDB_INDEX_UNIQUE, ['courseid', 'cmid', 'questionid']));
            $dbman->create_table($table);
        }
        upgrade_block_savepoint(true, 2026110600, 'aiplugin_nav');
    }

    if ($oldversion < 2026110601) {
        $dbman = $DB->get_manager();
        $review = new xmldb_table('block_aiplugin_nav_qreview');
        $reviewfields = [
            new xmldb_field('reviewstatus', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'none', 'reviewedat'),
            new xmldb_field('fault', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'reviewstatus'),
            new xmldb_field('rationale', XMLDB_TYPE_TEXT, null, null, null, null, null, 'fault'),
            new xmldb_field('rewrite', XMLDB_TYPE_TEXT, null, null, null, null, null, 'rationale'),
            new xmldb_field('teacheredit', XMLDB_TYPE_TEXT, null, null, null, null, null, 'rewrite'),
            new xmldb_field('suggestedby', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'teacheredit'),
            new xmldb_field('suggestedat', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'suggestedby'),
        ];
        if ($dbman->table_exists($review)) {
            foreach ($reviewfields as $field) {
                if (!$dbman->field_exists($review, $field)) {
                    $dbman->add_field($review, $field);
                }
            }
        }

        $guardian = new xmldb_table('block_aiplugin_nav_guardian');
        $guardian->addField(new xmldb_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE));
        $guardian->addField(new xmldb_field('firstname', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL));
        $guardian->addField(new xmldb_field('lastname', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL));
        $guardian->addField(new xmldb_field('email', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL));
        $guardian->addField(new xmldb_field('language', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'en'));
        $guardian->addField(new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL));
        $guardian->addField(new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL));
        $guardian->addKey(new xmldb_key('primary', XMLDB_KEY_PRIMARY, ['id']));
        $guardian->addIndex(new xmldb_index('email_idx', XMLDB_INDEX_NOTUNIQUE, ['email']));
        if (!$dbman->table_exists($guardian)) {
            $dbman->create_table($guardian);
        }

        $link = new xmldb_table('block_aiplugin_nav_guardlink');
        $link->addField(new xmldb_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE));
        $link->addField(new xmldb_field('guardianid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL));
        $link->addField(new xmldb_field('studentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL));
        $link->addField(new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL));
        $link->addField(new xmldb_field('relationship', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL));
        $link->addField(new xmldb_field('consentedby', XMLDB_TYPE_INTEGER, '10', null, null));
        $link->addField(new xmldb_field('consentedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL));
        $link->addField(new xmldb_field('confirmedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0'));
        $link->addField(new xmldb_field('frequency', XMLDB_TYPE_CHAR, '12', null, XMLDB_NOTNULL, null, 'off'));
        $link->addField(new xmldb_field('nextsend', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0'));
        $link->addField(new xmldb_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending'));
        $link->addField(new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL));
        $link->addField(new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL));
        $link->addKey(new xmldb_key('primary', XMLDB_KEY_PRIMARY, ['id']));
        $link->addIndex(new xmldb_index('guardian_student_course', XMLDB_INDEX_UNIQUE, ['guardianid', 'studentid', 'courseid']));
        $link->addIndex(new xmldb_index('student_course_idx', XMLDB_INDEX_NOTUNIQUE, ['studentid', 'courseid']));
        $link->addIndex(new xmldb_index('due_reports_idx', XMLDB_INDEX_NOTUNIQUE, ['status', 'frequency', 'nextsend']));
        if (!$dbman->table_exists($link)) {
            $dbman->create_table($link);
        }

        $token = new xmldb_table('block_aiplugin_nav_gtoken');
        $token->addField(new xmldb_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE));
        $token->addField(new xmldb_field('linkid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL));
        $token->addField(new xmldb_field('purpose', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL));
        $token->addField(new xmldb_field('tokenhash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL));
        $token->addField(new xmldb_field('expires', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL));
        $token->addField(new xmldb_field('revoked', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0'));
        $token->addField(new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL));
        $token->addKey(new xmldb_key('primary', XMLDB_KEY_PRIMARY, ['id']));
        $token->addIndex(new xmldb_index('tokenhash_idx', XMLDB_INDEX_UNIQUE, ['tokenhash']));
        $token->addIndex(new xmldb_index('link_purpose_idx', XMLDB_INDEX_NOTUNIQUE, ['linkid', 'purpose']));
        if (!$dbman->table_exists($token)) {
            $dbman->create_table($token);
        }

        $mail = new xmldb_table('block_aiplugin_nav_gmail');
        $mail->addField(new xmldb_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE));
        $mail->addField(new xmldb_field('guardianid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL));
        $mail->addField(new xmldb_field('linkid', XMLDB_TYPE_INTEGER, '10', null, null));
        $mail->addField(new xmldb_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL));
        $mail->addField(new xmldb_field('error', XMLDB_TYPE_TEXT, null, null, null));
        $mail->addField(new xmldb_field('sentat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0'));
        $mail->addField(new xmldb_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL));
        $mail->addKey(new xmldb_key('primary', XMLDB_KEY_PRIMARY, ['id']));
        $mail->addIndex(new xmldb_index('guardian_time_idx', XMLDB_INDEX_NOTUNIQUE, ['guardianid', 'timecreated']));
        $mail->addIndex(new xmldb_index('link_time_idx', XMLDB_INDEX_NOTUNIQUE, ['linkid', 'timecreated']));
        if (!$dbman->table_exists($mail)) {
            $dbman->create_table($mail);
        }

        upgrade_block_savepoint(true, 2026110601, 'aiplugin_nav');
    }

    if ($oldversion < 2026110602) {
        $dbman = $DB->get_manager();
        $review = new xmldb_table('block_aiplugin_nav_qreview');
        $rationale = new xmldb_field('rationale', XMLDB_TYPE_TEXT, null, null, null, null, null, 'fault');
        if ($dbman->table_exists($review) && !$dbman->field_exists($review, $rationale)) {
            $dbman->add_field($review, $rationale);
        }
        upgrade_block_savepoint(true, 2026110602, 'aiplugin_nav');
    }

    if ($oldversion < 2026110605) {
        $dbman = $DB->get_manager();
        $guardlink = new xmldb_table('block_aiplugin_nav_guardlink');
        $fields = [
            new xmldb_field('weeklysummary', XMLDB_TYPE_TEXT, null, null, null),
            new xmldb_field('summaryhash', XMLDB_TYPE_CHAR, '64', null, null),
            new xmldb_field('summaryupdatedby', XMLDB_TYPE_INTEGER, '10', null, null),
            new xmldb_field('summaryupdatedat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0'),
        ];
        if ($dbman->table_exists($guardlink)) {
            foreach ($fields as $field) {
                if (!$dbman->field_exists($guardlink, $field)) {
                    $dbman->add_field($guardlink, $field);
                }
            }
        }
        upgrade_block_savepoint(true, 2026110605, 'aiplugin_nav');
    }

    return true;
}
