# Changelog - AI Plugin Navigation Block

All notable changes to this plugin will be documented in this file.

## [2.5.16] - 2026-08-30

Findings from an adversarial review of the 2.5.x rewrite. All of these were shipped in earlier
2.5.x releases.

### Fixed
- **The "Check for updates" button was never rendered.** The 2.5.13 change that added it failed
  part-way through and the markup was never written to the file, leaving the state variable set,
  nothing displaying it, and a click handler bound to an element that did not exist. The button
  is there now, along with the states it was meant to bring: not yet checked, checking, check
  failed, and all-current with the time it was confirmed. A zero on a site that cannot reach the
  update server no longer reads as good news.
- **Eight of the fifteen Moodle shortcuts rendered as a generic chain-link.** The payload emitted
  icon names (layout-dashboard, graduation-cap, clipboard-check, calendar-icon, message-square,
  settings-2, users-2, bar-chart-2) that do not exist in the UI's icon set, and the lookup falls
  back silently. Dashboard, My courses, Grades, Calendar, Messages, Preferences, Cohorts and
  Reports now have their own icons.
- **Every Moodle shortcut opened in a new tab.** "External" was tested as "starts with http",
  and core links are emitted as absolute wwwroot URLs, so the site's own Dashboard and Calendar
  counted as external. A link is now external only if it is absolute and not under wwwroot.
- **Update all reported success even when every download failed.** The per-plugin callback
  ignored its result argument, so the database upgrade ran and the page reloaded with success
  messaging regardless. It now counts successes and failures, names what failed, and refuses to
  upgrade when nothing landed.
- **A free install wrote its receipt line before the install was attempted**, so a failure still
  left a receipt claiming the plugin was installed.
- **The purge schedule widget was entirely dead.** The service returns schedule_enabled,
  schedule_type, schedule_time, last_manual_purge and last_scheduled_purge; the JS read
  enabled, freq, time, lastmanual and lastauto — every one undefined. It always showed "No
  schedule" and "Manual never" however the site was configured. Field names now match, the
  weekday is returned by the service so a weekly schedule round-trips, and the timestamps are
  rendered as dates rather than raw epoch integers.
- **The credits balance stayed stale for five minutes after an unlock.** The cache-invalidation
  guard required creditsConsumed > 0, a field the API never sends, so it never fired.
- **Relative Moodle paths in custom links were always rejected**, despite the dialog offering
  them as an example. They are now resolved against wwwroot before validation.

### Changed
- The "monthly" purge frequency is no longer offered. The service treats anything that is not
  weekly as daily, so choosing monthly stored one thing in config while the task ran another.
- The Status filter is hidden on Settings and Reports. Both panels hardcode their state, so
  "Configured" and "Live" were filters that could only ever return an empty list.
- Starring an item in global search results now updates the star immediately.
- The legacy credits module is no longer loaded alongside the new UI. It wrote to element ids
  this shell never renders, so it did nothing but fire a second get_credits call on every page
  load. Savepoint 2026083057.

### Fixed
- The one-click update kept its clean flow but gained the two protections Moodle's own
  upgrade screen provides and a web service did not. Site maintenance mode was considered and
  deliberately left out: it would evict every learner and teacher for the duration, which on a
  live site mid-class is its own harm, and these are small plugin upgrades.
  - **No execution time limit.** The database upgrade ran under PHP's default
    max_execution_time, so a slow migration could be killed part-way through and leave the
    schema half-applied. This is the failure that actually damages a site.
  - **An exclusive lock**, so two admins clicking at the same moment cannot run the same
    upgrade concurrently. A second caller is told to wait rather than proceeding.
- A failed upgrade is no longer invisible. Previously any error was caught, returned, and then
  discarded by a page reload, leaving a dashboard that looked healthy on a site whose database
  was behind its code. The admin is now told what failed and taken to Moodle's own upgrade
  screen to finish the job — the plugin files are already in place, so it completes cleanly.
  Savepoint 2026083056.

## [2.5.13] - 2026-08-30

