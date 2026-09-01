# Changelog - AI Plugin Navigation Block

All notable changes to this plugin will be documented in this file.

## [2.5.23] - 2026-09-01

### Fixed
- **The block lost its accent colouring on Boost.** When no theme brand colour was set —
  Boost's default — `get_theme_primary_color()` returned the string `__DETECT_FROM_DOM__`,
  intended for JavaScript to swap out. No such JavaScript exists, so the marker went straight
  into `style="--primary: __DETECT_FROM_DOM__;"`. A custom property that is set-but-invalid is
  substituted as written rather than falling back, so `--accent` and every colour derived from
  it became invalid at computed-value time: icon backgrounds computed to `rgba(0, 0, 0, 0)` and
  the card tints and borders vanished. The property is now emitted only when the value is a
  real CSS colour, so the stylesheet's own `var(--primary, #0e6e68)` fallback applies. Theme
  settings are admin-supplied text, so the value is validated as a hex, `rgb()`, `hsl()` or
  keyword colour before it is inlined.
- **Testing-stage plugins could still appear in the catalogue.** AI Video Conference and AI
  Practical Assessment were visible on client dashboards. The rule was "hide unless the site has
  it installed", which leaks for anything Moodle reports as installed — `mod_bigbluebuttonbn`
  ships with core, so `is_plugin_installed()` is always true for it and it was always shown. A
  testing-stage plugin is now hidden unconditionally, installed or not. Client-only builds keep
  the old rule, since the owning client has nowhere else to reach them.
- **Bulk updates could leave a stale JavaScript bundle and crash the dashboard.** Moodle serves
  every AMD module as one response keyed on `jsrev`, and that key did not change when the updater
  swapped plugin files. A page request landing mid-extraction could cache a half-written bundle,
  which then persisted until someone purged caches by hand — symptom:
  `(0 , _jquery.default) is not a function` from `lib/requirejs.php`, cleared only by turning
  Cache JavaScript off. Install and update now call `js_reset_all_caches()` and
  `theme_reset_all_caches()` after writing files. Verified present and not deprecated in both
  Moodle 4.5 and 5.2.
- The updates card said how many plugins needed updating but not which. It now opens the Plugins
  panel filtered to exactly those, and says so.

### Changed
- **Type scale raised throughout.** 62% of font sizes were under 12px and the smallest was 9.5px;
  nothing reached 16px. All 94 declarations were lifted proportionally with a 12px floor, keeping
  the visual hierarchy intact.
- `supported` now declares `[400, 502]`, so the plugin is not flagged as unsupported on
  Moodle 5.2.

## [2.5.21] - 2026-09-01

Pipeline-correctness and layout release.

### Fixed
- **The status cards rendered their text one character per line** on live sites. Every
  multi-column row used a fixed column count with viewport media queries, but the block sits in a
  dashboard column far narrower than the window, so the breakpoints never fired. The card then
  squeezed its description to 0px between a fixed icon and two nowrap buttons. The rows now use
  `repeat(auto-fit, minmax(...))` and size from their own width, and the status card wraps its
  buttons onto a second line instead of crushing the text. Verified in headless Chromium at
  380/520/715/900/1100/1400px: no horizontal overflow and no crushed text at any width.
- **`amd/build/ui.min.js` was a copy of the source, not minified output** — it shared a git blob
  SHA with `amd/src/ui.js`. It is now genuine Moodle 4.5 core grunt output (rollup + babel +
  terser) and regeneration is idempotent. The stray unminified `amd/build/ui.js` was removed;
  Moodle core ships no such file.

### Changed
- The whole plugin now passes the real `moodle-plugin-ci` 4.5.11 on Moodle 4.5 / PHP 8.3:
  `phplint`, `phpcs --max-warnings 0`, `phpdoc --max-warnings 0`, `validate`, `savepoints`,
  `mustache`, `phpmd`, `grunt --max-lint-warnings 0` and `phpunit` all pass.
- `attendance_report.php` no longer interleaves PHP and HTML. Its template region is compiled to
  `echo` statements so the file has a single `<?php` and no closing tag, which is what
  `moodle.Commenting.MissingDocblock.File` requires. Output proved byte-identical to the previous
  template on both the populated and the empty-data path.
- `lang/en/block_aiplugin_nav.php`: 295 keys sorted, section comments removed, duplicate `docs`
  key dropped. All keys and values verified unchanged.
- `defined('MOODLE_INTERNAL') || die();` removed where the sniff reports it unnecessary, with
  class docblocks added to match.
- `global $PAGE` replaced with `$this->page` in the block class; empty `catch` blocks now call
  `debugging()`.
- ESLint taken to zero, including eight `complexity` warnings, by splitting functions rather than
  suppressing rules. String content verified unchanged by AST comparison throughout.

