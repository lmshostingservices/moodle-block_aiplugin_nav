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
 * Activity progress data contract for the Course Report.
 *
 * @package    block_aiplugin_nav
 * @copyright  2026 LMS Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface activity_provider {
    /**
     * Component served by this provider.
     *
     * @return string
     */
    public static function component(): string;

    /**
     * Get one student's progress in an activity.
     *
     * Callers must enforce course/report capabilities before asking for student data.
     *
     * @param int $cmid Course-module id.
     * @param int $userid Student id.
     * @return array|null Null when the course module is not this provider's activity.
     */
    public static function metrics(int $cmid, int $userid): ?array;
}
