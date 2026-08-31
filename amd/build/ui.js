/**
 * Interactive UI for the AI Dashboard Quick Links block (v2 redesign).
 *
 * Renders the entire client-side experience described in the v2.5.0 build
 * contract into #ainav2-root, from the JSON payload PHP embeds in
 * #ainav2-data: the four nav cards, Moodle shortcuts, credit traffic light,
 * global search, status strip, software cards and footer on the home view,
 * plus the four drill-in panels (Plugins / Settings / Manage / Reports) with
 * category chips, status/type filters, sort, saved layouts, favourites,
 * hover help and the row tooltip, icon-picker builder modal, purge schedule
 * modal and credit unlock modal.
 *
 * Ported from the approved mockup (quicklinks.html) to ES5-safe syntax and
 * wired to real Moodle user preferences and web services in place of
 * localStorage / fake demo data.
 *
 * @module     block_aiplugin_nav/ui
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/notification', 'core/str', 'core_user/repository'],
        function ($, Ajax, Notification, Str, UserRepository) {

    'use strict';

    /** @var {string} User preference name for the favourites array. */
    var PREF_FAVES = 'block_aiplugin_nav_faves';
    /** @var {string} User preference name for saved panel layouts. */
    var PREF_LAYOUT = 'block_aiplugin_nav_layout';
    /** @var {string} User preference name for the help-tips switch. */
    var PREF_HELP = 'block_aiplugin_nav_help';
    var PREF_SPEND = 'block_aiplugin_nav_spend';

    /* ------------------------------------------------------------------ *
     * Module state.
     * ------------------------------------------------------------------ */

    var DATA = null;               // Parsed payload.
    var CATS = {};                 // cat key -> label.
    var CATORDER = [];             // ordered cat keys.
    var PTYPES = {};               // ptype key -> label.
    var HELP = {};                 // help key -> {b,t,p,l,tip}.

    var current = null;            // 'plugins' | 'settings' | 'manage' | 'reports' | null.
    var filt = 'all';
    var sort = 'az';
    var ptype = 'all';
    var pstate = 'all';
    var pq = '';                   // Per-panel search term (Plugins/Settings/Manage/Reports).

    var checkState = 'idle';       // 'idle' | 'checking' | 'done' | 'failed'.
    var lastCheck = null;          // When the last successful check finished.
    var spend = [];                // Recent credit spends: [{n: name, c: credits, t: unixtime}].
    var faves = {};                // name -> true.
    var helpOn = true;
    var layouts = {};              // panel id -> {filt,sort,ptype,pstate,open:[]}.
    var pickIcon = 'link';
    var customLinks = [];
    var customReports = [];
    var schedule = {on: false, freq: 'weekly', day: 'Sunday', time: '02:00', lastmanual: '', lastauto: ''};
    var creditBalance = 0;
    var creditUnlimited = false;

    var isTouch = false;
    var reduceMotion = false;
    var rtTimer = null;
    var toastTimer = null;

    // Cached DOM references, populated by buildShell().
    var els = {};

    /* ------------------------------------------------------------------ *
     * Small utilities.
     * ------------------------------------------------------------------ */

    /**
     * HTML-escape a string for safe interpolation into innerHTML.
     *
     * @param {*} s
     * @return {string}
     */
    function esc(s) {
        if (s === undefined || s === null) {
            return '';
        }
        return String(s).replace(/[&<>"']/g, function (c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
        });
    }

    /**
     * Format an integer with thousands separators.
     *
     * @param {number} n
     * @return {string}
     */
    function fmtNum(n) {
        n = Math.round(n);
        var s = String(Math.abs(n));
        var out = '';
        var i;
        for (i = 0; i < s.length; i++) {
            if (i > 0 && (s.length - i) % 3 === 0) {
                out += ',';
            }
            out += s.charAt(i);
        }
        return (n < 0 ? '-' : '') + out;
    }

    /**
     * True if two arrays contain the same primitive values in order.
     *
     * @param {Array} a
     * @param {Array} b
     * @return {boolean}
     */
    function sameArray(a, b) {
        if (!a || !b || a.length !== b.length) {
            return false;
        }
        var i;
        for (i = 0; i < a.length; i++) {
            if (a[i] !== b[i]) {
                return false;
            }
        }
        return true;
    }

    /**
     * Object.keys shim-safe helper returning own keys of a plain object.
     *
     * @param {Object} o
     * @return {Array}
     */
    function keysOf(o) {
        var out = [];
        var k;
        for (k in o) {
            if (Object.prototype.hasOwnProperty.call(o, k)) {
                out.push(k);
            }
        }
        return out;
    }

    /* ------------------------------------------------------------------ *
     * User preference persistence (Moodle, not localStorage).
     * ------------------------------------------------------------------ */

    /**
     * Persist a user preference via M.util, silently ignoring failure.
     *
     * @param {string} name
     * @param {string} value
     */
    function setPref(name, value) {
        try {
            // core_user/repository is the supported route. Its predecessor,
            // M.util.set_user_preference, goes through an endpoint whose whitelist
            // function (user_preference_allow_ajax_update) is deprecated. The four
            // preferences this block writes are declared in lib.php via
            // block_aiplugin_nav_user_preferences(), which is what authorises them here.
            if (UserRepository && UserRepository.setUserPreference) {
                var p = UserRepository.setUserPreference(name, value);
                if (p && p.catch) {
                    p.catch(function () {
                        // Preference just won't stick this time; not worth interrupting.
                    });
                }
                return;
            }
            if (typeof M !== 'undefined' && M.util && M.util.set_user_preference) {
                M.util.set_user_preference(name, value);
            }
        } catch (e) {
            // Ignore - preference just won't stick this time.
        }
    }

    /**
     * Save the current favourites set as a user preference.
     */
    function saveFaves() {
        try {
            setPref(PREF_FAVES, JSON.stringify(keysOf(faves)));
        } catch (e) {
            // Ignore.
        }
    }

    /**
     * Save the saved-layouts map as a user preference.
     */
    function saveLayouts() {
        try {
            setPref(PREF_LAYOUT, JSON.stringify(layouts));
        } catch (e) {
            // Ignore.
        }
    }

    /**
     * Load preference-backed state from the payload, falling back to
     * sensible defaults when the payload does not carry it.
     */
    function loadPrefsFromPayload() {
        var i, arr;

        faves = {};
        try {
            arr = (DATA.prefs && DATA.prefs.faves) || DATA.faves || null;
            if (typeof arr === 'string') {
                arr = JSON.parse(arr);
            }
            if (Array.isArray(arr)) {
                for (i = 0; i < arr.length; i++) {
                    faves[arr[i]] = true;
                }
            }
        } catch (e) {
            faves = {};
        }

        spend = [];
        try {
            var sp = (DATA.prefs && DATA.prefs.spend) || null;
            if (typeof sp === 'string') {
                sp = JSON.parse(sp);
            }
            if (Array.isArray(sp)) {
                spend = sp;
            }
        } catch (e) {
            spend = [];
        }

        layouts = {};
        try {
            var lay = (DATA.prefs && DATA.prefs.layout) || DATA.layout || null;
            if (typeof lay === 'string') {
                lay = JSON.parse(lay);
            }
            if (lay && typeof lay === 'object') {
                layouts = lay;
            }
        } catch (e) {
            layouts = {};
        }

        helpOn = true;
        try {
            var h = (DATA.prefs && DATA.prefs.help);
            if (h === undefined) {
                h = DATA.help_pref;
            }
            if (h !== undefined && h !== null) {
                helpOn = (String(h) === '1');
            }
        } catch (e) {
            helpOn = true;
        }
    }

    /* ------------------------------------------------------------------ *
     * Icon set for the icon picker / core tile fallback icons.
     * ------------------------------------------------------------------ */

    var ICONS = {
        'link': '<path d="M9 15 15 9M10.5 6.5 12 5a4 4 0 1 1 5.7 5.7l-1.5 1.5M13.5 17.5 12 19a4 4 0 1 1-5.7-5.7l1.5-1.5"/>',
        'external': '<path d="M7 17 17 7M9 7h8v8"/>',
        'home': '<path d="m3 11 9-7 9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'chart': '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'trend': '<path d="m3 17 6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
        'gauge': '<path d="M4 18a8 8 0 1 1 16 0"/><path d="m12 14 4-4"/>',
        'users': '<circle cx="9" cy="8" r="3"/><path d="M3 20a6 6 0 0 1 12 0M17 11a3 3 0 1 0-2-5"/>',
        'user': '<circle cx="12" cy="8" r="4"/><path d="M5 21a7 7 0 0 1 14 0"/>',
        'group': '<circle cx="8" cy="9" r="2.5"/><circle cx="16" cy="9" r="2.5"/><path d="M3 19a5 5 0 0 1 10 0M13 19a5 5 0 0 1 8-4"/>',
        'book': '<path d="M5 4h11a2 2 0 0 1 2 2v14H7a2 2 0 0 1-2-2z"/><path d="M9 4v16"/>',
        'grad': '<path d="m2 8 10-4 10 4-10 4z"/><path d="M6 11v5c0 1.5 3 3 6 3s6-1.5 6-3v-5"/>',
        'calendar': '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 11h18"/>',
        'clock': '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'shield': '<path d="M12 3l7 3v6c0 4-3 7-7 9-4-2-7-5-7-9V6z"/>',
        'lock': '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 1 1 8 0v3"/>',
        'unlock': '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 7.5-2"/>',
        'key': '<circle cx="8" cy="14" r="4"/><path d="m11 11 9-9M17 5l2 2M14 8l2 2"/>',
        'cog': '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9 7 7M17 17l2.1 2.1M19.1 4.9 17 7M7 17l-2.1 2.1"/>',
        'sliders': '<path d="M4 6h16M4 12h16M4 18h16"/><circle cx="9" cy="6" r="2" fill="currentColor" stroke="none"/><circle cx="15" cy="12" r="2" fill="currentColor" stroke="none"/><circle cx="8" cy="18" r="2" fill="currentColor" stroke="none"/>',
        'wrench': '<path d="M15 3a5 5 0 0 0-4.6 7L3 17.4 6.6 21l7.4-7.4A5 5 0 1 0 15 3z"/>',
        'mail': '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'bell': '<path d="M6 9a6 6 0 1 1 12 0v5l2 3H4l2-3z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
        'chat': '<path d="M4 5h16v11H9l-5 4z"/>',
        'file': '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v5h5"/>',
        'files': '<path d="M8 2h7l4 4v11H8z"/><path d="M5 6v14h11"/>',
        'folder': '<path d="M3 7h6l2 2h10v10H3z"/>',
        'clipboard': '<rect x="6" y="4" width="12" height="17" rx="2"/><path d="M9 4V3h6v1"/><path d="M9 10h6M9 14h4"/>',
        'checklist': '<path d="M4 6h2l1 1 2-2M4 12h2l1 1 2-2M4 18h2l1 1 2-2M13 6h7M13 12h7M13 18h7"/>',
        'check': '<path d="m4 13 5 5L20 6"/>',
        'award': '<circle cx="12" cy="9" r="5"/><path d="m8.5 13.5-1 7.5 4.5-2.5 4.5 2.5-1-7.5"/>',
        'star': '<path d="m12 4 2.4 5 5.6.5-4.2 3.8 1.2 5.5L12 16l-5 2.8 1.2-5.5L4 9.5 9.6 9z"/>',
        'flag': '<path d="M5 21V4h9l-1 3h6v8h-8l-1-3H5"/>',
        'bolt': '<path d="M13 3 5 14h6l-1 7 8-11h-6z"/>',
        'rocket': '<path d="M12 3c4 2 6 6 6 10l-3 3H9l-3-3c0-4 2-8 6-10z"/><path d="M9 19l-2 3 4-1M15 19l2 3-4-1"/>',
        'globe': '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c5 5 5 13 0 18-5-5-5-13 0-18"/>',
        'db': '<ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6"/>',
        'server': '<rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><path d="M7 7.5h.01M7 16.5h.01"/>',
        'cloud': '<path d="M7 18a4 4 0 0 1 .5-8 6 6 0 0 1 11.5 2 3.5 3.5 0 0 1-1 6z"/>',
        'download': '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M4 20h16"/>',
        'upload': '<path d="M12 21V9"/><path d="m7 14 5-5 5 5"/><path d="M4 4h16"/>',
        'refresh': '<path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v5h-5"/>',
        'trash': '<path d="M4 7h16M9 7V5h6v2M6 7l1 14h10l1-14"/>',
        'search': '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.2-3.2"/>',
        'filter': '<path d="M3 5h18l-7 8v6l-4-2v-4z"/>',
        'tag': '<path d="M3 12V4h8l9 9-8 8z"/><circle cx="7.5" cy="7.5" r="1.3"/>',
        'card': '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
        'coins': '<ellipse cx="9" cy="7" rx="6" ry="3"/><path d="M3 7v4c0 1.7 2.7 3 6 3s6-1.3 6-3V7"/><ellipse cx="15" cy="15" rx="6" ry="3"/><path d="M9 15v3c0 1.7 2.7 3 6 3s6-1.3 6-3v-3"/>',
        'cart': '<circle cx="9" cy="20" r="1.5"/><circle cx="17" cy="20" r="1.5"/><path d="M3 4h2l2.5 11h11L21 8H6"/>',
        'video': '<rect x="3" y="6" width="12" height="12" rx="2"/><path d="m15 10 6-3v10l-6-3z"/>',
        'mic': '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 12a7 7 0 0 0 14 0M12 19v3"/>',
        'image': '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m4 19 6-6 4 4 3-3 4 4"/>',
        'play': '<circle cx="12" cy="12" r="9"/><path d="m10 8 7 4-7 4z"/>',
        'map': '<path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3z"/><path d="M9 3v15M15 6v15"/>',
        'pin': '<path d="M12 21s7-6 7-11a7 7 0 1 0-14 0c0 5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',
        'building': '<path d="M4 21V5l8-3v19"/><path d="M12 9h8v12"/><path d="M7 8h1M7 12h1M7 16h1M16 13h1M16 17h1"/>',
        'palette': '<path d="M12 3a9 9 0 1 0 0 18c1.7 0 2-1 1.3-1.8-.9-1 .1-2.2 1.2-2.2H18a3 3 0 0 0 3-3 9 9 0 0 0-9-9z"/><circle cx="8" cy="10" r="1" fill="currentColor"/>',
        'help': '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3 2.4V14"/><circle cx="12" cy="17" r=".6" fill="currentColor"/>',
        'info': '<circle cx="12" cy="12" r="9"/><path d="M12 11v6"/><circle cx="12" cy="7.5" r=".6" fill="currentColor"/>',
        'warn': '<path d="M12 4 2.5 20h19z"/><path d="M12 10v4"/><circle cx="12" cy="17" r=".6" fill="currentColor"/>',
        'eye': '<path d="M2 12s4-6 10-6 10 6 10 6-4 6-10 6-10-6-10-6z"/><circle cx="12" cy="12" r="3"/>',
        'grid': '<path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/>',
        'list': '<path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/>',
        'compass': '<circle cx="12" cy="12" r="9"/><path d="m15 9-2 5-5 2 2-5z"/>'
    };
    var ICONKEYS = keysOf(ICONS);

    var CHEV = '<svg class="ainav2-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m9 5 7 7-7 7"/></svg>';
    var STAR = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="m12 3 2.6 5.9 6.4.6-4.8 4.3 1.4 6.2L12 16.8 6.4 20l1.4-6.2L3 9.5l6.4-.6z"/></svg>';
    var I_CARDS = {
        plugins: '<path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/>',
        settings: '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9 7 7M17 17l2.1 2.1M19.1 4.9 17 7M7 17l-2.1 2.1"/>',
        manage: '<path d="M4 6h16M4 12h16M4 18h16"/><circle cx="9" cy="6" r="2" fill="currentColor" stroke="none"/><circle cx="15" cy="12" r="2" fill="currentColor" stroke="none"/><circle cx="8" cy="18" r="2" fill="currentColor" stroke="none"/>',
        reports: '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>'
    };

    /**
     * Build an inline SVG icon by key.
     *
     * @param {string} k
     * @param {string} [cls]
     * @return {string}
     */
    function svgIcon(k, cls) {
        return '<svg class="' + esc(cls || '') + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">' +
            (ICONS[k] || ICONS.link) + '</svg>';
    }

    /* ------------------------------------------------------------------ *
     * Datasets derived from the payload.
     * ------------------------------------------------------------------ */

    /**
     * Return the flat row-model array for a panel id.
     *
     * @param {string} id
     * @return {Array}
     */
    function datasetFor(id) {
        var src, out = [], i, x;
        if (id === 'plugins') {
            src = DATA.plugins || [];
            for (i = 0; i < src.length; i++) {
                x = src[i];
                out.push({
                    name: x.name, cat: x.cat, ptype: x.ptype || 'local', desc: x.desc || '',
                    docs: x.docs || '', kind: 'plugin', item: x
                });
            }
            return out;
        }
        if (id === 'settings') {
            src = DATA.settings || [];
            for (i = 0; i < src.length; i++) {
                x = src[i];
                out.push({
                    name: x.name, cat: x.cat, ptype: x.ptype || 'local', desc: x.desc || '',
                    docs: x.docs || '', url: x.url || '#', configured: !!x.configured, kind: 'link'
                });
            }
            return out;
        }
        if (id === 'manage') {
            src = DATA.manage || [];
            for (i = 0; i < src.length; i++) {
                x = src[i];
                out.push({
                    name: x.name, cat: x.cat, ptype: x.ptype || 'local', desc: x.desc || '',
                    docs: x.docs || '', url: x.url || '#', kind: 'link'
                });
            }
            return out;
        }
        // reports
        src = DATA.reports || [];
        for (i = 0; i < src.length; i++) {
            x = src[i];
            out.push({
                name: x.name, cat: x.cat, ptype: x.ptype || 'local', desc: x.desc || '',
                docs: x.docs || '', url: x.url || '#', live: !!x.live, kind: 'report'
            });
        }
        return out;
    }

    /**
     * Deterministic-ish state for a settings/manage/report row from real
     * payload fields (in place of the mockup's fake hashed demo state).
     *
     * @param {Object} d
     * @return {{cls:string,txt:string}}
     */
    function rowState(d) {
        if (d.kind === 'report') {
            return d.live ? {cls: 'ok', txt: 'Live'} : {cls: 'off', txt: 'Scheduled'};
        }
        if (current === 'settings') {
            return d.configured ? {cls: 'ok', txt: 'Configured'} : {cls: 'warn', txt: 'Needs setup'};
        }
        return {cls: 'off', txt: ''};
    }

    var FOUNDATION = 'AI Grader Central Config';

    /**
     * Build the trailing pill/button markup for a plugin row.
     *
     * @param {Object} it Plugin payload object.
     * @return {string}
     */
    function plugTail(it) {
        if (it.status === 'testing') {
            return '<span class="ainav2-state ainav2-testing">In testing</span>' +
                '<button class="ainav2-get ainav2-testing-disabled" type="button" disabled ' +
                'title="Available after testing completes">Unavailable</button>';
        }
        if (it.installed) {
            if (it.update) {
                // Show what it will become, not just what it is — "v3.9.9 \u2192 v4.0.1"
                // tells the admin whether the update is worth taking.
                var vertxt = esc(it.version || '');
                if (it.latestversion && it.latestversion !== it.version) {
                    vertxt += ' \u2192 ' + esc(it.latestversion);
                }
                return '<span class="ainav2-ver ainav2-up">' + vertxt + '</span>' +
                    '<button class="ainav2-get ainav2-upd" type="button" data-update="' + esc(it.component || it.pluginid || '') +
                    '" data-name="' + esc(it.name) + '">Update</button>';
            }
            // Activities, blocks and subplugins have no page to open — they are used
            // inside a course. PHP decides which of the two this row is (see
            // block_aiplugin_nav_payload::resolve_action).
            var label = it.action === 'settings' ? 'Settings' : 'Open';
            return '<span class="ainav2-ver">' + esc(it.version || '') + '</span>' +
                '<button class="ainav2-rowact" type="button" data-goto="' + esc(it.gotourl || '#') + '">' +
                label + '</button>';
        }
        var cost = it.credits || 0;
        if (cost === 0) {
            return '<span class="ainav2-cost ainav2-free">Free</span>' +
                '<button class="ainav2-get" type="button" data-cost="0" data-plug="' + esc(it.pluginid || '') +
                '" data-comp="' + esc(it.component || '') +
                '" data-plugname="' + esc(it.name) + '">Get</button>';
        }
        return '<span class="ainav2-cost">' + fmtNum(cost) + '</span>' +
            '<button class="ainav2-get" type="button" data-cost="' + cost + '" data-plug="' + esc(it.pluginid || '') +
            '" data-comp="' + esc(it.component || '') +
            '" data-plugname="' + esc(it.name) + '">Get</button>';
    }

    /**
     * Documentation pill for a row, linking to its docs URL if known.
     *
     * @param {string} name
     * @param {string} url
     * @return {string}
     */
    function docsPill(name, url) {
        var href = url || 'https://lms-labs.com/docs';
        var cls = url ? 'ainav2-docs' : 'ainav2-docs ainav2-generic';
        return '<a class="' + cls + '" href="' + esc(href) + '" target="_blank" rel="noopener" ' +
            'title="Documentation for ' + esc(name) + '" aria-label="Docs for ' + esc(name) + '">' +
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
            '<path d="M5 4h11a2 2 0 0 1 2 2v14H7a2 2 0 0 1-2-2z"/><path d="M9 4v16"/></svg>Docs</a>';
    }

    /**
     * Build the markup for one row, used identically by every panel.
     *
     * @param {Object} d Row model from datasetFor().
     * @return {string}
     */
    function rowHTML(d) {
        var on = !!faves[d.name];
        var kind = PTYPES[d.ptype] || 'Local plugins';
        var badge = d.name === FOUNDATION ? '<span class="ainav2-found">Install first</span>' : '';
        var docs = docsPill(d.name, d.docs);
        var tail;
        if (d.kind === 'plugin') {
            tail = plugTail(d.item);
        } else {
            var st = rowState(d);
            var act = d.kind === 'report' ? 'Run' : (current === 'settings' ? 'Configure' : 'Open');
            var pill = st.txt ? '<span class="ainav2-state ainav2-' + st.cls + '">' + esc(st.txt) + '</span>' : '';
            tail = pill + '<button class="ainav2-rowact" type="button" data-goto="' + esc(d.url || '#') + '">' + act + '</button>';
        }
        var dt = d.desc ? ' data-rowhelp="' + esc(d.desc) + '"' : '';
        return '<div class="ainav2-pl" data-name="' + esc(d.name) + '" tabindex="0"' + dt + '>' +
            '<button class="ainav2-star" type="button" aria-pressed="' + on + '" aria-label="Favourite ' + esc(d.name) +
            '" data-fav="' + esc(d.name) + '">' + STAR + '</button>' +
            '<span class="ainav2-nm">' + esc(d.name) + '</span>' +
            '<span class="ainav2-ptype" data-t="' + esc(d.ptype) + '">' + esc(kind) + '</span>' + badge + docs +
            '<span class="ainav2-tailwrap">' + tail + '</span></div>';
    }

    /**
     * Plugin credit cost text for the row tooltip footer.
     *
     * @param {Object} it
     * @return {string}
     */
    function plugCostLabel(it) {
        if (!it) {
            return '';
        }
        if (it.installed) {
            return '';
        }
        var cost = it.credits || 0;
        return cost === 0 ? 'Free' : fmtNum(cost) + ' credits to unlock';
    }

    /* ------------------------------------------------------------------ *
     * DOM shell.
     * ------------------------------------------------------------------ */

    /**
     * Build the static page skeleton into #ainav2-root and cache element
     * references. Content that depends on the payload is filled in by the
     * render* functions.
     */
    /**
     * Whether the current user may see the admin panels. The payload omits the data
     * entirely for non-admins, so this is belt and braces for the chrome.
     *
     * @return {boolean}
     */
    function isAdmin() {
        return !!DATA.isadmin;
    }

    function buildShell() {
        var root = els.root;
        var html = '';

        html += '<div class="ainav2-bhead">';
        html += '  <div class="ainav2-brandrow" data-help="block" tabindex="0">';
        html += '    <div class="ainav2-mark"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
            '<path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/></svg></div>';
        html += '    <div class="ainav2-btext"><h2>AI Quick Links</h2><div class="ainav2-bsub">' +
            '<a href="https://lms-labs.com" target="_blank" rel="noopener" class="ainav2-blink">LMS Labs</a></div></div>';
        html += '  </div>';
        if (DATA.cancredits) {
            html += '  <div class="ainav2-creditbox ainav2-ok" id="ainav2-creditbox" data-help="credits">';
            html += '    <div class="ainav2-creditrow"><span class="ainav2-lamp"></span>' +
                '<div class="ainav2-amt" id="ainav2-camt">0</div><div class="ainav2-lab">credits</div>' +
                '<button class="ainav2-topup" type="button" id="ainav2-ctop">Top up</button></div>';
            html += '    <div class="ainav2-meterlab" id="ainav2-cmsg"></div>';
            html += '  </div>';
        }
        html += '  <div class="ainav2-searchwrap" data-help="search">';
        html += '    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.2-3.2"/></svg>';
        html += '    <input id="ainav2-q" type="search" placeholder="Search everything…" autocomplete="off" ' +
            'aria-label="Search settings, manage, reports and plugins">';
        html += '    <button class="ainav2-clearq" id="ainav2-clearq" type="button" hidden aria-label="Clear search">×</button>';
        html += '  </div>';
        html += '</div>';

        html += '<div class="ainav2-home" id="ainav2-home">';
        html += '  <div class="ainav2-corehead" data-help="core" tabindex="0">Moodle</div>';
        html += '  <div class="ainav2-core" id="ainav2-core"></div>';
        html += '  <div class="ainav2-spend" id="ainav2-spend" hidden></div>';
        html += '  <div class="ainav2-cards" id="ainav2-cards"></div>';
        html += '  <div class="ainav2-strip" id="ainav2-strip"></div>';
        html += '  <div class="ainav2-family" id="ainav2-family"></div>';
        html += '</div>';

        html += '<div class="ainav2-panel" id="ainav2-panel">';
        html += '  <div class="ainav2-phead">';
        html += '    <button class="ainav2-back" id="ainav2-back" type="button">' +
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m15 5-7 7 7 7"/></svg>Back</button>';
        html += '    <div class="ainav2-ptitle" id="ainav2-ptitle">Panel</div>';
        html += '    <div class="ainav2-pcount" id="ainav2-pcount">0</div>';
        html += '  </div>';
        html += '  <div class="ainav2-toolbar" id="ainav2-toolbar"></div>';
        html += '  <div class="ainav2-plist" id="ainav2-plist"></div>';
        html += '</div>';

        html += '<div class="ainav2-helpcard" id="ainav2-helpcard" role="tooltip" aria-hidden="true"></div>';
        html += '<div class="ainav2-toast" id="ainav2-toast" role="status" aria-live="polite">' +
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m5 13 4 4L19 7"/></svg>' +
            '<span id="ainav2-toasttxt"></span></div>';

        html += '<div class="ainav2-ov" id="ainav2-ov">';
        html += '  <div class="ainav2-modal" role="dialog" aria-modal="true" aria-labelledby="ainav2-mtitle">';
        html += '    <div class="ainav2-mhead"><h3 id="ainav2-mtitle">Title</h3>' +
            '<button class="ainav2-mclose" id="ainav2-mclose" type="button" aria-label="Close">×</button></div>';
        html += '    <div class="ainav2-mbody" id="ainav2-mbody"></div>';
        html += '    <div class="ainav2-mfoot" id="ainav2-mfoot"></div>';
        html += '  </div>';
        html += '</div>';

        html += '<div class="ainav2-bfoot">';
        html += '  <div class="ainav2-fleft"><span><span class="ainav2-dotok"></span>Connected to LMS Labs</span>' +
            '<label class="ainav2-helptoggle"><input type="checkbox" id="ainav2-helpon"><span class="ainav2-sw"></span>Show help tips</label></div>';
        html += '  <div class="ainav2-fcentre"></div>';
        html += '  <div class="ainav2-flinks">';
        html += '    <a href="https://marketplace.moodle.com/user/31" target="_blank" rel="noopener">Moodle Marketplace</a>';
        html += '    <a href="https://lms-labs.com/docs" target="_blank" rel="noopener">Docs</a>';
        html += '    <a href="https://lms-labs.com" target="_blank" rel="noopener">LMS Labs</a>';
        html += '  </div>';
        html += '</div>';

        root.innerHTML = html;

        els.home = document.getElementById('ainav2-home');
        els.spend = document.getElementById('ainav2-spend');
        els.panel = document.getElementById('ainav2-panel');
        els.plist = document.getElementById('ainav2-plist');
        els.toolbar = document.getElementById('ainav2-toolbar');
        els.ptitle = document.getElementById('ainav2-ptitle');
        els.pcount = document.getElementById('ainav2-pcount');
        els.q = document.getElementById('ainav2-q');
        els.clearq = document.getElementById('ainav2-clearq');
        els.cards = document.getElementById('ainav2-cards');
        els.core = document.getElementById('ainav2-core');
        els.strip = document.getElementById('ainav2-strip');
        els.family = document.getElementById('ainav2-family');
        els.back = document.getElementById('ainav2-back');
        els.creditbox = document.getElementById('ainav2-creditbox');
        els.camt = document.getElementById('ainav2-camt');
        els.cmsg = document.getElementById('ainav2-cmsg');
        els.ctop = document.getElementById('ainav2-ctop');
        els.helpcard = document.getElementById('ainav2-helpcard');
        els.toast = document.getElementById('ainav2-toast');
        els.toasttxt = document.getElementById('ainav2-toasttxt');
        els.ov = document.getElementById('ainav2-ov');
        els.mtitle = document.getElementById('ainav2-mtitle');
        els.mbody = document.getElementById('ainav2-mbody');
        els.mfoot = document.getElementById('ainav2-mfoot');
        els.mclose = document.getElementById('ainav2-mclose');
        els.helpon = document.getElementById('ainav2-helpon');

        els.rowtip = document.createElement('div');
        els.rowtip.className = 'ainav2-rowtip';
        document.body.appendChild(els.rowtip);
    }

    /* ------------------------------------------------------------------ *
     * Home view rendering.
     * ------------------------------------------------------------------ */

    /**
     * Render the Moodle shortcut tiles (core + the user's custom links).
     */
    function renderCore() {
        var i, c, out = '';
        var core = DATA.core || [];
        for (i = 0; i < core.length; i++) {
            c = core[i];
            var ext = /^https?:\/\//.test(c.url || '');
            var href = ext ? c.url : (DATA.wwwroot || '') + c.url;
            out += '<a class="ainav2-corebtn" href="' + esc(href) + '"' + (ext ? ' target="_blank" rel="noopener"' : '') +
                ' data-name="' + esc(c.name) + '" title="' + esc(href) + '">' + svgIcon(c.icon || 'link') +
                esc(c.name) + '</a>';
        }
        for (i = 0; i < customLinks.length; i++) {
            c = customLinks[i];
            out += '<a class="ainav2-corebtn ainav2-mine" href="' + esc(c.url) + '" target="_blank" rel="noopener" ' +
                'data-name="' + esc(c.name) + '" title="' + esc(c.url) + '">' +
                '<button class="ainav2-del" type="button" data-del="' + esc(c.id) + '" aria-label="Remove ' + esc(c.name) + '">×</button>' +
                svgIcon(c.icon || 'link') + esc(c.name) + '</a>';
        }
        out += '<button class="ainav2-addtile" type="button" data-addtile="1">' +
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>Add link</button>';
        els.core.innerHTML = out;
    }

    /**
     * Render the four nav cards, counts computed from the payload.
     */
    function renderCards() {
        var counts = DATA.counts || {};
        var installed = counts.installed || 0;
        var updates = counts.updates || 0;
        var cards = [];
        if (isAdmin() && DATA.plugins && DATA.plugins.length) {
            cards.push({
                id: 'plugins', label: 'Plugins', n: DATA.plugins.length, unit: 'available',
                sub: installed + ' installed · ' + updates + ' updates', flag: updates > 0 ? String(updates) : ''
            });
        }
        if (isAdmin() && DATA.settings && DATA.settings.length) {
            cards.push({id: 'settings', label: 'Settings', n: DATA.settings.length, unit: 'pages', sub: 'Configure what you run', flag: ''});
        }
        if (isAdmin() && DATA.manage && DATA.manage.length) {
            cards.push({id: 'manage', label: 'Manage', n: DATA.manage.length, unit: 'tools', sub: 'Day-to-day admin', flag: ''});
        }
        if (isAdmin() && DATA.reports && DATA.reports.length) {
            cards.push({id: 'reports', label: 'Reports', n: DATA.reports.length, unit: 'reports', sub: 'View all reports', flag: ''});
        }
        var i, c, out = '';
        for (i = 0; i < cards.length; i++) {
            c = cards[i];
            out += '<button class="ainav2-card" type="button" data-id="' + c.id + '" data-help="' + c.id + '" aria-expanded="false">';
            if (c.flag) {
                out += '<span class="ainav2-flag">' + esc(c.flag) + ' new</span>';
            }
            out += '<div class="ainav2-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">' +
                (I_CARDS[c.id] || '') + '</svg></div>';
            out += '<div class="ainav2-lbl">' + esc(c.label) + '</div>';
            out += '<div class="ainav2-meta"><span class="ainav2-n">' + c.n + '</span><span class="ainav2-unit">' + esc(c.unit) + '</span></div>';
            out += '<div class="ainav2-sub">' + esc(c.sub) + '</div>';
            out += '</button>';
        }
        els.cards.innerHTML = out;
    }

    /**
     * Render the support / updates / health status strip.
     */
    function renderStrip() {
        var counts = DATA.counts || {};
        var updates = counts.updates || 0;
        var installed = counts.installed || 0;
        var out = '';
        var supporturl = DATA.supporturl || 'https://lms-labs.com/docs/ai-support';
        var supportext = supporturl.indexOf(DATA.wwwroot) !== 0;
        out += '<a class="ainav2-support" href="' + esc(supporturl) + '" id="ainav2-support"' +
            (supportext ? ' target="_blank" rel="noopener"' : '') + ' data-help="support">' +
            '<div class="ainav2-sico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
            '<path d="M12 3a9 9 0 0 0-9 9v4a3 3 0 0 0 3 3h1v-7H5v-.5a7 7 0 0 1 14 0V15h-2v7h1a3 3 0 0 0 3-3v-7a9 9 0 0 0-9-9z"/></svg></div>' +
            '<div class="ainav2-sbody"><div class="ainav2-stitle">Ask AI Support</div>' +
            '<div class="ainav2-sdesc">Answers about your site, plugins and credits</div></div>' +
            '<span class="ainav2-sarrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">' +
            '<path d="m9 5 7 7-7 7"/></svg></span></a>';
        out += '<div class="ainav2-alert" data-help="updates" tabindex="0">' +
            '<div class="ainav2-aico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
            '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M4 20h16"/></svg></div>' +
            '<div class="ainav2-abody"><div class="ainav2-atop"><span class="ainav2-anum">' + updates + '</span>' +
            '<span class="ainav2-atitle">updates ready</span></div><div class="ainav2-adesc">Keep plugins current</div></div>' +
            (updates > 0 ? '<button class="ainav2-act" type="button" data-updateall="1">Update all</button>' : '') + '</div>';
        out += '<div class="ainav2-alert ainav2-calm" data-help="health" tabindex="0">' +
            '<div class="ainav2-aico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
            '<path d="m5 13 4 4L19 7"/></svg></div>' +
            '<div class="ainav2-abody"><div class="ainav2-atop"><span class="ainav2-anum">' + installed + '</span>' +
            '<span class="ainav2-atitle">installed &amp; healthy</span></div><div class="ainav2-adesc">No conflicts</div></div>' +
            '<span class="ainav2-pulse" title="All healthy"></span></div>';
        els.strip.innerHTML = out;
    }

    /**
     * Render the "our other products" family strip.
     */
    function renderProducts() {
        var products = DATA.products || [];
        var i, p, out = '<div class="ainav2-famhead" data-help="family" tabindex="0">Our software</div>';
        for (i = 0; i < products.length; i++) {
            p = products[i];
            out += '<a class="ainav2-fam" href="' + esc(p.url) + '" target="_blank" rel="noopener" style="--fc:' + esc(p.colour || '#3B82F6') + '">';
            out += '<span class="ainav2-farrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M7 17 17 7M9 7h8v8"/></svg></span>';
            out += '<div class="ainav2-ftop"><div class="ainav2-fico ainav2-logo">' + (p.logo || '') + '</div>' +
                '<div class="ainav2-fname"><b>' + esc(p.name) + '</b><span class="ainav2-fkind">' + esc(p.kind || '') + '</span></div></div>';
            out += '<div class="ainav2-fbody"><div class="ainav2-fdesc">' + esc(p.desc || '') + '</div>' +
                '<div class="ainav2-fprice">' + esc(p.price || '') + '</div></div>';
            out += '</a>';
        }
        els.family.innerHTML = out;
    }

    /**
     * Show a small non-blocking confirmation toast.
     *
     * @param {string} msg
     */
    function showToast(msg) {
        els.toasttxt.textContent = msg;
        els.toast.classList.add('ainav2-show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            els.toast.classList.remove('ainav2-show');
        }, 2200);
    }

    /* ------------------------------------------------------------------ *
     * Credits.
     * ------------------------------------------------------------------ */

    /**
     * Apply a credit balance to the traffic-light widget.
     *
     * @param {number} n
     */
    function setCredits(n) {
        creditBalance = n;
        if (!els.creditbox) {
            return;
        }
        var level = creditUnlimited || n >= 5000 ? 'ok' : (n >= 2000 ? 'warn' : 'low');
        els.creditbox.className = 'ainav2-creditbox ainav2-' + level;
        els.camt.textContent = creditUnlimited ? 'Unlimited' : fmtNum(n);
        els.ctop.classList.toggle('ainav2-urgent', level === 'low');
        els.ctop.textContent = level === 'low' ? 'Top up now' : 'Top up';
        els.cmsg.textContent = creditUnlimited ? 'Unlimited plan — every plugin unlocks at no extra cost.' :
            (level === 'ok' ? 'Most plugins unlock for 500 credits. Already bought on the Moodle Marketplace? Yours at no cost.' :
                (level === 'warn' ? 'Running low. Most plugins unlock for 500 credits — or free if you bought them on the Moodle Marketplace.' :
                    'Credits critically low. Gated plugins will stop working.'));
        els.creditbox.setAttribute('aria-live', level === 'low' ? 'assertive' : 'off');
    }

    /**
     * Fetch the live credit balance from the server.
     */
    function loadCredits() {
        if (!DATA.cancredits) {
            return;
        }
        Ajax.call([{
            methodname: 'block_aiplugin_nav_get_credits',
            args: {},
            done: function (response) {
                if (!response || !response.success || response.credits === '') {
                    return;
                }
                if (response.credits === 'unlimited' || response.credits === '-1' || response.credits === -1) {
                    creditUnlimited = true;
                    setCredits(0);
                } else {
                    creditUnlimited = false;
                    setCredits(parseInt(response.credits, 10) || 0);
                }
            },
            fail: function () {
                // Silent fail - the traffic light simply keeps its default state.
            }
        }]);
    }

    /* ------------------------------------------------------------------ *
     * Panel rendering.
     * ------------------------------------------------------------------ */

    /**
     * @param {Array} defs
     * @return {string}
     */
    function chipsHTML(defs) {
        var i, d, out = '<div class="ainav2-chips">';
        for (i = 0; i < defs.length; i++) {
            d = defs[i];
            out += '<button class="ainav2-chip" type="button" data-f="' + esc(d.k) + '" aria-pressed="' + (filt === d.k) + '">' +
                esc(d.l) + '<span class="ainav2-cn">' + d.n + '</span></button>';
        }
        return out + '</div>';
    }

    /**
     * @param {string} msg
     * @return {string}
     */
    function emptyHTML(msg) {
        return '<div class="ainav2-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">' +
            '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.2-3.2"/></svg><p>' + esc(msg) + '</p>' +
            '<p class="ainav2-hintline">Try another filter, or clear it to see everything.</p></div>';
    }

    /**
     * Names of the currently expanded accordion groups, for reopening
     * after a re-render.
     *
     * @return {Array}
     */
    function openGroupNames() {
        var nodes = els.plist.querySelectorAll('.ainav2-grp.ainav2-open .ainav2-grpname');
        var out = [], i;
        for (i = 0; i < nodes.length; i++) {
            out.push(nodes[i].textContent);
        }
        return out;
    }

    /**
     * Reopen accordion groups by name after a re-render.
     *
     * @param {Array} names
     */
    function reopen(names) {
        if (!names || !names.length) {
            return;
        }
        var groups = els.plist.querySelectorAll('.ainav2-grp');
        var i, g, head, name;
        for (i = 0; i < groups.length; i++) {
            g = groups[i];
            head = g.querySelector('.ainav2-grphead');
            name = g.querySelector('.ainav2-grpname').textContent;
            if (names.indexOf(name) !== -1) {
                g.classList.add('ainav2-open');
                head.setAttribute('aria-expanded', 'true');
            }
        }
    }

    /**
     * Status dropdown options for the current panel.
     *
     * @return {Array}
     */
    function statusOptsFor() {
        if (current === 'plugins') {
            return [['all', 'All'], ['installed', 'Installed'], ['update', 'Updates'], ['free', 'Free'],
                ['paid', 'Credit-gated'], ['testing', 'In testing']];
        }
        if (current === 'settings') {
            return [['all', 'All'], ['Configured', 'Configured'], ['Needs setup', 'Needs setup']];
        }
        if (current === 'reports') {
            return [['all', 'All'], ['Live', 'Live'], ['Scheduled', 'Scheduled']];
        }
        return [['all', 'All']];
    }

    /**
     * Plugin status bucket, used by the Status dropdown filter.
     *
     * @param {Object} it
     * @return {string}
     */
    function pluginStatus(it) {
        if (it.status === 'testing') {
            return 'testing';
        }
        if (it.installed) {
            return it.update ? 'update' : 'installed';
        }
        return (it.credits || 0) === 0 ? 'free' : 'paid';
    }

    /**
     * Re-render the toolbar + row list for the currently open panel.
     */
    /**
     * Placeholder for the per-panel search box, named after the panel it sits in.
     *
     * @return {string}
     */
    function searchPlaceholder() {
        if (current === 'settings') {
            return 'Search settings\u2026';
        }
        if (current === 'manage') {
            return 'Search management tools\u2026';
        }
        if (current === 'reports') {
            return 'Search reports\u2026';
        }
        return 'Search plugins\u2026';
    }

    function renderPanel() {
        var all = datasetFor(current);
        var i, d;

        function byType(d) {
            return ptype === 'all' || d.ptype === ptype;
        }
        function byQuery(d) {
            if (!pq) {
                return true;
            }
            var hay = (d.name || '') + ' ' + (d.desc || '') + ' ' +
                (d.item && d.item.component ? d.item.component : '') + ' ' +
                (CATS[d.cat] || '') + ' ' + (PTYPES[d.ptype] || '');
            return hay.toLowerCase().indexOf(pq) !== -1;
        }
        function byState(d) {
            if (pstate === 'all') {
                return true;
            }
            if (current === 'plugins') {
                if (pstate === 'installed') {
                    var s = pluginStatus(d.item);
                    return s === 'installed' || s === 'update';
                }
                return pluginStatus(d.item) === pstate;
            }
            var t = rowState(d).txt;
            return t === pstate;
        }

        var base = [];
        for (i = 0; i < all.length; i++) {
            if (byType(all[i]) && byState(all[i]) && byQuery(all[i])) {
                base.push(all[i]);
            }
        }

        var defs = [{k: 'all', l: 'All', n: base.length}];
        var favCount = 0;
        for (i = 0; i < base.length; i++) {
            if (faves[base[i].name]) {
                favCount++;
            }
        }
        defs.push({k: 'fav', l: '★ Starred', n: favCount});
        for (i = 0; i < CATORDER.length; i++) {
            var c = CATORDER[i];
            var n = 0, j;
            for (j = 0; j < base.length; j++) {
                if (base[j].cat === c) {
                    n++;
                }
            }
            if (n > 0) {
                defs.push({k: c, l: CATS[c] || c, n: n});
            }
        }

        var types = [];
        for (i = 0; i < all.length; i++) {
            if (types.indexOf(all[i].ptype) === -1) {
                types.push(all[i].ptype);
            }
        }
        types.sort(function (a, b) {
            var la = PTYPES[a] || a, lb = PTYPES[b] || b;
            return la < lb ? -1 : (la > lb ? 1 : 0);
        });

        var statusOpts = statusOptsFor();
        var controls = '<div class="ainav2-typewrap"><label for="ainav2-pstatesel">Status</label><select class="ainav2-sortsel" id="ainav2-pstatesel">';
        for (i = 0; i < statusOpts.length; i++) {
            controls += '<option value="' + esc(statusOpts[i][0]) + '"' + (statusOpts[i][0] === pstate ? ' selected' : '') + '>' +
                esc(statusOpts[i][1]) + '</option>';
        }
        controls += '</select></div>';
        controls += '<div class="ainav2-typewrap"><label for="ainav2-ptypesel">Type</label><select class="ainav2-sortsel" id="ainav2-ptypesel"><option value="all">All types</option>';
        for (i = 0; i < types.length; i++) {
            controls += '<option value="' + esc(types[i]) + '"' + (types[i] === ptype ? ' selected' : '') + '>' +
                esc(PTYPES[types[i]] || types[i]) + '</option>';
        }
        controls += '</select></div>';

        var dirty = filt !== 'all' || ptype !== 'all' || pstate !== 'all' || pq !== '';

        var toolbarHtml = chipsHTML(defs);
        toolbarHtml += '<div class="ainav2-subbar">' +
            '<div class="ainav2-psearchwrap">' +
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
            '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>' +
            '<input type="search" id="ainav2-psearch" class="ainav2-psearch" autocomplete="off" ' +
            'placeholder="' + esc(searchPlaceholder()) + '" aria-label="' + esc(searchPlaceholder()) + '" ' +
            'value="' + esc(pq) + '">' +
            (pq ? '<button class="ainav2-pclear" type="button" data-pclear="1" aria-label="Clear search">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                '<path d="M18 6 6 18M6 6l12 12"/></svg></button>' : '') +
            '</div>' + controls +
            '<select class="ainav2-sortsel" id="ainav2-sortsel" aria-label="Sort">' +
            '<option value="az">A–Z</option><option value="fav">Favourites first</option></select>' +
            '<div class="ainav2-resn" id="ainav2-resn"></div>' +
            (dirty ? '<button class="ainav2-clearf" type="button" data-clear="1">Clear filters</button>' : '') +
            '<button class="ainav2-savebtn' + (isSaved() ? ' ainav2-saved' : '') + '" type="button" data-savelayout="1" data-help="savelayout">' +
            (isSaved() ? 'Layout saved' : 'Save layout') + '</button>' +
            (current === 'plugins' ? '<button class="ainav2-bulk" type="button" data-updateall="1">Update all</button>' : '') +
            '</div>';
        // renderPanel rewrites the whole toolbar, which would drop focus mid-typing.
        // Remember where the caret was and put it back.
        var act = document.activeElement;
        var hadfocus = !!(act && act.id === 'ainav2-psearch');
        var caret = hadfocus ? act.selectionStart : 0;
        els.toolbar.innerHTML = toolbarHtml;
        if (hadfocus) {
            var qi = document.getElementById('ainav2-psearch');
            if (qi) {
                qi.focus();
                try {
                    qi.setSelectionRange(caret, caret);
                } catch (ignore) {
                    // Some browsers refuse setSelectionRange on type=search; harmless.
                }
            }
        }

        var items = [];
        for (i = 0; i < base.length; i++) {
            if (filt === 'all' || (filt === 'fav' ? faves[base[i].name] : base[i].cat === filt)) {
                items.push(base[i]);
            }
        }
        if (sort === 'fav') {
            items.sort(function (a, b) {
                return (faves[b.name] ? 1 : 0) - (faves[a.name] ? 1 : 0);
            });
        }

        var open = dirty;
        var body = '';
        for (i = 0; i < CATORDER.length; i++) {
            var cat = CATORDER[i];
            var rows = [];
            for (var k = 0; k < items.length; k++) {
                if (items[k].cat === cat) {
                    rows.push(items[k]);
                }
            }
            if (!rows.length) {
                continue;
            }
            var rowsHtml = '';
            for (k = 0; k < rows.length; k++) {
                rowsHtml += rowHTML(rows[k]);
            }
            body += '<div class="ainav2-grp' + (open ? ' ainav2-open' : '') + '">' +
                '<button class="ainav2-grphead" type="button" aria-expanded="' + open + '">' + CHEV +
                '<span class="ainav2-grpname">' + esc(CATS[cat] || cat) + '</span>' +
                '<span class="ainav2-count">' + rows.length + '</span></button>' +
                '<div class="ainav2-grpbody">' + rowsHtml + '</div></div>';
        }

        els.plist.innerHTML = (body || emptyHTML(pq ?
            'Nothing here matches \u201c' + pq + '\u201d.' : 'Nothing matches these filters.')) +
            (current === 'manage' ? maintenanceHTML() : '') +
            (current === 'reports' ? customBlockHTML('report') : '') +
            (current === 'manage' ? customBlockHTML('link') : '');
        els.pcount.textContent = items.length;
        var resn = document.getElementById('ainav2-resn');
        if (resn) {
            resn.textContent = items.length + ' of ' + all.length;
        }
    }

    /**
     * Re-render the panel while preserving (or explicitly setting) which
     * accordion groups are open.
     *
     * @param {Array} [keepOpen]
     */
    function paint(keepOpen) {
        if (!current) {
            return;
        }
        var keep = keepOpen === undefined ? openGroupNames() : keepOpen;
        renderPanel();
        reopen(keep);
        var sel = document.getElementById('ainav2-sortsel');
        if (sel) {
            sel.value = sort;
        }
    }

    /**
     * Open a panel by id.
     *
     * @param {string} id
     */
    function openPanel(id) {
        current = id;
        var reopenNames = applyLayout(id);
        var labels = {plugins: 'Plugins', settings: 'Settings', manage: 'Manage', reports: 'Reports'};
        els.ptitle.textContent = labels[id] || id;
        paint([]);
        reopen(reopenNames);
        els.home.classList.add('ainav2-hide');
        els.panel.classList.add('ainav2-show');
        els.plist.scrollTop = 0;
    }

    /**
     * Return to the home view.
     */
    function goHome() {
        current = null;
        els.panel.classList.remove('ainav2-show');
        els.home.classList.remove('ainav2-hide');
        els.toolbar.innerHTML = '';
    }

    /* ------------------------------------------------------------------ *
     * Saved layouts.
     * ------------------------------------------------------------------ */

    /**
     * @return {Object}
     */
    function currentLayout() {
        return {filt: filt, sort: sort, ptype: ptype, pstate: pstate, open: openGroupNames()};
    }

    /**
     * @return {boolean}
     */
    function isSaved() {
        var l = layouts[current];
        if (!l) {
            return false;
        }
        return l.filt === filt && l.sort === sort && l.ptype === ptype && l.pstate === pstate;
    }

    /**
     * Apply a saved layout (or defaults) when opening a panel.
     *
     * @param {string} id
     * @return {?Array}
     */
    function applyLayout(id) {
        // A search term is never part of a saved layout — every panel opens clean.
        pq = '';
        var l = layouts[id];
        if (!l) {
            filt = 'all';
            sort = 'az';
            ptype = 'all';
            pstate = 'all';
            return null;
        }
        filt = l.filt || 'all';
        sort = l.sort || 'az';
        ptype = l.ptype || 'all';
        pstate = l.pstate || 'all';
        return l.open || null;
    }

    /* ------------------------------------------------------------------ *
     * Manage panel extras: cache maintenance + custom links/reports.
     * ------------------------------------------------------------------ */

    /**
     * @return {string}
     */
    function maintenanceHTML() {
        var tag = schedule.on
            ? '<span class="ainav2-schedtag">● ' + esc(schedule.freq) +
                (schedule.freq === 'weekly' ? ' · ' + esc(schedule.day) : '') + ' · ' + esc(schedule.time) + '</span>'
            : '<span class="ainav2-schedtag ainav2-off">○ No schedule</span>';
        return '<div class="ainav2-tool"><div class="ainav2-toolrow">' +
            '<div class="ainav2-tico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">' +
            '<path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v5h-5"/></svg></div>' +
            '<div class="ainav2-tbody"><div class="ainav2-ttitle">Cache maintenance</div>' +
            '<div class="ainav2-tmeta">Manual ' + esc(schedule.lastmanual || 'never') + ' · Auto ' + esc(schedule.lastauto || 'never') + '</div></div>' +
            tag + '</div><div class="ainav2-toolbtns">' +
            '<button class="ainav2-btn ainav2-primary" type="button" data-purge="1">Purge caches now</button>' +
            '<button class="ainav2-btn" type="button" data-sched="1">Schedule…</button></div></div>';
    }

    /**
     * @param {string} kind 'link' | 'report'
     * @return {string}
     */
    function customBlockHTML(kind) {
        var list = kind === 'link' ? customLinks : customReports;
        var label = kind === 'link' ? 'Your quick links' : 'Your reports';
        var i, c, rows = '';
        for (i = 0; i < list.length; i++) {
            c = list[i];
            rows += '<a class="ainav2-row ainav2-custom" href="' + esc(c.url) + '" target="_blank" rel="noopener" data-name="' + esc(c.name) + '">' +
                '<span class="ainav2-star" style="color:var(--accent)">' + svgIcon(c.icon || 'link') + '</span>' +
                '<span class="ainav2-nm">' + esc(c.name) + '<span class="ainav2-desc">' + esc(c.url) + '</span></span>' +
                '<span class="ainav2-tag">custom</span>' +
                '<button class="ainav2-del ainav2-inline" type="button" data-delcustom="' + esc(c.id) + '" data-kind="' + kind +
                '" aria-label="Remove ' + esc(c.name) + '">×</button></a>';
        }
        return '<div class="ainav2-srchead">' + esc(label) + ' · ' + list.length + '</div>' +
            '<div class="ainav2-rows">' + rows + '</div>' +
            '<button class="ainav2-addbtn" type="button" data-add="' + kind + '">+ Add ' + (kind === 'link' ? 'quick link' : 'report') + '</button>';
    }

    /* ------------------------------------------------------------------ *
     * Global search.
     * ------------------------------------------------------------------ */

    /**
     * Run the global search across all four datasets, core links and
     * products, grouped by source.
     *
     * @param {string} t
     */
    function search(t) {
        t = t.replace(/^\s+|\s+$/g, '').toLowerCase();
        els.clearq.hidden = !t;
        if (!t) {
            if (current) {
                paint();
            } else {
                goHome();
            }
            return;
        }
        current = null;
        els.toolbar.innerHTML = '';

        function matchName(name) {
            return name.toLowerCase().indexOf(t) !== -1;
        }

        var buckets = [];
        var i, rows;

        rows = [];
        var settings = DATA.settings || [], j;
        for (j = 0; j < settings.length; j++) {
            if (matchName(settings[j].name)) {
                rows.push(rowHTML({name: settings[j].name, cat: settings[j].cat, ptype: settings[j].ptype || 'local',
                    desc: settings[j].desc, docs: settings[j].docs, url: settings[j].url, configured: !!settings[j].configured, kind: 'link'}));
            }
        }
        if (rows.length) {
            buckets.push(['Settings', rows]);
        }

        rows = [];
        var manage = DATA.manage || [];
        for (j = 0; j < manage.length; j++) {
            if (matchName(manage[j].name)) {
                rows.push(rowHTML({name: manage[j].name, cat: manage[j].cat, ptype: manage[j].ptype || 'local',
                    desc: manage[j].desc, docs: manage[j].docs, url: manage[j].url, kind: 'link'}));
            }
        }
        if (rows.length) {
            buckets.push(['Manage', rows]);
        }

        rows = [];
        var reports = DATA.reports || [];
        for (j = 0; j < reports.length; j++) {
            if (matchName(reports[j].name)) {
                rows.push(rowHTML({name: reports[j].name, cat: reports[j].cat, ptype: reports[j].ptype || 'local',
                    desc: reports[j].desc, docs: reports[j].docs, url: reports[j].url, live: !!reports[j].live, kind: 'report'}));
            }
        }
        if (rows.length) {
            buckets.push(['Reports', rows]);
        }

        rows = [];
        var plugins = DATA.plugins || [];
        for (j = 0; j < plugins.length; j++) {
            if (matchName(plugins[j].name)) {
                rows.push(rowHTML({name: plugins[j].name, cat: plugins[j].cat, ptype: plugins[j].ptype || 'local',
                    desc: plugins[j].desc, docs: plugins[j].docs, item: plugins[j], kind: 'plugin'}));
            }
        }
        if (rows.length) {
            buckets.push(['Plugins', rows]);
        }

        rows = [];
        var core = DATA.core || [];
        for (j = 0; j < core.length; j++) {
            if (matchName(core[j].name)) {
                var href = /^https?:\/\//.test(core[j].url || '') ? core[j].url : (DATA.wwwroot || '') + core[j].url;
                rows.push('<a class="ainav2-row ainav2-famrow" href="' + esc(href) + '" data-name="' + esc(core[j].name) + '">' +
                    '<span class="ainav2-nm">' + esc(core[j].name) + '</span>' +
                    '<span class="ainav2-ptype" data-t="local">Moodle</span></a>');
            }
        }
        if (rows.length) {
            buckets.push(['Moodle', rows]);
        }

        rows = [];
        var products = DATA.products || [];
        for (j = 0; j < products.length; j++) {
            if (matchName(products[j].name + ' ' + (products[j].desc || ''))) {
                rows.push('<a class="ainav2-row ainav2-famrow" href="' + esc(products[j].url) + '" target="_blank" rel="noopener" data-name="' +
                    esc(products[j].name) + '"><span class="ainav2-nm">' + esc(products[j].name) + '</span>' +
                    '<span class="ainav2-ptype" data-t="local">Product</span></a>');
            }
        }
        if (rows.length) {
            buckets.push(['Our products', rows]);
        }

        var total = 0;
        for (i = 0; i < buckets.length; i++) {
            total += buckets[i][1].length;
        }
        els.ptitle.textContent = 'Results';
        els.pcount.textContent = total;

        if (!total) {
            els.plist.innerHTML = emptyHTML('Nothing matches "' + t + '".');
        } else {
            var out = '';
            for (i = 0; i < buckets.length; i++) {
                out += '<div class="ainav2-srchead">' + esc(buckets[i][0]) + ' · ' + buckets[i][1].length + '</div>' +
                    '<div class="ainav2-rows">' + buckets[i][1].join('') + '</div>';
            }
            els.plist.innerHTML = out;
        }
        els.home.classList.add('ainav2-hide');
        els.panel.classList.add('ainav2-show');
        els.plist.scrollTop = 0;
    }

    /* ------------------------------------------------------------------ *
     * Hover / focus help cards.
     * ------------------------------------------------------------------ */

    /**
     * @param {string} key
     * @param {Element} el
     */
    function showHelp(key, el) {
        var h = HELP[key];
        if (!h || !helpOn) {
            return;
        }
        var body = '<h4>' + esc(h.t) + '<span class="ainav2-hb">' + esc(h.b) + '</span></h4>';
        var i;
        if (h.p) {
            for (i = 0; i < h.p.length; i++) {
                body += '<p>' + esc(h.p[i]) + '</p>';
            }
        }
        if (h.l && h.l.length) {
            body += '<ul>';
            for (i = 0; i < h.l.length; i++) {
                body += '<li>' + esc(h.l[i]) + '</li>';
            }
            body += '</ul>';
        }
        if (h.tip) {
            body += '<div class="ainav2-protip"><div class="ainav2-pico">' +
                '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 18h6"/><path d="M10 21h4"/>' +
                '<path d="M12 3a6 6 0 0 1 4 10.5V16H8v-2.5A6 6 0 0 1 12 3z"/></svg></div>' +
                '<div class="ainav2-ptxt"><span class="ainav2-plabel">Pro tip</span>' + esc(h.tip) + '</div></div>';
        }
        els.helpcard.innerHTML = body;
        els.helpcard.setAttribute('aria-hidden', 'false');
        els.helpcard.classList.add('ainav2-show');
        el.setAttribute('aria-expanded', 'true');

        var r = el.getBoundingClientRect();
        var w = els.helpcard.offsetWidth;
        var hh = els.helpcard.offsetHeight;
        var left = r.left;
        var top = r.bottom + 9;
        if (left + w > window.innerWidth - 10) {
            left = window.innerWidth - w - 10;
        }
        if (left < 10) {
            left = 10;
        }
        if (top + hh > window.innerHeight - 10) {
            top = Math.max(10, r.top - hh - 9);
        }
        els.helpcard.style.left = left + 'px';
        els.helpcard.style.top = top + 'px';
        els.helpcard.setAttribute('data-owner-set', '1');
    }

    var helpOwner = null;

    function hideHelp() {
        els.helpcard.classList.remove('ainav2-show');
        els.helpcard.setAttribute('aria-hidden', 'true');
        if (helpOwner) {
            helpOwner.setAttribute('aria-expanded', 'false');
            helpOwner = null;
        }
    }

    /**
     * Locate the nearest ancestor (or self) carrying data-help/data-rowhelp.
     *
     * @param {Element} el
     * @param {string} attr
     * @return {?Element}
     */
    function closestWithAttr(el, attr) {
        while (el && el.nodeType === 1) {
            if (el.hasAttribute && el.hasAttribute(attr)) {
                return el;
            }
            el = el.parentNode;
        }
        return null;
    }

    /* ------------------------------------------------------------------ *
     * Row description tooltip.
     * ------------------------------------------------------------------ */

    /**
     * @param {Element} el
     */
    function showRowTip(el) {
        var txt = el.getAttribute('data-rowhelp');
        if (!txt || !helpOn) {
            return;
        }
        var cost = '';
        if (current === 'plugins') {
            var name = el.getAttribute('data-name');
            var plugins = DATA.plugins || [];
            var i;
            for (i = 0; i < plugins.length; i++) {
                if (plugins[i].name === name) {
                    cost = plugCostLabel(plugins[i]);
                    break;
                }
            }
        }
        els.rowtip.innerHTML = esc(txt) + (cost ? '<span class="ainav2-tipcost">' + esc(cost) + '</span>' : '');
        els.rowtip.classList.add('ainav2-show');
        var r = el.getBoundingClientRect();
        var w = els.rowtip.offsetWidth;
        var h = els.rowtip.offsetHeight;
        var left = r.left + 8;
        var top = r.top - h - 9;
        if (left + w > window.innerWidth - 10) {
            left = window.innerWidth - w - 10;
        }
        if (left < 10) {
            left = 10;
        }
        if (top < 8) {
            top = r.bottom + 9;
        }
        els.rowtip.style.left = left + 'px';
        els.rowtip.style.top = top + 'px';
        els.rowtip.style.setProperty('--ax', Math.max(10, Math.min(w - 20, r.left + 18 - left)) + 'px');
    }

    function hideRowTip() {
        els.rowtip.classList.remove('ainav2-show');
    }

    /* ------------------------------------------------------------------ *
     * Modals: icon-picker builder, purge schedule, credit unlock.
     * ------------------------------------------------------------------ */

    function openModal(title, body, foot) {
        els.mtitle.textContent = title;
        els.mbody.innerHTML = body;
        els.mfoot.innerHTML = foot;
        els.ov.classList.add('ainav2-show');
    }

    function closeModal() {
        els.ov.classList.remove('ainav2-show');
    }

    /**
     * @param {string} filterText
     * @return {string}
     */
    function iconTiles(filterText) {
        var list = filterText ? ICONKEYS.filter(function (k) {
            return k.indexOf(filterText.toLowerCase()) !== -1;
        }) : ICONKEYS;
        if (!list.length) {
            return '<div class="ainav2-hint" style="padding:8px">No icon matches.</div>';
        }
        var i, out = '';
        for (i = 0; i < list.length; i++) {
            out += '<button class="ainav2-ibtn" type="button" data-icon="' + list[i] + '" ' +
                'aria-pressed="' + (list[i] === pickIcon) + '" title="' + list[i] + '" aria-label="' + list[i] + '">' +
                svgIcon(list[i]) + '</button>';
        }
        return out;
    }

    function iconGrid() {
        return '<div class="ainav2-fld"><label>Icon · ' + ICONKEYS.length + ' available</label>' +
            '<input class="ainav2-iconsearch" id="ainav2-iconsearch" placeholder="Filter icons…" autocomplete="off">' +
            '<div class="ainav2-icons" id="ainav2-icons">' + iconTiles('') + '</div></div>';
    }

    /**
     * @param {string} kind 'link' | 'report'
     */
    function builderModal(kind) {
        pickIcon = 'link';
        var title = kind === 'link' ? 'New quick link' : 'New report link';
        var body = '<div class="ainav2-fld"><label>Name</label><input id="ainav2-bname" placeholder="' +
            (kind === 'link' ? 'Vendor portal' : 'Weekly enrolments') + '"></div>' +
            '<div class="ainav2-fld"><label>URL</label><input id="ainav2-burl" placeholder="https://">' +
            '<div class="ainav2-hint">Relative Moodle paths work too, e.g. /admin/settings.php?section=…</div></div>' +
            iconGrid();
        var foot = '<button class="ainav2-btn" type="button" data-close="1">Cancel</button>' +
            '<button class="ainav2-btn ainav2-primary" type="button" data-save="' + kind + '">Add ' +
            (kind === 'link' ? 'link' : 'report') + '</button>';
        openModal(title, body, foot);
    }

    var WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    function scheduleModal() {
        var body = '<div class="ainav2-toggle"><input type="checkbox" id="ainav2-schon"' + (schedule.on ? ' checked' : '') +
            '><span>Purge caches automatically</span></div>' +
            '<div class="ainav2-fld"><label>Frequency</label><select id="ainav2-schfreq">' +
            ['daily', 'weekly', 'monthly'].map(function (f) {
                return '<option' + (schedule.freq === f ? ' selected' : '') + '>' + f + '</option>';
            }).join('') + '</select></div>' +
            '<div class="ainav2-fld"><label>Day</label><select id="ainav2-schday">' +
            WEEKDAYS.map(function (d) {
                return '<option' + (d === schedule.day ? ' selected' : '') + '>' + d + '</option>';
            }).join('') + '</select></div>' +
            '<div class="ainav2-fld"><label>Time</label><input id="ainav2-schtime" type="time" value="' + esc(schedule.time) + '">' +
            '<div class="ainav2-hint">Runs via Moodle cron. Pick a quiet hour — purging clears all caches site-wide.</div></div>';
        var foot = '<button class="ainav2-btn" type="button" data-close="1">Cancel</button>' +
            '<button class="ainav2-btn ainav2-primary" type="button" data-savesched="1">Save schedule</button>';
        openModal('Purge cache schedule', body, foot);
    }

    /**
     * @param {string} pluginid
     * @param {string} name
     * @param {number} cost
     */
    /**
     * Wording for a plugin that cost no credits.
     *
     * The API is the only thing that knows why a site is already entitled to a plugin —
     * a Moodle Marketplace purchase, or an earlier unlock on this site. When it tells us
     * (entitlementSource), we say so. When it does not, we state only what is certain:
     * no credits were used.
     *
     * @param {string} source Entitlement source reported by the API, may be empty.
     * @return {string}
     */
    function freeReason(source) {
        var s = String(source || '').toLowerCase();
        if (s.indexOf('marketplace') !== -1) {
            return 'Moodle Marketplace';
        }
        if (s.indexOf('bundle') !== -1) {
            return 'Included in your bundle';
        }
        if (s === 'free') {
            return 'Free plugin';
        }
        return 'No credits used';
    }

    /**
     * Persist the recent-spend log.
     */
    function saveSpend() {
        try {
            setPref(PREF_SPEND, JSON.stringify(spend));
        } catch (e) {
            // Ignore.
        }
    }

    /**
     * Record one credit deduction so it survives the post-install page reload.
     *
     * @param {string} name Plugin name.
     * @param {number} credits Credits deducted.
     */
    function recordSpend(name, credits, source) {
        spend.push({n: name, c: credits || 0, s: source || '',
            t: Math.floor(new Date().getTime() / 1000)});
        while (spend.length > 10) {
            spend.shift();
        }
        saveSpend();
    }

    /**
     * Render the "credits deducted" receipt above the nav cards. It lists every plugin
     * unlocked recently and what each one cost, so the deduction is accounted for after
     * the page reloads rather than only being visible in the moment.
     */
    function renderSpend() {
        if (!els.spend) {
            return;
        }
        if (!spend.length) {
            els.spend.hidden = true;
            els.spend.innerHTML = '';
            return;
        }
        var total = 0, charged = 0, i, rows = '';
        for (i = 0; i < spend.length; i++) {
            var c = parseInt(spend[i].c, 10) || 0;
            var cell;
            if (c > 0) {
                total += c;
                charged++;
                cell = '<span class="ainav2-spc">' + fmtNum(c) + '</span>';
            } else {
                cell = '<span class="ainav2-spc ainav2-spfree">' + esc(freeReason(spend[i].s)) + '</span>';
            }
            rows += '<li><span class="ainav2-spn">' + esc(spend[i].n) + '</span>' + cell + '</li>';
        }

        var word = spend.length === 1 ? 'plugin' : 'plugins';
        var title;
        if (total === 0) {
            title = spend.length + ' ' + word + ' installed — no credits used';
        } else if (charged === spend.length) {
            title = fmtNum(total) + ' credits deducted for ' + spend.length + ' ' + word;
        } else {
            title = fmtNum(total) + ' credits deducted — ' + (spend.length - charged) +
                ' of ' + spend.length + ' cost nothing';
        }

        els.spend.innerHTML =
            '<div class="ainav2-sphead">' +
            '<div class="ainav2-sptitle">' + title + '</div>' +
            '<button class="ainav2-spx" type="button" data-spendclear="1">Dismiss</button></div>' +
            '<ul class="ainav2-splist">' + rows + '</ul>' +
            '<div class="ainav2-spfoot">Each unlock is one-time — updates and re-downloads are free.' +
            (creditUnlimited || total === 0 ? '' : ' Balance now ' + fmtNum(creditBalance) + '.') + '</div>';
        els.spend.hidden = false;
    }

    function unlockModal(pluginid, component, name, cost) {
        var bal = creditBalance;
        var short = !creditUnlimited && bal < cost;
        var body = '<div class="ainav2-costline"><div class="ainav2-big">' + fmtNum(cost) + '</div>' +
            '<div class="ainav2-small">credits, deducted once.<br>Every update and re-download after that is free.</div></div>';

        if (creditUnlimited) {
            body += '<div class="ainav2-balline">Your account has unlimited credits, so nothing ' +
                'will be deducted. ' + esc(name) + ' will be downloaded and installed on your site.</div>';
        } else if (short) {
            body += '<div class="ainav2-balline">You have ' + fmtNum(bal) + ' credits — ' +
                fmtNum(cost - bal) + ' short of the ' + fmtNum(cost) + ' this unlock costs.</div>' +
                '<div class="ainav2-hint" style="color:var(--warn)">Top up your balance to unlock ' +
                esc(name) + '.</div>';
        } else {
            body += '<div class="ainav2-balline">Confirming will deduct <b>' + fmtNum(cost) +
                ' credits</b> from your balance, taking it from ' + fmtNum(bal) + ' to ' +
                fmtNum(bal - cost) + '. ' + esc(name) + ' is then downloaded and installed ' +
                'on your site.</div>';
        }

        var foot = '<button class="ainav2-btn" type="button" data-close="1">Cancel</button>' +
            (short ? '<button class="ainav2-btn ainav2-primary" type="button" data-topup="1">Top up credits</button>'
                : '<button class="ainav2-btn ainav2-primary" type="button" data-unlock="' + cost + '" data-plug="' + esc(pluginid) +
                '" data-comp="' + esc(component) + '" data-plugname="' + esc(name) + '">' +
                (creditUnlimited ? 'Unlock &amp; install' : 'Deduct ' + fmtNum(cost) + ' credits &amp; install') +
                '</button>');
        openModal('Unlock ' + name, body, foot);
    }

    /**
     * Confirm a free install. No credits change hands, but the plugin is still being
     * installed on a live site, so it is confirmed rather than fired on a single click.
     *
     * @param {string} component
     * @param {string} name
     */
    function freeModal(component, name) {
        var body = '<div class="ainav2-costline"><div class="ainav2-big">Free</div>' +
            '<div class="ainav2-small">No credits are deducted for this plugin.</div></div>' +
            '<div class="ainav2-balline">Confirming will download ' + esc(name) +
            ' and install it on your site. Your credit balance is not affected.</div>';
        var foot = '<button class="ainav2-btn" type="button" data-close="1">Cancel</button>' +
            '<button class="ainav2-btn ainav2-primary" type="button" data-freeinstall="1" data-comp="' +
            esc(component) + '" data-plugname="' + esc(name) + '">Install</button>';
        openModal('Install ' + name, body, foot);
    }

    /* ------------------------------------------------------------------ *
     * Web-service actions: install / update / purge / custom links.
     * ------------------------------------------------------------------ */


    /* Download URLs come from the version-check proxy (check_versions.php), the same
       source the legacy block used. Cached for the life of the page. */
    var downloadCache = {};

    /**
     * Resolve a component's download URL and SHA-256, then run a callback.
     *
     * @param {string} component
     * @param {Function} cb Receives (downloadurl, sha256) or (null) on failure.
     */
    /**
     * Map a weekday name to the 0-6 index the service expects.
     *
     * @param {string} d
     * @return {number}
     */
    function dayIndex(d) {
        var days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        var i = days.indexOf(d);
        return i < 0 ? 0 : i;
    }

    /**
     * The install/update web services type expectedsha256 as PARAM_ALPHANUM, so any
     * value that is not a clean hex digest — a "sha256:" prefix, base64 padding, a
     * dash — is rejected by Moodle before the call runs, and the whole update fails
     * with "Invalid parameter value detected". The hash is optional, so anything that
     * does not look like a real digest is dropped rather than sent.
     *
     * @param {string} sha
     * @return {string}
     */
    function safeSha(sha) {
        var s = String(sha || '');
        return /^[a-fA-F0-9]{64}$/.test(s) ? s : '';
    }

    /**
     * Work out which installed plugins have an update waiting.
     *
     * PHP cannot answer this: the only place the block ever computed "update available"
     * was a five-minute config cache written by the OLD plugin-management renderer, which
     * this UI replaced and therefore never runs. With the cache permanently cold the
     * payload reported update=false for every plugin, so the card always read
     * "0 updates ready" no matter how far behind the site was.
     *
     * The check is done here instead, against check_versions.php — the same source the
     * old UI used — comparing Moodle's numeric version (YYYYMMDDXX) rather than the
     * display string. Plugins the server marks as anything other than ready are skipped:
     * a testing build must never join the update queue.
     */
    /**
     * Compare two Moodle numeric versions.
     *
     * These are NOT safely comparable as plain integers. Moodle numerics here come in both
     * the 10-digit (YYYYMMDDXX) and 13-digit (YYYYMMDDXXXXX) schemes, and this plugin family
     * has used both. Compared numerically, a 13-digit 2026072400116 looks larger than a
     * 10-digit 2026083053 even though it is five weeks older, so every update is missed.
     * The date prefix is compared first, then the trailing sequence — the same algorithm the
     * old UI used.
     *
     * @param {string|number} a Installed numeric version.
     * @param {string|number} b Latest numeric version.
     * @return {number} 1 if b is newer than a, -1 if older, 0 if equal or unparseable.
     */
    function cmpVersions(a, b) {
        function parseV(v) {
            var s = String(v === null || typeof v === 'undefined' ? '' : v).replace(/[^0-9]/g, '');
            if (s.length < 8) {
                return null;
            }
            return {d: s.slice(0, 8), seq: parseInt(s.slice(8) || '0', 10)};
        }
        var pa = parseV(a), pb = parseV(b);
        if (!pa || !pb) {
            return 0;
        }
        if (pb.d !== pa.d) {
            return pb.d > pa.d ? 1 : -1;
        }
        if (pb.seq > pa.seq) {
            return 1;
        }
        if (pa.seq > pb.seq) {
            return -1;
        }
        return 0;
    }

    function refreshUpdates(manual) {
        if (!DATA.proxyurl || !DATA.isadmin) {
            return;
        }
        if (checkState === 'checking') {
            return;
        }
        checkState = 'checking';
        if (manual) {
            renderStrip();
        }
        $.ajax({url: DATA.proxyurl, type: 'GET', dataType: 'json', timeout: 20000})
            .fail(function () {
                // A failed check is not the same as "everything is current". Say so rather
                // than leaving a zero on screen that looks like good news.
                checkState = 'failed';
                renderStrip();
                if (manual) {
                    showToast('Could not reach the update server. Try again shortly.');
                }
            })
            .done(function (data) {
                var map = (data && data.plugins) || null;
                if (!map) {
                    checkState = 'failed';
                    renderStrip();
                    if (manual) {
                        showToast('The update server returned nothing usable.');
                    }
                    return;
                }
                var plugins = DATA.plugins || [];
                var changed = false;
                var i;

                // Second line of defence on testing builds. PHP drops the ones the registry
                // knows about, but check_versions.php is the authoritative source: any
                // plugin the server does not call ready is removed here unless this site
                // already has it installed. A testing build must never be offered for
                // install from the block.
                var kept = [];
                for (i = 0; i < plugins.length; i++) {
                    var q = plugins[i];
                    var live = map[q.component];
                    if (!q.installed && live && live.status && live.status !== 'ready') {
                        changed = true;
                        continue;
                    }
                    kept.push(q);
                }
                if (kept.length !== plugins.length) {
                    DATA.plugins = kept;
                    plugins = kept;
                }

                for (i = 0; i < plugins.length; i++) {
                    var p = plugins[i];
                    var latest = map[p.component];
                    if (!p.installed || !latest) {
                        continue;
                    }
                    if (latest.downloadUrl) {
                        downloadCache[p.component] = {
                            url: latest.downloadUrl,
                            sha: safeSha(latest.sha256)
                        };
                    }
                    if (latest.status && latest.status !== 'ready') {
                        continue;
                    }
                    if (cmpVersions(p.versionint, latest.numericVersion) !== 1) {
                        continue;
                    }
                    // An update with no download would fail the moment it was attempted.
                    if (!latest.downloadUrl) {
                        continue;
                    }
                    p.update = true;
                    p.latestversion = latest.version || '';
                    changed = true;
                }
                var n = 0;
                for (i = 0; i < plugins.length; i++) {
                    if (plugins[i].update) {
                        n++;
                    }
                }
                DATA.counts = DATA.counts || {};
                DATA.counts.updates = n;
                checkState = 'done';
                lastCheck = new Date();
                // The Plugins card carries both the available total and the update count,
                // and either may have moved — redraw it as well as the status strip.
                renderCards();
                renderStrip();
                if (current) {
                    paint();
                }
                if (manual) {
                    showToast(n === 0 ? 'All plugins are up to date.' :
                        n + (n === 1 ? ' update available.' : ' updates available.'));
                }
            });
    }

    function withDownload(component, cb) {
        if (downloadCache[component]) {
            cb(downloadCache[component].url, downloadCache[component].sha);
            return;
        }
        if (!DATA.proxyurl) {
            cb(null);
            return;
        }
        $.ajax({url: DATA.proxyurl, type: 'GET', dataType: 'json', timeout: 20000})
            .done(function (data) {
                var p = data && data.plugins && data.plugins[component];
                if (p && p.downloadUrl) {
                    downloadCache[component] = {url: p.downloadUrl, sha: safeSha(p.sha256)};
                    cb(p.downloadUrl, safeSha(p.sha256));
                } else {
                    cb(null);
                }
            })
            .fail(function () {
                cb(null);
            });
    }

    /**
     * Unlock a credit-gated plugin then install it.
     *
     * @param {string} pluginid
     * @param {string} name
     * @param {number} cost
     */
    function unlockAndInstall(pluginid, component, name, cost) {
        Ajax.call([{
            methodname: 'block_aiplugin_nav_plugin_unlock',
            // The component goes too: a Moodle Marketplace purchase is recorded against
            // the full component string, not the short plugin id, so the server needs it
            // to spot a plugin this site has already paid for and skip the deduction.
            args: {pluginid: pluginid, component: component || ''},
            done: function (r) {
                if (!r || !r.success) {
                    Notification.alert('Unlock failed',
                        (r && (r.error || r.message)) || 'Could not unlock this plugin.');
                    return;
                }
                // What the API really returns (confirmed with the LMS Labs server):
                //   new unlock       -> remainingCredits + downloadUrl, no creditsConsumed
                //   already unlocked -> alreadyUnlocked + downloadUrl, no balance at all
                // So the amount deducted is derived from the balance before the call and
                // the balance reported after it, and creditsconsumed is used only if the
                // API ever starts sending it.
                var before = creditUnlimited ? null : creditBalance;
                var rem = r.remainingcredits;
                var after = null;
                if (rem === 'Unlimited' || rem === 'unlimited') {
                    creditUnlimited = true;
                    setCredits(0);
                } else if (rem !== '' && rem !== null && typeof rem !== 'undefined' && !isNaN(parseInt(rem, 10))) {
                    after = parseInt(rem, 10);
                    creditUnlimited = false;
                    setCredits(after);
                }

                var used = parseInt(r.creditsconsumed, 10) || 0;
                if (!used && before !== null && after !== null && before > after) {
                    used = before - after;
                }
                if (!used && !r.alreadyunlocked && !creditUnlimited && after === null) {
                    // Unlock succeeded but the API told us nothing about the balance.
                    // Fall back to the advertised price rather than claiming it was free.
                    used = cost || 0;
                }

                var src = r.source || '';
                recordSpend(name, used, used > 0 ? '' : (src || 'entitled'));
                if (used === 0 && String(src).toLowerCase().indexOf('marketplace') !== -1) {
                    showToast(name + ' is covered by your Moodle Marketplace purchase — ' +
                        'no credits used. Installing…');
                } else if (r.alreadyunlocked) {
                    showToast(name + ' is already licensed to this site — no credits used. Installing…');
                } else if (used > 0) {
                    showToast(fmtNum(used) + ' credits deducted. Installing ' + name + '…');
                } else {
                    showToast('Installing ' + name + '…');
                }

                // The unlock response carries the download URL already; use it rather than
                // making a second round trip to the version-check proxy.
                installComponent(component, name, r.downloadurl || '');
            },
            fail: function (err) {
                Notification.alert('Unlock failed', (err && err.message) || 'An error occurred during unlock.');
            }
        }]);
    }

    /**
     * Download and install one component via the updater service.
     *
     * @param {string} component
     * @param {string} name
     */
    function installComponent(component, name, knownurl) {
        if (knownurl) {
            // The unlock response already carried the download URL; no second lookup.
            runInstall(component, name, knownurl, '');
            return;
        }
        withDownload(component, function (url, sha) {
            if (!url) {
                Notification.alert('Install failed', 'Could not find a download for ' + name + '.');
                return;
            }
            runInstall(component, name, url, sha || '');
        });
    }

    /**
     * Fire the install web service for one resolved download, then finish the job:
     * run the database upgrade if the install asks for one, and reload.
     *
     * @param {string} component
     * @param {string} name
     * @param {string} url
     * @param {string} sha
     */
    function runInstall(component, name, url, sha) {
        Ajax.call([{
            methodname: 'block_aiplugin_nav_auto_install_plugin',
            args: {component: component, downloadurl: url, expectedsha256: safeSha(sha)},
            done: function (installResponse) {
                if (installResponse && installResponse.success) {
                    if (installResponse.needsupgrade) {
                        // The install left the database behind the code. Finish the job
                        // rather than dropping the admin on Moodle's upgrade screen.
                        showToast(name + ' installed. Running the database upgrade…');
                        Ajax.call([{
                            methodname: 'block_aiplugin_nav_run_upgrade',
                            args: {},
                            done: function () {
                                window.location.reload(true);
                            },
                            fail: function () {
                                window.location.reload(true);
                            }
                        }]);
                        return;
                    }
                    showToast(name + ' installed. Reloading…');
                    setTimeout(function () {
                        window.location.reload(true);
                    }, 900);
                } else {
                    Notification.alert('Install failed', (installResponse && installResponse.message) || 'Could not install this plugin.');
                }
            },
            fail: function (err) {
                Notification.alert('Install failed', (err && err.message) || 'An error occurred during install.');
            }
        }]);
    }

    /**
     * Install a free (0-credit) plugin directly.
     *
     * @param {string} pluginid
     * @param {string} name
     */
    function installFree(component, name) {
        recordSpend(name, 0, 'free');
        showToast('Installing ' + name + '…');
        installComponent(component, name);
    }

    /**
     * Update one installed plugin.
     *
     * @param {string} component
     * @param {string} name
     * @param {Function} [onDone]
     */
    function updatePlugin(component, name, onDone) {
        withDownload(component, function (url, sha) {
            if (!url) {
                if (onDone) { onDone(false, {message: 'No download available'}); }
                return;
            }
            Ajax.call([{
                methodname: 'block_aiplugin_nav_auto_update_plugin',
                args: {component: component, downloadurl: url, expectedsha256: safeSha(sha)},
                done: function (response) {
                    if (onDone) { onDone(!!(response && response.success), response); }
                },
                fail: function (err) {
                    if (onDone) { onDone(false, err); }
                }
            }]);
        });
    }

    /**
     * Update every plugin currently flagged with an update, sequentially,
     * then run the DB upgrade.
     */
    function updateAll() {
        var pending = [];
        var plugins = DATA.plugins || [];
        var i;
        for (i = 0; i < plugins.length; i++) {
            if (plugins[i].installed && plugins[i].update) {
                pending.push(plugins[i]);
            }
        }
        if (!pending.length) {
            showToast('No updates pending.');
            return;
        }
        showToast('Updating ' + pending.length + ' plugin(s)…');
        var idx = 0;
        function next() {
            if (idx >= pending.length) {
                Ajax.call([{
                    methodname: 'block_aiplugin_nav_run_upgrade',
                    args: {},
                    done: function () {
                        window.location.reload(true);
                    },
                    fail: function () {
                        window.location.reload(true);
                    }
                }]);
                return;
            }
            var p = pending[idx];
            updatePlugin(p.component || p.pluginid, p.name, function () {
                idx++;
                next();
            });
        }
        next();
    }

    /**
     * Purge all caches immediately.
     */
    function purgeCachesNow() {
        var btn = els.plist.querySelector('[data-purge]');
        if (btn) {
            btn.textContent = 'Purging…';
            btn.disabled = true;
        }
        Ajax.call([{
            methodname: 'block_aiplugin_nav_purge_caches',
            args: {},
            done: function (response) {
                schedule.lastmanual = 'Just now';
                if (current) {
                    paint();
                }
                showToast((response && response.message) || 'Caches purged.');
            },
            fail: function (err) {
                if (current) {
                    paint();
                }
                Notification.alert('Purge failed', (err && err.message) || 'Could not purge caches.');
            }
        }]);
    }

    /**
     * Load the current purge status/schedule from the server.
     */
    function loadPurgeStatus() {
        Ajax.call([{
            methodname: 'block_aiplugin_nav_get_purge_status',
            args: {},
            done: function (response) {
                if (!response) {
                    return;
                }
                schedule.on = !!response.enabled;
                schedule.freq = response.freq || schedule.freq;
                schedule.day = response.day || schedule.day;
                schedule.time = response.time || schedule.time;
                schedule.lastmanual = response.lastmanual || schedule.lastmanual;
                schedule.lastauto = response.lastauto || schedule.lastauto;
                if (current === 'manage') {
                    paint();
                }
            },
            fail: function () {
                // Keep local defaults.
            }
        }]);
    }

    /**
     * Save the purge schedule.
     */
    function savePurgeSchedule() {
        Ajax.call([{
            methodname: 'block_aiplugin_nav_save_purge_schedule',
            args: {enabled: schedule.on ? 1 : 0, schedule_type: schedule.freq,
                schedule_time: schedule.time, schedule_day: dayIndex(schedule.day)},
            done: function () {
                showToast('Purge schedule saved.');
            },
            fail: function (err) {
                Notification.alert('Save failed', (err && err.message) || 'Could not save the purge schedule.');
            }
        }]);
    }

    var customIdSeq = -1;

    /**
     * Save a new custom Moodle-tile link or report link.
     *
     * @param {string} kind 'link' | 'report'
     * @param {string} name
     * @param {string} url
     * @param {string} icon
     */
    function saveCustom(kind, name, url, icon) {
        var methodname = kind === 'link' ? 'block_aiplugin_nav_save_custom_link' : 'block_aiplugin_nav_save_custom_report';
        Ajax.call([{
            methodname: methodname,
            args: {name: name, url: url, icon: icon},
            done: function (response) {
                var id = (response && response.id) ? response.id : customIdSeq--;
                var entry = {id: id, name: name, url: url, icon: icon};
                if (kind === 'link') {
                    customLinks.push(entry);
                    renderCore();
                } else {
                    customReports.push(entry);
                }
                if (current) {
                    paint();
                }
            },
            fail: function (err) {
                Notification.alert('Save failed', (err && err.message) || 'Could not save this link.');
            }
        }]);
    }

    /**
     * Delete a custom link or report. The service takes the item's position in the
     * stored array, so resolve the index before calling it.
     *
     * @param {string} kind
     * @param {*} id
     */
    function deleteCustom(kind, id) {
        var methodname = kind === 'link' ? 'block_aiplugin_nav_delete_custom_link' : 'block_aiplugin_nav_delete_custom_report';
        var list = kind === 'link' ? customLinks : customReports;
        var idx = -1;
        var n;
        for (n = 0; n < list.length; n++) {
            if (String(list[n].id) === String(id)) {
                idx = n;
                break;
            }
        }
        if (idx < 0) {
            return;
        }
        Ajax.call([{
            methodname: methodname,
            args: {index: idx},
            done: function () {
                var i;
                for (i = 0; i < list.length; i++) {
                    if (String(list[i].id) === String(id)) {
                        list.splice(i, 1);
                        break;
                    }
                }
                if (kind === 'link') {
                    renderCore();
                }
                if (current) {
                    paint();
                }
            },
            fail: function (err) {
                Notification.alert('Remove failed', (err && err.message) || 'Could not remove this link.');
            }
        }]);
    }

    /* ------------------------------------------------------------------ *
     * Event wiring.
     * ------------------------------------------------------------------ */

    /**
     * Attach every delegated event listener. Called once from init().
     */
    function wireEvents() {
        els.cards.addEventListener('click', function (e) {
            var c = closestWithAttr(e.target, 'data-id');
            if (c) {
                openPanel(c.getAttribute('data-id'));
            }
        });

        els.back.addEventListener('click', function () {
            els.q.value = '';
            els.clearq.hidden = true;
            goHome();
        });

        els.toolbar.addEventListener('click', function (e) {
            var chip = closestClass(e.target, 'ainav2-chip');
            if (chip) {
                filt = chip.getAttribute('data-f');
                paint();
                return;
            }
            if (closestAttr(e.target, 'data-clear')) {
                filt = 'all';
                ptype = 'all';
                pstate = 'all';
                pq = '';
                paint();
                return;
            }
            if (closestAttr(e.target, 'data-pclear')) {
                pq = '';
                paint();
                var qc = document.getElementById('ainav2-psearch');
                if (qc) {
                    qc.focus();
                }
                return;
            }
            if (closestAttr(e.target, 'data-savelayout')) {
                layouts[current] = currentLayout();
                saveLayouts();
                paint([]);
                reopen(layouts[current].open);
                showToast('Saved as your default ' + els.ptitle.textContent + ' view');
                return;
            }
            if (closestAttr(e.target, 'data-updateall')) {
                updateAll();
            }
        });

        els.toolbar.addEventListener('input', function (e) {
            if (e.target.id === 'ainav2-psearch') {
                pq = String(e.target.value || '').trim().toLowerCase();
                paint();
            }
        });

        els.toolbar.addEventListener('keydown', function (e) {
            if (e.target.id === 'ainav2-psearch' && e.keyCode === 27) {
                e.stopPropagation();
                if (pq) {
                    pq = '';
                    paint();
                    var qk = document.getElementById('ainav2-psearch');
                    if (qk) {
                        qk.focus();
                    }
                }
            }
        });

        els.toolbar.addEventListener('change', function (e) {
            if (e.target.id === 'ainav2-sortsel') {
                sort = e.target.value;
                paint();
            } else if (e.target.id === 'ainav2-ptypesel') {
                ptype = e.target.value;
                paint();
            } else if (e.target.id === 'ainav2-pstatesel') {
                pstate = e.target.value;
                paint();
            }
        });

        els.plist.addEventListener('click', function (e) {
            var star = closestClass(e.target, 'ainav2-star');
            if (star && star.hasAttribute('data-fav')) {
                e.preventDefault();
                e.stopPropagation();
                var n = star.getAttribute('data-fav');
                if (faves[n]) {
                    delete faves[n];
                } else {
                    faves[n] = true;
                }
                saveFaves();
                var y = els.plist.scrollTop;
                var open = openGroupNames();
                paint(open);
                els.plist.scrollTop = y;
                return;
            }

            var head = closestClass(e.target, 'ainav2-grphead');
            if (head) {
                var g = head.parentNode;
                var isOpen = g.classList.toggle('ainav2-open');
                head.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                return;
            }

            var goBtn = closestAttr(e.target, 'data-goto');
            if (goBtn) {
                var url = goBtn.getAttribute('data-goto');
                if (url && url !== '#') {
                    window.open(url, '_self');
                }
                return;
            }

            var upd = closestAttr(e.target, 'data-update');
            if (upd) {
                var comp = upd.getAttribute('data-update');
                var pname = upd.getAttribute('data-name');
                upd.disabled = true;
                upd.textContent = 'Updating…';
                updatePlugin(comp, pname, function (ok) {
                    if (ok) {
                        showToast(pname + ' updated. Reloading…');
                        setTimeout(function () {
                            window.location.reload(true);
                        }, 800);
                    } else {
                        upd.disabled = false;
                        upd.textContent = 'Update';
                        Notification.alert('Update failed', 'Could not update ' + pname + '.');
                    }
                });
                return;
            }

            var add = closestAttr(e.target, 'data-add');
            if (add) {
                builderModal(add.getAttribute('data-add'));
                return;
            }
            var sched = closestAttr(e.target, 'data-sched');
            if (sched) {
                scheduleModal();
                return;
            }
            var purge = closestAttr(e.target, 'data-purge');
            if (purge) {
                purgeCachesNow();
                return;
            }
            var delc = closestAttr(e.target, 'data-delcustom');
            if (delc) {
                e.preventDefault();
                deleteCustom(delc.getAttribute('data-kind'), delc.getAttribute('data-delcustom'));
                return;
            }
            var get = closestAttr(e.target, 'data-cost');
            if (get) {
                e.preventDefault();
                var cost = parseInt(get.getAttribute('data-cost'), 10) || 0;
                var plug = get.getAttribute('data-plug');
                var comp = get.getAttribute('data-comp');
                var pn = get.getAttribute('data-plugname');
                if (cost > 0) {
                    unlockModal(plug, comp, pn, cost);
                } else {
                    freeModal(comp, pn);
                }
                return;
            }
            if (closestClass(e.target, 'ainav2-docs')) {
                e.stopPropagation();
                return;
            }
            if (closestAttr(e.target, 'data-goto') || closestClass(e.target, 'ainav2-get') || closestClass(e.target, 'ainav2-rowact')) {
                e.preventDefault();
            }
        });

        els.core.addEventListener('click', function (e) {
            var del = closestAttr(e.target, 'data-del');
            if (del) {
                e.preventDefault();
                deleteCustom('link', del.getAttribute('data-del'));
                return;
            }
            if (closestAttr(e.target, 'data-addtile')) {
                builderModal('link');
                return;
            }
            var a = closestTag(e.target, 'A');
            if (a && a.getAttribute('href') && a.getAttribute('href').indexOf('http') !== 0 && a.getAttribute('href') !== '#') {
                // Relative Moodle link: allow default navigation.
                return;
            }
        });

        els.spend.addEventListener('click', function (e) {
            if (closestAttr(e.target, 'data-spendclear')) {
                spend = [];
                saveSpend();
                renderSpend();
            }
        });

        els.strip.addEventListener('click', function (e) {
            // The support card is a real link (see buildStrip) — let it navigate.
            if (closestAttr(e.target, 'data-recheck')) {
                refreshUpdates(true);
                return;
            }
            if (closestAttr(e.target, 'data-updateall')) {
                updateAll();
            }
        });

        document.addEventListener('mouseover', function (e) {
            var t = closestWithAttr(e.target, 'data-help');
            if (t && root_contains(t)) {
                helpOwner = t;
                showHelp(t.getAttribute('data-help'), t);
            } else if (!closestClass(e.target, 'ainav2-helpcard')) {
                hideHelp();
            }
        });
        document.addEventListener('focusin', function (e) {
            var t = closestWithAttr(e.target, 'data-help');
            if (t && root_contains(t)) {
                helpOwner = t;
                showHelp(t.getAttribute('data-help'), t);
            }
            var rt = closestWithAttr(e.target, 'data-rowhelp');
            if (rt && root_contains(rt)) {
                showRowTip(rt);
            }
        });
        document.addEventListener('focusout', function () {
            hideHelp();
            hideRowTip();
        });
        window.addEventListener('scroll', function () {
            hideHelp();
            hideRowTip();
        }, {passive: true});

        document.addEventListener('mouseover', function (e) {
            var el = closestWithAttr(e.target, 'data-rowhelp');
            clearTimeout(rtTimer);
            if (el && root_contains(el)) {
                var delay = reduceMotion ? 0 : 110;
                rtTimer = setTimeout(function () {
                    showRowTip(el);
                }, delay);
            } else {
                hideRowTip();
            }
        });

        if (isTouch) {
            els.plist.addEventListener('click', function (e) {
                var el = closestWithAttr(e.target, 'data-rowhelp');
                if (el && !closestClass(e.target, 'ainav2-star') && !closestAttr(e.target, 'data-fav')) {
                    showRowTip(el);
                }
            });
        }

        els.q.addEventListener('input', function () {
            search(els.q.value);
        });
        els.q.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                els.q.value = '';
                search('');
            }
        });
        els.clearq.addEventListener('click', function () {
            els.q.value = '';
            search('');
            els.q.focus();
        });

        els.mclose.addEventListener('click', closeModal);
        els.ov.addEventListener('click', function (e) {
            if (e.target === els.ov) {
                closeModal();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && els.ov.classList.contains('ainav2-show')) {
                closeModal();
            }
        });

        els.mfoot.addEventListener('click', function (e) {
            var b = closestTag(e.target, 'BUTTON');
            if (!b) {
                return;
            }
            if (b.getAttribute('data-close')) {
                closeModal();
                return;
            }
            if (b.hasAttribute('data-save')) {
                var kind = b.getAttribute('data-save');
                var nameEl = document.getElementById('ainav2-bname');
                var urlEl = document.getElementById('ainav2-burl');
                var n = (nameEl.value || '').replace(/^\s+|\s+$/g, '') || 'Untitled';
                var u = (urlEl.value || '').replace(/^\s+|\s+$/g, '') || '#';
                saveCustom(kind, n, u, pickIcon);
                closeModal();
                return;
            }
            if (b.hasAttribute('data-savesched')) {
                schedule.on = document.getElementById('ainav2-schon').checked;
                schedule.freq = document.getElementById('ainav2-schfreq').value;
                schedule.day = document.getElementById('ainav2-schday').value;
                schedule.time = document.getElementById('ainav2-schtime').value;
                savePurgeSchedule();
                closeModal();
                paint();
                return;
            }
            if (b.hasAttribute('data-freeinstall')) {
                closeModal();
                installFree(b.getAttribute('data-comp'), b.getAttribute('data-plugname'));
                return;
            }
            if (b.hasAttribute('data-unlock')) {
                var plug = b.getAttribute('data-plug');
                var comp = b.getAttribute('data-comp');
                var pn = b.getAttribute('data-plugname');
                closeModal();
                unlockAndInstall(plug, comp, pn, parseInt(b.getAttribute('data-unlock'), 10) || 0);
                return;
            }
            if (b.hasAttribute('data-topup')) {
                closeModal();
                if (els.ctop) {
                    els.ctop.click();
                }
            }
        });

        els.mbody.addEventListener('input', function (e) {
            if (e.target.id === 'ainav2-iconsearch') {
                document.getElementById('ainav2-icons').innerHTML = iconTiles(e.target.value);
            }
        });
        els.mbody.addEventListener('click', function (e) {
            var i = closestClass(e.target, 'ainav2-ibtn');
            if (!i) {
                return;
            }
            pickIcon = i.getAttribute('data-icon');
            var tiles = els.mbody.querySelectorAll('.ainav2-ibtn');
            var k;
            for (k = 0; k < tiles.length; k++) {
                tiles[k].setAttribute('aria-pressed', tiles[k] === i ? 'true' : 'false');
            }
        });

        if (els.helpon) {
            els.helpon.checked = helpOn;
            els.helpon.addEventListener('change', function () {
                helpOn = els.helpon.checked;
                setPref(PREF_HELP, helpOn ? '1' : '0');
                if (!helpOn) {
                    hideHelp();
                    hideRowTip();
                }
            });
        }

        if (els.ctop) {
            els.ctop.addEventListener('click', function () {
                if (DATA.wwwroot) {
                    window.open(DATA.wwwroot + '/local/lmslabs/credits.php', '_blank');
                }
            });
        }
    }

    /**
     * True if a node is inside our root (helps ignore delegated document
     * listeners firing for unrelated ainav-* legacy markup).
     *
     * @param {Element} node
     * @return {boolean}
     */
    function root_contains(node) {
        return !!(els.root && els.root.contains(node));
    }

    function closestClass(el, cls) {
        while (el && el.nodeType === 1) {
            if (el.classList && el.classList.contains(cls)) {
                return el;
            }
            el = el.parentNode;
        }
        return null;
    }

    function closestAttr(el, attr) {
        while (el && el.nodeType === 1) {
            if (el.hasAttribute && el.hasAttribute(attr)) {
                return el;
            }
            el = el.parentNode;
        }
        return null;
    }

    function closestTag(el, tag) {
        while (el && el.nodeType === 1) {
            if (el.tagName === tag) {
                return el;
            }
            el = el.parentNode;
        }
        return null;
    }

    /* ------------------------------------------------------------------ *
     * Init.
     * ------------------------------------------------------------------ */

    /**
     * Parse the payload, build the UI and wire every behaviour.
     */
    function init() {
        var root = document.getElementById('ainav2-root');
        var script = document.getElementById('ainav2-data');
        if (!root || !script) {
            return;
        }

        var payload;
        try {
            payload = JSON.parse(script.textContent || script.innerHTML || '{}');
        } catch (e) {
            if (window.console && window.console.error) {
                window.console.error('block_aiplugin_nav/ui: failed to parse #ainav2-data payload', e);
            }
            // Leave the server-rendered home view alone.
            return;
        }

        DATA = payload || {};
        CATS = DATA.categories || {};
        CATORDER = DATA.catorder || keysOf(CATS);
        PTYPES = DATA.ptypes || {};
        HELP = DATA.help || {};
        customLinks = (DATA.custom || []).slice();
        customReports = (DATA.customreports || []).slice();

        isTouch = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
        try {
            reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        } catch (e) {
            reduceMotion = false;
        }

        loadPrefsFromPayload();

        els.root = root;
        buildShell();
        renderCore();
        renderCards();
        renderStrip();
        renderSpend();
        renderProducts();
        refreshUpdates(false);

        wireEvents();

        if (DATA.cancredits) {
            setCredits(0);
            loadCredits();
        }

        if (DATA.manage && DATA.manage.length) {
            loadPurgeStatus();
        }
    }

    return {
        init: init
    };
});
