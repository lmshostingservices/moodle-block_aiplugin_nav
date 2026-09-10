<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Capability helpers shared by the Quick Links tool surfaces.
 *
 * @package block_aiplugin_nav
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