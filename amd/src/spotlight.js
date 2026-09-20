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
 * Plugin spotlight: a rotating hero and a numbered poster row of LMS Labs plugins.
 *
 * Renders from the `spotlight` part of the block payload (see classes/local/spotlight.php).
 * Every user-facing string arrives translated in `data.strings`; placeholders such as {$a}
 * are filled in here. The spotlight never installs or charges anything itself: "Get plugin"
 * hands over to the plugin's own row in the Plugins panel, which runs the usual
 * credit-gated unlock.
 *
 * @module     block_aiplugin_nav/spotlight
 * @copyright  2026 LMS Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @var {number} Milliseconds each plugin stays in the hero. */
const DURATION = 5000;

/** @var {number} Width (px) below which the hero stacks and the preview moves behind the copy. */
const NARROW = 820;

/** @var {number} Width (px) below which the hero drops secondary detail. */
const TINY = 520;

/** @var {Object} Stroke icons (24px grid). Paths only, no text. */
const ICONS = {
    check: 'M20 6 9 17l-5-5',
    down: 'M12 3v12M7 10l5 5 5-5M5 21h14',
    open: 'M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6',
    settings: 'M20 7h-9M14 17H5M17 20a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM7 10a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
    info: 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20zM12 16v-4M12 8h.01',
    docs: 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20V2H6.5A2.5 2.5 0 0 0 4 4.5zM4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5',
    close: 'M18 6 6 18M6 6l12 12',
    pause: 'M7 5h3v14H7zM14 5h3v14h-3z',
    play: 'M7 4l13 8-13 8z',
    up: 'm18 15-6-6-6 6',
    chevdown: 'm6 9 6 6 6-6',
    left: 'm15 18-6-6 6-6',
    right: 'm9 18 6-6-6-6'
};

/**
 * HTML-escape a value for interpolation into markup.
 *
 * @param {*} value
 * @return {string}
 */
function esc(value) {
    if (value === undefined || value === null) {
        return '';
    }
    return String(value).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
}

/**
 * Render one stroke icon.
 *
 * @param {string} name Key in ICONS.
 * @param {number} [width] Stroke width.
 * @return {string} SVG markup.
 */
function icon(name, width) {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="' + (width || 2) +
        '" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="' +
        ICONS[name] + '"/></svg>';
}

/**
 * Format a whole number with thousands separators.
 *
 * @param {number} n
 * @return {string}
 */
