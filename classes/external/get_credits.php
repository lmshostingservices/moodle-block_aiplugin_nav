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
 * External service to fetch credits balance with an identity-scoped short cache.
 *
 * @package    block_aiplugin_nav
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_aiplugin_nav\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;

/**
 * External service to fetch credit balance from lms-labs.com.
 * Validated results are cached for 30 seconds per credential pair.
 */
class get_credits extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'force' => new external_value(PARAM_BOOL, 'Refresh balance now', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Fetch credit balance, optionally bypassing the normal short cache.
     *
     * @return array Credits info.
     */
    public static function execute($force = false) {
        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['force' => $force]);
        $force = $params['force'];
        $context = \context_system::instance();
        self::validate_context($context);

        // Soft role check — return empty success:false instead of throwing an exception.
        // The block PHP already restricts the credits UI to admins/teachers; the web service must
        // Not throw here because any PHP exception causes the AJAX call to fail and
        // Silently hides the credits display for everyone.
        // Allowed: site admins, editing teachers, non-editing teachers, lmshsadmin, and any
        // User who has moodle/site:configview (covers custom admin roles that aren't full siteadmins).
        $isallowed = is_siteadmin()
            || has_capability('moodle/site:configview', $context, $USER->id, false);
        if (!$isallowed) {
            $isallowed = $DB->record_exists_sql(
                "SELECT ra.id
                   FROM {role_assignments} ra
                   JOIN {role} r ON r.id = ra.roleid
                  WHERE ra.userid = :userid
                    AND r.shortname IN ('editingteacher', 'teacher', 'lmshsadmin', 'lmshostingadmin', 'manager')",
                ['userid' => $USER->id]
            );
        }
        if (!$isallowed) {
            return ['credits' => '', 'cached' => false, 'success' => false];
        }

        // SESSION LOCK: Release before external credits API call (up to 10 s timeout).
        \core\session\manager::write_close();

        // Load AI Grader Central Config library.
        $aiconfiglib = $CFG->dirroot . '/local/aiconfig/lib.php';
        if (file_exists($aiconfiglib)) {
            require_once($aiconfiglib);
        }

        $siteid = '';
        $apikey = '';

        if (function_exists('local_aiconfig_get_siteid')) {
            $siteid = trim(local_aiconfig_get_siteid('block_aiplugin_nav') ?? '');
        }
        if (function_exists('local_aiconfig_get_apikey')) {
            $apikey = trim(local_aiconfig_get_apikey('block_aiplugin_nav') ?? '');
        }

        if (empty($siteid) || empty($apikey)) {
            return ['credits' => '', 'cached' => false, 'success' => false];
        }

        // Resolve identity before cache lookup. Never use the legacy global balance.
        $cachekey = 'balance_' . hash('sha256', json_encode([$siteid, $apikey]));
        $cached = json_decode(get_config('block_aiplugin_nav', $cachekey) ?: '', true);
        $age = time() - (int)($cached['at'] ?? 0);
        // Two seconds coalesces focus/visibility and concurrent manual refreshes.
        if ($cached && $age >= 0 && $age < ($force ? 2 : 30)) {
            $balance = self::normalise($cached['data'] ?? []);
            if ($balance !== null) {
                return $balance + ['cached' => true, 'success' => true];
            }
        }

        // Make API request — try endpoints in order (Replit first: always reachable from
        // Vultr/datacenter IPs; lms-labs.com second: may be blocked by datacenter firewall).
        // FIX-ENDPOINT-ORDER (v2.4.17): same root cause as check_versions.php — lms-labs.com
        // Is unreachable from Vultr-hosted Moodle servers so credits never loaded.
        $query    = '?siteId=' . rawurlencode($siteid) . '&apiKey=' . rawurlencode($apikey);
        $endpoints = [
            'https://ai-grader-site-nct185.replit.app/api/credits' . $query,
            'https://lms-labs.com/api/credits' . $query,
        ];

        // Moodle's \curl class lives in filelib.php — load it explicitly.
        require_once($CFG->libdir . '/filelib.php');

        $data = null;
        foreach ($endpoints as $url) {
            $curl = new \curl();
            $curl->setopt([
                'CURLOPT_TIMEOUT'        => 5,
                'CURLOPT_CONNECTTIMEOUT' => 3,
                'CURLOPT_SSL_VERIFYPEER' => true,
                'CURLOPT_FOLLOWLOCATION' => false,
            ]);
            $curl->setHeader(['Accept: application/json']);
            $response = $curl->get($url);
            $httpcode = (int)($curl->info['http_code'] ?? 0);

            if ($httpcode === 200 && !empty($response)) {
                $decoded = json_decode($response, true);
                $balance = self::normalise($decoded);
                if ($balance !== null) {
                    $data = $balance;
                    break;
                }
            }
            // This endpoint failed — try next.
        }

        if (!$data) {
            return ['credits' => '', 'cached' => false, 'success' => false];
        }

        // Cache only validated successful responses, scoped to the credential pair.
        set_config($cachekey, json_encode(['at' => time(), 'data' => $data]), 'block_aiplugin_nav');

        return $data + ['cached' => false, 'success' => true];
    }

    /** Validate balances without rounding fractional credits or inventing zero. */
    private static function normalise($data) {
        if (!is_array($data) || (isset($data['success']) && $data['success'] !== true)) {
            return null;
        }
        $raw = $data['creditsRaw'] ?? $data['credits'] ?? null;
        if ($raw === 'unlimited' || $raw === -1 || $raw === '-1' || $raw === -1.0) {
            return ['credits' => 'unlimited', 'creditsRaw' => '-1', 'isUnlimited' => true];
        }
        if ((!is_string($raw) && !is_int($raw) && !is_float($raw)) ||
                !preg_match('/^\d+(?:\.\d+)?$/D', (string)$raw) ||
                !is_finite((float)$raw) || (float)$raw < 0 ||
                (!empty($data['isUnlimited']))) {
            return null;
        }
        return ['credits' => (string)$raw, 'creditsRaw' => (string)$raw, 'isUnlimited' => false];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'credits' => new external_value(PARAM_TEXT, 'Credits balance, or empty string if unavailable'),
            'creditsRaw' => new external_value(PARAM_TEXT, 'Unrounded balance', VALUE_OPTIONAL),
            'isUnlimited' => new external_value(PARAM_BOOL, 'Unlimited account', VALUE_OPTIONAL),
            'cached'  => new external_value(PARAM_BOOL, 'Whether result came from cache'),
            'success' => new external_value(PARAM_BOOL, 'Whether credits were fetched successfully'),
        ]);
    }
}
