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
 * Scheduled task that refreshes the LMS Labs plugin feeds.
 *
 * @package    block_aiplugin_nav
 * @copyright  2026 LMS Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_aiplugin_nav\task;

use block_aiplugin_nav\local\live_feed;

/**
 * Fetches the versions and spotlight feeds from LMS Labs and caches them.
 *
 * @package    block_aiplugin_nav
 * @copyright  2026 LMS Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class refresh_live_feed_task extends \core\task\scheduled_task {
    /**
     * Get task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_refresh_live_feed', 'block_aiplugin_nav');
    }

    /**
     * Fetch both feeds. A failed fetch keeps the previous copy until it expires.
     */
    public function execute() {
        foreach ([live_feed::VERSIONS, live_feed::SPOTLIGHT] as $feed) {
            $ok = live_feed::refresh($feed);
            mtrace('block_aiplugin_nav: ' . $feed . ' feed ' . ($ok ? 'refreshed' : 'not available, keeping the previous copy'));
        }
    }
}
