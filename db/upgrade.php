<?php
// This file is part of Moodle - https://moodle.org/
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

/**
 * block_aiplugin_nav upgrade steps.
 *
 * @package    block_aiplugin_nav
 * @copyright  2026 LMS-Labs
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded -- Upgrade files require direct-access protection.
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade block_aiplugin_nav.
 *
 * @param int $oldversion Previously installed version.
 * @return bool
 */
function xmldb_block_aiplugin_nav_upgrade($oldversion) {
    if ($oldversion < 2026081100) {
        // BASELINE-10DIGIT (v2.4.50): Collapsed savepoint for sites upgrading.
        // From the baseline reset. No DB schema changes.
        upgrade_block_savepoint(true, 2026081100, 'aiplugin_nav');
    }

    if ($oldversion < 2026081101) {
        // NEW-DESIGN (v2.4.51): Category layout redesign.
        // No DB schema changes.
        upgrade_block_savepoint(true, 2026081101, 'aiplugin_nav');
    }

    if ($oldversion < 2026081201) {
        // V2.4.52 (13-digit scheme restored): layout render fixes, curl/global fixes,
        // Version-scheme correction. No DB schema changes.
        upgrade_block_savepoint(true, 2026081201, 'aiplugin_nav');
    }

    if ($oldversion < 2026081202) {
        // V2.4.53: registry (tiny_aipagetemplates), check_versions capability, coding-style.
        // And packaging bump for Moodle upload. No DB schema changes.
        upgrade_block_savepoint(true, 2026081202, 'aiplugin_nav');
    }

    if ($oldversion < 2026081203) {
        // V2.4.54: version bump for pipeline promotion (strictly > promoted 2026081202).
        // No DB schema changes.
        upgrade_block_savepoint(true, 2026081203, 'aiplugin_nav');
    }

    if ($oldversion < 2026081204) {
        // V2.4.55: version bump for pipeline promotion (strictly > promoted 2026081203).
        // No DB schema changes.
        upgrade_block_savepoint(true, 2026081204, 'aiplugin_nav');
    }

    if ($oldversion < 2026081205) {
        // V2.4.56: world-class plugin finder (search / sort / filter / Sections-Grid-List views).
        // Integrated into the Plugin Manager. No DB schema changes.
        upgrade_block_savepoint(true, 2026081205, 'aiplugin_nav');
    }

    if ($oldversion < 2026081206) {
        // V2.4.57: theme-proof install icon (filled white download glyph, forced fill).
        // No DB schema changes.
        upgrade_block_savepoint(true, 2026081206, 'aiplugin_nav');
    }

    if ($oldversion < 2026081207) {
        // V2.4.58: per-plugin credit pricing (500 credits / 2000 for RTO Compliance) shown on.
        // Finder cards; install button wired to the existing credit-unlock gate. No DB schema changes.
        upgrade_block_savepoint(true, 2026081207, 'aiplugin_nav');
    }

    if ($oldversion < 2026081208) {
        // V2.4.59: finder now renders on its own light canvas with card shadows so it is legible.
        // On white Moodle themes (was white-on-white). No DB schema changes.
        upgrade_block_savepoint(true, 2026081208, 'aiplugin_nav');
    }

    if ($oldversion < 2026081209) {
        // V2.4.60: version bump for pipeline promotion (strictly > promoted 2026081208).
        // No DB schema changes.
        upgrade_block_savepoint(true, 2026081209, 'aiplugin_nav');
    }

    if ($oldversion < 2026083000) {
        // V2.4.67: add Course Recertification and Completion Auto-Suspend to.
        // The Settings, Manage, and Reports Quicklinks registries.
        // No DB schema changes.
        upgrade_block_savepoint(true, 2026083000, 'aiplugin_nav');
    }

    if ($oldversion < 2026083001) {
        // V2.4.68: keep long dropdowns within the viewport and make them scrollable.
        // No DB schema changes.
        upgrade_block_savepoint(true, 2026083001, 'aiplugin_nav');
    }

    if ($oldversion < 2026083002) {
        // V2.4.69: add the installed-plugin action launcher.
        // No DB schema changes.
        upgrade_block_savepoint(true, 2026083002, 'aiplugin_nav');
    }

    return true;
}