### Fixed
- The update check compared numeric versions as plain integers, which is wrong for this plugin
  family because it has used both the 10-digit (YYYYMMDDXX) and 13-digit (YYYYMMDDXXXXX)
  schemes. Compared as integers a 13-digit 2026072400116 looks larger than a 10-digit
  2026083053 despite being five weeks older, so on any site running 13-digit versions every
  update was missed and the card still read zero. It now compares the eight-digit date prefix
  first and then the trailing sequence, which is the algorithm the old UI used.

### Added
- A "Check for updates" button on the updates card, so the check can be re-run on demand
  rather than only at page load. It reports the outcome: how many updates were found, that
  everything is current, or that the server could not be reached.
- The card no longer shows a bare zero before the check has run or after it has failed. It
  distinguishes not yet checked, checking, check failed, and a genuine all-current result with
  the time it was confirmed — a zero on a site that cannot reach the update server previously
  read as good news. Savepoint 2026083054.

## [2.5.12] - 2026-08-30

### Fixed
- Release pipeline blocker and errors introduced by the previous two releases:
  - plugin_unlock's new downloadurl return value was typed PARAM_RAW. It is a URL, so it is
    now PARAM_URL.
  - The block's four user preferences are JSON documents decoded on read, so PARAM_RAW is
    correct for three of them and each now carries the pipeline's documented annotation. The
    help switch is a single flag and is now PARAM_INT, which is stricter and needs no
    annotation at all.
  - amd/build was missing a minified counterpart for ui.js, which the pipeline requires for
    every source module.
  - Removed a blank line after the class opening brace in payload.php, and reworded a comment
    that began with a lowercase variable name. Savepoint 2026083053.

## [2.5.11] - 2026-08-30

### Fixed
- Testing-stage plugins were listed in the block. Certificate Pro, which is still in testing,
  appeared in the Plugins panel and could be selected. Earlier releases only rendered such a
  plugin disabled and orange, which still put an unreleased build in front of admins. They are
  now removed outright, in two places: PHP drops any testing entry from the payload, and the
  browser applies the same rule again against check_versions.php, which is the authoritative
  source for status and catches anything the registry has not been told about. A testing
  plugin is kept only where the site already has it installed, since hiding a plugin an admin
  is actually running would remove their means of configuring or updating it.
- The testing check no longer depends on an exact-case description match, so a capitalisation
  slip in a future registry entry cannot quietly put a testing build back on offer.
- Client-specific builds are excluded on the same terms. Wilkinson Coutts Question Behaviour
  is a single-client plugin that sits in the shared registry and was being offered to every
  site. Components named in the payload's private list are dropped from the catalogue unless
  the site already runs one. Savepoint 2026083052.

## [2.5.10] - 2026-08-30

### Fixed
- "0 updates ready" was permanent, whatever the state of the site. The only place the block
  ever computed "update available" was a five-minute config cache written by the old
  plugin-management renderer — which this UI replaced, so it never runs. With the cache
  permanently cold the payload marked every plugin update=false, the card always read zero,
  and Update all had nothing to do. The check now runs in the browser against
  check_versions.php, the same source the old UI used, comparing Moodle's numeric version
  rather than the display string. Plugins the server marks as anything but ready are skipped
  so a testing build never joins the update queue, as are any with no download URL, which
  would fail the moment they were attempted. The payload carries the installed numeric
  version for the comparison. Savepoint 2026083051.

### Changed
- A row with an update now shows what it will become, not only what it is — "v3.9.9 → v4.0.1".

## [2.5.9] - 2026-08-30

### Fixed
- Version number raised to 2026083050. A build numbered 2026083018 is already installed on at
  least one production site, so every 2.5.x release up to 2026083010 was refused by Moodle as a
  downgrade. The jump to 50 leaves clear headroom above anything else published under today's
  date. Contents are otherwise identical to 2.5.8.

## [2.5.8] - 2026-08-30

### Fixed
- user_preference_allow_ajax_update() is deprecated and logged a debug notice for each of the
  block's four preferences on every page load. The preferences are now declared the supported
  way, through block_aiplugin_nav_user_preferences() in a new lib.php, and the JavaScript
  writes them through the core_user/repository module instead of M.util.set_user_preference.
  Each preference is writable only by its owner (core_user::is_current_user).
