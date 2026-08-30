<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * AI Plugin Navigation block.
 *
 * @package    block_aiplugin_nav
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- Block class files require direct-access protection.
defined('MOODLE_INTERNAL') || die();

// Long embedded markup, SQL, and dormant registry examples cannot be wrapped without changing their content.
// phpcs:disable moodle.Files.LineLength,moodle.WhiteSpace.WhiteSpaceInStrings.EndLine,Squiz.PHP.CommentedOutCode.Found,moodle.PHP.ForbiddenGlobalUse.BadGlobal,moodle.Commenting.InlineComment
/**
 * AI Plugin Navigation block.
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
     * @param string $plugintype Plugin type.
     * @param string $pluginname Plugin name.
     * @return bool Whether the plugin is installed.
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
    private function user_has_role_shortname($userid, $shortname) {
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
     * Debug function to check credits retrieval step by step.
     * Returns an array with debug info for console logging.
     */
    private function debug_credits_check() {
        global $CFG;

        $debug = [
            'step1_isAdmin' => has_capability('moodle/site:config', context_system::instance()),
            'step2_aiconfigLibExists' => false,
            'step3_getSiteIdFunctionExists' => false,
            'step4_getApiKeyFunctionExists' => false,
            'step5_siteId' => '',
            'step6_apiKey' => '',
            'step7_apiCallMade' => false,
            'step8_httpCode' => 0,
            'step9_response' => '',
            'step10_finalResult' => null,
        ];

        $aiconfiglib = $CFG->dirroot . '/local/aiconfig/lib.php';
        $debug['step2_aiconfigLibExists'] = file_exists($aiconfiglib);

        if ($debug['step2_aiconfigLibExists']) {
            require_once($aiconfiglib);
        }

        $debug['step3_getSiteIdFunctionExists'] = function_exists('local_aiconfig_get_siteid');
        $debug['step4_getApiKeyFunctionExists'] = function_exists('local_aiconfig_get_apikey');

        if ($debug['step3_getSiteIdFunctionExists']) {
            $debug['step5_siteId'] = trim(local_aiconfig_get_siteid('block_aiplugin_nav') ?? '');
        }
        if ($debug['step4_getApiKeyFunctionExists']) {
            $rawkey = trim(local_aiconfig_get_apikey('block_aiplugin_nav') ?? '');
            $debug['step6_apiKey'] = !empty($rawkey) ? substr($rawkey, 0, 8) . '...' : '(empty)';
        }

        if (!empty($debug['step5_siteId']) && $debug['step6_apiKey'] !== '(empty)') {
            $debug['step7_apiCallMade'] = true;
            $siteid = $debug['step5_siteId'];
            $apikey = trim(local_aiconfig_get_apikey('block_aiplugin_nav') ?? '');

            // Multi-endpoint fallback: Replit first (always reachable from Vultr IPs),
            // Lms-labs.com second. essaygraderai.app removed — legacy dead domain.
            $creditbases = [
                'https://ai-grader-site-nct185.replit.app',
                'https://lms-labs.com',
            ];
            $qs = '?siteId=' . rawurlencode($siteid) . '&apiKey=' . rawurlencode($apikey);
            $debug['step7b_url'] = $creditbases[0] . '/api/credits' . $qs;

            \core\session\manager::write_close();
            require_once($CFG->libdir . '/filelib.php');
            $response = false;
            $httpcode = 0;
            foreach ($creditbases as $base) {
                $curl = new \curl();
                $response = $curl->get($base . '/api/credits' . $qs, [], [
                    'CURLOPT_TIMEOUT'        => 8,
                    'CURLOPT_CONNECTTIMEOUT' => 4,
                    'CURLOPT_HTTPHEADER'     => ['Accept: application/json'],
                ]);
                $httpcode = (int)($curl->info['http_code'] ?? 0);
                if ($httpcode === 200 && !empty($response)) {
                    break;
                }
            }
            $debug['step8_httpCode'] = $httpcode;
            $debug['step8b_curlError'] = $curl->error ?? '';
            $debug['step8c_effectiveUrl'] = $curl->info['url'] ?? '';

            $debug['step9_response'] = substr($response, 0, 200);

            $data = json_decode($response, true);
            if ($data && isset($data['credits'])) {
                $debug['step10_finalResult'] = $data['credits'];
            }
        }

        return $debug;
    }

    /**
     * Fetch credit balance from lms-labs.com API.
     * Uses credentials from AI Grader Central Config plugin.
     *
     * @return array|null Credits info array or null if unavailable.
     */
    private function get_credits_balance() {
        global $CFG;

        // Try to load the central config library.
        $aiconfiglib = $CFG->dirroot . '/local/aiconfig/lib.php';
        if (file_exists($aiconfiglib)) {
            require_once($aiconfiglib);
        }

        // Get credentials from central config.
        $siteid = '';
        $apikey = '';

        if (function_exists('local_aiconfig_get_siteid')) {
            $siteid = trim(local_aiconfig_get_siteid('block_aiplugin_nav') ?? '');
        }
        if (function_exists('local_aiconfig_get_apikey')) {
            $apikey = trim(local_aiconfig_get_apikey('block_aiplugin_nav') ?? '');
        }

        // If no credentials, return null.
        if (empty($siteid) || empty($apikey)) {
            return null;
        }

        // Make API request to fetch credits.
        // Multi-endpoint fallback: Replit first (always reachable from Vultr/datacenter IPs),
        // Lms-labs.com second. essaygraderai.app removed — legacy dead domain.
        $creditbases = [
            'https://ai-grader-site-nct185.replit.app',
            'https://lms-labs.com',
        ];
        $qs = '?siteId=' . rawurlencode($siteid) . '&apiKey=' . rawurlencode($apikey);

        // FIX-NAV-SESSION-LOCK (v2.3.56): Release PHP session lock before HTTP call.
        \core\session\manager::write_close();
        require_once($CFG->libdir . '/filelib.php');
        $response = false;
        $httpcode = 0;
        foreach ($creditbases as $base) {
            $curl = new \curl();
            $response = $curl->get($base . '/api/credits' . $qs, [], [
                'CURLOPT_TIMEOUT'        => 5,
                'CURLOPT_CONNECTTIMEOUT' => 3,
                'CURLOPT_HTTPHEADER'     => ['Accept: application/json'],
            ]);
            $httpcode = (int)($curl->info['http_code'] ?? 0);
            if ($httpcode === 200 && !empty($response)) {
                break;
            }
        }

        if ($httpcode !== 200 || empty($response)) {
            return null;
        }

        $data = json_decode($response, true);
        if (!$data || !isset($data['credits'])) {
            return null;
        }

        return $data;
    }

    /**
     * Get color class for credits badge based on amount.
     *
     * @param int|string $credits Credit amount or "unlimited".
     * @return string CSS color class.
     */
    private function get_credits_color_class($credits) {
        if ($credits === 'unlimited' || $credits === -1) {
            return 'ainav-credits-green';
        }
        $amount = (int) $credits;
        if ($amount < 100) {
            return 'ainav-credits-red';
        } else if ($amount < 1000) {
            return 'ainav-credits-orange';
        }
        return 'ainav-credits-green';
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
            // ===== AI PLUGINS (Credit-Based) =====
            // Note: Plugins with only Site ID/API Key don't have settings_url.
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
            // Some sites still have it installed from the transition period with broken class.
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
            // TESTING - Hidden until ready.
            // 'mod_aiquiz' => array(
            // 'name' => 'AI Quiz',
            // 'plugin_type' => 'mod',
            // 'plugin_name' => 'aiquiz',
            // 'settings_url' => '/admin/settings.php?section=modsettingaiquiz',
            // 'icon' => 'help-circle',
            // 'category' => 'ai_credit',
            // ),
            // TESTING - Hidden until ready.
            // 'mod_practicalassessment' => array(
            // 'name' => 'AI Practical Assessment',
            // 'plugin_type' => 'mod',
            // 'plugin_name' => 'practicalassessment',
            // 'icon' => 'clipboard-check',
            // 'category' => 'ai_credit',
            // ),
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
            // TESTING - Hidden until ready.
            // 'quizaccess_webcamproctor' => array(
            // 'name' => 'AI Webcam Proctoring',
            // 'plugin_type' => 'quizaccess',
            // 'plugin_name' => 'webcamproctor',
            // 'report_url' => '/mod/quiz/accessrule/webcamproctor/report.php',
            // 'icon' => 'video',
            // 'category' => 'ai_credit',
            // ),
            // 'mod_aivideoconf' => array(
            // 'name' => 'AI Video Conference',
            // 'plugin_type' => 'mod',
            // 'plugin_name' => 'aivideoconf',
            // 'icon' => 'video-cam',
            // 'category' => 'ai_credit',
            // ),
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
                'aliases' => 'AI Voiceover Chirp HD voice narration',
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
                'aliases' => 'RTO AVETMISS VET compliance',
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
            // ===== BLOCKS =====
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
            // TESTING - Hidden until ready.
            // 'block_trainingmatrix' => array(
            // 'name' => 'My Training Progress',
            // 'plugin_type' => 'block',
            // 'plugin_name' => 'trainingmatrix',
            // 'icon' => 'bar-chart-2',
            // 'category' => 'block',
            // ),
            // 'block_trainingmatrix_teacher' => array(
            // 'name' => 'Staff Training Dashboard',
            // 'plugin_type' => 'block',
            // 'plugin_name' => 'trainingmatrix_teacher',
            // 'icon' => 'users-2',
            // 'category' => 'block',
            // ),
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
            // ===== CENTRAL CONFIG =====
            'local_aiconfig' => [
                'name' => 'AI Grader Central Config',
                'plugin_type' => 'local',
                'plugin_name' => 'aiconfig',
                'settings_url' => '/admin/settings.php?section=local_aiconfig',
                'page_url' => '/admin/settings.php?section=local_aiconfig',
                'icon' => 'settings',
                'category' => 'config',
            ],
            // ===== TIME SAVING PLUGINS (Admin) =====
            // TESTING - Hidden until ready.
            // 'local_trainingmatrix' => array(
            // 'name' => 'AI Training Matrix HCM',
            // 'plugin_type' => 'local',
            // 'plugin_name' => 'trainingmatrix',
            // 'settings_url' => '/admin/settings.php?section=local_trainingmatrix',
            // 'page_url' => '/local/trainingmatrix/index.php',
            // 'icon' => 'users-2',
            // 'category' => 'admin',
            // ),
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
            'local_recertify' => [
                'name' => 'Course Recertification',
                'plugin_type' => 'local',
                'plugin_name' => 'recertify',
                'settings_url' => '/admin/settings.php?section=local_recertify_settings',
                'page_url' => '/local/recertify/index.php',
                'report_url' => '/local/recertify/index.php',
                'icon' => 'refresh-cw',
                'category' => 'training',
                'aliases' => 'recertify recertification renew certification',
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
                'aliases' => 'completion suspend auto suspend users',
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
            // TESTING - Hidden until ready.
            // 'assignfeedback_aipdf' => array(
            // 'name' => 'AI PDF Assignment Grader',
            // 'plugin_type' => 'assignfeedback',
            // 'plugin_name' => 'aipdf',
            // 'settings_url' => '/admin/settings.php?section=assignfeedback_aipdf',
            // 'icon' => 'file-text',
            // 'category' => 'ai_credit',
            // ),
            // TESTING - Hidden until ready.
            // 'report_performanceintel' => array(
            // 'name' => 'Speed (Performance Intelligence)',
            // 'plugin_type' => 'report',
            // 'plugin_name' => 'performanceintel',
            // 'settings_url' => '/admin/settings.php?section=report_performanceintel',
            // 'page_url' => '/report/performanceintel/index.php',
            // 'icon' => 'zap',
            // 'category' => 'utility',
            // ),
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
     * Get the navigation links registry - dynamically built from installed plugins.
     * Automatically detects installed AI Grader ecosystem plugins.
     */
    public function get_links_registry() {
        global $CFG;

        $registry = $this->get_master_plugin_registry();
        $settingsitems = [];
        $manageitems = [];
        $reportitems = [];

        // Build settings, manage, and reports from installed plugins.
        foreach ($registry as $component => $plugin) {
            // Check if plugin is installed.
            if (!$this->is_plugin_installed($plugin['plugin_type'], $plugin['plugin_name'])) {
                continue;
            }

            // Add settings link if available.
            if (!empty($plugin['settings_url'])) {
                $settingsitems[] = [
                    'name' => $plugin['name'],
                    'url' => $CFG->wwwroot . $plugin['settings_url'],
                    'icon' => 'settings',
                    'plugin_type' => $plugin['plugin_type'],
                    'plugin_name' => $plugin['plugin_name'],
                    'capability' => 'moodle/site:config',
                    'category' => $plugin['category'],
                    'aliases' => isset($plugin['aliases']) ? $plugin['aliases'] : '',
                ];
            }

            // Add page link to Manage section (management pages like Cohort Branding index).
            if (!empty($plugin['page_url'])) {
                $manageitems[] = [
                    'name' => $plugin['name'],
                    'url' => $CFG->wwwroot . $plugin['page_url'],
                    'icon' => $plugin['icon'],
                    'plugin_type' => $plugin['plugin_type'],
                    'plugin_name' => $plugin['plugin_name'],
                    'capability' => 'moodle/site:config',
                    'category' => $plugin['category'],
                    'aliases' => isset($plugin['aliases']) ? $plugin['aliases'] : '',
                ];
            }

            // Add report link if available.
            if (!empty($plugin['report_url'])) {
                $reportitems[] = [
                    'name' => $plugin['name'],
                    'url' => $CFG->wwwroot . $plugin['report_url'],
                    'icon' => $plugin['icon'],
                    'plugin_type' => $plugin['plugin_type'],
                    'plugin_name' => $plugin['plugin_name'],
                    'capability' => 'moodle/site:config',
                    'category' => $plugin['category'],
                    'aliases' => isset($plugin['aliases']) ? $plugin['aliases'] : '',
                ];
            }
        }

        // Group settings by category then sort alphabetically within each group.
        $configsettings = array_filter($settingsitems, function ($item) {
            return $item['category'] === 'config';
        });
        $aicats = ['ai_grading', 'ai_content', 'ai_media', 'ai_rto', 'ai_ux'];
        $admincats = ['block', 'training', 'enrolment', 'integrity', 'comms', 'branding', 'media_storage', 'security', 'reporting', 'payments'];
        $aisettings = array_filter($settingsitems, function ($item) use ($aicats) {
            return in_array($item['category'], $aicats);
        });
        $adminsettings = array_filter($settingsitems, function ($item) use ($admincats) {
            return in_array($item['category'], $admincats);
        });
        usort($aisettings, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        usort($adminsettings, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        // Prepend config items to AI settings (Central Config appears first).
        $aisettings = array_merge(array_values($configsettings), array_values($aisettings));
        usort($manageitems, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        // Inject "Manage Plagiarism Plugins" into Settings > Admin Plugins column.
        // If Essay Guard or DocGuard is installed. This page controls the global.
        // Plagiarism_use_essayguard / plagiarism_use_docguard toggles that Moodle.
        // Requires before either plugin will run on any activity.
        // Inject "Certificate Settings" for RTO Compliance into the AI settings column.
        if ($this->is_plugin_installed('local', 'rtocompliance')) {
            $aisettings[] = [
                'name'        => 'RTO Compliance — Certificate Settings',
                'url'         => $CFG->wwwroot . '/admin/settings.php?section=local_rtocompliance_certs',
                'icon'        => 'settings',
                'plugin_type' => 'local',
                'plugin_name' => 'rtocompliance',
                'capability'  => 'moodle/site:config',
                'category'    => 'ai_rto',
            ];
        }

        $hasplagiarismplugin = $this->is_plugin_installed('plagiarism', 'essayguard')
                              || $this->is_plugin_installed('plagiarism', 'docguard');
        if ($hasplagiarismplugin) {
            $adminsettings[] = [
                'name'        => 'Manage Plagiarism Plugins',
                'url'         => $CFG->wwwroot . '/admin/settings.php?section=manageplagiarismplugins',
                'icon'        => 'sliders',
                'plugin_type' => 'plagiarism',
                'plugin_name' => '',
                'capability'  => 'moodle/site:config',
                'category'    => 'integrity',
            ];
        }

        $links = [
            // Reports Section.
            'tools' => [
                'label' => get_string('ai_reports', 'block_aiplugin_nav'),
                'items' => $reportitems,
            ],
            // Settings Section (grouped by category).
            'settings' => [
                'label' => get_string('ai_settings', 'block_aiplugin_nav'),
                'items' => $settingsitems,
                'ai_items' => array_values($aisettings),
                'admin_items' => array_values($adminsettings),
            ],
            // Manage Section (management pages).
            'manage' => [
                'label' => get_string('ai_manage', 'block_aiplugin_nav'),
                'items' => $manageitems,
            ],
            // External Links.
            'external' => [
                'label' => '',
                'items' => [
                    [
                        'name' => get_string('visit_website', 'block_aiplugin_nav'),
                        'url' => 'https://lms-labs.com',
                        'icon' => 'external-link',
                        'external' => true,
                    ],
                    [
                        'name' => get_string('buy_credits', 'block_aiplugin_nav'),
                        'url' => 'https://lms-labs.com/pricing',
                        'icon' => 'credit-card',
                        'external' => true,
                    ],
                    [
                        'name' => get_string('become_affiliate', 'block_aiplugin_nav'),
                        'url' => 'https://lms-labs.com/affiliate/signup',
                        'icon' => 'users',
                        'external' => true,
                    ],
                ],
            ],
        ];

        return $links;
    }

    /**
     * Get installed plugins grouped by category for the plugins management tab.
     *
     * @return array Plugins grouped by category.
     */
    public function get_installed_plugins_by_category() {
        $registry = $this->get_master_plugin_registry();
        $categories = [
            'config'        => [],
            'ai_grading'    => [],
            'ai_content'    => [],
            'ai_media'      => [],
            'ai_rto'        => [],
            'ai_ux'         => [],
            'block'         => [],
            'training'      => [],
            'enrolment'     => [],
            'integrity'     => [],
            'comms'         => [],
            'branding'      => [],
            'media_storage' => [],
            'security'      => [],
            'reporting'     => [],
            'payments'      => [],
        ];

        foreach ($registry as $component => $plugin) {
            if ($this->is_plugin_installed($plugin['plugin_type'], $plugin['plugin_name'])) {
                $plugin['component'] = $component;
                $plugin['installed'] = true;
                $categories[$plugin['category']][] = $plugin;
            }
        }

        return $categories;
    }

    /**
     * Get SVG icon markup.
     *
     * @param string $icon Icon identifier.
     * @return string SVG markup.
     */
    private function get_icon_svg($icon) {
        $icons = [
            'search' => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
            'headset' => '<path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/>',
            'file-text' => '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/>',
            'video' => '<path d="m22 8-6 4 6 4V8Z"/><rect x="2" y="6" width="14" height="12" rx="2" ry="2"/>',
            'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
            'external-link' => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>',
            'credit-card' => '<rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
            'chevron-down' => '<polyline points="6 9 12 15 18 9"/>',
            'pen-tool' => '<path d="m12 19 7-7 3 3-7 7-3-3z"/><path d="m18 13-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/><path d="m2 2 7.586 7.586"/><circle cx="11" cy="11" r="2"/>',
            'book-open' => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>',
            'check-square' => '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
            'clipboard-check' => '<rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/>',
            'user-check' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><polyline points="16 11 18 13 22 9"/>',
            'help-circle' => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
            'edit-3' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>',
            'video-cam' => '<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>',
            'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
            'sliders' => '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
            'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
            'users-2' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'bar-chart-2' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
            'palette' => '<circle cx="13.5" cy="6.5" r=".5"/><circle cx="17.5" cy="10.5" r=".5"/><circle cx="8.5" cy="7.5" r=".5"/><circle cx="6.5" cy="12.5" r=".5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.555C21.965 6.012 17.461 2 12 2z"/>',
            'layout-dashboard' => '<rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/>',
            'graduation-cap' => '<path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c0 2 2 3 6 3s6-1 6-3v-5"/>',
            'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'calendar-icon' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
            'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
            'calendar-clock' => '<path d="M21 7.5V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h3.5"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h5"/><circle cx="18" cy="18" r="4"/><path d="M18 16v2l1 1"/>',
            'message-square' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
            'folder' => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
            'settings-2' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
            'plus' => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
            'x' => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
            'trash-2' => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>',
            'link' => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
            'home' => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
            'star' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
            'heart' => '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>',
            'bookmark' => '<path d="m19 21-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>',
            'globe' => '<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
            'zap' => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
            'briefcase' => '<rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
            'mail' => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
            'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
            'map-pin' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
            'map' => '<path d="M3 7l6-3 6 3 6-3v13l-6 3-6-3-6 3V7z"/><path d="M9 4v13"/><path d="M15 7v13"/>',
            'image' => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
            'music' => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
            'film' => '<rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"/><line x1="7" y1="2" x2="7" y2="22"/><line x1="17" y1="2" x2="17" y2="22"/><line x1="2" y1="12" x2="22" y2="12"/><line x1="2" y1="7" x2="7" y2="7"/><line x1="2" y1="17" x2="7" y2="17"/><line x1="17" y1="17" x2="22" y2="17"/><line x1="17" y1="7" x2="22" y2="7"/>',
            'hard-drive' => '<line x1="22" y1="12" x2="2" y2="12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/><line x1="6" y1="16" x2="6.01" y2="16"/><line x1="10" y1="16" x2="10.01" y2="16"/>',
            'award' => '<circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/>',
            'coffee' => '<path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/><line x1="6" y1="1" x2="6" y2="4"/><line x1="10" y1="1" x2="10" y2="4"/><line x1="14" y1="1" x2="14" y2="4"/>',
            'shopping-cart' => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
            'lock' => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
            'navigation' => '<polygon points="3 11 22 2 13 21 11 13 3 11"/>',
            'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
            'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
            'layers' => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
            'table' => '<rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/>',
            'play-circle' => '<circle cx="12" cy="12" r="10"/><polygon points="10 8 16 12 10 16 10 8"/>',
            'refresh-cw' => '<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>',
            'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
            'file-search' => '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><circle cx="11.5" cy="14.5" r="2.5"/><path d="M13.25 16.25 15 18"/>',
            'presentation' => '<path d="M2 3h20"/><path d="M21 3v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V3"/><path d="m7 21 5-5 5 5"/>',
            'layout' => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/>',
            'box' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
            'archive' => '<polyline points="21 8 21 21 3 21 3 8"/><rect x="1" y="3" width="22" height="5"/><line x1="10" y1="12" x2="14" y2="12"/>',
            'file-check-2' => '<path d="M4 22h14a2 2 0 0 0 2-2V7.5L14.5 2H6a2 2 0 0 0-2 2v4"/><polyline points="14 2 14 8 20 8"/><path d="m3 15 2 2 4-4"/>',
            'bell' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        ];

        return isset($icons[$icon]) ? $icons[$icon] : '';
    }

    /**
     * Get the theme's primary/brand color.
     * Returns a placeholder that will be replaced by JavaScript detection.
     * This is more reliable in Moodle 5 where PHP can't always read theme settings.
     *
     * @return string Placeholder or fallback color.
     */
    private function get_theme_primary_color() {
        global $PAGE, $CFG;

        $defaultcolor = '#3b82f6';

        // Method 1: Try using theme_config::load() for Moodle 4.x/5.x.
        try {
            $themeconfig = \theme_config::load($PAGE->theme->name);
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
            // Fall through.
            unset($e);
        }

        // Method 2: Try get_config for current theme.
        $themename = !empty($PAGE->theme->name) ? $PAGE->theme->name : '';
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
                // Fall through.
                unset($e);
            }
        }

        // Method 4: Boost theme.
        $boostcolor = get_config('theme_boost', 'brandcolor');
        if (!empty($boostcolor)) {
            return $boostcolor;
        }

        // Return special marker for JavaScript detection.
        // JS will detect the primary color from existing themed elements.
        return '__DETECT_FROM_DOM__';
    }

    /**
     * Get JavaScript for detecting primary color from DOM.
     * This is more reliable in Moodle 5 where themes use CSS variables.
     *
     * @return string JavaScript code.
     */
    private function get_color_detection_js() {
        return "
            (function () {
                var container = document.querySelector('.ainav-container');
                if (!container) return;
                
                // Check if we need to detect color (marker from PHP).
                var currentColor = getComputedStyle(container).getPropertyValue('--primary').trim();
                if (currentColor !== '__DETECT_FROM_DOM__' && currentColor !== '' && !currentColor.includes('DETECT')) {
                    return; // PHP already detected a valid color.
                }
                
                // Try to detect primary color from existing themed elements.
                var detectedColor = null;
                
                // Method 1: Check CSS variable --primary or --bs-primary from :root.
                var rootStyles = getComputedStyle(document.documentElement);
                var cssVarPrimary = rootStyles.getPropertyValue('--primary').trim();
                if (cssVarPrimary && cssVarPrimary.match(/^#[0-9a-fA-F]{3,6}$|^rgb/)) {
                    detectedColor = cssVarPrimary;
                }
                if (!detectedColor) {
                    var bsPrimary = rootStyles.getPropertyValue('--bs-primary').trim();
                    if (bsPrimary && bsPrimary.match(/^#[0-9a-fA-F]{3,6}$|^rgb/)) {
                        detectedColor = bsPrimary;
                    }
                }
                
                // Method 2: Check .btn-primary button background color.
                if (!detectedColor) {
                    var btnPrimary = document.querySelector('.btn-primary:not(.ainav-btn-primary)');
                    if (btnPrimary) {
                        var bgColor = getComputedStyle(btnPrimary).backgroundColor;
                        if (bgColor && bgColor !== 'rgba(0, 0, 0, 0)' && bgColor !== 'transparent') {
                            detectedColor = bgColor;
                        }
                    }
                }
                
                // Method 3: Check navbar/header background.
                if (!detectedColor) {
                    var navbar = document.querySelector('.navbar, nav.navbar, #page-header, header');
                    if (navbar) {
                        var bgColor = getComputedStyle(navbar).backgroundColor;
                        if (bgColor && bgColor !== 'rgba(0, 0, 0, 0)' && bgColor !== 'transparent' && bgColor !== 'rgb(255, 255, 255)') {
                            detectedColor = bgColor;
                        }
                    }
                }
                
                // Method 4: Check any element with primary class.
                if (!detectedColor) {
                    var primaryEl = document.querySelector('.bg-primary, [class*=\"primary\"]:not(.ainav-btn-primary)');
                    if (primaryEl) {
                        var bgColor = getComputedStyle(primaryEl).backgroundColor;
                        if (bgColor && bgColor !== 'rgba(0, 0, 0, 0)' && bgColor !== 'transparent') {
                            detectedColor = bgColor;
                        }
                    }
                }
                
                // Method 5: Check nav links or active items.
                if (!detectedColor) {
                    var activeNav = document.querySelector('.nav-link.active, .navbar-nav .active > a, a.active');
                    if (activeNav) {
                        var color = getComputedStyle(activeNav).color;
                        if (color && color !== 'rgb(0, 0, 0)' && color !== 'rgb(255, 255, 255)') {
                            detectedColor = color;
                        }
                    }
                }
                
                // Apply detected color.
                if (detectedColor) {
                    container.style.setProperty('--primary', detectedColor);
                    console.log('AI Quick Links: Detected primary color:', detectedColor);
                } else {
                    // Fallback to a nice blue.
                    container.style.setProperty('--primary', '#3b82f6');
                    console.log('AI Quick Links: Using fallback color');
                }
            })();
        ";
    }

    /**
     * Build the block content.
     */
    public function get_content() {
        global $CFG, $PAGE, $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';

        $primarycolor = $this->get_theme_primary_color();
        $registry = $this->get_links_registry();
        $categories = $this->get_installed_plugins_by_category();
        $installedcount = 0;
        foreach ($categories as $plugins) {
            $installedcount += count($plugins);
        }

        $hubbase = $CFG->wwwroot . '/blocks/aiplugin_nav/management_hub.php';
        $html = '<div class="ainav-container" style="--primary: ' . htmlspecialchars($primarycolor) . ';">';
        $html .= '<div class="ainav-operations" aria-label="' .
            s(get_string('managementhub', 'block_aiplugin_nav')) . '">';
        $html .= '<a class="ainav-operations-brand" href="' . $hubbase . '?view=plugins">';
        $html .= '<svg class="ainav-logo" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $html .= '<path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>';
        $html .= '</svg>';
        $html .= '<span><strong>' . s(get_string('managementhub', 'block_aiplugin_nav')) . '</strong>';
        $html .= '<span class="ainav-operations-count">' .
            s(get_string('installedplugincount', 'block_aiplugin_nav', $installedcount)) . '</span></span></a>';

        $html .= '<nav class="ainav-operations-links" aria-label="' .
            s(get_string('hubviews', 'block_aiplugin_nav')) . '">';
        $html .= '<button type="button" class="ainav-launcher-trigger" id="ainav-launcher-trigger" ' .
            'aria-haspopup="dialog" aria-controls="ainav-action-launcher">';
        $html .= '<svg class="ainav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $html .= $this->get_icon_svg('search');
        $html .= '</svg><span>' . s(get_string('findanaction', 'block_aiplugin_nav')) . '</span></button>';
        foreach (['plugins', 'reports', 'updates', 'customlinks', 'help'] as $view) {
            if ($view === 'updates' && !has_capability('moodle/site:config', context_system::instance())) {
                continue;
            }
            $html .= '<a href="' . $hubbase . '?view=' . $view . '">' .
                s(get_string('hubview' . $view, 'block_aiplugin_nav')) . '</a>';
        }
        $html .= '</nav>';

        $isadmin = has_capability('moodle/site:config', context_system::instance());
        $canseecredits = $isadmin
            || $this->user_has_role_shortname($USER->id, 'editingteacher')
            || $this->user_has_role_shortname($USER->id, 'teacher')
            || $this->user_has_role_shortname($USER->id, 'lmshsadmin');
        if ($canseecredits) {
            $html .= '<a class="ainav-operations-status" href="' . $hubbase . '?view=help">';
            $html .= '<span>' . s(get_string('creditbalance', 'block_aiplugin_nav')) . '</span>';
            $html .= '<span class="ainav-credits-badge" id="ainav-credits-badge" aria-live="polite">' .
                s(get_string('balanceunavailable', 'block_aiplugin_nav')) . '</span></a>';
        }
        $html .= '</div>';
        $html .= $this->render_action_launcher($registry);
        $html .= '</div>';

        if ($canseecredits) {
            $PAGE->requires->js_call_amd('block_aiplugin_nav/credits', 'init');
        }
        $this->get_required_javascript();
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
                'description' => 'Centralized Site ID and API Key configuration. Install first - all AI plugins inherit these settings.',
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
                'description' => 'Generate quiz and essay questions with marking criteria based on competency units and learning outcomes. Supports model responses, ChatGPT prompt helper, and Moodle XML export.',
                'access' => 'Quiz → Settings icon (gear) → AI Quiz Maker',
            ],
            [
                'name' => 'AI Content Creator',
                'component' => 'mod_contentcreator',
                'plugin_type' => 'mod',
                'plugin_name' => 'contentcreator',
                'icon' => 'book-open',
                'category' => 'ai_content',
                'description' => 'Create interactive SCORM slideshows with AI-generated images, voiceovers in 52 languages, and embedded activities.',
                'access' => 'Course > Add activity > AI Content Creator',
            ],
            [
                'name' => 'AI Knowledge Check',
                'component' => 'mod_aiknowledgecheck',
                'plugin_type' => 'mod',
                'plugin_name' => 'aiknowledgecheck',
                'icon' => 'check-square',
                'category' => 'ai_grading',
                'description' => 'Self-paced knowledge checks with voice feedback, psychometric distractors, and comprehensive reporting.',
                'access' => 'Course > Add activity > AI Knowledge Check',
            ],
            [
                'name' => 'AI Learning Activities',
                'component' => 'mod_aiactivities',
                'plugin_type' => 'mod',
                'plugin_name' => 'aiactivities',
                'icon' => 'layers',
                'category' => 'ai_content',
                'description' => 'Interactive revision activities generated from learning content. 5 types: ordering, category sort, column sort, card select, and matching.',
                'access' => 'Course > Add activity > AI Learning Activities',
            ],
            [
                'name' => 'AI Video Activity',
                'component' => 'mod_aivideoactivity',
                'plugin_type' => 'mod',
                'plugin_name' => 'aivideoactivity',
                'icon' => 'play-circle',
                'category' => 'ai_media',
                'description' => 'AI-powered video learning with auto-generated questions from YouTube transcripts and voiceover narration.',
                'access' => 'Course > Add activity > AI Video Activity',
            ],
            [
                'name' => 'AI Slideshow with Voiceover',
                'component' => 'mod_slideshow',
                'plugin_type' => 'mod',
                'plugin_name' => 'slideshow',
                'icon' => 'image',
                'category' => 'ai_media',
                'description' => 'Standalone slideshow player with AI voiceover in 52 languages, progress tracking, and SCORM export.',
                'access' => 'Course > Add activity > AI Slideshow',
            ],
            [
                'name' => 'Slides',
                'component' => 'mod_slides',
                'plugin_type' => 'mod',
                'plugin_name' => 'slides',
                'icon' => 'presentation',
                'category' => 'ai_content',
                'description' => 'Interactive multi-type slide activities with seven slide sub-types (video, flip, image-text, image-poster, introduction, matching, summary). Build rich course content with completion tracking.',
                'access' => 'Course > Add activity > Slides',
            ],
            [
                'name' => 'AI SCORM Voiceover',
                'component' => 'local_chirpvoice',
                'plugin_type' => 'local',
                'plugin_name' => 'chirpvoice',
                'icon' => 'headset',
                'category' => 'ai_media',
                'description' => 'Google Chirp 3 HD narration for Articulate Rise 360 SCORM courses. Floating toolbar with 8 voices, 31 languages, speed control, and server-side audio cache (3 credits per paragraph, first play only).',
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
                'description' => 'AI-powered ASQA-compliant mapping table. Uses OpenAI to analyse course content and automatically map activities to training package elements. 100 credits per AI analysis.',
                'access' => 'Course > Add activity > AI Mapping',
            ],
            [
                'name' => 'AI Course Information',
                'component' => 'mod_courseinfo',
                'plugin_type' => 'mod',
                'plugin_name' => 'courseinfo',
                'icon' => 'file-text',
                'category' => 'ai_content',
                'description' => 'Generates ASQA 2025-compliant course information with step-by-step student guides, activity timings, and Volume of Learning compliance. 100 credits per generation.',
                'access' => 'Course > Add activity > AI Course Information',
            ],
            [
                'name' => 'AI Slide Flow',
                'component' => 'mod_productexplainer',
                'plugin_type' => 'mod',
                'plugin_name' => 'productexplainer',
                'icon' => 'presentation',
                'category' => 'ai_content',
                'description' => 'AI-powered slide presentations. Upload a PDF for Product Slides or enter a concept for Concept Slides — AI generates structured training slides with optional voiceover narration (10 credits per generation).',
                'access' => 'Course > Add activity > AI Slide Flow',
            ],
            [
                'name' => 'AI Smart Workbook',
                'component' => 'mod_smartworkbook',
                'plugin_type' => 'mod',
                'plugin_name' => 'smartworkbook',
                'icon' => 'book-open',
                'category' => 'ai_content',
                'description' => 'Convert any Word or PDF teacher workbook into an interactive fillable student activity. AI auto-marks submissions against your answer key; teacher reviews and approves before grades post to the gradebook.',
                'access' => 'Course > Add activity > AI Smart Workbook',
            ],
            // TESTING - Hidden until ready.
            // Array(
            // 'name' => 'AI Quiz',
            // 'component' => 'mod_aiquiz',
            // 'plugin_type' => 'mod',
            // 'plugin_name' => 'aiquiz',
            // 'icon' => 'help-circle',
            // 'category' => 'ai',
            // 'description' => 'AI-powered quiz activity with 5 question types, webcam proctoring, security features, and detailed analytics.',
            // 'access' => 'Course > Add activity > AI Quiz',
            // ),
            // TESTING - Hidden until ready.
            // Array(
            // 'name' => 'AI Practical Assessment',
            // 'component' => 'mod_practicalassessment',
            // 'plugin_type' => 'mod',
            // 'plugin_name' => 'practicalassessment',
            // 'icon' => 'clipboard-check',
            // 'category' => 'ai',
            // 'description' => 'Workplace practical assessments with skills checklists, supervisor verification, and competency mapping.',
            // 'access' => 'Course > Add activity > AI Practical Assessment',
            // ),
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
            // TESTING - Hidden until ready.
            // Array(
            // 'name' => 'AI Webcam Proctoring',
            // 'component' => 'quizaccess_webcamproctor',
            // 'plugin_type' => 'quizaccess',
            // 'plugin_name' => 'webcamproctor',
            // 'icon' => 'eye',
            // 'category' => 'ai',
            // 'description' => 'Webcam monitoring during quizzes with periodic photo capture for exam integrity.',
            // 'access' => 'Quiz > Settings > Extra restrictions',
            // ),
            // Array(
            // 'name' => 'AI Video Conference',
            // 'component' => 'mod_aivideoconf',
            // 'plugin_type' => 'mod',
            // 'plugin_name' => 'aivideoconf',
            // 'icon' => 'video-cam',
            // 'category' => 'ai',
            // 'description' => 'HD video conferencing with AI transcription, session recording, and attendance tracking.',
            // 'access' => 'Course > Add activity > AI Video Conference',
            // ),
            [
                'name' => 'AI RTO Compliance',
                'component' => 'local_rtocompliance',
                'plugin_type' => 'local',
                'plugin_name' => 'rtocompliance',
                'icon' => 'briefcase',
                'category' => 'ai_rto',
                'description' => 'ASQA 2025 compliant Student Management System with trainer credentials, TAS generator, qualification builder, and AI-powered compliance reporting.',
                'access' => 'Site admin > Plugins > Local plugins > RTO Compliance',
                'goto_url' => '/local/rtocompliance/index.php',
            ],
            // V2.4.62 REMOVE-AIPAGETEMPLATES-FINAL: tiny_aipagetemplates deliberately.
            // ABSENT from this registry. It crashed customer sites, was removed in.
            // V2.4.39, and was accidentally re-added in v2.4.53. It is also.
            // Deprecated server-side (excluded from the lms-labs.com manifest), so.
            // It must NEVER be re-added here.
            [
                'name' => 'RPL Kit',
                'component' => 'local_rplkit',
                'plugin_type' => 'local',
                'plugin_name' => 'rplkit',
                'icon' => 'file-check-2',
                'category' => 'ai_rto',
                'description' => 'Generates ASQA-mapped RPL assessment kits for any unit of competency: theory quiz (essay questions from Knowledge Evidence), SmartForm checklist (Performance Criteria), and Assignment Benchmarks criteria. Integrates with RTO Compliance for shared qualification data.',
                'access' => 'Site admin > Plugins > Local plugins > RPL Kit',
                'goto_url' => '/local/rplkit/index.php',
            ],
            // Array(
            // 'name' => 'AI Training Matrix HCM',
            // 'component' => 'local_trainingmatrix',
            // 'plugin_type' => 'local',
            // 'plugin_name' => 'trainingmatrix',
            // 'icon' => 'users-2',
            // 'category' => 'ai',
            // 'description' => 'Human Capital Management for staff competency tracking. AI-powered competency generation (10 credits per position).',
            // 'access' => 'Site admin > Plugins > Local plugins > AI Training Matrix',
            // 'goto_url' => '/local/trainingmatrix/index.php',
            // ),
            [
                'name' => 'AI Support',
                'component' => 'local_moodlesupport',
                'plugin_type' => 'local',
                'plugin_name' => 'moodlesupport',
                'icon' => 'headset',
                'category' => 'ai_ux',
                'description' => 'AI-powered help desk with trained knowledge base. Answers Moodle questions instantly via chat widget.',
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
                'description' => 'Flexible report builder with 49 pre-built recipes. Grain-aware engine prevents row-multiplication errors. Schedule reports by email or cohort with threshold alerts.',
                'access' => 'Site admin > Plugins > Local plugins > Beacon',
                'goto_url' => '/local/beacon/index.php',
            ],
            // TESTING - Hidden until ready.
            // Array(
            // 'name' => 'Speed',
            // 'component' => 'report_performanceintel',
            // 'plugin_type' => 'report',
            // 'plugin_name' => 'performanceintel',
            // 'icon' => 'zap',
            // 'category' => 'utility',
            // 'description' => 'Performance Intelligence for real-time monitoring of user experience, bottleneck identification, and actionable fixes.',
            // 'access' => 'Site admin > Reports > Speed (Performance Intelligence)',
            // 'goto_url' => '/report/performanceintel/index.php',
            // ),
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
                'description' => 'Teacher view of student progress across courses with search, filters, and per-student completion tracking.',
                'access' => 'Dashboard > Add block > My Students Progress',
            ],
            // TESTING - Hidden until ready.
            // Array(
            // 'name' => 'My Training Progress',
            // 'component' => 'block_trainingmatrix',
            // 'plugin_type' => 'block',
            // 'plugin_name' => 'trainingmatrix',
            // 'icon' => 'users-2',
            // 'category' => 'block',
            // 'group' => 'AI Training Matrix HCM',
            // 'description' => 'Staff training progress with compliance ring, required competencies, and action items.',
            // 'access' => 'Dashboard > Add block > My Training Progress',
            // ),
            // Array(
            // 'name' => 'Staff Training Dashboard',
            // 'component' => 'block_trainingmatrix_teacher',
            // 'plugin_type' => 'block',
            // 'plugin_name' => 'trainingmatrix_teacher',
            // 'icon' => 'users-2',
            // 'category' => 'block',
            // 'group' => 'AI Training Matrix HCM',
            // 'description' => 'Manager view of staff compliance, expiring competencies, and staff needing attention.',
            // 'access' => 'Dashboard > Add block > Staff Training Dashboard',
            // ),
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
                'description' => 'Scan your Moodle file store for oversized images, duplicate files, unused backups, and unoptimised video — with impact scores and fix recommendations.',
                'access' => 'Site admin > Local plugins > Media Optimiser > Dashboard',
            ],
            [
                'name' => 'AI Course Format',
                'component' => 'format_aicourse',
                'plugin_type' => 'format',
                'plugin_name' => 'aicourse',
                'icon' => 'book-open',
                'category' => 'ai_content',
                'description' => 'Modern course format with world-class AI Tutor that adapts to activity context, assignment-safe mode, and guided practice.',
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
                'description' => 'Cohort-based time-controlled access with intake groups, grace periods, and AVETMISS compliance reports.',
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
                'description' => 'Availability restriction based on group intake dates. Activities auto-hide before/after access windows.',
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
                'description' => 'Version management for course materials with auto-lock protection, audit trails, and TAS integration.',
                'access' => 'Course > Course Version Control',
            ],
            [
                'name' => 'Activity Navigation',
                'component' => 'local_activitynav',
                'plugin_type' => 'local',
                'plugin_name' => 'activitynav',
                'icon' => 'navigation',
                'category' => 'training',
                'description' => 'Streamlined navigation between activities with breadcrumbs, previous/next buttons, and progress tracking.',
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
                'description' => 'Global font customisation with 10 Google Fonts, comprehensive CSS overrides, and FontAwesome preservation.',
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
                'description' => 'Multi-tenant branding per cohort with logos, colours, fonts, and priority system for multi-cohort users.',
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
            ],
            [
                'name' => 'Simple 2FA & SSO',
                'component' => 'auth_simple2fa',
                'plugin_type' => 'auth',
                'plugin_name' => 'simple2fa',
                'icon' => 'shield',
                'category' => 'security',
                'credits_required' => 1000,
                'description' => 'Two-factor authentication with Google Authenticator TOTP for admin accounts. Includes built-in OAuth2/OIDC SSO.',
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
                'description' => 'Enforce maximum group size. Blocks self-enrolment and manual additions when a group reaches its member limit.',
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
                'description' => 'Monetise late assignment submissions. Students pay via Stripe to reopen locked assignments for another attempt. Configurable fees per attempt, admin overrides, revenue reporting, and full audit log.',
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
                'description' => 'Live writing-process analysis for academic integrity. Monitors keystroke dynamics, paste events, burst typing, pause patterns, and revision behaviour — gives teachers a Low / Medium / High risk badge on every submission.',
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
                'description' => 'AI and plagiarism detection for PDF and Word document submissions. Extracts text, detects question/answer sections, and scores each answer across 12 signals — producing a Low / Medium / High risk badge per file.',
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
                'description' => 'Delay when enrolled courses appear on a student\'s My Courses dashboard. Set per-course delays (days since enrolment) or fixed unlock dates, with per-user overrides and bulk CSV import.',
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
                'description' => 'Paddle as Merchant of Record for global tax-compliant payments. Hosted checkout, automatic enrolment, 30-currency support.',
                'access' => 'Site admin > Plugins > Payment gateways > Paddle',
                'goto_url' => '/payment/gateway/paddle/admin/reports.php',
            ],
            [
                'name' => 'Workshop Scheduler',
                'component' => 'local_workshops',
                'plugin_type' => 'local',
                'plugin_name' => 'workshops',
                'icon' => 'calendar',
                'category' => 'training',
                'description' => 'Complete workshop logistics and bookings management. Schedule face-to-face, webinar and hybrid workshops, manage participant bookings, track checklists, and upload documents.',
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
                'description' => 'Auto-generates AI explanations with optional voiceover and images for wrong quiz answers. Dynamic credit system.',
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
                'description' => 'AI-powered branded login page designer. Generates a custom colour scheme and CSS via AI (25 credits per generation). Animated favicon particles, logo upload, slogan and subtext customisation.',
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
                'description' => 'Multi-site uptime monitoring with deep server diagnostics. Checks HTTP, SSL, DNS, disk, CPU, memory, MySQL, Redis, PHP-FPM, and Moodle health — then emails a root-cause alert when any site goes down.',
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
                'description' => 'Auto-provisions and manages cPanel email accounts for every enrolled student. Dashboard with live stats, bulk actions, and a student "My Email" portal showing credentials and Roundcube webmail link.',
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
                'description' => 'Lets students log into Moodle using their provisioned college email address and password, verified in real-time via IMAP. Works alongside Student Email Manager.',
                'access' => 'Site admin > Plugins > Authentication > Student Email IMAP Auth',
            ],
            // TESTING — BigBlueButton and Recordings hidden until fully tested.
            // Array(
            // 'name' => 'BigBlueButton (Moodle 4.x)',
            // 'component' => 'mod_bigbluebuttonbn',
            // 'plugin_type' => 'mod',
            // 'plugin_name' => 'bigbluebuttonbn',
            // 'icon' => 'video',
            // 'category' => 'utility',
            // 'description' => 'HD video conferencing with breakout rooms, polling, and session recording. Moodle 4.x compatible build.',
            // 'access' => 'Course > Add activity > BigBlueButton',
            // ),
            // Array(
            // 'name' => 'BigBlueButton Recordings (Moodle 4.x)',
            // 'component' => 'mod_recordingsbn',
            // 'plugin_type' => 'mod',
            // 'plugin_name' => 'recordingsbn',
            // 'icon' => 'play-circle',
            // 'category' => 'utility',
            // 'description' => 'Companion plugin for BigBlueButton. Browse, manage, and share session recordings within Moodle courses.',
            // 'access' => 'Course > Add activity > BigBlueButton Recordings',
            // ),
            // TESTING - Hidden until ready.
            // Array(
            // 'name' => 'AI PDF Assignment Grader',
            // 'component' => 'assignfeedback_aipdf',
            // 'plugin_type' => 'assignfeedback',
            // 'plugin_name' => 'aipdf',
            // 'icon' => 'file-text',
            // 'category' => 'ai',
            // 'description' => 'AI-powered PDF assignment grading with rubric-based feedback and inline annotations.',
            // 'access' => 'Assignment > Feedback types > AI PDF Grader',
            // ),
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
        ];
    }

    /**
     * Get the AI Tools registry for quick access section.
     * Each tool has a capability that determines who can see it.
     */
    private function get_ai_tools_registry() {
        return [
            [
                'name' => get_string('ai_essay_grader', 'block_aiplugin_nav'),
                'access' => get_string('ai_essay_grader_access', 'block_aiplugin_nav'),
                'icon' => 'pen-tool',
                'plugin_type' => 'quiz',
                'plugin_name' => 'aigrader',
                'capability' => 'mod/quiz:grade',
            ],
            [
                'name' => get_string('ai_content_creator', 'block_aiplugin_nav'),
                'access' => get_string('ai_content_creator_access', 'block_aiplugin_nav'),
                'icon' => 'book-open',
                'plugin_type' => 'mod',
                'plugin_name' => 'contentcreator',
                'capability' => 'moodle/course:manageactivities',
            ],
            [
                'name' => get_string('ai_knowledge_check', 'block_aiplugin_nav'),
                'access' => get_string('ai_knowledge_check_access', 'block_aiplugin_nav'),
                'icon' => 'check-square',
                'plugin_type' => 'mod',
                'plugin_name' => 'aiknowledgecheck',
                'capability' => 'moodle/course:manageactivities',
            ],
            // TESTING - Hidden until ready.
            // Array(
            // 'name' => get_string('ai_practical_assessment', 'block_aiplugin_nav'),
            // 'access' => get_string('ai_practical_assessment_access', 'block_aiplugin_nav'),
            // 'icon' => 'clipboard-check',
            // 'plugin_type' => 'mod',
            // 'plugin_name' => 'practicalassessment',
            // 'capability' => 'moodle/course:manageactivities',
            // ),
            [
                'name' => get_string('ai_learning_activities', 'block_aiplugin_nav'),
                'access' => get_string('ai_learning_activities_access', 'block_aiplugin_nav'),
                'icon' => 'layers',
                'plugin_type' => 'mod',
                'plugin_name' => 'aiactivities',
                'capability' => 'moodle/course:manageactivities',
            ],
            [
                'name' => get_string('ai_verify_id', 'block_aiplugin_nav'),
                'access' => get_string('ai_verify_id_access', 'block_aiplugin_nav'),
                'icon' => 'user-check',
                'plugin_type' => 'mod',
                'plugin_name' => 'verifyid',
                'capability' => 'moodle/course:manageactivities',
            ],
            [
                'name' => get_string('ai_video_activity', 'block_aiplugin_nav'),
                'access' => get_string('ai_video_activity_access', 'block_aiplugin_nav'),
                'icon' => 'play-circle',
                'plugin_type' => 'mod',
                'plugin_name' => 'aivideoactivity',
                'capability' => 'moodle/course:manageactivities',
            ],
            // TESTING - Hidden until ready.
            // Array(
            // 'name' => get_string('ai_quiz', 'block_aiplugin_nav'),
            // 'access' => get_string('ai_quiz_access', 'block_aiplugin_nav'),
            // 'icon' => 'help-circle',
            // 'plugin_type' => 'mod',
            // 'plugin_name' => 'aiquiz',
            // 'capability' => 'moodle/course:manageactivities',
            // ),
            [
                'name' => get_string('ai_essay_maker', 'block_aiplugin_nav'),
                'access' => get_string('ai_essay_maker_access', 'block_aiplugin_nav'),
                'icon' => 'edit-3',
                'plugin_type' => 'local',
                'plugin_name' => 'aiquizmaker',
                'capability' => 'mod/quiz:manage',
            ],
            [
                'name' => get_string('learning_mapping', 'block_aiplugin_nav'),
                'access' => get_string('learning_mapping_access', 'block_aiplugin_nav'),
                'icon' => 'table',
                'plugin_type' => 'mod',
                'plugin_name' => 'learningmapping',
                'capability' => 'moodle/course:manageactivities',
            ],
            [
                'name' => get_string('ai_course_information', 'block_aiplugin_nav'),
                'access' => get_string('ai_course_information_access', 'block_aiplugin_nav'),
                'icon' => 'file-text',
                'plugin_type' => 'mod',
                'plugin_name' => 'courseinfo',
                'capability' => 'moodle/course:manageactivities',
            ],
            [
                'name' => get_string('ai_product_explainer', 'block_aiplugin_nav'),
                'access' => get_string('ai_product_explainer_access', 'block_aiplugin_nav'),
                'icon' => 'presentation',
                'plugin_type' => 'mod',
                'plugin_name' => 'productexplainer',
                'capability' => 'moodle/course:manageactivities',
            ],
            [
                'name' => get_string('ai_smart_workbook', 'block_aiplugin_nav'),
                'access' => get_string('ai_smart_workbook_access', 'block_aiplugin_nav'),
                'icon' => 'book-open',
                'plugin_type' => 'mod',
                'plugin_name' => 'smartworkbook',
                'capability' => 'moodle/course:manageactivities',
            ],
            [
                'name' => get_string('workshop_scheduler', 'block_aiplugin_nav'),
                'access' => get_string('workshop_scheduler_access', 'block_aiplugin_nav'),
                'icon' => 'calendar',
                'plugin_type' => 'local',
                'plugin_name' => 'workshops',
                'capability' => 'moodle/site:config',
            ],
            // TESTING - Hidden until ready.
            // Array(
            // 'name' => get_string('ai_video_conference', 'block_aiplugin_nav'),
            // 'access' => get_string('ai_video_conference_access', 'block_aiplugin_nav'),
            // 'icon' => 'video-cam',
            // 'plugin_type' => 'local',
            // 'plugin_name' => 'aivideoconf',
            // 'capability' => 'moodle/course:manageactivities',
            // ),
            // Array(
            // 'name' => get_string('ai_webcam_proctoring', 'block_aiplugin_nav'),
            // 'access' => get_string('ai_webcam_proctoring_access', 'block_aiplugin_nav'),
            // 'icon' => 'eye',
            // 'plugin_type' => 'quizaccess',
            // 'plugin_name' => 'webcamproctor',
            // 'capability' => 'mod/quiz:manage',
            // ),
            // Array(
            // 'name' => get_string('training_matrix', 'block_aiplugin_nav'),
            // 'access' => get_string('training_matrix_access', 'block_aiplugin_nav'),
            // 'icon' => 'users-2',
            // 'plugin_type' => 'local',
            // 'plugin_name' => 'trainingmatrix',
            // 'capability' => 'moodle/site:config',
            // ),
        ];
    }

    /**
     * Check if user has capability in any course context.
     * This is used for dashboard display where we want to show tools
     * if the user has the capability in at least one course.
     *
     * @param string $capability Capability name.
     * @return bool Whether the user has the capability.
     */
    private function has_capability_anywhere($capability) {
        global $USER, $DB;

        // Registry rendering asks the same capability question for many actions.
        // Cache once per request so a large course enrolment list is never scanned.
        // Repeatedly while building menus and launcher results.
        static $capabilitycache = [];
        if (array_key_exists($capability, $capabilitycache)) {
            return $capabilitycache[$capability];
        }

        // Admins always have access.
        if (is_siteadmin()) {
            $capabilitycache[$capability] = true;
            return $capabilitycache[$capability];
        }

        // Check system context first.
        $systemcontext = context_system::instance();
        if (has_capability($capability, $systemcontext)) {
            $capabilitycache[$capability] = true;
            return $capabilitycache[$capability];
        }

        // Get all courses the user is enrolled in.
        $courses = enrol_get_my_courses('id', 'id ASC', 0);
        foreach ($courses as $course) {
            $coursecontext = context_course::instance($course->id);
            if (has_capability($capability, $coursecontext)) {
                $capabilitycache[$capability] = true;
                return $capabilitycache[$capability];
            }
        }

        $capabilitycache[$capability] = false;
        return $capabilitycache[$capability];
    }

    /**
     * Render the AI Tools quick access section with version checking.
     * For admins, shows all installed plugins with status labels and update buttons.
     */
    private function render_ai_tools_section() {
        global $USER;

        $context = context_system::instance();
        $isadmin = is_siteadmin() || has_capability('moodle/site:config', $context);

        // For admins, show comprehensive plugin management.
        if ($isadmin) {
            return $this->render_plugin_management_section();
        }

        // For non-admins, show simple AI Tools grid.
        $tools = $this->get_ai_tools_registry();
        $visibletools = [];

        foreach ($tools as $tool) {
            if (!$this->is_plugin_installed($tool['plugin_type'], $tool['plugin_name'])) {
                continue;
            }
            if (!empty($tool['capability']) && !$this->has_capability_anywhere($tool['capability'])) {
                continue;
            }
            $visibletools[] = $tool;
        }

        if (empty($visibletools)) {
            return '';
        }

        // Get default collapsed setting.
        $collapseddefault = get_config('block_aiplugin_nav', 'aitools_collapsed_default');
        $collapsedclass = $collapseddefault ? ' ainav-section-collapsed' : '';

        $html = '<div class="ainav-tools-section' . $collapsedclass . '" id="ainav-tools-section-user">';
        $html .= '<div class="ainav-tools-header">';

        // Toggle button with arrow (same as admin view).
        $html .= '<button type="button" class="ainav-section-toggle" id="ainav-toggle-tools-user" title="' . get_string('collapse_section', 'block_aiplugin_nav') . '">';
        $html .= '<svg class="ainav-toggle-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $html .= '<polyline points="6 9 12 15 18 9"/>';
        $html .= '</svg>';
        $html .= '</button>';

        $html .= '<span class="ainav-tools-title">' . get_string('ai_tools_section', 'block_aiplugin_nav') . '</span>';
        $html .= '</div>';

        // Collapsible content wrapper.
        $html .= '<div class="ainav-section-content" id="ainav-tools-content-user">';
        $html .= '<div class="ainav-tools-grid">';

        foreach ($visibletools as $tool) {
            $html .= '<div class="ainav-tool-card">';
            $html .= '<div class="ainav-tool-icon">';
            $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $html .= $this->get_icon_svg($tool['icon']);
            $html .= '</svg>';
            $html .= '</div>';
            $html .= '<div class="ainav-tool-content">';
            $html .= '<div class="ainav-tool-name">' . $tool['name'] . '</div>';
            $html .= '<div class="ainav-tool-access">' . $tool['access'] . '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';

        // V4.9.108 STUDENT-DOC-REPOSITORY — inject "My Documents & Certificates" quick link.
        // For students when local_rtocompliance is installed.
        if (
            $this->is_plugin_installed('local', 'rtocompliance')
                && has_capability('local/rtocompliance:viewown', context_user::instance($USER->id))
        ) {
            $html .= '<div style="margin-top:10px;padding:8px 0;border-top:1px solid rgba(255,255,255,0.12);">';
            $html .= '<div style="font-size:0.7rem;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;'
                   . 'color:rgba(255,255,255,0.5);margin-bottom:6px;padding:0 4px;">My Portfolio</div>';
            $mydocsurl = new moodle_url('/local/rtocompliance/mydocs.php');
            $mycertsurl = new moodle_url('/local/rtocompliance/mycerts.php');
            $html .= '<a href="' . $mydocsurl->out(false) . '" style="display:flex;align-items:center;gap:8px;'
                   . 'padding:6px 8px;border-radius:6px;text-decoration:none;color:rgba(255,255,255,0.85);'
                   . 'font-size:0.82rem;transition:background 0.15s;" '
                   . 'onmouseover="this.style.background=\'rgba(255,255,255,0.1)\'" '
                   . 'onmouseout="this.style.background=\'transparent\'">'
                   . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="flex-shrink:0;">'
                   . '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>'
                   . '</svg>My Documents &amp; Certificates</a>';
            $html .= '<a href="' . $mycertsurl->out(false) . '" style="display:flex;align-items:center;gap:8px;'
                   . 'padding:6px 8px;border-radius:6px;text-decoration:none;color:rgba(255,255,255,0.85);'
                   . 'font-size:0.82rem;transition:background 0.15s;" '
                   . 'onmouseover="this.style.background=\'rgba(255,255,255,0.1)\'" '
                   . 'onmouseout="this.style.background=\'transparent\'">'
                   . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="flex-shrink:0;">'
                   . '<circle cx="12" cy="8" r="6"/><path d="M15.477 12.89L17 22l-5-3-5 3 1.523-9.11"/>'
                   . '</svg>My Certificates</a>';
            $html .= '</div>';
        }

        $html .= '</div>'; // End .ainav-section-content.

        // Toggle section JavaScript (user view).
        $html .= '<script>
        (function () {
            var section = document.getElementById("ainav-tools-section-user");
            var toggleBtn = document.getElementById("ainav-toggle-tools-user");
            var storageKey = "ainav_tools_collapsed";
            
            if (!section || !toggleBtn) return;
            
            // Check localStorage for user preference
            var userPref = localStorage.getItem(storageKey);
            if (userPref !== null) {
                if (userPref === "1") {
                    section.classList.add("ainav-section-collapsed");
                } else {
                    section.classList.remove("ainav-section-collapsed");
                }
            }
            
            toggleBtn.addEventListener("click", function (e) {
                e.preventDefault();
                var isCollapsed = section.classList.toggle("ainav-section-collapsed");
                localStorage.setItem(storageKey, isCollapsed ? "1" : "0");
            });
        })();
        </script>';

        $html .= '</div>';

        return $html;
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
     * @param string $plugintype Plugin type.
     * @param string $pluginname Plugin name.
     * @return string Plugin release version.
     */
    public function get_plugin_version($plugintype, $pluginname) {
        return $this->get_plugin_version_data($plugintype, $pluginname)['release'];
    }

    /**
     * Get the numeric version of an installed plugin.
     *
     * @param string $plugintype Plugin type.
     * @param string $pluginname Plugin name.
     * @return int|null Numeric plugin version.
     */
    public function get_plugin_numeric_version($plugintype, $pluginname) {
        return $this->get_plugin_version_data($plugintype, $pluginname)['version'];
    }

    /**
     * Render plugin management section for admins.
     * Shows ALL plugins with status labels and update/install functionality.
     */
    public function render_plugin_management_section() {
        $registry = $this->get_complete_plugin_registry();
        $allplugins = [];

        // 5-minute cross-request cache for plugin installed status + versions.
        // Reading 44 version.php files on every admin page load is the biggest bottleneck.
        $cachettl = 300; // 5 minutes.
        $cachetime = (int)get_config('block_aiplugin_nav', 'plugin_status_cache_time');
        $pluginstatusmap = null;
        if ($cachetime && (time() - $cachetime) < $cachettl) {
            $cachedjson = get_config('block_aiplugin_nav', 'plugin_status_cache_data');
            if ($cachedjson) {
                $pluginstatusmap = json_decode($cachedjson, true);
            }
        }

        // Cache invalidation: detect newly installed/removed plugins by hashing.
        // The count of each plugin type we care about. If anything changed since the.
        // Cache was built (e.g. admin installed local_aiconfig manually), bust the cache.
        // Immediately rather than waiting up to 5 minutes for the TTL to expire.
        if ($pluginstatusmap !== null) {
            $livehash = md5(
                count(core_component::get_plugin_list('local')) . '|' .
                count(core_component::get_plugin_list('mod')) . '|' .
                count(core_component::get_plugin_list('block')) . '|' .
                count(core_component::get_plugin_list('quiz')) . '|' .
                count(core_component::get_plugin_list('quizaccess')) . '|' .
                count(core_component::get_plugin_list('auth')) . '|' .
                count(core_component::get_plugin_list('enrol')) . '|' .
                count(core_component::get_plugin_list('gradingform')) . '|' .
                count(core_component::get_plugin_list('availability')) . '|' .
                count(core_component::get_plugin_list('paygw')) . '|' .
                count(core_component::get_plugin_list('plagiarism')) . '|' .
                count(core_component::get_plugin_list('format'))
            );
            $storedhash = get_config('block_aiplugin_nav', 'plugin_list_hash');
            if ($storedhash !== $livehash) {
                // Plugin landscape changed — invalidate the cache immediately.
                $pluginstatusmap = null;
                set_config('plugin_list_hash', $livehash, 'block_aiplugin_nav');
            }
        }

        if ($pluginstatusmap !== null) {
            // Cache hit: reconstruct $all_plugins from registry + cached status data.
            foreach ($registry as $plugin) {
                $component = $plugin['component'];
                $status = isset($pluginstatusmap[$component]) ? $pluginstatusmap[$component] : [];
                $plugin['is_installed'] = !empty($status['is_installed']);
                $plugin['installed_version'] = isset($status['installed_version']) ? $status['installed_version'] : null;
                $plugin['installed_numeric_version'] = isset($status['installed_numeric_version']) ? $status['installed_numeric_version'] : '0';
                $allplugins[] = $plugin;
            }
        } else {
            // Cache miss: read all version.php files, then persist to config.
            $newstatusmap = [];
            foreach ($registry as $plugin) {
                $isinstalled = $this->is_plugin_installed($plugin['plugin_type'], $plugin['plugin_name']);
                $vdata = $isinstalled ? $this->get_plugin_version_data($plugin['plugin_type'], $plugin['plugin_name'])
                                       : ['release' => null, 'version' => '0'];
                $plugin['is_installed'] = $isinstalled;
                $plugin['installed_version'] = $vdata['release'];
                $plugin['installed_numeric_version'] = $vdata['version'];
                $allplugins[] = $plugin;
                $newstatusmap[$plugin['component']] = [
                    'is_installed' => $isinstalled,
                    'installed_version' => $vdata['release'],
                    'installed_numeric_version' => $vdata['version'],
                ];
            }
            set_config('plugin_status_cache_data', json_encode($newstatusmap), 'block_aiplugin_nav');
            set_config('plugin_status_cache_time', time(), 'block_aiplugin_nav');
        }

        if (empty($allplugins)) {
            return '';
        }

        $pluginsjson = [];
        foreach ($allplugins as $plugin) {
            $pluginsjson[] = [
                'component' => $plugin['component'],
                'installedVersion' => $plugin['installed_version'],
                'installedNumericVersion' => $plugin['installed_numeric_version'],
                'isInstalled' => $plugin['is_installed'],
            ];
        }

        // Get default collapsed setting.
        $collapseddefault = get_config('block_aiplugin_nav', 'aitools_collapsed_default');
        $collapsedclass = $collapseddefault ? ' ainav-section-collapsed' : '';

        $html = '<div class="ainav-tools-section ainav-plugin-management' . $collapsedclass . '" id="ainav-tools-section">';

        // Header with AI Support link and Update All button.
        $html .= '<div class="ainav-tools-header ainav-pm-header">';

        // Toggle button with arrow.
        $html .= '<button type="button" class="ainav-section-toggle" id="ainav-toggle-tools" title="' . get_string('collapse_section', 'block_aiplugin_nav') . '">';
        $html .= '<svg class="ainav-toggle-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $html .= '<polyline points="6 9 12 15 18 9"/>';
        $html .= '</svg>';
        $html .= '</button>';

        $html .= '<span class="ainav-tools-title">' . get_string('ai_tools_section', 'block_aiplugin_nav') . '</span>';
        $html .= '<div class="ainav-pm-actions">';

        // AI Moodle Support button (only show if installed).
        if ($this->is_plugin_installed('local', 'moodlesupport')) {
            global $CFG;
            $html .= '<a href="' . $CFG->wwwroot . '/local/moodlesupport/index.php" class="ainav-btn ainav-btn-support">';
            $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ainav-btn-icon">';
            $html .= $this->get_icon_svg('headset');
            $html .= '</svg>';
            $html .= '<span>' . get_string('ai_support', 'block_aiplugin_nav') . '</span>';
            $html .= '</a>';
        }

        $html .= '<button type="button" class="ainav-btn ainav-btn-check" id="ainav-check-versions">';
        $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ainav-btn-icon">';
        $html .= '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>';
        $html .= '</svg>';
        $html .= '<span>' . get_string('check_updates', 'block_aiplugin_nav') . '</span>';
        $html .= '</button>';
        $html .= '<button type="button" class="ainav-btn ainav-btn-update-all" id="ainav-update-all" style="display:none;">';
        $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ainav-btn-icon">';
        $html .= '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>';
        $html .= '</svg>';
        $html .= '<span>' . get_string('update_all', 'block_aiplugin_nav') . '</span>';
        $html .= '</button>';
        $html .= '<button type="button" class="ainav-btn ainav-btn-autoupdate-all" id="ainav-autoupdate-all" style="display:none;">';
        $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ainav-btn-icon">';
        $html .= '<path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/>';
        $html .= '</svg>';
        $html .= '<span>' . get_string('auto_update_all', 'block_aiplugin_nav') . '</span>';
        $html .= '</button>';
        $html .= '</div>';
        $html .= '</div>';

        // Collapsible content wrapper.
        $html .= '<div class="ainav-section-content" id="ainav-tools-content">';

        // Plugin categories - Super-group layout matching the approved design.
        // Left super-group: AI & Learning Intelligence (5 category columns).
        // Right super-group: Administration & Operations (9 category columns).

        // Helper: filter plugins by one or more categories.
        $getcat = function ($all, $cats) {
            return array_values(array_filter($all, function ($p) use ($cats) {
                return in_array($p['category'], $cats);
            }));
        };

        // Render Foundation plugin card first (local_aiconfig).
        $foundationplugins  = array_values(array_filter($allplugins, function ($p) {
            return !empty($p['install_first']);
        }));

        // AI super-group categories (left).
        $cataigrading   = $getcat($allplugins, ['ai_grading']);
        $cataicontent   = $getcat($allplugins, ['ai_content']);
        $cataimedia     = $getcat($allplugins, ['ai_media']);
        $catairto       = $getcat($allplugins, ['ai_rto']);
        $cataiux        = $getcat($allplugins, ['ai_ux']);

        // Admin super-group categories (right).
        $catblock        = $getcat($allplugins, ['block']);
        $cattraining     = $getcat($allplugins, ['training']);
        $catenrolment    = $getcat($allplugins, ['enrolment']);
        $catintegrity    = $getcat($allplugins, ['integrity']);
        $catcomms        = $getcat($allplugins, ['comms']);
        $catbranding     = $getcat($allplugins, ['branding']);
        $catmediastorage = $getcat($allplugins, ['media_storage']);
        $catsecurity     = $getcat($allplugins, ['security']);
        $catreporting    = $getcat($allplugins, ['reporting']);
        $catpayments     = $getcat($allplugins, ['payments']);

        // Helper: render one category column.
        $rendercatcol = function ($title, $plugins, $isai = true) use (&$html) {
            if (empty($plugins)) {
                return;
            }
            $installed = count(array_filter($plugins, function ($p) {
                return !empty($p['is_installed']);
            }));
            $total     = count($plugins);
            $tonecls  = $isai ? 'ainav-pm-cat-ai' : 'ainav-pm-cat-admin';
            $html .= '<div class="ainav-pm-category ' . $tonecls . '">';
            $html .= '<div class="ainav-pm-category-header">';
            $html .= '<div class="ainav-pm-category-title">' . htmlspecialchars($title) . '</div>';
            $html .= '<div class="ainav-pm-category-badge">' . $installed . '/' . $total . '</div>';
            $html .= '</div>';
            $html .= '<div class="ainav-pm-list">';
            foreach ($plugins as $plugin) {
                $html .= $this->render_plugin_card($plugin);
            }
            $html .= '</div>';
            $html .= '</div>';
        };

        // ── Outer wrapper ──────────────────────────────────────────────────
        // ===== World-class plugin finder (search / sort / filter / views) =====
        $fdrcats = [
            'config' => ['label' => 'Central Config', 'group' => 'ai', 'icon' => 'settings'],
            'ai_grading' => ['label' => 'AI Grading & Assessment', 'group' => 'ai', 'icon' => 'clipboard-check'],
            'ai_content' => ['label' => 'AI Content & Courses', 'group' => 'ai', 'icon' => 'book-open'],
            'ai_media' => ['label' => 'AI Voice & Media', 'group' => 'ai', 'icon' => 'music'],
            'ai_rto' => ['label' => 'RTO & Compliance', 'group' => 'ai', 'icon' => 'shield'],
            'ai_ux' => ['label' => 'AI Personalisation', 'group' => 'ai', 'icon' => 'user-check'],
            'block' => ['label' => 'Blocks & Dashboards', 'group' => 'admin', 'icon' => 'layout-dashboard'],
            'training' => ['label' => 'Training & Scheduling', 'group' => 'admin', 'icon' => 'calendar-clock'],
            'enrolment' => ['label' => 'Enrolment & Access', 'group' => 'admin', 'icon' => 'user-check'],
            'integrity' => ['label' => 'Academic Integrity', 'group' => 'admin', 'icon' => 'shield'],
            'comms' => ['label' => 'Communications', 'group' => 'admin', 'icon' => 'mail'],
            'branding' => ['label' => 'Branding & Appearance', 'group' => 'admin', 'icon' => 'palette'],
            'media_storage' => ['label' => 'Media & Storage', 'group' => 'admin', 'icon' => 'hard-drive'],
            'security' => ['label' => 'Security & Auth', 'group' => 'admin', 'icon' => 'lock'],
            'reporting' => ['label' => 'Reporting & Analytics', 'group' => 'admin', 'icon' => 'bar-chart-2'],
            'payments' => ['label' => 'Payments', 'group' => 'admin', 'icon' => 'credit-card'],
        ];
        $fdrdata = [];
        $fdriconkeys = ['star', 'settings'];
        foreach ($allplugins as $p) {
            $cat = isset($p['category']) ? $p['category'] : '';
            if (!isset($fdrcats[$cat])) {
                continue;
            }
            $vlabel = $this->format_version_label($p);
            $fdrdata[] = [
                'name'       => $p['name'],
                'component'  => $p['component'],
                'cat'        => $cat,
                'catLabel'   => $fdrcats[$cat]['label'],
                'group'      => $fdrcats[$cat]['group'],
                'icon'       => isset($p['icon']) ? $p['icon'] : 'star',
                'desc'       => isset($p['description']) ? $p['description'] : '',
                'credits'    => ($p['component'] === 'local_rtocompliance') ? 2000 : 500,
                'usd'        => ($p['component'] === 'local_rtocompliance') ? 2000 : 50,
                'pluginId'   => isset($p['plugin_name']) ? $p['plugin_name'] : '',
                'installed'  => !empty($p['is_installed']),
                'version'    => ($vlabel === '?' ? '' : $vlabel),
                'foundation' => !empty($p['install_first']),
                'added'      => count($fdrdata),
                'popularity' => (!empty($p['is_installed']) ? 1000000 : 0) - count($fdrdata),
                'docs'       => $this->get_plugin_docs_url($p['component']),
            ];
            $fdriconkeys[] = isset($p['icon']) ? $p['icon'] : 'star';
        }
        foreach ($fdrcats as $c) {
            $fdriconkeys[] = $c['icon'];
        }
        $fdricons = [];
        foreach (array_unique($fdriconkeys) as $ik) {
            $fdricons[$ik] = $this->get_icon_svg($ik);
        }

        $html .= '<div class="ainav-fdr">';
        $html .= <<<'FDR_TOOLBAR'
<div class="finder">
 <div class="finder-top">
  <div class="f-title"><span class="dot"></span>AI Tools Quick Access</div>
  <span class="f-count" id="count"></span>
  <div class="grow"></div>
  <div class="search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input id="q" type="text" placeholder="Search plugins…" autocomplete="off">
    <button class="clr" aria-label="Clear search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>
 </div>
 <div class="f-controls">
  <span class="lbl">Sort</span>
  <div class="selectwrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ><path d="M11 5h10M11 9h7M11 13h4M3 17l3 3 3-3M6 20V4"/></svg><select class="sortsel" id="sort">
    <option value="section">Grouped by section</option>
    <option value="az">Name A–Z</option>
    <option value="za">Name Z–A</option>
    <option value="new">Recently added</option>
    <option value="popular">Most popular</option>
    <option value="installed">Installed first</option>
  </select></div>
  <div class="grow"></div>
  <span class="lbl">View</span>
  <div class="seg" data-seg="view">
   <button class="on" data-v="section"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>Sections</button>
   <button data-v="grid"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Grid</button>
   <button data-v="list"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>List</button>
  </div>
 </div>
 <div class="chips" id="chips"></div>
</div>
<div class="results" id="results"></div>
FDR_TOOLBAR;
        $html .= '</div>';
        $html .= '<script type="application/json" id="ainav-fdr-data">' . json_encode($fdrdata) . '</script>';
        $html .= '<script type="application/json" id="ainav-fdr-cats">' . json_encode($fdrcats) . '</script>';
        $html .= '<script type="application/json" id="ainav-fdr-icons">' . json_encode($fdricons) . '</script>';
        $html .= '<script>';
        $html .= <<<'FDR_JS'


const DATA=JSON.parse(document.getElementById('ainav-fdr-data').textContent);
const CATS=JSON.parse(document.getElementById('ainav-fdr-cats').textContent);
const ICONS=JSON.parse(document.getElementById('ainav-fdr-icons').textContent);
const state={q:'',cat:'all',status:'all',sort:'section',view:'section'};
function esc(s){return (s||'').replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));}
function hl(text){const q=state.q.trim();if(!q)return esc(text);
  const i=text.toLowerCase().indexOf(q.toLowerCase());if(i<0)return esc(text);
  return esc(text.slice(0,i))+'<span class="mark">'+esc(text.slice(i,i+q.length))+'</span>'+esc(text.slice(i+q.length));}
function match(p){
  const q=state.q.trim().toLowerCase();
  if(q){const hay=(p.name+' '+p.desc+' '+p.catLabel).toLowerCase();if(!hay.includes(q))return false;}
  if(state.cat!=='all'&&p.cat!==state.cat)return false;
  if(state.status==='installed'&&!p.installed)return false;
  if(state.status==='available'&&p.installed)return false;
  return true;
}
function ic(n){return ICONS[n]||ICONS.star||'';}
const TICK='<span class="tick"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="10"/><polyline points="9 12 11.5 14.5 16 9"/></svg></span>';
const DOCSVG='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>';
function card(p){
  const off=p.installed?'':' off';
  const DL='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a1 1 0 0 1 1 1v9.586l2.293-2.293a1 1 0 0 1 1.414 1.414l-4 4a1 1 0 0 1-1.414 0l-4-4a1 1 0 0 1 1.414-1.414L11 13.586V4a1 1 0 0 1 1-1z"/><path d="M5 19a1 1 0 0 1 1-1h12a1 1 0 0 1 0 2H6a1 1 0 0 1-1-1z"/></svg>';
  const ver=p.installed?`<span class="vbadge on">v${p.version}</span>${TICK}`:`<button class="dlbtn ainav-pm-action-install" title="Install ${esc(p.name)}" data-component="${p.component}" data-pluginname="${esc(p.name)}" data-credits-required="${p.credits}" data-plugin-id="${p.pluginId||''}">${DL}</button>`;
  const credit=(p.foundation?'<span class="tag founder"><span class="d"></span>Foundation</span>':'')+`<span class="credit">${p.credits.toLocaleString()} Credits ($${p.usd} USD)</span>`;
  const g=CATS[p.cat].group;
  const tag=p.foundation?'':`<span class="tag ${g}"><span class="d"></span>${esc(p.catLabel)}</span>`;
  return `<div class="pc${off}" data-c="${p.component}">
    <div class="pc-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">${ic(p.icon)}</svg></div>
    <div class="pc-main">
      <div class="pc-top"><span class="pc-name">${hl(p.name)}</span><span class="pc-badges">${ver}</span></div>
      <div class="pc-desc">${hl(p.desc)}</div>
      <div class="pc-foot">${tag}${credit}<a class="docs" href="#" onclick="return false">${DOCSVG}Docs</a></div>
    </div></div>`;
}
function foundationBanner(p){
  return `<div class="foundation">
    <div class="f-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">${ic(p.icon)}</svg></div>
    <div class="f-body"><div class="f-kicker">Foundation Plugin — Install First</div>
      <div class="f-name">${hl(p.name)} ${p.installed?TICK:''}</div>
      <div class="f-desc">${hl(p.desc)}</div></div>
    <div class="f-badges">${p.installed?`<span class="vbadge on">v${p.version}</span>`:'<span class="vbadge">Not installed</span>'}
      <a class="docs" href="#" onclick="return false">${DOCSVG}Docs</a></div></div>`;
}
function sortArr(a){
  const s=state.sort;const c=[...a];
  if(s==='az')c.sort((x,y)=>x.name.localeCompare(y.name));
  else if(s==='za')c.sort((x,y)=>y.name.localeCompare(x.name));
  else if(s==='new')c.sort((x,y)=>y.added-x.added);
  else if(s==='popular')c.sort((x,y)=>y.popularity-x.popularity);
  else if(s==='installed')c.sort((x,y)=>(y.installed-x.installed)||x.name.localeCompare(y.name));
  return c;
}
const CATORDER=Object.keys(CATS);
function render(){
  const list=DATA.filter(match);
  document.getElementById('count').textContent=list.length+(list.length===1?' plugin':' plugins');
  const res=document.getElementById('results');
  if(!list.length){res.innerHTML=`<div class="empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg><h3>No plugins match</h3><div>Try a different search or clear the filters.</div><button onclick="clearAll()">Clear all filters</button></div>`;return;}
  if(state.view==='section'&&state.sort==='section'){
    let html='';const groups=[['ai','AI & Learning Intelligence','star'],['admin','Administration & Operations','settings']];
    for(const [g,gl,gi] of groups){
      const cin=CATORDER.filter(k=>CATS[k].group===g);
      let sec='';let secCount=0;let banner='';
      for(const k of cin){
        const items=list.filter(p=>p.cat===k);if(!items.length)continue;secCount+=items.length;
        if(k==='config'){banner=items.map(foundationBanner).join('');continue;}
        const inst=items.filter(p=>p.installed).length;
        sec+=`<div class="section"><div class="sec-head"><div class="sec-ic ${g}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">${ic(CATS[k].icon)}</svg></div><span class="sec-title">${esc(CATS[k].label)}</span><span class="sec-badge">${inst}/${items.length}</span><span class="sec-line"></span></div><div class="grid">${items.map(card).join('')}</div></div>`;
      }
      if(sec||banner){html+=`<div class="supergroup sg-${g}"><div class="sg-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">${ic(gi)}</svg></div><span class="sg-t">${gl}</span><span class="sg-badge">${secCount}</span><span class="sg-line"></span></div>`+banner+sec;}
    }
    res.innerHTML=html;return;
  }
  const arr=sortArr(list);
  res.innerHTML=`<div class="grid ${state.view==='list'?'list':''}">${arr.map(card).join('')}</div>`;
}
function clearAll(){state.q='';state.cat='all';state.status='all';const q=document.getElementById('q');q.value='';q.parentElement.classList.remove('has');syncChips();render();}
function syncChips(){document.querySelectorAll('.chip[data-cat]').forEach(c=>c.classList.toggle('on',c.dataset.cat===state.cat));
  document.querySelectorAll('.chip[data-status]').forEach(c=>c.classList.toggle('on',c.dataset.status===state.status));}
function buildChips(){
  const box=document.getElementById('chips');let h='';
  h+=`<button class="chip ${state.cat==='all'?'on':''}" data-cat="all">All<span class="n">${DATA.length}</span></button>`;
  h+=`<button class="chip" data-status="installed">Installed<span class="n">${DATA.filter(p=>p.installed).length}</span></button>`;
  h+=`<button class="chip" data-status="available">Available<span class="n">${DATA.filter(p=>!p.installed).length}</span></button>`;
  h+=`<span class="chip grp">AI</span>`;
  Object.keys(CATS).filter(k=>CATS[k].group==='ai').forEach(k=>{const n=DATA.filter(p=>p.cat===k).length;if(!n)return;h+=`<button class="chip" data-cat="${k}">${esc(CATS[k].label)}<span class="n">${n}</span></button>`;});
  h+=`<span class="chip grp">Admin</span>`;
  Object.keys(CATS).filter(k=>CATS[k].group==='admin').forEach(k=>{const n=DATA.filter(p=>p.cat===k).length;if(!n)return;h+=`<button class="chip" data-cat="${k}">${esc(CATS[k].label)}<span class="n">${n}</span></button>`;});
  box.innerHTML=h;
  box.querySelectorAll('.chip[data-cat]').forEach(c=>c.onclick=()=>{state.cat=c.dataset.cat;state.status='all';syncChips();render();});
  box.querySelectorAll('.chip[data-status]').forEach(c=>c.onclick=()=>{state.status=state.status===c.dataset.status?'all':c.dataset.status;state.cat='all';syncChips();render();});
}
function init(){
  buildChips();
  const q=document.getElementById('q');
  q.addEventListener('input',()=>{state.q=q.value;q.parentElement.classList.toggle('has',!!q.value);render();});
  document.querySelector('.clr').onclick=()=>{state.q='';q.value='';q.parentElement.classList.remove('has');render();q.focus();};
  document.querySelectorAll('.seg[data-seg="view"] button').forEach(b=>b.onclick=()=>{
    state.view=b.dataset.v;document.querySelectorAll('.seg[data-seg="view"] button').forEach(x=>x.classList.toggle('on',x===b));
    if(state.view!=='section'&&state.sort==='section'){state.sort='popular';document.getElementById('sort').value='popular';}
    render();});
  document.getElementById('sort').onchange=e=>{state.sort=e.target.value;
    if(state.sort==='section'){state.view='section';document.querySelectorAll('.seg[data-seg="view"] button').forEach(x=>x.classList.toggle('on',x.dataset.v==='section'));}
    render();};
  document.addEventListener('keydown',e=>{if(e.key==='Escape'&&document.activeElement===q){clearAll();q.blur();}});
  render();
}
if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init);}else{init();}