function fmt(n) {
    return String(Math.round(Number(n) || 0)).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

/**
 * Convert #rrggbb to an rgba() string.
 *
 * @param {string} hex
 * @param {number} alpha
 * @return {string}
 */
function rgba(hex, alpha) {
    const h = String(hex).replace('#', '');
    const part = i => parseInt(h.substr(i, 2), 16) || 0;
    return 'rgba(' + part(0) + ',' + part(2) + ',' + part(4) + ',' + alpha + ')';
}

/**
 * Inline CSS custom properties carrying one plugin's colours.
 *
 * @param {Object} item Spotlight item.
 * @return {string} A style attribute value.
 */
function colourVars(item) {
    return '--sp-a:' + item.accent + ';--sp-b:' + item.accent2 + ';--sp-a60:' + rgba(item.accent, 0.6) +
        ';--sp-b55:' + rgba(item.accent2, 0.55) + ';--sp-a30:' + rgba(item.accent, 0.3) + ';--sp-a70:' + rgba(item.accent, 0.7);
}

/**
 * Create the spotlight controller.
 *
 * @param {Object} opts
 * @param {Element} opts.mount Where the spotlight is rendered.
 * @param {Element} opts.footer Where the on/off switch is added (may be null).
 * @param {Object} opts.data The `spotlight` payload.
 * @param {Function} opts.setPref Persists a user preference: (name, value).
 * @param {Function} opts.openPlugin Opens the Plugins panel on one plugin: (name).
 * @param {Function} opts.toast Shows a short status message: (text).
 * @return {Object} The controller.
 */
function createSpotlight(opts) {
    const data = opts.data;
    const S = data.strings || {};
    const all = data.items || [];
    const st = {list: all.slice(), cur: 0, filter: 'all', userPaused: false, modalOpen: false, state: data.state || 'open'};
    const el = {};

    const str = (key, a) => {
        let s = S[key] || '';
        if (a !== undefined && a !== null && typeof a === 'object') {
            Object.keys(a).forEach(k => {
                s = s.split('{$a->' + k + '}').join(a[k]);
            });
            return s;
        }
        return s.split('{$a}').join(a === undefined ? '' : a);
    };

    const title = item => (item.subtitle ? '<em>' + esc(item.subtitle) + '</em>' : '') + esc(item.name);

    const openHtml = item => {
        if (!item.gotourl) {
            return '';
        }
        const label = item.action === 'settings' ? 'settings' : 'open';
        return '<button type="button" class="ainav2-sp-btn ainav2-sp-btnp" data-sp-act="open" data-sp-c="' +
            esc(item.component) + '">' + icon(label) + esc(str(label)) + '</button>';
    };

    const ctaHtml = (item, inModal) => {
        const main = item.installed
            ? openHtml(item)
            : '<button type="button" class="ainav2-sp-btn ainav2-sp-btnp" data-sp-act="get" data-sp-c="' + esc(item.component) +
                '">' + icon('down') + esc(str('get')) + '<span class="ainav2-sp-price">' +
                esc(item.credits > 0 ? str('creditsshort', fmt(item.credits)) : '') + '</span></button>';
        if (!inModal) {
            return main + '<button type="button" class="ainav2-sp-btn ainav2-sp-btng" data-sp-act="info" data-sp-c="' +
                esc(item.component) + '">' + icon('info') + esc(str('moreinfo')) + '</button>';
        }
        if (!item.docs) {
            return main;
        }
        return main + '<a class="ainav2-sp-btn ainav2-sp-btng" href="' + esc(item.docs) +
            '" target="_blank" rel="noopener">' + icon('docs') + esc(str('docs')) + '</a>';
    };

    const chips = item => '<div class="ainav2-sp-chips">' +
        (item.installed
            ? '<span class="ainav2-sp-chip ainav2-sp-chipok">' + icon('check', 3) + esc(str('installed')) + '</span>'
            : '') +
        '<span class="ainav2-sp-chip">' + esc(data.categories[item.cat] || '') + '</span>' +
        (item.type ? '<span class="ainav2-sp-chip">' + esc(item.type) + '</span>' : '') +
        '<span class="ainav2-sp-chip" data-sp-ver="' + esc(item.component) + '"' + (item.latest ? '' : ' hidden') + '>' +
        esc(item.latest ? str('version', item.latest) : '') + '</span></div>';

    const features = item => '<ul class="ainav2-sp-feats">' + item.features.map(f =>
        '<li>' + icon('check', 3) + '<span>' + esc(f) + '</span></li>').join('') + '</ul>';

    const shot = item => '<div class="ainav2-sp-shot">' + (item.image
        ? '<img src="' + esc(item.image) + '" alt="' + esc(str('screenshot', item.name)) + '" loading="lazy" decoding="async">'
        : '') + '</div>';

    const priceLabel = (item, key) => {
        if (item.installed) {
            return str(key === 'credits' ? 'onsite' : 'installed');
        }
        return item.credits > 0 ? str('credits', fmt(item.credits)) : '';
    };

    const rankLine = item => '<div class="ainav2-sp-rank"><span class="ainav2-sp-n">' + esc(str('rank', item.rank)) +
        '</span><span>' + esc(str('position', {total: all.length, category: data.categories[item.cat] || ''})) + '</span></div>';

    return {S, st, el, all, data, opts, str, title, ctaHtml, chips, features, shot, rankLine, priceLabel};
}

/**
 * Markup for the hero copy of one plugin.
 *
 * @param {Object} sp Controller.
 * @param {Object} item
 * @return {string}
 */
function copyHtml(sp, item) {
    return sp.rankLine(item) +
        '<h3 class="ainav2-sp-title">' + sp.title(item) + '</h3>' +
        sp.chips(item) +
        '<p class="ainav2-sp-desc">' + esc(item.desc) + '</p>' +
        sp.features(item) +
        '<div class="ainav2-sp-cta">' + sp.ctaHtml(item, false) +
        (item.usage ? '<span class="ainav2-sp-usage">' + esc(sp.str('usageprefix', item.usage)) + '</span>' : '') + '</div>';
}

/**
 * Markup for the floating preview of one plugin.
 *
 * @param {Object} sp Controller.
 * @param {Object} item
 * @return {string}
 */
function shotHtml(sp, item) {
    return '<div class="ainav2-sp-tilt"><div class="ainav2-sp-float">' + sp.shot(item) +
        (item.statvalue ? '<div class="ainav2-sp-stat"><b>' + esc(item.statvalue) + '</b><span>' + esc(item.statlabel) +
            '</span></div>' : '') + '</div></div>';
}

/**
 * Swap an element into a slot with an enter transition, retiring the previous one.
 *
 * @param {Element} slot
 * @param {Element} node
 * @param {?Element} old
 */
function swapIn(slot, node, old) {
    slot.appendChild(node);
    window.requestAnimationFrame(() => {
        window.requestAnimationFrame(() => node.classList.add('ainav2-sp-in'));
    });
    if (old) {
        old.classList.remove('ainav2-sp-in');
        old.classList.add('ainav2-sp-out');
        window.setTimeout(() => {
            if (old.parentNode) {
                old.parentNode.removeChild(old);
            }
        }, 700);
    }
}

/**
 * Restart a CSS animation on an element.
 *
 * @param {Element} node
 */
function restartAnimation(node) {
    node.style.animation = 'none';
    // Reading offsetWidth forces a reflow so the animation starts again from zero.
    node.getBoundingClientRect();
    node.style.animation = '';
}

/**
 * Show one plugin in the hero.
 *
 * @param {Object} sp Controller.
 * @param {number} index Index in the current (filtered) list.
 */
function render(sp, index) {
    const n = sp.st.list.length;
    if (!n) {
        return;
    }
    sp.st.cur = ((index % n) + n) % n;
    const item = sp.st.list[sp.st.cur];
    const vars = colourVars(item);
    sp.el.wrap.setAttribute('style', vars);

    const bg = document.createElement('div');
    bg.className = 'ainav2-sp-bg';
    bg.setAttribute('style', vars);
    swapIn(sp.el.bgs, bg, sp.el.curBg);
    sp.el.curBg = bg;

    const copy = document.createElement('div');
    copy.className = 'ainav2-sp-copy';
    copy.setAttribute('style', vars);
    copy.innerHTML = copyHtml(sp, item);
    swapIn(sp.el.copySlot, copy, sp.el.curCopy);
    sp.el.curCopy = copy;

    const wrap = document.createElement('div');
    wrap.className = 'ainav2-sp-shotwrap';
    wrap.setAttribute('style', vars);
    wrap.innerHTML = shotHtml(sp, item);
    swapIn(sp.el.shotSlot, wrap, sp.el.curShot);
    sp.el.curShot = wrap;

    syncProgress(sp);
    syncPosters(sp, item);
    sp.el.barNow.textContent = sp.str('nowshowing', sp.str('rank', item.rank) + ' ' + item.name);
}

/**
 * Update the progress segments and counter for the current slide.
 *
 * @param {Object} sp Controller.
 */
function syncProgress(sp) {
    const segs = sp.el.prog.children;
    for (let i = 0; i < segs.length; i++) {
        let cls = '';
        if (i < sp.st.cur) {
            cls = 'ainav2-sp-done';
        } else if (i === sp.st.cur) {
            cls = 'ainav2-sp-cur';
        }
        segs[i].className = cls;
        segs[i].setAttribute('aria-selected', i === sp.st.cur ? 'true' : 'false');
        restartAnimation(segs[i].firstChild);
    }
    const pad = v => (v < 10 ? '0' : '') + v;
    sp.el.count.innerHTML = '<b>' + pad(sp.st.cur + 1) + '</b> / ' + pad(sp.st.list.length);
}

/**
 * Highlight the current plugin's poster. The row is never scrolled from here: it scrolls
 * independently of the hero, so auto-rotation cannot move it.
 *
 * @param {Object} sp Controller.
 * @param {Object} item
 */
function syncPosters(sp, item) {
    const posters = sp.el.row.querySelectorAll('.ainav2-sp-poster');
    posters.forEach(p => {
        p.classList.toggle('ainav2-sp-iscur', p.getAttribute('data-sp-c') === item.component);
        restartAnimation(p.querySelector('.ainav2-sp-cardbar'));
    });
}

/**
 * Build the progress segments for the current list.
 *
 * @param {Object} sp Controller.
 */
function buildProgress(sp) {
    sp.el.prog.innerHTML = sp.st.list.map((item, i) => '<button type="button" role="tab" data-sp-seg="' + i +
        '" aria-label="' + esc(item.name) + '"><i></i></button>').join('');
}

/**
 * Build the poster row for the current list.
 *
 * @param {Object} sp Controller.
 */
function buildRow(sp) {
    sp.el.row.innerHTML = sp.st.list.map((item, i) => {
        let flag = '';
        if (item.installed) {
            flag = '<span class="ainav2-sp-flag ainav2-sp-flagok">' + esc(sp.str('installed')) + '</span>';
        } else if (item.rank <= 3) {
            flag = '<span class="ainav2-sp-flag">' + esc(sp.str('featured')) + '</span>';
        }
        const meta = sp.priceLabel(item, 'credits');
        return '<button type="button" class="ainav2-sp-poster" data-sp-c="' + esc(item.component) + '" style="' +
            colourVars(item) + ';animation-delay:' + Math.min(i * 30, 600) + 'ms" aria-label="' +
            esc(sp.str('rank', item.rank) + ' ' + item.name) + '">' +
            '<span class="ainav2-sp-num' + (item.rank > 9 ? ' ainav2-sp-two' : '') + '" aria-hidden="true">' + item.rank +
            '</span><span class="ainav2-sp-card"><span class="ainav2-sp-cardart"></span>' +
            (item.image
                ? '<img class="ainav2-sp-cardimg" src="' + esc(item.image) + '" alt="" loading="lazy" decoding="async">'
                : '') +
            flag + '<span class="ainav2-sp-cardfoot"><span class="ainav2-sp-cardtitle">' + esc(item.name) + '</span>' +
            '<span class="ainav2-sp-cardmeta">' + esc(meta) + '</span></span>' +
            '<span class="ainav2-sp-cardbar"></span></span></button>';
    }).join('');
}

/**
 * Build the category filter chips.
 *
 * @param {Object} sp Controller.
 */
function buildFilters(sp) {
    const keys = ['all'].concat(Object.keys(sp.data.categories).filter(k => sp.all.some(i => i.cat === k)));
    sp.el.filters.innerHTML = keys.map(k => {
        const n = k === 'all' ? sp.all.length : sp.all.filter(i => i.cat === k).length;
        const label = k === 'all' ? sp.str('all') : sp.data.categories[k];
        return '<button type="button" class="ainav2-sp-f" data-sp-f="' + esc(k) + '" aria-pressed="' +
            (k === sp.st.filter ? 'true' : 'false') + '">' + esc(label) + '<span>' + n + '</span></button>';
    }).join('');
}

/**
 * Filter the hero and the row to one category.
 *
 * @param {Object} sp Controller.
 * @param {string} key Category key, or 'all'.
 */
function setFilter(sp, key) {
    sp.st.filter = key;
    sp.st.list = key === 'all' ? sp.all.slice() : sp.all.filter(i => i.cat === key);
    buildFilters(sp);
    buildProgress(sp);
    buildRow(sp);
    sp.el.row.scrollLeft = 0;
    render(sp, 0);
}

/**
 * Apply the paused state: user pause, open details, hidden tab or collapsed spotlight.
 *
 * @param {Object} sp Controller.
 */
function syncPaused(sp) {
    const paused = sp.st.userPaused || sp.st.modalOpen || document.hidden || sp.st.state !== 'open';
    sp.el.wrap.classList.toggle('ainav2-sp-paused', paused);
    sp.el.pause.innerHTML = icon(sp.st.userPaused ? 'play' : 'pause');
    sp.el.pause.setAttribute('aria-label', sp.str(sp.st.userPaused ? 'resume' : 'pause'));
    sp.el.pause.setAttribute('title', sp.str(sp.st.userPaused ? 'resume' : 'pause'));
}

/**
 * Set open, collapsed or off, and remember it for this user.
 *
 * @param {Object} sp Controller.
 * @param {string} state
 * @param {boolean} save Whether to store the preference.
 */
function setState(sp, state, save) {
    const wasOpen = sp.st.state === 'open';
    sp.st.state = state;
    sp.el.wrap.setAttribute('data-sp-state', state);
    sp.el.bar.setAttribute('aria-expanded', state === 'open' ? 'true' : 'false');
    if (sp.el.toggle) {
        sp.el.toggle.checked = state !== 'off';
    }
    if (save) {
        sp.opts.setPref('block_aiplugin_nav_spotlight', state);
    }
    syncPaused(sp);
    if (state === 'open' && !wasOpen) {
        render(sp, sp.st.cur);
    }
}

/**
 * The item with a given component, from the full list.
 *
 * @param {Object} sp Controller.
 * @param {string} component
 * @return {?Object}
 */
function find(sp, component) {
    return sp.all.find(i => i.component === component) || null;
}

/**
 * Markup for the details dialog.
 *
 * @param {Object} sp Controller.
 * @param {Object} item
 * @return {string}
 */
function modalHtml(sp, item) {
    const more = sp.all.filter(i => i.cat === item.cat && i.component !== item.component).slice(0, 3);
    const row = (label, value) => '<dt>' + esc(label) + '</dt><dd>' + value + '</dd>';
    // An installed plugin is already unlocked, so it has no price to show.
    const price = !item.installed && item.credits > 0 ? esc(sp.str('pricevalue', fmt(item.credits))) : '';
    // The close button sits outside the scrolling area so it stays in view.
    return '<button type="button" class="ainav2-sp-ib ainav2-sp-mx" data-sp-act="close" ' +
        'aria-label="' + esc(sp.str('close')) + '">' +
        icon('close', 2.4) + '</button><div class="ainav2-sp-mscroll">' +
        '<div class="ainav2-sp-mban">' + '<div class="ainav2-sp-mshot">' + sp.shot(item) + '</div>' +
        '<div class="ainav2-sp-mhead">' + sp.rankLine(item) + '<h3 class="ainav2-sp-title" id="ainav2-sp-mtitle">' +
        sp.title(item) + '</h3></div></div>' +
        '<div class="ainav2-sp-mbody">' + sp.chips(item) + '<div class="ainav2-sp-mgrid"><div><p class="ainav2-sp-desc">' +
        esc(item.desc) + '</p>' + sp.features(item) + '</div><dl class="ainav2-sp-dl">' +
        (price ? row(sp.str('price'), price) : '') +
        (item.usage ? row(sp.str('usage'), esc(item.usage)) : '') +
        (item.docs ? row(sp.str('docslabel'), '<code>' + esc(item.docs.replace('https://', '')) + '</code>') : '') +
        (item.type ? row(sp.str('type'), esc(item.type)) : '') +
        (item.latest ? row(sp.str('latest'), esc(sp.str('version', item.latest))) : '') +
        (item.installedversion ? row(sp.str('installedversion'), esc(sp.str('version', item.installedversion))) : '') +
        row(sp.str('component'), '<code>' + esc(item.component) + '</code>') +
        row(sp.str('status'), esc(sp.str(item.installed ? 'statusinstalled' : 'statusnot'))) +
        (item.includes ? row(sp.str('includes'), esc(item.includes)) : '') + '</dl></div>' +
        '<div class="ainav2-sp-mcta">' + sp.ctaHtml(item, true) + '</div>' +
        (item.installed ? '' : '<div class="ainav2-sp-mnote">' + esc(sp.str('creditnote')) + '</div>') +
        (more.length ? '<div class="ainav2-sp-more"><h4>' + esc(sp.str('morein', sp.data.categories[item.cat])) + '</h4>' +
            '<div class="ainav2-sp-moreg">' + more.map(m => '<button type="button" class="ainav2-sp-mc" data-sp-act="info" ' +
            'data-sp-c="' + esc(m.component) + '" style="' + colourVars(m) + '"><span class="ainav2-sp-mcart">' + sp.shot(m) +
            '</span><span class="ainav2-sp-mctx"><b>' + esc(sp.str('rank', m.rank) + ' ' + m.name) + '</b><small>' +
            esc(sp.priceLabel(m, 'short')) +
            '</small></span></button>').join('') + '</div></div>' : '') + '</div></div>';
}

/**
 * Open the details dialog for one plugin.
 *
 * @param {Object} sp Controller.
 * @param {string} component
 */
function openModal(sp, component) {
    const item = find(sp, component);
    if (!item) {
        return;
    }
    sp.el.lastFocus = document.activeElement;
    sp.el.modal.setAttribute('style', colourVars(item));
    sp.el.modal.innerHTML = modalHtml(sp, item);
    sp.el.ov.classList.add('ainav2-sp-ovopen');
    sp.el.ov.setAttribute('aria-hidden', 'false');
    sp.el.ov.scrollTop = 0;
    sp.st.modalOpen = true;
    syncPaused(sp);
    const close = sp.el.modal.querySelector('[data-sp-act="close"]');
    if (close) {
        close.focus();
    }
}

/**
 * Close the details dialog.
 *
 * @param {Object} sp Controller.
 */
function closeModal(sp) {
    if (!sp.st.modalOpen) {
        return;
    }
    sp.el.ov.classList.remove('ainav2-sp-ovopen');
    sp.el.ov.setAttribute('aria-hidden', 'true');
    sp.st.modalOpen = false;
    syncPaused(sp);
    if (sp.el.lastFocus && sp.el.lastFocus.focus) {
        sp.el.lastFocus.focus();
    }
}

/**
 * Carry out a button action (get, open, info, close).
 *
 * @param {Object} sp Controller.
 * @param {string} action
 * @param {string} component
 */
function act(sp, action, component) {
    const item = find(sp, component);
    if (action === 'close') {
        closeModal(sp);
    } else if (action === 'info') {
        openModal(sp, component);
    } else if (item && action === 'open' && item.gotourl) {
        window.location.href = item.gotourl;
    } else if (item) {
        // Get (and Open without a direct link) both go to the plugin's own row in the
        // Plugins panel, where unlocking runs through the normal credit-gated flow.
        closeModal(sp);
        sp.opts.openPlugin(item.pluginname);
    }
}

/**
 * Handle a click anywhere inside the spotlight or its dialog.
 *
 * @param {Object} sp Controller.
 * @param {Event} e
 */
function onClick(sp, e) {
    const t = e.target;
    const actBtn = t.closest('[data-sp-act]');
    if (actBtn) {
        e.preventDefault();
        act(sp, actBtn.getAttribute('data-sp-act'), actBtn.getAttribute('data-sp-c'));
        return;
    }
    const chip = t.closest('[data-sp-f]');
    if (chip) {
        setFilter(sp, chip.getAttribute('data-sp-f'));
        return;
    }
    const seg = t.closest('[data-sp-seg]');
    if (seg) {
        render(sp, parseInt(seg.getAttribute('data-sp-seg'), 10) || 0);
        return;
    }
    const poster = t.closest('.ainav2-sp-poster');
    if (poster) {
        onPoster(sp, poster.getAttribute('data-sp-c'));
    }
}

/**
 * A poster click: show that plugin in the hero, or open its details if it is already showing.
 *
 * @param {Object} sp Controller.
 * @param {string} component
 */
function onPoster(sp, component) {
    const index = sp.st.list.findIndex(i => i.component === component);
    if (index === sp.st.cur) {
        openModal(sp, component);
    } else if (index >= 0) {
        render(sp, index);
    }
}

/**
 * Keyboard support: arrows move between plugins, Escape closes the dialog.
 *
 * @param {Object} sp Controller.
 * @param {KeyboardEvent} e
 */
function onKey(sp, e) {
    if (e.key === 'Escape' && sp.st.modalOpen) {
        closeModal(sp);
        return;
    }
    if (sp.st.modalOpen || sp.st.state !== 'open' || !sp.el.cinema.contains(document.activeElement)) {
        return;
    }
    const tag = document.activeElement.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
        return;
    }
    if (e.key === 'ArrowRight') {
        render(sp, sp.st.cur + 1);
    } else if (e.key === 'ArrowLeft') {
        render(sp, sp.st.cur - 1);
    }
}

