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
 * Capability helpers shared by the Quick Links tool surfaces.
 *
 * @package    block_aiplugin_nav
 * @copyright  2026 LMS Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

trait block_aiplugin_nav_ai_tools_registry_trait {
    /**
     * True when the user has a capability at system level or in an enrolled course.
     *
     * @param string $capability Moodle capability name.
     * @return bool
     */
    private function has_capability_anywhere($capability) {
        global $USER;
        if (is_siteadmin()) {
            return true;
        }
        $systemcontext = context_system::instance();
        if (has_capability($capability, $systemcontext)) {
            return true;
        }
        $courses = enrol_get_my_courses('id', 'id ASC', 0);
        foreach ($courses as $course) {
            if (has_capability($capability, context_course::instance($course->id))) {
                return true;
            }
        }
        return false;
    }
}