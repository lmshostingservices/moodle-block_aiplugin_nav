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
 * Live plugin data from LMS Labs, the single source of truth for versions, prices and copy.
 *
 * @package    block_aiplugin_nav
 * @copyright  2026 LMS Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_aiplugin_nav\local;

/**
 * Fetches and caches the two LMS Labs feeds the block reads.
 *
 * - versions: /api/plugins/versions. Per component: the latest release, numeric version,
 *   download, readiness status and unlock price. Uploading a new release to LMS Labs is
 *   enough for the block to show it.
 * - spotlight: /api/plugins/spotlight. The editorial catalogue: which plugins are promoted
 *   (spotlightEligible), in what order, with their copy, features and preview image.
 *
 * Nothing here runs while a page is being built. The feeds are fetched by the hourly
 * refresh task, and the versions feed is also stored whenever an admin's browser runs the
 * update check (check_versions.php), so a new release shows up on the next page view.
 * Pages only read the cache. When a feed has never been fetched or is older than MAX_AGE,
 * callers get null and fall back to the data bundled with the plugin.
 *
 * @package    block_aiplugin_nav
 * @copyright  2026 LMS Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class live_feed {
    /** @var string Versions feed name. */
    public const VERSIONS = 'versions';

    /** @var string Spotlight feed name. */
    public const SPOTLIGHT = 'spotlight';

    /** @var array Feed name => endpoints, tried in order. */
    private const ENDPOINTS = [
        self::VERSIONS => [
            'https://lms-labs.com/api/plugins/versions',
            'https://ai-grader-site-nct185.replit.app/api/plugins/versions',
        ],
        self::SPOTLIGHT => [
            'https://lms-labs.com/api/plugins/spotlight',
            'https://ai-grader-site-nct185.replit.app/api/plugins/spotlight',
        ],
    ];

    /** @var int A cached feed older than this (seconds) is not used. */
    public const MAX_AGE = DAYSECS;

    /** @var string[] Hosts that may serve spotlight preview images. */
    public const IMAGE_HOSTS = ['lms-labs.com', 'www.lms-labs.com', 'ai-grader-site-nct185.replit.app'];

    /**
     * The versions feed, keyed by component, or null when there is no usable copy.
     *
     * @return array|null
     */
    public static function versions(): ?array {
        return self::read(self::VERSIONS);
    }

    /**
     * The spotlight feed's plugin list in display order, or null when there is no usable copy.
     *
     * @return array|null
     */
    public static function spotlight(): ?array {
        return self::read(self::SPOTLIGHT);
    }

    /**
     * When a feed was last fetched successfully.
     *
     * @param string $feed self::VERSIONS or self::SPOTLIGHT.
     * @return int Unix time, or 0 when never.
     */
    public static function fetched(string $feed): int {
        $entry = self::cache()->get($feed);
        return is_array($entry) ? (int) ($entry['fetched'] ?? 0) : 0;
    }

    /**
     * Fetch a feed from LMS Labs and cache it.
     *
     * @param string $feed self::VERSIONS or self::SPOTLIGHT.
     * @return bool True when a valid feed was fetched and stored.
     */
    public static function refresh(string $feed): bool {
        global $CFG;
        if (!isset(self::ENDPOINTS[$feed])) {
            return false;
        }
        require_once($CFG->libdir . '/filelib.php');
        foreach (self::ENDPOINTS[$feed] as $url) {
            $curl = new \curl();
            $curl->setopt([
                'CURLOPT_TIMEOUT' => 10,
                'CURLOPT_CONNECTTIMEOUT' => 5,
                'CURLOPT_SSL_VERIFYPEER' => true,
            ]);
            $curl->setHeader(['Accept: application/json']);
            $response = $curl->get($url);
            $info = $curl->get_info();
            if ($response !== false && (int) ($info['http_code'] ?? 0) === 200) {
                $decoded = json_decode((string) $response, true);
                if (is_array($decoded) && self::store($feed, $decoded)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Validate and cache a decoded feed response.
     *
     * @param string $feed self::VERSIONS or self::SPOTLIGHT.
     * @param array $decoded The decoded JSON response.
     * @return bool True when the response was valid and is now cached.
     */
    public static function store(string $feed, array $decoded): bool {
        if (empty($decoded['success']) || !isset($decoded['plugins']) || !is_array($decoded['plugins'])) {
            return false;
        }
        $plugins = $decoded['plugins'];
        if ($feed === self::VERSIONS) {
            // Keyed by component.
            if (!$plugins || self::is_list($plugins)) {
                return false;
            }
        } else if ($feed === self::SPOTLIGHT) {
            // An ordered list of entries.
            if (!self::is_list($plugins)) {
                return false;
            }
        } else {
            return false;
        }
        self::cache()->set($feed, ['fetched' => time(), 'plugins' => $plugins]);
        return true;
    }

    /**
     * The latest release of a component, e.g. "2.5.36", from the versions feed.
     *
     * @param array|null $entry The component's versions feed entry.
     * @return string The release without a leading v, or '' when unknown or malformed.
     */
    public static function release(?array $entry): string {
        $release = ltrim(trim((string) ($entry['version'] ?? '')), 'vV');
        return preg_match('/^\d+(\.\d+){0,3}([-+][0-9A-Za-z.]+)?$/', $release) ? $release : '';
    }

    /**
     * The unlock price in credits from a versions feed entry.
     *
     * @param array|null $entry The component's versions feed entry.
     * @return int|null Credits, or null when the entry carries no price.
     */
    public static function credits(?array $entry): ?int {
        foreach (['credits', 'creditsRequired', 'credits_required', 'creditCost', 'credit_cost'] as $key) {
            if (isset($entry[$key]) && is_numeric($entry[$key]) && $entry[$key] >= 0) {
                return (int) $entry[$key];
            }
        }
        return null;
    }

    /**
     * Whether a versions feed entry marks the plugin as withdrawn or not yet released.
     *
     * @param array|null $entry The component's versions feed entry.
     * @return bool True when the feed says the plugin is not ready.
     */
    public static function not_ready(?array $entry): bool {
        return $entry !== null && isset($entry['status']) && $entry['status'] !== 'ready';
    }

    /**
     * Accept a preview image URL only when LMS Labs serves it over HTTPS.
     *
     * @param mixed $url Candidate URL from the spotlight feed.
     * @return string The URL, or '' when it is not acceptable.
     */
    public static function image_url($url): string {
        $url = (string) $url;
        if (!preg_match('#^https://([a-z0-9.-]+)/[A-Za-z0-9._~/%-]+\.(jpe?g|png|webp)(\?[A-Za-z0-9=&._-]*)?$#', $url, $m)) {
            return '';
        }
        return in_array(strtolower($m[1]), self::IMAGE_HOSTS, true) ? $url : '';
    }

    /**
     * Whether an array is a plain list (keys 0..n-1). array_is_list() needs PHP 8.1.
     *
     * @param array $value
     * @return bool
     */
    private static function is_list(array $value): bool {
        return array_keys($value) === range(0, count($value) - 1) || $value === [];
    }

    /**
     * Read a cached feed if it is fresh enough.
     *
     * @param string $feed self::VERSIONS or self::SPOTLIGHT.
     * @return array|null
     */
    private static function read(string $feed): ?array {
        $entry = self::cache()->get($feed);
        if (!is_array($entry) || !isset($entry['plugins']) || !is_array($entry['plugins'])) {
            return null;
        }
        if (time() - (int) ($entry['fetched'] ?? 0) > self::MAX_AGE) {
            return null;
        }
        return $entry['plugins'];
    }

    /**
     * The application cache that holds both feeds.
     *
     * @return \cache
     */
    private static function cache(): \cache {
        return \cache::make('block_aiplugin_nav', 'livefeed');
    }
}
