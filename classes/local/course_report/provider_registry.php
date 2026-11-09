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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace block_aiplugin_nav\local\course_report;

defined('MOODLE_INTERNAL') || die();

/**
 * Read-only provider registry used by the Course Report.
 *
 * @package    block_aiplugin_nav
 * @copyright  2026 LMS Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider_registry {
    /** @var array<string, class-string<activity_provider>> */
    private const PROVIDERS = [
        'mod_aisafetyhazards' => providers\ai_safety_hazards::class,
        'mod_aianatomy' => providers\ai_anatomy::class,
    ];

    /**
     * List the components with a registered report provider.
     *
     * @return string[]
     */
    public static function components(): array {
        return array_keys(self::PROVIDERS);
    }

    /**
     * Get activity metrics from the provider registered for a component.
     *
     * @param string $component
     * @param int $cmid
     * @param int $userid
     * @return array|null
     */
    public static function metrics(string $component, int $cmid, int $userid): ?array {
        $class = self::PROVIDERS[$component] ?? null;
        if (!$class || !class_exists($class) || !is_subclass_of($class, activity_provider::class)) {
            return null;
        }
        return $class::metrics($cmid, $userid);
    }
}
