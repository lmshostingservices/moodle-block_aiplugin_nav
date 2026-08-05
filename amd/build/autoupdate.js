/**
 * Auto-update functionality for AI plugins.
 *
 * @module     block_aiplugin_nav/autoupdate
 * @copyright  2025 Essay Grader AI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/notification', 'core/str'], function($, Ajax, Notification, Str) {
    return {
        init: function() {
            var self = this;
            
            // Handle auto-update button clicks.
            $(document).on('click', '.ainav-btn-autoupdate', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var component = $btn.data('component');
                var downloadUrl = $btn.data('downloadurl');
                var pluginName = $btn.data('pluginname');
                
                self.autoUpdatePlugin($btn, component, downloadUrl, pluginName);
            });
            
            // Handle auto-update all button.
            $(document).on('click', '.ainav-btn-autoupdate-all', function(e) {
                e.preventDefault();
                self.autoUpdateAll();
            });
        },
        
        autoUpdatePlugin: function($btn, component, downloadUrl, pluginName) {
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
                    expectedsha256: $btn.data('sha256') || ''
                }
            }])[0].done(function(response) {
                if (response.success) {
                    // Show brief success message.
                    $btn.removeClass('ainav-btn-autoupdate').addClass('ainav-btn-success');
                    $btn.html('<span class="ainav-spinner"></span> Redirecting...');
                    
                    // Auto-redirect to admin upgrade page after brief delay.
                    // This triggers Moodle's standard plugin upgrade process.
                    setTimeout(function() {
                        try {
                            var upgradeUrl = M.cfg.wwwroot + '/admin/index.php';
                            window.location.href = upgradeUrl;
                            // Fallback: if redirect doesn't work after 2 seconds, force reload
                            setTimeout(function() {
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
            }).fail(function(error) {
                $btn.prop('disabled', false);
                $btn.text(originalText);
                Notification.alert('Update Failed', error.message || 'An error occurred during update.');
            });
        },
        
        autoUpdateAll: function() {
            var self = this;
            var $buttons = $('.ainav-btn-autoupdate:visible');
            var total = $buttons.length;
            var completed = 0;
            
            if (total === 0) {
                Notification.alert('No Updates', 'No plugins to update.');
                return;
            }
            
            // Update each plugin sequentially to avoid conflicts.
            var updateNext = function(index) {
                if (index >= $buttons.length) {
                    // All done, auto-redirect to upgrade page.
                    setTimeout(function() {
                        try {
                            var upgradeUrl = M.cfg.wwwroot + '/admin/index.php';
                            window.location.href = upgradeUrl;
                            // Fallback: if redirect doesn't work after 2 seconds, force reload
                            setTimeout(function() {
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
                        expectedsha256: $btn.data('sha256') || ''
                    }
                }])[0].done(function(response) {
                    if (response.success) {
                        $btn.removeClass('ainav-btn-autoupdate').addClass('ainav-btn-success');
                        $btn.html('<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg> Updated!');
                    } else {
                        $btn.html('Failed');
                    }
                    updateNext(index + 1);
                }).fail(function() {
                    $btn.html('Failed');
                    updateNext(index + 1);
                });
            };
            
            updateNext(0);
        },
        
        showUpgradePrompt: function() {
            // Show a modal or notification prompting user to run database upgrade.
            var $overlay = $('<div class="ainav-upgrade-overlay"></div>');
            var $modal = $(
                '<div class="ainav-upgrade-modal">' +
                '<div class="ainav-upgrade-icon">' +
                '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>' +
                '</div>' +
                '<h3 class="ainav-upgrade-title">Plugins Updated!</h3>' +
                '<p class="ainav-upgrade-message">Plugin files have been updated. Click below to run the database upgrade.</p>' +
                '<div class="ainav-upgrade-actions">' +
                '<a href="' + M.cfg.wwwroot + '/admin/index.php" class="ainav-btn-primary">Run Database Upgrade</a>' +
                '<button type="button" class="ainav-btn-secondary ainav-upgrade-close">Close</button>' +
                '</div>' +
                '</div>'
            );
            
            $('body').append($overlay).append($modal);
            
            $overlay.on('click', function() {
                $overlay.remove();
                $modal.remove();
            });
            
            $modal.find('.ainav-upgrade-close').on('click', function() {
                $overlay.remove();
                $modal.remove();
            });
        }
    };
});