- Plugin updates failed with "Invalid parameter value detected". The install and update web
  services type expectedsha256 as PARAM_ALPHANUM, so any digest carrying a prefix, padding or
  a separator is rejected by Moodle before the call runs — and because the same malformed
  value is sent for every plugin, every update in the batch fails identically. The hash is an
  optional parameter, so a value that is not a clean 64-character hex digest is now dropped
  rather than sent, in the new UI, the legacy update-all path and the autoupdate module.
  Savepoint 2026083010.

## [2.5.7] - 2026-08-30

### Changed
- The unlock call now sends the plugin's full Moodle component alongside the short plugin id.
  A Moodle Marketplace purchase is recorded in Stripe against the component string
  (plugin_frankenstyle, e.g. mod_smartworkbook), while the block has only ever sent the short
  id (smartworkbook). Without the component the server cannot tell that a site has already
  bought a plugin, and would charge credits for it a second time. `plugin_unlock` takes it as
  an optional parameter so older callers are unaffected. Savepoint 2026083009.

## [2.5.6] - 2026-08-30

### Changed
- The unlock call now also sends the site's wwwroot as `siteUrl`. Moodle Marketplace records
  the buyer's Moodle site at checkout as a bare hostname, and the server matches that against
  the client's stored site URL to decide whether a plugin was already bought. Sending the
  live wwwroot lets that match still succeed after a domain move or when the stored client
  URL has gone stale. It is supplementary evidence only — the server continues to
  authenticate on the siteId/apiKey pair and must not trust this field in its place.
  Savepoint 2026083008.

## [2.5.5] - 2026-08-30

### Fixed
- The block read a `creditsConsumed` field from the unlock API that the API has never sent.
  Confirmed against the LMS Labs server: a new unlock returns success, message, downloadUrl
  and remainingCredits; an already-unlocked plugin returns success, alreadyUnlocked, message
  and downloadUrl. Because the missing field defaulted to zero, every unlock was about to be
  reported in the install receipt as costing nothing. The amount deducted is now derived from
  the balance before the call against remainingCredits after it, falling back to the
  advertised price when the API reports no balance, and only an already-unlocked plugin is
  recorded as free.
- The unlock response already carries the plugin's downloadUrl, which the block ignored in
  favour of a second lookup through the version-check proxy. It is now used directly, removing
  a round trip from every credit-gated install. `plugin_unlock` returns it as `downloadurl`.
  Savepoint 2026083007.

## [2.5.4] - 2026-08-30

### Added
- Install receipt. After a plugin is installed the block shows a summary above the nav cards
  listing each plugin and what it cost, the total deducted, and the resulting balance. It is
  written to a user preference before the post-install reload, so it survives the reload
  instead of vanishing with the toast. Dismissable, and capped at the last ten installs.
- Plugins that cost nothing are named in the receipt with the reason rather than a figure.
  When the unlock API reports an entitlement source, a Moodle Marketplace purchase is
  labelled as such — "Moodle Marketplace" in the receipt, and "covered by your Moodle
  Marketplace purchase" in the toast. `plugin_unlock` now passes the API's
  `entitlementSource` through as a `source` field for this. When the API does not report a
  source, the receipt says only what is certain: "No credits used".

### Fixed
- Favourites, saved layouts and the help switch did not actually persist. Moodle's
  set_user_preference AJAX endpoint rejects any preference not whitelisted during page
  generation, and none of the block's four preferences were. They are now registered with
  `user_preference_allow_ajax_update()`, so they survive a page load. Savepoint 2026083006.

## [2.5.3] - 2026-08-30

### Fixed
- The plugin row's action button said "Open" for every installed plugin, including activity
  modules and blocks, which have no site-level page to open — an activity is used inside a
  course. The action is now decided by plugin type: activities, blocks, quiz access rules,
  assignment feedback and submission plugins, availability conditions, course formats,
  enrolment methods, authentication plugins, payment gateways, plagiarism plugins, themes,
  filters, question types and editors get "Settings" pointing at their own admin settings
  page; plugins that really do have a standalone page keep "Open".

