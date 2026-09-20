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
 * Plugin spotlight: the rotating promotional hero and poster row on the home view.
 *
 * @package    block_aiplugin_nav
 * @copyright  2026 LMS Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_aiplugin_nav\local;

/**
 * Builds the spotlight model the block's JavaScript renders.
 *
 * Promotion is deliberately separate from installed-plugin navigation. The list of what is
 * promoted comes from a bundled editorial snapshot (generated/spotlight_catalogue.json), which
 * holds only plugins that are publicly listed and owner-approved for promotion. The release
 * feed's "ready" flag is not treated as approval. Each snapshot entry is then joined to the row
 * the Plugins panel already built for it, so the spotlight can never show a plugin the panel
 * would not list, and price, install state and the open link always agree with that row.
 * A plugin held out of the spotlight still appears normally in the Plugins panel.
 *
 * @package    block_aiplugin_nav
 * @copyright  2026 LMS Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class spotlight {
    /** @var string User preference that holds the spotlight state for each user. */
    public const PREF = 'block_aiplugin_nav_spotlight';

    /** @var string[] The states the spotlight can be in. */
    public const STATES = ['open', 'collapsed', 'off'];

    /** @var string[] Filter categories, in the order the filter chips appear. */
    private const CATEGORIES = [
        'content',
        'grading',
        'rto',
        'reporting',
        'groups',
        'training',
        'payments',
        'branding',
        'security',
        'media',
        'monitor',
        'block',
    ];

    /** @var string[] Language string keys the JavaScript needs, without the spotlight_ prefix. */
    private const STRINGS = [
        'all',
        'brand',
        'carousel',
        'close',
        'collapse',
        'collapsedtoast',
        'component',
        'creditnote',
        'credits',
        'creditsshort',
        'docs',
        'docslabel',
        'featured',
        'get',
        'includes',
        'installed',
        'kicker',
        'morein',
        'moreinfo',
        'next',
        'nowshowing',
        'offtoast',
        'onsite',
        'ontoast',
        'open',
        'pause',
        'paused',
        'position',
        'prev',
        'price',
        'pricevalue',
        'rank',
        'resume',
        'rowtitle',
        'screenshot',
        'scrollleft',
        'scrollright',
        'show',
        'status',
        'statusinstalled',
        'statusnot',
        'toggle',
        'type',
        'usage',
        'usageprefix',
    ];

    /** @var string The only host the documentation links may point at. */
    private const DOCS_PREFIX = 'https://lms-labs.com/docs/';

    /**
     * Build the spotlight model, or null when there is nothing to promote.
     *
     * @param array $plugins The already-built Plugins panel rows. Empty for non-admins.
     * @param \renderer_base $output Renderer used to resolve the preview image URLs.
     * @return array|null The model, or null when no promoted plugin has a matching row.
     */
    public static function build(array $plugins, \renderer_base $output): ?array {
        $rows = [];
        foreach ($plugins as $row) {
            if (!empty($row['component'])) {
                $rows[$row['component']] = $row;
            }
        }

        $items = [];
        foreach (self::catalogue() as $entry) {
            $row = $rows[$entry['component'] ?? ''] ?? null;
            if ($row === null || ($row['status'] ?? 'ready') !== 'ready') {
                continue;
            }
            $item = self::item($entry, $row, $output);
            if ($item !== null) {
                $item['rank'] = count($items) + 1;
                $items[] = $item;
            }
        }

        if (!$items) {
            return null;
        }

        return [
            'state' => self::user_state(),
            'items' => $items,
            'categories' => self::category_labels(),
            'strings' => self::strings(),
        ];
    }

    /**
     * The state the spotlight starts in for the current user.
     *
     * A user who has never used the switch or the collapse control gets the site default.
     *
     * @return string One of self::STATES.
     */
    public static function user_state(): string {
        $default = self::site_default();
        $state = (string) get_user_preferences(self::PREF, $default);
        return in_array($state, self::STATES, true) ? $state : $default;
    }

    /**
     * The site-wide starting state, from the spotlight_default setting.
     *
     * @return string One of self::STATES.
     */
    public static function site_default(): string {
        $default = (string) get_config('block_aiplugin_nav', 'spotlight_default');
        return in_array($default, self::STATES, true) ? $default : 'open';
    }

    /**
     * Read the bundled editorial snapshot.
     *
     * @return array The snapshot's plugin entries, in display order. Empty when unreadable.
     */
    private static function catalogue(): array {
        $file = __DIR__ . '/../../generated/spotlight_catalogue.json';
        if (!is_readable($file)) {
            debugging('block_aiplugin_nav: spotlight catalogue is missing', DEBUG_DEVELOPER);
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data) || !isset($data['plugins']) || !is_array($data['plugins'])) {
            debugging('block_aiplugin_nav: spotlight catalogue is malformed', DEBUG_DEVELOPER);
            return [];
        }
        return $data['plugins'];
    }

    /**
     * Shape one promoted plugin for the browser.
     *
     * Price, install state and the open link come from the Plugins panel row, never from the
     * snapshot, so the spotlight cannot quote a different price from the one the unlock flow
     * charges. Values that end up in markup or CSS are validated here.
     *
     * @param array $entry The snapshot entry.
     * @param array $row The matching Plugins panel row.
     * @param \renderer_base $output Renderer used to resolve the preview image URL.
     * @return array|null The item, or null when the entry is unusable.
     */
    private static function item(array $entry, array $row, \renderer_base $output): ?array {
        $component = clean_param($entry['component'] ?? '', PARAM_COMPONENT);
        $category = (string) ($entry['category'] ?? '');
        if ($component === '' || !in_array($category, self::CATEGORIES, true)) {
            return null;
        }

        $docs = (string) ($entry['docs'] ?? '');
        if (strpos($docs, self::DOCS_PREFIX) !== 0) {
            $docs = '';
        }

        $image = '';
        if (is_readable(__DIR__ . '/../../pix/spotlight/' . $component . '.jpg')) {
            $image = $output->image_url('spotlight/' . $component, 'block_aiplugin_nav')->out(false);
        }

        $features = [];
        foreach (array_slice((array) ($entry['features'] ?? []), 0, 4) as $feature) {
            $features[] = (string) $feature;
        }

        return [
            'component' => $component,
            'name' => (string) ($entry['name'] ?? $row['name']),
            'subtitle' => (string) ($entry['subtitle'] ?? ''),
            'cat' => $category,
            'type' => (string) ($entry['type'] ?? ''),
            'accent' => self::colour($entry['accent'] ?? '', '#ff6a0f'),
            'accent2' => self::colour($entry['accent2'] ?? '', '#ff8a3d'),
            'desc' => (string) ($entry['description'] ?? ''),
            'features' => $features,
            'statvalue' => (string) ($entry['stat']['value'] ?? ''),
            'statlabel' => (string) ($entry['stat']['label'] ?? ''),
            'usage' => (string) ($entry['usage'] ?? ''),
            'includes' => (string) ($entry['includes'] ?? ''),
            'docs' => $docs,
            'image' => $image,
            'pluginname' => (string) $row['name'],
            'credits' => (int) ($row['credits'] ?? 0),
            'installed' => !empty($row['installed']),
            'gotourl' => !empty($row['installed']) ? (string) ($row['gotourl'] ?? '') : '',
        ];
    }

    /**
     * Accept a six-digit hex colour, or fall back.
     *
     * @param mixed $value The candidate colour.
     * @param string $fallback The colour to use when the candidate is not valid.
     * @return string A #rrggbb colour.
     */
    private static function colour($value, string $fallback): string {
        $value = (string) $value;
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtolower($value) : $fallback;
    }

    /**
     * Translated labels for the filter categories.
     *
     * @return array Category key => label.
     */
    private static function category_labels(): array {
        $labels = [];
        foreach (self::CATEGORIES as $key) {
            $labels[$key] = get_string('spotlight_cat_' . $key, 'block_aiplugin_nav');
        }
        return $labels;
    }

    /**
     * Translated UI strings for the JavaScript.
     *
     * @return array Short key => string. Placeholders such as {$a} are filled in by the browser.
     */
    private static function strings(): array {
        $strings = [];
        foreach (self::STRINGS as $key) {
            $strings[$key] = get_string('spotlight_' . $key, 'block_aiplugin_nav');
        }
        return $strings;
    }
}
