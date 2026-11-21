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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin Finder: "Ask AI — find the right plugin".
 *
 * A full-screen finder opened from the button beside the block's search box. The user types
 * or speaks what they need; results come back from block_aiplugin_nav_find_plugins in two
 * passes: instant Smart match results first, then (when the site has Moodle AI set up) AI
 * picks with a one-line reason each. Every result is a card with screenshot, features,
 * install / ask-admin, docs and YouTube playlist links.
 *
 * @module     block_aiplugin_nav/finder
 * @copyright  2026 LMS Labs
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(Ajax) {

    'use strict';

    var ICONS = {
        spark: '<path d="M12 3l1.9 4.6L18.5 9.5l-4.6 1.9L12 16l-1.9-4.6L5.5 9.5l4.6-1.9z"/>' +
            '<path d="M19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9z"/>',
        mic: '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/>',
        stop: '<rect x="7" y="7" width="10" height="10" rx="2"/>',
        arrow: '<path d="M5 12h14M13 6l6 6-6 6"/>',
        close: '<path d="M6 6l12 12M18 6L6 18"/>',
        check: '<path d="m5 13 4 4L19 7"/>',
        doc: '<path d="M14 3H6v18h12V7z"/><path d="M14 3v4h4M9 13h6M9 17h6"/>',
        play: '<rect x="2.5" y="5" width="19" height="14" rx="4"/><path d="m10 9 5 3-5 3z" fill="currentColor"/>',
        image: '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/>',
        install: '<path d="M12 3v12M7 10l5 5 5-5M5 21h14"/>',
        open: '<path d="M14 4h6v6M20 4l-9 9M18 14v6H4V6h6"/>',
        mail: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        bolt: '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>'
    };

    var cfg = null; // Init options.
    var S = {}; // Strings.
    var els = {}; // Cached DOM.
    var seq = 0; // Guards against out-of-order responses.
    var lastQuery = '';
    var lastTitle = '';
    var opener = null;
    var rec = null; // SpeechRecognition instance.
    var listening = false;
    var speechBase = '';
    var speechFinal = '';

    /**
     * HTML-escape.
     *
     * @param {*} s
     * @return {string}
     */
    function esc(s) {
        if (s === undefined || s === null) {
            return '';
        }
        return String(s).replace(/[&<>"']/g, function(c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
        });
    }

    /**
     * Inline SVG icon.
     *
     * @param {string} name
     * @param {string} [cls]
     * @return {string}
     */
    function icon(name, cls) {
        return '<svg class="' + (cls || 'ainav2-fx-ic') + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
            'stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + ICONS[name] + '</svg>';
    }

    /**
     * Fill {name}-style placeholders.
     *
     * @param {string} s
     * @param {Object} vals
     * @return {string}
     */
    function fill(s, vals) {
        return String(s || '').replace(/\{(\w+)\}|\{\$a\}/g, function(m, k) {
            if (k === undefined) {
                return vals.a !== undefined ? vals.a : m;
            }
            return vals[k] !== undefined ? vals[k] : m;
        });
    }

    /**
     * Only http(s) links reach an href.
     *
     * @param {string} url
     * @return {string}
     */
    function safeUrl(url) {
        return /^https?:\/\//i.test(String(url || '')) ? String(url) : '';
    }

    /* ------------------------------------------------------------------ *
     * Header button.
     * ------------------------------------------------------------------ */

    /**
     * Render the "Ask AI" button into the header slot.
     */
    function renderButton() {
        cfg.mount.innerHTML =
            '<button type="button" class="ainav2-fxbtn" id="ainav2-fxbtn" aria-haspopup="dialog" ' +
                'data-help="finder">' +
                '<span class="ainav2-fxbtn-orb">' + icon('spark', 'ainav2-fxbtn-ic') + '</span>' +
                '<span class="ainav2-fxbtn-txt"><strong>' + esc(S.button_title) + '</strong>' +
                '<span>' + esc(S.button_sub) + '</span></span>' +
                icon('arrow', 'ainav2-fxbtn-go') +
            '</button>';
        cfg.mount.querySelector('#ainav2-fxbtn').addEventListener('click', function(e) {
            open(e.currentTarget);
        });
    }

    /* ------------------------------------------------------------------ *
     * Overlay shell.
     * ------------------------------------------------------------------ */

    /**
     * Build the finder overlay the first time it opens.
     */
    function build() {
        if (els.ov) {
            return;
        }
        var Speech = window.SpeechRecognition || window.webkitSpeechRecognition;
        var d = cfg.data;
        var html = '';
        html += '<div class="ainav2-fx-sheet">';
        html += '<header class="ainav2-fx-top">';
        html += '  <div class="ainav2-fx-brand"><span class="ainav2-fx-orb">' + icon('spark') + '</span>' +
            '<div><div class="ainav2-fx-kicker">' + esc(S.kicker) + '</div>' +
            '<h2 id="ainav2-fx-title">' + esc(S.heading) + '</h2></div></div>';
        html += '  <div class="ainav2-fx-topact">';
        if (safeUrl(d.channel)) {
            html += '<a class="ainav2-fx-chan" href="' + esc(d.channel) + '" target="_blank" rel="noopener noreferrer">' +
                icon('play') + '<span>' + esc(S.youtube_channel) + '</span></a>';
        }
        html += '    <button type="button" class="ainav2-fx-x" id="ainav2-fx-x" aria-label="' + esc(S.close) + '">' +
            icon('close') + '</button>';
        html += '  </div>';
        html += '</header>';

        html += '<div class="ainav2-fx-scroll" id="ainav2-fx-scroll">';
        html += '<section class="ainav2-fx-ask" id="ainav2-fx-ask">';
        html += '  <p class="ainav2-fx-lead">' + esc(S.lead) + '</p>';
        html += '  <form class="ainav2-fx-form" id="ainav2-fx-form" autocomplete="off">';
        html += '    <div class="ainav2-fx-field">';
        html += '      <textarea id="ainav2-fx-q" rows="2" maxlength="600" placeholder="' + esc(S.placeholder) + '" ' +
            'aria-label="' + esc(S.heading) + '"></textarea>';
        if (Speech) {
            html += '      <button type="button" class="ainav2-fx-mic" id="ainav2-fx-mic" aria-pressed="false" ' +
                'aria-label="' + esc(S.mic) + '" title="' + esc(S.mic) + '">' + icon('mic') + '</button>';
        }
        html += '    </div>';
        html += '    <button type="submit" class="ainav2-fx-go" id="ainav2-fx-go">' +
            '<span>' + esc(S.find) + '</span>' + icon('arrow') + '</button>';
        html += '  </form>';
        html += '  <div class="ainav2-fx-listen" id="ainav2-fx-listen" hidden aria-live="polite">' +
            '<span class="ainav2-fx-wave"><i></i><i></i><i></i><i></i><i></i></span>' +
            '<span id="ainav2-fx-listentxt">' + esc(S.listening) + '</span></div>';

        html += '  <div class="ainav2-fx-row"><span class="ainav2-fx-rowlab">' + esc(S.examples) + '</span>' +
            '<div class="ainav2-fx-chips">';
        (d.examples || []).forEach(function(ex) {
            html += '<button type="button" class="ainav2-fx-ex" data-fx-ex="' + esc(ex) + '">' + esc(ex) + '</button>';
        });
        html += '</div></div>';
        html += '  <div class="ainav2-fx-row"><span class="ainav2-fx-rowlab">' + esc(S.topics) + '</span>' +
            '<div class="ainav2-fx-chips">';
        (d.topics || []).forEach(function(t) {
            html += '<button type="button" class="ainav2-fx-topic" data-fx-topic="' + esc(t.key) + '" ' +
                'data-fx-label="' + esc(t.label) + '">' + esc(t.label) + '</button>';
        });
        html += '</div></div>';
        html += '</section>';

        html += '<section class="ainav2-fx-results" id="ainav2-fx-results" aria-live="polite" hidden>';
        html += '  <div class="ainav2-fx-rhead">';
        html += '    <div class="ainav2-fx-rtitle"><h3 id="ainav2-fx-rtitle"></h3>' +
            '<span class="ainav2-fx-count" id="ainav2-fx-count"></span></div>';
        html += '    <div class="ainav2-fx-mode" id="ainav2-fx-mode"></div>';
        html += '  </div>';
        html += '  <div class="ainav2-fx-summary" id="ainav2-fx-summary" hidden></div>';
        html += '  <div class="ainav2-fx-notice" id="ainav2-fx-notice" hidden></div>';
        html += '  <div class="ainav2-fx-cards" id="ainav2-fx-cards"></div>';
        html += '</section>';
        html += '</div>'; // Scroll.
        html += '</div>'; // Sheet.

        html += '<div class="ainav2-fx-lb" id="ainav2-fx-lb" hidden>' +
            '<button type="button" class="ainav2-fx-lbx" id="ainav2-fx-lbx" aria-label="' + esc(S.close) + '">' +
            icon('close') + '</button><img id="ainav2-fx-lbimg" alt=""></div>';

        var ov = document.createElement('div');
        ov.className = 'ainav2-fx';
        ov.id = 'ainav2-fx';
        ov.setAttribute('role', 'dialog');
        ov.setAttribute('aria-modal', 'true');
        ov.setAttribute('aria-labelledby', 'ainav2-fx-title');
        ov.hidden = true;
        ov.innerHTML = html;
        cfg.root.appendChild(ov);

        els.ov = ov;
        els.q = ov.querySelector('#ainav2-fx-q');
        els.form = ov.querySelector('#ainav2-fx-form');
        els.mic = ov.querySelector('#ainav2-fx-mic');
        els.listen = ov.querySelector('#ainav2-fx-listen');
        els.listentxt = ov.querySelector('#ainav2-fx-listentxt');
        els.results = ov.querySelector('#ainav2-fx-results');
        els.rtitle = ov.querySelector('#ainav2-fx-rtitle');
        els.count = ov.querySelector('#ainav2-fx-count');
        els.mode = ov.querySelector('#ainav2-fx-mode');
        els.summary = ov.querySelector('#ainav2-fx-summary');
        els.notice = ov.querySelector('#ainav2-fx-notice');
        els.cards = ov.querySelector('#ainav2-fx-cards');
        els.scroll = ov.querySelector('#ainav2-fx-scroll');
        els.lb = ov.querySelector('#ainav2-fx-lb');
        els.lbimg = ov.querySelector('#ainav2-fx-lbimg');
        els.go = ov.querySelector('#ainav2-fx-go');

        wire();
    }

    /**
     * Event wiring for the overlay.
     */
    function wire() {
        els.ov.querySelector('#ainav2-fx-x').addEventListener('click', close);
        els.ov.querySelector('#ainav2-fx-lbx').addEventListener('click', closeLightbox);
        els.lb.addEventListener('click', function(e) {
            if (e.target === els.lb) {
                closeLightbox();
            }
        });
        els.ov.addEventListener('click', function(e) {
            if (e.target === els.ov) {
                close();
            }
        });

        els.form.addEventListener('submit', function(e) {
            e.preventDefault();
            stopListening();
            ask(els.q.value);
        });
        els.q.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
                e.preventDefault();
                stopListening();
                ask(els.q.value);
            }
        });
        els.q.addEventListener('input', autosize);

        if (els.mic) {
            els.mic.addEventListener('click', function() {
                if (listening) {
                    stopListening();
                } else {
                    startListening();
                }
            });
        }

        els.ov.addEventListener('click', function(e) {
            var t = e.target.closest ? e.target.closest('[data-fx-ex],[data-fx-topic],[data-fx-act],[data-fx-more],' +
                '[data-fx-shot]') : null;
            if (!t) {
                return;
            }
            if (t.hasAttribute('data-fx-ex')) {
                els.q.value = t.getAttribute('data-fx-ex');
                autosize();
                ask(els.q.value);
            } else if (t.hasAttribute('data-fx-topic')) {
                topic(t.getAttribute('data-fx-topic'), t.getAttribute('data-fx-label'));
            } else if (t.hasAttribute('data-fx-more')) {
                var card = t.closest('.ainav2-fx-card');
                var full = card.querySelector('.ainav2-fx-full');
                var openNow = full.hidden;
                full.hidden = !openNow;
                t.setAttribute('aria-expanded', openNow ? 'true' : 'false');
                t.querySelector('span').textContent = openNow ? S.less : S.more;
            } else if (t.hasAttribute('data-fx-shot')) {
                openLightbox(t.getAttribute('data-fx-shot'), t.getAttribute('data-fx-alt'));
            } else if (t.hasAttribute('data-fx-act')) {
                action(t.getAttribute('data-fx-act'), t.getAttribute('data-fx-comp'));
            }
        });

        // A screenshot that fails to load becomes the branded placeholder, never a broken image.
        els.cards.addEventListener('error', function(e) {
            var img = e.target;
            if (!img || img.tagName !== 'IMG') {
                return;
            }
            var btn = img.closest('.ainav2-fx-shotbtn');
            var art = img.closest('.ainav2-fx-card');
            if (btn && art) {
                var name = art.querySelector('.ainav2-fx-name').textContent;
                btn.outerHTML = noShot(name);
                Array.prototype.forEach.call(art.querySelectorAll('.ainav2-fx-acts [data-fx-shot]'), function(b) {
                    b.parentNode.removeChild(b);
                });
            }
        }, true);

        els.ov.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                if (!els.lb.hidden) {
                    closeLightbox();
                } else {
                    close();
                }
                return;
            }
            if (e.key === 'Tab') {
                trapFocus(e);
            }
        });
    }

    /**
     * Keep keyboard focus inside the dialog.
     *
     * @param {KeyboardEvent} e
     */
    function trapFocus(e) {
        var scope = !els.lb.hidden ? els.lb : els.ov;
        var nodes = Array.prototype.filter.call(scope.querySelectorAll(
            'a[href],button:not([disabled]),textarea,input,select,[tabindex]:not([tabindex="-1"])'), function(n) {
            return n.offsetParent !== null || n === document.activeElement;
        });
        if (!nodes.length) {
            return;
        }
        var first = nodes[0];
        var last = nodes[nodes.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }

    /**
     * Grow the textarea with its content.
     */
    function autosize() {
        els.q.style.height = 'auto';
        els.q.style.height = Math.min(els.q.scrollHeight, 180) + 'px';
    }

    /**
     * Open the finder.
     *
     * @param {HTMLElement} from The control that opened it, for focus return.
     */
    function open(from) {
        build();
        opener = from || null;
        els.ov.hidden = false;
        document.documentElement.classList.add('ainav2-fx-lock');
        // Next frame so the entrance transition runs.
        window.requestAnimationFrame(function() {
            els.ov.classList.add('ainav2-fx-in');
            els.q.focus();
        });
    }

    /**
     * Close the finder.
     */
    function close() {
        if (!els.ov || els.ov.hidden) {
            return;
        }
        stopListening();
        closeLightbox();
        els.ov.classList.remove('ainav2-fx-in');
        els.ov.hidden = true;
        document.documentElement.classList.remove('ainav2-fx-lock');
        if (opener && opener.focus) {
            opener.focus();
        }
    }

    /* ------------------------------------------------------------------ *
     * Voice input.
     * ------------------------------------------------------------------ */

    /**
     * Start speech recognition, writing what is heard into the request box.
     */
    function startListening() {
        var Speech = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!Speech) {
            return;
        }
        try {
            rec = new Speech();
        } catch (err) {
            return;
        }
        rec.lang = cfg.data.lang || document.documentElement.lang || 'en-AU';
        rec.interimResults = true;
        rec.continuous = false;
        rec.maxAlternatives = 1;
        speechBase = els.q.value.trim();
        speechFinal = '';

        rec.onresult = function(ev) {
            var interim = '';
            var i;
            for (i = ev.resultIndex; i < ev.results.length; i++) {
                if (ev.results[i].isFinal) {
                    speechFinal += ev.results[i][0].transcript;
                } else {
                    interim += ev.results[i][0].transcript;
                }
            }
            els.q.value = (speechBase ? speechBase + ' ' : '') + (speechFinal + interim).trim();
            autosize();
        };
        rec.onerror = function(ev) {
            var msg = ev.error === 'not-allowed' || ev.error === 'service-not-allowed' ? S.mic_denied :
                (ev.error === 'aborted' ? '' : S.mic_error);
            setListening(false, msg);
        };
        rec.onend = function() {
            var heard = speechFinal.trim();
            var wasListening = listening;
            setListening(false, null);
            if (wasListening && heard) {
                ask(els.q.value);
            }
        };
        try {
            rec.start();
            setListening(true, null);
        } catch (err) {
            setListening(false, S.mic_error);
        }
    }

    /**
     * Stop listening without submitting.
     */
    function stopListening() {
        if (rec && listening) {
            listening = false;
            try {
                rec.stop();
            } catch (err) {
                // Already stopped.
            }
        }
        setListening(false, null);
    }

    /**
     * Reflect the listening state in the UI.
     *
     * @param {boolean} on
     * @param {string|null} message Message to leave on screen, or null to hide.
     */
    function setListening(on, message) {
        listening = on;
        if (!els.mic) {
            return;
        }
        els.mic.classList.toggle('ainav2-fx-on', on);
        els.mic.setAttribute('aria-pressed', on ? 'true' : 'false');
        els.mic.setAttribute('aria-label', on ? S.mic_stop : S.mic);
        els.mic.innerHTML = icon(on ? 'stop' : 'mic');
        els.listen.classList.toggle('ainav2-fx-err', !on && !!message);
        if (on) {
            els.listentxt.textContent = S.listening;
            els.listen.hidden = false;
        } else if (message) {
            els.listentxt.textContent = message;
            els.listen.hidden = false;
        } else {
            els.listen.hidden = true;
        }
    }

    /* ------------------------------------------------------------------ *
     * Searching.
     * ------------------------------------------------------------------ */

    /**
     * Call the finder service.
     *
     * @param {Object} args
     * @return {Promise}
     */
    function call(args) {
        return Ajax.call([{methodname: 'block_aiplugin_nav_find_plugins', args: args}])[0];
    }

    /**
     * Search for a typed or spoken request: Smart match now, AI picks after.
     *
     * @param {string} text
     */
    function ask(text) {
        text = String(text || '').trim();
        if (!text) {
            els.q.focus();
            return;
        }
        var my = ++seq;
        lastQuery = text;
        lastTitle = fill(S.results_for, {a: text.length > 90 ? text.slice(0, 88) + '…' : text});
        showLoading(lastTitle);

        call({query: text, topic: '', mode: 'match', acceptpolicy: false}).then(function(res) {
            if (my !== seq) {
                return null;
            }
            render(res, false);
            if (cfg.data.ai === 'ok' && res.aistatus === 'ok') {
                runAi(my, text, false);
            } else {
                setMode('match', res.aistatus);
            }
            return null;
        }).catch(function() {
            if (my === seq) {
                showError();
            }
        });
    }

    /**
     * Second pass: ask the site's AI provider.
     *
     * @param {number} my Sequence number of the request.
     * @param {string} text
     * @param {boolean} acceptpolicy
     */
    function runAi(my, text, acceptpolicy) {
        setMode('thinking', '');
        call({query: text, topic: '', mode: 'ai', acceptpolicy: acceptpolicy}).then(function(res) {
            if (my !== seq) {
                return null;
            }
            if (res.aistatus === 'ok' || res.aistatus === 'policy') {
                cfg.data.ai = res.aistatus;
            }
            if (res.mode === 'ai' && res.results.length) {
                render(res, true);
                setMode('ai', res.aistatus);
            } else {
                setMode('match', res.aistatus);
            }
            return null;
        }).catch(function() {
            if (my === seq) {
                setMode('match', 'failed');
            }
        });
    }

    /**
     * Browse a quick-start topic.
     *
     * @param {string} key
     * @param {string} label
     */
    function topic(key, label) {
        var my = ++seq;
        lastQuery = label;
        lastTitle = label;
        showLoading(label);
        Array.prototype.forEach.call(els.ov.querySelectorAll('[data-fx-topic]'), function(b) {
            b.setAttribute('aria-pressed', b.getAttribute('data-fx-topic') === key ? 'true' : 'false');
        });
        call({query: '', topic: key, mode: 'match', acceptpolicy: false}).then(function(res) {
            if (my === seq) {
                render(res, false);
                els.mode.innerHTML = '';
            }
            return null;
        }).catch(function() {
            if (my === seq) {
                showError();
            }
        });
    }

    /**
     * Skeleton cards while waiting.
     *
     * @param {string} title
     */
    function showLoading(title) {
        els.results.hidden = false;
        els.rtitle.textContent = title;
        els.count.textContent = '';
        els.summary.hidden = true;
        els.notice.hidden = true;
        els.mode.innerHTML = '<span class="ainav2-fx-badge ainav2-fx-busy"><i></i>' + esc(S.searching) + '</span>';
        var sk = '';
        var i;
        for (i = 0; i < 3; i++) {
            sk += '<div class="ainav2-fx-card ainav2-fx-skel" aria-hidden="true"><div class="ainav2-fx-shot"></div>' +
                '<div class="ainav2-fx-main"><b></b><b></b><b></b><b></b></div></div>';
        }
        els.cards.innerHTML = sk;
        els.go.disabled = true;
        window.setTimeout(function() {
            els.go.disabled = false;
        }, 400);
        scrollToResults();
    }

    /**
     * Bring the results into view, below the ask area.
     */
    function scrollToResults() {
        var top = els.results.getBoundingClientRect().top - els.scroll.getBoundingClientRect().top +
            els.scroll.scrollTop - 4;
        if (els.scroll.scrollTop < top - 40) {
            try {
                els.scroll.scrollTo({top: top, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ?
                    'auto' : 'smooth'});
            } catch (err) {
                els.scroll.scrollTop = top;
            }
        }
    }

    /**
     * Something failed.
     */
    function showError() {
        els.mode.innerHTML = '';
        els.count.textContent = '';
        els.cards.innerHTML = '<div class="ainav2-fx-empty">' + esc(S.error) + '</div>';
    }

    /**
     * The mode badge and any notice under the title.
     *
     * @param {string} mode 'match', 'ai' or 'thinking'.
     * @param {string} aistatus
     */
    function setMode(mode, aistatus) {
        if (mode === 'thinking') {
            els.mode.innerHTML = '<span class="ainav2-fx-badge ainav2-fx-match">' + icon('bolt') + esc(S.mode_match) +
                '</span><span class="ainav2-fx-badge ainav2-fx-busy ainav2-fx-aibusy"><i></i>' + esc(S.thinking) + '</span>';
            els.notice.hidden = true;
            return;
        }
        if (mode === 'ai') {
            els.mode.innerHTML = '<span class="ainav2-fx-badge ainav2-fx-ai">' + icon('spark') + esc(S.mode_ai) + '</span>';
            els.notice.hidden = true;
            return;
        }
        els.mode.innerHTML = '<span class="ainav2-fx-badge ainav2-fx-match">' + icon('bolt') + esc(S.mode_match) +
            '</span>';
        var note = '';
        if (aistatus === 'policy') {
            note = esc(S.ai_policy) + ' <button type="button" class="ainav2-fx-linkbtn" id="ainav2-fx-accept">' +
                esc(S.ai_policy_accept) + '</button>';
        } else if (aistatus === 'failed') {
            note = esc(S.ai_failed);
        } else if (aistatus === 'unavailable' && cfg.isadmin) {
            // Only admins can do anything about a missing AI provider.
            note = esc(S.ai_unavailable);
        }
        els.notice.innerHTML = note;
        els.notice.hidden = !note;
        var accept = els.notice.querySelector('#ainav2-fx-accept');
        if (accept) {
            accept.addEventListener('click', function() {
                cfg.data.ai = 'ok';
                runAi(seq, lastQuery, true);
            });
        }
    }

    /* ------------------------------------------------------------------ *
     * Cards.
     * ------------------------------------------------------------------ */

    /**
     * Render a response.
     *
     * @param {Object} res
     * @param {boolean} fromAi
     */
    function render(res, fromAi) {
        var items = res.results || [];
        els.rtitle.textContent = lastTitle;
        els.count.textContent = items.length === 1 ? S.count_one : fill(S.count, {a: items.length});
        if (fromAi && res.summary) {
            els.summary.innerHTML = icon('spark') + '<span>' + esc(res.summary) + '</span>';
            els.summary.hidden = false;
        } else {
            els.summary.hidden = true;
        }
        if (!items.length) {
            els.cards.innerHTML = '<div class="ainav2-fx-empty">' + esc(S.none) + '</div>';
            return;
        }
        els.cards.innerHTML = items.map(function(it, i) {
            return card(it, i, fromAi);
        }).join('');
        els.cards.classList.remove('ainav2-fx-swap');
        // Restart the reveal animation for the new set.
        void els.cards.offsetWidth;
        els.cards.classList.add('ainav2-fx-swap');
    }

    /**
     * The match label for a card.
     *
     * @param {Object} it
     * @param {number} i
     * @return {string}
     */
    function fitLabel(it, i) {
        if (i === 0) {
            return S.best;
        }
        return it.score >= 60 ? S.strong : S.good;
    }

    /**
     * The Plugins panel row for a component, when this user is an admin.
     *
     * @param {string} component
     * @return {Object|null}
     */
    function rowFor(component) {
        var rows = cfg.plugins || [];
        var i;
        for (i = 0; i < rows.length; i++) {
            if (rows[i].component === component) {
                return rows[i];
            }
        }
        return null;
    }

    /**
     * One result card.
     *
     * @param {Object} it
     * @param {number} i
     * @param {boolean} fromAi
     * @return {string}
     */
    function card(it, i, fromAi) {
        var a1 = /^#[0-9a-f]{6}$/i.test(it.accent) ? it.accent : '#3b82f6';
        var a2 = /^#[0-9a-f]{6}$/i.test(it.accent2) ? it.accent2 : '#8b5cf6';
        var img = safeUrl(it.image);
        var docs = safeUrl(it.docs);
        var video = safeUrl(it.video);
        var row = cfg.isadmin ? rowFor(it.component) : null;
        var h = '';

        h += '<article class="ainav2-fx-card' + (i === 0 ? ' ainav2-fx-top1' : '') + '" style="--fa:' + a1 + ';--fb:' + a2 +
            ';--i:' + i + '" aria-label="' + esc(it.name) + '">';

        // Screenshot.
        h += '<div class="ainav2-fx-shot">';
        if (img) {
            h += '<button type="button" class="ainav2-fx-shotbtn" data-fx-shot="' + esc(img) + '" data-fx-alt="' +
                esc(it.name) + '" aria-label="' + esc(S.enlarge + ': ' + it.name) + '">' +
                '<img src="' + esc(img) + '" alt="" loading="lazy" decoding="async">' +
                '<span class="ainav2-fx-zoom">' + icon('image') + '</span></button>';
        } else {
            h += noShot(it.name);
        }
        h += '<span class="ainav2-fx-fit">' + (i === 0 ? icon('spark') : '') + esc(fitLabel(it, i)) + '</span>';
        h += '</div>';

        // Body.
        h += '<div class="ainav2-fx-main">';
        h += '<div class="ainav2-fx-meta">';
        if (it.type) {
            h += '<span class="ainav2-fx-pill ainav2-fx-type">' + esc(it.type) + '</span>';
        }
        if (it.catlabel) {
            h += '<span class="ainav2-fx-pill">' + esc(it.catlabel) + '</span>';
        }
        if (it.installed) {
            h += '<span class="ainav2-fx-pill ainav2-fx-inst">' + icon('check') + esc(S.installed) + '</span>';
        } else if (it.audience === 'admin' && !cfg.isadmin) {
            h += '<span class="ainav2-fx-pill">' + esc(S.for_admin) + '</span>';
        }
        h += '</div>';
        h += '<h3 class="ainav2-fx-name">' + esc(it.name) + '</h3>';
        if (it.why) {
            h += '<p class="ainav2-fx-why' + (fromAi ? ' ainav2-fx-whyai' : '') + '">' +
                icon(fromAi ? 'spark' : 'bolt') + '<span>' + esc(it.why) + '</span></p>';
        }
        h += '<p class="ainav2-fx-pitch">' + esc(it.pitch) + '</p>';

        if (it.features && it.features.length) {
            h += '<div class="ainav2-fx-fhead">' + esc(S.features) + '</div><ul class="ainav2-fx-feat">';
            it.features.forEach(function(f) {
                h += '<li>' + icon('check') + '<span>' + esc(f) + '</span></li>';
            });
            h += '</ul>';
        }

        if (it.desc && it.desc !== it.pitch && it.desc.length > it.pitch.length + 20) {
            h += '<button type="button" class="ainav2-fx-more" data-fx-more="1" aria-expanded="false">' +
                '<span>' + esc(S.more) + '</span></button>';
            h += '<p class="ainav2-fx-full" hidden>' + esc(it.desc) + '</p>';
        }
        if (it.usage) {
            h += '<div class="ainav2-fx-usage"><strong>' + esc(S.usage) + ':</strong> ' + esc(it.usage) + '</div>';
        }

        // Actions.
        h += '<div class="ainav2-fx-acts">';
        if (it.installed) {
            if (row && row.gotourl && row.action !== 'none') {
                h += '<a class="ainav2-fx-btn ainav2-fx-primary" href="' + esc(row.gotourl) + '">' + icon('open') +
                    esc(S.open) + '</a>';
            }
        } else if (cfg.isadmin) {
            if (row && row.status === 'ready') {
                h += '<button type="button" class="ainav2-fx-btn ainav2-fx-primary" data-fx-act="install" data-fx-comp="' +
                    esc(it.component) + '">' + icon('install') + esc(S.install) + '</button>';
            } else if (docs) {
                h += '<a class="ainav2-fx-btn ainav2-fx-primary" href="' + esc(docs) + '" target="_blank" ' +
                    'rel="noopener noreferrer">' + icon('install') + esc(S.get) + '</a>';
            }
        } else {
            h += '<button type="button" class="ainav2-fx-btn ainav2-fx-primary" data-fx-act="ask" data-fx-comp="' +
                esc(it.component) + '">' + icon('mail') + esc(S.ask_admin) + '</button>';
        }
        if (docs) {
            h += '<a class="ainav2-fx-btn" href="' + esc(docs) + '" target="_blank" rel="noopener noreferrer">' +
                icon('doc') + esc(S.docs) + '</a>';
        }
        if (video) {
            h += '<a class="ainav2-fx-btn ainav2-fx-yt" href="' + esc(video) + '" target="_blank" rel="noopener noreferrer">' +
                icon('play') + esc(it.videokind === 'playlist' ? S.videos : S.videos_search) + '</a>';
        }
        if (img) {
            h += '<button type="button" class="ainav2-fx-btn" data-fx-shot="' + esc(img) + '" data-fx-alt="' +
                esc(it.name) + '">' + icon('image') + esc(S.screenshot) + '</button>';
        }
        h += '</div>';

        if (it.installed) {
            h += '<p class="ainav2-fx-note">' + esc(it.courseaddable ? S.installed_activity : S.installed_other) + '</p>';
        }
        h += '</div>';
        h += '</article>';
        return h;
    }

    /**
     * The branded placeholder shown where a plugin has no screenshot.
     *
     * @param {string} name
     * @return {string}
     */
    function noShot(name) {
        return '<div class="ainav2-fx-noshot"><span>' + esc(String(name || '').replace(/^AI\s+/, '').charAt(0)) + '</span>' +
            '<small>' + esc(S.noimage) + '</small></div>';
    }

    /**
     * Card button actions.
     *
     * @param {string} act
     * @param {string} component
     */
    function action(act, component) {
        var it = findCard(component);
        if (act === 'install') {
            var row = rowFor(component);
            close();
            if (row && cfg.openPlugin) {
                cfg.openPlugin(row.name);
            }
            return;
        }
        if (act === 'ask' && it) {
            var text = fill(S.ask_text, {name: it.name, need: lastQuery || it.pitch, docs: it.docs || ''});
            copy(text).then(function() {
                if (cfg.toast) {
                    cfg.toast(S.ask_copied);
                }
                return null;
            }).catch(function() {
                window.prompt(S.ask_admin, text); // eslint-disable-line no-alert
            });
        }
    }

    /**
     * The card data for a component, read back from the rendered card.
     *
     * @param {string} component
     * @return {Object|null}
     */
    function findCard(component) {
        var cardel = els.cards.querySelector('[data-fx-comp="' + component.replace(/[^a-z0-9_]/g, '') + '"]');
        if (!cardel) {
            return null;
        }
        var art = cardel.closest('.ainav2-fx-card');
        var docs = art.querySelector('a.ainav2-fx-btn[href*="lms-labs.com"]');
        return {
            name: art.querySelector('.ainav2-fx-name').textContent,
            pitch: art.querySelector('.ainav2-fx-pitch').textContent,
            docs: docs ? docs.href : ''
        };
    }

    /**
     * Copy text to the clipboard.
     *
     * @param {string} text
     * @return {Promise}
     */
    function copy(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function(resolve, reject) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            var ok = false;
            try {
                ok = document.execCommand('copy');
            } catch (err) {
                ok = false;
            }
            document.body.removeChild(ta);
            if (ok) {
                resolve();
            } else {
                reject(new Error('copy'));
            }
        });
    }

    /* ------------------------------------------------------------------ *
     * Screenshot lightbox.
     * ------------------------------------------------------------------ */

    /**
     * Show a screenshot full size.
     *
     * @param {string} src
     * @param {string} alt
     */
    function openLightbox(src, alt) {
        if (!safeUrl(src)) {
            return;
        }
        els.lbimg.src = src;
        els.lbimg.alt = alt || '';
        els.lb.hidden = false;
        els.lb.querySelector('#ainav2-fx-lbx').focus();
    }

    /**
     * Hide the lightbox.
     */
    function closeLightbox() {
        if (els.lb && !els.lb.hidden) {
            els.lb.hidden = true;
            els.lbimg.removeAttribute('src');
        }
    }

    return {
        /**
         * Initialise the finder.
         *
         * @param {Object} options mount, root, data, isadmin, plugins, openPlugin, toast.
         */
        init: function(options) {
            cfg = options;
            S = options.data.strings || {};
            renderButton();
        },
        open: open
    };
});
