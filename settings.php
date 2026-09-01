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
 * Settings for the AI Plugin Navigation block.
 *
 * @package    block_aiplugin_nav
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings->add(new admin_setting_heading(
        'block_aiplugin_nav/aitools_header',
        get_string('aitools_settings_header', 'block_aiplugin_nav'),
        get_string('aitools_settings_desc', 'block_aiplugin_nav')
    ));

    $settings->add(new admin_setting_configtext(
        'block_aiplugin_nav/minwidth',
        get_string('minwidth', 'block_aiplugin_nav'),
        get_string('minwidth_desc', 'block_aiplugin_nav'),
        0,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configcheckbox(
        'block_aiplugin_nav/aitools_collapsed_default',
        get_string('aitools_collapsed_default', 'block_aiplugin_nav'),
        get_string('aitools_collapsed_default_desc', 'block_aiplugin_nav'),
        0
    ));
}
