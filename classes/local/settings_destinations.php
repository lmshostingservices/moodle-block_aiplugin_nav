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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Compatibility with obsolete settings destinations in older catalogue generators.
 *
 * @package    block_aiplugin_nav
 * @copyright  2026 LMS Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_aiplugin_nav\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Correct known obsolete destinations without overriding future reviewed routes.
 */
final class settings_destinations {
    /**
     * Repair only the exact legacy settings values, preserving membership and prices.
     *
     * Empty replacements intentionally remove settings pages the plugin does not register.
     * Native admin-tree and capability checks remain the responsibility of the payload.
     *
     * @param array $registry Generated master registry.
     * @return array Registry with known obsolete destinations repaired.
     */
    public static function apply(array $registry): array {
        $legacy = [
            'mod_smartworkbook' => ['/admin/settings.php?section=modsettingsmartworkbook', ''],
            'block_rtocompliance' => ['/admin/settings.php?section=blocksettingrtocompliance', ''],
            'local_rplkit' => ['/admin/settings.php?section=local_rplkit', ''],
            'block_my_progress' => ['/admin/settings.php?section=blocksettingmy_progress', ''],
            'block_my_students_progress' => ['/admin/settings.php?section=blocksettingmy_students_progress', ''],
            'gradingform_benchmarks' => [
                '/admin/settings.php?section=gradingformbenchmarks', '/grade/grading/form/benchmarks/admin.php',
            ],
            'quizaccess_aigrader' => [
                '/admin/settings.php?section=quizaccessaigrader', '/admin/settings.php?section=modsettingsquizcataigrader',
            ],
            'local_ailogin' => ['/admin/settings.php?section=local_ailogin_admin', '/local/ailogin/admin.php'],
            'local_beacon' => [
                '/admin/settings.php?section=local_beacon', '/admin/settings.php?section=local_beacon_settings',
            ],
        ];
        foreach ($legacy as $component => [$old, $reviewed]) {
            if (isset($registry[$component]) && ($registry[$component]['settings_url'] ?? null) === $old) {
                $registry[$component]['settings_url'] = $reviewed;
                if ($component === 'local_beacon') {
                    // Only older catalogue rows lack these fields. Never replace newer authored values.
                    if (!array_key_exists('settings_name', $registry[$component])) {
                        $registry[$component]['settings_name'] = 'Beacon — Settings';
                    }
                    if (!array_key_exists('additional_settings', $registry[$component])) {
                        $registry[$component]['additional_settings'] = [[
                            'name' => 'Beacon — KPI Settings',
                            'url' => '/admin/settings.php?section=local_beacon_kpitargets',
                        ]];
                    }
                }
            }
        }
        // Client Portal: older catalogue rows lack the plugin type and name, so Quick links never saw it as
        // installed, and point the report at the clients list. Fill the gaps; never replace newer authored values.
        if (isset($registry['local_clientportal'])) {
            $row = &$registry['local_clientportal'];
            $row += [
                'plugin_type' => 'local',
                'plugin_name' => 'clientportal',
                'settings_url' => '/admin/settings.php?section=local_clientportal',
                'page_url' => '/local/clientportal/admin/clients.php',
            ];
            if (($row['report_url'] ?? '') === '/local/clientportal/admin/clients.php') {
                $row['report_url'] = '/local/clientportal/admin/report.php';
                $row['report_name'] = 'Client Portal — Clients report';
            }
            unset($row);
        }
        return $registry;
    }
}