### Changed
- The unlock confirmation now states the charge in plain terms — how many credits will be
  deducted, what the balance goes from and to, and that the plugin is then installed on your
  site — and the confirm button reads "Deduct N credits & install". When the balance is short
  it says how far short. Free plugins now confirm too, saying explicitly that no credits are
  deducted, rather than installing on a single click.
- The credit balance never moved after an unlock. The confirm step reads the balance back
  from `plugin_unlock`, but it was looking for a `balance` field the service does not return —
  the service reports `creditsconsumed` and `remainingcredits`. Both are now read, the on-screen
  balance updates immediately, and an already-unlocked plugin says so instead of implying a
  charge. The deduction itself always happened server-side; only the feedback was wrong.
- Installing a plugin that needed a database upgrade left the admin on Moodle's upgrade screen.
  When the install reports `needsupgrade` the block now calls `run_upgrade` and reloads.
- Focus ring on filled buttons was accent-on-accent, so clicking Get or Open left a
  blue-on-blue selected state. Filled controls now take an ink-coloured ring clear of the
  fill, and keep their light text through :hover, :focus and :active rather than only
  :hover. Savepoint 2026083005.

## [2.5.2] - 2026-08-30

### Fixed
- Row hover tooltip rendered with no background. The tooltip is appended to `<body>`, outside
  the block, so it never inherited the palette custom properties declared on `.ainav2` and its
  `background: var(--ink)` resolved to nothing. Every var() on the tooltip now carries a literal
  fallback, and its reduced-motion rules are unscoped so they actually match.
- Theme colours bleeding through hover states. Moodle themes are Bootstrap-based and style bare
  `<a>` and `<button>` hard, so the theme's link blue landed on our accent hover fills and the
  theme's white text on our white hover surfaces. A scoped theme-isolation reset now neutralises
  the theme's colouring inside the block, and the hover rules that previously relied on inherited
  colour restate it explicitly.
- "Add link" dialog was hidden behind the theme's fixed navbar. The overlay now clears a fixed
  header and centres via `margin: auto`, so a dialog taller than the viewport scrolls instead of
  having its title bar clipped.
- "Ask AI Support" card pointed at `#` and its click handler cancelled navigation. It now links to
  `/local/moodlesupport/index.php` when local_moodlesupport is installed, and to the published
  support documentation when it is not.

### Added
- Every panel now has its own search box, sitting first in its filter bar: Search plugins,
  Search settings, Search management tools, Search reports. It matches on name, description,
  component, category and type, stacks with the chips and dropdowns, is cleared by Clear filters
  or Escape, and resets whenever a panel is opened.
- The personal Moodle links from the old block are back in the Moodle row, and are no longer
  admin-only: Dashboard, My courses, My profile, Grades, Calendar, Messages, Badges (when badges
  are enabled site-wide), Private files and Preferences. Site administration links continue to be
  appended for admins only. Savepoint 2026083004.

## [2.5.1] - 2026-08-30

### Fixed
- Added the 85 hover-help language strings the new interface requests. Without them Moodle
  logged an "Invalid get_string() identifier" debug notice for each on every dashboard load,
  and the help cards showed placeholder text. Savepoint 2026083003.

## [2.5.0] - 2026-08-30

### Changed
- **New interface.** The three dropdowns and the plugin grid are replaced by four cards that
  open panels inside the block — Plugins, Settings, Manage and Reports. Nothing navigates away
  to Site administration.
- Every panel shares one layout: category chips with counts, Status and Type filters, sort,
  Clear filters, Save layout, and collapsed category accordions.
- One row component across all four panels: favourite star, name, colour-coded Moodle type pill,
  documentation link, state chip and action button, with the description on hover.
- Home view adds Moodle shortcut tiles with a custom-link builder (69 icons), a credit traffic
  light (green above 5,000, amber to 2,000, red below), global search across everything,
  an AI Support card, plugin update and health cards, and links to Trainly, SmartForm AI
  and LLND Check.
- Favourites, saved layouts and the help switch persist as Moodle user preferences.

### Added
- `classes/payload.php` builds a JSON payload from the existing registries.
- `amd/src/ui.js` renders the interface from that payload.
- 21 new language strings; `.ainav2`-scoped styles appended to `styles.css`.

### Internal
- Nine block methods changed from private to public so the payload reads the registries
  rather than duplicating them.
