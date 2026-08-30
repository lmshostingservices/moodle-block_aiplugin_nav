# AI Dashboard Quick Links — Navigation & Operations Review

**Scope:** Moodle block navigation for LMS Labs plugins, Moodle 4.0–5.1.  
**Review type:** Product, interaction, accessibility, and scalability review. No implementation changes proposed in this document.

## Executive recommendation

Do **not** continue to scale the current three-dropdown model as the primary navigation system. Keep the block as a compact, context-independent **operations entry point**, but make a searchable **Action Launcher** its primary control and move discovery, plugin administration, update review, and custom links into a dedicated **Management Hub** page.

### Is a mega menu recommended?

**No—not as the primary solution.** A bounded mega menu can be an acceptable *transitional* treatment for a short curated set, but it does not solve the underlying information-retrieval problem:

- Settings and Manage already exceed practical scan length at 1,785px and 1,491px of content.
- More columns reduce vertical scrolling but turn a known-item task into a visual search task, increase eye travel, and fail sharply at narrow widths.
- A 443px scroll container prevents inaccessible links, but it creates nested scrolling, hides inventory, and gives keyboard and screen-reader users little orientation.
- Installed-plugin growth is unbounded; redesigning columns and heights on each growth step is not a strategy.

The durable answer is search, task-oriented ranking, explicit management surfaces, and progressive disclosure. The dashboard should expose high-confidence, high-frequency work; the hub should expose the complete catalogue.

---

## 1. User and task model

### Primary users

| User | Context | Dominant need | Tolerance for navigation friction |
|---|---|---|---|
| System administrator | Configuration, upgrades, incident response | Reach a precise installed-plugin action safely | Very low |
| LMS manager | Reporting, course operations, compliance | Understand what needs attention and open a workflow | Low |
| Support administrator | Investigating an issue while working in another Moodle area | Jump to a plugin, report, documentation, or support | Very low |
| Platform owner | Catalogue and release governance | Inspect installed estate, compatibility, release status | Moderate, provided information is trustworthy |
| Keyboard/screen-reader administrator | Same tasks through non-pointer interaction | Predictable order, announced state, no trap or hidden context | Zero tolerance for ambiguous controls |

### Core jobs-to-be-done

1. **Known destination:** “Open Course Recertification settings.”  
   The correct answer should be reachable by launcher search in one query and one activation.
2. **Exception triage:** “Which LMS Labs plugins need review or an upgrade?”  
   The answer must be a visible status count and a review queue, never an automatic installation.
3. **Discover installed capability:** “What can this site do for AVETMISS / compliance / media?”  
   The answer is a browsable catalogue organized by plugin/category, with actions shown in context.
4. **Repeat work:** “Return to the report I used yesterday.”  
   The answer is a Recent list and optional personal pins.
5. **Safe administrative change:** “Install or stage an available update.”  
   The answer is an explicit multi-step review with version, compatibility, release notes, impact, and confirmation.
6. **Site-specific navigation:** “Open our funding configuration or internal process guide.”  
   The answer is a clearly labeled Custom links area with governance and safe edit/delete controls.

### Retrieval principle

Organize the system by **three simultaneous retrieval paths**, not one taxonomy:

- **Search and recency** for known and repeat destinations.
- **Task / action type** for operational work (“Review updates”, “Open reports”, “Configure”).
- **Plugin/category** for exploration and understanding the installed estate.

No single grouping is sufficient. Action type alone separates related actions from their plugin; plugin alone forces users to infer which action exists; category alone is weak for urgent operations.

---

## 2. Severity-ranked findings

