# Changelog - AI Plugin Navigation Block

All notable changes to this plugin will be documented in this file.

## [2.4.56] - 2026-08-12

### Added
- **World-class plugin finder**: the Plugin Manager now renders a premium search / sort / filter
  experience — live search (press `/`), sort (A–Z, recently added, most popular, installed first),
  category filter chips with live counts, and Sections / Grid / List views. Install action is now a
  green download icon; installed plugins show version + tick. Namespaced CSS (`.ainav-fdr`) to coexist
  with Moodle themes. No DB schema changes. Savepoint 2026081205.

## [2.4.55] - 2026-08-12

### Fixed
- **Category headings showed literal `&amp;`**: Every super-group category title containing an
  ampersand (e.g. "AI Grading &amp; Assessment", "Administration &amp; Operations" columns) rendered
  the raw entity because the titles were passed to `render_cat_col()` already HTML-encoded and then
  encoded a second time by `htmlspecialchars()`. Titles are now plain text (`&`) and encoded once.
- **Administration & Operations grid collapsed**: the admin category grid used
  `minmax(160px, 1fr)` (and `140px` on medium screens), squeezing 10 columns so tightly that
  plugin names truncated to single letters and "Not Installed" wrapped. Widened to
  `minmax(210px, 1fr)` (190px on medium) so columns wrap cleanly and card content stays legible.
  The AI grid changed from a forced 5-column layout to `auto-fit minmax(230px, 1fr)` for the same reason.
- **Duplicate "Slides" card**: `mod_slides` was listed twice in `get_complete_plugin_registry()`
  (a second stub entry with icon `box` and description "Slides."). The stub duplicate was removed.
- **Version shown as "v?"**: installed plugins whose `version.php` has no `release` string showed
  "v?". A new `format_version_label()` helper falls back to the numeric version and strips a leading
  "v" (also prevents "vv2.4.51"). Applied to both the compact card and row card.

### Fixed (full audit pass — 12 Aug 2026)
- **Missing `global $USER` in `get_content()`**: caused an undefined-variable warning and a null
  read on every page render for non-admin staff (editing teachers etc.), silently hiding the credits
  badge from the very roles it targets. Added `$USER` to the global declaration.
- **`check_versions.php` fatal `Class "curl" not found`**: the position-#1 version-check proxy called
  `new \curl()` without `require_once($CFG->libdir.'/filelib.php')`, so every check failed through to
  slow cross-origin fallbacks. Added the require.
- **Check-for-Updates button could freeze**: `updateStatusLabels()`'s JSON-parse catch referenced
  `btn`/`originalHtml` from an outer scope, throwing a ReferenceError that left the button stuck.
  Removed the out-of-scope reset (the caller already resets the button).
- **Robust version comparison**: client `compareVersions()` did a naive `parseInt` magnitude compare
  that mis-handles 10-digit vs 13-digit Moodle numerics. Rewritten to compare the YYYYMMDD date prefix
  then the sequence, so mixed-width numerics compare correctly.
- **Changelog modal DOM XSS**: `autoupdate.js` built the "What's Changed" modal from unescaped server
  strings. Now HTML-escapes changelog entries and the version before insertion. Minified build synced.
- **Blank plugin icons**: `box`, `archive`, `file-check-2`, and `bell` icon keys were used but absent
  from `get_icon_svg()`, rendering empty SVGs (RPL Kit, SCORM Compress, RTO Compliance Dashboard, etc.).
  Added all four SVGs.
- **Missing Docs buttons**: added docs URLs for `local_downalert` and `block_rtocompliance`.
- **Privacy (GDPR) declaration was false**: provider declared `null_provider` while the plugin stores
  `block_aiplugin_nav_purge.purged_by` and two user preferences. Replaced with a full metadata +
  request + userlist privacy provider (export/delete) and added the metadata lang strings.
- **DB portability**: removed non-portable `LIMIT 1` from `get_purge_status` queries (now
  `IGNORE_MULTIPLE`); validated `save_purge_schedule` time to 00:00–23:59; guarded `plugin_unlock`
  error coalescing against an undefined index.