/**
 * Size classes from the spotlight's own width: the block sits in a column that can be much
 * narrower than the window, so viewport media queries would never fire.
 *
 * @param {Object} sp Controller.
 */
function syncSize(sp) {
    const w = sp.el.wrap.getBoundingClientRect().width;
    if (!w) {
        return;
    }
    sp.el.wrap.classList.toggle('ainav2-sp-narrow', w < NARROW);
    sp.el.wrap.classList.toggle('ainav2-sp-tiny', w < TINY);
}

/**
 * Tilt the preview towards the pointer.
 *
 * @param {Object} sp Controller.
 * @param {MouseEvent} e
 */
function onPointer(sp, e) {
    const r = sp.el.hero.getBoundingClientRect();
    if (!r.width || !r.height) {
        return;
    }
    sp.el.hero.style.setProperty('--sp-mx', (((e.clientX - r.left) / r.width) * 2 - 1).toFixed(3));
    sp.el.hero.style.setProperty('--sp-my', (((e.clientY - r.top) / r.height) * 2 - 1).toFixed(3));
}

/**
 * The static frame the slides render into.
 *
 * @param {Object} sp Controller.
 * @return {string}
 */
function frameHtml(sp) {
    const t = k => esc(sp.str(k));
    return '<button type="button" class="ainav2-sp-bar" data-sp-bar="1" aria-expanded="false">' +
        '<span class="ainav2-sp-badge">' + t('all') + '<b>' + sp.all.length + '</b></span>' +
        '<span class="ainav2-sp-bartxt"><b>' + t('carousel') + '</b> · <span data-sp-now="1"></span></span>' +
        '<span class="ainav2-sp-bargo">' + t('show') + icon('chevdown', 2.4) +
        '</span></button>' +
        '<section class="ainav2-sp-cinema" aria-roledescription="carousel" aria-label="' + t('carousel') + '">' +
        '<div class="ainav2-sp-hero"><div class="ainav2-sp-bgs"></div><div class="ainav2-sp-beams"></div>' +
        '<div class="ainav2-sp-vig"></div>' +
        '<div class="ainav2-sp-top"><span class="ainav2-sp-badge">' + t('all') + '<b>' + sp.all.length + '</b></span>' +
        '<span class="ainav2-sp-kicker"><b>' + t('brand') + '</b> <span>· ' + t('kicker') + '</span></span>' +
        '<span class="ainav2-sp-tools"><button type="button" class="ainav2-sp-ib" data-sp-pause="1"></button>' +
        '<button type="button" class="ainav2-sp-ib" data-sp-collapse="1" aria-label="' + t('collapse') + '" title="' +
        t('collapse') + '">' + icon('up', 2.4) + '</button></span></div>' +
        '<div class="ainav2-sp-stage"><div class="ainav2-sp-copyslot" aria-live="polite"></div>' +
        '<div class="ainav2-sp-shotslot"></div></div>' +
        '<button type="button" class="ainav2-sp-arrow ainav2-sp-arrowl" data-sp-step="-1" aria-label="' + t('prev') + '">' +
        icon('left', 2.4) + '</button><button type="button" class="ainav2-sp-arrow ainav2-sp-arrowr" data-sp-step="1" ' +
        'aria-label="' + t('next') + '">' + icon('right', 2.4) + '</button>' +
        '<div class="ainav2-sp-foot"><div class="ainav2-sp-prog" role="tablist"></div><span class="ainav2-sp-count"></span>' +
        '<span class="ainav2-sp-hint">' + icon('pause') + t('paused') + '</span></div></div>' +
        '<div class="ainav2-sp-rowwrap"><div class="ainav2-sp-rowhead"><h3 class="ainav2-sp-rowtitle">' + t('rowtitle') + '</h3>' +
        '<span class="ainav2-sp-rowtools"><button type="button" class="ainav2-sp-ib" data-sp-scroll="-1" aria-label="' +
        t('scrollleft') + '">' + icon('left', 2.4) + '</button><button type="button" class="ainav2-sp-ib" data-sp-scroll="1" ' +
        'aria-label="' + t('scrollright') + '">' + icon('right', 2.4) + '</button></span>' +
        '<div class="ainav2-sp-filters"></div></div><div class="ainav2-sp-row"></div></div></section>' +
        '<div class="ainav2-sp-ov" aria-hidden="true"><div class="ainav2-sp-modal" role="dialog" aria-modal="true" ' +
        'aria-labelledby="ainav2-sp-mtitle"></div></div>';
}