| Severity | Finding | Evidence / risk | Required direction |
|---|---|---|---|
| Critical | The three expanding dropdowns do not scale as the installed catalogue grows. | Reports already has 10+ entries; Settings and Manage require 1,785px and 1,491px; scroll limits merely mask scale. Valid actions have already been below the viewport. | Establish Action Launcher + Management Hub as the scalable IA. |
| Critical | Update controls can create an unsafe interpretation of a destructive workflow. | Current single and bulk controls begin download/update then redirect to Moodle upgrade; “Auto-update all” encourages broad action with inadequate review. | Separate **check**, **review**, **stage/download**, and **run Moodle upgrade**. Require clear, deliberate confirmation. Never imply installation is complete until Moodle confirms it. |
| High | Nested scroll menus create poor keyboard, pointer, touch, and screen-reader experiences. | 443px max-height preserves access but adds a small scrolling region inside a page/block; columns and menu position complicate orientation. | Avoid long scrollable menus for primary retrieval. Use a modal/drawer launcher and a full hub page. |
| High | The current IA mixes dissimilar intents in shallow labels. | “Settings,” “Manage,” “Reports,” AI tools, updates, support, credits, and custom links compete in one bar. “Manage” especially has broad semantics. | Rename based on user intent and make system status explicit: Launcher, Reports, Updates, Help, Manage plugins. |
| High | The block overclaims a global horizontal layout in theme-controlled Moodle regions. | A full-width bar can appear in columns, narrow course pages, dashboard side regions, and theme variants. | Use a responsive compact control surface that may wrap intentionally; never depend on page width or block position. |
| High | Plugin Manager is overloaded when embedded as a modal in a quick-link block. | Catalogue browsing, installed detection, updates, configuration, install capability, and potentially dozens of cards require durable URL, deep linking, and space. | Create a dedicated Management Hub. Retain a block entry point and concise summary only. |
| High | Long lists lack robust orientation and result accountability. | A user cannot quickly answer which plugin/action they are viewing, why an item is available, or how many results exist. | Add result counts, persistent query, category/action filters, plugin metadata, installed/version/status labels, and empty-state guidance. |
| Medium | Current custom-link deletion relies on hover visibility. | The delete affordance is unavailable to touch users and may be undiscoverable or unreachable by keyboard. | Provide an explicit “Manage custom links” view; each item has labelled Edit/Delete actions and confirmation. |
| Medium | Status colors and labels need stronger semantic treatment. | Credits and updates use status color classes; relying on red/orange/green alone is insufficient. | Pair icon, text, and accessible status; meet contrast in host themes and high-contrast modes. |
| Medium | External credit/changelog retrieval introduces latency and ambiguous degraded states. | Credit API has fallback endpoints/timeouts; changelog is fetched directly and errors currently surface generically. | Show delayed/failed state as “Balance unavailable—retry”; never block navigation; disclose external link behavior and preserve a retry route. |
| Medium | “What’s changed?” is appended dynamically beside triggers. | Mutation-driven injection and tiny inline links can produce inconsistent placement, focus order, and context. | Render release notes as an intentional secondary action in the update-review row. |
| Medium | Label length and duplication will become more visible across plugin types. | Examples include legacy plugins, broad “AI” prefixes, central config, dashboard blocks, reports/settings/pages. | Normalize action grammar: **Plugin name** / **Action**; preserve legacy warnings separately from display name. |
| Medium | Native theme and version variability has not been treated as a first-class constraint. | Moodle 4.0–5.1 themes vary in colors, font sizing, regions, focus styles, z-index, and Bootstrap implementation details. | Adopt scoped, token-based styling; rely on Moodle core accessibility patterns and test core themes plus representative third-party themes. |
| Low | The block needs a defined non-admin and no-plugin posture. | Installed detection means catalogues may be empty or capability-limited. | Hide unavailable administrative controls; show a concise, permission-aware empty state with no dead affordances. |

---

## 3. Distinct design hypotheses

### Hypothesis A — Bounded mega menu

**Premise:** Keep Reports, Settings, and Manage as independent triggers; replace their dropdowns with a responsive mega menu that adds category headings, in-menu search, and a fixed footer link to “View all plugins.”

**What it improves**

- Lowest migration cost.
- Preserves existing mental model and destinations.
- Can make the first 8–12 prioritized actions easier to scan.
- Allows immediate remediation of the invisible-links problem.

