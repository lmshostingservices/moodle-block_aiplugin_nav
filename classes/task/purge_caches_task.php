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
 * Scheduled task to purge all caches.
 *
 * @package    block_aiplugin_nav
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_aiplugin_nav\task;

/**
 * Scheduled task to purge all caches.
 */
class purge_caches_task extends \core\task\scheduled_task {
    /**
     * Get task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_purge_caches', 'block_aiplugin_nav');
    }

    /**
     * Execute the scheduled task.
     */
    public function execute() {
        global $DB;

        // Check if scheduled purge is enabled.
        $enabled = get_config('block_aiplugin_nav', 'purge_schedule_enabled');
        if (!$enabled) {
            return;
        }

        // Purge all caches — same complete purge as Moodle's admin page.
        purge_all_caches();

        // Log the purge.
        $record = new \stdClass();
        $record->purge_type = 'scheduled';
        $record->purged_at = time();
        $record->purged_by = null;
        $DB->insert_record('block_aiplugin_nav_purge', $record);

        mtrace('All caches purged by scheduled task.');
    }
}