/**
 * Cache the frame's elements.
 *
 * @param {Object} sp Controller.
 */
function cacheElements(sp) {
    const q = s => sp.el.wrap.querySelector(s);
    Object.assign(sp.el, {
        bar: q('[data-sp-bar]'), barNow: q('[data-sp-now]'), cinema: q('.ainav2-sp-cinema'), hero: q('.ainav2-sp-hero'),
        bgs: q('.ainav2-sp-bgs'), copySlot: q('.ainav2-sp-copyslot'), shotSlot: q('.ainav2-sp-shotslot'),
        prog: q('.ainav2-sp-prog'), count: q('.ainav2-sp-count'), pause: q('[data-sp-pause]'), row: q('.ainav2-sp-row'),
        filters: q('.ainav2-sp-filters'), ov: q('.ainav2-sp-ov'), modal: q('.ainav2-sp-modal')
    });
}

/**
 * Add the on/off switch to the block footer, beside "Show help tips".
 *
 * @param {Object} sp Controller.
 */
function addFooterSwitch(sp) {
    if (!sp.opts.footer) {
        return;
    }
    const label = document.createElement('label');
    label.className = 'ainav2-helptoggle';
    label.innerHTML = '<input type="checkbox"><span class="ainav2-sw"></span>' + esc(sp.str('toggle'));
    sp.opts.footer.appendChild(label);
    sp.el.toggle = label.querySelector('input');
    sp.el.toggle.addEventListener('change', () => {
        const on = sp.el.toggle.checked;
        setState(sp, on ? 'open' : 'off', true);
        sp.opts.toast(sp.str(on ? 'ontoast' : 'offtoast'));
    });
}