**What it cannot solve**

- Still makes the dashboard block carry the full catalogue.
- Still requires internal scrolling or a very large overlay.
- Still treats cross-plugin known-item lookup as browsing.
- Remains difficult in a block sidebar and on mobile.
- More columns worsen reading order for assistive technologies unless the DOM and visual order are carefully reconciled.

**Best use:** A short-lived compatibility layer during migration.  
**Verdict:** Not recommended as target architecture.

### Hypothesis B — Unified searchable Action Launcher

**Premise:** Replace the three primary dropdowns with one launcher. It opens as a modal on desktop and an edge-to-edge dialog on mobile. Search is focused on open. Results combine actions across installed plugins and are ranked by exact match, pinned, recent, status urgency, and then alphabetical relevance.

**What it improves**

- Fastest path for the dominant known-destination task.
- Flat retrieval model scales with catalogue size.
- Supports keyboard-first operation, aliases, and action-level destinations.
- Makes each result explicit: `Course Recertification — Settings`, not a vague list item.
- Gives users a single dependable answer without forcing every action into the initial view.

**Risks**

- Search-only is poor for first-time discovery.
- Ranking must be explainable; urgent alerts must not overwhelm normal navigation.
- Requires a small indexed data model rather than only rendered link groups.

**Best use:** Primary control surface, paired with curated browse sections and a hub.  
**Verdict:** Recommended as the primary pattern.

### Hypothesis C — Dedicated Management Hub + compact dashboard block

**Premise:** The block becomes a compact operational strip: Launcher, summary status, recent/pinned actions, and direct routes to Reports / Updates / Manage plugins. A full-page Management Hub holds the installed catalogue, plugin detail, configuration/action routes, version status, custom-link administration, support, and credits.

**What it improves**

- Gives complex management workflows a stable, linkable, spacious home.
- Supports richer auditing: version, status, compatibility, documentation, release notes, and per-plugin actions.
- Eliminates modal-as-mini-application behavior.
- Keeps the dashboard fast and comprehensible in every Moodle region.

**Risks**

- More navigation depth for users who only browse.
- Requires the hub to be reliable and permission-aware.
- Needs careful rollout so existing administrators do not feel destinations disappeared.

**Best use:** The durable home for operations and inventory.  
**Verdict:** Recommended, in combination with Hypothesis B.

### Hypothesis D — Task console with alerts and personal workspace

**Premise:** The dashboard block is primarily a personalized queue: update exceptions, low credits, recently used actions, pins, and current administrative tasks. Plugin browsing is secondary through search/hub.

**What it improves**

- Excellent for repeat administrators and exception handling.
- Reduces cognitive load for daily use.
- Gives updates and configuration risk a coherent operational home.

**Risks**

- Needs trustworthy data and user-level storage.
- Can hide long-tail capability if it replaces browse/search.
- “Personalized” ranking must not override an urgent platform-wide safety alert.

**Best use:** Enhancement after the launcher and hub have established predictable baseline retrieval.  
**Verdict:** Add progressively, not first.

### Pattern comparison matrix

| Criterion | A. Bounded mega menu | B. Action Launcher | C. Management Hub | D. Task console |
|---|---:|---:|---:|---:|
| Known action in seconds | Moderate | Excellent | Moderate | Excellent for repeats |
| Catalogue growth | Poor | Excellent | Excellent | Good |
| First-time discovery | Good | Moderate | Excellent | Poor–Moderate |
| Narrow/mobile behavior | Poor | Excellent | Good | Good |
| Keyboard / SR control | Moderate | Excellent | Excellent | Good |
| Safe update workflow | Poor | Moderate | Excellent | Excellent |
| Migration cost | Low | Medium | Medium–High | Medium |
| Suitable as final architecture | No | Yes | Yes | Complement only |

---

## 4. Recommended target architecture

### 4.1 The dashboard block: compact Operations Bar

