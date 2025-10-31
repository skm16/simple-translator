/**
 * Simple Translator Quick Edit
 *
 * Handles quick edit functionality for translations
 */

(function($) {
    'use strict';

    /**
     * Quick Edit Handler
     */
    var STQuickEdit = {
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
            // Populate quick edit form when quick edit link is clicked
            $('#the-list').on('click', '.editinline', this.populateQuickEdit);
        },

        /**
         * Populate quick edit form with translation data
         */
        populateQuickEdit: function() {
            var $row = $(this).closest('tr');
            var postId = $row.attr('id').replace('post-', '');
            var $editRow = $('#edit-' + postId);

            // Get language from inline data
            var language = $row.find('.st-lang-indicator.st-current').text().toLowerCase().trim();

            if (language && $editRow.find('.st-language-select').length) {
                // Wait a moment for WordPress to populate the quick edit form
                setTimeout(function() {
                    $editRow.find('.st-language-select').val(language);
                }, 50);
            }
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        STQuickEdit.init();
    });

    /**
     * Export to global scope
     */
    window.STQuickEdit = STQuickEdit;

})(jQuery);
