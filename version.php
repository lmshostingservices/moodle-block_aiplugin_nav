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
 * Version details for the AI Dashboard Quick Links block.
 *
 * The release history lives in CHANGELOG.md.
 *
 * @package    block_aiplugin_nav
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'block_aiplugin_nav';
// Ten-digit Marketplace scheme: YYYYMMDDXX.
$plugin->version = 2026090161;
$plugin->release = 'v2.5.21';
$plugin->release_prev = '2.5.20';
$plugin->requires = 2022041900;
$plugin->supported = [400, 501];
$plugin->maturity = MATURITY_STABLE;