- The previous interface is retained as `get_content_legacy()` for rollback.
  Savepoint 2026083000.

## [2.4.68] - 2026-08-30

### Fixed
- Long Settings, Manage, and Reports dropdowns now open above or below the
  trigger according to available viewport space.
- Dropdown height is constrained to the visible viewport and excess entries
  scroll vertically, so lower links such as Course Recertification and
  Completion Auto-Suspend remain reachable.
- No database schema changes. Savepoint 2026083001.

## [2.4.67] - 2026-08-30

### Fixed
- Added Course Recertification to the Quicklinks Settings, Manage, and Reports
  dropdowns when `local_recertify` is installed.
- Added Completion Auto-Suspend to the Quicklinks Settings, Manage, and Reports
  dropdowns when `local_completionsuspend` is installed.
- Links use each plugin's registered Moodle settings and management pages; the
  Reports links open the recertification audit log and completion activity log.
  No database schema changes. Savepoint 2026083000.

## [2.4.66] - 2026-08-24

### Changed
- Moodle coding-style pass on top of 2.4.64 only: capitalised inline comments outside GPL
  headers, split two multi-statement lines, and expanded one single-line closure.
  No registry, URL, string, capability or behaviour changes. Savepoint 2026082403.

## [2.4.65] - 2026-08-24

### Changed
- Version bump only, with no functional change from 2.4.64. The release was re-cut because earlier
  git tags were already claimed by release commits and immutable tags cannot be re-pointed.
  Savepoint 2026082402.

## [2.4.64] - 2026-08-24

### Changed
- Version bump only, no functional change. The `v2.4.63` git tag was already claimed by an earlier
  release commit and immutable tags cannot be re-pointed, so the release was re-cut as 2.4.64.
  Contents are identical to 2.4.63. Savepoint 2026082401.

## [2.4.63] - 2026-08-24

### Added
- Added documentation links for Training Pathways Block, Prerequisite 2 Enrolment, Campion Education,
  Completion Auto-Suspend, Custom Pages, Course Recertification, Student Activity Evidence,
  AI Training Simulation, and Workplace Task.
- Added the testing-stage Certificate Pro component to the complete release registry so the
  cross-registry release check remains fail-closed.

### Documentation
- Replaced the placeholder README with installation, compatibility, ecosystem, credit and support guidance.

## [2.4.62] - 2026-08-15

### Changed
- Permanently removed AI Page Templates from the active registry and documentation map.

## [2.4.61] - 2026-08-14

### Changed
- Removed the "(press / )" keyboard-shortcut hint from the AI Tools Quick Access search box placeholder, and removed the "/" keydown focus handler. Placeholder is now just "Search plugins…". Escape-to-clear behaviour unchanged. Savepoint 2026081400.

## [2.4.60] - 2026-08-13

### Changed
- Version bump for pipeline promotion (strictly > promoted 2026081208). No functional change. Savepoint 2026081209.

## [2.4.59] - 2026-08-12

### Fixed
- **White cards on white theme**: the finder had no background of its own, so its white cards blended
  into white Moodle block areas. The finder now renders on a light canvas (`--bg`) and cards carry a
  subtle default shadow, so it is legible on any theme (adapts in dark mode). Savepoint 2026081208.

## [2.4.58] - 2026-08-12

### Added
- **Per-plugin credit pricing on finder cards**: every plugin shows "500 Credits ($50 USD)"; AI RTO
  Compliance (local_rtocompliance) shows "2,000 Credits ($2000 USD)". The install button now carries
  the credit-gate data attributes and hooks into the existing plugin_unlock -> auto_install flow, so a
  user is charged/confirmed before download. NOTE: the authoritative deduction is enforced server-side
  by the lms-labs credits gateway (/api/plugin-unlock) — server pricing must be set to match. Savepoint 2026081207.

## [2.4.57] - 2026-08-12

### Fixed
- **Install icon invisible on some Moodle themes**: the green install button's download arrow used a
  stroked SVG that host themes could override, leaving a blank green square. Switched to a filled white
  glyph forced with `fill:#fff !important` so it renders regardless of theme. No DB changes. Savepoint 2026081206.

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