FDR_JS;
        $html .= '</script>';
        $html .= '</div>'; // End .ainav-section-content.

        // Hidden data for JavaScript.
        $html .= '<script type="application/json" id="ainav-installed-plugins">' . json_encode($pluginsjson) . '</script>';

        // Toggle section JavaScript.
        $html .= '<script>
        (function () {
            var section = document.getElementById("ainav-tools-section");
            var toggleBtn = document.getElementById("ainav-toggle-tools");
            var storageKey = "ainav_tools_collapsed";
            
            if (!section || !toggleBtn) return;
            
            // Check localStorage for user preference
            var userPref = localStorage.getItem(storageKey);
            if (userPref !== null) {
                if (userPref === "1") {
                    section.classList.add("ainav-section-collapsed");
                } else {
                    section.classList.remove("ainav-section-collapsed");
                }
            }
            
            toggleBtn.addEventListener("click", function (e) {
                e.preventDefault();
                var isCollapsed = section.classList.toggle("ainav-section-collapsed");
                localStorage.setItem(storageKey, isCollapsed ? "1" : "0");
            });
        })();
        </script>';

        // Success modal.
        $html .= '<div class="ainav-modal-overlay" id="ainav-update-success-modal">';
        $html .= '<div class="ainav-modal ainav-success-modal">';
        $html .= '<div class="ainav-success-icon">';
        $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $html .= '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>';
        $html .= '</svg>';
        $html .= '</div>';
        $html .= '<h3 class="ainav-success-title">' . get_string('update_success', 'block_aiplugin_nav') . '</h3>';
        $html .= '<p class="ainav-success-message" id="ainav-success-message"></p>';
        $html .= '<button type="button" class="ainav-btn ainav-btn-primary" id="ainav-success-close">' . get_string('close', 'block_aiplugin_nav') . '</button>';
        $html .= '</div>';
        $html .= '</div>';

        // Credit unlock confirmation modal (v2.3.37).
        // Shown before deducting credits for credit-gated Time Saving Plugins.
        $html .= '<div id="ainav-credit-confirm-overlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.55);z-index:999990;align-items:center;justify-content:center;">';
        $html .= '<div style="background:#fff;border-radius:8px;padding:28px 24px 24px;max-width:440px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,0.28);font-family:inherit;">';
        $html .= '<div style="display:flex;align-items:flex-start;margin-bottom:16px;gap:14px;">';
        $html .= '<div style="flex-shrink:0;width:40px;height:40px;background:#fef3c7;border-radius:50%;display:flex;align-items:center;justify-content:center;">';
        $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" style="width:20px;height:20px;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
        $html .= '</div>';
        $html .= '<div>';
        $html .= '<h3 id="ainav-credit-confirm-title" style="margin:0 0 8px;font-size:16px;font-weight:600;color:#111;"></h3>';
        $html .= '<p id="ainav-credit-confirm-body" style="margin:0;font-size:14px;color:#555;line-height:1.55;"></p>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">';
        $html .= '<button id="ainav-credit-cancel-btn" type="button" style="padding:8px 18px;border:1px solid #d1d5db;border-radius:6px;background:#f9fafb;color:#374151;cursor:pointer;font-size:14px;font-weight:500;">Cancel</button>';
        $html .= '<button id="ainav-credit-confirm-btn" type="button" style="padding:8px 18px;border:none;border-radius:6px;background:#2563eb;color:#fff;cursor:pointer;font-size:14px;font-weight:600;">Unlock &amp; Install</button>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        // All-up-to-date toast (v2.3.81): shown inside the block when version check finds no updates.
        // Uses var(--primary) so it inherits the Moodle site's theme colour, not a hardcoded value.
        $html .= '<div class="ainav-uptodate-toast" id="ainav-uptodate-toast" role="status" aria-live="polite">';
        $html .= '<div class="ainav-uptodate-toast-icon">';
        $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">';
        $html .= '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>';
        $html .= '</svg>';
        $html .= '</div>';
        $html .= '<span class="ainav-uptodate-toast-msg">' . get_string('no_updates', 'block_aiplugin_nav') . '</span>';
        $html .= '<button type="button" class="ainav-uptodate-toast-close" id="ainav-uptodate-toast-close" aria-label="' . get_string('close', 'block_aiplugin_nav') . '">';
        $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
        $html .= '</button>';
        $html .= '</div>';

        // Plugin Update Popup (v2.3.83): full-screen modal shown after "Check for Updates" completes.
        // Orange gradient header when updates exist; green when all plugins are current.
        $html .= '<div id="ainav-update-popup-overlay" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.55);backdrop-filter:blur(6px);z-index:999996;align-items:center;justify-content:center;padding:16px;box-sizing:border-box;">';
        $html .= '<div class="ainav-update-popup-modal">';
        // Gradient header — colour set dynamically by JS.
        $html .= '<div class="ainav-update-popup-header" id="ainav-update-popup-header" style="background:linear-gradient(135deg, #6b7280 0%, #4b5563 100%);">';
        $html .= '<button type="button" class="ainav-update-popup-close" id="ainav-update-popup-close" aria-label="' . get_string('close', 'block_aiplugin_nav') . '">';
        $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
        $html .= '</button>';
        $html .= '<div class="ainav-update-popup-header-inner">';
        $html .= '<div class="ainav-update-popup-icon-wrap">';
        // Bell icon (shown when updates exist).
        $html .= '<svg id="ainav-update-popup-icon-bell" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
        // Shield-check icon (shown when up-to-date); hidden by default.
        $html .= '<svg id="ainav-update-popup-icon-shield" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11.5 14.5 16 9"/></svg>';
        $html .= '</div>';
        $html .= '<div>';
        $html .= '<h2 class="ainav-update-popup-title" id="ainav-update-popup-title">Checking...</h2>';
        $html .= '<p class="ainav-update-popup-subtitle" id="ainav-update-popup-subtitle"></p>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        // Body.
        $html .= '<div class="ainav-update-popup-body">';
        // Plugin list (shown when updates exist).
        $html .= '<div id="ainav-update-popup-list" class="ainav-update-popup-list"></div>';
        // Up-to-date state (hidden by default; shown when no updates).
        $html .= '<div id="ainav-update-popup-uptodate" class="ainav-update-popup-uptodate" style="display:none;">';
        $html .= '<div class="ainav-update-popup-sparkle">';
        $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z"/></svg>';
        $html .= '</div>';
        $html .= '<p class="ainav-update-popup-uptodate-msg">All your Moodle plugins are running the latest available versions.</p>';
        $html .= '</div>';
        // Footer buttons.
        // Left  — "Close" when updates are pending, "Re-check" when everything is current.
        // Right — "Update All Plugins" when pending (triggers the update flow + closes),
        // "Perfect, Thanks!" when current (just closes).
        // Both labels/icons are set dynamically by showUpdatePopup() below.
        $html .= '<div class="ainav-update-popup-footer">';
        $html .= '<button type="button" id="ainav-update-popup-recheck" class="ainav-update-popup-btn ainav-update-popup-btn-outline">';
        $html .= '<svg id="ainav-update-popup-recheck-icon-refresh" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>';
        $html .= '<svg id="ainav-update-popup-recheck-icon-x" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
        $html .= '<span id="ainav-update-popup-recheck-text">Re-check</span>';
        $html .= '</button>';
        $html .= '<button type="button" id="ainav-update-popup-confirm" class="ainav-update-popup-btn ainav-update-popup-btn-primary" style="background:linear-gradient(135deg, #6b7280, #4b5563);border:none;" data-has-updates="0">';
        $html .= '<svg id="ainav-update-popup-confirm-icon-got" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';
        $html .= '<svg id="ainav-update-popup-confirm-icon-ok" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;"><polyline points="20 6 9 17 4 12"/></svg>';
        $html .= '<span id="ainav-update-popup-confirm-text">Got It</span>';
        $html .= '</button>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Get settings URL for a plugin by component.
     *
     * @param string $component Plugin component.
     * @return string|null Settings URL, or null if unavailable.
     */
    private function get_plugin_settings_url($component) {
        $registry = $this->get_master_plugin_registry();
        if (isset($registry[$component]) && !empty($registry[$component]['settings_url'])) {
            return $registry[$component]['settings_url'];
        }
        return null;
    }

    /**
     * Render a single plugin card with status label, update/install button, and beautiful tooltip.
     */
    /**
     * Build a clean version label for an installed plugin.
     * Falls back to the numeric version when the release string is missing
     * (prevents "v?"), and strips a leading "v" to avoid "vv2.4.51".
     *
     * @param array $plugin
     * @return string
     */
    private function format_version_label($plugin) {
        $ver = isset($plugin['installed_version']) ? (string)$plugin['installed_version'] : '';
        if ($ver === '' && !empty($plugin['installed_numeric_version']) && $plugin['installed_numeric_version'] !== '0') {
            $ver = (string)$plugin['installed_numeric_version'];
        }
        $ver = ltrim($ver, 'vV');
        return $ver !== '' ? $ver : '?';
    }

    /**
     * Render a plugin card.
     *
     * @param array $plugin Plugin information.
     * @return string Card HTML.
     */
    private function render_plugin_card($plugin) {
        global $CFG;
        $isinstalled = !empty($plugin['is_installed']);
        $cardclass = 'ainav-pm-card ainav-pm-card-compact' . ($isinstalled ? '' : ' ainav-pm-card-notinstalled');
        $description = isset($plugin['description']) ? $plugin['description'] : '';
        $access = isset($plugin['access']) ? $plugin['access'] : '';
        $docsurl = $this->get_plugin_docs_url($plugin['component']);

        $html = '<div class="' . $cardclass . '" data-component="' . htmlspecialchars($plugin['component']) . '" data-installed="' . ($isinstalled ? '1' : '0') . '">';

        // Icon.
        $html .= '<div class="ainav-pm-icon ainav-pm-icon-compact">';
        $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $html .= $this->get_icon_svg($plugin['icon']);
        $html .= '</svg>';
        $html .= '</div>';

        // Plugin info.
        $html .= '<div class="ainav-pm-info">';
        $html .= '<div class="ainav-pm-name">' . htmlspecialchars($plugin['name']) . '</div>';
        $html .= '<div class="ainav-pm-version">';

        if ($isinstalled) {
            $html .= '<span class="ainav-pm-version-label">v' . htmlspecialchars($this->format_version_label($plugin)) . '</span>';
            $html .= '<span class="ainav-pm-status-label" data-component="' . htmlspecialchars($plugin['component']) . '"></span>';
        } else {
            $html .= '<span class="ainav-pm-notinstalled-label">' . get_string('not_installed', 'block_aiplugin_nav') . '</span>';
            $html .= '<span class="ainav-pm-status-label" data-component="' . htmlspecialchars($plugin['component']) . '"></span>';
        }

        // Add docs link.
        if (!empty($docsurl)) {
            $html .= '<a href="' . htmlspecialchars($docsurl) . '" class="ainav-pm-docs-link-compact" target="_blank" rel="noopener" title="' . get_string('view_docs', 'block_aiplugin_nav') . '">';
            $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $html .= '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>';
            $html .= '</svg>';
            $html .= get_string('docs', 'block_aiplugin_nav');
            $html .= '</a>';
        }

        $html .= '</div>';
        $html .= '</div>';

        // Action column - show settings icon and green tick when installed.
        $gotourl = isset($plugin['goto_url']) ? $plugin['goto_url'] : '';
        $settingsurl = $this->get_plugin_settings_url($plugin['component']);

        $html .= '<div class="ainav-pm-action-col">';

        if ($isinstalled) {
            // Update icon (hidden until update check) - shown when update is available.
            $html .= '<span class="ainav-pm-action-btn ainav-pm-action-update" data-component="' . htmlspecialchars($plugin['component']) . '" title="' . get_string('update_available', 'block_aiplugin_nav') . '" style="display:none;">';
            $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $html .= '<path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/>';
            $html .= '</svg>';
            $html .= '</span>';

            // Go to icon (only for installed plugins with direct access URL - not course/activity specific).
            if (!empty($gotourl)) {
                $html .= '<a href="' . $CFG->wwwroot . htmlspecialchars($gotourl) . '" class="ainav-pm-action-btn ainav-pm-action-goto" title="' . get_string('go_to_plugin', 'block_aiplugin_nav') . '">';
                $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                $html .= '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>';
                $html .= '</svg>';
                $html .= '</a>';
            }

            // Settings icon (only for plugins with settings URL).
            if (!empty($settingsurl)) {
                $html .= '<a href="' . $CFG->wwwroot . htmlspecialchars($settingsurl) . '" class="ainav-pm-action-btn ainav-pm-action-settings" title="' . get_string('settings', 'block_aiplugin_nav') . '">';
                $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                $html .= '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>';
                $html .= '</svg>';
                $html .= '</a>';
            }

            // Installed: Green tick circle with 1px border outline.
            $html .= '<span class="ainav-pm-action-btn ainav-pm-action-installed" title="' . get_string('installed', 'block_aiplugin_nav') . '">';
            $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">';
            $html .= '<circle cx="12" cy="12" r="10"/><polyline points="9 12 11.5 14.5 16 9"/>';
            $html .= '</svg>';
            $html .= '</span>';
        } else if (!empty($plugin['install_first'])) {
            // Install First icon for Central Config (not installed).
            $html .= '<span class="ainav-pm-action-btn ainav-pm-action-installfirst" title="' . get_string('install_first', 'block_aiplugin_nav') . '">';
            $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $html .= '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>';
            $html .= '</svg>';
            $html .= '</span>';
        } else {
            // Not installed - show INSTALL button (auto-install via AJAX).
            // Credit-gated plugins get extra data attributes so the JS can show a confirm popup.
            $installcredits = (int)($plugin['credits_required'] ?? 0);
            $installpluginid = htmlspecialchars($plugin['plugin_name'] ?? '');
            if ($installcredits > 0) {
                $installtitle = 'Unlock &amp; Install (' . number_format($installcredits) . ' credits)';
                $creditclass  = ' ainav-pm-credit-gate';
            } else {
                $installtitle = get_string('auto_install', 'block_aiplugin_nav');
                $creditclass  = '';
            }
            $html .= '<button type="button" class="ainav-pm-action-btn ainav-pm-action-install' . $creditclass . '"'
                . ' data-component="' . htmlspecialchars($plugin['component']) . '"'
                . ' data-pluginname="' . htmlspecialchars($plugin['name']) . '"'
                . ' data-credits-required="' . $installcredits . '"'
                . ' data-plugin-id="' . $installpluginid . '"'
                . ' title="' . $installtitle . '" style="cursor:pointer;">';
            $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $html .= '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>';
            $html .= '</svg>';
            $html .= '</button>';
        }
        $html .= '</div>';

        // Beautiful tooltip with description and access info.
        if (!empty($description) || !empty($access)) {
            $html .= '<div class="ainav-tooltip">';
            $html .= '<div class="ainav-tooltip-arrow"></div>';
            $html .= '<div class="ainav-tooltip-content">';
            $html .= '<div class="ainav-tooltip-header">';
            $html .= '<div class="ainav-tooltip-icon">';
            $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $html .= $this->get_icon_svg($plugin['icon']);
            $html .= '</svg>';
            $html .= '</div>';
            $html .= '<div class="ainav-tooltip-title">' . htmlspecialchars($plugin['name']) . '</div>';
            $html .= '</div>';
            if (!empty($description)) {
                $html .= '<div class="ainav-tooltip-desc">' . htmlspecialchars($description) . '</div>';
            }
            if (!empty($access)) {
                $html .= '<div class="ainav-tooltip-access">';
                $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ainav-tooltip-access-icon">';
                $html .= $this->get_icon_svg('map-pin');
                $html .= '</svg>';
                $html .= '<span>' . htmlspecialchars($access) . '</span>';
                $html .= '</div>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render a single plugin card in row format with docs link.
     *
     * @param array $plugin Plugin information.
     * @return string Card HTML.
     */
    private function render_plugin_card_row($plugin) {
        $isinstalled = !empty($plugin['is_installed']);
        $cardclass = 'ainav-pm-card-row' . ($isinstalled ? '' : ' ainav-pm-card-notinstalled');
        $docsurl = $this->get_plugin_docs_url($plugin['component']);

        $html = '<div class="' . $cardclass . '" data-component="' . htmlspecialchars($plugin['component']) . '" data-installed="' . ($isinstalled ? '1' : '0') . '">';

        // Left side: Icon + Name.
        $html .= '<div class="ainav-pm-card-left">';
        $html .= '<div class="ainav-pm-icon-row">';
        $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $html .= $this->get_icon_svg($plugin['icon']);
        $html .= '</svg>';
        $html .= '</div>';
        $html .= '<span class="ainav-pm-name-row">' . htmlspecialchars($plugin['name']) . '</span>';
        $html .= '</div>';

        // Right side: Docs Link + Version Badge.
        $html .= '<div class="ainav-pm-card-right">';

        // Docs Link.
        if (!empty($docsurl)) {
            $html .= '<a href="' . htmlspecialchars($docsurl) . '" class="ainav-pm-docs-link" target="_blank" rel="noopener">';
            $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $html .= '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>';
            $html .= '</svg>';
            $html .= get_string('view_docs', 'block_aiplugin_nav');
            $html .= '</a>';
        }

        // Version/Status Badge.
        if ($isinstalled) {
            $html .= '<span class="ainav-pm-version-badge">v' . htmlspecialchars($this->format_version_label($plugin)) . '</span>';
        } else {
            $html .= '<span class="ainav-pm-notinstalled-badge">' . get_string('not_installed', 'block_aiplugin_nav') . '</span>';
        }

        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Get the documentation URL for a plugin.
     *
     * @param string $component Plugin component.
     * @return string|null Documentation URL, or null if unavailable.
     */
    public function get_plugin_docs_url($component) {
        $docsurls = [
            'quiz_aigrader' => 'https://lms-labs.com/docs/ai-grader',
            'local_aiquizmaker' => 'https://lms-labs.com/docs/ai-quiz-maker',
            'mod_contentcreator' => 'https://lms-labs.com/docs/ai-content-creator',
            'mod_aiknowledgecheck' => 'https://lms-labs.com/docs/ai-knowledge-check',
            'mod_aiactivities' => 'https://lms-labs.com/docs/ai-learning-activities',
            'mod_aiquiz' => 'https://lms-labs.com/docs/ai-quiz',
            'mod_practicalassessment' => 'https://lms-labs.com/docs/ai-practical-assessment',
            'mod_verifyid' => 'https://lms-labs.com/docs/ai-verify-id',
            'quizaccess_webcamproctor' => 'https://lms-labs.com/docs/webcam-proctoring',
            'mod_aivideoactivity' => 'https://lms-labs.com/docs/ai-video-activity',
            'mod_aivideoconf' => 'https://lms-labs.com/docs/ai-video-conference',
            'local_rtocompliance' => 'https://lms-labs.com/docs/rto-compliance',
            'local_moodlesupport' => 'https://lms-labs.com/docs/ai-support',
            'block_aigrader_dashboard' => 'https://lms-labs.com/docs/dashboard-block',
            'block_aiplugin_nav' => 'https://lms-labs.com/docs/ai-dashboard-quick-links',
            'quizaccess_aigrader' => 'https://lms-labs.com/docs/quiz-access-rule',
            'enrol_prerequisite' => 'https://lms-labs.com/docs/course-prerequisite',
            'local_videocompress' => 'https://lms-labs.com/docs/video-compress',
            'local_scormcompress' => 'https://lms-labs.com/docs/scorm-compress',
            'local_mediaoptimiser' => 'https://lms-labs.com/docs/media-optimiser',
            'local_groupmanager' => 'https://lms-labs.com/docs/groups-management',
            'block_my_progress' => 'https://lms-labs.com/docs/my-progress',
            'block_my_students_progress' => 'https://lms-labs.com/docs/my-students-progress',
            'local_activitynav' => 'https://lms-labs.com/docs/activity-navigation',
            'local_courseversion' => 'https://lms-labs.com/docs/course-version',
            'availability_groupmanager' => 'https://lms-labs.com/docs/groups-management',
            'local_sitefont' => 'https://lms-labs.com/docs/change-site-font',
            'local_cohortbranding' => 'https://lms-labs.com/docs/cohort-branding',
            'gradingform_benchmarks' => 'https://lms-labs.com/docs/assignment-benchmarks',
            'auth_simple2fa' => 'https://lms-labs.com/docs/simple-2fa',
            'local_groupcap' => 'https://lms-labs.com/docs/group-membership-limit',
            'local_courseavailabilitydelay' => 'https://lms-labs.com/docs/course-availability-delay',
            'local_aiconfig' => 'https://lms-labs.com/docs/ai-central-config',
            'format_aicourse' => 'https://lms-labs.com/docs/ai-course-format',
            'report_performanceintel' => 'https://lms-labs.com/docs/speed',
            'assignfeedback_aipdf' => 'https://lms-labs.com/docs/ai-pdf-grader',
            'mod_learningmapping' => 'https://lms-labs.com/docs/learning-mapping',
            'plagiarism_docguard' => 'https://lms-labs.com/docs/doc-guard',
            'plagiarism_essayguard' => 'https://lms-labs.com/docs/essay-guard',
            'local_aiquizremedial' => 'https://lms-labs.com/docs/ai-quiz-remedial',
            'local_ailogin' => 'https://lms-labs.com/docs/ai-login-designer',
            'mod_smartworkbook' => 'https://lms-labs.com/docs/smart-workbook',
            'mod_slideshow' => 'https://lms-labs.com/docs/slideshow',
            'mod_slides' => 'https://lms-labs.com/docs/slides',
            'mod_courseinfo' => 'https://lms-labs.com/docs/course-information',
            'mod_productexplainer' => 'https://lms-labs.com/docs/product-explainer',
            'local_workshops' => 'https://lms-labs.com/docs/workshop-scheduler',
            'local_paymentunlockassign' => 'https://lms-labs.com/docs/payment-unlock-assignment',
            'paygw_paddle' => 'https://lms-labs.com/docs/paddle-payment',
            'local_chirpvoice' => 'https://lms-labs.com/docs/ai-voiceover',
            'local_studentemail' => 'https://lms-labs.com/docs/student-email-manager',
            'auth_studentemail' => 'https://lms-labs.com/docs/student-email-imap-auth',
            'local_beacon' => 'https://lms-labs.com/docs/beacon',
            'local_rplkit' => 'https://lms-labs.com/docs/rpl-kit',
            'local_lmshomepage' => 'https://lms-labs.com/docs/lms-home-page',
            'local_downalert' => 'https://lms-labs.com/docs/site-down-alert',
            'block_rtocompliance' => 'https://lms-labs.com/docs/rto-compliance',
            'local_campion' => 'https://lms-labs.com/docs/campion-integration',
            'local_completionsuspend' => 'https://lms-labs.com/docs/completion-auto-suspend',
            'local_custompage' => 'https://lms-labs.com/docs/custom-pages',
            'local_recertify' => 'https://lms-labs.com/docs/course-recertification',
            'block_studentactivity' => 'https://lms-labs.com/docs/student-activity',
            'mod_aitrainingsim' => 'https://lms-labs.com/docs/ai-training-simulation',
            'mod_workplacetask' => 'https://lms-labs.com/docs/workplace-task',
        ];

        return isset($docsurls[$component]) ? $docsurls[$component] : '';
    }

    /**
     * Get the Site Quick Links registry.
     */
    private function get_site_links_registry() {
        global $CFG;

        return [
            'admin' => [
                'label' => get_string('site_admin', 'block_aiplugin_nav'),
                'capability' => 'moodle/site:config',
                'items' => [
                    [
                        'name' => get_string('site_admin', 'block_aiplugin_nav'),
                        'url' => $CFG->wwwroot . '/admin/search.php',
                        'icon' => 'sliders',
                        'hide_for_lmshsadmin' => true,
                    ],
                    [
                        'name' => get_string('manage_users', 'block_aiplugin_nav'),
                        'url' => $CFG->wwwroot . '/admin/user.php',
                        'icon' => 'users',
                    ],
                    [
                        'name' => get_string('manage_courses', 'block_aiplugin_nav'),
                        'url' => $CFG->wwwroot . '/course/management.php',
                        'icon' => 'book',
                    ],
                    [
                        'name' => get_string('cohorts', 'block_aiplugin_nav'),
                        'url' => $CFG->wwwroot . '/cohort/index.php',
                        'icon' => 'users-2',
                    ],
                    [
                        'name' => get_string('reports', 'block_aiplugin_nav'),
                        'url' => $CFG->wwwroot . '/admin/category.php?category=reports',
                        'icon' => 'bar-chart-2',
                    ],
                    [
                        'name' => get_string('themes', 'block_aiplugin_nav'),
                        'url' => $CFG->wwwroot . '/admin/themeselector.php',
                        'icon' => 'palette',
                        'hide_for_lmshsadmin' => true,
                    ],
                ],
            ],
            'user' => [
                'label' => get_string('my_profile', 'block_aiplugin_nav'),
                'capability' => null,
                'items' => [
                    [
                        'name' => get_string('dashboard', 'block_aiplugin_nav'),
                        'url' => $CFG->wwwroot . '/my/',
                        'icon' => 'layout-dashboard',
                    ],
                    [
                        'name' => get_string('my_courses', 'block_aiplugin_nav'),
                        'url' => $CFG->wwwroot . '/my/courses.php',
                        'icon' => 'graduation-cap',
                    ],
                    [
                        'name' => get_string('my_profile', 'block_aiplugin_nav'),
                        'url' => $CFG->wwwroot . '/user/profile.php',
                        'icon' => 'user',
                    ],
                    [
                        'name' => get_string('calendar', 'block_aiplugin_nav'),
                        'url' => $CFG->wwwroot . '/calendar/view.php',
                        'icon' => 'calendar-icon',
                    ],
                    [
                        'name' => get_string('messages', 'block_aiplugin_nav'),
                        'url' => $CFG->wwwroot . '/message/index.php',
                        'icon' => 'message-square',
                    ],
                    [
                        'name' => get_string('private_files', 'block_aiplugin_nav'),
                        'url' => $CFG->wwwroot . '/user/files.php',
                        'icon' => 'folder',
                    ],
                    [
                        'name' => get_string('preferences', 'block_aiplugin_nav'),
                        'url' => $CFG->wwwroot . '/user/preferences.php',
                        'icon' => 'settings-2',
                    ],
                ],
            ],
        ];
    }

    /**
     * Get site quick links available to the current user.
     *
     * @return array Flat list of available quick links.
     */
    public function get_available_site_links() {
        global $CFG, $USER, $DB;

        $context = context_system::instance();
        $registry = $this->get_site_links_registry();
        $islmshsadmin = $this->user_has_role_shortname($USER->id, 'lmshsadmin');
        $links = [];

        foreach ($registry as $groupid => $group) {
            if (!empty($group['capability']) && !has_capability($group['capability'], $context)) {
                continue;
            }
            foreach ($group['items'] as $link) {
                if ($islmshsadmin && !empty($link['hide_for_lmshsadmin'])) {
                    continue;
                }
                $link['group'] = $groupid;
                $link['groupname'] = $group['label'];
                $links[] = $link;
            }
        }

        if (
            $this->is_plugin_installed('local', 'studentemail') &&
                !empty(get_config('local_studentemail', 'mailbox_enabled')) &&
                $DB->get_manager()->table_exists('local_studentemail_accounts') &&
                $DB->record_exists('local_studentemail_accounts', [
                    'userid' => $USER->id,
                    'status' => 'active',
                ])
        ) {
            $links[] = [
                'name' => get_string('my_email', 'block_aiplugin_nav'),
                'url' => $CFG->wwwroot . '/local/studentemail/mailbox.php',
                'icon' => 'mail',
                'group' => 'user',
                'groupname' => get_string('my_profile', 'block_aiplugin_nav'),
            ];
        }

        return $links;
    }

    /**
     * Get available icons for the icon picker.
     */
    private function get_available_icons() {
        return ['link', 'home', 'star', 'heart', 'bookmark', 'globe', 'zap', 'briefcase',
                     'mail', 'phone', 'map-pin', 'image', 'music', 'film', 'award', 'coffee',
                     'shopping-cart', 'book', 'users', 'folder', 'settings', 'external-link'];
    }

    /**
     * Get user's custom links from preferences.
     */
    public function get_custom_links() {
        global $USER;
        $linksjson = get_user_preferences('block_aiplugin_nav_custom_links', '[]', $USER->id);
        $links = json_decode($linksjson, true);
        return is_array($links) ? $links : [];
    }

    /**
     * Get user's custom reports from preferences.
     */
    public function get_custom_reports() {
        global $USER;
        $reportsjson = get_user_preferences('block_aiplugin_nav_custom_reports', '[]', $USER->id);
        $reports = json_decode($reportsjson, true);
        return is_array($reports) ? $reports : [];
    }

    /**
     * Render a "My Email" quicklink for students who have an active provisioned mailbox.
     * Shown automatically when:
     *   - local_studentemail is installed
     *   - mailbox_enabled admin setting is on
     *   - the current user has an active row in local_studentemail_accounts
     */
    private function get_student_email_quicklink(): ?array {
        global $CFG, $USER, $DB;

        if (!$this->is_plugin_installed('local', 'studentemail')) {
            return null;
        }

        $mailboxenabled = get_config('local_studentemail', 'mailbox_enabled');
        if (empty($mailboxenabled)) {
            return null;
        }

        if (!$DB->get_manager()->table_exists('local_studentemail_accounts')) {
            return null;
        }

        $account = $DB->get_record('local_studentemail_accounts', [
            'userid' => $USER->id,
            'status' => 'active',
        ]);
        if (!$account) {
            return null;
        }

        return [
            'name' => get_string('my_email', 'block_aiplugin_nav'),
            'url' => $CFG->wwwroot . '/local/studentemail/mailbox.php',
            'icon' => 'mail',
        ];
    }

    /**
     * Render a "My Email" quicklink for students who have an active provisioned mailbox.
     */
    private function render_student_email_quicklink(): string {
        $link = $this->get_student_email_quicklink();
        if (!$link) {
            return '';
        }

        $html  = '<a href="' . $link['url'] . '" class="ainav-site-link-card ainav-myemail-link">';
        $html .= '<div class="ainav-site-link-icon">';
        $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $html .= $this->get_icon_svg($link['icon']);
        $html .= '</svg>';
        $html .= '</div>';
        $html .= '<div class="ainav-site-link-name">' . $link['name'] . '</div>';
        $html .= '</a>';

        return $html;
    }

    /**
     * Render the Site Quick Links section.
     */
    private function render_site_links_section() {
        global $USER;
        $context = context_system::instance();
        $registry = $this->get_site_links_registry();
        $customlinks = $this->get_custom_links();

        // Check if user has lmshsadmin role.
        $islmshsadmin = $this->user_has_role_shortname($USER->id, 'lmshsadmin');

        $html = '<div class="ainav-site-links-section">';
        $html .= '<div class="ainav-site-links-header">';
        $html .= '<span class="ainav-site-links-title">' . get_string('site_quick_links', 'block_aiplugin_nav') . '</span>';
        $html .= '</div>';
        $html .= '<div class="ainav-site-links-grid">';

        foreach ($registry as $groupid => $group) {
            // Check capability for admin group.
            if (!empty($group['capability']) && !has_capability($group['capability'], $context)) {
                continue;
            }

            foreach ($group['items'] as $link) {
                // Skip items hidden for lmshsadmin role.
                if ($islmshsadmin && !empty($link['hide_for_lmshsadmin'])) {
                    continue;
                }

                $html .= '<a href="' . $link['url'] . '" class="ainav-site-link-card">';
                $html .= '<div class="ainav-site-link-icon">';
                $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                $html .= $this->get_icon_svg($link['icon']);
                $html .= '</svg>';
                $html .= '</div>';
                $html .= '<div class="ainav-site-link-name">' . $link['name'] . '</div>';
                $html .= '</a>';
            }
        }

        // My Email — auto-shown for students with an active provisioned mailbox.
        $html .= $this->render_student_email_quicklink();

        // Render custom links.
        foreach ($customlinks as $index => $link) {
            $html .= '<div class="ainav-site-link-card ainav-custom-link" data-index="' . $index . '">';
            $html .= '<a href="' . htmlspecialchars($link['url'], ENT_QUOTES, 'UTF-8') . '" class="ainav-custom-link-content" target="_blank" rel="noopener">';
            $html .= '<div class="ainav-site-link-icon">';
            $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $html .= $this->get_icon_svg($link['icon']);
            $html .= '</svg>';
            $html .= '</div>';
            $html .= '<div class="ainav-site-link-name">' . htmlspecialchars($link['name'], ENT_QUOTES, 'UTF-8') . '</div>';
            $html .= '</a>';
            $html .= '<button type="button" class="ainav-delete-link" data-index="' . $index . '" title="' . get_string('delete_link', 'block_aiplugin_nav') . '">';
            $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $html .= $this->get_icon_svg('x');
            $html .= '</svg>';
            $html .= '</button>';
            $html .= '</div>';
        }

        // Create Link button.
        $html .= '<button type="button" class="ainav-create-link-btn" id="ainav-create-link-btn">';
        $html .= '<div class="ainav-create-link-icon">';
        $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $html .= $this->get_icon_svg('plus');
        $html .= '</svg>';
        $html .= '</div>';
        $html .= '<div class="ainav-create-link-text">' . get_string('create_link', 'block_aiplugin_nav') . '</div>';
        $html .= '</button>';

        $html .= '</div>';
        $html .= '</div>';

        // Add the modal.
        $html .= $this->render_create_link_modal();

        return $html;
    }

    /**
     * Render the Cache Management section (admin only).
     */
    public function render_cache_management_section() {
        global $DB;
        $context = context_system::instance();

        // Only show to site admins.
        if (!has_capability('moodle/site:config', $context)) {
            return '';
        }

        // Get last purge times.
        $lastmanual = $DB->get_record_sql(
            "SELECT * FROM {block_aiplugin_nav_purge} WHERE purge_type = 'manual' ORDER BY purged_at DESC LIMIT 1"
        );
        $lastscheduled = $DB->get_record_sql(
            "SELECT * FROM {block_aiplugin_nav_purge} WHERE purge_type = 'scheduled' ORDER BY purged_at DESC LIMIT 1"
        );

        // Get schedule settings.
        $scheduleenabled = get_config('block_aiplugin_nav', 'purge_schedule_enabled');
        $scheduletype = get_config('block_aiplugin_nav', 'purge_schedule_type') ?: 'daily';
        $scheduletime = get_config('block_aiplugin_nav', 'purge_schedule_time') ?: '03:00';
        $scheduleday = get_config('block_aiplugin_nav', 'purge_schedule_day') ?: 0;

        // Format times.
        $neverstr = get_string('never', 'block_aiplugin_nav');
        $lastmanualstr = $lastmanual ? userdate($lastmanual->purged_at, get_string('strftimedatetimeshort', 'langconfig')) : $neverstr;
        $lastscheduledstr = $lastscheduled ? userdate($lastscheduled->purged_at, get_string('strftimedatetimeshort', 'langconfig')) : $neverstr;

        $html = '<div class="ainav-cache-section">';
        $html .= '<div class="ainav-cache-header">';
        $html .= '<span class="ainav-cache-title">' . get_string('cache_management', 'block_aiplugin_nav') . '</span>';
        $html .= '</div>';

        $html .= '<div class="ainav-cache-content">';

        // Main row with button and status.
        $html .= '<div class="ainav-cache-main">';

        // Purge button with text.
        $html .= '<button type="button" class="ainav-purge-btn" id="ainav-purge-caches-btn">';
        $html .= '<svg class="ainav-purge-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $html .= '<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>';
        $html .= '</svg>';
        $html .= '<span>' . get_string('purge_caches_btn', 'block_aiplugin_nav') . '</span>';
        $html .= '</button>';

        // Status and schedule in a group.
        $html .= '<div class="ainav-cache-info">';
        $html .= '<div class="ainav-cache-times">';
        $html .= '<span class="ainav-time-item"><span class="ainav-time-label">' . get_string('last_manual_short', 'block_aiplugin_nav') . '</span> <span id="ainav-last-manual">' . $lastmanualstr . '</span></span>';
        $html .= '<span class="ainav-time-sep">|</span>';
        $html .= '<span class="ainav-time-item"><span class="ainav-time-label">' . get_string('last_scheduled_short', 'block_aiplugin_nav') . '</span> <span id="ainav-last-scheduled">' . $lastscheduledstr . '</span></span>';
        $html .= '</div>';
        $html .= '<button type="button" class="ainav-schedule-link" id="ainav-schedule-btn">';
        $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $html .= '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>';
        $html .= '</svg>';
        $html .= '<span>' . get_string('configure_schedule', 'block_aiplugin_nav') . '</span>';
        $html .= '</button>';
        $html .= '</div>';

        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        // Schedule modal.
        $html .= $this->render_schedule_modal($scheduleenabled, $scheduletype, $scheduletime, $scheduleday);

        return $html;
    }

    /**
     * Render the Schedule Modal.
     *
     * @param bool $enabled Whether the schedule is enabled.
     * @param string $type Schedule type.
     * @param string $time Schedule time.
     * @param int $day Day of the week.
     * @return string Modal HTML.
     */
    private function render_schedule_modal($enabled, $type, $time, $day) {
        $days = [
            0 => get_string('sunday', 'block_aiplugin_nav'),
            1 => get_string('monday', 'block_aiplugin_nav'),
            2 => get_string('tuesday', 'block_aiplugin_nav'),
            3 => get_string('wednesday', 'block_aiplugin_nav'),
            4 => get_string('thursday', 'block_aiplugin_nav'),
            5 => get_string('friday', 'block_aiplugin_nav'),
            6 => get_string('saturday', 'block_aiplugin_nav'),
        ];

        $html = '<div class="ainav-modal-overlay" id="ainav-schedule-overlay">';
        $html .= '<div class="ainav-modal">';
        $html .= '<div class="ainav-modal-header">';
        $html .= '<h3 class="ainav-modal-title">' . get_string('schedule_settings', 'block_aiplugin_nav') . '</h3>';
        $html .= '<button type="button" class="ainav-modal-close" id="ainav-schedule-close">';
        $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $html .= $this->get_icon_svg('x');
        $html .= '</svg>';
        $html .= '</button>';
        $html .= '</div>';
        $html .= '<div class="ainav-modal-body">';

        // Enable toggle.
        $html .= '<div class="ainav-form-group ainav-toggle-group">';
        $html .= '<label class="ainav-form-label">' . get_string('schedule_enabled', 'block_aiplugin_nav') . '</label>';
        $html .= '<label class="ainav-toggle">';
        $checked = $enabled ? 'checked' : '';
        $html .= '<input type="checkbox" id="ainav-schedule-enabled" ' . $checked . '>';
        $html .= '<span class="ainav-toggle-slider"></span>';
        $html .= '</label>';
        $html .= '</div>';

        // Schedule type.
        $html .= '<div class="ainav-form-group">';
        $html .= '<label class="ainav-form-label">' . get_string('schedule_type', 'block_aiplugin_nav') . '</label>';
        $html .= '<select id="ainav-schedule-type" class="ainav-form-select">';
        $html .= '<option value="daily"' . ($type === 'daily' ? ' selected' : '') . '>' . get_string('schedule_daily', 'block_aiplugin_nav') . '</option>';
        $html .= '<option value="weekly"' . ($type === 'weekly' ? ' selected' : '') . '>' . get_string('schedule_weekly', 'block_aiplugin_nav') . '</option>';
        $html .= '</select>';
        $html .= '</div>';

        // Day of week (for weekly).
        $html .= '<div class="ainav-form-group" id="ainav-day-group" style="' . ($type === 'weekly' ? '' : 'display:none;') . '">';
        $html .= '<label class="ainav-form-label">' . get_string('schedule_day', 'block_aiplugin_nav') . '</label>';
        $html .= '<select id="ainav-schedule-day" class="ainav-form-select">';
        foreach ($days as $daynum => $dayname) {
            $selected = ($day == $daynum) ? ' selected' : '';
            $html .= '<option value="' . $daynum . '"' . $selected . '>' . $dayname . '</option>';
        }
        $html .= '</select>';
        $html .= '</div>';

        // Time.
        $html .= '<div class="ainav-form-group">';
        $html .= '<label class="ainav-form-label">' . get_string('schedule_time', 'block_aiplugin_nav') . '</label>';
        $html .= '<input type="time" id="ainav-schedule-time" class="ainav-form-input" value="' . $time . '">';
        $html .= '</div>';

        $html .= '</div>';
        $html .= '<div class="ainav-modal-footer">';
        $html .= '<button type="button" class="ainav-btn ainav-btn-secondary" id="ainav-schedule-cancel">' . get_string('cancel', 'block_aiplugin_nav') . '</button>';
        $html .= '<button type="button" class="ainav-btn ainav-btn-primary" id="ainav-save-schedule">' . get_string('save_schedule', 'block_aiplugin_nav') . '</button>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render the Create Link modal.
     */
    private function render_create_link_modal() {
        global $CFG;
        $availableicons = $this->get_available_icons();

        $html = '<div class="ainav-modal-overlay" id="ainav-modal-overlay">';
        $html .= '<div class="ainav-modal">';
        $html .= '<div class="ainav-modal-header">';
        $html .= '<h3 class="ainav-modal-title">' . get_string('create_link', 'block_aiplugin_nav') . '</h3>';
        $html .= '<button type="button" class="ainav-modal-close" id="ainav-modal-close">';
        $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $html .= $this->get_icon_svg('x');
        $html .= '</svg>';
        $html .= '</button>';
        $html .= '</div>';
        $html .= '<div class="ainav-modal-body">';

        // Icon picker.
        $html .= '<div class="ainav-form-group">';
        $html .= '<label class="ainav-form-label">' . get_string('select_icon', 'block_aiplugin_nav') . '</label>';
        $html .= '<div class="ainav-icon-picker" id="ainav-icon-picker">';
        foreach ($availableicons as $icon) {
            $html .= '<button type="button" class="ainav-icon-option" data-icon="' . $icon . '">';
            $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $html .= $this->get_icon_svg($icon);
            $html .= '</svg>';
            $html .= '</button>';
        }
        $html .= '</div>';
        $html .= '<input type="hidden" id="ainav-selected-icon" value="link">';
        $html .= '</div>';

        // Link name input.
        $html .= '<div class="ainav-form-group">';
        $html .= '<label class="ainav-form-label" for="ainav-link-name">' . get_string('link_name', 'block_aiplugin_nav') . '</label>';
        $html .= '<input type="text" id="ainav-link-name" class="ainav-form-input" placeholder="' . get_string('link_name_placeholder', 'block_aiplugin_nav') . '" maxlength="50">';
        $html .= '</div>';

        // URL input.
        $html .= '<div class="ainav-form-group">';
        $html .= '<label class="ainav-form-label" for="ainav-link-url">' . get_string('link_url', 'block_aiplugin_nav') . '</label>';
        $html .= '<input type="url" id="ainav-link-url" class="ainav-form-input" placeholder="https://example.com">';
        $html .= '</div>';

        $html .= '</div>';
        $html .= '<div class="ainav-modal-footer">';
        $html .= '<button type="button" class="ainav-btn ainav-btn-secondary" id="ainav-modal-cancel">' . get_string('cancel', 'block_aiplugin_nav') . '</button>';
        $html .= '<button type="button" class="ainav-btn ainav-btn-primary" id="ainav-save-link">' . get_string('save_link', 'block_aiplugin_nav') . '</button>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render a dropdown menu.
     *
     * @param string $id Dropdown ID.
     * @param array $section Dropdown section.
     * @return string Dropdown HTML.
     */
    private function render_dropdown($id, $section) {
        $html = '';

        // Filter items based on plugin availability and capabilities.
        $visibleitems = [];
        foreach ($section['items'] as $item) {
            // Check capability if required - use has_capability_anywhere for broader access.
            if (!empty($item['capability']) && !$this->has_capability_anywhere($item['capability'])) {
                continue;
            }

            // Check if plugin is installed (skip for external links).
            if (!isset($item['external']) && isset($item['plugin_type']) && isset($item['plugin_name'])) {
                if (!$this->is_plugin_installed($item['plugin_type'], $item['plugin_name'])) {
                    continue;
                }
            }

            $visibleitems[] = $item;
        }

        // Get custom reports for tools dropdown.
        $customreports = [];
        if ($id === 'tools') {
            $customreports = $this->get_custom_reports();
        }

        // Don't render dropdown if no visible items and no custom reports.
        if (empty($visibleitems) && empty($customreports)) {
            return '';
        }

        $html .= '<div class="ainav-dropdown" data-dropdown="' . $id . '">';
        $html .= '<button type="button" class="ainav-dropdown-trigger">';
        $html .= '<span>' . $section['label'] . '</span>';
        $html .= '<svg class="ainav-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $html .= $this->get_icon_svg('chevron-down');
        $html .= '</svg>';
        $html .= '</button>';

        $html .= '<div class="ainav-dropdown-menu' . ($id === 'settings' ? ' ainav-dropdown-twocol' : '') . '">';

        // Special 2-column layout for settings dropdown.
        if ($id === 'settings' && !empty($section['ai_items']) && !empty($section['admin_items'])) {
            // AI & Learning Intelligence column.
            $html .= '<div class="ainav-dropdown-col">';
            $html .= '<div class="ainav-dropdown-heading ainav-dropdown-heading-ai">&#10022; AI &amp; Learning Intelligence</div>';
            // Subheading label map for AI categories.
            $aicatlabels = [
                'config'     => 'Central Config',
                'ai_grading' => 'AI Grading &amp; Assessment',
                'ai_content' => 'AI Content &amp; Courses',
                'ai_media'   => 'AI Voice &amp; Media',
                'ai_rto'     => 'RTO &amp; Compliance',
                'ai_ux'      => 'AI Personalisation',
            ];
            $lastaicat = null;
            foreach ($section['ai_items'] as $item) {
                if (!empty($item['capability']) && !$this->has_capability_anywhere($item['capability'])) {
                    continue;
                }
                $itemcat = isset($item['category']) ? $item['category'] : '';
                if ($itemcat !== $lastaicat && isset($aicatlabels[$itemcat])) {
                    $html .= '<div class="ainav-dropdown-subheading">' . $aicatlabels[$itemcat] . '</div>';
                    $lastaicat = $itemcat;
                }
                $html .= '<a href="' . $item['url'] . '" class="ainav-dropdown-item">';
                $html .= '<svg class="ainav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                $html .= $this->get_icon_svg($item['icon']);
                $html .= '</svg>';
                $html .= '<span>' . $item['name'] . '</span>';
                if (isset($item['category']) && $item['category'] === 'config') {
                    $html .= '<span class="ainav-badge-install-first ainav-badge-configured">Foundation</span>';
                }
                $html .= '</a>';
            }
            $html .= '</div>';

            // Administration & Operations column.
            $html .= '<div class="ainav-dropdown-col">';
            $html .= '<div class="ainav-dropdown-heading ainav-dropdown-heading-admin">&#9881; Administration &amp; Operations</div>';
            $admincatlabels = [
                'block'         => 'Blocks &amp; Dashboards',
                'training'      => 'Training &amp; Scheduling',
                'enrolment'     => 'Enrolment &amp; Access',
                'integrity'     => 'Academic Integrity',
                'comms'         => 'Communications',
                'branding'      => 'Branding &amp; Appearance',
                'media_storage' => 'Media &amp; Storage',
                'security'      => 'Security &amp; Auth',
                'reporting'     => 'Reporting &amp; Analytics',
                'payments'      => 'Payments',
            ];
            $lastadmincat = null;
            foreach ($section['admin_items'] as $item) {
                if (!empty($item['capability']) && !$this->has_capability_anywhere($item['capability'])) {
                    continue;
                }
                $itemcat = isset($item['category']) ? $item['category'] : '';
                if ($itemcat !== $lastadmincat && isset($admincatlabels[$itemcat])) {
                    $html .= '<div class="ainav-dropdown-subheading">' . $admincatlabels[$itemcat] . '</div>';
                    $lastadmincat = $itemcat;
                }
                $html .= '<a href="' . $item['url'] . '" class="ainav-dropdown-item">';
                $html .= '<svg class="ainav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                $html .= $this->get_icon_svg($item['icon']);
                $html .= '</svg>';
                $html .= '<span>' . $item['name'] . '</span>';
                $html .= '</a>';
            }
            $html .= '</div>';
        } else {
            // Standard single-column layout.
            foreach ($visibleitems as $item) {
                $html .= '<a href="' . $item['url'] . '" class="ainav-dropdown-item">';
                $html .= '<svg class="ainav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                $html .= $this->get_icon_svg($item['icon']);
                $html .= '</svg>';
                $html .= '<span>' . $item['name'] . '</span>';
                $html .= '</a>';
            }
        }

        // Add custom reports for tools dropdown.
        if ($id === 'tools') {
            foreach ($customreports as $index => $report) {
                $html .= '<div class="ainav-dropdown-item ainav-custom-report" data-index="' . $index . '">';
                $html .= '<a href="' . htmlspecialchars($report['url'], ENT_QUOTES, 'UTF-8') . '" class="ainav-custom-report-link" target="_blank" rel="noopener">';
                $html .= '<svg class="ainav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                $html .= $this->get_icon_svg($report['icon']);
                $html .= '</svg>';
                $html .= '<span>' . htmlspecialchars($report['name'], ENT_QUOTES, 'UTF-8') . '</span>';
                $html .= '</a>';
                $html .= '<button type="button" class="ainav-delete-report" data-index="' . $index . '" title="' . get_string('delete_report', 'block_aiplugin_nav') . '">';
                $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
                $html .= $this->get_icon_svg('x');
                $html .= '</svg>';
                $html .= '</button>';
                $html .= '</div>';
            }

            // Add Report button.
            $html .= '<button type="button" class="ainav-dropdown-item ainav-create-report-btn" id="ainav-create-report-btn">';
            $html .= '<svg class="ainav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $html .= $this->get_icon_svg('plus');
            $html .= '</svg>';
            $html .= '<span>' . get_string('create_report', 'block_aiplugin_nav') . '</span>';
            $html .= '</button>';
        }
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Render the installed-only action launcher from the same registry as the legacy menus.
     *
     * @param array $registry Navigation registry.
     * @return string
     */
    private function render_action_launcher($registry) {
        global $CFG, $USER;

        $categorylabels = [
            'config' => 'Central configuration',
            'ai_grading' => 'AI grading and assessment',
            'ai_content' => 'AI content and courses',
            'ai_media' => 'AI voice and media',
            'ai_rto' => 'RTO AVETMISS and compliance',
            'ai_ux' => 'AI personalisation',
            'block' => 'Blocks and dashboards',
            'training' => 'Training scheduling and course operations',
            'enrolment' => 'Enrolment and access',
            'integrity' => 'Academic integrity',
            'comms' => 'Communications',
            'branding' => 'Branding and appearance',
            'media_storage' => 'Media and storage',
            'security' => 'Security authentication and SSO',
            'reporting' => 'Reporting and analytics',
            'payments' => 'Payments',
        ];
        $actions = [];
        $seen = [];
        $groups = [
            'Settings' => array_merge(
                isset($registry['settings']['ai_items']) ? $registry['settings']['ai_items'] : [],
                isset($registry['settings']['admin_items']) ? $registry['settings']['admin_items'] : []
            ),
            'Manage' => isset($registry['manage']['items']) ? $registry['manage']['items'] : [],
            'Report' => isset($registry['tools']['items']) ? $registry['tools']['items'] : [],
        ];

        foreach ($groups as $actionlabel => $items) {
            foreach ($items as $item) {
                if (!empty($item['capability']) && !$this->has_capability_anywhere($item['capability'])) {
                    continue;
                }
                if (empty($item['url']) || empty($item['name'])) {
                    continue;
                }
                $key = $actionlabel . '|' . $item['url'] . '|' . $item['name'];
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $component = trim(
                    (isset($item['plugin_type']) ? $item['plugin_type'] : '') . '_' .
                    (isset($item['plugin_name']) ? $item['plugin_name'] : ''),
                    '_'
                );
                $category = isset($item['category']) ? $item['category'] : '';
                $actions[] = [
                    'name' => $item['name'],
                    'action' => $actionlabel,
                    'url' => $item['url'],
                    'category' => isset($categorylabels[$category]) ? $categorylabels[$category] : $category,
                    'aliases' => trim(
                        str_replace(['_', '-'], ' ', $component . ' ' . (isset($item['plugin_name']) ? $item['plugin_name'] : '')) .
                        ' ' . (isset($item['aliases']) ? $item['aliases'] : '')
                    ),
                ];
            }
        }

        foreach ($this->get_custom_reports() as $report) {
            if (empty($report['url']) || empty($report['name'])) {
                continue;
            }
            $actions[] = [
                'name' => $report['name'],
                'action' => 'Report',
                'url' => $report['url'],
                'category' => 'Custom reports',
                'aliases' => 'custom saved report',
                'external' => true,
            ];
        }

        // Site Quick Links use the same capability and role visibility rules as.
        // Their rendered cards.
        $context = context_system::instance();
        $islmshsadmin = $this->user_has_role_shortname($USER->id, 'lmshsadmin');
        foreach ($this->get_site_links_registry() as $group) {
            if (!empty($group['capability']) && !has_capability($group['capability'], $context)) {
                continue;
            }
            foreach ($group['items'] as $link) {
                if ($islmshsadmin && !empty($link['hide_for_lmshsadmin'])) {
                    continue;
                }
                $actions[] = [
                    'name' => $link['name'],
                    'action' => 'Site',
                    'url' => $link['url'],
                    'category' => 'Site quick links',
                    'aliases' => 'Moodle account administration navigation',
                ];
            }
        }

        $email = $this->get_student_email_quicklink();
        if ($email) {
            $actions[] = [
                'name' => $email['name'],
                'action' => 'Open',
                'url' => $email['url'],
                'category' => 'Site quick links communications',
                'aliases' => 'mail mailbox student email',
            ];
        }

        foreach ($this->get_custom_links() as $link) {
            if (empty($link['url']) || empty($link['name'])) {
                continue;
            }
            $actions[] = [
                'name' => $link['name'],
                'action' => 'Open',
                'url' => $link['url'],
                'category' => 'Custom site links',
                'aliases' => 'custom saved quick link',
                'external' => true,
            ];
        }

        // Student portfolio links are rendered in the non-admin tools section.
        $isadmin = is_siteadmin() || has_capability('moodle/site:config', $context);
        if (
            !$isadmin
                && $this->is_plugin_installed('local', 'rtocompliance')
                && has_capability('local/rtocompliance:viewown', context_user::instance($USER->id))
        ) {
            $actions[] = [
                'name' => 'My Documents & Certificates',
                'action' => 'Open',
                'url' => (new moodle_url('/local/rtocompliance/mydocs.php'))->out(false),
                'category' => 'My portfolio RTO compliance',
                'aliases' => 'student documents files evidence',
            ];
            $actions[] = [
                'name' => 'My Certificates',
                'action' => 'Open',
                'url' => (new moodle_url('/local/rtocompliance/mycerts.php'))->out(false),
                'category' => 'My portfolio RTO compliance',
                'aliases' => 'student certificates awards',
            ];
        }

        foreach ($registry['external']['items'] as $link) {
            if (empty($link['url']) || empty($link['name'])) {
                continue;
            }
            $actions[] = [
                'name' => $link['name'],
                'action' => 'External',
                'url' => $link['url'],
                'category' => 'LMS Labs help account and services',
                'aliases' => 'website pricing credits affiliate',
                'external' => true,
            ];
        }

        usort($actions, function ($a, $b) {
            return strcasecmp($a['name'] . $a['action'], $b['name'] . $b['action']);
        });

        $json = json_encode($actions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $html = '<div class="ainav-launcher-overlay" id="ainav-action-launcher" role="dialog" aria-modal="true" aria-labelledby="ainav-launcher-title" aria-describedby="ainav-launcher-status" aria-hidden="true">';
        $html .= '<div class="ainav-launcher-panel">';
        $html .= '<div class="ainav-launcher-header"><div><h2 id="ainav-launcher-title">Find an action</h2><p>Search installed plugin settings, management pages, and reports.</p></div>';
        $html .= '<button type="button" class="ainav-launcher-close" aria-label="Close action launcher">';
        $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' . $this->get_icon_svg('x') . '</svg></button></div>';
        $html .= '<div class="ainav-launcher-search-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' . $this->get_icon_svg('search') . '</svg>';
        $html .= '<label class="sr-only" for="ainav-launcher-search">Search actions</label>';
        $html .= '<input type="search" id="ainav-launcher-search" autocomplete="off" placeholder="Try a plugin, task, category, or report" aria-controls="ainav-launcher-results"></div>';
        $html .= '<div id="ainav-launcher-status" class="ainav-launcher-status" aria-live="polite" aria-atomic="true"></div>';
        $html .= '<div id="ainav-launcher-results" class="ainav-launcher-results"></div>';
        $html .= '<div class="ainav-launcher-empty" hidden>No matching actions. Try a plugin name, “settings”, “manage”, “report”, or a category such as compliance.</div>';
        $html .= '</div></div>';
        $html .= '<script type="application/json" id="ainav-launcher-actions">' . $json . '</script>';
        return $html;
    }

    /**
     * Render the Create Report modal.
     */
    private function render_create_report_modal() {
        global $CFG;
        $availableicons = $this->get_available_icons();

        $html = '<div class="ainav-modal-overlay" id="ainav-report-modal-overlay">';
        $html .= '<div class="ainav-modal">';
        $html .= '<div class="ainav-modal-header">';
        $html .= '<h3 class="ainav-modal-title">' . get_string('create_report', 'block_aiplugin_nav') . '</h3>';
        $html .= '<button type="button" class="ainav-modal-close" id="ainav-report-modal-close">';
        $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
        $html .= $this->get_icon_svg('x');
        $html .= '</svg>';
        $html .= '</button>';
        $html .= '</div>';
        $html .= '<div class="ainav-modal-body">';

        // Icon picker.
        $html .= '<div class="ainav-form-group">';
        $html .= '<label class="ainav-form-label">' . get_string('select_icon', 'block_aiplugin_nav') . '</label>';
        $html .= '<div class="ainav-icon-picker" id="ainav-report-icon-picker">';
        foreach ($availableicons as $icon) {
            $html .= '<button type="button" class="ainav-icon-option ainav-report-icon-option" data-icon="' . $icon . '">';
            $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">';
            $html .= $this->get_icon_svg($icon);
            $html .= '</svg>';
            $html .= '</button>';
        }
        $html .= '</div>';
        $html .= '<input type="hidden" id="ainav-selected-report-icon" value="file-text">';
        $html .= '</div>';

        // Report name input.
        $html .= '<div class="ainav-form-group">';
        $html .= '<label class="ainav-form-label" for="ainav-report-name">' . get_string('report_name', 'block_aiplugin_nav') . '</label>';
        $html .= '<input type="text" id="ainav-report-name" class="ainav-form-input" placeholder="' . get_string('report_name_placeholder', 'block_aiplugin_nav') . '" maxlength="50">';
        $html .= '</div>';

        // URL input.
        $html .= '<div class="ainav-form-group">';
        $html .= '<label class="ainav-form-label" for="ainav-report-url">' . get_string('report_url', 'block_aiplugin_nav') . '</label>';
        $html .= '<input type="url" id="ainav-report-url" class="ainav-form-input" placeholder="https://example.com/report">';
        $html .= '</div>';

        $html .= '</div>';
        $html .= '<div class="ainav-modal-footer">';
        $html .= '<button type="button" class="ainav-btn ainav-btn-secondary" id="ainav-report-modal-cancel">' . get_string('cancel', 'block_aiplugin_nav') . '</button>';
        $html .= '<button type="button" class="ainav-btn ainav-btn-primary" id="ainav-save-report">' . get_string('save_report', 'block_aiplugin_nav') . '</button>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Include JavaScript for dropdown and modal functionality.
     */
    public function get_required_javascript() {
        global $PAGE, $CFG;

        $PAGE->requires->js_amd_inline("
            require(['jquery', 'core/ajax', 'core/notification'], function ($, Ajax, Notification) {
                // Accessible action launcher.
                var launcher = $('#ainav-action-launcher');
                var launcherTrigger = $('#ainav-launcher-trigger');
                var launcherSearch = $('#ainav-launcher-search');
                var launcherResults = $('#ainav-launcher-results');
                var launcherStatus = $('#ainav-launcher-status');
                var launcherEmpty = launcher.find('.ainav-launcher-empty');
                var launcherActions = [];
                var launcherLastFocus = null;
                var launcherActive = -1;
                try {
                    launcherActions = JSON.parse($('#ainav-launcher-actions').text() || '[]');
                } catch (ignore) {}

                function normaliseLauncherText(value) {
                    return String(value || '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
                }

                function renderLauncherResults() {
                    var query = normaliseLauncherText(launcherSearch.val());
                    var terms = query ? query.split(/\\s+/) : [];
                    var matches = launcherActions.filter(function (item) {
                        var haystack = normaliseLauncherText([
                            item.name, item.action, item.category, item.aliases
                        ].join(' '));
                        return terms.every(function (term) { return haystack.indexOf(term) !== -1; });
                    });
                    launcherResults.empty();
                    matches.forEach(function (item) {
                        var link = $('<a class=\"ainav-launcher-result\"></a>').attr('href', item.url);
                        if (item.external) {
                            link.attr({target: '_blank', rel: 'noopener'});
                        }
                        $('<span class=\"ainav-launcher-result-action\"></span>').text(item.action).appendTo(link);
                        var copy = $('<span class=\"ainav-launcher-result-copy\"></span>').appendTo(link);
                        $('<strong></strong>').text(item.name).appendTo(copy);
                        var metadata = item.category || 'Installed plugin';
                        if (item.external) {
                            metadata += ' · Opens in a new window';
                        }
                        $('<small></small>').text(metadata).appendTo(copy);
                        launcherResults.append(link);
                    });
                    launcherActive = -1;
                    launcherEmpty.prop('hidden', matches.length !== 0);
                    launcherStatus.text(matches.length + (matches.length === 1 ? ' action available.' : ' actions available.'));
                }

                function closeLauncher() {
                    if (!launcher.hasClass('is-open')) {
                        return;
                    }
                    launcher.removeClass('is-open').attr('aria-hidden', 'true');
                    $('body').removeClass('ainav-launcher-open');
                    if (launcherLastFocus) {
                        launcherLastFocus.focus();
                    }
                }

                function openLauncher() {
                    launcherLastFocus = document.activeElement;
                    $('.ainav-dropdown').removeClass('is-open');
                    launcher.addClass('is-open').attr('aria-hidden', 'false');
                    $('body').addClass('ainav-launcher-open');
                    renderLauncherResults();
                    window.setTimeout(function () { launcherSearch.trigger('focus'); }, 0);
                }

                launcherTrigger.on('click', openLauncher);
                launcher.find('.ainav-launcher-close').on('click', closeLauncher);
                launcher.on('click', function (e) {
                    if (e.target === this) {
                        closeLauncher();
                    }
                });
                launcherSearch.on('input', renderLauncherResults);
                launcherSearch.on('keydown', function (e) {
                    var results = launcherResults.find('.ainav-launcher-result');
                    if (!results.length || ['ArrowDown', 'ArrowUp', 'Home', 'End'].indexOf(e.key) === -1) {
                        return;
                    }
                    e.preventDefault();
                    if (e.key === 'Home') {
                        launcherActive = 0;
                    } else if (e.key === 'End') {
                        launcherActive = results.length - 1;
                    } else if (e.key === 'ArrowDown') {
                        launcherActive = (launcherActive + 1) % results.length;
                    } else {
                        launcherActive = launcherActive <= 0 ? results.length - 1 : launcherActive - 1;
                    }
                    results.eq(launcherActive).trigger('focus');
                });
                launcher.on('keydown', function (e) {
                    if (e.key === 'Escape') {
                        e.preventDefault();
                        closeLauncher();
                        return;
                    }
                    if (e.key === 'Tab') {
                        var focusable = launcher.find('button:visible, input:visible, a:visible');
                        if (!focusable.length) {
                            return;
                        }
                        var first = focusable[0];
                        var last = focusable[focusable.length - 1];
                        if (e.shiftKey && document.activeElement === first) {
                            e.preventDefault();
                            last.focus();
                        } else if (!e.shiftKey && document.activeElement === last) {
                            e.preventDefault();
                            first.focus();
                        }
                    }
                });
                launcherResults.on('keydown', '.ainav-launcher-result', function (e) {
                    var results = launcherResults.find('.ainav-launcher-result');
                    var index = results.index(this);
                    if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Home' || e.key === 'End') {
                        e.preventDefault();
                        if (e.key === 'Home') {
                            index = 0;
                        } else if (e.key === 'End') {
                            index = results.length - 1;
                        } else if (e.key === 'ArrowDown') {
                            index = (index + 1) % results.length;
                        } else {
                            index = index <= 0 ? results.length - 1 : index - 1;
                        }
                        launcherActive = index;
                        results.eq(index).trigger('focus');
                    }
                });

                // Toggle dropdown on click
                $('.ainav-dropdown-trigger').on('click', function (e) {
                    e.stopPropagation();
                    var dropdown = $(this).closest('.ainav-dropdown');
                    var menu = dropdown.find('.ainav-dropdown-menu').first();
                    var willopen = !dropdown.hasClass('is-open');
                    $('.ainav-dropdown').not(dropdown).removeClass('is-open');
                    dropdown.toggleClass('is-open', willopen);

                    if (willopen) {
                        // Keep long menus inside the viewport. Settings and Manage can contain
                        // dozens of installed plugins, so an unconstrained menu hides its lower
                        // links below the screen with no way to reach them.
                        menu.css({
                            'top': 'calc(100% + 8px)',
                            'bottom': 'auto',
                            'max-height': '',
                            'overflow-y': 'auto'
                        });

                        var triggerrect = this.getBoundingClientRect();
                        var viewportmargin = 16;
                        var menugap = 8;
                        var spacebelow = window.innerHeight - triggerrect.bottom - menugap - viewportmargin;
                        var spaceabove = triggerrect.top - menugap - viewportmargin;
                        var openabove = spaceabove > spacebelow;
                        var availableheight = openabove ? spaceabove : spacebelow;

                        if (openabove) {
                            menu.css({
                                'top': 'auto',
                                'bottom': 'calc(100% + 8px)'
                            });
                        }

                        menu.css('max-height', Math.max(120, availableheight) + 'px');
                    }
                });
                
                // Close dropdowns when clicking outside
                $(document).on('click', function () {
                    $('.ainav-dropdown').removeClass('is-open');
                });
                
                // Prevent menu clicks from closing
                $('.ainav-dropdown-menu').on('click', function (e) {
                    e.stopPropagation();
                });
                
                // Modal functionality
                var modal = $('#ainav-modal-overlay');
                var selectedIcon = 'link';
                
                // Open modal
                $('#ainav-create-link-btn').on('click', function () {
                    modal.addClass('is-open');
                    $('#ainav-link-name').val('');
                    $('#ainav-link-url').val('');
                    selectedIcon = 'link';
                    $('#ainav-selected-icon').val('link');
                    $('.ainav-icon-option').removeClass('is-selected');
                    $('.ainav-icon-option[data-icon=\"link\"]').addClass('is-selected');
                });
                
                // Close modal
                function closeModal() {
                    modal.removeClass('is-open');
                }
                
                $('#ainav-modal-close, #ainav-modal-cancel').on('click', closeModal);
                
                modal.on('click', function (e) {
                    if (e.target === this) {
                        closeModal();
                    }
                });
                
                // Icon picker
                $('.ainav-icon-option').on('click', function () {
                    $('.ainav-icon-option').removeClass('is-selected');
                    $(this).addClass('is-selected');
                    selectedIcon = $(this).data('icon');
                    $('#ainav-selected-icon').val(selectedIcon);
                });
                
                // Save link
                $('#ainav-save-link').on('click', function () {
                    var name = $('#ainav-link-name').val().trim();
                    var url = $('#ainav-link-url').val().trim();
                    
                    if (!name) {
                        Notification.alert('Error', 'Please enter a link name.');
                        return;
                    }
                    if (!url) {
                        Notification.alert('Error', 'Please enter a URL.');
                        return;
                    }
                    
                    // Basic URL validation
                    if (!url.match(/^https?:\\/\\//i)) {
                        url = 'https://' + url;
                    }
                    
                    Ajax.call([{
                        methodname: 'block_aiplugin_nav_save_custom_link',
                        args: {
                            name: name,
                            url: url,
                            icon: selectedIcon
                        },
                        done: function () {
                            closeModal();
                            location.reload();
                        },
                        fail: Notification.exception
                    }]);
                });
                
                // Delete link
                $('.ainav-delete-link').on('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var index = $(this).data('index');
                    
                    Ajax.call([{
                        methodname: 'block_aiplugin_nav_delete_custom_link',
                        args: {
                            index: index
                        },
                        done: function () {
                            location.reload();
                        },
                        fail: Notification.exception
                    }]);
                });
                
                // Report Modal functionality
                var reportModal = $('#ainav-report-modal-overlay');
                var selectedReportIcon = 'file-text';
                
                // Open report modal
                $('#ainav-create-report-btn').on('click', function (e) {
                    e.stopPropagation();
                    reportModal.addClass('is-open');
                    $('#ainav-report-name').val('');
                    $('#ainav-report-url').val('');
                    selectedReportIcon = 'file-text';
                    $('#ainav-selected-report-icon').val('file-text');
                    $('.ainav-report-icon-option').removeClass('is-selected');
                    $('.ainav-report-icon-option[data-icon=\"file-text\"]').addClass('is-selected');
                });
                
                // Close report modal
                function closeReportModal() {
                    reportModal.removeClass('is-open');
                }
                
                $('#ainav-report-modal-close, #ainav-report-modal-cancel').on('click', closeReportModal);
                
                reportModal.on('click', function (e) {
                    if (e.target === this) {
                        closeReportModal();
                    }
                });
                
                // Report Icon picker
                $('.ainav-report-icon-option').on('click', function () {
                    $('.ainav-report-icon-option').removeClass('is-selected');
                    $(this).addClass('is-selected');
                    selectedReportIcon = $(this).data('icon');
                    $('#ainav-selected-report-icon').val(selectedReportIcon);
                });
                
                // Save report
                $('#ainav-save-report').on('click', function () {
                    var name = $('#ainav-report-name').val().trim();
                    var url = $('#ainav-report-url').val().trim();
                    
                    if (!name) {
                        Notification.alert('Error', 'Please enter a report name.');
                        return;
                    }
                    if (!url) {
                        Notification.alert('Error', 'Please enter a URL.');
                        return;
                    }
                    
                    // Basic URL validation
                    if (!url.match(/^https?:\\/\\//i)) {
                        url = 'https://' + url;
                    }
                    
                    Ajax.call([{
                        methodname: 'block_aiplugin_nav_save_custom_report',
                        args: {
                            name: name,
                            url: url,
                            icon: selectedReportIcon
                        },
                        done: function () {
                            closeReportModal();
                            location.reload();
                        },
                        fail: Notification.exception
                    }]);
                });
                
                // Delete report
                $('.ainav-delete-report').on('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var index = $(this).data('index');
                    
                    Ajax.call([{
                        methodname: 'block_aiplugin_nav_delete_custom_report',
                        args: {
                            index: index
                        },
                        done: function () {
                            location.reload();
                        },
                        fail: Notification.exception
                    }]);
                });
                
                // --------------------------------------------
                // Plugin Version Checking (v1.7.0)
                // --------------------------------------------

                // FIX-ENDPOINT-ORDER (v2.4.16): PHP proxy moved to position #1.
                // The PHP proxy (check_versions.php) already has its own multi-endpoint
                // internal fallback (Replit → lms-labs.com) so it always resolves quickly.
                // Putting it first avoids a 10s browser timeout on lms-labs.com direct when
                // the Moodle server is on a Vultr/datacenter IP that is blocked by lms-labs.com.
                // essaygraderai.app removed — legacy domain no longer operational.
                var ainav_endpoints = [
                    '{$CFG->wwwroot}/blocks/aiplugin_nav/check_versions.php',
                    'https://ai-grader-site-nct185.replit.app/api/plugins/versions',
                    'https://lms-labs.com/api/plugins/versions'
                ];

                var latestVersions = null;
                var pluginsNeedingUpdate = [];
                
                /**
                 * Compare installed vs latest plugin versions.
                 * Parses both values as integers and returns:
                 *   1 = server has a newer version (update available)
                 *   0 = same version
                 *  -1 = installed is newer (dev/rollback scenario)
                 * Version-scheme regressions (e.g. 10-digit vs 13-digit numerics)
                 * are prevented by the server-side GUARD-NUMERIC pipeline gate,
                 * not by this client function.
                 */
                function compareVersions( installedNumeric, latestNumeric ) {
                    // Robust across 10-digit and 13-digit Moodle numerics: compare the
                    // YYYYMMDD date prefix first, then the trailing sequence numerically.
                    function parseV( v ) {
                        var s = String(v).replace(/[^0-9]/g, '');
                        if (s.length < 8) return null;
                        return { d: s.slice(0, 8), seq: parseInt(s.slice(8) || '0', 10) };
                    }
                    var pa = parseV(installedNumeric), pb = parseV(latestNumeric);
                    if (!pa || !pb) return 0;
                    if (pb.d !== pa.d) return (pb.d > pa.d) ? 1 : -1;
                    if (pb.seq > pa.seq) return 1;
                    if (pa.seq > pb.seq) return -1;
                    return 0;
                }
                
                // Check for updates
                $('#ainav-check-versions').on('click', function () {
                    var btn = $(this);
                    var originalHtml = btn.html();
                    btn.html('<span class=\"ainav-spinner\"></span> Checking...');
                    btn.prop('disabled', true);

                    // Try each endpoint in order — move to next on any failure
                    function doVersionCheck(attempt) {
                        if (attempt > ainav_endpoints.length) {
                            Notification.alert('Error', 'Could not reach the LMS Labs update server. Please try again later.');
                            btn.html(originalHtml);
                            btn.prop('disabled', false);
                            return;
                        }
                        var url = ainav_endpoints[attempt - 1] + '?t=' + Date.now();
                        $.ajax({
                            url: url,
                            type: 'GET',
                            dataType: 'json',
                            cache: false,
                            timeout: 10000,
                            success: function (response) {
                                if (response.success && response.plugins) {
                                    latestVersions = response.plugins;
                                    updateStatusLabels();
                                } else {
                                    // Got a response but malformed — try next
                                    setTimeout(function () { doVersionCheck(attempt + 1); }, 500);
                                    return;
                                }
                                btn.html(originalHtml);
                                btn.prop('disabled', false);
                            },
                            error: function () {
                                // This endpoint failed — silently try the next one
                                setTimeout(function () { doVersionCheck(attempt + 1); }, 500);
                            }
                        });
                    }
                    doVersionCheck(1);
                });
                
                // Update status labels based on fetched versions
                function updateStatusLabels() {
                    var installedData = $('#ainav-installed-plugins');
                    if (!installedData.length) {
                        Notification.addNotification({message: 'No installed plugins data found.', type: 'warning'});
                        return;
                    }
                    
                    var installed;
                    try {
                        installed = JSON.parse(installedData.text());
                    } catch (parseErr) {
                        // The btn/originalHtml vars belong to the click handler's scope; the caller
                        // resets the button after updateStatusLabels() returns.
                        Notification.alert('Error', 'Failed to read installed plugin data. Please reload the page and try again.');
                        return;
                    }
                    pluginsNeedingUpdate = [];
                    var updatesChecked = 0;
                    
                    installed.forEach(function (plugin) {
                        var component = plugin.component;
                        var installedNumeric = plugin.installedNumericVersion || '0';
                        var latest = latestVersions[component];
                        
                        if (!latest) return;
                        if (!plugin.isInstalled) return;
                        // Only offer updates for 'ready' plugins — testing/paid plugins show
                        // a status dot but must never join the Update All queue.
                        if (latest.status && latest.status !== 'ready') {
                            var statusLabel2 = $('.ainav-pm-status-label[data-component=\"' + component + '\"]');
                            statusLabel2.removeClass('status-testing status-ready status-update status-purchase').attr('title', '');
                            statusLabel2.addClass('status-testing').attr('title', 'In Testing');
                            return;
                        }
                        
                        var statusLabel = $('.ainav-pm-status-label[data-component=\"' + component + '\"]');
                        var updateIcon = $('.ainav-pm-action-update[data-component=\"' + component + '\"]');
                        
                        statusLabel.removeClass('status-testing status-ready status-update status-purchase').attr('title', '');
                        
                        var comparison = compareVersions(
                            installedNumeric,
                            latest.numericVersion || '0'
                        );
                        
                        if (comparison === 1) {
                            // Update available — only proceed if the server gave us a download URL.
                            // An empty/missing URL would be sent to PHP as an empty string and fail.
                            var latestDlUrl = latest.downloadUrl || '';
                            if (!latestDlUrl) return; // No URL from server — skip silently
                            statusLabel.addClass('status-update').attr('title', 'v' + (latest.version || '?') + ' Available');
                            updateIcon.show().attr('data-downloadurl', latestDlUrl).attr('data-sha256', latest.sha256 || '').css('cursor', 'pointer');
                            pluginsNeedingUpdate.push({
                                component: component,
                                name: latest.name || component,
                                downloadUrl: latestDlUrl,
                                sha256: latest.sha256 || '',
                                installedVersion: plugin.installedVersion || '?',
                                latestVersion: latest.version || '?',
                                releaseDate: latest.releaseDate || 'Not supplied',
                                compatibility: latest.compatibility || latest.moodleCompatibility ||
                                    (latest.documentation && Array.isArray(latest.documentation.requirements)
                                        ? latest.documentation.requirements.join('; ')
                                        : 'Not supplied — confirm with LMS Labs before staging'),
                                changelog: Array.isArray(latest.changelog) ? latest.changelog : []
                            });
                        } else if (comparison === 0) {
                            // Latest version - show testing (orange) or ready (green)
                            if (latest.status === 'testing') {
                                statusLabel.addClass('status-testing').attr('title', 'In Testing');
                            } else {
                                // Ready or any other status shows green
                                statusLabel.addClass('status-ready').attr('title', 'Ready');
                            }
                            updateIcon.hide();
                        } else {
                            // Installed is newer (dev version) - show as ready (green)
                            statusLabel.addClass('status-ready').attr('title', 'Ready');
                            updateIcon.hide();
                        }
                    });
                    
                    // Show/hide Auto Update All button only
                    if (pluginsNeedingUpdate.length > 0) {
                        $('#ainav-update-all').hide(); // Hide Download All - Auto Update All is primary
                        $('#ainav-autoupdate-all').show().find('span').text('Review Updates (' + pluginsNeedingUpdate.length + ')');
                    } else {
                        $('#ainav-update-all').hide();
                        $('#ainav-autoupdate-all').hide();
                        // All plugins are current — show the in-block \"up to date\" toast (v2.3.81).
                        showUpToDateToast();
                    }
                    // Show the popup (v2.3.83) — works for both updates and up-to-date states.
                    showUpdatePopup(pluginsNeedingUpdate);
                    
                    // Update install buttons for uninstalled plugins in testing mode (v2.2.55)
                    updateTestingInstallButtons();
                }
                
                // Update install buttons for uninstalled testing plugins - make them orange and disabled
                function updateTestingInstallButtons() {
                    if (!latestVersions) return;
                    
                    // Find all install buttons (uninstalled plugins)
                    $('.ainav-pm-action-install').each(function () {
                        var btn = $(this);
                        var component = btn.data('component');
                        var latest = latestVersions[component];
                        
                        if (latest && latest.status === 'testing') {
                            // Plugin is in testing mode - make button orange and disabled
                            btn.addClass('testing-disabled');
                            btn.prop('disabled', true);
                            btn.attr('title', 'Available after testing completes');
                            
                            // Also update the status label if not installed
                            var statusLabel = $('.ainav-pm-status-label[data-component=\"' + component + '\"]');
                            statusLabel.addClass('status-testing').attr('title', 'In Testing');
                        }
                    });
                }
                
                // Show \"All plugins are up to date\" toast inside the block (v2.3.81).
                // The toast uses var(--primary) which is set from the Moodle theme colour,
                // so the accent automatically matches the site's brand — no hardcoded colour.
                function showUpToDateToast() {
                    var toast = document.getElementById('ainav-uptodate-toast');
                    if (!toast) return;
                    toast.classList.add('ainav-uptodate-toast-visible');
                    // Auto-dismiss after 5 seconds.
                    setTimeout(function () {
                        toast.classList.remove('ainav-uptodate-toast-visible');
                    }, 5000);
                }

                // Close button for the \"all up to date\" toast.
                $(document).on('click', '#ainav-uptodate-toast-close', function () {
                    var toast = document.getElementById('ainav-uptodate-toast');
                    if (toast) toast.classList.remove('ainav-uptodate-toast-visible');
                });

                // ------------------------------------------------------------
                // Plugin Update Popup (v2.3.83)
                // Shown after \"Check for Updates\" completes. Mirrors the popup
                // on the lms-labs.com admin panel.
                // ------------------------------------------------------------
                function showUpdatePopup(updates) {
                    var overlay = document.getElementById('ainav-update-popup-overlay');
                    if (!overlay) return;
                    var header      = document.getElementById('ainav-update-popup-header');
                    var titleEl     = document.getElementById('ainav-update-popup-title');
                    var subtitleEl  = document.getElementById('ainav-update-popup-subtitle');
                    var iconBell    = document.getElementById('ainav-update-popup-icon-bell');
                    var iconShield  = document.getElementById('ainav-update-popup-icon-shield');
                    var listEl      = document.getElementById('ainav-update-popup-list');
                    var uptodateEl  = document.getElementById('ainav-update-popup-uptodate');
                    var confirmText = document.getElementById('ainav-update-popup-confirm-text');
                    var iconGot     = document.getElementById('ainav-update-popup-confirm-icon-got');
                    var iconOk      = document.getElementById('ainav-update-popup-confirm-icon-ok');
                    var confirmBtn  = document.getElementById('ainav-update-popup-confirm');
                    var hasUpdates  = updates.length > 0;

                    var recheckIconRefresh = document.getElementById('ainav-update-popup-recheck-icon-refresh');
                    var recheckIconX       = document.getElementById('ainav-update-popup-recheck-icon-x');
                    var recheckText        = document.getElementById('ainav-update-popup-recheck-text');

                    if (hasUpdates) {
                        header.style.background = 'linear-gradient(135deg, #f59e0b 0%, #ea580c 100%)';
                        iconBell.style.display   = '';
                        iconShield.style.display = 'none';
                        titleEl.textContent    = updates.length + ' Plugin Update' + (updates.length !== 1 ? 's' : '') + ' Available';
                        subtitleEl.textContent = 'New versions are ready to download';
                        listEl.style.display     = '';
                        uptodateEl.style.display = 'none';
                        // Right button: triggers the actual update flow.
                        confirmText.textContent  = 'Update All Plugins';
                        iconGot.style.display    = '';
                        iconOk.style.display     = 'none';
                        confirmBtn.style.background = 'linear-gradient(135deg, #f59e0b, #ea580c)';
                        confirmBtn.style.border     = 'none';
                        confirmBtn.dataset.hasUpdates = '1';
                        // Left button: just closes — the right button handles the action.
                        if (recheckIconRefresh) recheckIconRefresh.style.display = 'none';
                        if (recheckIconX)       recheckIconX.style.display       = '';
                        if (recheckText)        recheckText.textContent          = 'Close';

                        // Build plugin rows using DOM API to avoid HTML-escaping issues.
                        listEl.innerHTML = '';
                        updates.forEach(function (u) {
                            var row = document.createElement('div');
                            row.className = 'ainav-update-popup-row';

                            // Left: icon + name
                            var left = document.createElement('div');
                            left.className = 'ainav-update-popup-row-left';

                            var iconWrap = document.createElement('div');
                            iconWrap.className = 'ainav-update-popup-row-icon';
                            var svgPkg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                            svgPkg.setAttribute('viewBox', '0 0 24 24');
                            svgPkg.setAttribute('fill', 'none');
                            svgPkg.setAttribute('stroke', 'currentColor');
                            svgPkg.setAttribute('stroke-width', '2');
                            var pkgPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                            pkgPath.setAttribute('d', 'M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z');
                            svgPkg.appendChild(pkgPath);
                            iconWrap.appendChild(svgPkg);

                            var nameSpan = document.createElement('span');
                            nameSpan.className = 'ainav-update-popup-row-name';
                            nameSpan.textContent = u.name;

                            left.appendChild(iconWrap);
                            left.appendChild(nameSpan);

                            // Right: installed → latest versions
                            var right = document.createElement('div');
                            right.className = 'ainav-update-popup-row-versions';

                            var oldSpan = document.createElement('span');
                            oldSpan.className = 'ainav-update-popup-row-old';
                            oldSpan.textContent = u.installedVersion || '?';

                            var arrowSvg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                            arrowSvg.setAttribute('viewBox', '0 0 24 24');
                            arrowSvg.setAttribute('fill', 'none');
                            arrowSvg.setAttribute('stroke', '#9ca3af');
                            arrowSvg.setAttribute('stroke-width', '2');
                            arrowSvg.style.cssText = 'width:12px;height:12px;flex-shrink:0;';
                            var arrowLine = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                            arrowLine.setAttribute('x1', '5'); arrowLine.setAttribute('y1', '12');
                            arrowLine.setAttribute('x2', '19'); arrowLine.setAttribute('y2', '12');
                            var arrowPoly = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
                            arrowPoly.setAttribute('points', '12 5 19 12 12 19');
                            arrowSvg.appendChild(arrowLine);
                            arrowSvg.appendChild(arrowPoly);

                            var newSpan = document.createElement('span');
                            newSpan.className = 'ainav-update-popup-row-new';
                            newSpan.textContent = u.latestVersion || '?';

                            right.appendChild(oldSpan);
                            right.appendChild(arrowSvg);
                            right.appendChild(newSpan);

                            row.appendChild(left);
                            row.appendChild(right);
                            listEl.appendChild(row);
                        });
                    } else {
                        header.style.background  = 'linear-gradient(135deg, #10b981 0%, #0d9488 100%)';
                        iconBell.style.display   = 'none';
                        iconShield.style.display = '';
                        titleEl.textContent    = 'All Plugins Up to Date';
                        subtitleEl.textContent = 'Everything is current \u2014 no action needed';
                        listEl.style.display     = 'none';
                        uptodateEl.style.display = '';
                        confirmText.textContent  = 'Perfect, Thanks!';
                        iconGot.style.display    = 'none';
                        iconOk.style.display     = '';
                        confirmBtn.style.background = 'linear-gradient(135deg, #10b981, #0d9488)';
                        confirmBtn.style.border     = 'none';
                        confirmBtn.dataset.hasUpdates = '0';
                        // Left button: re-run the version check.
                        if (recheckIconRefresh) recheckIconRefresh.style.display = '';
                        if (recheckIconX)       recheckIconX.style.display       = 'none';
                        if (recheckText)        recheckText.textContent          = 'Re-check';
                    }

                    overlay.style.display = 'flex';
                }

                // Dismiss popup on X button (always just closes).
                $(document).on('click', '#ainav-update-popup-close', function () {
                    var ov = document.getElementById('ainav-update-popup-overlay');
                    if (ov) ov.style.display = 'none';
                });
                // Right confirm button — closes popup, then triggers update-all when updates are pending.
                $(document).on('click', '#ainav-update-popup-confirm', function () {
                    var ov = document.getElementById('ainav-update-popup-overlay');
                    if (ov) ov.style.display = 'none';
                    if (this.dataset.hasUpdates === '1') {
                        // Fire the Auto Update All button that was already shown by the version check.
                        var updateAllBtn = document.getElementById('ainav-autoupdate-all');
                        if (updateAllBtn) $(updateAllBtn).trigger('click');
                    }
                });
                // Dismiss popup on backdrop click.
                $(document).on('click', '#ainav-update-popup-overlay', function (e) {
                    if (e.target === this) this.style.display = 'none';
                });
                // Left button — 'Close' when updates pending, 'Re-check' when up to date.
                $(document).on('click', '#ainav-update-popup-recheck', function () {
                    var confirmBtn = document.getElementById('ainav-update-popup-confirm');
                    var ov = document.getElementById('ainav-update-popup-overlay');
                    if (ov) ov.style.display = 'none';
                    if (!confirmBtn || confirmBtn.dataset.hasUpdates !== '1') {
                        // Up-to-date state: re-run the version check.
                        var checkBtn = document.getElementById('ainav-check-versions');
                        if (checkBtn) $(checkBtn).trigger('click');
                    }
                    // Update state: just close (already done above).
                });

                // Auto-Install button for uninstalled plugins (v2.2.52)
                // Store download URLs and credit costs for quick access
                var pluginDownloadUrls = {};
                var pluginSha256 = {};
                // Resolve expected SHA-256 for a component, freshest source first:
                // a just-fetched response object, then the URL-cache, then the version-check store.
                function ainavShaFor(component, fresh) {
                    if (fresh && fresh.sha256) return fresh.sha256;
                    if (pluginSha256[component]) return pluginSha256[component];
                    if (latestVersions && latestVersions[component] && latestVersions[component].sha256) return latestVersions[component].sha256;
                    return '';
                }
                var pluginCreditCosts  = {};

                // Handle Install button clicks - auto-install via AJAX (v2.3.37: credit gate)
                $(document).on('click', '.ainav-pm-action-install', function (e) {
                    e.preventDefault();
                    var btn = $(this);

                    // Block clicks on testing plugins
                    if (btn.hasClass('testing-disabled')) {
                        Notification.addNotification({message: 'This plugin is currently in testing and will be available soon.', type: 'warning'});
                        return;
                    }

                    var component      = btn.data('component');
                    var pluginName     = btn.data('pluginname') || component;
                    var creditsNeeded  = parseInt(btn.data('credits-required') || '0', 10);
                    var pluginId       = btn.data('plugin-id') || '';

                    // Check if we have a cached download URL
                    var downloadUrl = pluginDownloadUrls[component];
                    if (downloadUrl) {
                        if (creditsNeeded > 0 && pluginId) {
                            showCreditConfirm(btn, component, downloadUrl, pluginName, creditsNeeded, pluginId, ainavShaFor(component));
                        } else {
                            doAutoInstall(btn, component, downloadUrl, pluginName, ainavShaFor(component));
                        }
                        return;
                    }

                    // Fetch download URL (and creditsRequired) via local PHP proxy (no CORS).
                    btn.css('opacity', '0.5');
                    btn.prop('disabled', true);
                    function fetchInstallUrl(attempt) {
                        $.ajax({
                            url: ainav_proxy_url,
                            type: 'GET',
                            dataType: 'json',
                            timeout: 20000,
                            success: function (data) {
                                var pdata = data.plugins && data.plugins[component];
                                if (pdata && pdata.downloadUrl) {
                                    pluginDownloadUrls[component] = pdata.downloadUrl;
                                    pluginSha256[component] = pdata.sha256 || '';
                                    // Use creditsRequired from API if button attr was 0 (fallback)
                                    if (!creditsNeeded && pdata.creditsRequired) {
                                        creditsNeeded = pdata.creditsRequired;
                                    }
                                    btn.css('opacity', '1');
                                    btn.prop('disabled', false);
                                    if (creditsNeeded > 0 && pluginId) {
                                        showCreditConfirm(btn, component, pdata.downloadUrl, pluginName, creditsNeeded, pluginId, ainavShaFor(component, pdata));
                                    } else {
                                        doAutoInstall(btn, component, pdata.downloadUrl, pluginName, ainavShaFor(component, pdata));
                                    }
                                } else {
                                    btn.css('opacity', '1');
                                    btn.prop('disabled', false);
                                    alert('Could not find download URL for ' + component);
                                }
                            },
                            error: function () {
                                if (attempt < 2) {
                                    setTimeout(function () { fetchInstallUrl(attempt + 1); }, 3000);
                                } else {
                                    btn.css('opacity', '1');
                                    btn.prop('disabled', false);
                                    alert('Could not fetch download URL. Please try again in a moment.');
                                }
                            }
                        });
                    }
                    fetchInstallUrl(1);
                });

                // Show credit confirmation modal before unlocking a credit-gated plugin (v2.3.37)
                function showCreditConfirm(btn, component, downloadUrl, pluginName, creditsNeeded, pluginId, expectedSha256) {
                    var overlay    = document.getElementById('ainav-credit-confirm-overlay');
                    var titleEl    = document.getElementById('ainav-credit-confirm-title');
                    var bodyEl     = document.getElementById('ainav-credit-confirm-body');
                    var confirmBtn = document.getElementById('ainav-credit-confirm-btn');
                    var cancelBtn  = document.getElementById('ainav-credit-cancel-btn');

                    if (!overlay) { doAutoInstall(btn, component, downloadUrl, pluginName, expectedSha256 || ''); return; }

                    titleEl.textContent = 'Unlock ' + pluginName + '?';
                    var creditStr = creditsNeeded.toLocaleString();
                    bodyEl.textContent = 'Installing ' + pluginName + ' requires a one-time unlock of '
                        + creditStr + ' credits. Once unlocked, all future downloads and '
                        + 'updates for this plugin are free. Do you want to continue?';
                    overlay.style.display = 'flex';

                    // Replace buttons to strip old event listeners
                    var newConfirm = confirmBtn.cloneNode(true);
                    var newCancel  = cancelBtn.cloneNode(true);
                    confirmBtn.parentNode.replaceChild(newConfirm, confirmBtn);
                    cancelBtn.parentNode.replaceChild(newCancel, cancelBtn);

                    newCancel.addEventListener('click', function () {
                        overlay.style.display = 'none';
                    });
                    newConfirm.addEventListener('click', function () {
                        overlay.style.display = 'none';
                        doCreditUnlock(btn, component, downloadUrl, pluginName, pluginId, creditsNeeded, expectedSha256);
                    });
                }

                // Perform credit unlock via Moodle AJAX then proceed with install (v2.3.37)
                function doCreditUnlock(btn, component, downloadUrl, pluginName, pluginId, creditsNeeded, expectedSha256) {
                    btn.prop('disabled', true);
                    btn.css('opacity', '0.5');
                    btn.html('<span class=\"ainav-spinner\"></span>');

                    Ajax.call([{
                        methodname: 'block_aiplugin_nav_plugin_unlock',
                        args: { pluginid: pluginId },
                        done: function (response) {
                            if (response.success) {
                                if (response.alreadyunlocked) {
                                    Notification.addNotification({
                                        message: pluginName + ' already unlocked - installing now...',
                                        type: 'success'
                                    });
                                } else {
                                    var consumed = response.creditsconsumed || creditsNeeded;
                                    var remaining = response.remainingcredits;
                                    var msg = consumed.toLocaleString() + ' credits consumed for ' + pluginName + '.';
                                    if (remaining !== '' && remaining !== undefined) {
                                        msg += ' Remaining: ' + remaining;
                                    }
                                    Notification.addNotification({message: msg, type: 'success'});
                                }
                                // Restore download icon before handing off to installer
                                btn.html('<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4\"/><polyline points=\"7 10 12 15 17 10\"/><line x1=\"12\" y1=\"15\" x2=\"12\" y2=\"3\"/></svg>');
                                btn.css('opacity', '1');
                                btn.prop('disabled', false);
                                doAutoInstall(btn, component, downloadUrl, pluginName, expectedSha256 || '');
                            } else {
                                btn.html('<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4\"/><polyline points=\"7 10 12 15 17 10\"/><line x1=\"12\" y1=\"15\" x2=\"12\" y2=\"3\"/></svg>');
                                btn.css('opacity', '1');
                                btn.prop('disabled', false);
                                var errMsg = response.error || 'Credit unlock failed. Please try again.';
                                Notification.addNotification({message: errMsg, type: 'error'});
                            }
                        },
                        fail: function (error) {
                            btn.html('<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4\"/><polyline points=\"7 10 12 15 17 10\"/><line x1=\"12\" y1=\"15\" x2=\"12\" y2=\"3\"/></svg>');
                            btn.css('opacity', '1');
                            btn.prop('disabled', false);
                            Notification.addNotification({message: 'Credit unlock failed: ' + (error.message || 'Unknown error'), type: 'error'});
                        }
                    }]);
                }

                // Perform auto-install via Moodle AJAX
                function doAutoInstall(btn, component, downloadUrl, pluginName, expectedSha256) {
                    btn.prop('disabled', true);
                    btn.css('opacity', '0.5');
                    btn.html('<span class=\"ainav-spinner\"></span>');
                    
                    // Use Ajax/Notification from outer scope (already loaded)
                    Ajax.call([{
                        methodname: 'block_aiplugin_nav_auto_install_plugin',
                        args: {
                            component: component,
                            downloadurl: downloadUrl,
                            expectedsha256: expectedSha256 || ''
                        },
                        done: function (response) {
                            if (response.success) {
                                btn.html('<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"20 6 9 17 4 12\"/></svg>');
                                btn.removeClass('ainav-pm-action-install').addClass('ainav-pm-action-success');
                                Notification.addNotification({message: pluginName + ' files staged. Continuing to Moodle upgrade...', type: 'success'});
                                // Redirect to admin page for database upgrade
                                setTimeout(function () {
                                    window.location.href = M.cfg.wwwroot + '/admin/index.php';
                                }, 1000);
                            } else {
                                btn.html('<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4\"/><polyline points=\"7 10 12 15 17 10\"/><line x1=\"12\" y1=\"15\" x2=\"12\" y2=\"3\"/></svg>');
                                btn.css('opacity', '1');
                                btn.prop('disabled', false);
                                Notification.addNotification({message: 'Install failed: ' + response.message, type: 'error'});
                            }
                        },
                        fail: function (error) {
                            btn.html('<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><path d=\"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4\"/><polyline points=\"7 10 12 15 17 10\"/><line x1=\"12\" y1=\"15\" x2=\"12\" y2=\"3\"/></svg>');
                            btn.css('opacity', '1');
                            btn.prop('disabled', false);
                            Notification.addNotification({message: 'Install failed: ' + (error.message || 'Unknown error'), type: 'error'});
                        }
                    }]);
                }
                
                // Store download URLs from version check for quick access in action buttons
                function updatePluginDownloadUrls(data) {
                    if (data.plugins) {
                        Object.keys(data.plugins).forEach(function (component) {
                            if (data.plugins[component].downloadUrl) {
                                pluginDownloadUrls[component] = data.plugins[component].downloadUrl;
                                pluginSha256[component] = data.plugins[component].sha256 || '';
                            }
                        });
                    }
                }
                
                function ainavEsc(value) {
                    return $('<span>').text(String(value == null ? '' : value)).html();
                }

                function findReviewedUpdate(component) {
                    return pluginsNeedingUpdate.filter(function (plugin) {
                        return plugin.component === component;
                    })[0] || null;
                }

                function showUpdateReview(candidates, bulk) {
                    var valid = candidates.filter(function (plugin) {
                        return plugin && /^[a-f0-9]{64}$/i.test(plugin.sha256 || '');
                    });
                    if (valid.length !== candidates.length) {
                        Notification.alert('Update Cannot Be Verified',
                            'One or more selected releases has no valid published SHA-256. Check for updates again; no files were changed.');
                        return;
                    }

                    var rows = valid.map(function (plugin, index) {
                        var notes = plugin.changelog.length
                            ? '<ul>' + plugin.changelog.slice(0, 5).map(function (note) {
                                return '<li>' + ainavEsc(note) + '</li>';
                            }).join('') + '</ul>'
                            : '<em>No release notes were supplied.</em>';
                        return '<article style=\"border:1px solid #d1d5db;border-radius:6px;padding:12px;margin:10px 0;\">' +
                            '<label style=\"display:flex;gap:10px;align-items:flex-start;\">' +
                            '<input type=\"checkbox\" class=\"ainav-review-select\" data-index=\"' + index + '\" ' +
                            (bulk ? '' : 'checked disabled') + ' style=\"margin-top:5px;\">' +
                            '<span><strong>' + ainavEsc(plugin.name) + '</strong> <code>' + ainavEsc(plugin.component) + '</code><br>' +
                            'Installed: ' + ainavEsc(plugin.installedVersion) + ' &rarr; Available: ' + ainavEsc(plugin.latestVersion) + '<br>' +
                            'Release date: ' + ainavEsc(plugin.releaseDate) + '<br>' +
                            'Moodle compatibility: ' + ainavEsc(plugin.compatibility) + '<br>' +
                            'SHA-256 verified by server before staging: <code>' + ainavEsc(plugin.sha256) + '</code><br>' +
                            '<strong>Release notes</strong>' + notes + '</span></label></article>';
                    }).join('');

                    var overlay = $('<div class=\"ainav-update-review\" role=\"dialog\" aria-modal=\"true\" aria-labelledby=\"ainav-review-title\" ' +
                        'style=\"position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:99999;display:flex;align-items:center;justify-content:center;padding:16px;\">' +
                        '<div style=\"background:#fff;color:#111;border-radius:8px;padding:22px;max-width:760px;width:100%;max-height:90vh;overflow:auto;\">' +
                        '<h3 id=\"ainav-review-title\">Review plugin updates</h3>' +
                        '<p>Select only the plugins you reviewed. Files will be staged one at a time; Moodle must still confirm the database upgrade.</p>' +
                        rows +
                        '<div class=\"ainav-review-status\" aria-live=\"polite\"></div>' +
                        '<div style=\"display:flex;justify-content:flex-end;gap:8px;margin-top:16px;\">' +
                        '<button type=\"button\" class=\"btn btn-secondary ainav-review-cancel\">Cancel</button>' +
                        '<button type=\"button\" class=\"btn btn-primary ainav-review-confirm\" disabled>Confirm selected updates</button>' +
                        '</div></div></div>');
                    $('body').append(overlay);
                    var confirm = overlay.find('.ainav-review-confirm');
                    function selected() {
                        return overlay.find('.ainav-review-select:checked').map(function () {
                            return valid[parseInt($(this).attr('data-index'), 10)];
                        }).get();
                    }
                    function refreshConfirm() {
                        var count = selected().length;
                        confirm.prop('disabled', count === 0).text('Confirm ' + count + ' selected update' + (count === 1 ? '' : 's'));
                    }
                    overlay.on('change', '.ainav-review-select', refreshConfirm);
                    overlay.find('.ainav-review-cancel').on('click', function () { overlay.remove(); });
                    confirm.on('click', function () {
                        var reviewed = selected();
                        if (!reviewed.length) return;
                        overlay.find('input, button').prop('disabled', true);
                        stageReviewedUpdates(reviewed, overlay.find('.ainav-review-status'), confirm);
                    });
                    refreshConfirm();
                    overlay.find('.ainav-review-select').first().trigger('focus');
                }

                function stageReviewedUpdates(reviewed, status, confirm) {
                    var outcomes = [];
                    function finish(failedIndex) {
                        var staged = outcomes.filter(function (o) { return o.result === 'staged'; });
                        var failed = outcomes.filter(function (o) { return o.result === 'failed'; });
                        var notStarted = failedIndex == null ? [] : reviewed.slice(failedIndex + 1);
                        var html = '<h4>Staging outcome</h4><ul>' +
                            staged.map(function (o) { return '<li>' + ainavEsc(o.name) + ': files staged; Moodle upgrade required</li>'; }).join('') +
                            failed.map(function (o) { return '<li><strong>' + ainavEsc(o.name) + ': failed</strong> — ' + ainavEsc(o.message) + '</li>'; }).join('') +
                            notStarted.map(function (o) { return '<li>' + ainavEsc(o.name) + ': not started because the batch stopped safely</li>'; }).join('') +
                            '</ul><p>No update is complete until Moodle confirms its upgrade.</p>';
                        status.html(html);
                        confirm.hide();
                        var actions = $('<div style=\"display:flex;justify-content:flex-end;gap:8px\"></div>');
                        var close = $('<button type=\"button\" class=\"btn btn-secondary\">Close</button>');
                        close.on('click', function () { status.closest('.ainav-update-review').remove(); });
                        actions.append(close);
                        if (failed.length) {
                            var retry = $('<button type=\"button\" class=\"btn btn-secondary\">Review failed plugin again</button>');
                            retry.on('click', function () {
                                status.closest('.ainav-update-review').remove();
                                showUpdateReview([reviewed[failedIndex]], false);
                            });
                            actions.append(retry);
                        }
                        if (staged.length) {
                            actions.append($('<a class=\"btn btn-primary\">Continue to Moodle upgrade</a>')
                                .attr('href', M.cfg.wwwroot + '/admin/index.php'));
                        }
                        status.after(actions);
                    }
                    function next(index) {
                        if (index >= reviewed.length) {
                            finish(null);
                            return;
                        }
                        var plugin = reviewed[index];
                        status.text('Staging ' + (index + 1) + ' of ' + reviewed.length + ': ' + plugin.name);
                        Ajax.call([{
                            methodname: 'block_aiplugin_nav_auto_update_plugin',
                            args: {
                                downloadurl: plugin.downloadUrl,
                                component: plugin.component,
                                expectedsha256: plugin.sha256,
                                expectedversion: plugin.latestVersion,
                                reviewconfirmed: 1
                            }
                        }])[0].done(function (response) {
                            if (!response.success) {
                                outcomes.push({name: plugin.name, result: 'failed', message: response.message || 'Unknown error'});
                                finish(index);
                                return;
                            }
                            outcomes.push({name: plugin.name, result: 'staged'});
                            $('.ainav-pm-action-update[data-component=\"' + plugin.component + '\"]').hide();
                            next(index + 1);
                        }).fail(function (error) {
                            outcomes.push({name: plugin.name, result: 'failed', message: (error && error.message) || 'Request failed'});
                            finish(index);
                        });
                    }
                    next(0);
                }

                // Update icon click opens review; it never starts an update directly.
                $(document).on('click', '.ainav-pm-action-update', function () {
                    var icon = $(this);
                    var component = icon.data('component');
                    var plugin = findReviewedUpdate(component);
                    if (plugin) showUpdateReview([plugin], false);
                });
                
                // Update All functionality
                $('#ainav-update-all').on('click', function () {
                    if (pluginsNeedingUpdate.length === 0) return;
                    
                    showUpdateReview(pluginsNeedingUpdate.slice(), true);
                });
                
                // Close success modal
                $('#ainav-success-close, #ainav-update-success-modal').on('click', function (e) {
                    if (e.target === this || e.target.id === 'ainav-success-close') {
                        $('#ainav-update-success-modal').removeClass('is-open');
                    }
                });
                
                // Legacy: AUTO UPDATE - Individual plugin button (v1.8.0) - kept for backwards compatibility
                // Note: v2.2.32+ uses the update icon click handler above
                
                // Bulk action opens an itemized, opt-in review. Nothing is preselected.
                $('#ainav-autoupdate-all').on('click', function () {
                    if (pluginsNeedingUpdate.length === 0) return;
                    showUpdateReview(pluginsNeedingUpdate.slice(), true);
                });
                
                // Show upgrade prompt modal
                function showUpgradePrompt(pluginName) {
                    var modal = $('<div class=\"ainav-upgrade-overlay\"><div class=\"ainav-upgrade-modal\">' +
                        '<div class=\"ainav-upgrade-icon\"><svg viewBox=\"0 0 24 24\" width=\"48\" height=\"48\" fill=\"none\" stroke=\"#10b981\" stroke-width=\"2\"><polyline points=\"20 6 9 17 4 12\"/></svg></div>' +
                        '<h3 class=\"ainav-upgrade-title\">Plugin files staged</h3>' +
                        '<p class=\"ainav-upgrade-message\">' + pluginName + ' is not yet upgraded. Continue to Moodle so it can confirm the database upgrade.</p>' +
                        '<div class=\"ainav-upgrade-actions\">' +
                        '<a href=\"" . $CFG->wwwroot . "/admin/index.php\" class=\"ainav-btn-primary\">Run Database Upgrade</a>' +
                        '<button type=\"button\" class=\"ainav-btn-secondary\" onclick=\"this.closest(\'.ainav-upgrade-overlay\').remove()\">Close</button>' +
                        '</div></div></div>');
                    $('body').append(modal);
                }
                
                // --------------------------------------------
                // Cache Management (v2.0.0)
                // --------------------------------------------
                
                // Purge Caches button - uses Ajax/Notification from outer scope
                $('#ainav-purge-caches-btn').on('click', function () {
                    var btn = $(this);
                    var originalHtml = btn.html();
                    
                    btn.html('<span class=\"ainav-spinner\"></span> Purging...').prop('disabled', true);
                    
                    Ajax.call([{
                        methodname: 'block_aiplugin_nav_purge_caches',
                        args: {},
                        done: function (response) {
                            if (response.success) {
                                btn.html('<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" class=\"ainav-purge-icon\"><polyline points=\"20 6 9 17 4 12\"/></svg><span>Purged!</span>');
                                btn.addClass('ainav-btn-success');
                                // Update last manual purge time using server-formatted time
                                if (response.formatted_time) {
                                    $('#ainav-last-manual').text(response.formatted_time);
                                }
                                // Reset button after 3 seconds
                                setTimeout(function () {
                                    btn.html(originalHtml).removeClass('ainav-btn-success').prop('disabled', false);
                                }, 3000);
                            } else {
                                Notification.alert('Purge Failed', response.message || 'Could not purge caches.');
                                btn.html(originalHtml).prop('disabled', false);
                            }
                        },
                        fail: function (error) {
                            Notification.alert('Purge Failed', 'Could not purge caches: ' + (error.message || 'Unknown error'));
                            btn.html(originalHtml).prop('disabled', false);
                        }
                    }]);
                });
                
                // Open schedule modal
                $('#ainav-schedule-btn').on('click', function () {
                    $('#ainav-schedule-overlay').addClass('is-open');
                });
                
                // Close schedule modal
                $('#ainav-schedule-close, #ainav-schedule-cancel').on('click', function () {
                    $('#ainav-schedule-overlay').removeClass('is-open');
                });
                $('#ainav-schedule-overlay').on('click', function (e) {
                    if (e.target === this) {
                        $(this).removeClass('is-open');
                    }
                });
                
                // Toggle day selector visibility
                $('#ainav-schedule-type').on('change', function () {
                    if ($(this).val() === 'weekly') {
                        $('#ainav-day-group').show();
                    } else {
                        $('#ainav-day-group').hide();
                    }
                });
                
                // Save schedule
                $('#ainav-save-schedule').on('click', function () {
                    var btn = $(this);
                    var originalText = btn.text();
                    var enabled = $('#ainav-schedule-enabled').is(':checked');
                    var scheduleType = $('#ainav-schedule-type').val();
                    var scheduleTime = $('#ainav-schedule-time').val();
                    var scheduleDay = parseInt($('#ainav-schedule-day').val()) || 0;
                    
                    btn.text('Saving...').prop('disabled', true);
                    
                    // Use Ajax/Notification from outer scope
                    Ajax.call([{
                        methodname: 'block_aiplugin_nav_save_purge_schedule',
                        args: {
                            enabled: enabled,
                            schedule_type: scheduleType,
                            schedule_time: scheduleTime,
                            schedule_day: scheduleDay
                        },
                        done: function (response) {
                            if (response.success) {
                                btn.text('Saved!');
                                setTimeout(function () {
                                    btn.text(originalText).prop('disabled', false);
                                    $('#ainav-schedule-overlay').removeClass('is-open');
                                }, 1500);
                            } else {
                                Notification.alert('Save Failed', response.message || 'Could not save schedule.');
                                btn.text(originalText).prop('disabled', false);
                            }
                        },
                        fail: function (error) {
                            Notification.alert('Save Failed', 'Could not save schedule: ' + (error.message || 'Unknown error'));
                            btn.text(originalText).prop('disabled', false);
                        }
                    }]);
                });
                
                // --------------------------------------------
                // Primary Color Detection (v1.8.9) - Moodle 5 compatibility
                // Detects theme primary color from DOM when PHP can't get it.
                // --------------------------------------------
                (function detectPrimaryColor() {
                    var container = document.querySelector('.ainav-container');
                    if (!container) return;
                    
                    // Check if we need to detect color (marker from PHP).
                    var currentColor = getComputedStyle(container).getPropertyValue('--primary').trim();
                    if (currentColor && currentColor !== '__DETECT_FROM_DOM__' && !currentColor.includes('DETECT')) {
                        return; // PHP already detected a valid color.
                    }
                    
                    // Try to detect primary color from existing themed elements.
                    var detectedColor = null;
                    
                    // Method 1: Check CSS variable --primary or --bs-primary from :root.
                    var rootStyles = getComputedStyle(document.documentElement);
                    var cssVarPrimary = rootStyles.getPropertyValue('--primary').trim();
                    if (cssVarPrimary && cssVarPrimary.match(/^#[0-9a-fA-F]{3,6}$|^rgb/)) {
                        detectedColor = cssVarPrimary;
                    }
                    if (!detectedColor) {
                        var bsPrimary = rootStyles.getPropertyValue('--bs-primary').trim();
                        if (bsPrimary && bsPrimary.match(/^#[0-9a-fA-F]{3,6}$|^rgb/)) {
                            detectedColor = bsPrimary;
                        }
                    }
                    
                    // Method 2: Check .btn-primary button background color.
                    if (!detectedColor) {
                        var btnPrimary = document.querySelector('.btn-primary:not(.ainav-btn-primary)');
                        if (btnPrimary) {
                            var bgColor = getComputedStyle(btnPrimary).backgroundColor;
                            if (bgColor && bgColor !== 'rgba(0, 0, 0, 0)' && bgColor !== 'transparent') {
                                detectedColor = bgColor;
                            }
                        }
                    }
                    
                    // Method 3: Check navbar/header background.
                    if (!detectedColor) {
                        var navbar = document.querySelector('.navbar, nav.navbar, #page-header, header');
                        if (navbar) {
                            var bgColor = getComputedStyle(navbar).backgroundColor;
                            if (bgColor && bgColor !== 'rgba(0, 0, 0, 0)' && bgColor !== 'transparent' && bgColor !== 'rgb(255, 255, 255)') {
                                detectedColor = bgColor;
                            }
                        }
                    }
                    
                    // Method 4: Check any element with primary class.
                    if (!detectedColor) {
                        var primaryEl = document.querySelector('.bg-primary, [class*=\"primary\"]:not(.ainav-btn-primary)');
                        if (primaryEl) {
                            var bgColor = getComputedStyle(primaryEl).backgroundColor;
                            if (bgColor && bgColor !== 'rgba(0, 0, 0, 0)' && bgColor !== 'transparent') {
                                detectedColor = bgColor;
                            }
                        }
                    }
                    
                    // Method 5: Check nav links or active items.
                    if (!detectedColor) {
                        var activeNav = document.querySelector('.nav-link.active, .navbar-nav .active > a, a.active');
                        if (activeNav) {
                            var color = getComputedStyle(activeNav).color;
                            if (color && color !== 'rgb(0, 0, 0)' && color !== 'rgb(255, 255, 255)') {
                                detectedColor = color;
                            }
                        }
                    }
                    
                    // Apply detected color.
                    if (detectedColor) {
                        container.style.setProperty('--primary', detectedColor);
                        console.log('AI Quick Links: Detected primary color from DOM:', detectedColor);
                    } else {
                        // Fallback to a nice blue.
                        container.style.setProperty('--primary', '#3b82f6');
                        console.log('AI Quick Links: Using fallback color');
                    }
                })();
            });
        ");
    }
}
