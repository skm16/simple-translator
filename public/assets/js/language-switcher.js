/**
 * Simple Translator Frontend JavaScript
 *
 * Handles language switcher interactions
 */

(function($) {
    'use strict';

    /**
     * Language Switcher Handler
     */
    var STLanguageSwitcher = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.enhanceAccessibility();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Handle dropdown language selection
            $('.st-language-select').on('change', function() {
                var url = $(this).val();
                if (url) {
                    window.location.href = url;
                }
            });

            // Handle language link clicks with loading state
            $('.st-lang-link, .st-flag-link').on('click', function(e) {
                var $link = $(this);
                var $switcher = $link.closest('.st-language-switcher');

                // Add loading state
                $switcher.addClass('loading');

                // Store selected language in localStorage
                var lang = $link.data('lang');
                if (lang && typeof(Storage) !== "undefined") {
                    localStorage.setItem('st_preferred_language', lang);
                }
            });
        },

        /**
         * Enhance accessibility
         */
        enhanceAccessibility: function() {
            // Add ARIA labels to language selectors
            $('.st-language-select').attr('aria-label', 'Select Language');

            // Add role to language lists
            $('.st-language-switcher.st-list').attr('role', 'navigation')
                                              .attr('aria-label', 'Language Navigation');

            $('.st-language-switcher.st-flags').attr('role', 'navigation')
                                               .attr('aria-label', 'Language Navigation');

            // Add lang attribute to language links
            $('.st-lang-link, .st-flag-link').each(function() {
                var lang = $(this).data('lang');
                if (lang) {
                    $(this).attr('hreflang', lang);
                }
            });
        },

        /**
         * Get preferred language from storage
         */
        getPreferredLanguage: function() {
            if (typeof(Storage) !== "undefined") {
                return localStorage.getItem('st_preferred_language');
            }
            return null;
        },

        /**
         * Detect user language
         */
        detectUserLanguage: function() {
            if (navigator.language || navigator.userLanguage) {
                var browserLang = (navigator.language || navigator.userLanguage).substring(0, 2).toLowerCase();
                return browserLang;
            }
            return null;
        },

        /**
         * Suggest language based on browser settings
         */
        suggestLanguage: function() {
            var preferredLang = this.getPreferredLanguage();
            var browserLang = this.detectUserLanguage();
            var currentLang = stFrontend.currentLang;

            // If user hasn't selected a preference and browser language differs from current
            if (!preferredLang && browserLang && browserLang !== currentLang) {
                // Check if the browser language is available
                var $browserLangLink = $('.st-lang-link[data-lang="' + browserLang + '"], .st-flag-link[data-lang="' + browserLang + '"]');

                if ($browserLangLink.length > 0) {
                    // Show a subtle notice suggesting the browser language
                    this.showLanguageSuggestion(browserLang, $browserLangLink.attr('href'));
                }
            }
        },

        /**
         * Show language suggestion notice
         */
        showLanguageSuggestion: function(lang, url) {
            var langName = this.getLanguageName(lang);
            var message = 'This content is also available in ' + langName + '.';

            var $notice = $('<div class="st-translation-notice" role="alert">' +
                          '<p>' + message + ' <a href="' + url + '">Switch to ' + langName + '</a></p>' +
                          '</div>');

            // Insert after main content header or at top of content
            if ($('.entry-header').length) {
                $notice.insertAfter('.entry-header');
            } else if ($('.entry-content').length) {
                $notice.prependTo('.entry-content');
            }

            // Make dismissible
            $notice.on('click', 'a', function() {
                localStorage.setItem('st_preferred_language', lang);
            });
        },

        /**
         * Get language name (basic implementation)
         */
        getLanguageName: function(code) {
            var languages = {
                'en': 'English',
                'es': 'Spanish',
                'fr': 'French',
                'de': 'German',
                'it': 'Italian',
                'pt': 'Portuguese',
                'ru': 'Russian',
                'ja': 'Japanese',
                'zh': 'Chinese',
                'ar': 'Arabic'
            };

            return languages[code] || code.toUpperCase();
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        STLanguageSwitcher.init();

        // Optionally suggest language based on browser settings
        // Uncomment the line below to enable this feature
        // STLanguageSwitcher.suggestLanguage();
    });

    /**
     * Export to global scope for debugging
     */
    window.STLanguageSwitcher = STLanguageSwitcher;

})(jQuery);