### Removed
- ~204 lines of commented-out registry entries. The eight that the release pipeline's
  cross-plugin completeness check requires (`mod_aiquiz`, `assignfeedback_aipdf`,
  `quizaccess_webcamproctor`, `mod_practicalassessment`, `mod_aivideoconf`,
  `mod_bigbluebuttonbn`, `mod_recordingsbn`, `report_performanceintel`) are now real
  registry entries carrying `'status' => 'testing'`, so they are visible to the pipeline but
  still dropped from the block's catalogue unless the site already runs one.

## [2.5.20] - 2026-08-31

Coding-standard release. No functional change: every string literal in the plugin was verified
byte-identical before and after, by tokenising the PHP and by parsing the JavaScript with a real
AST parser and folding concatenations.

### Removed
- **2,470 lines of dead code.** A call-graph analysis of all 41 methods found 28 unreachable
  from any entry point — the entire v1 dropdown interface: `get_content_legacy`,
  `render_plugin_management_section`, `render_dropdown`, `render_plugin_card`, the icon map, the
  three legacy registries and the rest. `get_content()` has not called any of them since v2.5.0.
- **The 1,226-line `get_required_javascript()` override**, which shipped an inline jQuery bundle
  on every page of the site and bound a global document click handler, targeting only `#ainav-*`
  element ids the current UI never renders.
- The `autoupdate` and `credits` AMD modules, no longer loaded by anything.

`block_aiplugin_nav.php` is now 1,778 lines, down from 5,616. All twelve surviving methods were
diffed and are byte-identical.

### Changed
- `version.php` reduced from 272 lines to 36. It carried an 8,183-character single line — the
  whole release history appended to `$plugin->maturity`. That history is preserved in this
  changelog.
- Coding standard: 326 `array()` converted to `[]`, 119 snake_case variables renamed with
  collision checking, 246 comment-block fixes, and every over-long line wrapped. The plugin now
  reports zero findings against the sniffs CI flagged.
- JavaScript: anonymous functions match Moodle's `function()` convention, nested ternaries
  replaced with if/else, 22 missing JSDoc blocks added, and `amd/build/ui.min.js` is now
  genuinely minified rather than a copy of the source.

## [2.5.19] - 2026-08-30

### Fixed
- The "Top up" button on the credit card led nowhere. It opened
  `<wwwroot>/local/lmslabs/credits.php` — a path on the customer's own Moodle site that no LMS
  Labs plugin provides, so the click produced a 404. Credits are bought from LMS Labs, so it now
  goes to https://lms-labs.com/pricing. The destination is carried in the payload as `topupurl`
  rather than hardcoded in JavaScript, so it can be changed server-side without a plugin
  release. This also fixes the Top up button inside the unlock dialog, which delegates to the
  same control. Savepoint 2026083060.

## [2.5.18] - 2026-08-30

### Fixed
- "Unexpected token '<'" on update, properly this time. 2.5.17 silenced upgrade_noncore() and
  wrapped it in an output buffer, and the error still occurred — because the problem is not a
  flag. upgrade_noncore() calls upgrade_started(), which prints a page header and flushes, and
  Moodle's upgrade machinery ends output buffers itself as it runs. Buffering around it cannot
  help. That code is written to render a page, and a web service is not a page.
  The block no longer calls run_upgrade at all. Once the plugin files are downloaded it hands
  over to /admin/index.php, Moodle's own upgrade flow, which lists what changed, asks for
  confirmation and reports errors properly. One extra click, and no failure dialog.
  Savepoint 2026083059.

## [2.5.17] - 2026-08-30

### Fixed
- Clicking Update produced "Unexpected token" and then dropped the admin on Moodle's plugin
  upgrade screens. run_upgrade() called upgrade_noncore(true) — that parameter is Moodle's
  verbose flag, so core printed HTML upgrade progress straight into what was supposed to be a
  JSON web service response. The browser hit the markup before it reached the result and threw.
  The upgrade itself completed and the 2.5.14 fallback correctly handed over to Moodle to
  finish, so no site was left half-upgraded, but the error was alarming and unnecessary. The
  upgrade now runs quietly, with any output core emits regardless discarded, so the response is
  always valid JSON. Savepoint 2026083058.

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

## Historical notes migrated from version.php

These release notes previously lived as comments inside `version.php`, where an
8,183-character single line failed the coding standard. They are preserved here
verbatim; the changelog is the right home for them.

AI Dashboard Quick Links v2.5.1 - Version information
v2.5.1: FIX-LANG-STRINGS (30 Aug 2026) — Added the 85 hover-help language strings the new
         payload requests (13 cards x badge, title, paragraphs, bullets and pro tip). Without
         them Moodle logged an "Invalid get_string() identifier" debug notice per string on
         every dashboard load and the help cards rendered placeholder text. Block behaviour
         otherwise unchanged from v2.5.0. Savepoint 2026083003.