/**
 * Wire the frame's controls.
 *
 * @param {Object} sp Controller.
 */
function wire(sp) {
    sp.el.wrap.addEventListener('click', e => {
        if (e.target === sp.el.ov) {
            closeModal(sp);
            return;
        }
        const t = e.target;
        if (t.closest('[data-sp-bar]')) {
            setState(sp, 'open', true);
        } else if (t.closest('[data-sp-collapse]')) {
            setState(sp, 'collapsed', true);
            sp.opts.toast(sp.str('collapsedtoast'));
        } else if (t.closest('[data-sp-pause]')) {
            sp.st.userPaused = !sp.st.userPaused;
            syncPaused(sp);
        } else if (t.closest('[data-sp-step]')) {
            render(sp, sp.st.cur + (parseInt(t.closest('[data-sp-step]').getAttribute('data-sp-step'), 10) || 1));
        } else if (t.closest('[data-sp-scroll]')) {
            const dir = parseInt(t.closest('[data-sp-scroll]').getAttribute('data-sp-scroll'), 10) || 1;
            sp.el.row.scrollBy({left: dir * sp.el.row.clientWidth * 0.8, behavior: 'smooth'});
        } else {
            onClick(sp, e);
        }
    });
    // The timer is the progress bar itself: when the current segment finishes filling, advance.
    sp.el.prog.addEventListener('animationend', e => {
        if (e.target.parentNode && e.target.parentNode.classList.contains('ainav2-sp-cur')) {
            render(sp, sp.st.cur + 1);
        }
    });
    document.addEventListener('keydown', e => onKey(sp, e));
    document.addEventListener('visibilitychange', () => syncPaused(sp));
    sp.el.hero.addEventListener('mousemove', e => onPointer(sp, e));
    sp.el.hero.addEventListener('mouseleave', () => {
        sp.el.hero.style.setProperty('--sp-mx', '0');
        sp.el.hero.style.setProperty('--sp-my', '0');
    });
    wireTouch(sp);
    if (window.ResizeObserver) {
        new window.ResizeObserver(() => syncSize(sp)).observe(sp.el.wrap);
    } else {
        window.addEventListener('resize', () => syncSize(sp));
    }
}

