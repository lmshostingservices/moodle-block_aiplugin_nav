/**
 * Auto-update functionality for AI plugins.
 *
 * @module     block_aiplugin_nav/autoupdate
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/notification', 'core/str'], function ($, Ajax, Notification, Str) {
    return {
        init: function () {
            var self = this;
            
            // Handle auto-update button clicks.
            $(document).on('click', '.ainav-btn-autoupdate', function (e) {
                e.preventDefault();
                var $btn = $(this);
                var component = $btn.data('component');
                var downloadUrl = $btn.data('downloadurl');
                var pluginName = $btn.data('pluginname');
                
                self.autoUpdatePlugin($btn, component, downloadUrl, pluginName);
            });
            
            // Handle auto-update all button.
            $(document).on('click', '.ainav-btn-autoupdate-all', function (e) {
                e.preventDefault();
                self.autoUpdateAll();
            });

            // Handle "What's changed?" link clicks.
            $(document).on('click', '.ainav-whats-new-link', function (e) {
                e.preventDefault();
                var $link = $(this);
                var component = $link.data('component');
                var fromVersion = $link.data('from') || '';
                var pluginName = $link.data('pluginname') || component;
                self.showChangelog(component, fromVersion, pluginName);
            });

            // Inject "What's changed?" links next to update buttons/icons as they appear.
            self._watchForUpdateElements();
        },

        /**
         * Observe the DOM for update buttons/icons being made visible and inject
         * a "What's changed?" link next to each one.
         */
        _watchForUpdateElements: function () {
            var self = this;

            // Run once after a brief delay (version check is async, ~1-2 s).
            setTimeout(function () { self._injectChangelogLinks(); }, 1500);

            // Also watch for attribute/style mutations in case the version check
            // takes longer or runs multiple times.
            if (typeof MutationObserver !== 'undefined') {
                var observer = new MutationObserver(function () {
                    self._injectChangelogLinks();
                });
                observer.observe(document.body, {
                    attributes: true,
                    subtree: true,
                    attributeFilter: ['style', 'class']
                });
            }
        },

        /**
         * Find all visible update triggers and inject the changelog link if not
         * already present.
         */
        _injectChangelogLinks: function () {
            var self = this;

            // Sidebar: .ainav-btn-autoupdate
            $('.ainav-btn-autoupdate').each(function () {
                var $el = $(this);
                if ($el.data('wn-injected')) return;
                var component = $el.data('component');
                if (!component) return;
                $el.data('wn-injected', true);
                var pluginName = $el.data('pluginname') || component;
                var $link = $('<a href="#" class="ainav-whats-new-link" style="font-size:10px;display:block;text-align:center;margin-top:3px;opacity:0.75;">What\'s changed?</a>');
                $link.data('component', component).data('pluginname', pluginName);
                $el.after($link);
            });

            // Plugin manager: .ainav-pm-action-update (shown via JS)
            $('.ainav-pm-action-update:visible').each(function () {
                var $el = $(this);
                if ($el.data('wn-injected')) return;
                var component = $el.data('component');
                if (!component) return;
                $el.data('wn-injected', true);
                var $link = $('<a href="#" class="ainav-whats-new-link" style="font-size:10px;display:block;text-align:center;margin-top:2px;opacity:0.75;line-height:1.3;">What\'s changed?</a>');
                $link.data('component', component).data('pluginname', component);
                $el.closest('.ainav-pm-card').find('.ainav-pm-card-actions, .ainav-pm-card-footer').first().append($link);
                if (!$link.closest('.ainav-pm-card').length) {
                    // Fallback: insert directly after the icon
                    $el.after($link);
                }
            });
        },

        /**
         * Fetch changelog from lms-labs.com and display in a modal.
         * @param {string} component  Moodle component name, e.g. "mod_quiz_aigrader"
         * @param {string} fromVersion  Currently-installed version (used to filter entries)
         * @param {string} pluginName  Human-readable plugin name for the modal title
         */
        showChangelog: function (component, fromVersion, pluginName) {
            var apiBase = 'https://lms-labs.com';
            var url = apiBase + '/api/plugin-changelog/' + encodeURIComponent(component);
            if (fromVersion) url += '?from=' + encodeURIComponent(fromVersion);

            $.getJSON(url, function (data) {
                if (!data.ok || !data.entries || !data.entries.length) {
                    Notification.alert('What\'s Changed', 'No changelog available for this plugin yet. Check <a href="https://lms-labs.com/plugins" target="_blank">lms-labs.com/plugins</a> for the latest release notes.');
                    return;
                }

                var title = (pluginName || component) + ' — What\'s Changed';
                var html = '<div style="max-height:320px;overflow-y:auto;font-size:13px;line-height:1.5;">';
                data.entries.slice(0, 15).forEach(function (entry) {
                    // Highlight the version tag at the start
                    var safe = String(entry).replace(/[&<>"]/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; });
                    var formatted = safe.replace(/^(v[\d.]+(?:[:\s-]+))/i, '<strong>$1</strong>');
                    html += '<div style="padding:5px 0;border-bottom:1px solid #e5e7eb;">' + formatted + '</div>';
                });
                if (data.entries.length > 15) {
                    html += '<div style="padding:5px 0;opacity:0.6;font-size:11px;">+ ' + (data.entries.length - 15) + ' older entries — see <a href="https://lms-labs.com/plugins" target="_blank">lms-labs.com</a></div>';
                }
                html += '</div>';
                if (data.version) {
                    html += '<p style="margin-top:8px;font-size:11px;opacity:0.6;">Latest: v' + String(data.version).replace(/[&<>"]/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }) + '</p>';
                }

                Notification.alert(title, html);
            }).fail(function () {
                Notification.alert('What\'s Changed', 'Could not load changelog. Check your internet connection or visit <a href="https://lms-labs.com/plugins" target="_blank">lms-labs.com/plugins</a>.');
            });
        },
        
        autoUpdatePlugin: function ($btn, component, downloadUrl, pluginName) {
            var self = this;
            var originalText = $btn.text();
            
            // Disable button and show loading.
            $btn.prop('disabled', true);
            $btn.html('<span class="ainav-spinner"></span> Updating...');
            
            Ajax.call([{
                methodname: 'block_aiplugin_nav_auto_update_plugin',
                args: {
                    component: component,
                    downloadurl: downloadUrl,
                    expectedsha256: (function (v) { var t = String(v || ''); return /^[a-fA-F0-9]{64}$/.test(t) ? t : ''; }($btn.data('sha256')))
                }
            }])[0].done(function (response) {
                if (response.success) {
                    // Show brief success message.
                    $btn.removeClass('ainav-btn-autoupdate').addClass('ainav-btn-success');
                    $btn.html('<span class="ainav-spinner"></span> Redirecting...');
                    
                    // Auto-redirect to admin upgrade page after brief delay.
                    // This triggers Moodle's standard plugin upgrade process.
                    setTimeout(function () {
                        try {
                            var upgradeUrl = M.cfg.wwwroot + '/admin/index.php';
                            window.location.href = upgradeUrl;
                            // Fallback: if redirect doesn't work after 2 seconds, force reload
                            setTimeout(function () {
                                window.location.reload(true);
                            }, 2000);
                        } catch (e) {
                            // Fallback to page reload if redirect fails
                            window.location.reload(true);
                        }
                    }, 800);
                } else {
                    $btn.prop('disabled', false);
                    $btn.text(originalText);
                    Notification.alert('Update Failed', response.message);
                }
            }).fail(function (error) {
                $btn.prop('disabled', false);
                $btn.text(originalText);
                Notification.alert('Update Failed', error.message || 'An error occurred during update.');
            });
        },
        
        autoUpdateAll: function () {
            var self = this;
            var $buttons = $('.ainav-btn-autoupdate:visible');
            var total = $buttons.length;
            var completed = 0;
            
            if (total === 0) {
                Notification.alert('No Updates', 'No plugins to update.');
                return;
            }
            
            // Update each plugin sequentially to avoid conflicts.
            var updateNext = function (index) {
                if (index >= $buttons.length) {
                    // All done, auto-redirect to upgrade page.
                    setTimeout(function () {
                        try {
                            var upgradeUrl = M.cfg.wwwroot + '/admin/index.php';
                            window.location.href = upgradeUrl;
                            // Fallback: if redirect doesn't work after 2 seconds, force reload
                            setTimeout(function () {
                                window.location.reload(true);
                            }, 2000);
                        } catch (e) {
                            // Fallback to page reload if redirect fails
                            window.location.reload(true);
                        }
                    }, 800);
                    return;
                }
                
                var $btn = $($buttons[index]);
                var component = $btn.data('component');
                var downloadUrl = $btn.data('downloadurl');
                var pluginName = $btn.data('pluginname');
                
                $btn.prop('disabled', true);
                $btn.html('<span class="ainav-spinner"></span> Updating...');
                
                Ajax.call([{
                    methodname: 'block_aiplugin_nav_auto_update_plugin',
                    args: {
                        component: component,
                        downloadurl: downloadUrl,
                        expectedsha256: (function (v) { var t = String(v || ''); return /^[a-fA-F0-9]{64}$/.test(t) ? t : ''; }($btn.data('sha256')))
                    }
                }])[0].done(function (response) {
                    completed++;
                    if (response.success) {
                        $btn.removeClass('ainav-btn-autoupdate').addClass('ainav-btn-success');
                        $btn.text('Updated ✓');
                    } else {
                        $btn.prop('disabled', false);
                        $btn.text('Failed');
                    }
                    updateNext(index + 1);
                }).fail(function () {
                    $btn.prop('disabled', false);
                    $btn.text('Failed');
                    updateNext(index + 1);
                });
            };
            
            updateNext(0);
        },

        // Show a modal or notification prompting user to run database upgrade.
        showUpgradeModal: function () {
            var $overlay = $('<div class="ainav-upgrade-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9998;"></div>');
            var $modal = $(
                '<div class="ainav-upgrade-modal" style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:24px;border-radius:8px;z-index:9999;max-width:400px;text-align:center;">' +
                '<h3 style="margin:0 0 12px;">Plugin Updated</h3>' +
                '<p>The plugin has been updated. Please run the database upgrade to complete the process.</p>' +
                '<button class="ainav-upgrade-close btn btn-primary">Run Upgrade Now</button>' +
                '</div>'
            );
            $('body').append($overlay).append($modal);

            $modal.find('.ainav-upgrade-close').on('click', function () {
                $overlay.remove();
                $modal.remove();
                window.location.href = M.cfg.wwwroot + '/admin/index.php';
            });

            $overlay.on('click', function () {
                $overlay.remove();
                $modal.remove();
            });
        }
    };
});
