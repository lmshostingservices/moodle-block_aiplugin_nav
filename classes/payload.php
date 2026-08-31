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
 * Data payload assembler for the new (v2.5.0) AI Plugin Navigation UI.
 *
 * Builds the single JSON payload described in CONTRACT.md, section "The payload".
 * This class does NOT render any HTML. It only assembles and returns a PHP array
 * ready to be json_encode()-d into the `<script type="application/json"
 * id="ainav2-data">` tag by the block's get_content().
 *
 * Data is sourced from the existing block's registries (get_complete_plugin_registry(),
 * get_master_plugin_registry(), get_links_registry(), get_site_links_registry(), etc).
 * Those registries remain the single source of truth for plugin metadata; this class
 * only re-shapes and re-categorises that data for the new UI contract. Because most of
 * those helpers are `private` on block_aiplugin_nav, and CONTRACT.md/task instructions
 * forbid editing that file, the minimum logic needed from each private helper has been
 * ported (duplicated) below rather than accessed via reflection. Where block_aiplugin_nav
 * exposes an equivalent method as public/protected, this class uses it directly instead.
 *
 * @package    block_aiplugin_nav
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class block_aiplugin_nav_payload {
    // =====================================================================================
    // CATEGORY MAPPING — 21 legacy category values (from get_complete_plugin_registry() /
    // get_master_plugin_registry()) mapped onto the 7 new contract categories
    // (assess, content, media, rto, access, training, site).
    //
    // Basis for each mapping: cross-referenced against the *approved* mockup's own
    // per-plugin category tags (the GROUPS/SETTINGS/MANAGE/REPORTS arrays in
    // /root/mockup/quicklinks.html), which is the ground truth for "what category did the
    // designer actually put this plugin in". Where every live plugin carrying a given
    // legacy category landed in the same new category in the mockup, that is the mapping
    // below. Where the mockup's own per-plugin placement disagrees with the category-level
    // majority (a handful of plugins), a component-level override is used instead — see
    // CATEGORY_OVERRIDES. Legacy values with no live registry example (ai, ai_credit, admin)
    // are given a defensible default and flagged in payload_notes.md.
    // =====================================================================================
    const CATEGORY_MAP = [
        // AI plugin categories.
        'ai'            => 'assess',   // No live entries (commented-out testing stubs only);
                                        // those stubs were themselves mostly quiz/mod grading
                                        // plugins, so 'assess' is the closest default.
        'ai_grading'    => 'assess',   // AI Essay Grader, AI Quiz Maker, AI Knowledge Check,
                                        // AI Mapping, Assignment Benchmarks, AI Quiz Remedial
                                        // Learning — all land in mockup's "assess" group.
        'ai_content'    => 'content',  // AI Content Creator, AI Learning Activities, Slides,
                                        // AI Course Information, AI Slide Flow, AI Smart
                                        // Workbook, AI Course Format — all in "content".
        'ai_media'      => 'media',    // AI Video Activity, AI Slideshow, AI SCORM Voiceover.
        'ai_rto'        => 'rto',      // AI RTO Compliance, RTO Compliance Dashboard, RPL Kit.
        'ai_ux'         => 'site',     // AI Verify ID, AI Support, AI Login Designer — all
                                        // three sit in the mockup's Plugins "site" group.
                                        // NOTE: the mockup's *Settings* row for AI Verify ID
                                        // tags it 'access' instead — a genuine inconsistency
                                        // in the mockup itself. We follow the Plugins-panel
                                        // (GROUPS) placement as primary. See payload_notes.md.
        'ai_credit'     => 'assess',   // No live entries (legacy generic "credit gated AI"
                                        // catch-all used only by commented-out stubs, which
                                        // spanned assess/content/media/rto). Defaulted to
                                        // 'assess' as the single most common case.
        // Non-AI / general categories.
        'block'         => 'site',     // AI Dashboard Quick Links, My Progress -> "site" group.
        'enrolment'     => 'access',   // Groups Management, Course Prerequisite, Group
                                        // Membership Limit, Payment Unlock Assignment, Quiz
                                        // Access Rule, Groups Availability Condition.
        'training'      => 'training', // Training Pathways, Training Plan, Workshop
                                        // Scheduler, Attendance -> "training" group.
        'branding'      => 'site',     // Change Site Font, Cohort Branding, LMS Home Page.
        'comms'         => 'site',     // Site Down Alert, Student Email Manager/Auth.
        'media_storage' => 'media',    // Video Compress, SCORM Compress, Media Optimiser.
        'security'      => 'site',     // Course Version Control, Simple 2FA & SSO.
        'integrity'     => 'assess',   // Essay Guard, DocGuard -> mockup's "assess" group.
        'utility'       => 'site',     // No live entries (commented-out Speed/BigBlueButton
                                        // stubs). Defaulted to 'site' as the general bucket.
        'reporting'     => 'site',     // Beacon — Reports & Analytics.
        'payments'      => 'site',     // Paddle Payment Gateway.
        'config'        => 'site',     // AI Grader Central Config.
        'admin'         => 'site',     // No live entries (commented-out AI Training Matrix
                                        // stub only). Defaulted to 'site'.
        'other'         => 'site',     // Catch-all for the ~14 stub/placeholder components
                                        // added late to get_complete_plugin_registry() with
                                        // generic descriptions. Individually confirmed
                                        // exceptions are listed in CATEGORY_OVERRIDES.
    ];

    /**
     * Component-level category overrides. Applied AFTER CATEGORY_MAP, for the handful of
     * plugins whose approved-mockup placement disagrees with the category-level default
     * above. Keyed by component (the key used in get_master_plugin_registry(), and the
     * 'component' field in get_complete_plugin_registry()).
     */
    const CATEGORY_OVERRIDES = [
        // Legacy category 'training', but mockup's Content & Courses group.
        'local_activitynav'             => 'content',
        // Legacy category 'training', but mockup's Enrolment & Access group.
        'local_courseavailabilitydelay' => 'access',
        // Legacy category 'other', but mockup's RTO & Compliance group.
        'mod_certificatepro'            => 'rto',
        'block_studentactivity'         => 'rto',
        // Legacy category 'other', but mockup's Training & Scheduling group.
        'block_trainingpathways'        => 'training',
        'availability_workshopattendance' => 'training',
        // Legacy category 'other', but mockup's Enrolment & Access group.
        'enrol_prereq2'                 => 'access',
    ];

    // =====================================================================================
    // PLUGIN TYPE MAPPING — Moodle plugin_type values used in the registries, mapped onto
    // the contract's `ptypes` keys (mod, block, quizaccess, assignfeedback, availability,
    // enrol, format, local, theme, auth, paygw, plagiarism). Direct 1:1 matches pass through
    // unchanged; the remainder are cross-referenced against the mockup's GROUPS array, which
    // tags each plugin row with the ptype used for the coloured type pill.
    // =====================================================================================
    const PTYPE_MAP = [
        'mod'          => 'mod',
        'block'        => 'block',
        'quizaccess'   => 'quizaccess',
        'availability' => 'availability',
        'enrol'        => 'enrol',
        'format'       => 'format',
        'local'        => 'local',
        'auth'         => 'auth',
        'paygw'        => 'paygw',
        'plagiarism'   => 'plagiarism',
        'theme'        => 'theme',
        // quiz_aigrader (plugin_type 'quiz' in this registry — not a real Moodle plugin
        // type) is tagged 'quizaccess' in the mockup's GROUPS array for AI Essay Grader.
        'quiz'         => 'quizaccess',
        // gradingform_benchmarks is tagged 'assignfeedback' in the mockup's GROUPS array
        // for Assignment Benchmarks.
        'gradingform'  => 'assignfeedback',
        // No live examples in the active registry (commented-out stubs only). No ptypes
        // key fits; defaulted to 'local'.
        'report'       => 'local',
        'qbehaviour'   => 'local',
    ];

    /**
     * Component-level ptype overrides. The mockup deliberately re-labels three
     * appearance-affecting local plugins as 'theme' for the type pill, even though their
     * real Moodle plugin_type is 'local'.
     */
    const PTYPE_OVERRIDES = [
        'local_sitefont'      => 'theme',
        'local_cohortbranding' => 'theme',
        'local_ailogin'        => 'theme',
    ];

    /** Base URL for docs pages (verbatim from the mockup). */
    const DOCS_BASE = 'https://lms-labs.com/docs/';

    /**
     * Verified docs slugs, keyed by the row's display name — ported verbatim from
     * DOCS_OVERRIDE in /root/mockup/quicklinks.html. A row whose name has no entry here
     * emits docs:null (the UI greys the pill) rather than guessing a slug.
     */
    const DOCS_OVERRIDE = [
        'AI Grader Central Config' => 'ai-central-config',
        'AI Essay Grader' => 'ai-grader',
        'AI Quiz Maker' => 'ai-quiz-maker',
        'AI Content Creator' => 'ai-content-creator',
        'AI Knowledge Check' => 'ai-knowledge-check',
        'AI Video Activity' => 'ai-video-activity',
        'AI Learning Activities' => 'ai-learning-activities',
        'AI Webcam Proctoring' => 'webcam-proctoring',
        'AI Practical Assessment' => 'ai-practical-assessment',
        'AI Smart Workbook' => 'smart-workbook',
        'AI RTO Compliance' => 'rto-compliance',
        'AI Verify ID' => 'ai-verify-id',
        'AI Support' => 'ai-support',
        'AI SCORM Voiceover' => 'ai-voiceover',
        'AI Video Conference' => 'ai-video-conference',
        'AI Course Format' => 'ai-course-format',
        'AI Training Matrix HCM' => 'training-matrix',
        'AI Slideshow with Voiceover' => 'slideshow',
        'AI Quiz Remedial Learning' => 'ai-quiz-remedial',
        'AI PDF Assignment Grader' => 'ai-pdf-grader',
        'AI Mapping' => 'learning-mapping',
        'AI Course Information' => 'course-information',
        'AI Slide Flow' => 'product-explainer',
        'RPL Kit' => 'rpl-kit',
        'RTO Compliance Dashboard' => 'rto-compliance',
        'RTO Compliance — Certificates' => 'rto-compliance',
        'RTO Compliance Settings' => 'rto-compliance',
        'RTO Compliance dashboard' => 'rto-compliance',
        'AI Dashboard Quick Links' => 'ai-dashboard-quick-links',
        'AI Login Designer' => 'ai-login-designer',
        'Beacon — Reports & Analytics' => 'beacon',
        'Certificate Pro' => 'rto-compliance',
        'Workplace Task' => 'workplace-task',
        'AI Training Simulation' => 'ai-training-simulation',
        'Slides' => 'slides',
        'My Progress' => 'my-progress',
        'My Students Progress' => 'my-students-progress',
        'Student Activity & Participation' => 'student-activity',
        'Student Activity & Participation Evidence' => 'student-activity',
        'Training Pathways Block' => 'training-pathways',
        'Course Prerequisite' => 'course-prerequisite',
        'Activity Navigation' => 'activity-navigation',
        'Course Version Control' => 'courseversion',
        'Course Availability Delay' => 'course-availability-delay',
        'Course Recertification' => 'course-recertification',
        'Training Pathways' => 'training-pathways',
        'Training Plan' => 'training-plan',
        'Workshop Scheduler' => 'workshop-scheduler',
        'Quiz Access Rule' => 'quiz-access-rule',
        'Simple 2FA & SSO' => 'simple-2fa',
        'Essay Guard' => 'essay-guard',
        'DocGuard' => 'doc-guard',
        'Assignment Benchmarks' => 'benchmarks',
        'Completion Auto-Suspend' => 'completion-auto-suspend',
        'Groups Management' => 'groups-management',
        'Group Membership Limit' => 'group-membership-limit',
        'Student Email Manager' => 'student-email-manager',
        'Student Email IMAP Auth' => 'student-email-imap-auth',
        'Paddle Payment Gateway' => 'paddle-payment',
        'Payment Unlock Assignment' => 'payment-unlock-assignment',
        'Change Site Font' => 'change-site-font',
        'Cohort Branding' => 'cohort-branding',
        'Custom Pages' => 'custom-pages',
        'Site Down Alert' => 'site-down-alert',
        'Video Compress' => 'video-compress',
        'Media Optimiser' => 'media-optimiser',
        'SCORM Compress' => 'scorm-compress',
        'LMS Home Page' => 'lms-home-page',
        'Campion Education Integration' => 'campion-integration',
        'Attendance summary' => 'ai-dashboard-quick-links',
        'Plugin version audit' => 'ai-dashboard-quick-links',
        'Credit usage' => 'ai-central-config',
        'Beacon analytics' => 'beacon',
        'Site Down Alert health' => 'site-down-alert',
        'Course prerequisite gates' => 'course-prerequisite',
        'AI Course Format Q&A' => 'ai-course-format',
        'Essay Guard originality' => 'essay-guard',
        'Assignment benchmarks' => 'benchmarks',
        'My students progress' => 'my-students-progress',
        'Training matrix' => 'training-matrix',
        'Student activity & participation' => 'student-activity',
    ];

    /**
     * Entry point. Assembles and returns the full payload array for block_aiplugin_nav.
     *
     * @param block_aiplugin_nav $block The block instance (used for its public/protected
     *                                   helpers and content()-time context; private
     *                                   registries are re-declared as static methods
     *                                   below rather than reflected into).
     * @return array The payload, ready for json_encode().
     */
    public static function build(block_aiplugin_nav $block): array {
        global $CFG, $USER;

        $isadmin = has_capability('moodle/site:config', context_system::instance());
        $cancredits = $isadmin
            || $block->user_has_role_shortname($USER->id, 'editingteacher')
            || $block->user_has_role_shortname($USER->id, 'teacher')
            || $block->user_has_role_shortname($USER->id, 'lmshsadmin');

        $completeregistry = $block->get_complete_plugin_registry();
        $masterregistry    = $block->get_master_plugin_registry();

        // Admin-only surfaces. A non-admin never receives this data: the previous UI gated
        // the Settings, Manage and Plugin Manager dropdowns behind moodle/site:config, and
        // omitting the data entirely is stronger than hiding it in the browser.
        if ($isadmin) {
            $plugins = self::build_plugins($block, $completeregistry, $masterregistry);
            $smr     = self::build_settings_manage_reports($block, $masterregistry, $completeregistry);
        } else {
            $plugins = [];
            $smr = ['settings' => [], 'manage' => [], 'reports' => []];
        }

        $installedcount = 0;
        $updatecount = 0;
        foreach ($plugins as $p) {
            if (!empty($p['installed'])) {
                $installedcount++;
            }
            if (!empty($p['update'])) {
                $updatecount++;
            }
        }

        return [
            'wwwroot'    => $CFG->wwwroot,
            'sesskey'    => sesskey(),
            'isadmin'    => $isadmin,
            'cancredits' => $cancredits,
            'categories' => self::get_categories(),
            'catorder'   => self::get_catorder(),
            'ptypes'     => self::get_ptypes(),
            'plugins'    => $plugins,
            'settings'   => $smr['settings'],
            'manage'     => $smr['manage'],
            'reports'    => $smr['reports'],
            'core'       => self::build_core_links($isadmin),
            'custom'     => self::build_custom_links($block),
            'customreports' => self::build_custom_reports($block),
            'products'   => self::build_products(),
            'help'       => self::build_help(),
            'proxyurl'   => $CFG->wwwroot . '/blocks/aiplugin_nav/check_versions.php',
            'supporturl' => self::build_support_url($block),
            'prefs'      => [
                'faves'  => json_decode(get_user_preferences('block_aiplugin_nav_faves', '[]'), true) ?: [],
                'layout' => json_decode(get_user_preferences('block_aiplugin_nav_layout', '{}'), true) ?: new stdClass(),
                'help'   => get_user_preferences('block_aiplugin_nav_help', '1'),
                'spend'  => json_decode(get_user_preferences('block_aiplugin_nav_spend', '[]'), true) ?: [],
            ],
            'counts'     => [
                'installed' => $installedcount,
                'updates'   => $updatecount,
            ],
        ];
    }

    // =====================================================================================
    // Fixed contract vocabularies (categories / catorder / ptypes). Labels are user-facing
    // UI chrome, so they are routed through get_string(). See TODO_LANG list in
    // payload_notes.md for the exact keys/fallback English needed in lang/en/block_aiplugin_nav.php.
    // =====================================================================================

    private static function get_categories(): array {
        return [
            'assess'   => get_string('ainav2_cat_assess', 'block_aiplugin_nav'),
            'content'  => get_string('ainav2_cat_content', 'block_aiplugin_nav'),
            'media'    => get_string('ainav2_cat_media', 'block_aiplugin_nav'),
            'rto'      => get_string('ainav2_cat_rto', 'block_aiplugin_nav'),
            'access'   => get_string('ainav2_cat_access', 'block_aiplugin_nav'),
            'training' => get_string('ainav2_cat_training', 'block_aiplugin_nav'),
            'site'     => get_string('ainav2_cat_site', 'block_aiplugin_nav'),
        ];
    }

    private static function get_catorder(): array {
        return ['assess', 'content', 'media', 'rto', 'access', 'training', 'site'];
    }

    private static function get_ptypes(): array {
        return [
            'mod'            => get_string('ainav2_ptype_mod', 'block_aiplugin_nav'),
            'block'          => get_string('ainav2_ptype_block', 'block_aiplugin_nav'),
            'quizaccess'     => get_string('ainav2_ptype_quizaccess', 'block_aiplugin_nav'),
            'assignfeedback' => get_string('ainav2_ptype_assignfeedback', 'block_aiplugin_nav'),
            'availability'   => get_string('ainav2_ptype_availability', 'block_aiplugin_nav'),
            'enrol'          => get_string('ainav2_ptype_enrol', 'block_aiplugin_nav'),
            'format'         => get_string('ainav2_ptype_format', 'block_aiplugin_nav'),
            'local'          => get_string('ainav2_ptype_local', 'block_aiplugin_nav'),
            'theme'          => get_string('ainav2_ptype_theme', 'block_aiplugin_nav'),
            'auth'           => get_string('ainav2_ptype_auth', 'block_aiplugin_nav'),
            'paygw'          => get_string('ainav2_ptype_paygw', 'block_aiplugin_nav'),
            'plagiarism'     => get_string('ainav2_ptype_plagiarism', 'block_aiplugin_nav'),
        ];
    }

    // =====================================================================================
    // Category / ptype / docs resolution helpers.
    // =====================================================================================

    private static function resolve_category(string $component, string $legacycategory): string {
        if (isset(self::CATEGORY_OVERRIDES[$component])) {
            return self::CATEGORY_OVERRIDES[$component];
        }
        return self::CATEGORY_MAP[$legacycategory] ?? 'site';
    }

    private static function resolve_ptype(string $component, string $plugintype): string {
        if (isset(self::PTYPE_OVERRIDES[$component])) {
            return self::PTYPE_OVERRIDES[$component];
        }
        return self::PTYPE_MAP[$plugintype] ?? 'local';
    }

    /**
     * Docs URL for a row by its display name. Returns null when there is no verified
     * slug for that exact name (the UI greys the pill in that case).
     */
    private static function docs_url_for(string $name): ?string {
        if (isset(self::DOCS_OVERRIDE[$name])) {
            return self::DOCS_BASE . self::DOCS_OVERRIDE[$name];
        }
        return null;
    }

    // =====================================================================================
    // "plugins" array — built from get_complete_plugin_registry(), enriched with live
    // install/version/credit data from get_master_plugin_registry() + is_plugin_installed()
    // + get_plugin_version().
    // =====================================================================================

    private static function build_plugins(block_aiplugin_nav $block, array $completeregistry, array $masterregistry): array {
        $out = [];

        // Best-effort re-use of the block's own 5-minute plugin-status cache (built by
        // block_aiplugin_nav::render_plugin_management_section()) so "update available"
        // reflects the same data the old UI showed, without re-implementing the remote
        // version-check HTTP call here. If the cache is cold/absent we fall back to
        // is_plugin_installed()/get_plugin_version() only, with update always false — see
        // payload_notes.md ("update detection").
        $statusmap = null;
        $cachetime = (int) get_config('block_aiplugin_nav', 'plugin_status_cache_time');
        if ($cachetime && (time() - $cachetime) < 300) {
            $cachedjson = get_config('block_aiplugin_nav', 'plugin_status_cache_data');
            if ($cachedjson) {
                $statusmap = json_decode($cachedjson, true);
            }
        }

        foreach ($completeregistry as $plugin) {
            $component   = $plugin['component'];
            $plugintype  = $plugin['plugin_type'];
            $pluginname  = $plugin['plugin_name'];
            $masterentry = $masterregistry[$component] ?? null;

            if ($statusmap !== null && isset($statusmap[$component])) {
                $installed = !empty($statusmap[$component]['is_installed']);
                $version   = $statusmap[$component]['installed_version'] ?? null;
                $update    = !empty($statusmap[$component]['update_available']);
            } else {
                $installed = $block->is_plugin_installed($plugintype, $pluginname);
                $version   = $installed ? $block->get_plugin_version($plugintype, $pluginname) : null;
                $update    = false;
            }

            // Testing-stage plugins render disabled/orange (contract behaviour #10).
            // No explicit "status" field exists on the registries; the only live entry
            // documented as testing-stage is Certificate Pro (see its description text) —
            // detected here rather than adding a new registry field, per instructions not
            // to touch block_aiplugin_nav.php. See payload_notes.md ("status" heuristic).
            $status = self::is_testing_stage($plugin) ? 'testing' : 'ready';

            // Client-specific builds are treated the same way as testing builds: never on
            // offer, kept only where the site already runs one.
            if ($status === 'ready' && in_array($component, self::PRIVATE_COMPONENTS, true)) {
                $status = 'private';
            }

            // A plugin still in testing must never be offered for install from the block.
            // It is dropped from the catalogue entirely unless this site already runs it —
            // an installed plugin is a fact of the site and stays visible so it can still be
            // configured and updated. check_versions.php is the authoritative source for
            // status, and the browser applies the same rule again on the live data (see
            // refreshUpdates() in amd/src/ui.js) to catch anything the registry has not
            // been told about.
            if (($status === 'testing' || $status === 'private') && !$installed) {
                continue;
            }

            $action = self::resolve_action($plugintype, $pluginname, $plugin, $masterentry);

            $out[] = [
                'name'      => $plugin['name'],
                'component' => $component,
                'cat'       => self::resolve_category($component, $plugin['category']),
                'ptype'     => self::resolve_ptype($component, $plugintype),
                'desc'      => $plugin['description'],
                'docs'      => self::docs_url_for($plugin['name']),
                'installed' => $installed,
                'version'   => $version,
                // Installed numeric version (Moodle's YYYYMMDDXX). The browser compares this
                // against the latest numericVersion from check_versions.php to work out
                // whether an update exists — see refreshUpdates() in amd/src/ui.js.
                'versionint' => $installed ? $block->get_plugin_numeric_version($plugintype, $pluginname) : null,
                'update'    => $update,
                'credits'   => $masterentry['credits_required'] ?? ($plugin['credits_required'] ?? 0),
                'status'    => $status,
                'gotourl'   => $action['url'],
                'action'    => $action['label'],
                'pluginid'  => $pluginname,
            ];
        }

        return $out;
    }

    /**
     * Work out what the row's action button should say and where it should go.
     *
     * Not every installed plugin has somewhere to "open". An activity module is used
     * inside a course — there is no site-level page for it — and the same is true of
     * blocks, quiz access rules, assignment feedback plugins, availability conditions,
     * course formats, enrolment methods, authentication plugins, payment gateways,
     * plagiarism plugins and themes. For all of those the only meaningful destination
     * is the plugin's own settings page, so the button says "Settings" rather than
     * offering an "Open" that leads nowhere.
     *
     * Plugins that genuinely do have a standalone admin page (most local_* and report_*
     * plugins) keep "Open" and go to that page.
     *
     * @param string $plugintype Moodle plugin type, e.g. 'mod', 'local'.
     * @param string $pluginname Plugin name without its type prefix.
     * @param array $plugin Entry from get_complete_plugin_registry().
     * @param array|null $masterentry Matching entry from get_master_plugin_registry(), if any.
     * @return array{url: string, label: string}
     */
    private static function resolve_action(string $plugintype, string $pluginname,
            array $plugin, ?array $masterentry): array {

        // Plugin types with no standalone page — they are used from inside a course,
        // an activity or another plugin's settings, never opened on their own.
        $settingsonly = ['mod', 'block', 'quizaccess', 'assignfeedback', 'assignsubmission',
            'availability', 'format', 'enrol', 'auth', 'paygw', 'plagiarism', 'theme',
            'filter', 'qtype', 'qbehaviour', 'atto', 'tiny', 'editor'];

        $settingsurl = $masterentry['settings_url'] ?? null;
        $pageurl     = $masterentry['page_url'] ?? null;
        $gotourl     = $plugin['goto_url'] ?? null;

        if (in_array($plugintype, $settingsonly, true)) {
            $url = $settingsurl ?: self::settings_section_url($plugintype, $pluginname);
            return ['url' => $url, 'label' => 'settings'];
        }

        // A goto_url that is itself a settings section is a settings destination,
        // whatever the plugin type.
        $candidate = $pageurl ?: ($gotourl ?: $settingsurl);
        if (!$candidate) {
            return ['url' => self::settings_section_url($plugintype, $pluginname), 'label' => 'settings'];
        }
        if (strpos($candidate, '/admin/settings.php') !== false) {
            return ['url' => $candidate, 'label' => 'settings'];
        }
        return ['url' => $candidate, 'label' => 'open'];
    }

    /**
     * The admin settings section URL for a plugin, following Moodle's own naming.
     *
     * @param string $plugintype Moodle plugin type.
     * @param string $pluginname Plugin name without its type prefix.
     * @return string
     */
    private static function settings_section_url(string $plugintype, string $pluginname): string {
        global $CFG;

        // Moodle names these sections per plugin type; the rest use the full component.
        $prefixes = [
            'mod'    => 'modsetting',
            'block'  => 'blocksetting',
            'enrol'  => 'enrolsettings',
            'auth'   => 'authsetting',
            'format' => 'formatsetting',
            'filter' => 'filtersetting',
            'theme'  => 'themesetting',
        ];

        if (isset($prefixes[$plugintype])) {
            $section = $prefixes[$plugintype] . $pluginname;
        } else {
            $section = $plugintype . '_' . $pluginname;
        }

        return $CFG->wwwroot . '/admin/settings.php?section=' . $section;
    }

    // =====================================================================================
    // "settings" / "manage" / "reports" arrays — built from get_master_plugin_registry(),
    // filtered to installed plugins only (identical filtering logic to the existing
    // get_links_registry()).
    // =====================================================================================

    private static function build_settings_manage_reports(block_aiplugin_nav $block, array $masterregistry, array $completeregistry): array {
        global $CFG;

        // component => description, for rows whose name/component matches a
        // get_complete_plugin_registry() entry. Rows with no match (e.g. duplicate label
        // rows like "Training Plan — Notifications") fall back to an empty description —
        // see payload_notes.md ("desc" for settings/manage/reports).
        $descbycomponent = [];
        foreach ($completeregistry as $p) {
            $descbycomponent[$p['component']] = $p['description'];
        }

        $settings = [];
        $manage   = [];
        $reports  = [];

        foreach ($masterregistry as $component => $plugin) {
            if (!$block->is_plugin_installed($plugin['plugin_type'], $plugin['plugin_name'])) {
                continue;
            }

            $cat  = self::resolve_category($component, $plugin['category']);
            $ptype = self::resolve_ptype($component, $plugin['plugin_type']);
            $desc = $descbycomponent[$component] ?? '';
            $docs = self::docs_url_for($plugin['name']);

            if (!empty($plugin['settings_url'])) {
                $settings[] = [
                    'name'       => $plugin['name'],
                    'cat'        => $cat,
                    'ptype'      => $ptype,
                    'desc'       => $desc,
                    'docs'       => $docs,
                    'url'        => $CFG->wwwroot . $plugin['settings_url'],
                    // No per-plugin "is this configured" signal exists in the registries
                    // (would require inspecting each plugin's own config table/settings).
                    // Left false; see payload_notes.md ("configured").
                    'configured' => false,
                ];
            }

            if (!empty($plugin['page_url'])) {
                $manage[] = [
                    'name'  => $plugin['name'],
                    'cat'   => $cat,
                    'ptype' => $ptype,
                    'desc'  => $desc,
                    'docs'  => $docs,
                    'url'   => $CFG->wwwroot . $plugin['page_url'],
                ];
            }

            if (!empty($plugin['report_url'])) {
                $reports[] = [
                    'name'  => $plugin['name'],
                    'cat'   => $cat,
                    'ptype' => $ptype,
                    'desc'  => $desc,
                    'docs'  => $docs,
                    'url'   => $CFG->wwwroot . $plugin['report_url'],
                    // No per-report "is this a live/real-time report" signal exists in the
                    // registries. Left false; see payload_notes.md ("live").
                    'live'  => false,
                ];
            }
        }

        usort($settings, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        usort($manage,   fn($a, $b) => strcasecmp($a['name'], $b['name']));
        usort($reports,  fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return ['settings' => $settings, 'manage' => $manage, 'reports' => $reports];
    }

    /**
     * Components that are never offered for install from the block.
     *
     * These are client-commissioned or site-specific builds that happen to sit in the shared
     * registry. They are not products, so listing them in the catalogue offers other
     * customers something they cannot have. As with testing builds they are dropped unless
     * the site already runs one, in which case the row stays so it can still be managed.
     */
    const PRIVATE_COMPONENTS = [
        'qbehaviour_wilkinsoncoutts',
    ];

    /**
     * Is this registry entry a testing-stage plugin?
     *
     * The registries carry no dedicated status field, so a testing build is identified by
     * an explicit status key where one exists, and otherwise by the "Testing-stage" marker
     * the registry descriptions use. Matched case-insensitively so a capitalisation slip in
     * a future entry cannot quietly put a testing plugin back on offer.
     *
     * @param array $plugin Entry from get_complete_plugin_registry().
     * @return bool
     */
    private static function is_testing_stage(array $plugin): bool {
        if (!empty($plugin['status']) && strtolower((string) $plugin['status']) !== 'ready') {
            return true;
        }
        return stripos((string) ($plugin['description'] ?? ''), 'testing-stage') !== false;
    }

    /**
     * Destination for the "Ask AI Support" card.
     *
     * When local_moodlesupport is installed the card goes to the on-site support
     * console, exactly as the old block's support button did. When it is not
     * installed the card falls back to the published documentation so it is never
     * a dead link.
     *
     * @param block_aiplugin_nav $block The block instance.
     * @return string
     */
    private static function build_support_url(block_aiplugin_nav $block): string {
        global $CFG;

        if ($block->is_plugin_installed('local', 'moodlesupport')) {
            return $CFG->wwwroot . '/local/moodlesupport/index.php';
        }
        return 'https://lms-labs.com/docs/ai-support';
    }

    // =====================================================================================
    // "core" array — Moodle shortcuts, ported from get_site_links_registry().
    //
    // Two groups, in the order the old block showed them:
    //   'user'  — the personal destinations every logged-in user has (Dashboard, My
    //             courses, Profile, Grades, Calendar, Messages, Badges, Private files,
    //             Preferences). These are NOT admin-gated: a student sees this row.
    //   'admin' — site administration destinations, appended for admins only.
    //
    // The personal links carry no capability requirement in core Moodle — every
    // authenticated user can reach them — so they are gated on being logged in rather
    // than on moodle/site:config. Guests get nothing.
    // =====================================================================================

    private static function build_core_links(bool $isadmin): array {
        global $CFG;

        if (!isloggedin() || isguestuser()) {
            return [];
        }

        $links = [
            ['name' => get_string('dashboard', 'block_aiplugin_nav'),
                'url' => $CFG->wwwroot . '/my/', 'icon' => 'layout-dashboard'],
            ['name' => get_string('my_courses', 'block_aiplugin_nav'),
                'url' => $CFG->wwwroot . '/my/courses.php', 'icon' => 'graduation-cap'],
            ['name' => get_string('my_profile', 'block_aiplugin_nav'),
                'url' => $CFG->wwwroot . '/user/profile.php', 'icon' => 'user'],
            ['name' => get_string('grades', 'block_aiplugin_nav'),
                'url' => $CFG->wwwroot . '/grade/report/overview/index.php', 'icon' => 'clipboard-check'],
            ['name' => get_string('calendar', 'block_aiplugin_nav'),
                'url' => $CFG->wwwroot . '/calendar/view.php', 'icon' => 'calendar-icon'],
            ['name' => get_string('messages', 'block_aiplugin_nav'),
                'url' => $CFG->wwwroot . '/message/index.php', 'icon' => 'message-square'],
        ];

        // Badges are a site-level switch; do not offer the link when they are off.
        if (!empty($CFG->enablebadges)) {
            $links[] = ['name' => get_string('my_badges', 'block_aiplugin_nav'),
                'url' => $CFG->wwwroot . '/badges/mybadges.php', 'icon' => 'award'];
        }

        $links[] = ['name' => get_string('private_files', 'block_aiplugin_nav'),
            'url' => $CFG->wwwroot . '/user/files.php', 'icon' => 'folder'];
        $links[] = ['name' => get_string('preferences', 'block_aiplugin_nav'),
            'url' => $CFG->wwwroot . '/user/preferences.php', 'icon' => 'settings-2'];

        if (!$isadmin) {
            return $links;
        }

        return array_merge($links, [
            ['name' => get_string('site_admin', 'block_aiplugin_nav'), 'url' => $CFG->wwwroot . '/admin/search.php', 'icon' => 'sliders'],
            ['name' => get_string('manage_users', 'block_aiplugin_nav'), 'url' => $CFG->wwwroot . '/admin/user.php', 'icon' => 'users'],
            ['name' => get_string('manage_courses', 'block_aiplugin_nav'), 'url' => $CFG->wwwroot . '/course/management.php', 'icon' => 'book'],
            ['name' => get_string('cohorts', 'block_aiplugin_nav'), 'url' => $CFG->wwwroot . '/cohort/index.php', 'icon' => 'users-2'],
            ['name' => get_string('reports', 'block_aiplugin_nav'), 'url' => $CFG->wwwroot . '/admin/category.php?category=reports', 'icon' => 'bar-chart-2'],
            ['name' => get_string('themes', 'block_aiplugin_nav'), 'url' => $CFG->wwwroot . '/admin/themeselector.php', 'icon' => 'palette'],
        ]);
    }

    // =====================================================================================
    // "custom" array — the user's own custom links, ported from get_custom_links().
    // =====================================================================================

    /**
     * The user's own report links, stored in the same shape as custom links.
     *
     * @param block_aiplugin_nav $block The block instance.
     * @return array
     */
    private static function build_custom_reports(block_aiplugin_nav $block): array {
        global $USER;
        $json = get_user_preferences('block_aiplugin_nav_custom_reports', '[]', $USER->id);
        $reports = json_decode($json, true);
        if (!is_array($reports)) {
            return [];
        }
        $out = [];
        foreach ($reports as $i => $report) {
            $out[] = [
                'id'   => $report['id'] ?? $i,
                'name' => $report['name'] ?? '',
                'url'  => $report['url'] ?? '',
                'icon' => $report['icon'] ?? 'chart',
            ];
        }
        return $out;
    }

    private static function build_custom_links(block_aiplugin_nav $block): array {
        global $USER;
        $linksjson = get_user_preferences('block_aiplugin_nav_custom_links', '[]', $USER->id);
        $links = json_decode($linksjson, true);
        if (!is_array($links)) {
            return [];
        }
        $out = [];
        foreach ($links as $i => $link) {
            $out[] = [
                'id'   => $link['id'] ?? $i,
                'name' => $link['name'] ?? '',
                'url'  => $link['url'] ?? '',
                'icon' => $link['icon'] ?? 'link',
            ];
        }
        return $out;
    }

    // =====================================================================================
    // "products" array — LMS Labs' other products, ported from PRODUCTS in the mockup
    // (fam cards). Names/urls/kinds/descriptions/prices/colours are hardcoded content
    // (identical treatment to plugin names/descriptions in the existing registries — see
    // get_complete_plugin_registry(), which is likewise not routed through get_string()).
    // =====================================================================================

    private static function build_products(): array {
        return [
            [
                'name'   => 'Trainly',
                'url'    => 'https://trainlycrm.com',
                'kind'   => 'CRM',
                'desc'   => 'Connects CRM, course sales, delivery, finance, support and reporting around Moodle™.',
                'price'  => '$100 USD/month',
                'colour' => '#7F22FE',
                'logo'   => '<svg viewBox="0 0 180 180" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
                    . '<defs><linearGradient id="lg-trainly" x1="24" y1="18" x2="154" y2="166" gradientUnits="userSpaceOnUse">'
                    . '<stop stop-color="#2563EB"/><stop offset="1" stop-color="#1D4ED8"/></linearGradient></defs>'
                    . '<rect width="180" height="180" rx="42" fill="#0F172A"/>'
                    . '<rect x="12" y="12" width="156" height="156" rx="34" fill="url(#lg-trainly)"/>'
                    . '<path d="M42 48C42 43.582 45.582 40 50 40H130C134.418 40 138 43.582 138 48V58C138 62.418 134.418 66 130 66H108V132'
                    . 'C108 136.418 104.418 140 100 140H80C75.582 140 72 136.418 72 132V66H50C45.582 66 42 62.418 42 58V48Z" fill="white"/>'
                    . '<path d="M121 121.5C121 116.253 125.253 112 130.5 112H138V132C138 136.418 134.418 140 130 140H121V121.5Z" fill="#F97316"/>'
                    . '</svg>',
            ],
            [
                'name'   => 'SmartForm AI',
                'url'    => 'https://smartformai.app',
                'kind'   => 'Forms',
                'desc'   => 'Builds surveys, assessments and application forms from a plain-language prompt.',
                'price'  => '$30 AUD/month · 7-day trial',
                'colour' => '#3B82F6',
                'logo'   => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
                    . '<defs><linearGradient id="lg-sfai" x1="0" y1="0" x2="32" y2="32" gradientUnits="userSpaceOnUse">'
                    . '<stop offset="0%" stop-color="#6366F1"/><stop offset="100%" stop-color="#8B5CF6"/></linearGradient></defs>'
                    . '<circle cx="16" cy="16" r="16" fill="url(#lg-sfai)"/>'
                    . '<rect x="8" y="9" width="16" height="14" rx="2" fill="white" fill-opacity="0.9"/>'
                    . '<rect x="10" y="12" width="8" height="1.5" rx="0.75" fill="#6366F1"/>'
                    . '<rect x="10" y="15" width="6" height="1.5" rx="0.75" fill="#6366F1"/>'
                    . '<rect x="10" y="18" width="10" height="1.5" rx="0.75" fill="#6366F1"/>'
                    . '<g transform="translate(20, 6)"><rect x="0" y="2" width="6" height="6" rx="2" fill="#F59E0B"/>'
                    . '<circle cx="1.5" cy="4" r="0.5" fill="white"/><circle cx="4.5" cy="4" r="0.5" fill="white"/>'
                    . '<rect x="2" y="5.5" width="2" height="0.5" rx="0.25" fill="white"/>'
                    . '<rect x="1" y="0" width="1" height="2" rx="0.5" fill="#F59E0B"/>'
                    . '<rect x="4" y="0" width="1" height="2" rx="0.5" fill="#F59E0B"/></g></svg>',
            ],
            [
                'name'   => 'LLND Check',
                'url'    => 'https://llndcheck.app',
                'kind'   => 'Assessment',
                'desc'   => "Australia's only behavioural LLND assessment measuring all ACSF and ADCF subcriteria, "
                    . 'with next-level strategies. ASQA Standards 2025 compliant.',
                'price'  => '$30 AUD/month · 7-day free trial',
                'colour' => '#3B82F6',
                // The approved mockup uses a base64-embedded raster logo for this card
                // (not an SVG like the other two products). A raster data: URI of that
                // size does not belong duplicated into PHP source, so a simple placeholder
                // SVG mark is used here instead — see payload_notes.md ("LLND Check logo").
                'logo'   => '<svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
                    . '<rect width="32" height="32" rx="8" fill="#3B82F6"/>'
                    . '<path d="M9 17l5 5 9-11" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>'
                    . '</svg>',
            ],
        ];
    }

    // =====================================================================================
    // "help" array — the hover help cards, ported from HELP/TIPS in the mockup. Each card's
    // body/title/paragraphs/bullets/tip is user-facing UI copy, so every string is routed
    // through get_string(). See payload_notes.md for the full TODO_LANG key list with their
    // English fallback text (too long to repeat as inline comments here).
    // =====================================================================================

    private static function build_help(): array {
        // key => [paragraph count, bullet count].
        $cards = [
            'block'      => [2, 3],
            'credits'    => [2, 4],
            'search'     => [1, 2],
            'plugins'    => [2, 3],
            'settings'   => [1, 2],
            'manage'     => [1, 2],
            'reports'    => [1, 2],
            'support'    => [1, 2],
            'updates'    => [1, 2],
            'health'     => [1, 2],
            'core'       => [1, 2],
            'family'     => [1, 2],
            'savelayout' => [1, 2],
        ];

        $help = [];
        foreach ($cards as $key => [$pcount, $lcount]) {
            $p = [];
            for ($i = 1; $i <= $pcount; $i++) {
                $p[] = get_string("ainav2_help_{$key}_p{$i}", 'block_aiplugin_nav');
            }
            $l = [];
            for ($i = 1; $i <= $lcount; $i++) {
                $l[] = get_string("ainav2_help_{$key}_l{$i}", 'block_aiplugin_nav');
            }
            $help[$key] = [
                'b'   => get_string("ainav2_help_{$key}_b", 'block_aiplugin_nav'),
                't'   => get_string("ainav2_help_{$key}_t", 'block_aiplugin_nav'),
                'p'   => $p,
                'l'   => $l,
                'tip' => get_string("ainav2_help_{$key}_tip", 'block_aiplugin_nav'),
            ];
        }
        return $help;
    }

    // =====================================================================================
    // Ported private helpers from block_aiplugin_nav.php (minimum logic only — see class
    // docblock for why these are duplicated rather than reflected into).
    // =====================================================================================





}