- **External API hygiene**: `custom_links` functions now call `validate_context()`.
- **Moodle support range**: `$plugin->supported` raised to `[400, 501]` (through Moodle 5.1).

NOTE: `$plugin->version` = `2026081201` (10-digit, matching the release pipeline's required scheme).
Caveat: any site still on a 13-digit build (e.g. 2026081100221) will not auto-upgrade to a 10-digit
numeric; verify installed numerics on target sites before relying on auto-update.

- **Cross-plugin registry**: added `tiny_aipagetemplates` (AI Page Templates) to
  `get_complete_plugin_registry()` (+ docs URL) so the pipeline registry check passes.
- **`check_versions.php` capability**: added `require_capability('moodle/site:config')` — the
  version-check proxy is only used by the admin Check-for-Updates flow.
- **Coding style**: AMD `function(` → `function (`; expanded single-line conditionals; capitalised
  introduced inline comments.

No DB schema changes. Savepoint 2026081204.


## [2.3.48] - 2026-04-23

### Fixed
- **sectionerror on admin/settings.php?section=blocksettingaiplugin_nav**: Settings section
  failed to register in Moodle's admin tree, causing a moodle_exception (sectionerror) when
  visiting the block's settings page directly. Root cause: settings.php used `$ADMIN->fulltree`
  as the guard condition — `$ADMIN` may not be fully initialised during certain admin tree
  build paths in Moodle 4.5+. Fixed by replacing with the standard `$hassiteconfig` guard
  which is always defined and set correctly by Moodle before any settings.php is included.
- **Missing upgrade.php savepoint for v2.3.47**: v2.3.47 was released without an upgrade.php
  savepoint. If the upgrade process failed partway through from v2.3.45 or earlier, the DB
  version could be left at an intermediate value preventing the settings section from loading.
  Savepoint for v2.3.47 (2026042200047) and v2.3.48 (2026042300048) both added.

## [2.3.47] - 2026-04-22

### Fixed
- **Em dash in inline JS causing Check for Updates and all block JS to stop working**: The
  notification string inside get_required_javascript() contained a U+2014 em dash. Since this is
  emitted via js_amd_inline(), Moodle bundles it into RequireJS first.js bootstrap. The non-ASCII
  byte caused a SyntaxError, throwing No define call for core/first and aborting the entire AMD
  chain. This silently broke all block JavaScript: dropdown menus, Plugin Manager, version
  checking, credit purchase flow, and the Check for Updates button. Fixed by replacing em dash
  with plain ASCII hyphen. Same class of encoding bug as v2.3.46. No DB schema changes.


## [2.3.34] - 2026-03-27

### Added
- **Paddle Price Mapping in Reports**: When `paygw_paddle` is installed, a "Paddle Payment Gateway" entry now appears in the Reports dropdown of the QuickLinks block. Clicking it opens `/payment/gateway/paddle/admin/pricemap.php` — the per-course Paddle Price ID mapping admin page. Previously the Reports section had no link to Price Mapping (only the transaction reports page was accessible via `page_url`). Added `report_url` to the `paygw_paddle` registry entry in `block_aiplugin_nav.php`.

## [2.3.33] - 2026-03-27

### Changed
- VERSION BUMP: Clean master release bump following v2.3.32 AI Quiz Maker link fix.

## [2.3.31] - 2026-03-25
- FIX: AI Grader Central Config (local_aiconfig) not appearing in block after manual install via Moodle admin UI
- FIX: Plugin Manager grid cross-request cache (5-min TTL) had no invalidation on manual plugin install — now busts immediately by hashing plugin-type counts across 12 Moodle plugin types; if count changes, cache is discarded
- FIX: Settings dropdown "Install First" badge always showed as amber regardless of install state — now shows green "Foundation" badge when local_aiconfig is already installed
- FIX: local_aiconfig was visually buried among 15+ AI plugin cards with no distinction — now rendered at the top of the AI Plugins column with a green "Foundation Plugin — Install First" group header

## [2.3.30] - 2026-03-25
- ADD: format_aicourse added to master plugin registry
- PERF: 4 performance bottlenecks fixed in plugin registry and role checks

## [2.3.10] - 2026-03-06
- NEW: Added Payment Unlock Assignment (local_paymentunlockassign) to plugin grid (Time Saving Plugins section)

## [2.3.9] - 2026-03-06
- NEW: Added Payment Unlock Assignment (local_paymentunlockassign) to settings registry

## [2.3.8] - 2026-03-04
- FIX: Auto Update All only includes installed plugins - uninstalled plugins are no longer flagged for updates

## [2.2.67] - 2025-12-29

### Added
- Credits display next to lms-labs.com link showing current credit balance
- Color-coded credits badge: red (<100), orange (<1000), green (>1000)
- Fetches credits from lms-labs.com API using Central Config credentials
- Only visible to site administrators

## [2.2.55] - 2025-12-25

### Added
- Testing plugins now show orange download icon (matching status-testing dot color #f97316)
- Tooltip on hover: "Available after testing completes"
- Click on testing plugin install button shows warning notification

## [2.2.12] - 2025-12-22

### Added
- Role-based filtering: hide Site Administration and Themes buttons for lmshsadmin role

## [2.2.11] - 2025-12-22

### Added
- Change Site Font plugin to registry with docs URL
- Cohort Branding plugin to registry with docs URL
- Assignment Benchmarks (gradingform_checklist) plugin to registry with docs URL

## [2.2.10] - 2025-12-22

### Fixed
- Added missing 'lock' and 'navigation' icons for Groups Availability Condition and Activity Navigation plugins

## [2.2.9] - 2025-12-22

### Fixed
- Fixed Training Matrix grouped block icons not displaying (added explicit CSS for .ainav-pm-icon-compact)
- Added CSS color fallbacks for browsers not supporting color-mix()

## [2.2.8] - 2025-12-22

### Changed
- Removed download icons from action column - only show green tick when plugin is installed

## [2.2.7] - 2025-12-22

### Fixed
- Fixed 5 incorrect docs URLs: AI Support, Dashboard Block, Activity Navigation, Course Version, removed AI Course Format

## [2.2.6] - 2025-12-22

### Added
- Blue "Go to" icon for admin plugins with direct access URLs (AI Support, RTO Compliance, Training Matrix)
- New action icon states: green tick circle for installed, $ icon for purchase, download icon for free plugins

### Changed
- Action column redesigned with conditional icons based on installation/purchase status
- Green tick uses 1px border outline (not filled) for cleaner appearance

## [2.2.5] - 2025-12-22

### Added
- AI Support plugin added to registry (local plugin only)
- Purple header button for quick access to AI Support when installed

## [2.2.3] - 2025-12-22

### Security
- Added Privacy API provider for GDPR compliance

## [2.2.2] - 2025-12-22

### Changed
- Added official Moodle 5.x compatibility declaration (`$plugin->supported = [400, 500]`)



## [2.2.0] - 2025-12-21

### Fixed
- **Responsive Grid Layout**: Fixed 3-column grid (AI Plugins, Blocks, Time Saving Plugins) to properly resize at all viewport widths
- Changed from 4-column with span to proper 3-column layout using CSS minmax()
- Added better breakpoints at 1400px, 1000px, and 768px for smooth responsive transitions
- Improved gap spacing for better visual separation between columns

## [2.1.9] - 2025-12-20

### Changed
- Migrated to centralized download architecture
- Aligned design with lms-labs.com (Inter font, HSL colors)

## [2.1.0] - 2025-12-01

### Added
- Navigation block for AI Grader plugin suite
- Icon-based plugin links
- Responsive layout

## [1.0.0] - 2025-01-01

### Added
- Initial release
- Navigation block for AI plugins
- Moodle 4.0+ compatibility
