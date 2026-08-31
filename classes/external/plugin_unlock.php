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
 * External function to unlock a credit-gated plugin.
 *
 * Calls POST https://lms-labs.com/api/plugin-unlock server-side so that
 * the Site ID and API Key are never exposed to the browser.
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
use context_system;

/**
 * Unlock a credit-gated Time Saving Plugin by consuming the required credits.
 * The siteId and apiKey are read from server-side config (never sent to browser).
 */
class plugin_unlock extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'pluginid' => new external_value(PARAM_ALPHANUMEXT, 'Plugin short ID (e.g. groupmanager, essayguard)'),
            // The Moodle Marketplace records a purchase against the full component string
            // (plugin_frankenstyle, e.g. mod_smartworkbook), not the short id. Sending it
            // lets the server match an existing purchase and skip the credit deduction.
            'component' => new external_value(PARAM_COMPONENT, 'Full Moodle component, e.g. mod_smartworkbook', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Unlock a plugin by consuming credits on lms-labs.com.
     *
     * @param string $pluginid Plugin short ID.
     * @return array Result with success flag, credits consumed, and remaining balance.
     */
    public static function execute(string $pluginid, string $component = '') {
        global $CFG;

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:config', $context);

        $params = self::validate_parameters(self::execute_parameters(),
            ['pluginid' => $pluginid, 'component' => $component]);

        // SESSION LOCK: Release before plugin-unlock API call (up to 15 s timeout).
        \core\session\manager::write_close();
        $pluginid  = $params['pluginid'];
        $component = $params['component'];

        // Load AI Grader Central Config library.
        $aiconfig_lib = $CFG->dirroot . '/local/aiconfig/lib.php';
        if (file_exists($aiconfig_lib)) {
            require_once($aiconfig_lib);
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
            return [
                'success'          => false,
                'alreadyunlocked'  => false,
                'creditsconsumed'  => 0,
                'remainingcredits' => '',
                'message'          => '',
                'source'           => '',
                'downloadurl'      => '',
                'error'            => 'Site ID or API Key not configured. Please configure AI Grader Central Config first.',
            ];
        }

        // POST to plugin-unlock endpoint.
        $url     = 'https://lms-labs.com/api/plugin-unlock';
        // The siteUrl field is supplementary evidence only — the server authenticates on the
        // siteId/apiKey pair and must not trust this value in its place. It is sent so
        // that a Moodle Marketplace purchase, which records the buyer's site as a bare
        // hostname at checkout, can still be matched to this site after a domain move
        // or when the stored client URL has gone stale.
        $payload = json_encode([
            'pluginId'  => $pluginid,
            'component' => $component,
            'siteId'    => $siteid,
            'apiKey'    => $apikey,
            'siteUrl'   => $CFG->wwwroot,
        ]);

        // Moodle's \curl class lives in filelib.php — load it explicitly.
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();
        $curl->setopt(['CURLOPT_TIMEOUT' => 15, 'CURLOPT_SSL_VERIFYPEER' => true, 'CURLOPT_FOLLOWLOCATION' => true]);
        $curl->setHeader(['Content-Type: application/json', 'Accept: application/json']);
        $response = $curl->post($url, $payload);
        $httpcode = $curl->info['http_code'];

        if (empty($response)) {
            return [
                'success'          => false,
                'alreadyunlocked'  => false,
                'creditsconsumed'  => 0,
                'remainingcredits' => '',
                'message'          => '',
                'source'           => '',
                'downloadurl'      => '',
                'error'            => 'No response from server. Please try again.',
            ];
        }

        $data = json_decode($response, true);
        if (!$data) {
            return [
                'success'          => false,
                'alreadyunlocked'  => false,
                'creditsconsumed'  => 0,
                'remainingcredits' => '',
                'message'          => '',
                'source'           => '',
                'downloadurl'      => '',
                'error'            => 'Invalid response from server.',
            ];
        }

        // Handle errors from the API (402 insufficient credits, 400 not a credit plugin, etc).
        if (!empty($data['error'])) {
            return [
                'success'          => false,
                'alreadyunlocked'  => false,
                'creditsconsumed'  => 0,
                'remainingcredits' => '',
                'message'          => '',
                'source'           => '',
                'downloadurl'      => '',
                'error'            => (string)($data['message'] ?? '') ?: (string)($data['error'] ?? ''),
            ];
        }

        // Successfully unlocked (or already unlocked).
        //
        // The API's actual response shape (confirmed with the LMS Labs server, 30 Aug 2026):
        //   new unlock      -> success, message, downloadUrl, remainingCredits
        //   already unlocked-> success, alreadyUnlocked, message, downloadUrl
        // There is no creditsConsumed field on either path. Earlier code read one and
        // defaulted it to 0, so every unlock reported as costing nothing. The amount
        // actually deducted is therefore derived by the caller from the balance before
        // the call and the remainingCredits after it; where remainingCredits is absent
        // (the already-unlocked path) nothing was deducted.
        $alreadyunlocked  = !empty($data['alreadyUnlocked']);
        $creditsconsumed  = (int) ($data['creditsConsumed'] ?? 0);
        $remainingcredits = isset($data['remainingCredits']) ? (string) $data['remainingCredits'] : '';
        $downloadurl      = isset($data['downloadUrl']) ? (string) $data['downloadUrl'] : '';
        $message          = (string) ($data['message'] ?? '');

        // Why this site is entitled to the plugin. The API is the only thing that knows
        // whether the site already owned it from a Moodle Marketplace purchase or from an
        // earlier credit unlock, so the value is passed straight through. An empty string
        // means the API did not say; the UI then reports only what it can prove.
        $source = (string) ($data['entitlementSource'] ?? $data['source'] ?? '');

        // Invalidate the credits cache so the block re-fetches the updated balance.
        if (!$alreadyunlocked && $creditsconsumed > 0) {
            set_config('credits_cache', '', 'block_aiplugin_nav');
            set_config('credits_cached_at', 0, 'block_aiplugin_nav');
        }

        return [
            'success'          => true,
            'alreadyunlocked'  => $alreadyunlocked,
            'creditsconsumed'  => $creditsconsumed,
            'remainingcredits' => $remainingcredits,
            'message'          => $message,
            'source'           => $source,
            'downloadurl'      => $downloadurl,
            'error'            => '',
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success'          => new external_value(PARAM_BOOL, 'Whether the unlock succeeded'),
            'alreadyunlocked'  => new external_value(PARAM_BOOL, 'True if plugin was already unlocked (no credits consumed)'),
            'creditsconsumed'  => new external_value(PARAM_INT,  'Number of credits consumed (0 if already unlocked)'),
            'remainingcredits' => new external_value(PARAM_TEXT, 'Remaining credits balance, or empty string'),
            'message'          => new external_value(PARAM_TEXT, 'Informational message from server'),
            'source'           => new external_value(PARAM_TEXT, 'Entitlement source reported by the API, e.g. marketplace; empty when not reported'),
            'downloadurl'      => new external_value(PARAM_URL, 'Download URL returned by the unlock API, or empty string'),
            'error'            => new external_value(PARAM_TEXT, 'Error message, or empty string on success'),
        ]);
    }
}
