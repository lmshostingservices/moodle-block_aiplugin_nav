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
 * block_aiplugin_nav upgrade steps.
 *
 * @package    block_aiplugin_nav
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_block_aiplugin_nav_upgrade($oldversion) {
    if ($oldversion < 2026081100) {
        // BASELINE-10DIGIT (v2.4.50): Collapsed savepoint for sites upgrading
        // from the baseline reset. No DB schema changes.
        upgrade_block_savepoint(true, 2026081100, 'aiplugin_nav');
    }

    if ($oldversion < 2026081101) {
        // NEW-DESIGN (v2.4.51): Category layout redesign.
        // No DB schema changes.
        upgrade_block_savepoint(true, 2026081101, 'aiplugin_nav');
    }

    if ($oldversion < 2026081201) {
        // v2.4.52 (13-digit scheme restored): layout render fixes, curl/global fixes,
        // version-scheme correction. No DB schema changes.
        upgrade_block_savepoint(true, 2026081201, 'aiplugin_nav');
    }

    if ($oldversion < 2026081202) {
        // v2.4.53: registry (tiny_aipagetemplates), check_versions capability, coding-style
        // and packaging bump for Moodle upload. No DB schema changes.
        upgrade_block_savepoint(true, 2026081202, 'aiplugin_nav');
    }

    if ($oldversion < 2026081203) {
        // v2.4.54: version bump for pipeline promotion (strictly > promoted 2026081202).
        // No DB schema changes.
        upgrade_block_savepoint(true, 2026081203, 'aiplugin_nav');
    }

    if ($oldversion < 2026081204) {
        // v2.4.55: version bump for pipeline promotion (strictly > promoted 2026081203).
        // No DB schema changes.
        upgrade_block_savepoint(true, 2026081204, 'aiplugin_nav');
    }

    if ($oldversion < 2026081205) {
        // v2.4.56: world-class plugin finder (search / sort / filter / Sections-Grid-List views)
        // integrated into the Plugin Manager. No DB schema changes.
        upgrade_block_savepoint(true, 2026081205, 'aiplugin_nav');
    }

    if ($oldversion < 2026081206) {
        // v2.4.57: theme-proof install icon (filled white download glyph, forced fill).
        // No DB schema changes.
        upgrade_block_savepoint(true, 2026081206, 'aiplugin_nav');
    }

    if ($oldversion < 2026081207) {
        // v2.4.58: per-plugin credit pricing (500 credits / 2000 for RTO Compliance) shown on
        // finder cards; install button wired to the existing credit-unlock gate. No DB schema changes.
        upgrade_block_savepoint(true, 2026081207, 'aiplugin_nav');
    }

    if ($oldversion < 2026081208) {
        // v2.4.59: finder now renders on its own light canvas with card shadows so it is legible
        // on white Moodle themes (was white-on-white). No DB schema changes.
        upgrade_block_savepoint(true, 2026081208, 'aiplugin_nav');
    }

    if ($oldversion < 2026081209) {
        // v2.4.60: version bump for pipeline promotion (strictly > promoted 2026081208).
        // No DB schema changes.
        upgrade_block_savepoint(true, 2026081209, 'aiplugin_nav');
    }

    if ($oldversion < 2026083000) {
        // v2.4.67: add Course Recertification and Completion Auto-Suspend to
        // the Settings, Manage, and Reports Quicklinks registries.
        // No DB schema changes.
        upgrade_block_savepoint(true, 2026083000, 'aiplugin_nav');
    }

    return true;
}
