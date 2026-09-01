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
 * AI Plugin Navigation block.
 *
 * @package    block_aiplugin_nav
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The AI Dashboard Quick Links block.
 *
 * Renders the dashboard shell and hands the client-side UI its JSON payload.
 *
 * @package    block_aiplugin_nav
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_aiplugin_nav extends block_base {
    /**
     * Initialize the block.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_aiplugin_nav');
    }

    /**
     * Allow multiple instances.
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * This block applies to all pages.
     */
    public function applicable_formats() {
        return [
            'all' => true,
            'my' => true,
            'site-index' => true,
            'course-view' => true,
            'mod' => true,
        ];
    }

    /**
     * Check if plugin is installed.
     * Static cache prevents repeated get_plugin_list() calls for the same type.
     *
     * @param string $plugintype The Moodle plugin type, e.g. mod or block.
     * @param string $pluginname The plugin's short name.
     * @return bool True when the plugin is present on this site.
     */
    public function is_plugin_installed($plugintype, $pluginname) {
        static $pluginlists = [];
        if (!isset($pluginlists[$plugintype])) {
            $pluginlists[$plugintype] = core_component::get_plugin_list($plugintype);
        }
        return isset($pluginlists[$plugintype][$pluginname]);
    }

    /**
     * Check if user has a role with a specific shortname at system level.
     *
     * @param int $userid The user ID to check.
     * @param string $shortname The role shortname to look for.
     * @return bool True if user has the role, false otherwise.
     */
    public function user_has_role_shortname($userid, $shortname) {
        global $DB;
        static $rolecache = [];
        $key = $userid . '_' . $shortname;
        if (!isset($rolecache[$key])) {
            $sql = "SELECT ra.id
                    FROM {role_assignments} ra
                    JOIN {role} r ON r.id = ra.roleid
                    WHERE ra.userid = :userid
                    AND r.shortname = :shortname";
            $rolecache[$key] = $DB->record_exists_sql($sql, ['userid' => $userid, 'shortname' => $shortname]);
        }
        return $rolecache[$key];
    }

    /**
     * Master registry of all AI Grader ecosystem plugins.
     * This is the single source of truth - new plugins only need to be added here.
     * The block will automatically detect which ones are installed.
     *
     * @return array Complete plugin registry with detection and URL patterns.
     */
    public function get_master_plugin_registry() {
        return [
            // AI PLUGINS (Credit-Based).
            // Note: Plugins with only Site ID/API Key don't have settings_url
            // As those credentials come from AI Grader Central Config.
            'quiz_aigrader' => [
                'name' => 'AI Essay Grader',
                'plugin_type' => 'quiz',
                'plugin_name' => 'aigrader',
                'settings_url' => '/admin/settings.php?section=quiz_aigrader',
                'report_url' => '/mod/quiz/report/aigrader/grader_report.php',
                'icon' => 'edit-3',
                'category' => 'ai_grading',
            ],
            'local_aiquizmaker' => [
                'name' => 'AI Quiz Maker',
                'plugin_type' => 'local',
                'plugin_name' => 'aiquizmaker',
                'settings_url' => '/admin/settings.php?section=local_aiquizmaker',
                'page_url' => '/local/aiquizmaker/index.php',
                'icon' => 'pen-tool',
                'category' => 'ai_grading',
            ],
            // Local_essaymaker is the legacy name for local_aiquizmaker (renamed at v3.16.16).
            // Some sites still have it installed from the transition period with broken class
            // Namespaces that cause a fatal PHP collision with local_aiquizmaker.
            // This entry makes the block detect it and offer the namespace-fix upgrade (v3.16.89).
            'local_essaymaker' => [
                'name' => 'AI Quiz Maker (Legacy — update to fix)',
                'plugin_type' => 'local',
                'plugin_name' => 'essaymaker',
                'settings_url' => '/admin/settings.php?section=local_essaymaker',
                'page_url' => '/local/essaymaker/index.php',
                'icon' => 'pen-tool',
                'category' => 'ai_grading',
                'legacy' => true,
            ],
            'mod_smartworkbook' => [
                'name' => 'AI Smart Workbook',
                'plugin_type' => 'mod',
                'plugin_name' => 'smartworkbook',
                'settings_url' => '/admin/settings.php?section=modsettingsmartworkbook',
                'icon' => 'book-open',
                'category' => 'ai_content',
            ],
            'mod_contentcreator' => [
                'name' => 'AI Content Creator',
                'plugin_type' => 'mod',
                'plugin_name' => 'contentcreator',
                'settings_url' => '/admin/settings.php?section=modsettingcontentcreator',
                'icon' => 'book-open',
                'category' => 'ai_content',
            ],
            'mod_aiknowledgecheck' => [
                'name' => 'AI Knowledge Check',
                'plugin_type' => 'mod',
                'plugin_name' => 'aiknowledgecheck',
                'settings_url' => '/admin/settings.php?section=modsettingaiknowledgecheck',
                'icon' => 'check-square',
                'category' => 'ai_grading',
            ],
            'mod_aiactivities' => [
                'name' => 'AI Learning Activities',
                'plugin_type' => 'mod',
                'plugin_name' => 'aiactivities',
                'settings_url' => '/admin/settings.php?section=modsettingaiactivities',
                'icon' => 'layers',
                'category' => 'ai_content',
            ],
            'mod_learningmapping' => [
                'name' => 'AI Mapping',
                'plugin_type' => 'mod',
                'plugin_name' => 'learningmapping',
                'settings_url' => '/admin/settings.php?section=modsettinglearningmapping',
                'icon' => 'table',
                'category' => 'ai_grading',
            ],
            'mod_courseinfo' => [
                'name' => 'AI Course Information',
                'plugin_type' => 'mod',
                'plugin_name' => 'courseinfo',
                'settings_url' => '/admin/settings.php?section=modsettingcourseinfo',
                'icon' => 'file-text',
                'category' => 'ai_content',
            ],
            'mod_productexplainer' => [
                'name' => 'AI Slide Flow',
                'plugin_type' => 'mod',
                'plugin_name' => 'productexplainer',
                'settings_url' => '/admin/settings.php?section=modsettingproductexplainer',
                'icon' => 'presentation',
                'category' => 'ai_content',
            ],
            'mod_verifyid' => [
                'name' => 'AI Verify ID',
                'plugin_type' => 'mod',
                'plugin_name' => 'verifyid',
                'settings_url' => '/admin/settings.php?section=modsettingverifyid',
                'icon' => 'user-check',
                'category' => 'ai_ux',
            ],
            'mod_aivideoactivity' => [
                'name' => 'AI Video Activity',
                'plugin_type' => 'mod',
                'plugin_name' => 'aivideoactivity',
                'settings_url' => '/admin/settings.php?section=modsettingaivideoactivity',
                'icon' => 'play-circle',
                'category' => 'ai_media',
            ],
            'mod_slideshow' => [
                'name' => 'AI Slideshow with Voiceover',
                'plugin_type' => 'mod',
                'plugin_name' => 'slideshow',
                'settings_url' => '/admin/settings.php?section=modsettingslideshow',
                'icon' => 'image',
                'category' => 'ai_media',
            ],
            'local_chirpvoice' => [
                'name' => 'AI SCORM Voiceover',
                'plugin_type' => 'local',
                'plugin_name' => 'chirpvoice',
                'settings_url' => '/admin/settings.php?section=local_chirpvoice',
                'icon' => 'headset',
                'category' => 'ai_media',
            ],
            'local_moodlesupport' => [
                'name' => 'AI Moodle Support',
                'plugin_type' => 'local',
                'plugin_name' => 'moodlesupport',
                'settings_url' => '/admin/settings.php?section=local_moodlesupport',
                'page_url' => '/local/moodlesupport/index.php',
                'icon' => 'help-circle',
                'category' => 'ai_ux',
            ],
            'local_rtocompliance' => [
                'name' => 'AI RTO Compliance',
                'plugin_type' => 'local',
                'plugin_name' => 'rtocompliance',
                'settings_url' => '/admin/settings.php?section=local_rtocompliance_settings',
                'page_url' => '/local/rtocompliance/index.php',
                'report_url' => '/local/rtocompliance/ai_usage_report.php',
                'icon' => 'briefcase',
                'category' => 'ai_rto',
            ],
            'block_rtocompliance' => [
                'name' => 'RTO Compliance Dashboard',
                'plugin_type' => 'block',
                'plugin_name' => 'rtocompliance',
                'settings_url' => '/admin/settings.php?section=blocksettingrtocompliance',
                'page_url' => '/local/rtocompliance/index.php',
                'icon' => 'layout-dashboard',
                'category' => 'ai_rto',
            ],
            'local_rplkit' => [
                'name' => 'RPL Kit',
                'plugin_type' => 'local',
                'plugin_name' => 'rplkit',
                'settings_url' => '/admin/settings.php?section=local_rplkit',
                'page_url' => '/local/rplkit/index.php',
                'icon' => 'file-check-2',
                'category' => 'ai_rto',
            ],
            // BLOCKS.
            'block_aigrader_dashboard' => [
                'name' => 'AI Grader Dashboard',
                'plugin_type' => 'block',
                'plugin_name' => 'aigrader_dashboard',
                'settings_url' => '/admin/settings.php?section=blocksettingaigrader_dashboard',
                'icon' => 'layout-dashboard',
                'category' => 'block',
            ],
            'block_aiplugin_nav' => [
                'name' => 'AI Dashboard Quick Links',
                'plugin_type' => 'block',
                'plugin_name' => 'aiplugin_nav',
                'settings_url' => '/admin/settings.php?section=blocksettingaiplugin_nav',
                'icon' => 'navigation',
                'category' => 'block',
            ],
            'block_my_progress' => [
                'name' => 'My Progress',
                'plugin_type' => 'block',
                'plugin_name' => 'my_progress',
                'settings_url' => '/admin/settings.php?section=blocksettingmy_progress',
                'icon' => 'bar-chart-2',
                'category' => 'block',
            ],
            'block_my_students_progress' => [
                'name' => 'My Students Progress',
                'plugin_type' => 'block',
                'plugin_name' => 'my_students_progress',
                'settings_url' => '/admin/settings.php?section=blocksettingmy_students_progress',
                'icon' => 'users',
                'category' => 'block',
            ],
            // CENTRAL CONFIG.
            'local_aiconfig' => [
                'name' => 'AI Grader Central Config',
                'plugin_type' => 'local',
                'plugin_name' => 'aiconfig',
                'settings_url' => '/admin/settings.php?section=local_aiconfig',
                'page_url' => '/admin/settings.php?section=local_aiconfig',
                'icon' => 'settings',
                'category' => 'config',
            ],
            // TIME SAVING PLUGINS (Admin).
            'local_groupmanager' => [
                'name' => 'Groups Management',
                'plugin_type' => 'local',
                'plugin_name' => 'groupmanager',
                'settings_url' => '/admin/settings.php?section=local_groupmanager',
                'page_url' => '/local/groupmanager/index.php',
                'icon' => 'users',
                'category' => 'enrolment',
            ],
            'enrol_prerequisite' => [
                'name' => 'Course Prerequisite',
                'plugin_type' => 'enrol',
                'plugin_name' => 'prerequisite',
                'settings_url' => '/admin/settings.php?section=enrolsettingsprerequisite',
                'page_url' => '/enrol/prerequisite/gates.php',
                'report_url' => '/enrol/prerequisite/gates.php',
                'icon' => 'lock',
                'category' => 'enrolment',
            ],
            'local_courseversion' => [
                'name' => 'Course Version Control',
                'plugin_type' => 'local',
                'plugin_name' => 'courseversion',
                'settings_url' => '/admin/settings.php?section=local_courseversion',
                'page_url' => '/local/courseversion/index.php',
                'icon' => 'folder',
                'category' => 'security',
            ],
            'local_sitefont' => [
                'name' => 'Change Site Font',
                'plugin_type' => 'local',
                'plugin_name' => 'sitefont',
                'settings_url' => '/admin/settings.php?section=local_sitefont',
                'page_url' => '/admin/settings.php?section=local_sitefont',
                'icon' => 'sliders',
                'category' => 'branding',
            ],
            'local_cohortbranding' => [
                'name' => 'Cohort Branding',
                'plugin_type' => 'local',
                'plugin_name' => 'cohortbranding',
                'settings_url' => '/admin/settings.php?section=local_cohortbranding',
                'page_url' => '/local/cohortbranding/index.php',
                'icon' => 'palette',
                'category' => 'branding',
            ],
            'gradingform_benchmarks' => [
                'name' => 'Assignment Benchmarks',
                'plugin_type' => 'gradingform',
                'plugin_name' => 'benchmarks',
                'settings_url' => '/admin/settings.php?section=gradingformbenchmarks',
                'icon' => 'award',
                'category' => 'ai_grading',
            ],
            'auth_simple2fa' => [
                'name' => 'Simple 2FA & SSO',
                'plugin_type' => 'auth',
                'plugin_name' => 'simple2fa',
                'settings_url' => '/admin/settings.php?section=authsettingsimple2fa',
                'page_url' => '/admin/settings.php?section=authsettingsimple2fa',
                'icon' => 'shield',
                'category' => 'security',
            ],
            'local_groupcap' => [
                'name' => 'Group Membership Limit',
                'plugin_type' => 'local',
                'plugin_name' => 'groupcap',
                'settings_url' => '/admin/settings.php?section=local_groupcap',
                'page_url' => '/admin/settings.php?section=local_groupcap',
                'icon' => 'users',
                'category' => 'enrolment',
            ],
            'local_paymentunlockassign' => [
                'name' => 'Payment Unlock Assignment',
                'plugin_type' => 'local',
                'plugin_name' => 'paymentunlockassign',
                'settings_url' => '/admin/settings.php?section=local_paymentunlockassign',
                'page_url' => '/local/paymentunlockassign/manage.php',
                'icon' => 'lock',
                'category' => 'enrolment',
            ],
            'plagiarism_essayguard' => [
                'name' => 'Essay Guard',
                'plugin_type' => 'plagiarism',
                'plugin_name' => 'essayguard',
                'settings_url' => '/plagiarism/essayguard/settings.php',
                'page_url' => '/plagiarism/essayguard/settings.php',
                'report_url' => '/plagiarism/essayguard/report.php',
                'icon' => 'shield',
                'category' => 'integrity',
            ],
            'plagiarism_docguard' => [
                'name' => 'DocGuard',
                'plugin_type' => 'plagiarism',
                'plugin_name' => 'docguard',
                'settings_url' => '/plagiarism/docguard/settings.php',
                'page_url' => '/plagiarism/docguard/settings.php',
                'report_url' => '/plagiarism/docguard/report.php',
                'icon' => 'file-search',
                'category' => 'integrity',
            ],
            'local_videocompress' => [
                'name' => 'Video Compress',
                'plugin_type' => 'local',
                'plugin_name' => 'videocompress',
                'settings_url' => '/admin/settings.php?section=local_videocompress',
                'page_url' => '/local/videocompress/index.php',
                'icon' => 'film',
                'category' => 'media_storage',
            ],
            'local_scormcompress' => [
                'name' => 'SCORM Compress',
                'plugin_type' => 'local',
                'plugin_name' => 'scormcompress',
                'settings_url' => '/admin/settings.php?section=local_scormcompress',
                'page_url' => '/local/scormcompress/index.php',
                'icon' => 'archive',
                'category' => 'media_storage',
            ],
            'local_mediaoptimiser' => [
                'name' => 'Media Optimiser',
                'plugin_type' => 'local',
                'plugin_name' => 'mediaoptimiser',
                'settings_url' => '/admin/settings.php?section=local_mediaoptimiser_settings',
                'page_url' => '/local/mediaoptimiser/index.php',
                'icon' => 'hard-drive',
                'category' => 'media_storage',
            ],
            'local_activitynav' => [
                'name' => 'Activity Navigation',
                'plugin_type' => 'local',
                'plugin_name' => 'activitynav',
                'settings_url' => '/admin/settings.php?section=local_activitynav',
                'icon' => 'navigation',
                'category' => 'training',
            ],
            'local_courseavailabilitydelay' => [
                'name' => 'Course Availability Delay',
                'plugin_type' => 'local',
                'plugin_name' => 'courseavailabilitydelay',
                'settings_url' => '/admin/settings.php?section=local_courseavailabilitydelay',
                'page_url' => '/local/courseavailabilitydelay/manage.php',
                'icon' => 'clock',
                'category' => 'training',
            ],
            'local_trainingpathways' => [
                'name' => 'Training Pathways',
                'plugin_type' => 'local',
                'plugin_name' => 'trainingpathways',
                'settings_url' => '/admin/settings.php?section=local_trainingpathways',
                'page_url' => '/local/trainingpathways/manage.php',
                'icon' => 'map',
                'category' => 'training',
            ],
            'local_recertify' => [
                'name' => 'Course Recertification',
                'plugin_type' => 'local',
                'plugin_name' => 'recertify',
                'settings_url' => '/admin/settings.php?section=local_recertify_settings',
                'page_url' => '/local/recertify/index.php',
                'report_url' => '/local/recertify/index.php',
                'icon' => 'refresh-cw',
                'category' => 'training',
            ],
            'local_completionsuspend' => [
                'name' => 'Completion Auto-Suspend',
                'plugin_type' => 'local',
                'plugin_name' => 'completionsuspend',
                'settings_url' => '/admin/settings.php?section=local_completionsuspend_settings',
                'page_url' => '/local/completionsuspend/index.php',
                'report_url' => '/local/completionsuspend/index.php?tab=activity',
                'icon' => 'user-check',
                'category' => 'training',
            ],
            'block_trainingplan' => [
                'name' => 'Training Plan',
                'plugin_type' => 'block',
                'plugin_name' => 'trainingplan',
                'settings_url' => '/admin/settings.php?section=blocksettingtrainingplan',
                'page_url' => '/admin/settings.php?section=blocksettingtrainingplan',
                'icon' => 'calendar-clock',
                'category' => 'training',
            ],
            // Separate notifications entry so "Training Plan Notifications" is explicitly
            // Searchable in the Settings dropdown — resolves to the same settings page
            // Which contains the master kill switch, test recipients, cutoff, and exclusion list.
            'block_trainingplan_notifications' => [
                'name' => 'Training Plan — Notifications',
                'plugin_type' => 'block',
                'plugin_name' => 'trainingplan',
                'settings_url' => '/admin/settings.php?section=blocksettingtrainingplan',
                'icon' => 'bell',
                'category' => 'training',
            ],
            'local_workshops' => [
                'name' => 'Workshop Scheduler',
                'plugin_type' => 'local',
                'plugin_name' => 'workshops',
                'page_url' => '/local/workshops/index.php',
                'icon' => 'calendar',
                'category' => 'training',
            ],
            'local_downalert' => [
                'name' => 'Site Down Alert',
                'plugin_type' => 'local',
                'plugin_name' => 'downalert',
                'settings_url' => '/admin/settings.php?section=local_downalert',
                'report_url' => '/local/downalert/report.php',
                'icon' => 'zap',
                'category' => 'comms',
            ],
            'local_studentemail' => [
                'name' => 'Student Email Manager',
                'plugin_type' => 'local',
                'plugin_name' => 'studentemail',
                'settings_url' => '/admin/settings.php?section=local_studentemail',
                'page_url' => '/local/studentemail/dashboard.php',
                'icon' => 'mail',
                'category' => 'comms',
            ],
            'auth_studentemail' => [
                'name' => 'Student Email IMAP Auth',
                'plugin_type' => 'auth',
                'plugin_name' => 'studentemail',
                'settings_url' => '/admin/settings.php?section=authsettingstudentemail',
                'icon' => 'mail',
                'category' => 'comms',
            ],
            'format_aicourse' => [
                'name' => 'AI Course Format',
                'plugin_type' => 'format',
                'plugin_name' => 'aicourse',
                'settings_url' => '/admin/settings.php?section=formatsettingaicourse',
                'report_url' => '/course/format/aicourse/admin_report.php',
                'icon' => 'book-open',
                'category' => 'ai_content',
            ],
            'quizaccess_aigrader' => [
                'name' => 'Quiz Access Rule',
                'plugin_type' => 'quizaccess',
                'plugin_name' => 'aigrader',
                'settings_url' => '/admin/settings.php?section=quizaccessaigrader',
                'icon' => 'lock',
                'category' => 'enrolment',
            ],
            'availability_groupmanager' => [
                'name' => 'Groups Availability Condition',
                'plugin_type' => 'availability',
                'plugin_name' => 'groupmanager',
                'settings_url' => '/admin/settings.php?section=availabilitysettinggroupmanager',
                'icon' => 'users',
                'category' => 'enrolment',
            ],
            'paygw_paddle' => [
                'name' => 'Paddle Payment Gateway',
                'plugin_type' => 'paygw',
                'plugin_name' => 'paddle',
                // No settings_url: Paddle has no single admin settings page.
                // API keys are at Site admin > Plugins > Payment gateways > Manage payment gateways.
                // Payment prices are configured per-course via Course settings > Enrolments > Payment.
                'page_url' => '/payment/gateway/paddle/admin/reports.php',
                'report_url' => '/payment/gateway/paddle/admin/pricemap.php',
                'icon' => 'credit-card',
                'category' => 'payments',
            ],
            'local_aiquizremedial' => [
                'name' => 'AI Quiz Remedial Learning',
                'plugin_type' => 'local',
                'plugin_name' => 'aiquizremedial',
                'settings_url' => '/admin/settings.php?section=local_aiquizremedial',
                'page_url' => '/local/aiquizremedial/index.php',
                'icon' => 'refresh-cw',
                'category' => 'ai_grading',
            ],
            'local_ailogin' => [
                'name' => 'AI Login Designer',
                'plugin_type' => 'local',
                'plugin_name' => 'ailogin',
                'settings_url' => '/admin/settings.php?section=local_ailogin_admin',
                'page_url' => '/local/ailogin/admin.php',
                'icon' => 'layout',
                'category' => 'ai_ux',
            ],
            'mod_attendance' => [
                'name' => 'Attendance',
                'plugin_type' => 'mod',
                'plugin_name' => 'attendance',
                'report_url' => '/blocks/aiplugin_nav/attendance_report.php',
                'icon' => 'users',
                'category' => 'training',
            ],
            'local_beacon' => [
                'name' => 'Beacon — Reports & Analytics',
                'plugin_type' => 'local',
                'plugin_name' => 'beacon',
                'settings_url' => '/admin/settings.php?section=local_beacon',
                'page_url' => '/local/beacon/index.php',
                'icon' => 'bar-chart-2',
                'category' => 'reporting',
            ],
            'local_lmshomepage' => [
                'name' => 'LMS Home Page',
                'plugin_type' => 'local',
                'plugin_name' => 'lmshomepage',
                'settings_url' => '/admin/settings.php?section=local_lmshomepage',
                'icon' => 'home',
                'category' => 'branding',
            ],
        ];
    }

    /**
     * Get the theme's primary/brand color.
     * Returns a placeholder that will be replaced by JavaScript detection.
     * This is more reliable in Moodle 5 where PHP can't always read theme settings.
     *
     * @return string Placeholder or fallback color.
     */
    public function get_theme_primary_color() {
        global $CFG;

        $defaultcolor = '#3b82f6';

        // Method 1: Try using theme_config::load() for Moodle 4.x/5.x.
        try {
            $themeconfig = \theme_config::load($this->page->theme->name);
            if (!empty($themeconfig->settings->brandcolor)) {
                return $themeconfig->settings->brandcolor;
            }
            if (!empty($themeconfig->settings->primarycolor)) {
                return $themeconfig->settings->primarycolor;
            }
            if (!empty($themeconfig->settings->primary)) {
                return $themeconfig->settings->primary;
            }
        } catch (Exception $e) {
            debugging($e->getMessage(), DEBUG_DEVELOPER);
        }

        // Method 2: Try get_config for current theme.
        $themename = !empty($this->page->theme->name) ? $this->page->theme->name : '';
        if (!empty($themename)) {
            $brandcolor = get_config('theme_' . $themename, 'brandcolor');
            if (!empty($brandcolor)) {
                return $brandcolor;
            }
            $primarycolor = get_config('theme_' . $themename, 'primarycolor');
            if (!empty($primarycolor)) {
                return $primarycolor;
            }
        }

        // Method 3: Check parent themes.
        if (!empty($themename)) {
            try {
                $themeconfig = \theme_config::load($themename);
                if (!empty($themeconfig->parents)) {
                    foreach ($themeconfig->parents as $parent) {
                        $parentcolor = get_config('theme_' . $parent, 'brandcolor');
                        if (!empty($parentcolor)) {
                            return $parentcolor;
                        }
                    }
                }
            } catch (Exception $e) {
                debugging($e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // Method 4: Boost theme.
        $boostcolor = get_config('theme_boost', 'brandcolor');
        if (!empty($boostcolor)) {
            return $boostcolor;
        }

        // Nothing usable. Return an empty string so the caller omits the custom property
        // altogether and the stylesheet's own fallback applies. This used to return the
        // marker '__DETECT_FROM_DOM__' for JavaScript to swap out, but no such JavaScript
        // exists: the marker went straight into style="--primary: ..." as an invalid colour,
        // which made --accent and every colour derived from it invalid at computed-value
        // time. Backgrounds fell back to transparent and the accent icons disappeared —
        // visible on Boost, whose brandcolor is unset by default.
        return '';
    }

    /**
     * Does this string look like a CSS colour we can safely inline into a style attribute?
     *
     * A theme setting is admin-supplied text, and anything that is not a colour would
     * silently invalidate every custom property derived from it.
     *
     * @param string $value The candidate colour.
     * @return bool True when the value is a hex, rgb(), hsl() or simple keyword colour.
     */
    protected function is_css_color(string $value): bool {
        $value = trim($value);
        if ($value === '') {
            return false;
        }
        if (preg_match('/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value)) {
            return true;
        }
        if (preg_match('/^(?:rgb|hsl)a?\([0-9a-z%.,\/\s+-]*\)$/i', $value)) {
            return true;
        }
        return (bool) preg_match('/^[a-z]{3,20}$/i', $value);
    }

    /**
     * Build the block content.
     */
    public function get_content() {
        global $CFG, $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';

        // NEW-UI (v2.5.0): the block renders a mount point plus a JSON payload; the
        // ainav2 AMD module builds the card and panel interface from it. Everything
        // stays inside the block — the admin is never sent to Site administration.
        require_once($CFG->dirroot . '/blocks/aiplugin_nav/classes/payload.php');

        $primarycolor = $this->get_theme_primary_color();
        $payload = block_aiplugin_nav_payload::build($this);

        // Only set --primary when we actually have a colour. Setting it to anything else
        // defeats the stylesheet's own var(--primary, #0e6e68) fallback, because a custom
        // property that is set-but-invalid is substituted as-is rather than falling back.
        $vars = '';
        if ($this->is_css_color((string) $primarycolor)) {
            $vars .= '--primary: ' . s($primarycolor) . ';';
        }
        // Minimum width, in pixels. Clamped: 0 disables it, and anything beyond 3000 is a
        // typo rather than an intention.
        $minwidth = (int) get_config('block_aiplugin_nav', 'minwidth');
        if ($minwidth > 0) {
            $vars .= '--ainav2-minw: ' . min($minwidth, 3000) . 'px;';
        }
        $style = $vars === '' ? '' : ' style="' . $vars . '"';
        $html  = '<div class="ainav2" id="ainav2-root"' . $style . '>';
        $html .= '<div class="ainav2-boot">' . get_string('loading_block', 'block_aiplugin_nav') . '</div>';
        $html .= '</div>';
        $html .= '<script type="application/json" id="ainav2-data">'
            . json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES)
            . '</script>';
        $html .= '<noscript><div class="ainav2-noscript">'
            . get_string('noscript_message', 'block_aiplugin_nav') . '</div></noscript>';

        // The block's four user preferences (favourites, saved layouts, help switch and
        // install receipt) are declared in lib.php via block_aiplugin_nav_user_preferences(),
        // which is what lets core_user/repository write them. The old
        // user_preference_allow_ajax_update() route is deprecated and is not used.
        $this->page->requires->js_call_amd('block_aiplugin_nav/ui', 'init');

        // The credits module is not loaded alongside the new UI. It writes to
        // #ainav-credits-placeholder and #ainav-credits-badge, legacy element ids this
        // shell never renders, so it did nothing except fire a second get_credits web
        // service call on every page load. The new UI fetches the balance itself.

        $this->content->text = $html;

        return $this->content;
    }

    /**
     * Get the complete plugin registry for version checking and updates.
     * Each plugin has component name, status, download URL, description, and access info.
     */
    public function get_complete_plugin_registry() {
        return [
            // Configuration Plugin (Install First).
            [
                'name' => 'AI Grader Central Config',
                'component' => 'local_aiconfig',
                'plugin_type' => 'local',
                'plugin_name' => 'aiconfig',
                'icon' => 'settings',
                'category' => 'config',
                'description' => 'Centralized Site ID and API Key configuration. Install first - all AI plugins inherit these' .
                    ' settings.',
                'access' => 'Site admin > Plugins > Local plugins > AI Grader Central Config',
                'goto_url' => '/admin/settings.php?section=local_aiconfig',
                'install_first' => true,
            ],
            // AI Plugins (Credit-Based).
            [
                'name' => 'AI Essay Grader',
                'component' => 'quiz_aigrader',
                'plugin_type' => 'quiz',
                'plugin_name' => 'aigrader',
                'icon' => 'pen-tool',
                'category' => 'ai_grading',
                'description' => 'AI-powered essay grading with detailed feedback, rubric alignment, and growth mindset guidance.',
                'access' => 'Quiz > Settings > AI Grader tab',
            ],
            [
                'name' => 'AI Quiz Maker',
                'component' => 'local_aiquizmaker',
                'plugin_type' => 'local',
                'plugin_name' => 'aiquizmaker',
                'icon' => 'edit-3',
                'category' => 'ai_grading',
                'description' => 'Generate quiz and essay questions with marking criteria based on competency units and learning ' .
                    'outcomes. Supports model responses, ChatGPT prompt helper, and Moodle XML export.',
                'access' => 'Quiz → Settings icon (gear) → AI Quiz Maker',
            ],
            [
                'name' => 'AI Content Creator',
                'component' => 'mod_contentcreator',
                'plugin_type' => 'mod',
                'plugin_name' => 'contentcreator',
                'icon' => 'book-open',
                'category' => 'ai_content',
                'description' => 'Create interactive SCORM slideshows with AI-generated images, voiceovers in 52 languages, and e' .
                    'mbedded activities.',
                'access' => 'Course > Add activity > AI Content Creator',
            ],
            [
                'name' => 'AI Knowledge Check',
                'component' => 'mod_aiknowledgecheck',
                'plugin_type' => 'mod',
                'plugin_name' => 'aiknowledgecheck',
                'icon' => 'check-square',
                'category' => 'ai_grading',
                'description' => 'Self-paced knowledge checks with voice feedback, psychometric distractors, and comprehensive ' .
                    'reporting.',
                'access' => 'Course > Add activity > AI Knowledge Check',
            ],
            [
                'name' => 'AI Learning Activities',
                'component' => 'mod_aiactivities',
                'plugin_type' => 'mod',
                'plugin_name' => 'aiactivities',
                'icon' => 'layers',
                'category' => 'ai_content',
                'description' => 'Interactive revision activities generated from learning content. 5 types: ordering, category so' .
                    'rt, column sort, card select, and matching.',
                'access' => 'Course > Add activity > AI Learning Activities',
            ],
            [
                'name' => 'AI Video Activity',
                'component' => 'mod_aivideoactivity',
                'plugin_type' => 'mod',
                'plugin_name' => 'aivideoactivity',
                'icon' => 'play-circle',
                'category' => 'ai_media',
                'description' => 'AI-powered video learning with auto-generated questions from YouTube transcripts and voiceover ' .
                    'narration.',
                'access' => 'Course > Add activity > AI Video Activity',
            ],
            [
                'name' => 'AI Slideshow with Voiceover',
                'component' => 'mod_slideshow',
                'plugin_type' => 'mod',
                'plugin_name' => 'slideshow',
                'icon' => 'image',
                'category' => 'ai_media',
                'description' => 'Standalone slideshow player with AI voiceover in 52 languages, progress tracking, and SCO' .
                    'RM export.',
                'access' => 'Course > Add activity > AI Slideshow',
            ],
            [
                'name' => 'Slides',
                'component' => 'mod_slides',
                'plugin_type' => 'mod',
                'plugin_name' => 'slides',
                'icon' => 'presentation',
                'category' => 'ai_content',
                'description' => 'Interactive multi-type slide activities with seven slide sub-types (video, flip, image-text, im' .
                    'age-poster, introduction, matching, summary). Build rich course content with completion tracking.',
                'access' => 'Course > Add activity > Slides',
            ],
            [
                'name' => 'AI SCORM Voiceover',
                'component' => 'local_chirpvoice',
                'plugin_type' => 'local',
                'plugin_name' => 'chirpvoice',
                'icon' => 'headset',
                'category' => 'ai_media',
                'description' => 'Google Chirp 3 HD narration for Articulate Rise 360 SCORM courses. Floating toolbar with 8 voic' .
                    'es, 31 languages, speed control, and server-side audio cache (3 credits per paragraph, first play only).',
                'access' => 'Site admin > Plugins > Local plugins > AI SCORM Voiceover',
                'goto_url' => '/admin/settings.php?section=local_chirpvoice',
            ],
            [
                'name' => 'AI Mapping',
                'component' => 'mod_learningmapping',
                'plugin_type' => 'mod',
                'plugin_name' => 'learningmapping',
                'icon' => 'table',
                'category' => 'ai_grading',
                'description' => 'AI-powered ASQA-compliant mapping table. Uses OpenAI to analyse course content and automaticall' .
                    'y map activities to training package elements. 100 credits per AI analysis.',
                'access' => 'Course > Add activity > AI Mapping',
            ],
            [
                'name' => 'AI Course Information',
                'component' => 'mod_courseinfo',
                'plugin_type' => 'mod',
                'plugin_name' => 'courseinfo',
                'icon' => 'file-text',
                'category' => 'ai_content',
                'description' => 'Generates ASQA 2025-compliant course information with step-by-step student guides, activity tim' .
                    'ings, and Volume of Learning compliance. 100 credits per generation.',
                'access' => 'Course > Add activity > AI Course Information',
            ],
            [
                'name' => 'AI Slide Flow',
                'component' => 'mod_productexplainer',
                'plugin_type' => 'mod',
                'plugin_name' => 'productexplainer',
                'icon' => 'presentation',
                'category' => 'ai_content',
                'description' => 'AI-powered slide presentations. Upload a PDF for Product Slides or enter a concept for Concept ' .
                    'Slides — AI generates structured training slides with optional voiceover narration (10 credits per ge' .
                        'neration).',
                'access' => 'Course > Add activity > AI Slide Flow',
            ],
            [
                'name' => 'AI Smart Workbook',
                'component' => 'mod_smartworkbook',
                'plugin_type' => 'mod',
                'plugin_name' => 'smartworkbook',
                'icon' => 'book-open',
                'category' => 'ai_content',
                'description' => 'Convert any Word or PDF teacher workbook into an interactive fillable student activity. AI auto' .
                    '-marks submissions against your answer key; teacher reviews and approves before grades post to the gradebook.',
                'access' => 'Course > Add activity > AI Smart Workbook',
            ],
            [
                'name' => 'AI Verify ID',
                'component' => 'mod_verifyid',
                'plugin_type' => 'mod',
                'plugin_name' => 'verifyid',
                'icon' => 'user-check',
                'category' => 'ai_ux',
                'description' => 'AI-powered identity verification using face comparison with configurable similarity thresholds.',
                'access' => 'Course > Add activity > AI Verify ID',
            ],
            [
                'name' => 'AI RTO Compliance',
                'component' => 'local_rtocompliance',
                'plugin_type' => 'local',
                'plugin_name' => 'rtocompliance',
                'icon' => 'briefcase',
                'category' => 'ai_rto',
                'description' => 'ASQA 2025 compliant Student Management System with trainer credentials, TAS generator, qualific' .
                    'ation builder, and AI-powered compliance reporting.',
                'access' => 'Site admin > Plugins > Local plugins > RTO Compliance',
                'goto_url' => '/local/rtocompliance/index.php',
            ],
            // V2.4.62 REMOVE-AIPAGETEMPLATES-FINAL: tiny_aipagetemplates deliberately
            // ABSENT from this registry. It crashed customer sites, was removed in
            // V2.4.39, and was accidentally re-added in v2.4.53. It is also
            // Deprecated server-side (excluded from the lms-labs.com manifest), so
            // It must NEVER be re-added here.
            [
                'name' => 'RPL Kit',
                'component' => 'local_rplkit',
                'plugin_type' => 'local',
                'plugin_name' => 'rplkit',
                'icon' => 'file-check-2',
                'category' => 'ai_rto',
                'description' => 'Generates ASQA-mapped RPL assessment kits for any unit of competency: theory quiz (essay questi' .
                    'ons from Knowledge Evidence), SmartForm checklist (Performance Criteria), and Assignment Benchmarks criteria' .
                        '. Integrates with RTO Compliance for shared qualification data.',
                'access' => 'Site admin > Plugins > Local plugins > RPL Kit',
                'goto_url' => '/local/rplkit/index.php',
            ],
            [
                'name' => 'AI Support',
                'component' => 'local_moodlesupport',
                'plugin_type' => 'local',
                'plugin_name' => 'moodlesupport',
                'icon' => 'headset',
                'category' => 'ai_ux',
                'description' => 'AI-powered help desk with trained knowledge base. Answers Moodle questions instantly via ch' .
                    'at widget.',
                'access' => 'Site admin > Plugins > Local plugins > AI Support',
                'goto_url' => '/local/moodlesupport/index.php',
            ],
            // Reporting & Analytics.
            [
                'name' => 'Beacon — Reports & Analytics',
                'component' => 'local_beacon',
                'plugin_type' => 'local',
                'plugin_name' => 'beacon',
                'icon' => 'bar-chart-2',
                'category' => 'reporting',
                'description' => 'Flexible report builder with 49 pre-built recipes. Grain-aware engine prevents row-multiplicati' .
                    'on errors. Schedule reports by email or cohort with threshold alerts.',
                'access' => 'Site admin > Plugins > Local plugins > Beacon',
                'goto_url' => '/local/beacon/index.php',
            ],
            // Blocks.
            [
                'name' => 'AI Grader Dashboard',
                'component' => 'block_aigrader_dashboard',
                'plugin_type' => 'block',
                'plugin_name' => 'aigrader_dashboard',
                'icon' => 'layout-dashboard',
                'category' => 'block',
                'description' => 'Dashboard showing essays pending AI grading across all courses with quick access links.',
                'access' => 'Dashboard > Add block > AI Grader Dashboard',
            ],
            [
                'name' => 'AI Dashboard Quick Links',
                'component' => 'block_aiplugin_nav',
                'plugin_type' => 'block',
                'plugin_name' => 'aiplugin_nav',
                'icon' => 'link',
                'category' => 'block',
                'description' => 'Central navigation hub for all AI plugins with version checking and quick site links.',
                'access' => 'Dashboard > Add block > AI Dashboard Quick Links',
            ],
            [
                'name' => 'My Progress',
                'component' => 'block_my_progress',
                'plugin_type' => 'block',
                'plugin_name' => 'my_progress',
                'icon' => 'bar-chart-2',
                'category' => 'block',
                'description' => 'Student progress dashboard with real-time course completion tracking and motivational labels.',
                'access' => 'Dashboard > Add block > My Progress',
            ],
            [
                'name' => 'My Students Progress',
                'component' => 'block_my_students_progress',
                'plugin_type' => 'block',
                'plugin_name' => 'my_students_progress',
                'icon' => 'users',
                'category' => 'block',
                'description' => 'Teacher view of student progress across courses with search, filters, and per-student completio' .
                    'n tracking.',
                'access' => 'Dashboard > Add block > My Students Progress',
            ],
            // Time Saving Plugins ($100 AUD one-time purchase).
            [
                'name' => 'Quiz Access Rule',
                'component' => 'quizaccess_aigrader',
                'plugin_type' => 'quizaccess',
                'plugin_name' => 'aigrader',
                'icon' => 'settings',
                'category' => 'enrolment',
                'description' => 'Required dependency for AI Essay Grader. Enables AI grading settings in quiz configuration.',
                'access' => 'Auto-installed with AI Essay Grader',
            ],
            [
                'name' => 'Course Prerequisite',
                'component' => 'enrol_prerequisite',
                'plugin_type' => 'enrol',
                'plugin_name' => 'prerequisite',
                'icon' => 'book',
                'category' => 'enrolment',
                'description' => 'Gatekeeper enrolment that suspends students until they complete prerequisite courses.',
                'access' => 'Course > Enrolment methods > Add > Prerequisite',
            ],
            [
                'name' => 'Video Compress',
                'component' => 'local_videocompress',
                'plugin_type' => 'local',
                'plugin_name' => 'videocompress',
                'icon' => 'film',
                'category' => 'media_storage',
                'description' => 'Automatic video compression for file uploads. Reduces storage and improves playback.',
                'access' => 'Site admin > Plugins > Local plugins > Video Compress',
            ],
            [
                'name' => 'SCORM Compress',
                'component' => 'local_scormcompress',
                'plugin_type' => 'local',
                'plugin_name' => 'scormcompress',
                'icon' => 'archive',
                'category' => 'media_storage',
                'description' => 'Automatic SCORM package compression on upload. Reduces file size and speeds up delivery.',
                'access' => 'Site admin > Plugins > Local plugins > SCORM Compress',
            ],
            [
                'name' => 'Media Optimiser',
                'component' => 'local_mediaoptimiser',
                'plugin_type' => 'local',
                'plugin_name' => 'mediaoptimiser',
                'icon' => 'hard-drive',
                'category' => 'media_storage',
                'description' => 'Scan your Moodle file store for oversized images, duplicate files, unused backups, and unoptimi' .
                    'sed video — with impact scores and fix recommendations.',
                'access' => 'Site admin > Local plugins > Media Optimiser > Dashboard',
            ],
            [
                'name' => 'AI Course Format',
                'component' => 'format_aicourse',
                'plugin_type' => 'format',
                'plugin_name' => 'aicourse',
                'icon' => 'book-open',
                'category' => 'ai_content',
                'description' => 'Modern course format with world-class AI Tutor that adapts to activity context, assignment-safe' .
                    ' mode, and guided practice.',
                'access' => 'Course > Settings > Course format > AI Course',
            ],
            [
                'name' => 'Groups Management',
                'component' => 'local_groupmanager',
                'plugin_type' => 'local',
                'plugin_name' => 'groupmanager',
                'icon' => 'users',
                'category' => 'enrolment',
                'credits_required' => 1000,
                'description' => 'Cohort-based time-controlled access with intake groups, grace periods, and AVETMISS complianc' .
                    'e reports.',
                'access' => 'Course > Users > Groups Management',
            ],
            [
                'name' => 'Groups Availability Condition',
                'component' => 'availability_groupmanager',
                'plugin_type' => 'availability',
                'plugin_name' => 'groupmanager',
                'icon' => 'lock',
                'category' => 'enrolment',
                'group' => 'Groups Management',
                'credits_required' => 1000,
                'description' => 'Availability restriction based on group intake dates. Activities auto-hide before/after acces' .
                    's windows.',
                'access' => 'Activity > Restrict access > Add restriction > Group Intake Dates',
            ],
            [
                'name' => 'Course Version Control',
                'component' => 'local_courseversion',
                'plugin_type' => 'local',
                'plugin_name' => 'courseversion',
                'icon' => 'book',
                'category' => 'security',
                'credits_required' => 1000,
                'description' => 'Version management for course materials with auto-lock protection, audit trails, and TAS in' .
                    'tegration.',
                'access' => 'Course > Course Version Control',
            ],
            [
                'name' => 'Activity Navigation',
                'component' => 'local_activitynav',
                'plugin_type' => 'local',
                'plugin_name' => 'activitynav',
                'icon' => 'navigation',
                'category' => 'training',
                'description' => 'Streamlined navigation between activities with breadcrumbs, previous/next buttons, and progress' .
                    ' tracking.',
                'access' => 'Site admin > Plugins > Local plugins > Activity Navigation',
            ],
            [
                'name' => 'Change Site Font',
                'component' => 'local_sitefont',
                'plugin_type' => 'local',
                'plugin_name' => 'sitefont',
                'icon' => 'palette',
                'category' => 'branding',
                'credits_required' => 1000,
                'description' => 'Global font customisation with 10 Google Fonts, comprehensive CSS overrides, and FontAwesome pr' .
                    'eservation.',
                'access' => 'Site admin > Appearance > Change Site Font',
            ],
            [
                'name' => 'Cohort Branding',
                'component' => 'local_cohortbranding',
                'plugin_type' => 'local',
                'plugin_name' => 'cohortbranding',
                'icon' => 'palette',
                'category' => 'branding',
                'credits_required' => 1000,
                'description' => 'Multi-tenant branding per cohort with logos, colours, fonts, and priority system for multi-coh' .
                    'ort users.',
                'access' => 'Site admin > Appearance > Cohort Branding',
            ],
            [
                'name' => 'Assignment Benchmarks',
                'component' => 'gradingform_benchmarks',
                'plugin_type' => 'gradingform',
                'plugin_name' => 'benchmarks',
                'icon' => 'clipboard-check',
                'category' => 'ai_grading',
                'credits_required' => 1000,
                'description' => 'Competency-based checklist grading with automatic grade calculation and evidence requirements.',
                'access' => 'Assignment > Grading method > Benchmarks',
            ],
            [
                'name' => 'Certificate Pro',
                'component' => 'mod_certificatepro',
                'plugin_type' => 'mod',
                'plugin_name' => 'certificatepro',
                'icon' => 'award',
                'category' => 'other',
                'description' => 'Testing-stage certificate activity tracked by the LMS Labs release pipeline.',
                'access' => 'Course > Add an activity or resource > Certificate Pro',
                // Stated explicitly rather than left to the "testing-stage" description
                // heuristic, which a reworded description would silently defeat.
                'status' => 'testing',
            ],
            [
                'name' => 'Simple 2FA & SSO',
                'component' => 'auth_simple2fa',
                'plugin_type' => 'auth',
                'plugin_name' => 'simple2fa',
                'icon' => 'shield',
                'category' => 'security',
                'credits_required' => 1000,
                'description' => 'Two-factor authentication with Google Authenticator TOTP for admin accounts. Includes built-in ' .
                    'OAuth2/OIDC SSO.',
                'access' => 'Site admin > Plugins > Authentication > Simple 2FA',
            ],
            [
                'name' => 'Group Membership Limit',
                'component' => 'local_groupcap',
                'plugin_type' => 'local',
                'plugin_name' => 'groupcap',
                'icon' => 'users',
                'category' => 'enrolment',
                'credits_required' => 1000,
                'description' => 'Enforce maximum group size. Blocks self-enrolment and manual additions when a group reaches its' .
                    ' member limit.',
                'access' => 'Course > Participants > Groups > Edit Group',
            ],
            [
                'name' => 'Payment Unlock Assignment',
                'component' => 'local_paymentunlockassign',
                'plugin_type' => 'local',
                'plugin_name' => 'paymentunlockassign',
                'icon' => 'lock',
                'category' => 'enrolment',
                'credits_required' => 1000,
                'description' => 'Monetise late assignment submissions. Students pay via Stripe to reopen locked assignments for ' .
                    'another attempt. Configurable fees per attempt, admin overrides, revenue reporting, and full audit log.',
                'access' => 'Site admin > Plugins > Local plugins > Payment Unlock Assignment',
                'goto_url' => '/local/paymentunlockassign/manage.php',
            ],
            [
                'name' => 'Essay Guard',
                'component' => 'plagiarism_essayguard',
                'plugin_type' => 'plagiarism',
                'plugin_name' => 'essayguard',
                'icon' => 'shield',
                'category' => 'integrity',
                'credits_required' => 5000,
                'description' => 'Live writing-process analysis for academic integrity. Monitors keystroke dynamics, paste events' .
                    ', burst typing, pause patterns, and revision behaviour — gives teachers a Low / Medium / High risk badge on ' .
                        'every submission.',
                'access' => 'Site admin > Plugins > Plagiarism prevention > Essay Guard',
                'goto_url' => '/plagiarism/essayguard/settings.php',
            ],
            [
                'name' => 'DocGuard',
                'component' => 'plagiarism_docguard',
                'plugin_type' => 'plagiarism',
                'plugin_name' => 'docguard',
                'icon' => 'file-search',
                'category' => 'integrity',
                'credits_required' => 5000,
                'description' => 'AI and plagiarism detection for PDF and Word document submissions. Extracts text, detects quest' .
                    'ion/answer sections, and scores each answer across 12 signals — producing a Low / Medium / High risk badge' .
                        ' per file.',
                'access' => 'Site admin > Plugins > Plagiarism prevention > DocGuard',
                'goto_url' => '/plagiarism/docguard/settings.php',
            ],
            [
                'name' => 'Course Availability Delay',
                'component' => 'local_courseavailabilitydelay',
                'plugin_type' => 'local',
                'plugin_name' => 'courseavailabilitydelay',
                'icon' => 'clock',
                'category' => 'training',
                'credits_required' => 1000,
                'description' => 'Delay when enrolled courses appear on a student\'s My Courses dashboard. Set per-course delays ' .
                    '(days since enrolment) or fixed unlock dates, with per-user overrides and bulk CSV import.',
                'access' => 'Site admin > Plugins > Local plugins > Course Availability Delay',
                'goto_url' => '/local/courseavailabilitydelay/manage.php',
            ],
            [
                'name' => 'Paddle Payment Gateway',
                'component' => 'paygw_paddle',
                'plugin_type' => 'paygw',
                'plugin_name' => 'paddle',
                'icon' => 'credit-card',
                'category' => 'payments',
                'description' => 'Paddle as Merchant of Record for global tax-compliant payments. Hosted checkout, automatic enro' .
                    'lment, 30-currency support.',
                'access' => 'Site admin > Plugins > Payment gateways > Paddle',
                'goto_url' => '/payment/gateway/paddle/admin/reports.php',
            ],
            [
                'name' => 'Training Pathways',
                'component' => 'local_trainingpathways',
                'plugin_type' => 'local',
                'plugin_name' => 'trainingpathways',
                'icon' => 'map',
                'category' => 'training',
                'description' => 'Visual employee training journey management system. Create multi-stage training pathways, assig' .
                    'n employees, and track task completion with real-time progress.',
                'access' => 'Site admin > Plugins > Local plugins > Training Pathways',
                'goto_url' => '/local/trainingpathways/manage.php',
            ],
            [
                'name' => 'Training Plan',
                'component' => 'block_trainingplan',
                'plugin_type' => 'block',
                'plugin_name' => 'trainingplan',
                'icon' => 'calendar-clock',
                'category' => 'training',
                'credits_required' => 5000,
                'description' => 'Weekly overdue digest to trainers. One email per trainer listing every learner whose training p' .
                    'lan has fallen behind — each shown against the specific course they are stuck on. Settings → Notification Se' .
                        'ttings to control the kill switch, test recipients, and overdue cutoff.',
                'access' => 'Site admin > Plugins > Blocks > Training Plan > Notification Settings section',
                'goto_url' => '/admin/settings.php?section=blocksettingtrainingplan',
            ],
            [
                'name' => 'Workshop Scheduler',
                'component' => 'local_workshops',
                'plugin_type' => 'local',
                'plugin_name' => 'workshops',
                'icon' => 'calendar',
                'category' => 'training',
                'description' => 'Complete workshop logistics and bookings management. Schedule face-to-face, webinar and hybrid ' .
                    'workshops, manage participant bookings, track checklists, and upload documents.',
                'access' => 'Site admin > Workshops > Manage Workshops',
                'goto_url' => '/local/workshops/index.php',
            ],
            [
                'name' => 'AI Quiz Remedial Learning',
                'component' => 'local_aiquizremedial',
                'plugin_type' => 'local',
                'plugin_name' => 'aiquizremedial',
                'icon' => 'refresh-cw',
                'category' => 'ai_grading',
                'description' => 'Auto-generates AI explanations with optional voiceover and images for wrong quiz answers. Dynam' .
                    'ic credit system.',
                'access' => 'Site admin > Plugins > Local plugins > AI Quiz Remedial Learning',
                'goto_url' => '/local/aiquizremedial/index.php',
            ],
            [
                'name' => 'AI Login Designer',
                'component' => 'local_ailogin',
                'plugin_type' => 'local',
                'plugin_name' => 'ailogin',
                'icon' => 'layout',
                'category' => 'ai_ux',
                'description' => 'AI-powered branded login page designer. Generates a custom colour scheme and CSS via AI (25 cre' .
                    'dits per generation). Animated favicon particles, logo upload, slogan and subtext customisation.',
                'access' => 'Site admin > Plugins > Local plugins > AI Login Designer',
                'goto_url' => '/local/ailogin/admin.php',
            ],
            [
                'name' => 'Site Down Alert',
                'component' => 'local_downalert',
                'plugin_type' => 'local',
                'plugin_name' => 'downalert',
                'icon' => 'zap',
                'category' => 'comms',
                'description' => 'Multi-site uptime monitoring with deep server diagnostics. Checks HTTP, SSL, DNS, disk, CPU, me' .
                    'mory, MySQL, Redis, PHP-FPM, and Moodle health — then emails a root-cause alert when any site goes down.',
                'access' => 'Site admin > Plugins > Local plugins > Site Down Alert',
                'goto_url' => '/local/downalert/report.php',
            ],
            [
                'name' => 'LMS Home Page',
                'component' => 'local_lmshomepage',
                'plugin_type' => 'local',
                'plugin_name' => 'lmshomepage',
                'icon' => 'home',
                'category' => 'branding',
                'description' => 'Customise the Moodle home page layout and content for a professional landing experience.',
                'access' => 'Site admin > Plugins > Local plugins > LMS Home Page',
                'goto_url' => '/admin/settings.php?section=local_lmshomepage',
            ],
            [
                'name' => 'Student Email Manager',
                'component' => 'local_studentemail',
                'plugin_type' => 'local',
                'plugin_name' => 'studentemail',
                'icon' => 'mail',
                'category' => 'comms',
                'description' => 'Auto-provisions and manages cPanel email accounts for every enrolled student. Dashboard with li' .
                    've stats, bulk actions, and a student "My Email" portal showing credentials and Roundcube webmail link.',
                'access' => 'Site admin > Plugins > Local plugins > Student Email Manager',
                'goto_url' => '/local/studentemail/dashboard.php',
            ],
            [
                'name' => 'Student Email IMAP Auth',
                'component' => 'auth_studentemail',
                'plugin_type' => 'auth',
                'plugin_name' => 'studentemail',
                'icon' => 'mail',
                'category' => 'comms',
                'description' => 'Lets students log into Moodle using their provisioned college email address and password, verif' .
                    'ied in real-time via IMAP. Works alongside Student Email Manager.',
                'access' => 'Site admin > Plugins > Authentication > Student Email IMAP Auth',
            ],
            [
                'name' => 'Workshop Attendance Condition',
                'component' => 'availability_workshopattendance',
                'plugin_type' => 'availability',
                'plugin_name' => 'workshopattendance',
                'icon' => 'box',
                'category' => 'other',
                'description' => 'Workshop Attendance Condition.',
                'access' => 'Site admin > Plugins',
            ],
            [
                'name' => 'RTO Compliance Dashboard',
                'component' => 'block_rtocompliance',
                'plugin_type' => 'block',
                'plugin_name' => 'rtocompliance',
                'icon' => 'box',
                'category' => 'ai_rto',
                'description' => 'RTO Compliance Dashboard.',
                'access' => 'Site admin > Plugins',
            ],
            [
                'name' => 'Student Activity & Participation Evidence',
                'component' => 'block_studentactivity',
                'plugin_type' => 'block',
                'plugin_name' => 'studentactivity',
                'icon' => 'box',
                'category' => 'other',
                'description' => 'Student Activity & Participation Evidence.',
                'access' => 'Site admin > Plugins',
            ],
            [
                'name' => 'Training Pathways Block',
                'component' => 'block_trainingpathways',
                'plugin_type' => 'block',
                'plugin_name' => 'trainingpathways',
                'icon' => 'box',
                'category' => 'other',
                'description' => 'Training Pathways Block.',
                'access' => 'Site admin > Plugins',
            ],
            [
                'name' => 'Prerequisite 2 Enrolment',
                'component' => 'enrol_prereq2',
                'plugin_type' => 'enrol',
                'plugin_name' => 'prereq2',
                'icon' => 'box',
                'category' => 'other',
                'description' => 'Prerequisite 2 Enrolment.',
                'access' => 'Site admin > Plugins',
            ],
            [
                'name' => 'AI Grader Tester',
                'component' => 'local_aitester',
                'plugin_type' => 'local',
                'plugin_name' => 'aitester',
                'icon' => 'box',
                'category' => 'other',
                'description' => 'AI Grader Tester.',
                'access' => 'Site admin > Plugins',
            ],
            [
                'name' => 'Apache / WAF 403 Monitor',
                'component' => 'local_apachemon',
                'plugin_type' => 'local',
                'plugin_name' => 'apachemon',
                'icon' => 'box',
                'category' => 'other',
                'description' => 'Apache / WAF 403 Monitor.',
                'access' => 'Site admin > Plugins',
            ],
            [
                'name' => 'Campion Education Integration',
                'component' => 'local_campion',
                'plugin_type' => 'local',
                'plugin_name' => 'campion',
                'icon' => 'box',
                'category' => 'other',
                'description' => 'Campion Education Integration.',
                'access' => 'Site admin > Plugins',
            ],
            [
                'name' => 'Completion Auto-Suspend',
                'component' => 'local_completionsuspend',
                'plugin_type' => 'local',
                'plugin_name' => 'completionsuspend',
                'icon' => 'box',
                'category' => 'other',
                'description' => 'Completion Auto-Suspend.',
                'access' => 'Site admin > Plugins',
            ],
            [
                'name' => 'Custom Pages',
                'component' => 'local_custompage',
                'plugin_type' => 'local',
                'plugin_name' => 'custompage',
                'icon' => 'box',
                'category' => 'other',
                'description' => 'Custom Pages.',
                'access' => 'Site admin > Plugins',
            ],
            [
                'name' => 'AI Essay Maker (Legacy)',
                'component' => 'local_essaymaker',
                'plugin_type' => 'local',
                'plugin_name' => 'essaymaker',
                'icon' => 'box',
                'category' => 'other',
                'description' => 'AI Essay Maker (Legacy).',
                'access' => 'Site admin > Plugins',
            ],
            [
                'name' => 'Course Recertification',
                'component' => 'local_recertify',
                'plugin_type' => 'local',
                'plugin_name' => 'recertify',
                'icon' => 'box',
                'category' => 'other',
                'description' => 'Course Recertification.',
                'access' => 'Site admin > Plugins',
            ],
            [
                'name' => 'Student Evidence Export',
                'component' => 'local_student_export',
                'plugin_type' => 'local',
                'plugin_name' => 'student_export',
                'icon' => 'box',
                'category' => 'other',
                'description' => 'Student Evidence Export.',
                'access' => 'Site admin > Plugins',
            ],
            [
                'name' => 'AI Training Simulation',
                'component' => 'mod_aitrainingsim',
                'plugin_type' => 'mod',
                'plugin_name' => 'aitrainingsim',
                'icon' => 'box',
                'category' => 'other',
                'description' => 'AI Training Simulation.',
                'access' => 'Site admin > Plugins',
            ],
            [
                'name' => 'Workplace Task',
                'component' => 'mod_workplacetask',
                'plugin_type' => 'mod',
                'plugin_name' => 'workplacetask',
                'icon' => 'box',
                'category' => 'other',
                'description' => 'Workplace Task.',
                'access' => 'Site admin > Plugins',
            ],
            [
                'name' => 'Wilkinson Coutts Question Behaviour',
                'component' => 'qbehaviour_wilkinsoncoutts',
                'plugin_type' => 'qbehaviour',
                'plugin_name' => 'wilkinsoncoutts',
                'icon' => 'box',
                'category' => 'other',
                'description' => 'Wilkinson Coutts Question Behaviour.',
                'access' => 'Site admin > Plugins',
            ],
            // Testing-stage builds. They stay in the registry so the release pipeline's
            // cross-plugin completeness check can see them, but carry 'status' => 'testing'
            // so payload.php drops them from the catalogue unless the site already runs one
            // (see block_aiplugin_nav_payload::is_testing_stage).
            [
                'name' => 'AI Quiz',
                'component' => 'mod_aiquiz',
                'plugin_type' => 'mod',
                'plugin_name' => 'aiquiz',
                'icon' => 'help-circle',
                'category' => 'ai',
                'description' => 'AI-powered quiz activity with 5 question types, webcam proctoring, security features, and ' .
                'detailed analytics.',
                'access' => 'Course > Add activity > AI Quiz',
                'status' => 'testing',
            ],
            [
                'name' => 'AI PDF Assignment Grader',
                'component' => 'assignfeedback_aipdf',
                'plugin_type' => 'assignfeedback',
                'plugin_name' => 'aipdf',
                'icon' => 'file-text',
                'category' => 'ai',
                'description' => 'AI-powered PDF assignment grading with rubric-based feedback and inline annotations.',
                'access' => 'Assignment > Feedback types > AI PDF Grader',
                'status' => 'testing',
            ],
            [
                'name' => 'AI Webcam Proctoring',
                'component' => 'quizaccess_webcamproctor',
                'plugin_type' => 'quizaccess',
                'plugin_name' => 'webcamproctor',
                'icon' => 'eye',
                'category' => 'ai',
                'description' => 'Webcam monitoring during quizzes with periodic photo capture for exam integrity.',
                'access' => 'Quiz > Settings > Extra restrictions',
                'status' => 'testing',
            ],
            [
                'name' => 'AI Practical Assessment',
                'component' => 'mod_practicalassessment',
                'plugin_type' => 'mod',
                'plugin_name' => 'practicalassessment',
                'icon' => 'clipboard-check',
                'category' => 'ai',
                'description' => 'Workplace practical assessments with skills checklists, supervisor verification, and ' .
                'competency mapping.',
                'access' => 'Course > Add activity > AI Practical Assessment',
                'status' => 'testing',
            ],
            [
                'name' => 'AI Video Conference',
                'component' => 'mod_aivideoconf',
                'plugin_type' => 'mod',
                'plugin_name' => 'aivideoconf',
                'icon' => 'video-cam',
                'category' => 'ai',
                'description' => 'HD video conferencing with AI transcription, session recording, and attendance tracking.',
                'access' => 'Course > Add activity > AI Video Conference',
                'status' => 'testing',
            ],
            [
                'name' => 'BigBlueButton (Moodle 4.x)',
                'component' => 'mod_bigbluebuttonbn',
                'plugin_type' => 'mod',
                'plugin_name' => 'bigbluebuttonbn',
                'icon' => 'video',
                'category' => 'utility',
                'description' => 'HD video conferencing with breakout rooms, polling, and session recording. Moodle 4.x ' .
                'compatible build.',
                'access' => 'Course > Add activity > BigBlueButton',
                'status' => 'testing',
            ],
            [
                'name' => 'BigBlueButton Recordings (Moodle 4.x)',
                'component' => 'mod_recordingsbn',
                'plugin_type' => 'mod',
                'plugin_name' => 'recordingsbn',
                'icon' => 'play-circle',
                'category' => 'utility',
                'description' => 'Companion plugin for BigBlueButton. Browse, manage, and share session recordings within ' .
                'Moodle courses.',
                'access' => 'Course > Add activity > BigBlueButton Recordings',
                'status' => 'testing',
            ],
            [
                'name' => 'Speed',
                'component' => 'report_performanceintel',
                'plugin_type' => 'report',
                'plugin_name' => 'performanceintel',
                'icon' => 'zap',
                'category' => 'utility',
                'description' => 'Performance Intelligence for real-time monitoring of user experience, bottleneck ' .
                'identification, and actionable fixes.',
                'access' => 'Site admin > Reports > Speed (Performance Intelligence)',
                'status' => 'testing',
            ],
        ];
    }

    /**
     * Read plugin version.php ONCE and return both release string and numeric version.
     * Static cache prevents reading the same file twice per request.
     *
     * @param string $plugintype Plugin type (mod, local, block, etc.)
     * @param string $pluginname Plugin folder name.
     * @return array ['release' => string|null, 'version' => string]
     */
    private function get_plugin_version_data($plugintype, $pluginname) {
        global $CFG;
        static $versioncache = [];
        $key = $plugintype . '_' . $pluginname;
        if (isset($versioncache[$key])) {
            return $versioncache[$key];
        }
        $typedirs = [
            'quiz' => 'mod/quiz/report',
            'mod' => 'mod',
            'local' => 'local',
            'block' => 'blocks',
            'quizaccess' => 'mod/quiz/accessrule',
            'enrol' => 'enrol',
            'format' => 'course/format',
            'availability' => 'availability/condition',
            'gradingform' => 'grade/grading/form',
            'paygw' => 'payment/gateway',
            'assignfeedback' => 'mod/assign/feedback',
        ];
        $dir = isset($typedirs[$plugintype]) ? $typedirs[$plugintype] : $plugintype;
        $versionfile = $CFG->dirroot . '/' . $dir . '/' . $pluginname . '/version.php';
        if (!file_exists($versionfile)) {
            $versioncache[$key] = ['release' => null, 'version' => '0'];
            return $versioncache[$key];
        }
        $plugin = new stdClass();
        include($versionfile);
        $versioncache[$key] = [
            'release' => isset($plugin->release) ? $plugin->release : null,
            'version' => isset($plugin->version) ? (string)$plugin->version : '0',
        ];
        return $versioncache[$key];
    }

    /**
     * Get installed plugin version from version.php.
     *
     * @param string $plugintype The Moodle plugin type, e.g. mod or block.
     * @param string $pluginname The plugin's short name.
     * @return string The release string, or an empty string when not installed.
     */
    public function get_plugin_version($plugintype, $pluginname) {
        return $this->get_plugin_version_data($plugintype, $pluginname)['release'];
    }

    /**
     * Get the installed numeric version of a plugin from its version.php.
     *
     * @param string $plugintype The Moodle plugin type, e.g. mod or block.
     * @param string $pluginname The plugin's short name.
     * @return string|int The numeric version, or an empty string when not installed.
     */
    public function get_plugin_numeric_version($plugintype, $pluginname) {
        return $this->get_plugin_version_data($plugintype, $pluginname)['version'];
    }
}