v2.5.0: NEW-UI (30 Aug 2026) — Replaced the three dropdowns and the plugin grid with a
         card-and-panel interface that never leaves the block. Home view: four nav cards
         (Plugins / Settings / Manage / Reports), Moodle shortcut tiles with a custom-link
         builder, credit traffic light, global search, status strip and product cards.
         Each panel shares one layout: category chips, Status and Type filters, sort,
         saved layouts, collapsed category accordions and one row component carrying a
         colour-coded type pill, docs link, state chip and action.
         New: classes/payload.php builds a JSON payload from the existing registries;
         amd/src/ui.js renders the interface; styles.css gains an .ainav2-scoped section.
         Nine block methods changed from private to public so the payload can read the
         registries rather than duplicating them. Legacy UI retained as
         get_content_legacy() for rollback. No DB changes. Savepoint 2026083002.
v2.4.68: VIEWPORT-SAFE-DROPDOWNS (30 Aug 2026) — Long Settings, Manage,
         and Reports menus now open toward the side with the most available
         space, stay within the viewport, and scroll vertically. This makes
         lower entries such as Course Recertification and Completion
         Auto-Suspend reachable instead of rendering below the screen.
         No DB schema changes. Savepoint 2026083001.
v2.4.66: CODING-STYLE-PASS (24 Aug 2026) — Moodle coding-style cleanup on top
         of v2.4.64 only: capitalised inline comments outside GPL headers,
         split two multi-statement lines, and expanded one single-line closure.
         No registry, URL, string, capability or behaviour changes.
         Savepoint 2026082403.
v2.4.65: RETAG-ONLY (24 Aug 2026) — No functional change from v2.4.64. Version bumped solely
         because earlier git tags were already claimed by release commits and immutable tags
         cannot be re-pointed. Registry, docs URLs and all behaviour remain byte-identical.
         Savepoint 2026082402.
v2.4.64: RETAG-ONLY (24 Aug 2026) — No functional change from v2.4.63. Version bumped solely
         because the v2.4.63 git tag was already claimed by an earlier release commit, and
         immutable tags cannot be re-pointed, so v2.4.63 could not be promoted a second time.
         Registry, docs URLs and all behaviour are byte-identical to v2.4.63.
         Savepoint 2026082401.
v2.4.63: MARKETPLACE-DOCS-COMPLETE (24 Aug 2026) — Added documentation links
         for nine active public plugins that previously had no Docs button:
         Training Pathways Block, Prerequisite 2, Campion Education,
         Completion Auto-Suspend, Custom Pages, Course Recertification,
         Student Activity Evidence, AI Training Simulation, and Workplace
         Task. Added the testing-stage Certificate Pro component to the
         complete release registry so cross-registry validation remains
         fail-closed. No DB changes. Savepoint 2026082400.
v2.4.62: REMOVE-AIPAGETEMPLATES-FINAL (15 Aug 2026) — tiny_aipagetemplates (AI Page
         Templates) removed from the master plugin registry AND the docs URL map. It was
         removed in v2.4.39 after crashing customer sites but accidentally re-added in
         v2.4.53 with the new category layout, so the card reappeared on every site. The
         plugin is now also deprecated server-side (excluded from the lms-labs.com
         manifest), making this removal permanent at both ends. A tombstone comment in
         get_master_plugin_registry() forbids re-adding it. No DB changes.
         Savepoint 2026081500.
v2.4.61: REMOVE-SLASH-HINT (14 Aug 2026) — Removed the "(press / )" keyboard-shortcut hint
         from the AI Tools Quick Access search placeholder and the matching "/" keydown
         focus handler. Placeholder is now just "Search plugins…". The Escape-to-clear
         behaviour is unchanged. No DB changes. Savepoint 2026081400.
v2.4.51: NEW-CATEGORY-LAYOUT (11 Aug 2026) — Replaced old 3-group Plugin Manager grid
         (AI Plugins / Blocks / Time Saving) with new super-group layout matching the
         approved mockup design. Two super-groups: "AI & Learning Intelligence" (5 category
         columns: AI Grading & Assessment, AI Content & Courses, AI Voice & Media, RTO &
         Compliance, AI Personalisation) and "Administration & Operations" (9–10 category
         columns: Blocks & Dashboards, Training & Scheduling, Enrolment & Access, Academic
         Integrity, Communications, Branding & Appearance, Media & Storage, Security & Auth,
         Reporting & Analytics, Payments). All 14 new category values added to
         get_master_plugin_registry(), get_complete_plugin_registry(), and
         get_installed_plugins_by_category(). Settings dropdown updated with sub-headings per
         category group. styles.css updated with new category heading and card styles.
         Savepoint 2026081100218.
