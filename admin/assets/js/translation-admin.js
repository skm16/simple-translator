/**
 * Simple Translator Admin JavaScript
 *
 * Handles AJAX interactions for translation management
 */

(function($) {
    'use strict';

    /**
     * Translation Admin Handler
     */
    var STAdmin = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Create translation button
            $(document).on('click', '.st-create-translation', this.createTranslation);

            // Status update select
            $(document).on('change', '.st-status-select', this.updateStatus);

            // Sync translation button
            $(document).on('click', '.st-sync-translation', this.syncTranslation);
        },

        /**
         * Create a new translation
         */
        createTranslation: function(e) {
            e.preventDefault();

            var $button = $(this);
            var sourceId = $button.data('source');
            var targetLang = $button.data('lang');

            // Disable button and show loading state
            $button.prop('disabled', true)
                   .addClass('loading')
                   .html('<span class="dashicons dashicons-update"></span> ' + stAdmin.strings.creating);

            // Send AJAX request
            $.ajax({
                url: stAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'st_create_translation',
                    source_id: sourceId,
                    target_lang: targetLang,
                    nonce: stAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        STAdmin.showNotice(response.data.message, 'success');

                        // Redirect to edit the new translation
                        window.location.href = response.data.edit_url;
                    } else {
                        // Show error message
                        STAdmin.showNotice(response.data, 'error');

                        // Re-enable button
                        $button.prop('disabled', false)
                               .removeClass('loading')
                               .html('<span class="dashicons dashicons-plus-alt"></span> ' +
                                     $button.text().replace(stAdmin.strings.creating, 'Create Translation'));
                    }
                },
                error: function(xhr, status, error) {
                    // Show error message
                    STAdmin.showNotice(stAdmin.strings.error + ': ' + error, 'error');

                    // Re-enable button
                    $button.prop('disabled', false)
                           .removeClass('loading')
                           .html('<span class="dashicons dashicons-plus-alt"></span> Create Translation');
                }
            });
        },

        /**
         * Update translation status
         */
        updateStatus: function() {
            var $select = $(this);
            var postId = $select.data('post');
            var status = $select.val();

            // Add loading class
            $select.addClass('loading');

            // Send AJAX request
            $.ajax({
                url: stAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'st_update_status',
                    post_id: postId,
                    status: status,
                    nonce: stAdmin.nonce
                },
                success: function(response) {
                    // Remove loading class
                    $select.removeClass('loading');

                    if (response.success) {
                        // Add saved class for visual feedback
                        $select.addClass('saved');

                        // Remove saved class after 2 seconds
                        setTimeout(function() {
                            $select.removeClass('saved');
                        }, 2000);

                        // Update status badge if present
                        var $statusBadge = $select.closest('.st-translations-metabox')
                                                  .find('.translation-status');
                        if ($statusBadge.length) {
                            $statusBadge.removeClass('status-not_started status-in_progress status-completed status-needs_update')
                                       .addClass('status-' + status)
                                       .text(status.replace('_', ' ').replace(/\b\w/g, function(l) {
                                           return l.toUpperCase();
                                       }));
                        }
                    } else {
                        STAdmin.showNotice(response.data, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    $select.removeClass('loading');
                    STAdmin.showNotice(stAdmin.strings.error + ': ' + error, 'error');
                }
            });
        },

        /**
         * Sync translation from source
         */
        syncTranslation: function(e) {
            e.preventDefault();

            var $button = $(this);
            var targetId = $button.data('target');

            // Confirm action
            if (!confirm('Are you sure you want to sync this translation? This will update taxonomies and metadata from the source post.')) {
                return;
            }

            // Disable button and show loading state
            $button.prop('disabled', true)
                   .addClass('loading')
                   .html('<span class="dashicons dashicons-update"></span> ' + stAdmin.strings.syncing);

            // Send AJAX request
            $.ajax({
                url: stAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'st_sync_translation',
                    target_id: targetId,
                    nonce: stAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        STAdmin.showNotice(response.data.message, 'success');

                        // Reload page to show updates
                        setTimeout(function() {
                            window.location.reload();
                        }, 1000);
                    } else {
                        // Show error message
                        STAdmin.showNotice(response.data, 'error');

                        // Re-enable button
                        $button.prop('disabled', false)
                               .removeClass('loading')
                               .html('<span class="dashicons dashicons-update"></span> Sync');
                    }
                },
                error: function(xhr, status, error) {
                    // Show error message
                    STAdmin.showNotice(stAdmin.strings.error + ': ' + error, 'error');

                    // Re-enable button
                    $button.prop('disabled', false)
                           .removeClass('loading')
                           .html('<span class="dashicons dashicons-update"></span> Sync');
                }
            });
        },

        /**
         * Show admin notice
         */
        showNotice: function(message, type) {
            type = type || 'info';

            var $notice = $('<div class="notice notice-' + type + ' is-dismissible">' +
                          '<p>' + message + '</p>' +
                          '<button type="button" class="notice-dismiss">' +
                          '<span class="screen-reader-text">Dismiss this notice.</span>' +
                          '</button>' +
                          '</div>');

            // Insert after first h1 or h2
            if ($('.wrap h1').length) {
                $notice.insertAfter('.wrap h1:first');
            } else if ($('.wrap h2').length) {
                $notice.insertAfter('.wrap h2:first');
            } else {
                $notice.prependTo('.wrap');
            }

            // Make dismissible work
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(function() {
                    $notice.remove();
                });
            });

            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $notice.remove();
                });
            }, 5000);
        },

        /**
         * Update translation progress bar
         */
        updateProgress: function() {
            var $metabox = $('.st-translations-metabox');
            var total = $metabox.find('.translation-item').length;
            var translated = $metabox.find('.translation-exists').length;

            if (total > 0) {
                var percentage = Math.round((translated / total) * 100);
                var $progressBar = $metabox.find('.st-progress-bar');
                var $progressPercent = $metabox.find('.st-progress-percent');

                if ($progressBar.length) {
                    $progressBar.css('width', percentage + '%');
                    $progressPercent.text(percentage + '%');

                    // Update progress bar color based on completion
                    $progressBar.removeClass('progress-low progress-medium progress-high');
                    if (percentage < 33) {
                        $progressBar.addClass('progress-low');
                    } else if (percentage < 66) {
                        $progressBar.addClass('progress-medium');
                    } else {
                        $progressBar.addClass('progress-high');
                    }
                }
            }
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        STAdmin.init();
        STAdmin.updateProgress();
    });

    /**
     * Export to global scope for debugging
     */
    window.STAdmin = STAdmin;

})(jQuery);
