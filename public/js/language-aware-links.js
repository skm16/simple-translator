/**
 * Language Aware Links
 *
 * Handles site logo and home link clicks to redirect to language-appropriate homepage
 *
 * @package SimpleTranslator
 */

(function() {
    'use strict';

    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {

        // Get language data from localized script
        if (typeof stLanguageLinks === 'undefined') {
            return; // No language data available
        }

        const currentLang = stLanguageLinks.currentLanguage;
        const defaultLang = stLanguageLinks.defaultLanguage;
        const homeUrl = stLanguageLinks.homeUrl;

        // If we're on the default language, no need to intercept
        if (currentLang === defaultLang) {
            return;
        }

        // Build the language-specific home URL
        const langHomeUrl = homeUrl.replace(/\/$/, '') + '/' + currentLang + '/';

        /**
         * Check if a link points to the homepage
         */
        function isHomeLink(href) {
            if (!href) return false;

            // Normalize the href
            const normalized = href.replace(/\/$/, '');
            const normalizedHome = homeUrl.replace(/\/$/, '');

            // Check if it's the home URL
            return normalized === normalizedHome ||
                   normalized === normalizedHome + '/' ||
                   href === '/' ||
                   href === homeUrl;
        }

        /**
         * Handle click on home links
         */
        function handleHomeClick(event) {
            const link = event.currentTarget;
            const href = link.getAttribute('href');

            if (isHomeLink(href)) {
                // Prevent default navigation
                event.preventDefault();

                // Redirect to language-specific homepage
                window.location.href = langHomeUrl;
            }
        }

        // Find and intercept home links
        const selectors = [
            '.site-logo a',           // Common logo class
            '.site-title a',          // Common site title class
            '.custom-logo-link',      // WordPress custom logo
            '.site-branding a',       // Common branding wrapper
            '.site-brand a',          // Alternative branding
            'a[rel="home"]',          // Semantic home link
            '.navbar-brand',          // Bootstrap navbar
            '.logo a',                // Generic logo class
            '.brand a'                // Generic brand class
        ];

        // Add click handlers
        selectors.forEach(function(selector) {
            const links = document.querySelectorAll(selector);
            links.forEach(function(link) {
                if (link && isHomeLink(link.getAttribute('href'))) {
                    link.addEventListener('click', handleHomeClick);
                }
            });
        });

        // Also handle any direct links to home (fallback)
        const allLinks = document.querySelectorAll('a[href]');
        allLinks.forEach(function(link) {
            const href = link.getAttribute('href');
            if (isHomeLink(href) && !link.hasAttribute('data-st-processed')) {
                link.addEventListener('click', handleHomeClick);
                link.setAttribute('data-st-processed', 'true');
            }
        });

        // Debug logging if enabled
        if (stLanguageLinks.debug) {
            console.log('[Simple Translator] Language-aware links initialized', {
                currentLanguage: currentLang,
                defaultLanguage: defaultLang,
                homeUrl: homeUrl,
                langHomeUrl: langHomeUrl
            });
        }
    });

})();