v2.4.50: ADD-SCORM-COMPRESS (11 Aug 2026) — Added local_scormcompress to the installed-plugin
         map, the What's New download list, and the docs URL map so SCORM Compress appears in
         the Quick Links block on all three surfaces.
v2.4.48: FIX-PARSE-ERROR (8 Aug 2026) — Reverted compareVersions() client JavaScript to
         a simple integer comparison. The semantic/release-string comparison added in v2.4.47
         introduced a PHP ParseError ("syntax error, unexpected identifier \"v2\"") at the
         js_amd_inline block on line ~4075, which blocked Moodle's upgrade_plugins_blocks()
         entirely. The server-side GUARD-NUMERIC pipeline gate (added in v2.4.47) is the
         correct place to enforce 13-digit numeric discipline — no client-side workaround is
         needed. Also added a mandatory php -l syntax lint gate to the release pipeline so
         any future PHP parse error in an uploaded ZIP is caught before promotion.
         Savepoint 2026080800215.
v2.4.47: FIX-NUMERIC-SCHEME (8 Aug 2026) — Corrected 10-digit $plugin->version in v2.4.46
         (2026080746) which was numerically SMALLER than prior 13-digit installed values
         (e.g. 2026080600204 from v2.4.45), causing every Moodle site to permanently show
         "all plugins up to date" — no update notification was ever triggered.
         Fix: new numeric 2026080800205 is 13 digits and strictly greater than every prior
         release. Added permanent guardrail in the server release pipeline: any upload whose
         numeric is not exactly 13 digits, has an invalid YYYYMMDD date prefix, or is not
         strictly greater than the currently promoted numeric is now hard-rejected with a
         clear error naming the component and both numerics. Added /api/plugins/version-audit
         endpoint. Also repaired local_lmshomepage source numeric (was 10-digit 2026080304;
         corrected to 13-digit 2026080800210 so future rebuilds cannot downgrade below
         production). No DB schema changes. Savepoint 2026080800205.
v2.4.46: FIX-BRANDING (7 Aug 2026) — Reworded "Could not reach the AI Grader update server"
         to "LMS Labs update server". No DB changes. Savepoint 2026080746 [10-digit — fixed in v2.4.47].
v2.4.30: UPDATE-SYSTEM-HARDENING (1 Aug 2026) — 6 security and reliability fixes to the
         built-in check-for-updates and auto-update system:
         (1) URL allowlist in plugin_updater.php: downloadUrl must come from lms-labs.com
             or ai-grader-site-nct185.replit.app — prevents SSRF / supply-chain attacks.
         (2) Component verification: after installing a ZIP, $plugin->component inside
             version.php must match the requested component — prevents installing plugin_a
             files under plugin_b's directory.
         (3) Exception temp-file cleanup: catch blocks in both auto_update_plugin and
             auto_install_plugin now delete $zipfile and $extractdir on exception.
         (4) JSON.parse guard: installed plugin data is now parsed with try/catch so a
             corrupt DOM data attribute cannot throw and break the entire version check.
         (5) downloadUrl validation: undefined/empty downloadUrl now skipped silently
             instead of being queued and sent to the PHP updater as an empty string.
         (6) DOM XSS fix in "Update All" failure modal: plugin names and error reasons
             from the API response are now HTML-escaped before insertion into the modal.
         No DB schema changes. Savepoint 2026080100133.
v2.4.29: PIPELINE-CROSS-REGISTRY (1 Aug 2026) — future-proof guarantee: the LMS-Labs
         Plugin Release Pipeline now fails with an error if any plugin in PLUGIN_ZIP_CONFIG
         is absent from get_complete_plugin_registry() when a Quicklinks ZIP is uploaded.
         Root cause of "Beacon showing old version in Quicklinks": the installed Beacon on
         the Moodle site was old; Quicklinks correctly read it from the filesystem. Going
         forward the pipeline enforces that every server plugin is registered before any
         Quicklinks release can ship. No PHP/DB/AMD changes. Savepoint 2026080100132.
v2.4.28: FIX-TINY-PATH (31 Jul 2026): tiny_* plugins (e.g. tiny_aipagetemplates) now show
         their correct installed version in the Plugin Manager column.
v2.4.20: ADD-BEACON — local_beacon (Beacon — Reports & Analytics) added to master plugin
         registry (settings_url → /admin/settings.php?section=local_beacon, page_url →
         /local/beacon/index.php, icon=bar-chart-2, category=utility) and complete plugin
         registry (category=utility, description, access, goto_url). Docs URL map updated.
         No DB schema changes. Savepoint 2026072602122.
v2.4.19: SAVEPOINT-BUMP — version increment so Moodle detects update and serves latest
         block_aiplugin_nav zip (includes tiny_aipagetemplates / AI Page Templates in
         complete plugin registry and quick-access links). No code changes. Savepoint 2026072602121.