/**
 * Swipe left or right on the hero to change plugin.
 *
 * @param {Object} sp Controller.
 */
function wireTouch(sp) {
    let startX = null;
    sp.el.hero.addEventListener('touchstart', e => {
        startX = e.touches[0].clientX;
    }, {passive: true});
    sp.el.hero.addEventListener('touchend', e => {
        if (startX === null) {
            return;
        }
        const dx = e.changedTouches[0].clientX - startX;
        startX = null;
        if (Math.abs(dx) > 50) {
            render(sp, sp.st.cur + (dx < 0 ? 1 : -1));
        }
    });
}

/**
 * Render the spotlight into its mount point.
 *
 * @param {Object} opts See createSpotlight().
 * @return {?Object} The controller, or null when there is nothing to show.
 */
/**
 * A release number from the LMS Labs versions feed, without a leading v.
 *
 * @param {*} value
 * @return {string} The release, or '' when missing or malformed.
 */
function cleanRelease(value) {
    const v = String(value || '').trim().replace(/^v/i, '');
    return /^\d+(\.\d+){0,3}([-+][0-9A-Za-z.]+)?$/.test(v) ? v : '';
}

/**
 * Apply the LMS Labs versions feed the block's update check has just fetched.
 *
 * Shows each plugin's latest release as soon as LMS Labs publishes it, and drops any plugin
 * the feed marks as not ready. The server applies the same feed on the next page view.
 *
 * @param {Object|null} sp Controller returned by init().
 * @param {Object} map The feed's plugins, keyed by component.
 */