The block is a **launch pad, not a catalogue**. It must fit a full-width dashboard and a narrow block region.

1. **Brand / identity:** “LMS Labs Operations” with concise installed-plugin count. The brand is not a button unless it leads to the Management Hub.
2. **Primary:** `Find an action` opens the Action Launcher. Include a visible shortcut hint only where host theme conventions permit it.
3. **Status cluster:** compact, text-backed indicators:
   - `3 updates to review`
   - `Balance unavailable` or `1,240 credits`
   - Avoid a number-only badge that requires color interpretation.
4. **Curated direct routes:** Reports, Updates, Manage plugins. These are normal links to durable hub views, not long menus. A fourth overflow control is acceptable for low-frequency Help and Custom links.
5. **Personal layer:** show up to 3 Recent/Pinned actions only, with an explicit “All actions” route. Do not use personal history as the only route.
6. **Contextual AI tools:** surface only when relevant to the current page/context and when permission allows. Otherwise discoverable through launcher/hub.

### 4.2 Action Launcher: action-first, installed-only

**Opening behavior:** trigger button, configurable shortcut where Moodle does not already claim it, and a standard accessible dialog. Initial focus lands in Search.

**Search index fields:** display name, plugin component, aliases/legacy names, action (`Settings`, `Report`, `Open tool`, `Manage`), category, and destination availability. Search must include “recertification,” “completion suspend,” “RTO,” “AVETMISS,” and legacy plugin names.

**Initial view, without typing**

- **Pinned** (if present)
- **Recent**
- **Needs attention** (only non-destructive routes: `Review 3 updates`, `Configure Central Config`)
- **Browse by task:** Configure; Reports & exports; Course operations; Content & AI; Security & compliance; Help & support.

**Result row**

- Primary: action label, e.g. `Open report`
- Secondary: plugin name, e.g. `Course Recertification`
- Tertiary metadata: installed/version/status only when useful
- Optional pin control with explicit accessible label
- Never use icon-only destination rows.

**Progressive disclosure:** show top results and a result count; provide filters for `Action`, `Category`, and `Status`. Filtering is an enhancement, not required to reach known items.

### 4.3 Management Hub: durable management destination

The hub is a page with URL state for view, search, filter, and plugin selection.

| Hub area | Purpose |
|---|---|
| Overview | Installed count, update-review count, configuration prerequisites, credits state, recent administrative activity |
| Plugins | Searchable installed catalogue, category filters, status and version labels |
| Plugin detail | Plugin overview, actions, settings/report/page links, installed version, update state, documentation/support, legacy warning when applicable |
| Reports | Action-first report directory with plugin/category filtering |
| Updates | Review queue; changelog, compatibility, checksum/source, explicit staged-update selection |
| Custom links | Create, edit, ordering, visibility scope, destination validation, delete confirmation |
| Help & account | Support destination, diagnostic information suitable for copying, credit balance and account route |

### 4.4 Content hierarchy rules

- **Plugin is the stable object; action is the verb.** In detailed listings, group actions under a plugin. In the launcher, group results by relevance.
- **Use category for browse, not primary retrieval.** Categories should be understandable operational domains, not internal registry labels.
- **Keep Reports as a task view**, even though it is no longer a standalone dropdown. A direct Reports link preserves the user’s intent without preserving the failing pattern.
- **Never mix install/update actions with ordinary navigation.** They live in Updates and Plugin detail, with safety treatment.

---

## 5. Desktop and narrow/mobile interaction model

### Desktop (wide content area)

- Operations Bar uses one row where space allows; wrap only the status/secondary cluster, never truncate actionable labels.
- Launcher is centered with a constrained width and maximum viewport height. Search and sticky filters remain above the scrollable result list; close control is explicit.
- Results are one-column. Do not restore multi-column menu reading merely to use horizontal space.
- Hub uses a persistent filter rail only above an agreed content-width threshold; filters collapse into a dialog/drawer below it.
- Tooltips are supplemental only; no meaning depends on hover.

