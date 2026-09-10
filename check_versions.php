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
// Uses the canonical promotion-aware LMS Labs endpoint so Check for updates
// cannot accept a valid-but-stale manifest from a retired deployment.

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

require_login(null, false);
require_capability('moodle/site:config', context_system::instance());

header('Content-Type: application/json; charset=utf-8');

$endpoints = [
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
        // Validate it looks like our expected JSON before passing through.
        $decoded = json_decode($response, true);
        if (isset($decoded['success']) && $decoded['success'] && isset($decoded['plugins'])) {
            echo $response;
            exit;
        }
    }
    // This endpoint failed — try next.
}

// All endpoints failed.
http_response_code(502);
echo json_encode(['success' => false, 'error' => 'all_endpoints_failed']);