v2.3.70: ADD-AIPAGETEMPLATES — tiny_aipagetemplates (AI Page Templates) added to master plugin
         registry (settings_url → /admin/settings.php?section=tiny_aipagetemplates, icon=layout,
         category=ai_credit) and complete plugin registry (category=ai, description, access path,
         goto_url). No DB schema changes. Savepoint 2026072600119.
v2.3.37: FEATURE — Credit gate for Time Saving Plugins in Plugin Manager. The 9 credit-gated
         plugins (Groups Management, Groups Availability Condition, Course Version Control,
         Change Site Font, Cohort Branding, Assignment Benchmarks, Simple 2FA, Group Membership
         Limit, Payment Unlock Assignment, Essay Guard) now show a confirmation popup before
         deducting credits when the install button is clicked. New block_aiplugin_nav_plugin_unlock
         external function calls POST /api/plugin-unlock server-side so credentials are never
         exposed to the browser. Handles already-unlocked state gracefully (no double-charge).
         Credits cache is invalidated immediately after a successful unlock.
v2.3.36: FIX — BigBlueButton and Recordings removed from Plugin Manager (still in testing).
v2.3.31: FIX — AI Grader Central Config (local_aiconfig) not appearing in block after manual install.
         Three bugs fixed: (1) Plugin Manager grid had a 5-minute stale cache with no invalidation
         mechanism — now busts immediately when Moodle's plugin count changes (e.g. after any manual
         install via admin UI); (2) "Install First" badge in Settings dropdown was always shown even
         when local_aiconfig was already installed — now shows green "Foundation" badge for installed,
         amber "Install First" for not-yet-installed; (3) local_aiconfig was visually buried as just
         another card in a column of 15+ AI plugins — now renders at the top with a dedicated
         "Foundation Plugin — Install First" header in green so admins find it instantly.
v2.3.30: ADD — format_aicourse added to master plugin registry.
v2.3.29: BUILD FIX — Synced amd/build/credits.js and amd/build/credits.min.js to be identical to
         amd/src/credits.js (all three CRC hashes now match). Previously the build files were written
         separately and did not match src, meaning Moodle was serving stale AMD code. No logic changes.
v2.3.28: PERF — Credits balance now loaded asynchronously via AMD AJAX instead of blocking page render.
         Removes two synchronous HTTP calls from get_content() (debug_credits_check 10s timeout +
         get_credits_balance 5s timeout = up to 15s page delay for admins). Credits placeholder renders
         instantly and populates after page load via block_aiplugin_nav_get_credits external function.
         Server-side 5-minute cache added to get_credits external function. No DB schema changes.
v2.3.27: FIX — AI Quiz Maker renamed throughout: plugin registry name, plugin grid name, description, access path, and all three lang strings (ai_essay_maker, ai_essay_maker_access, ai_essay_maker_settings) updated from "AI Essay Maker" to "AI Quiz Maker". Plugin was renamed in v3.16.2 of the essaymaker plugin but the block was never updated. No DB schema changes.
v2.3.26: VERSION BUMP — Routine release packaging following v2.3.25 attendance report fixes.
v2.3.25: FIX Attendance Report — 9 bugs fixed: (1) course filter now applies to all four summary stat
         cards (previously only affected tables); (2) rate calculation changed from COUNT(grade>0)/total
         to AVG(grade)*100 to match Moodle's own attendance percentage calculation and correctly handle
         Late/Excused/Medical statuses; (3) N+1 DB queries eliminated — all course modules pre-loaded
         in one query instead of per-row get_coursemodule_from_instance() calls; (4) LIMIT 20 raw SQL
         replaced with Moodle-standard get_records_sql() limitnum param for cross-DB compatibility;
         (5) date() replaced with userdate() for correct Australian timezone display; (6) per-student
         at-risk table added showing all students below 80% threshold with links and email; (7) CSV
         export added (activities, at-risk students, recent sessions); (8) capability changed from
         moodle/site:config to moodle/site:viewreports so academic coordinators and managers can access;
         (9) "Students Tracked" card relabelled "Students Logged" with explanatory sub-note;
         grade clamped to 100% max to guard against older plugin 0-100 grade scale.