export const live = (sp, map) => {
    if (!sp || !map) {
        return;
    }
    let removed = false;
    for (let i = sp.all.length - 1; i >= 0; i--) {
        const item = sp.all[i];
        const entry = map[item.component];
        if (!entry) {
            continue;
        }
        if (entry.status && entry.status !== 'ready') {
            sp.all.splice(i, 1);
            removed = true;
            continue;
        }
        const latest = cleanRelease(entry.version);
        if (latest && latest !== item.latest) {
            item.latest = latest;
            sp.el.wrap.querySelectorAll('[data-sp-ver="' + item.component + '"]').forEach(chip => {
                chip.textContent = sp.str('version', latest);
                chip.hidden = false;
            });
        }
    }
    if (!sp.all.length) {
        sp.el.wrap.hidden = true;
        return;
    }
    if (removed) {
        sp.all.forEach((item, i) => {
            item.rank = i + 1;
        });
        sp.el.wrap.querySelectorAll('.ainav2-sp-badge b').forEach(b => {
            b.textContent = sp.all.length;
        });
        setFilter(sp, sp.all.some(i => i.cat === sp.st.filter) ? sp.st.filter : 'all');
    }
};

export const init = opts => {
    if (!opts || !opts.mount || !opts.data || !opts.data.items || !opts.data.items.length) {
        return null;
    }
    const sp = createSpotlight(opts);
    sp.el.wrap = document.createElement('div');
    sp.el.wrap.className = 'ainav2-sp';
    sp.el.wrap.style.setProperty('--sp-dur', (DURATION / 1000) + 's');
    sp.el.wrap.innerHTML = frameHtml(sp);
    opts.mount.appendChild(sp.el.wrap);
    cacheElements(sp);
    addFooterSwitch(sp);
    buildFilters(sp);
    buildProgress(sp);
    buildRow(sp);
    wire(sp);
    syncSize(sp);
    setState(sp, sp.st.state, false);
    render(sp, 0);
    return sp;
};