### Narrow content area and mobile

“Mobile” includes a desktop browser with the block in a narrow Moodle region; measure container width, not only viewport width.

- Operations Bar becomes stacked: brand/status, `Find an action` full-width, then a compact row/list of Reports, Updates, Manage plugins.
- The launcher becomes a full-screen dialog or bottom sheet with a minimum 44×44 CSS-pixel target size, safe-area padding, persistent search, and a clear close action.
- Do not center dropdowns with transforms that can place content offscreen. Do not use hover-dependent controls.
- Hub filters open in a dedicated dialog. Filter state is retained while navigating back from a plugin detail.
- Long label wrapping is permitted; ellipsis is only acceptable where the full accessible name remains available and the lost text is nonessential.
- Update confirmation uses a full page/dialog with its action buttons fixed in a safe footer, not a small overlay.

---

## 6. Accessibility and inclusive design requirements

### Semantics and focus

1. Use native links for navigation and native buttons for dialogs, menus, filters, pinning, and update actions.
2. Launcher uses `role="dialog"`, `aria-modal="true"`, labelled title, and described result count. Move focus into it when opened; trap focus only while modal; restore focus to the trigger on close.
3. Escape closes launcher/dialogs without losing the query when reopened in the same session.
4. Search result navigation supports Up/Down, Enter, Home/End, and type input without hijacking browser-standard shortcuts. Tab continues through all interactive controls predictably.
5. Do not implement a composite ARIA menu for a catalogue of normal links. A dialog containing a search field and regular list of links/buttons is more robust.
6. Avoid dynamically injecting critical controls after focusable elements. When asynchronous status changes, announce concise updates through a polite live region.

### Labels, states, and contrast

1. Every icon-only control requires an accessible name; visible text remains preferred for high-consequence controls.
2. Status is text + icon + color; never color alone. Provide labels such as `Update available`, `Update check unavailable`, `Legacy plugin—review required`.
3. Meet WCAG 2.2 AA contrast for text, focus rings, controls, error/status states, and custom color overrides. Test in Moodle theme dark/high-contrast variants rather than relying only on `prefers-color-scheme`.
4. Focus indicators must remain visible against any host theme and not rely solely on box shadow that may be suppressed.
5. Respect reduced motion. Transitions may clarify open/close state but cannot be the only state signal.
6. Use logical DOM order. CSS columns must not produce a visual sequence that differs from keyboard/screen-reader sequence.

### Content and error states

1. Loading lists announce `Loading installed plugins` and reserve layout space; do not display an empty catalogue while detection is pending.
2. Failed checks preserve last known state only when timestamped and labelled stale; otherwise show `Couldn’t check updates` with Retry.
3. Empty search states name the query, offer clear search, and offer browsable categories—no dead end.
4. Permission-limited users see only authorized destinations and a clear explanation when an otherwise known feature is unavailable.
5. External support, changelog, and credit destinations disclose when they open a new site/window.

---

## 7. Specific component recommendations

### Header/navigation

- Replace the visual implication that all navigation belongs in one horizontal bar with an intentional compact Operations Bar.
- Preserve existing destinations as action records; use redirects/aliases during migration so bookmarked workflows remain stable.
- Expose update count separately from ordinary controls. A failed check should never look like zero updates.

### Settings, Manage, Reports

- Retire the long independent dropdowns as primary surfaces.
- Preserve each as a direct, URL-addressable hub view so the words remain familiar to administrators.
- In launcher results, provide direct action rows across all three, with exact language: `Settings — [Plugin]`, `Report — [Plugin]`, `Open — [Plugin]`.
- For a temporary mega-menu phase, cap visible curated items, include Search/All actions at the top, group by task, and make scrolling an explicit last resort—not two columns of an unbounded link list.

### Plugin finder / manager

