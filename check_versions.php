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
 * block_aiplugin_nav file.
 *
 * @package    block_aiplugin_nav
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

// Server-side proxy for plugin version checking.
// Tries multiple endpoints in order — resilient to DNS/firewall issues on the Moodle server.
// Called by block_aiplugin_nav.php JS as fallback attempt #2 (after direct browser call fails).
//
// FIX-ENDPOINT-ORDER (v2.4.16): Replit URL moved to position #1 because lms-labs.com is
// Unreachable from Vultr-hosted Moodle servers (datacenter IP blocking). essaygraderai.app
// (old legacy domain) removed — no longer operational. Timeout reduced from 10s to 5s per
// Endpoint so total worst-case wait drops from 30s to 10s.

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

require_login(null, false);
require_capability('moodle/site:config', context_system::instance());

header('Content-Type: application/json; charset=utf-8');

$endpoints = [
    'https://ai-grader-site-nct185.replit.app/api/plugins/versions',
    'https://lms-labs.com/api/plugins/versions',
];

require_once($CFG->libdir . '/filelib.php'); // The curl class is not autoloaded.
$curl = new \curl();
$curl->setopt([
    'CURLOPT_TIMEOUT'        => 5,
    'CURLOPT_CONNECTTIMEOUT' => 3,
    'CURLOPT_SSL_VERIFYPEER' => true,
    'CURLOPT_USERAGENT'      => 'Moodle-Block-AIPluginNav/' . get_config('block_aiplugin_nav', 'version'),
]);

foreach ($endpoints as $url) {
    $response = $curl->get($url);
    $info     = $curl->get_info();
    $httpcode = isset($info['http_code']) ? (int)$info['http_code'] : 0;

    if ($response !== false && $httpcode === 200) {
        // Validate it looks like our expected JSON before passing through
        $decoded = json_decode($response, true);
        if (isset($decoded['success']) && $decoded['success'] && isset($decoded['plugins'])) {
            echo $response;
            exit;
        }
    }
    // This endpoint failed — try next
}

// All endpoints failed
http_response_code(502);
echo json_encode(['success' => false, 'error' => 'all_endpoints_failed']);