v2.3.24: Renamed AI Learning and Assessment Mapping to AI Mapping across all registries and lang strings.
v2.3.23: Added Course Prerequisite gates page to Reports section in QuickLinks block.
v2.3.22: Renamed to AI Learning and Assessment Mapping. Added AI Course Information plugin to all registries.
v2.3.21: FIX Learning & Assessment Mapping moved back to AI Plugins (category ai_credit/ai) — it is an AI-powered plugin using 100 credits per analysis
v2.3.20: FIX Learning & Assessment Mapping moved from AI Plugins to Time Saving Plugins (master: admin, complete: utility)
v2.3.19: BUMP version for clean release with learning mapping quick access links and lang strings
v2.3.18: ADD Learning & Assessment Mapping to plugin registry, table icon SVG, docs URL map
v2.3.17: FIX settings URLs — quiz_aigrader_settings → quiz_aigrader, modsettingknowledgecheck → modsettingaiknowledgecheck
v2.3.16: ADD Attendance Report — new site-wide Moodle Attendance summary report page added to Reports dropdown; auto-detected when mod_attendance is installed; shows stat cards (activities/sessions/students/overall rate), course filter, date range filter, per-activity breakdown table with attendance rates, and recent sessions table with deep-links into each activity's native report
v2.3.15: UPDATE AI Course Format report link in Reports dropdown now points to site-wide admin Q&A report
v2.3.12: FIX Essay Guard entry corrected to plagiarism_essayguard type/component/URLs (was local_essayguard)
v2.3.11: ADD Essay Guard to settings registry and plugin grid (Time Saving Plugins section)
v2.3.10: ADD Payment Unlock Assignment to plugin grid (Time Saving Plugins section)
v2.3.9: ADD Payment Unlock Assignment to settings registry
v2.3.8: FIX Auto Update All only includes installed plugins - uninstalled plugins are skipped (prevents 11 failed updates)
v2.3.7: FIX version comparison uses Moodle numeric versions (13-digit) to prevent downgrades - installed > server means no update needed
v2.3.6: FIX version comparison - use exact string match instead of semantic comparison which broke when version numbering schemes changed (e.g. 3.58.0 -> 3.6.2)
v2.3.5: Added AI PDF Assignment Grader to plugin registry, assignfeedback type mapping in get_plugin_version()
v2.3.4: Fixed Paddle settings_url to use Moodle standard section name (paymentgatewaypaddle)
v2.3.3: Fixed Paddle settings_url to use registered admin_settingpage section name (paygw_paddle_settings)
v2.3.2: Ensured paygw type mapping in get_plugin_version() for correct Paddle version display
v2.3.1: Fixed Paddle settings_url to use proper admin settings section (was /payment/accounts.php, now /admin/settings.php?section=paygw_paddle)
v2.3.0: Added Paddle Payment Gateway and AI Quiz Remedial Learning to Plugin Manager grid
v2.2.99: Added AI Video Activity to plugin registry, download list, quick access links, icon map, and docs URLs
v2.2.98: Credits total displayed prominently in the top navigation bar with colour-coded badge (green/orange/red)
v2.2.96: Added AI Slideshow with Voiceover to quick access links list (was missing from display array)
v2.2.95: Added AI Slideshow with Voiceover to plugin registry
v2.2.94: Added missing layers icon for AI Learning Activities in icon map
v2.2.93: Added AI Learning Activities to plugin registry, download list, quick access links, and docs URLs