- Treat registry detection as data feeding launcher and hub, not only conditional markup feeding menus.
- Show installation state, plugin component, version, compatible action types, and a concise status. Keep legacy remediation distinct from routine updates.
- The block should open the hub; it should not host the full manager modal.
- Support a no-installed-plugins state that explains that managed links appear as LMS Labs plugins are installed, with a route appropriate to administrator permissions.

### Version checking and update controls

- Rename “Auto-update” to reflect its actual action. If it downloads code then redirects to Moodle’s upgrade page, use language such as `Download update and continue to Moodle upgrade`.
- The default update action is **Review update**, not update now.
- Update review must show plugin name/component, installed and available versions, release date, changelog, source, checksum verification state, Moodle compatibility, and rollback/support guidance.
- Bulk update begins with an itemized review and selection state. Require an explicit confirmation whose wording names the number of selected plugins. No automatic redirect before the review is acknowledged.
- On partial failure, show each outcome and offer Retry for failed items; never collapse failure into a generic notification.
- Clearly distinguish: “check unavailable,” “no update found,” “update downloaded/staged,” “Moodle database upgrade required,” and “Moodle upgrade completed.” Do not claim a plugin is updated before the final platform step succeeds.


#### Implemented safety contract

- Update checking is read-only. It only builds the review queue.
- Installation calls require an explicit per-plugin selection, reviewed target version, confirmation flag, and a 64-character SHA-256.
- The server re-fetches the publisher manifest and requires its component, version, download URL, and SHA-256 to match the reviewed request before downloading.
- Downloaded bytes must match the same SHA-256. A missing or changed hash fails closed.
- Bulk staging is sequential and stops at the first failure. The result names every staged, failed, and not-started item.
- “Staged” means plugin files are present only. The administrator must deliberately continue to Moodle’s upgrade page; this UI never reports Moodle’s upgrade as complete.
- Moodle's configuration log records the reviewed component/version, staging start, successful handoff, or the terminal rejection/failure phase for audit.

### Support, credits, and custom links

- Support is a global secondary route, not a competing primary call-to-action. Include diagnostics context only with deliberate user action and clear privacy language.
- Credit balance is informative, non-blocking, timestamped when stale, and remains reachable even when balance lookup fails. Avoid making a purchase action appear required to view balance.
- Custom links belong in launcher indexing and an editable hub section. Identify their site scope/owner. Use labelled edit/delete actions and confirm deletion with link name/destination.

---

## 8. Migration phases

### Phase 0 — Instrument and baseline (no IA removal)

- Define anonymous, privacy-reviewed events: launcher open, query length, result activation, menu scroll depth, no-result query, update review start/cancel/complete, custom-link actions.
- Capture baseline task success for representative administrators: open a known setting, locate a report, find a newly installed plugin, review an update.
- Audit every registry destination, capability condition, and installed detection result. This is the preservation contract.

### Phase 1 — Safety and access stabilization

- Keep current links but ensure every list has bounded, keyboard-operable access and result orientation.
- Rework update labeling and confirmations before any visual restructuring.
- Correct hover-only custom-link controls and asynchronous error/loading announcements.
- Add stable destinations for Reports, Settings, and Manage views even if initially backed by current content.

### Phase 2 — Introduce Action Launcher behind an additive entry point

- Index all current installed destinations and aliases; parity-test every existing destination.
- Add launcher to the block without removing familiar labels.
- Start with Recent, search, and Browse by task. Add pins after validating per-user persistence and privacy expectations.
- Evaluate time-to-destination, no-result rate, and accessibility testing with keyboard and screen-reader administrators.

**Implementation status (30 August 2026):** The additive Action Launcher is now present in the
Operations Bar and indexes eligible Settings, Manage, Reports, and custom-report destinations
from the installed-plugin navigation registry, plus eligible Site Quick Links, user custom links,
student email/portfolio routes, and external Quicklinks. The legacy dropdowns remain available
while parity is verified. The launcher includes live result counts, keyboard result movement,
Escape, modal focus containment/restoration, and a full-screen narrow/mobile presentation.

