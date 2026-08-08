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
 * AI Dashboard Quick Links v2.4.47 - Version information
 *
 * v2.4.47: FIX-NUMERIC-SCHEME (8 Aug 2026) — Corrected 10-digit $plugin->version in v2.4.46
 *          (2026080746) which was numerically SMALLER than prior 13-digit installed values
 *          (e.g. 2026080600204 from v2.4.45), causing every Moodle site to permanently show
 *          "all plugins up to date" — no update notification was ever triggered.
 *          Fix: new numeric 2026080800205 is 13 digits and strictly greater than every prior
 *          release. Hardened compareVersions() in the block JavaScript to compare semantic
 *          release strings (e.g. v2.4.47 > v2.4.38) as the primary signal, with zero-padded
 *          numeric fallback so a shorter numeric can never be misread as larger. Added
 *          permanent guardrail in the server release pipeline: any upload whose numeric is not
 *          exactly 13 digits, has an invalid YYYYMMDD date prefix, or is not strictly greater
 *          than the currently promoted numeric is now hard-rejected with a clear error naming
 *          the component and both numerics. Added /api/plugins/version-audit endpoint. Also
 *          repaired local_lmshomepage source numeric (was 10-digit 2026080304; corrected to
 *          13-digit 2026080800210 so future rebuilds cannot downgrade below production).
 *          No DB schema changes. Savepoint 2026080800205.
 *
 * v2.4.46: FIX-BRANDING (7 Aug 2026) — Reworded "Could not reach the AI Grader update server"
 *          to "LMS Labs update server". No DB changes. Savepoint 2026080746 [10-digit — fixed in v2.4.47].
 *
 * v2.4.30: UPDATE-SYSTEM-HARDENING (1 Aug 2026) — 6 security and reliability fixes to the
 *          built-in check-for-updates and auto-update system:
 *          (1) URL allowlist in plugin_updater.php: downloadUrl must come from lms-labs.com
 *              or ai-grader-site-nct185.replit.app — prevents SSRF / supply-chain attacks.
 *          (2) Component verification: after installing a ZIP, $plugin->component inside
 *              version.php must match the requested component — prevents installing plugin_a
 *              files under plugin_b's directory.
 *          (3) Exception temp-file cleanup: catch blocks in both auto_update_plugin and
 *              auto_install_plugin now delete $zipfile and $extractdir on exception.
 *          (4) JSON.parse guard: installed plugin data is now parsed with try/catch so a
 *              corrupt DOM data attribute cannot throw and break the entire version check.
 *          (5) downloadUrl validation: undefined/empty downloadUrl now skipped silently
 *              instead of being queued and sent to the PHP updater as an empty string.
 *          (6) DOM XSS fix in "Update All" failure modal: plugin names and error reasons
 *              from the API response are now HTML-escaped before insertion into the modal.
 *          No DB schema changes. Savepoint 2026080100133.
 *
 * v2.4.29: PIPELINE-CROSS-REGISTRY (1 Aug 2026) — future-proof guarantee: the LMS-Labs
 *          Plugin Release Pipeline now fails with an error if any plugin in PLUGIN_ZIP_CONFIG
 *          is absent from get_complete_plugin_registry() when a Quicklinks ZIP is uploaded.
 *          Root cause of "Beacon showing old version in Quicklinks": the installed Beacon on
 *          the Moodle site was old; Quicklinks correctly read it from the filesystem. Going
 *          forward the pipeline enforces that every server plugin is registered before any
 *          Quicklinks release can ship. No PHP/DB/AMD changes. Savepoint 2026080100132.
 *
 * v2.4.28: FIX-TINY-PATH (31 Jul 2026): tiny_* plugins (e.g. tiny_aipagetemplates) now show
 *          their correct installed version in the Plugin Manager column.
 *
 * v2.4.20: ADD-BEACON — local_beacon (Beacon — Reports & Analytics) added to master plugin
 *          registry (settings_url → /admin/settings.php?section=local_beacon, page_url →
 *          /local/beacon/index.php, icon=bar-chart-2, category=utility) and complete plugin
 *          registry (category=utility, description, access, goto_url). Docs URL map updated.
 *          No DB schema changes. Savepoint 2026072602122.
 *
 * v2.4.19: SAVEPOINT-BUMP — version increment so Moodle detects update and serves latest
 *          block_aiplugin_nav zip (includes tiny_aipagetemplates / AI Page Templates in
 *          complete plugin registry and quick-access links). No code changes. Savepoint 2026072602121.
 *
 * v2.3.70: ADD-AIPAGETEMPLATES — tiny_aipagetemplates (AI Page Templates) added to master plugin
 *          registry (settings_url → /admin/settings.php?section=tiny_aipagetemplates, icon=layout,
 *          category=ai_credit) and complete plugin registry (category=ai, description, access path,
 *          goto_url). No DB schema changes. Savepoint 2026072600119.
 *
 * v2.3.37: FEATURE — Credit gate for Time Saving Plugins in Plugin Manager. The 9 credit-gated
 *          plugins (Groups Management, Groups Availability Condition, Course Version Control,
 *          Change Site Font, Cohort Branding, Assignment Benchmarks, Simple 2FA, Group Membership
 *          Limit, Payment Unlock Assignment, Essay Guard) now show a confirmation popup before
 *          deducting credits when the install button is clicked. New block_aiplugin_nav_plugin_unlock
 *          external function calls POST /api/plugin-unlock server-side so credentials are never
 *          exposed to the browser. Handles already-unlocked state gracefully (no double-charge).
 *          Credits cache is invalidated immediately after a successful unlock.
 *
 * v2.3.36: FIX — BigBlueButton and Recordings removed from Plugin Manager (still in testing).
 *
 * v2.3.31: FIX — AI Grader Central Config (local_aiconfig) not appearing in block after manual install.
 *          Three bugs fixed: (1) Plugin Manager grid had a 5-minute stale cache with no invalidation
 *          mechanism — now busts immediately when Moodle's plugin count changes (e.g. after any manual
 *          install via admin UI); (2) "Install First" badge in Settings dropdown was always shown even
 *          when local_aiconfig was already installed — now shows green "Foundation" badge for installed,
 *          amber "Install First" for not-yet-installed; (3) local_aiconfig was visually buried as just
 *          another card in a column of 15+ AI plugins — now renders at the top with a dedicated
 *          "Foundation Plugin — Install First" header in green so admins find it instantly.
 *
 * v2.3.30: ADD — format_aicourse added to master plugin registry.
 *
 * v2.3.29: BUILD FIX — Synced amd/build/credits.js and amd/build/credits.min.js to be identical to
 *          amd/src/credits.js (all three CRC hashes now match). Previously the build files were written
 *          separately and did not match src, meaning Moodle was serving stale AMD code. No logic changes.
 *
 * v2.3.28: PERF — Credits balance now loaded asynchronously via AMD AJAX instead of blocking page render.
 *          Removes two synchronous HTTP calls from get_content() (debug_credits_check 10s timeout +
 *          get_credits_balance 5s timeout = up to 15s page delay for admins). Credits placeholder renders
 *          instantly and populates after page load via block_aiplugin_nav_get_credits external function.
 *          Server-side 5-minute cache added to get_credits external function. No DB schema changes.
 *
 * v2.3.27: FIX — AI Quiz Maker renamed throughout: plugin registry name, plugin grid name, description, access path, and all three lang strings (ai_essay_maker, ai_essay_maker_access, ai_essay_maker_settings) updated from "AI Essay Maker" to "AI Quiz Maker". Plugin was renamed in v3.16.2 of the essaymaker plugin but the block was never updated. No DB schema changes.
 *
 * v2.3.26: VERSION BUMP — Routine release packaging following v2.3.25 attendance report fixes.
 *
 * v2.3.25: FIX Attendance Report — 9 bugs fixed: (1) course filter now applies to all four summary stat
 *          cards (previously only affected tables); (2) rate calculation changed from COUNT(grade>0)/total
 *          to AVG(grade)*100 to match Moodle's own attendance percentage calculation and correctly handle
 *          Late/Excused/Medical statuses; (3) N+1 DB queries eliminated — all course modules pre-loaded
 *          in one query instead of per-row get_coursemodule_from_instance() calls; (4) LIMIT 20 raw SQL
 *          replaced with Moodle-standard get_records_sql() limitnum param for cross-DB compatibility;
 *          (5) date() replaced with userdate() for correct Australian timezone display; (6) per-student
 *          at-risk table added showing all students below 80% threshold with links and email; (7) CSV
 *          export added (activities, at-risk students, recent sessions); (8) capability changed from
 *          moodle/site:config to moodle/site:viewreports so academic coordinators and managers can access;
 *          (9) "Students Tracked" card relabelled "Students Logged" with explanatory sub-note;
 *          grade clamped to 100% max to guard against older plugin 0-100 grade scale.
 * v2.3.24: Renamed AI Learning and Assessment Mapping to AI Mapping across all registries and lang strings.
 * v2.3.23: Added Course Prerequisite gates page to Reports section in QuickLinks block.
 * v2.3.22: Renamed to AI Learning and Assessment Mapping. Added AI Course Information plugin to all registries.
 * v2.3.21: FIX Learning & Assessment Mapping moved back to AI Plugins (category ai_credit/ai) — it is an AI-powered plugin using 100 credits per analysis
 * v2.3.20: FIX Learning & Assessment Mapping moved from AI Plugins to Time Saving Plugins (master: admin, complete: utility)
 * v2.3.19: BUMP version for clean release with learning mapping quick access links and lang strings
 * v2.3.18: ADD Learning & Assessment Mapping to plugin registry, table icon SVG, docs URL map
 *
 * v2.3.17: FIX settings URLs — quiz_aigrader_settings → quiz_aigrader, modsettingknowledgecheck → modsettingaiknowledgecheck
 *
 * v2.3.16: ADD Attendance Report — new site-wide Moodle Attendance summary report page added to Reports dropdown; auto-detected when mod_attendance is installed; shows stat cards (activities/sessions/students/overall rate), course filter, date range filter, per-activity breakdown table with attendance rates, and recent sessions table with deep-links into each activity's native report
 * v2.3.15: UPDATE AI Course Format report link in Reports dropdown now points to site-wide admin Q&A report
 * v2.3.12: FIX Essay Guard entry corrected to plagiarism_essayguard type/component/URLs (was local_essayguard)
 * v2.3.11: ADD Essay Guard to settings registry and plugin grid (Time Saving Plugins section)
 * v2.3.10: ADD Payment Unlock Assignment to plugin grid (Time Saving Plugins section)
 * v2.3.9: ADD Payment Unlock Assignment to settings registry
 * v2.3.8: FIX Auto Update All only includes installed plugins - uninstalled plugins are skipped (prevents 11 failed updates)
 * v2.3.7: FIX version comparison uses Moodle numeric versions (13-digit) to prevent downgrades - installed > server means no update needed
 * v2.3.6: FIX version comparison - use exact string match instead of semantic comparison which broke when version numbering schemes changed (e.g. 3.58.0 -> 3.6.2)
 * v2.3.5: Added AI PDF Assignment Grader to plugin registry, assignfeedback type mapping in get_plugin_version()
 * v2.3.4: Fixed Paddle settings_url to use Moodle standard section name (paymentgatewaypaddle)
 * v2.3.3: Fixed Paddle settings_url to use registered admin_settingpage section name (paygw_paddle_settings)
 * v2.3.2: Ensured paygw type mapping in get_plugin_version() for correct Paddle version display
 * v2.3.1: Fixed Paddle settings_url to use proper admin settings section (was /payment/accounts.php, now /admin/settings.php?section=paygw_paddle)
 * v2.3.0: Added Paddle Payment Gateway and AI Quiz Remedial Learning to Plugin Manager grid
 * v2.2.99: Added AI Video Activity to plugin registry, download list, quick access links, icon map, and docs URLs
 * v2.2.98: Credits total displayed prominently in the top navigation bar with colour-coded badge (green/orange/red)
 * v2.2.96: Added AI Slideshow with Voiceover to quick access links list (was missing from display array)
 * v2.2.95: Added AI Slideshow with Voiceover to plugin registry
 * v2.2.94: Added missing layers icon for AI Learning Activities in icon map
 * v2.2.93: Added AI Learning Activities to plugin registry, download list, quick access links, and docs URLs
 *
 * @package    block_aiplugin_nav
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'block_aiplugin_nav';
// NUMERIC SCHEME RULE: $plugin->version MUST always be exactly 13 digits in
// YYYYMMDD + 5-digit-sequence format (e.g. 2026080800205). It must be strictly
// greater than the previous release's numeric. Do NOT use shorter formats — a
// 10-digit value (e.g. 2026080746 used in v2.4.46) is numerically SMALLER than
// prior 13-digit installs (e.g. 2026080600204 from v2.4.45), which caused every
// Moodle site to permanently see "all up to date" and never receive an update.
// The server promotion pipeline now hard-rejects any upload that violates this rule.
$plugin->version = 2026080800205;  // YYYYMMDDNNNNN — 8 Aug 2026, sequence 205.
$plugin->release = 'v2.4.47';
$plugin->release_prev = '2.4.46';  // Previous release (had 10-digit numeric — fixed here).
$plugin->requires  = 2022041900;
$plugin->supported  = [400, 500];  // Moodle 4.0 to 5.x
$plugin->maturity  = MATURITY_STABLE; // ADD-BEACON — local_beacon added to plugin registry. // SAVEPOINT-BUMP — serve latest zip with tiny_aipagetemplates in registry. // FIX-ENDPOINT-ORDER (v2.4.16): PHP proxy (check_versions.php) moved to position #1 in ainav_endpoints JS array so the browser never waits 10s for a blocked lms-labs.com direct call before trying the proxy. check_versions.php internal endpoint list reordered to Replit URL first (always reachable) then lms-labs.com; essaygraderai.app (legacy dead domain) removed from both lists; per-endpoint cURL timeout reduced from 10s to 5s. Total worst-case version-check wait drops from ~40s to ~10s for Vultr/datacenter-IP Moodle servers. No DB schema changes. Savepoint 2026072400116. // FIX-DOMAIN + FIX-LEGACY-ESSAYMAKER (v2.4.7): (1) All remaining lms-labs.com URLs replaced with lms-labs.com throughout block_aiplugin_nav.php — affects Visit Website, Pricing, affiliate, and all /docs/* links. (2) local_essaymaker added to master plugin registry so sites with the old legacy plugin installed (pre-rename) will see "AI Quiz Maker (Legacy — update to fix)" with update available, allowing auto-update to v3.16.89 which carries correct namespace local_essaymaker in its class files, resolving the fatal "Cannot declare class local_aiquizmaker\hook\before_footer_html_generation because the name is already in use" PHP crash. No DB schema changes. Savepoint 2026072300107. // REBRAND-LMS-LABS (v2.4.2): Rebranded all references from lms-labs.com to lms-labs.com. Visit Website button now links to https://lms-labs.com. All docs URLs, API endpoints, and lang strings updated to lms-labs.com domain. No DB schema changes. // ADD-TRAININGPLAN (v2.3.98): Added block_trainingplan (Training Plan) to Time Saving Plugins utility grid (category=utility, credits_required=5000, icon=calendar-clock, goto_url=/admin/settings.php?section=block_trainingplan). Added calendar-clock SVG to get_icon_svg() icon map. Added block_trainingplan to get_plugin_docs_url() pointing to /docs/training-plan. No DB schema changes. // ADD-SMARTWORKBOOK-COMPLETE-REGISTRY (v2.3.97): Added mod_smartworkbook (AI Smart Workbook) to get_complete_plugin_registry(). Plugin was present in get_master_plugin_registry() and get_ai_tools_registry() but absent from get_complete_plugin_registry(), so it never appeared in the admin Plugin Manager panel and could not be downloaded from the QuickLinks block. No DB schema changes. // ADD-SMARTWORKBOOK (v2.3.95): Added mod_smartworkbook (AI Smart Workbook) to master plugin registry, complete plugin grid, and docs URL map — appears in AI Plugins section. No DB schema changes. Savepoint 2026070900095. // ADD-AILOGIN (v2.3.92): Added local_ailogin (AI Login Designer) to master plugin registry (Settings + Manage dropdowns), complete plugin grid, and docs URL map. No DB schema changes. Savepoint 2026070800092. // FIX-QUIZACCESS-DOCS-URL (v2.3.91): quizaccess_aigrader docs link in get_plugin_docs_url() was pointing to /docs/ai-grader (the main AI Essay Grader page) instead of /docs/quiz-access-rule. No DB changes. Savepoint 2026070300091. // FIX-GET-CREDITS-GLOBALS (v2.3.90): classes/external/get_credits.php was missing global $DB, $USER declarations — execute() only declared global $CFG. When a non-siteadmin (e.g. lmshsadmin) called the web service, $DB->record_exists_sql() on a null variable threw a PHP exception, causing the AJAX call to fail silently and the credits badge to stay blank. Fix: added global $DB, $USER to execute(). Also expanded role-check to cover moodle/site:configview capability (catches custom admin roles) and added lmshostingadmin and manager shortnames to the hardcoded list. No DB schema changes. Savepoint 2026070300090. // SHOW-CREDITS-LMSHSADMIN (v2.3.89): Credit balance badge now also visible to lmshsadmin role (LMS Hosting Admin). Both block_aiplugin_nav.php (badge placeholder) and classes/external/get_credits.php (web service role check) updated. No DB changes. Savepoint 2026063000089. // SHOW-CREDITS-TEACHERS (v2.3.88): Credit balance badge now visible to editing teachers and non-editing teachers, not just site admins. Both block_aiplugin_nav.php (badge placeholder + AMD loader) and classes/external/get_credits.php (web service gate) updated. No DB changes. Savepoint 2026063000088. // ADD-UPDATE-POPUP (v2.3.83): Added full-screen plugin update popup to QuickLinks block. Clicking "Check for Updates" now shows a modal popup matching the lms-labs.com admin panel: orange gradient header listing each outdated plugin with installed vs latest version (old→new), or green gradient "All Plugins Up to Date" when everything is current. Popup has X close button, backdrop-click dismiss, "Check for Updates" re-run button, and "Got It"/"Perfect, Thanks!" confirm button. No DB changes. Savepoint 2026062600084. // FIX-PHP-PARSE (v2.3.82): Escaped three unescaped double-quote characters inside the js_amd_inline PHP double-quoted string that caused a PHP ParseError ("unexpected identifier up") on Moodle upgrade. Affected lines were JS comments containing "up to date" — bare " closed the PHP string prematurely. No DB changes. Savepoint 2026062200082. // FIX-UPTODATE-TOAST (v2.3.81): Added in-block "All plugins are up to date" toast. Toast renders inside .ainav-container on the Moodle dashboard so the message appears within the block — not on lms-labs.com. Accent colour uses var(--primary) inherited from the Moodle theme primary colour rather than a hardcoded value. Auto-dismisses after 5 s; manual close button included. No DB changes. Savepoint 2026062000081. // RENAME-AI-SLIDE-FLOW (v2.3.80): Renamed mod_productexplainer from 'AI Product Explainer' to 'AI Slide Flow' in master plugin registry, complete plugin grid, quick access links, and lang strings. No DB changes. Savepoint 2026061300080. // FIX-WORKSHOPS-CALENDAR-ICON (v2.3.79): Added missing 'calendar' key to get_icon_svg() lookup table. Workshop Scheduler quick-link rows were showing a blank icon because the registry uses icon='calendar' but only 'calendar-icon' existed in the SVG map. No DB schema changes. Savepoint 2026061200079. // REMOVE-DUPE-CREDITS (v2.3.78): Removed standalone credits badge from header bar — credit count now shown only inside the Buy Credits button. Fixed ainav-bar/ainav-sections/ainav-external flex-wrap: nowrap so the header always renders on a single line. Tightened gap (16px→12px) and padding (12px 20px→10px 16px). No DB changes. Savepoint 2026061200078. // ADD-WORKSHOPS-PRODUCTEXPLAINER (v2.3.76): Added Workshop Scheduler (local_workshops) and AI Product Explainer (mod_productexplainer) to master plugin registry, complete plugin grid, quick access links, docs URL table, and lang strings. No DB changes. // RENAME-CV-SCORM (v2.3.75): Updated plugin display name for local_chirpvoice from 'AI Voiceover (Chirp HD)' to 'AI SCORM Voiceover' in both registry entries (master plugin registry and complete plugin grid). No DB changes. // FIX-CV-DOCS-LINK (v2.3.74): // FIX-CV-DOCS-LINK (v2.3.74): Added local_chirpvoice to get_plugin_docs_url() lookup table. AI Voiceover (Chirp HD) card in the Plugin Manager was missing the Docs button because local_chirpvoice was not in the docs_urls array. URL: https://lms-labs.com/docs/ai-voiceover. No DB changes. // FIX-CV-COMPLETE-REGISTRY (v2.3.73): Added local_chirpvoice (AI Voiceover Chirp HD) to get_complete_plugin_registry() so it appears in the AI Plugins grid regardless of installation status. Was previously only in get_master_plugin_registry() (settings/nav links) but missing from the plugin management grid. No DB changes. // ADD-CV-QUICKLINKS (v2.3.72): Added local_chirpvoice (AI Voiceover Chirp HD) to master plugin registry. Appears in AI Plugins grid and Settings dropdown when installed. No DB changes; // SAVEPOINT-BUMP v2.3.71: no-op savepoint marker for clean upgrade path. No DB schema changes.; // FIX-MAP-ICON (v2.3.70): Added missing 'map' SVG to get_icon_svg() icon table. Training Pathways quick-link row was showing a blank icon space because 'map' was not in the lookup array (only 'map-pin' was). No DB schema changes. Savepoint 2026060200070.
// ADD-TRAININGPATHWAYS (v2.3.69): Added Training Pathways (local_trainingpathways) to the plugin registry (settings cog → /admin/settings.php?section=local_trainingpathways, page → /local/trainingpathways/manage.php, icon=map), the Time Saving Plugins marketplace grid (category=utility, goto_url=/local/trainingpathways/manage.php), and the docs URL map. No DB schema changes. Savepoint 2026060200069.
// ADD-DOWNALERT (v2.3.67): Added local_downalert (Site Down Alert) to the plugin registry under Time Saving Plugins (category: admin) with settings_url and report_url pointing to the new report.php health dashboard. Report now appears in the Reports dropdown; settings appears in the Settings dropdown. Free — no AI credits required. No DB schema changes. Savepoint 2026052900067. (v2.3.66): Removed settings_url from paygw_paddle registry entry. Paddle has no single admin settings page — API keys are at Site admin > Plugins > Payment gateways > Manage payment gateways; payment prices are per-course. The broken link to /admin/settings.php?section=paymentgatewaypaddle no longer appears in the quicklinks Settings dropdown. No DB changes. Savepoint 2026052900066.
// RTOC-CERT-SETTINGS-QUICKLINK (v2.3.65): Added "RTO Compliance — Certificate Settings" to the Settings dropdown in the AI Quick Links block, pointing to /admin/settings.php?section=local_rtocompliance_certs. No DB changes. Savepoint 2026052900065.
// REGISTRY-AUDIT (v2.3.64): Added missing block_rtocompliance (RTO Compliance Dashboard block) to plugin registry — was in server PLUGIN_ZIP_CONFIG but absent from Quick Links registry, so it never appeared in the dashboard and never triggered update notifications. No DB changes. Savepoint 2026052800064.
// FIX-RTOCOMPLIANCE-SETTINGS-URL (v2.3.63): RTO Compliance settings icon in Quick Links was linking to section=local_rtocompliance_dashboard (an admin category, not a page) causing a "section not found" error. Fixed to section=local_rtocompliance_settings which is the correct admin_settingpage. No DB changes. Savepoint 2026052800063.
// FIX-CURL-NOT-FOUND: plugin_unlock.php and get_credits.php both call new \curl() but never loaded filelib.php where Moodle's curl class is defined. This caused "Credit unlock failed: Exception - Class curl not found" when clicking the unlock button for any credit-gated plugin (e.g. Simple 2FA). Fix: added require_once($CFG->libdir.'/filelib.php') immediately before new \curl() in both plugin_unlock.php and get_credits.php. Also includes v2.3.61 credits-display fix (removed capabilities=>moodle/site:config from services.php + soft is_siteadmin() check in execute()). No DB schema changes. Savepoint 2026052500062.
