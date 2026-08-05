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
 * Upgrade script for AI Dashboard Quick Links block.
 *
 * @package    block_aiplugin_nav
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the block_aiplugin_nav plugin.
 *
 * @param int $oldversion The old version of the plugin
 * @return bool
 */
function xmldb_block_aiplugin_nav_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    // Add cache purge table in v2.0.0.
    if ($oldversion < 2025121814) {
        // Define table block_aiplugin_nav_purge.
        $table = new xmldb_table('block_aiplugin_nav_purge');

        // Adding fields.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('purge_type', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('purged_at', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('purged_by', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

        // Adding keys.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes.
        $table->add_index('purge_type_idx', XMLDB_INDEX_NOTUNIQUE, ['purge_type']);
        $table->add_index('purged_at_idx', XMLDB_INDEX_NOTUNIQUE, ['purged_at']);

        // Create the table if it doesn't exist.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_block_savepoint(true, 2025121814, 'aiplugin_nav');
    }

    if ($oldversion < 2026030600242) {
        // v2.3.12: FIX Essay Guard entry corrected to plagiarism_essayguard type.
        upgrade_block_savepoint(true, 2026030600242, 'aiplugin_nav');
    }

    if ($oldversion < 2026030600243) {
        // v2.3.13: FIX Essay Guard settings URL corrected to /plagiarism/essayguard/settings.php
        // (was /admin/settings.php?section=plagiarism_essayguard which is invalid for plagiarism plugins).
        // Fixed in both settings_url/page_url (plugin registry) and goto_url (quick links grid).
        upgrade_block_savepoint(true, 2026030600243, 'aiplugin_nav');
    }

    if ($oldversion < 2026031800314) {
        // v2.3.14: No DB changes.
        upgrade_block_savepoint(true, 2026031800314, 'aiplugin_nav');
    }

    if ($oldversion < 2026031800315) {
        // v2.3.15: No DB changes.
        upgrade_block_savepoint(true, 2026031800315, 'aiplugin_nav');
    }

    if ($oldversion < 2026031800316) {
        // v2.3.16: Attendance report page added. No DB changes.
        upgrade_block_savepoint(true, 2026031800316, 'aiplugin_nav');
    }

    if ($oldversion < 2026032200317) {
        // v2.3.17: FIX settings URLs — quiz_aigrader_settings → quiz_aigrader,
        // modsettingknowledgecheck → modsettingaiknowledgecheck. No DB changes.
        upgrade_block_savepoint(true, 2026032200317, 'aiplugin_nav');
    }

    if ($oldversion < 2026032200318) {
        // v2.3.18: ADD Learning & Assessment Mapping to all registries,
        // table icon SVG, docs URL map, quick access links. No DB changes.
        upgrade_block_savepoint(true, 2026032200318, 'aiplugin_nav');
    }

    if ($oldversion < 2026032200319) {
        // v2.3.19: Learning mapping quick access links and lang strings. No DB changes.
        upgrade_block_savepoint(true, 2026032200319, 'aiplugin_nav');
    }

    if ($oldversion < 2026032200320) {
        // v2.3.20: FIX Learning mapping moved to Time Saving Plugins category. No DB changes.
        upgrade_block_savepoint(true, 2026032200320, 'aiplugin_nav');
    }

    if ($oldversion < 2026032200321) {
        // v2.3.21: FIX Learning mapping moved back to AI Plugins (ai_credit/ai). No DB changes.
        upgrade_block_savepoint(true, 2026032200321, 'aiplugin_nav');
    }

    if ($oldversion < 2026032300322) {
        // v2.3.22: Renamed to AI Learning and Assessment Mapping. Added AI Course Information. No DB changes.
        upgrade_block_savepoint(true, 2026032300322, 'aiplugin_nav');
    }

    if ($oldversion < 2026032400100) {
        // v2.3.25: Attendance Report — 9 bugs fixed (course filter on stat cards, AVG grade rate,
        // N+1 eliminated, LIMIT raw SQL, userdate, at-risk table, CSV export, capability, label).
        // No DB schema changes.
        upgrade_block_savepoint(true, 2026032400100, 'aiplugin_nav');
    }

    if ($oldversion < 2026032400101) {
        // v2.3.26: Routine release packaging following v2.3.25 attendance report fixes. No DB changes.
        upgrade_block_savepoint(true, 2026032400101, 'aiplugin_nav');
    }

    if ($oldversion < 2026032400102) {
        // v2.3.27: FIX — AI Quiz Maker renamed throughout block (was AI Essay Maker). No DB schema changes.
        upgrade_block_savepoint(true, 2026032400102, 'aiplugin_nav');
    }

    if ($oldversion < 2026032400103) {
        // v2.3.28: PERF — Credits now loaded async via AMD JS. Removed two synchronous HTTP calls from
        // get_content() (up to 15s page delay). New external function block_aiplugin_nav_get_credits
        // with 5-minute server-side cache. No DB schema changes.
        upgrade_block_savepoint(true, 2026032400103, 'aiplugin_nav');
    }

    if ($oldversion < 2026032400104) {
        // v2.3.29: BUILD FIX — Synced amd/build/credits.js and amd/build/credits.min.js to match
        // amd/src/credits.js exactly (identical CRC). No logic changes, no DB schema changes.
        upgrade_block_savepoint(true, 2026032400104, 'aiplugin_nav');
    }

    if ($oldversion < 2026032500105) {
        // v2.3.30: DASHBOARD LOAD PERFORMANCE — 4 independent bottlenecks fixed:
        //   (1) is_plugin_installed(): static cache for get_plugin_list() per type — previously
        //       called 44+ times per admin page load; now calls once per unique plugin type.
        //   (2) get_plugin_version_data(): new combined function reads version.php ONCE per plugin
        //       instead of twice (old get_plugin_version + get_plugin_numeric_version both opened
        //       the same file separately). Static cache prevents re-reads within the same request.
        //   (3) render_plugin_management_section(): 5-minute cross-request cache (Moodle config)
        //       stores installed status + version data for all 44 plugins. Cold load reads 44
        //       version.php files; warm loads (most page loads) skip all file I/O. Cache is
        //       invalidated automatically on successful auto_install or auto_update.
        //   (4) user_has_role_shortname(): static cache per user+shortname per request — was a
        //       raw SQL JOIN on every page load for every user.
        //   Net result: admin dashboard load reduced from ~44 file includes per request to 0
        //   (cache hit) or 44 (one cold-start every 5 minutes). No DB schema changes.
        upgrade_block_savepoint(true, 2026032500105, 'aiplugin_nav');
    }

    if ($oldversion < 2026032500110) {
        // v2.3.31: Fix for local_aiconfig not appearing after manual installation.
        // (1) Plugin Manager grid now busts its 5-minute cache instantly when Moodle's plugin
        //     count changes — previously the cache had no invalidation on manual plugin installs.
        // (2) Settings dropdown "Install First" badge now shows "Foundation" (green) when
        //     local_aiconfig is already installed, amber "Install First" only when not yet installed.
        // (3) local_aiconfig now renders at the top of the AI Plugins column with a green
        //     "Foundation Plugin — Install First" header instead of being buried among 15+ cards.
        // No DB schema changes.
        upgrade_block_savepoint(true, 2026032500110, 'aiplugin_nav');
    }

    if ($oldversion < 2026032500111) {
        // v2.3.32: FIX — AI Quiz Maker completely disconnected from Quick Links block.
        // 7 references still pointed at old local_essaymaker component name; all corrected
        // to local_aiquizmaker/aiquizmaker. No DB schema changes.
        upgrade_block_savepoint(true, 2026032500111, 'aiplugin_nav');
    }

    if ($oldversion < 2026032700133) {
        // v2.3.33: VERSION BUMP — Clean master release bump following v2.3.32 fix.
        // No DB schema changes.
        upgrade_block_savepoint(true, 2026032700133, 'aiplugin_nav');
    }

    if ($oldversion < 2026032700134) {
        // v2.3.34: ADD — Paddle Price Mapping page added to Reports section.
        // No DB schema changes.
        upgrade_block_savepoint(true, 2026032700134, 'aiplugin_nav');
    }

    if ($oldversion < 2026032700135) {
        // v2.3.35: ADD — BigBlueButton (mod_bigbluebuttonbn) and BigBlueButton Recordings
        // (mod_recordingsbn) added to get_complete_plugin_registry() so admins see update
        // available badges for these companion plugins when a new build is released.
        // No DB schema changes.
        upgrade_block_savepoint(true, 2026032700135, 'aiplugin_nav');
    }

    if ($oldversion < 2026032700136) {
        // v2.3.36: FIX — BigBlueButton (mod_bigbluebuttonbn) and BigBlueButton Recordings
        // (mod_recordingsbn) removed from get_complete_plugin_registry(). These plugins are
        // still in testing and must not appear in customer Plugin Manager dashboards until
        // production-ready. Entries commented out in block_aiplugin_nav.php; no PHP, DB,
        // or AMD changes. No DB schema changes.
        upgrade_block_savepoint(true, 2026032700136, 'aiplugin_nav');
    }

    if ($oldversion < 2026040100137) {
        // v2.3.37: FEATURE — Credit gate for Time Saving Plugins in Plugin Manager.
        // New block_aiplugin_nav_plugin_unlock external function added (services.php +
        // classes/external/plugin_unlock.php). Calls POST /api/plugin-unlock server-side
        // so credentials are never exposed to the browser. Install buttons for the 9
        // credit-gated plugins (groupmanager, courseversion, sitefont, cohortbranding,
        // benchmarks, simple2fa, groupcap, paymentunlockassign, essayguard) now show a
        // confirmation popup before deducting credits. No DB schema changes.
        upgrade_block_savepoint(true, 2026040100137, 'aiplugin_nav');
    }

    // v2.3.38: VERSION BUMP — Clean release following v2.3.37 credit gate feature.
    // No DB schema changes.
    if ($oldversion < 2026040100138) {
        upgrade_block_savepoint(true, 2026040100138, 'aiplugin_nav');
    }

    // v2.3.39: READY — BigBlueButton (mod_bigbluebuttonbn) and BigBlueButton Recordings
    // (mod_recordingsbn) re-enabled in get_complete_plugin_registry(). Both plugins now
    // have status 'ready' — un-commented from the Plugin Manager grid.
    // No DB schema changes.
    if ($oldversion < 2026040100139) {
        upgrade_block_savepoint(true, 2026040100139, 'aiplugin_nav');
    }

    // v2.3.40: TESTING — BigBlueButton (mod_bigbluebuttonbn) and BigBlueButton Recordings
    // (mod_recordingsbn) removed from get_complete_plugin_registry(). Both plugins moved
    // back to 'testing' status — commented out from the Plugin Manager grid pending full
    // end-to-end testing on a live BBB server. No DB schema changes.
    if ($oldversion < 2026040100140) {
        upgrade_block_savepoint(true, 2026040100140, 'aiplugin_nav');
    }

    // v2.3.41: ADD — Course Availability Delay (local_courseavailabilitydelay) added to master
    // plugin registry (settings + manage.php page URL), Time Saving Plugins grid (clock icon,
    // 1000 credits), manage dropdown, and docs URL map. No DB schema changes.
    // version.php → 2026040901141.
    if ($oldversion < 2026040901141) {
        upgrade_block_savepoint(true, 2026040901141, 'aiplugin_nav');
    }

    // v2.3.42: FIX — Purge All Caches now calls purge_all_caches() instead of
    // cache_helper::purge_all(). The complete purge resets MUC caches, theme/CSS revision,
    // language pack cache, JS/template caches — identical to Moodle admin page. Previously
    // only MUC caches were cleared which is why the operation appeared to finish too quickly.
    // No DB schema changes. version.php → 2026040902000.
    if ($oldversion < 2026040902000) {
        upgrade_block_savepoint(true, 2026040902000, 'aiplugin_nav');
    }

    // v2.3.43: MAINTENANCE — upgrade.php savepoints backfilled for v2.3.41 and v2.3.42 which
    // were shipped without savepoints. No DB schema changes. version.php → 2026040902001.
    if ($oldversion < 2026040902001) {
        upgrade_block_savepoint(true, 2026040902001, 'aiplugin_nav');
    }

    // v2.3.44: SYNC — Course Availability Delay bumped to v1.0.3 (curl fix: switched from raw
    // PHP curl_init() to Moodle \curl class so unlock API calls succeed on Moodle hosting
    // environments). No block code changes. No DB schema changes.
    // version.php → 2026041000044.
    if ($oldversion < 2026041000044) {
        upgrade_block_savepoint(true, 2026041000044, 'aiplugin_nav');
    }

    // v2.3.45: SYNC — AI Support (local_moodlesupport v1.66.3) and Activity Navigation
    // (local_activitynav v1.4.10) ZIP structure fixed. Both ZIPs previously lacked the
    // wrapping plugin folder — Moodle rejected installs with "version.php not found after
    // extraction". Rebuilt ZIPs with correct structure. No block code changes.
    // No DB schema changes. version.php → 2026041100045.
    if ($oldversion < 2026041100045) {
        upgrade_block_savepoint(true, 2026041100045, 'aiplugin_nav');
    }
    // v2.3.46: AMD ENCODING FIX: All non-ASCII characters (em dashes, arrows, box-drawing chars, ellipsis, bullets, emoji, accented Latin) scrubbed from all AMD JS files (amd/src, amd/build, amd/build/*.min.js). Root cause of Moodle primary/secondary navigation menus disappearing site-wide: non-ASCII bytes in any installed plugin's AMD file cause a SyntaxError inside RequireJS's first.js bundle, throwing "No define call for core/first" and aborting the entire AMD module chain. No PHP, DB schema, or functional changes in this release.
    if ($oldversion < 2026042200046) {
        upgrade_block_savepoint(true, 2026042200046, 'aiplugin_nav');
    }

    // v2.3.47: FIX — Em dash (U+2014) in PHP string inside js_amd_inline() caused SyntaxError
    // in RequireJS first.js bundle, aborting the entire AMD chain and hiding primary/secondary
    // navigation menus site-wide. Replaced em dash with plain ASCII hyphen. No DB schema changes.
    if ($oldversion < 2026042200047) {
        upgrade_block_savepoint(true, 2026042200047, 'aiplugin_nav');
    }

    // v2.3.48: FIX — settings.php guard changed from $ADMIN->fulltree to $hassiteconfig to
    // prevent sectionerror on admin/settings.php when settings section fails to register.
    // Savepoint added for v2.3.47 which was released without one. No DB schema changes.
    if ($oldversion < 2026042300048) {
        upgrade_block_savepoint(true, 2026042300048, 'aiplugin_nav');
    }

    // v2.3.49: FIX — clock icon missing from get_icon_svg() icon map. Course Availability Delay
    // uses 'clock' as its icon in both the admin quick links registry and the Time Saving Plugins
    // grid, but 'clock' was never added to the $icons array in get_icon_svg(). The function
    // returned an empty string causing a blank icon to render. Added clock SVG path. No DB schema changes.
    if ($oldversion < 2026042500049) {
        upgrade_block_savepoint(true, 2026042500049, 'aiplugin_nav');
    }

    // v2.3.50: ADD — AI RTO Compliance (local_rtocompliance) added to master plugin registry.
    // When installed, it now appears in the Reports tab (audit log), Settings (ai_credit group),
    // and Manage section of the Quick Links block. No DB schema changes.
    if ($oldversion < 2026050800050) {
        upgrade_block_savepoint(true, 2026050800050, 'aiplugin_nav');
    }

    // v2.3.51: ADD — AI RTO Compliance added to Plugin Manager upgrade grid
    // (get_complete_plugin_registry). Now appears as an upgradeable plugin card alongside
    // all other AI plugins. No DB schema changes.
    if ($oldversion < 2026050800051) {
        upgrade_block_savepoint(true, 2026050800051, 'aiplugin_nav');
    }

    // v2.3.52: FIX — AI RTO Compliance settings_url corrected. Plugin registers pages via
    // admin_externalpage (not admin_settingpage), so section=local_rtocompliance caused a
    // sectionerror. Fixed to section=local_rtocompliance_dashboard which is the correct
    // registered externalpage name. No DB schema changes.
    if ($oldversion < 2026050800052) {
        upgrade_block_savepoint(true, 2026050800052, 'aiplugin_nav');
    }

    // v2.3.53: UPDATE — AI RTO Compliance report_url changed from auditlog.php to
    // ai_usage_report.php. Reports tab now links to the new AI Credit Usage Report page.
    // No DB schema changes.
    if ($oldversion < 2026050800053) {
        upgrade_block_savepoint(true, 2026050800053, 'aiplugin_nav');
    }

    // v2.3.54: ADD — DocGuard (plagiarism_docguard) added to plugin registry and Quick Links sidebar.
    // No DB schema changes.
    if ($oldversion < 2026051200055) {
        upgrade_block_savepoint(true, 2026051200055, 'aiplugin_nav');
    }

    // v2.3.56: FIX-NAV-SESSION-LOCK + FIX-NAV-CURL — debug_credits_check() and get_credits_balance()
    // switched from raw curl_init() to Moodle \curl + write_close() before HTTP calls.
    // No DB schema changes.
    if ($oldversion < 2026051200056) {
        upgrade_block_savepoint(true, 2026051200056, 'aiplugin_nav');
    }

    // v2.3.57: FIX-CURL-BATCH — Replaced all remaining raw curl_init() calls in externals
    //   with Moodle \curl wrapper. No DB schema changes.
    if ($oldversion < 2026051200057) {
        upgrade_block_savepoint(true, 2026051200057, 'aiplugin_nav');
    }

    // v2.3.58: FIX-NAV-DOCS-MISSING — Added docs URLs for 7 plugins missing Docs buttons:
    //   plagiarism_docguard, plagiarism_essayguard, local_aiquizremedial, mod_slideshow,
    //   mod_courseinfo, local_paymentunlockassign, paygw_paddle. No DB schema changes.
    if ($oldversion < 2026051300058) {
        upgrade_block_savepoint(true, 2026051300058, 'aiplugin_nav');
    }

    // v2.3.59: FIX-ICON — Added file-search icon to get_icon_svg() map so DocGuard displays
    //   its icon correctly in the Quick Links sidebar. No DB schema changes.
    if ($oldversion < 2026051300059) {
        upgrade_block_savepoint(true, 2026051300059, 'aiplugin_nav');
    }

    // v2.3.60: ADD-PLAGIARISM-LINKS — Essay Guard and DocGuard report.php URLs added to
    //   Reports dropdown. "Manage Plagiarism Plugins" link injected into Settings > Admin
    //   Plugins column when either plugin is installed (controls global plagiarism_use_*
    //   enable toggles). No DB schema changes.
    if ($oldversion < 2026051400060) {
        upgrade_block_savepoint(true, 2026051400060, 'aiplugin_nav');
    }

    // v2.3.71: SAVEPOINT-BUMP — no-op marker for clean upgrade path. No DB schema changes.
    if ($oldversion < 2026060400071) {
        upgrade_block_savepoint(true, 2026060400071, 'aiplugin_nav');
    }

    // v2.3.76: ADD-WORKSHOPS-PRODUCTEXPLAINER — Added Workshop Scheduler (local_workshops)
    //   and AI Product Explainer (mod_productexplainer) to master plugin registry,
    //   complete plugin grid, quick access links, docs URL table, and lang strings.
    //   No DB schema changes.
    if ($oldversion < 2026061000076) {
        upgrade_block_savepoint(true, 2026061000076, 'aiplugin_nav');
    }

    // v2.3.77: Added 'presentation' SVG to get_icon_svg() so AI Product Explainer
    // icon renders correctly in the Quick Links block. No DB schema changes.
    if ($oldversion < 2026061000077) {
        upgrade_block_savepoint(true, 2026061000077, 'aiplugin_nav');
    }

    // v2.3.79: FIX-WORKSHOPS-CALENDAR-ICON — Added missing 'calendar' key to
    // get_icon_svg() lookup table. Workshop Scheduler quick-link rows were showing
    // a blank icon because the registry uses icon='calendar' but only 'calendar-icon'
    // existed in the SVG map. No DB schema changes.
    if ($oldversion < 2026061200079) {
        upgrade_block_savepoint(true, 2026061200079, 'aiplugin_nav');
    }

    // v2.3.80: RENAME-AI-SLIDE-FLOW — Renamed mod_productexplainer display name from
    // 'AI Product Explainer' to 'AI Slide Flow' in master plugin registry, complete
    // plugin grid, quick access links, and lang strings. No DB schema changes.
    if ($oldversion < 2026061300080) {
        upgrade_block_savepoint(true, 2026061300080, 'aiplugin_nav');
    }

    // v2.3.81: FIX-UPTODATE-TOAST — Added in-block "All plugins are up to date" toast.
    // No DB schema changes.
    if ($oldversion < 2026062000081) {
        upgrade_block_savepoint(true, 2026062000081, 'aiplugin_nav');
    }

    // v2.3.83: ADD-UPDATE-POPUP — Full-screen plugin update popup added to QuickLinks block.
    // No DB schema changes.
    if ($oldversion < 2026062500083) {
        upgrade_block_savepoint(true, 2026062500083, 'aiplugin_nav');
    }

    // v2.3.84: ADD-STUDENTEMAIL-REGISTRY — Added local_studentemail and auth_studentemail to master
    // plugin registry, complete plugin registry, and docs URL table.
    // No DB schema changes.
    if ($oldversion < 2026062600084) {
        upgrade_block_savepoint(true, 2026062600084, 'aiplugin_nav');
    }

    // v2.3.88: SHOW-CREDITS-TEACHERS — Credit balance badge now shown to editing teachers
    // and non-editing teachers in addition to site admins. No DB schema changes.
    if ($oldversion < 2026063000088) {
        upgrade_block_savepoint(true, 2026063000088, 'aiplugin_nav');
    }

    // v2.3.89: SHOW-CREDITS-LMSHSADMIN — Credit balance badge now also shown to users
    // with the lmshsadmin role (LMS Hosting Admin). No DB schema changes.
    if ($oldversion < 2026063000089) {
        upgrade_block_savepoint(true, 2026063000089, 'aiplugin_nav');
    }

    // v2.3.90: FIX-GET-CREDITS-GLOBALS — get_credits.php execute() was missing global $DB, $USER.
    // Non-siteadmins (including lmshsadmin) hit a null-dereference on $DB->record_exists_sql(),
    // causing the web service to throw and the credits badge to stay blank. Also expanded the
    // allowed-user check to include moodle/site:configview capability and more role shortnames.
    // No DB schema changes.
    if ($oldversion < 2026070300090) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['classes/external/get_credits.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_block_savepoint(true, 2026070300090, 'aiplugin_nav');
    }

    // v2.3.91: FIX-QUIZACCESS-DOCS-URL — quizaccess_aigrader docs link in get_plugin_docs_url()
    // pointed to /docs/ai-grader (main essay grader page) instead of /docs/quiz-access-rule.
    // No DB schema changes.
    if ($oldversion < 2026070300091) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['block_aiplugin_nav.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_block_savepoint(true, 2026070300091, 'aiplugin_nav');
    }

    // v2.3.92: ADD-AILOGIN — Added local_ailogin (AI Login Designer) to master plugin registry,
    // complete plugin grid, and docs URL map. No DB schema changes.
    if ($oldversion < 2026070800092) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['block_aiplugin_nav.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_block_savepoint(true, 2026070800092, 'aiplugin_nav');
    }

    // v2.3.93: FIX-LAYOUT-ICON — Added 'layout' SVG to get_icon_svg() lookup table.
    // local_ailogin (AI Login Designer) uses icon='layout' but 'layout' was missing from
    // the icon map, so the Manage dropdown showed a blank space instead of an icon.
    if ($oldversion < 2026070800093) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['block_aiplugin_nav.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_block_savepoint(true, 2026070800093, 'aiplugin_nav');
    }

    // v2.3.94: AI-CATEGORY-AILOGIN — Moved local_ailogin (AI Login Designer) from 'utility'
    // category to 'ai_credit'/'ai' categories in both the master plugin registry and complete
    // plugin registry. It now appears in the AI Plugins section (left columns) of the plugin
    // management grid and in the "AI Plugins" column of the Settings dropdown, not Time Saving.
    if ($oldversion < 2026070800094) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['block_aiplugin_nav.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_block_savepoint(true, 2026070800094, 'aiplugin_nav');
    }

    // v2.3.95: ADD-SMARTWORKBOOK — Added mod_smartworkbook (AI Smart Workbook) to master plugin
    // registry (Settings + Manage dropdowns), complete plugin grid, and docs URL map. It now
    // appears in the AI Plugins section (ai_credit category) of the plugin management grid.
    if ($oldversion < 2026070900095) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['block_aiplugin_nav.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_block_savepoint(true, 2026070900095, 'aiplugin_nav');
    }

    // v2.3.96: ADD-SMARTWORKBOOK-QUICKLINKS
    // Added mod_smartworkbook (AI Smart Workbook) to get_ai_tools_registry() so
    // it appears in the AI Tools quick-links section of the block. Was already in
    // the plugin management registry (get_master_plugin_registry) but missing from
    // the quick-links array. Also added lang strings ai_smart_workbook and
    // ai_smart_workbook_access. No DB schema changes.
    if ($oldversion < 2026070900096) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['block_aiplugin_nav.php', 'lang/en/block_aiplugin_nav.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_block_savepoint(true, 2026070900096, 'aiplugin_nav');
    }

    // v2.3.97: ADD-SMARTWORKBOOK-COMPLETE-REGISTRY
    // Added mod_smartworkbook (AI Smart Workbook) to get_complete_plugin_registry().
    // Plugin was present in get_master_plugin_registry() and get_ai_tools_registry()
    // but absent from get_complete_plugin_registry(), so it never appeared in the
    // admin Plugin Manager panel and could not be downloaded from the QuickLinks block.
    // No DB schema changes.
    if ($oldversion < 2026071100097) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['block_aiplugin_nav.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_block_savepoint(true, 2026071100097, 'aiplugin_nav');
    }

    // ADD-TRAININGPLAN (v2.3.98): Added block_trainingplan (Training Plan) to Time Saving Plugins
    // utility grid (category=utility, credits_required=5000, icon=calendar-clock).
    // Added calendar-clock SVG to get_icon_svg() icon map.
    // Added block_trainingplan docs URL to get_plugin_docs_url().
    // No DB schema changes.
    if ($oldversion < 2026071300098) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['block_aiplugin_nav.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_block_savepoint(true, 2026071300098, 'aiplugin_nav');
    }

    // ADD-TRAININGPLAN-DROPDOWN (v2.3.99): Added block_trainingplan (Training Plan) to the
    // $admin_settings dropdown registry so it appears in the Quick Links block Settings dropdown
    // under the "Time Saving Plugins" column. Was already in the Plugin Manager grid (utility category)
    // but absent from admin_settings, so it never showed in the dropdown quick access.
    // No DB schema changes.
    if ($oldversion < 2026071300099) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['block_aiplugin_nav.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_block_savepoint(true, 2026071300099, 'aiplugin_nav');
    }

    // 2026071300100 - ADD-TRAININGPLAN-NOTIFICATIONS: Added dedicated
    // 'block_trainingplan_notifications' entry to get_master_plugin_registry()
    // so "Training Plan — Notifications" appears as a named item in the Settings
    // dropdown. Previously the plugin only appeared as "Training Plan"; admins
    // looking for "notifications" in the dropdown had no result. Updated the
    // utility grid card description and access hint to reference the Notification
    // Settings section. No DB schema changes.
    if ($oldversion < 2026071300100) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['block_aiplugin_nav.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_block_savepoint(true, 2026071300100, 'aiplugin_nav');
    }

    // v2.4.1: FIX-TRAININGPLAN-SETTINGS-URL
    // Fixed Training Plan settings URL in all three registry locations.
    // Was: /admin/settings.php?section=block_trainingplan (invalid section — gave "Section error!")
    // Now: /admin/settings.php?section=blocksettingtrainingplan (correct Moodle block section format)
    // Affected: get_master_plugin_registry() block_trainingplan + block_trainingplan_notifications entries,
    // and the utility grid goto_url. No DB schema changes.
    if ($oldversion < 2026071300101) {
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['block_aiplugin_nav.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_block_savepoint(true, 2026071300101, 'aiplugin_nav');
    }

    if ($oldversion < 2026072100102) {
        // REBRAND-LMS-LABS: Rebranded all references from lms-labs.com to lms-labs.com.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['block_aiplugin_nav.php', 'version.php', 'lang/en/block_aiplugin_nav.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_block_savepoint(true, 2026072100102, 'aiplugin_nav');
    }

    if ($oldversion < 2026072300103) {
        // FIX-AUTOUPDATE-ERROR-REPORTING: Auto Update All now shows each plugin's actual
        // failure reason (e.g. "Cannot write to ... Check file permissions.") instead of
        // the generic "X failed. Please refresh the page." message that swallowed all detail.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['block_aiplugin_nav.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_block_savepoint(true, 2026072300103, 'aiplugin_nav');
    }

    if ($oldversion < 2026072300104) {
        // FIX-VERSION-CHECK-RETRY: Check for Updates now retries once after 3 s on failure
        // (handles Replit cold-start / brief server wake-up delays). Also added
        // X-Requested-With to CORS Access-Control-Allow-Headers on the version endpoint.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['block_aiplugin_nav.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_block_savepoint(true, 2026072300104, 'aiplugin_nav');
    }

    if ($oldversion < 2026072300105) {
        // FIX-CORS-PROXY: Version check now uses a local PHP proxy (check_versions.php)
        // instead of calling lms-labs.com directly from the browser. PHP has no CORS
        // restrictions, so this works on every Moodle site regardless of browser policy
        // or domain name changes.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['block_aiplugin_nav.php', 'check_versions.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_block_savepoint(true, 2026072300105, 'aiplugin_nav');
    }

    if ($oldversion < 2026072300106) {
        // FIX-API-DOMAIN: Changed all server-side API call URLs from lms-labs.com to
        // lms-labs.com (working DNS). Also fixed check_versions.php require_once path
        // from ../../../config.php (3 levels) to __DIR__ . /../../config.php (2 levels,
        // absolute) — blocks/ sits 2 levels inside Moodle root, not 3.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['block_aiplugin_nav.php', 'check_versions.php', 'version.php',
                      'db/upgrade.php', 'classes/external/get_credits.php',
                      'classes/external/plugin_unlock.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_block_savepoint(true, 2026072300106, 'aiplugin_nav');
    }

    if ($oldversion < 2026072300107) {
        // FIX-DOMAIN + FIX-LEGACY-ESSAYMAKER: All lms-labs.com URLs replaced with
        // lms-labs.com. local_essaymaker added to plugin registry so sites with
        // the old legacy plugin get an update offer that fixes the namespace collision.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['block_aiplugin_nav.php', 'version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_block_savepoint(true, 2026072300107, 'aiplugin_nav');
    }

    if ($oldversion < 2026072300108) {
        // FIX-API-DOMAIN: Rebuilt ZIP with corrected API endpoint (lms-labs.com -> lms-labs.com).
        // classes/external/get_credits.php, plugin_unlock.php now call lms-labs.com.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'db/upgrade.php', 'classes/external/get_credits.php', 'classes/external/plugin_unlock.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_block_savepoint(true, 2026072300108, 'aiplugin_nav');
    }

    if ($oldversion < 2026072300109) {
        // FIX-API-DOMAIN: Reverted API endpoint to lms-labs.com (correct domain).
        // lms-labs.com was the original single-plugin domain; lms-labs.com is correct.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_block_savepoint(true, 2026072300109, 'aiplugin_nav');
    }

    if ($oldversion < 2026072300110) {
        // FIX-DOMAIN: CSS/template references updated from old brand to lms-labs.com.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_block_savepoint(true, 2026072300110, 'aiplugin_nav');
    }

    if ($oldversion < 2026072300111) {
        // v1.7.0: Version checker now calls lms-labs.com directly from the browser.
        // CORS headers are already set on the API. PHP proxy kept as fallback only.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'block_aiplugin_nav.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_block_savepoint(true, 2026072300111, 'aiplugin_nav');
    }

    if ($oldversion < 2026072300112) {
        // v1.8.0: Multi-endpoint fallback chain. check_versions.php also tries all endpoints.
        // Order: lms-labs.com → PHP proxy → ai-grader-site-nct185.replit.app → essaygraderai.app
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'block_aiplugin_nav.php', 'check_versions.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_block_savepoint(true, 2026072300112, 'aiplugin_nav');
    }

    if ($oldversion < 2026072300113) {
        // v1.8.1: PHP credit fetch now tries multiple endpoints (lms-labs.com → replit.app → essaygraderai.app).
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'block_aiplugin_nav.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_block_savepoint(true, 2026072300113, 'aiplugin_nav');
    }

    if ($oldversion < 2026072300114) {
        // Domain update: lms-labs.com → lms-labs.com
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'lib.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_block_savepoint(true, 2026072300114, 'aiplugin_nav');
    }

    if ($oldversion < 2026072300115) {
        // CSS/template domain update: lms-labs.com → lms-labs.com
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'db/upgrade.php'] as $_f) {
                if (file_exists($_pluginDir . '/' . $_f)) opcache_invalidate($_pluginDir . '/' . $_f, true);
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_block_savepoint(true, 2026072300115, 'aiplugin_nav');
    }

    if ($oldversion < 2026072400116) {
        // FIX-ENDPOINT-ORDER (v2.4.16): PHP proxy moved to position #1 in JS endpoint list.
        // check_versions.php reordered to Replit URL first; essaygraderai.app removed.
        // Timeout reduced from 10s to 5s. No DB schema changes.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'block_aiplugin_nav.php', 'check_versions.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_block_savepoint(true, 2026072400116, 'aiplugin_nav');
    }

    if ($oldversion < 2026072400117) {
        // FIX-GET-CREDITS-ENDPOINT (v2.4.17): classes/external/get_credits.php was calling
        // lms-labs.com directly — blocked on Vultr datacenter IPs, causing the credits balance
        // to never appear in the Quick Links block. Fixed to try Replit URL first (always
        // reachable), lms-labs.com as fallback. Per-endpoint timeout 10s → 5s. No DB changes.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'db/upgrade.php', 'classes/external/get_credits.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_block_savepoint(true, 2026072400117, 'aiplugin_nav');
    }

    if ($oldversion < 2026080100132) {
        // PIPELINE-CROSS-REGISTRY (v2.4.29): No DB changes. Savepoint marker only.
        // Pipeline now verifies get_complete_plugin_registry() covers all PLUGIN_ZIP_CONFIG
        // components when block_aiplugin_nav is uploaded — future-proof guarantee.
        upgrade_block_savepoint(true, 2026080100132, 'aiplugin_nav');
    }

    if ($oldversion < 2026080100133) {
        // UPDATE-SYSTEM-HARDENING (v2.4.30): No DB changes. Savepoint marker only.
        // 6 fixes to the built-in auto-update system: URL allowlist, component verification,
        // exception temp-file cleanup, JSON.parse guard, downloadUrl validation, DOM XSS fix.
        upgrade_block_savepoint(true, 2026080100133, 'aiplugin_nav');
    }

    if ($oldversion < 2026072400118) {
        // CLEANUP-DEAD-CODE-ENDPOINTS (v2.4.18): Two unused private methods in
        // block_aiplugin_nav.php (debug_credits_check, get_credits_balance) still had
        // lms-labs.com as the first endpoint and essaygraderai.app as a third entry.
        // Although never called (credits load via AMD AJAX since v2.3.28), the stale
        // references were misleading. Both arrays now use Replit URL first,
        // lms-labs.com second, essaygraderai.app removed. No DB changes.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'db/upgrade.php', 'block_aiplugin_nav.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_block_savepoint(true, 2026072400118, 'aiplugin_nav');
    }

    return true;
}