- ADD-BEACON — local_beacon added to plugin registry.
- SAVEPOINT-BUMP — serve latest zip with tiny_aipagetemplates in registry.
- FIX-ENDPOINT-ORDER (v2.4.16): PHP proxy (check_versions.php) moved to position #1 in ainav_endpoints JS array so the browser never waits 10s for a blocked lms-labs.com direct call before trying the proxy. check_versions.php internal endpoint list reordered to Replit URL first (always reachable) then lms-labs.com; essaygraderai.app (legacy dead domain) removed from both lists; per-endpoint cURL timeout reduced from 10s to 5s. Total worst-case version-check wait drops from ~40s to ~10s for Vultr/datacenter-IP Moodle servers. No DB schema changes. Savepoint 2026072400116.
- FIX-DOMAIN + FIX-LEGACY-ESSAYMAKER (v2.4.7): (1) All remaining lms-labs.com URLs replaced with lms-labs.com throughout block_aiplugin_nav.php — affects Visit Website, Pricing, affiliate, and all /docs/* links. (2) local_essaymaker added to master plugin registry so sites with the old legacy plugin installed (pre-rename) will see "AI Quiz Maker (Legacy — update to fix)" with update available, allowing auto-update to v3.16.89 which carries correct namespace local_essaymaker in its class files, resolving the fatal "Cannot declare class local_aiquizmaker\hook\before_footer_html_generation because the name is already in use" PHP crash. No DB schema changes. Savepoint 2026072300107.
- REBRAND-LMS-LABS (v2.4.2): Rebranded all references from lms-labs.com to lms-labs.com. Visit Website button now links to https://lms-labs.com. All docs URLs, API endpoints, and lang strings updated to lms-labs.com domain. No DB schema changes.
- ADD-TRAININGPLAN (v2.3.98): Added block_trainingplan (Training Plan) to Time Saving Plugins utility grid (category=utility, credits_required=5000, icon=calendar-clock, goto_url=/admin/settings.php?section=block_trainingplan). Added calendar-clock SVG to get_icon_svg() icon map. Added block_trainingplan to get_plugin_docs_url() pointing to /docs/training-plan. No DB schema changes.
- ADD-SMARTWORKBOOK-COMPLETE-REGISTRY (v2.3.97): Added mod_smartworkbook (AI Smart Workbook) to get_complete_plugin_registry(). Plugin was present in get_master_plugin_registry() and get_ai_tools_registry() but absent from get_complete_plugin_registry(), so it never appeared in the admin Plugin Manager panel and could not be downloaded from the QuickLinks block. No DB schema changes.
- ADD-SMARTWORKBOOK (v2.3.95): Added mod_smartworkbook (AI Smart Workbook) to master plugin registry, complete plugin grid, and docs URL map — appears in AI Plugins section. No DB schema changes. Savepoint 2026070900095.
- ADD-AILOGIN (v2.3.92): Added local_ailogin (AI Login Designer) to master plugin registry (Settings + Manage dropdowns), complete plugin grid, and docs URL map. No DB schema changes. Savepoint 2026070800092.
- FIX-QUIZACCESS-DOCS-URL (v2.3.91): quizaccess_aigrader docs link in get_plugin_docs_url() was pointing to /docs/ai-grader (the main AI Essay Grader page) instead of /docs/quiz-access-rule. No DB changes. Savepoint 2026070300091.
- FIX-GET-CREDITS-GLOBALS (v2.3.90): classes/external/get_credits.php was missing global $DB, $USER declarations — execute() only declared global $CFG. When a non-siteadmin (e.g. lmshsadmin) called the web service, $DB->record_exists_sql() on a null variable threw a PHP exception, causing the AJAX call to fail silently and the credits badge to stay blank. Fix: added global $DB, $USER to execute(). Also expanded role-check to cover moodle/site:configview capability (catches custom admin roles) and added lmshostingadmin and manager shortnames to the hardcoded list. No DB schema changes. Savepoint 2026070300090.
- SHOW-CREDITS-LMSHSADMIN (v2.3.89): Credit balance badge now also visible to lmshsadmin role (LMS Hosting Admin). Both block_aiplugin_nav.php (badge placeholder) and classes/external/get_credits.php (web service role check) updated. No DB changes. Savepoint 2026063000089.
- SHOW-CREDITS-TEACHERS (v2.3.88): Credit balance badge now visible to editing teachers and non-editing teachers, not just site admins. Both block_aiplugin_nav.php (badge placeholder + AMD loader) and classes/external/get_credits.php (web service gate) updated. No DB changes. Savepoint 2026063000088.
- ADD-UPDATE-POPUP (v2.3.83): Added full-screen plugin update popup to QuickLinks block. Clicking "Check for Updates" now shows a modal popup matching the lms-labs.com admin panel: orange gradient header listing each outdated plugin with installed vs latest version (old→new), or green gradient "All Plugins Up to Date" when everything is current. Popup has X close button, backdrop-click dismiss, "Check for Updates" re-run button, and "Got It"/"Perfect, Thanks!" confirm button. No DB changes. Savepoint 2026062600084.
- FIX-PHP-PARSE (v2.3.82): Escaped three unescaped double-quote characters inside the js_amd_inline PHP double-quoted string that caused a PHP ParseError ("unexpected identifier up") on Moodle upgrade. Affected lines were JS comments containing "up to date" — bare " closed the PHP string prematurely. No DB changes. Savepoint 2026062200082.
- FIX-UPTODATE-TOAST (v2.3.81): Added in-block "All plugins are up to date" toast. Toast renders inside .ainav-container on the Moodle dashboard so the message appears within the block — not on lms-labs.com. Accent colour uses var(--primary) inherited from the Moodle theme primary colour rather than a hardcoded value. Auto-dismisses after 5 s; manual close button included. No DB changes. Savepoint 2026062000081.
- RENAME-AI-SLIDE-FLOW (v2.3.80): Renamed mod_productexplainer from 'AI Product Explainer' to 'AI Slide Flow' in master plugin registry, complete plugin grid, quick access links, and lang strings. No DB changes. Savepoint 2026061300080.
- FIX-WORKSHOPS-CALENDAR-ICON (v2.3.79): Added missing 'calendar' key to get_icon_svg() lookup table. Workshop Scheduler quick-link rows were showing a blank icon because the registry uses icon='calendar' but only 'calendar-icon' existed in the SVG map. No DB schema changes. Savepoint 2026061200079.
- REMOVE-DUPE-CREDITS (v2.3.78): Removed standalone credits badge from header bar — credit count now shown only inside the Buy Credits button. Fixed ainav-bar/ainav-sections/ainav-external flex-wrap: nowrap so the header always renders on a single line. Tightened gap (16px→12px) and padding (12px 20px→10px 16px). No DB changes. Savepoint 2026061200078.
- ADD-WORKSHOPS-PRODUCTEXPLAINER (v2.3.76): Added Workshop Scheduler (local_workshops) and AI Product Explainer (mod_productexplainer) to master plugin registry, complete plugin grid, quick access links, docs URL table, and lang strings. No DB changes.
- RENAME-CV-SCORM (v2.3.75): Updated plugin display name for local_chirpvoice from 'AI Voiceover (Chirp HD)' to 'AI SCORM Voiceover' in both registry entries (master plugin registry and complete plugin grid). No DB changes.
- FIX-CV-DOCS-LINK (v2.3.74):
- FIX-CV-DOCS-LINK (v2.3.74): Added local_chirpvoice to get_plugin_docs_url() lookup table. AI Voiceover (Chirp HD) card in the Plugin Manager was missing the Docs button because local_chirpvoice was not in the docs_urls array. URL: https://lms-labs.com/docs/ai-voiceover. No DB changes.
- FIX-CV-COMPLETE-REGISTRY (v2.3.73): Added local_chirpvoice (AI Voiceover Chirp HD) to get_complete_plugin_registry() so it appears in the AI Plugins grid regardless of installation status. Was previously only in get_master_plugin_registry() (settings/nav links) but missing from the plugin management grid. No DB changes.
- ADD-CV-QUICKLINKS (v2.3.72): Added local_chirpvoice (AI Voiceover Chirp HD) to master plugin registry. Appears in AI Plugins grid and Settings dropdown when installed. No DB changes;
- SAVEPOINT-BUMP v2.3.71: no-op savepoint marker for clean upgrade path. No DB schema changes.;
- FIX-MAP-ICON (v2.3.70): Added missing 'map' SVG to get_icon_svg() icon table. Training Pathways quick-link row was showing a blank icon space because 'map' was not in the lookup array (only 'map-pin' was). No DB schema changes. Savepoint 2026060200070.
- ADD-TRAININGPATHWAYS (v2.3.69): Added Training Pathways (local_trainingpathways) to the plugin registry (settings cog → /admin/settings.php?section=local_trainingpathways, page → /local/trainingpathways/manage.php, icon=map), the Time Saving Plugins marketplace grid (category=utility, goto_url=/local/trainingpathways/manage.php), and the docs URL map. No DB schema changes. Savepoint 2026060200069.
- ADD-DOWNALERT (v2.3.67): Added local_downalert (Site Down Alert) to the plugin registry under Time Saving Plugins (category: admin) with settings_url and report_url pointing to the new report.php health dashboard. Report now appears in the Reports dropdown; settings appears in the Settings dropdown. Free — no AI credits required. No DB schema changes. Savepoint 2026052900067. (v2.3.66): Removed settings_url from paygw_paddle registry entry. Paddle has no single admin settings page — API keys are at Site admin > Plugins > Payment gateways > Manage payment gateways; payment prices are per-course. The broken link to /admin/settings.php?section=paymentgatewaypaddle no longer appears in the quicklinks Settings dropdown. No DB changes. Savepoint 2026052900066.
- RTOC-CERT-SETTINGS-QUICKLINK (v2.3.65): Added "RTO Compliance — Certificate Settings" to the Settings dropdown in the AI Quick Links block, pointing to /admin/settings.php?section=local_rtocompliance_certs. No DB changes. Savepoint 2026052900065.
- REGISTRY-AUDIT (v2.3.64): Added missing block_rtocompliance (RTO Compliance Dashboard block) to plugin registry — was in server PLUGIN_ZIP_CONFIG but absent from Quick Links registry, so it never appeared in the dashboard and never triggered update notifications. No DB changes. Savepoint 2026052800064.
- FIX-RTOCOMPLIANCE-SETTINGS-URL (v2.3.63): RTO Compliance settings icon in Quick Links was linking to section=local_rtocompliance_dashboard (an admin category, not a page) causing a "section not found" error. Fixed to section=local_rtocompliance_settings which is the correct admin_settingpage. No DB changes. Savepoint 2026052800063.
- FIX-CURL-NOT-FOUND: plugin_unlock.php and get_credits.php both call new \curl() but never loaded filelib.php where Moodle's curl class is defined. This caused "Credit unlock failed: Exception - Class curl not found" when clicking the unlock button for any credit-gated plugin (e.g. Simple 2FA). Fix: added require_once($CFG->libdir.'/filelib.php') immediately before new \curl() in both plugin_unlock.php and get_credits.php. Also includes v2.3.61 credits-display fix (removed capabilities=>moodle/site:config from services.php + soft is_siteadmin() check in execute()). No DB schema changes. Savepoint 2026052500062.
