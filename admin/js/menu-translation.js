/**
 * Menu Translation JavaScript
 *
 * Handles AJAX menu cloning functionality
 *
 * @package SimpleTranslator
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Clone menu button handler
        $('#st-clone-menu-btn').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $spinner = $button.next('.spinner');
            var $message = $('#st-menu-clone-message');
            var sourceMenuId = $('#st-source-menu').val();
            var targetLanguage = $('#st-target-language').val();

            // Validate inputs
            if (!sourceMenuId) {
                showMessage('error', stMenuTranslation.strings.error + ' ' + 'Please select a source menu.');
                return;
            }

            if (!targetLanguage) {
                showMessage('error', stMenuTranslation.strings.error + ' ' + 'Please select a target language.');
                return;
            }

            // Confirm action
            if (!confirm(stMenuTranslation.strings.confirmClone)) {
                return;
            }

            // Show loading state
            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            showMessage('info', stMenuTranslation.strings.cloning);

            // Send AJAX request
            $.ajax({
                url: stMenuTranslation.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'st_clone_menu',
                    nonce: stMenuTranslation.nonce,
                    source_menu_id: sourceMenuId,
                    target_language: targetLanguage
                },
                success: function(response) {
                    if (response.success) {
                        showMessage('success', response.data.message);

                        // Refresh the page after 2 seconds to show the new menu
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    } else {
                        showMessage('error', response.data.message || stMenuTranslation.strings.error);
                        $button.prop('disabled', false);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX Error:', textStatus, errorThrown);
                    showMessage('error', stMenuTranslation.strings.error + ' ' + textStatus);
                    $button.prop('disabled', false);
                },
                complete: function() {
                    $spinner.removeClass('is-active');
                }
            });
        });

        /**
         * Show a message to the user
         *
         * @param {string} type - Message type: 'success', 'error', 'warning', 'info'
         * @param {string} message - Message text to display
         */
        function showMessage(type, message) {
            var $message = $('#st-menu-clone-message');
            var iconClass = '';

            // Determine icon based on message type
            switch (type) {
                case 'success':
                    iconClass = 'dashicons-yes-alt';
                    break;
                case 'error':
                    iconClass = 'dashicons-dismiss';
                    break;
                case 'warning':
                    iconClass = 'dashicons-warning';
                    break;
                case 'info':
                default:
                    iconClass = 'dashicons-info';
                    break;
            }

            // Build message HTML
            var html = '<div class="notice notice-' + type + ' inline">' +
                       '<p>' +
                       '<span class="dashicons ' + iconClass + '"></span> ' +
                       message +
                       '</p>' +
                       '</div>';

            $message.html(html).slideDown('fast');

            // Auto-hide info messages after 5 seconds
            if (type === 'info') {
                setTimeout(function() {
                    $message.slideUp('fast');
                }, 5000);
            }
        }

        /**
         * Reset form to initial state
         */
        function resetForm() {
            $('#st-source-menu').val('');
            $('#st-target-language').val('');
            $('#st-clone-menu-btn').prop('disabled', false);
            $('#st-menu-clone-message').slideUp('fast').empty();
        }

        // Optional: Auto-hide success messages
        $(document).on('click', '.st-message .notice-dismiss', function() {
            $(this).closest('.st-message').slideUp('fast');
        });
    });

})(jQuery);