### Phase 3 — Launch Management Hub

- Move Plugin Manager capability into the durable hub with deep links.
- Add plugin detail and Updates review queue.
- Make Reports and Settings/Manage direct links open appropriate hub views rather than scrollable menus.
- Maintain legacy dropdown entry points as redirecting transitional affordances with deprecation messaging only where it does not interrupt work.

### Phase 4 — Retire unbounded dropdown catalogue

- Remove long dropdown lists after adoption and destination-parity checks succeed.
- Retain only short, curated quick lists where data supports them; every one includes `Find all actions`.
- Promote task console/attention queue and personal pins if evidence shows repeat use.

### Phase 5 — Governance and ongoing scale

- New plugin registration requires: display name, component, category, aliases, actions, destination capability, update metadata, accessibility label, and empty/error behavior.
- Add regression fixtures for 0, 1, 10, 50, and 100 installed plugins; long translated names; narrow regions; slow/failed external checks.
- Review analytics and taxonomy quarterly; do not add a new top-level dropdown as the catalogue grows.

---

## 9. Acceptance criteria

### Destination preservation and scalability

- [ ] Every destination currently exposed by Reports, Settings, Manage, AI tools, support, credits, Plugin Manager, and custom links remains reachable by a stable route or launcher result when the relevant plugin and capability are present.
- [ ] Installed-plugin detection remains the source of availability; unavailable plugins do not produce dead links.
- [ ] A catalogue with 100 installed plugins has no primary navigation surface that requires scanning an unbounded multi-column dropdown.
- [ ] A known action can be opened through the launcher in no more than: open launcher, type query, activate result.
- [ ] Search matches plugin name, action, relevant category terms, and documented legacy aliases.
- [ ] Reports, configuration, plugin inventory, and updates each have durable, bookmarkable management views.

### Update safety

- [ ] No update/download/install operation starts from a single ambiguous click.
- [ ] Before confirming, administrators can inspect selected plugin(s), installed/available versions, change notes, compatibility, source/checksum state, and the next Moodle upgrade step.
- [ ] Bulk actions identify all selected plugins and show per-plugin progress and failures.
- [ ] The system never reports “updated” until the appropriate Moodle completion state is confirmed.
- [ ] Failed version/credit/changelog checks are distinguishable from zero/empty results and include retry guidance.

### Responsive and accessibility quality

- [ ] At a 320 CSS-pixel viewport and in a narrow block region, all actions remain reachable without horizontal scrolling, clipped overlays, hover-only control, or transformed offscreen menus.
- [ ] Launcher and confirmations are fully usable by keyboard: trigger, focus placement, search, result activation, Escape, close, and focus restoration pass manual testing.
- [ ] Screen-reader testing verifies dialog announcement, result count/status announcements, sensible reading order, status text, and no duplicate/dynamically orphaned links.
- [ ] Every actionable control has a programmatic name; every status communicates without color alone.
- [ ] Focus, text, control, and error states meet WCAG 2.2 AA against supported Moodle themes.
- [ ] Reduced-motion users receive equivalent state feedback without required animation.

### Operational confidence

- [ ] Loading, error, no-result, no-installed-plugin, no-permission, and stale-data states are designed and tested.
- [ ] Credit and support integrations never prevent access to core navigation.
- [ ] Custom-link edit and deletion work by mouse, keyboard, touch, and screen reader, with deletion confirmation.
- [ ] The design is validated on Moodle 4.0–5.1 using supported core themes and representative constrained/narrow block placements.

---

## Final decision

Adopt **Hypothesis B + Hypothesis C**: an **Action Launcher** for rapid command retrieval and a **Management Hub** for complete plugin operations. Add **Hypothesis D** only after baseline navigation is stable. Treat a mega menu as a temporary compatibility mechanism, not the destination.

This changes the product from a growing set of dropdowns into an administrative control surface that remains fast when the catalogue doubles, preserves every installed-plugin destination, and makes consequential update actions visibly deliberate.
