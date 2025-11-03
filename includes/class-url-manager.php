<?php
/**
 * URL Manager Class - COMPLETE FIX
 *
 * Handles language detection and URL rewriting
 * This version properly maps clean URLs to suffixed slugs
 *
 * @package SimpleTranslator
 */

namespace SimpleTranslator;

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * URL Manager - Language detection and URL handling
 */
class URL_Manager {

    /**
     * Enabled languages
     *
     * @var array
     */
    private $languages = array();

    /**
     * Default language
     *
     * @var string
     */
    private $default_language = 'en';

    /**
     * Current language
     *
     * @var string
     */
    private $current_language = null;

    /**
     * Source of current language detection
     * Possible values: 'query_var', 'get_param', 'url_path', 'cookie', 'default'
     *
     * @var string
     */
    private $current_language_source = null;

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Constructor
     */
    public function __construct() {
        $this->languages = get_option('st_enabled_languages', array('en', 'es'));
        $this->default_language = get_option('st_default_language', 'en');

        // Initialize logger
        $this->logger = new Logger();
        $this->logger->init();
    }

    /**
     * Add rewrite rules for language prefixes
     */
    public function add_rewrite_rules() {
        // Only add rules for non-default languages
        $languages = array_diff($this->languages, array($this->default_language));

        $this->logger->debug('Adding rewrite rules for languages', array(
            'all_languages' => $this->languages,
            'default_language' => $this->default_language,
            'non_default_languages' => $languages
        ));

        if (empty($languages)) {
            $this->logger->debug('No non-default languages found, skipping rewrite rules', array(
                'default_language' => $this->default_language
            ));
            return;
        }

        // Create regex for language codes
        $lang_regex = implode('|', array_map('preg_quote', $languages));

        $this->logger->debug('Created language regex pattern', array(
            'pattern' => $lang_regex,
            'languages_count' => count($languages)
        ));

        // Add language prefix rules
        // Homepage with language: /es/
        add_rewrite_rule(
            '^(' . $lang_regex . ')/?$',
            'index.php?lang=$matches[1]',
            'top'
        );

        // Any page with language: /es/about/
        add_rewrite_rule(
            '^(' . $lang_regex . ')/(.+?)/?$',
            'index.php?lang=$matches[1]&pagename=$matches[2]',
            'top'
        );

        // Posts with language: /es/2024/01/post-name/
        add_rewrite_rule(
            '^(' . $lang_regex . ')/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/([^/]+)/?$',
            'index.php?lang=$matches[1]&year=$matches[2]&monthnum=$matches[3]&day=$matches[4]&name=$matches[5]',
            'top'
        );

        add_rewrite_rule(
            '^(' . $lang_regex . ')/([0-9]{4})/([0-9]{1,2})/([^/]+)/?$',
            'index.php?lang=$matches[1]&year=$matches[2]&monthnum=$matches[3]&name=$matches[4]',
            'top'
        );

        // Category archives with language: /es/category/news/
        add_rewrite_rule(
            '^(' . $lang_regex . ')/category/(.+?)/?$',
            'index.php?lang=$matches[1]&category_name=$matches[2]',
            'top'
        );

        // Tag archives with language: /es/tag/news/
        add_rewrite_rule(
            '^(' . $lang_regex . ')/tag/(.+?)/?$',
            'index.php?lang=$matches[1]&tag=$matches[2]',
            'top'
        );

        // Author archives with language: /es/author/john/
        add_rewrite_rule(
            '^(' . $lang_regex . ')/author/(.+?)/?$',
            'index.php?lang=$matches[1]&author_name=$matches[2]',
            'top'
        );

        $this->logger->debug('Rewrite rules added successfully', array(
            'rules_added' => array(
                'homepage' => '^(' . $lang_regex . ')/?$',
                'pages' => '^(' . $lang_regex . ')/(.+?)/?$',
                'posts_day' => '^(' . $lang_regex . ')/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/([^/]+)/?$',
                'posts_month' => '^(' . $lang_regex . ')/([0-9]{4})/([0-9]{1,2})/([^/]+)/?$',
                'category' => '^(' . $lang_regex . ')/category/(.+?)/?$',
                'tag' => '^(' . $lang_regex . ')/tag/(.+?)/?$',
                'author' => '^(' . $lang_regex . ')/author/(.+?)/?$'
            ),
            'priority' => 'top'
        ));
    }

    /**
     * Parse request to map clean URLs to actual post slugs with language suffixes
     * CRITICAL: This is what makes clean URLs work with suffixed slugs
     *
     * @param array $query_vars Query variables
     * @return array Modified query variables
     */
    public function parse_request($query_vars) {
        // Only process if we have a language parameter
        if (!isset($query_vars['lang'])) {
            return $query_vars;
        }

        $lang = $query_vars['lang'];

        $this->logger->debug('Parsing URL request - input', array(
            'lang' => $lang,
            'pagename' => isset($query_vars['pagename']) ? $query_vars['pagename'] : 'none',
            'name' => isset($query_vars['name']) ? $query_vars['name'] : 'none',
            'post_type' => isset($query_vars['post_type']) ? $query_vars['post_type'] : 'none'
        ));

        // Handle pagename (pages and hierarchical post types)
        if (isset($query_vars['pagename']) && !empty($query_vars['pagename'])) {
            $original_pagename = $query_vars['pagename'];
            $query_vars['pagename'] = $this->add_language_suffix_to_path($query_vars['pagename'], $lang);

            $this->logger->debug('Modified pagename with language suffix', array(
                'original' => $original_pagename,
                'modified' => $query_vars['pagename'],
                'lang' => $lang
            ));
        }

        // Handle name (posts)
        if (isset($query_vars['name']) && !empty($query_vars['name'])) {
            $original_name = $query_vars['name'];
            $query_vars['name'] = $this->add_language_suffix($query_vars['name'], $lang);

            $this->logger->debug('Modified name with language suffix', array(
                'original' => $original_name,
                'modified' => $query_vars['name'],
                'lang' => $lang
            ));
        }

        $this->logger->debug('Parse request - final query vars', array(
            'pagename' => isset($query_vars['pagename']) ? $query_vars['pagename'] : 'none',
            'name' => isset($query_vars['name']) ? $query_vars['name'] : 'none'
        ));

        return $query_vars;
    }

    /**
     * Add language suffix to a URL path (handles hierarchical pages)
     *
     * @param string $path Path like "parent/child"
     * @param string $lang Language code
     * @return string Path with language suffixes like "parent-es/child-es"
     */
    /**
     * Get a page by slug with optional parent constraint
     *
     * @param string $slug   Page slug to search for
     * @param int    $parent Parent page ID (0 for top-level pages)
     * @param string $lang   Language code
     * @return WP_Post|null Post object if found, null otherwise
     */
    private function get_page_by_slug_and_parent($slug, $parent = null, $lang = null) {
        global $wpdb;

        $this->logger->debug('Looking up page by slug and parent', array(
            'slug' => $slug,
            'parent' => $parent,
            'lang' => $lang
        ));

        // Build query
        $query = "SELECT ID, post_name, post_parent FROM {$wpdb->posts} WHERE post_name = %s AND post_type IN ('page', 'post') AND post_status = 'publish'";
        $params = array($slug);

        // Add parent constraint if specified
        if ($parent !== null) {
            $query .= " AND post_parent = %d";
            $params[] = $parent;
        }

        // Add language constraint if specified
        if ($lang !== null) {
            $query .= " AND ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_language' AND meta_value = %s)";
            $params[] = $lang;
        }

        $query .= " LIMIT 1";

        $post_id = $wpdb->get_var($wpdb->prepare($query, $params));

        if ($post_id) {
            $post = get_post($post_id);
            $this->logger->debug('Found page', array(
                'post_id' => $post_id,
                'post_name' => $post->post_name,
                'post_parent' => $post->post_parent
            ));
            return $post;
        }

        $this->logger->debug('Page not found');
        return null;
    }

    /**
     * Add language suffix to a hierarchical path
     * Handles parent/child page structures by looking up actual translations
     *
     * @param string $path Hierarchical path (e.g., "parent/child")
     * @param string $lang Language code
     * @return string Path with language-appropriate slugs
     */
    private function add_language_suffix_to_path($path, $lang) {
        $this->logger->debug('Adding language suffix to hierarchical path', array(
            'input_path' => $path,
            'lang' => $lang
        ));

        // Split path into segments
        $segments = explode('/', trim($path, '/'));

        if (empty($segments)) {
            return $path;
        }

        // Process each segment hierarchically
        $parent_id = 0; // Start at root level
        $translated_segments = array();

        foreach ($segments as $segment) {
            if (empty($segment)) {
                continue;
            }

            $this->logger->debug('Processing segment', array(
                'segment' => $segment,
                'parent_id' => $parent_id,
                'lang' => $lang
            ));

            // Try to find a page with this slug at this level in the target language
            $translated_page = $this->get_page_by_slug_and_parent($segment . '-' . $lang, $parent_id, $lang);

            if ($translated_page) {
                // Found the translation with language suffix
                $translated_segments[] = $translated_page->post_name;
                $parent_id = $translated_page->ID;

                $this->logger->debug('Found translation with suffix', array(
                    'original_segment' => $segment,
                    'translated_slug' => $translated_page->post_name,
                    'page_id' => $translated_page->ID
                ));
            } else {
                // Try without suffix (might be default language or custom slug)
                $page = $this->get_page_by_slug_and_parent($segment, $parent_id, null);

                if ($page) {
                    // Check if this page has a translation in the target language
                    $plugin = \SimpleTranslator\Plugin::get_instance();
                    $translation_id = $plugin->clone_manager->get_translation($page->ID, $lang);

                    if ($translation_id) {
                        $translated_page = get_post($translation_id);
                        if ($translated_page) {
                            $translated_segments[] = $translated_page->post_name;
                            $parent_id = $translated_page->ID;

                            $this->logger->debug('Found translation via translation mapping', array(
                                'original_page_id' => $page->ID,
                                'translation_id' => $translation_id,
                                'translated_slug' => $translated_page->post_name
                            ));
                            continue;
                        }
                    }

                    // No translation found, use original
                    $translated_segments[] = $segment;
                    $parent_id = $page->ID;

                    $this->logger->debug('No translation found, using original', array(
                        'segment' => $segment,
                        'page_id' => $page->ID
                    ));
                } else {
                    // Page not found at all, return original segment
                    $translated_segments[] = $segment;

                    $this->logger->debug('Page not found at this level', array(
                        'segment' => $segment,
                        'parent_id' => $parent_id
                    ));

                    // Can't continue hierarchical lookup if this level doesn't exist
                    break;
                }
            }
        }

        $result = implode('/', $translated_segments);

        $this->logger->debug('Path translation complete', array(
            'input_path' => $path,
            'output_path' => $result,
            'lang' => $lang
        ));

        return $result;
    }

    /**
     * Add language suffix to a single slug
     *
     * @param string $slug Slug without suffix
     * @param string $lang Language code
     * @return string Slug with language suffix
     */
    private function add_language_suffix($slug, $lang) {
        $this->logger->debug('Adding language suffix to slug', array(
            'input_slug' => $slug,
            'lang' => $lang,
            'already_has_suffix' => (substr($slug, -strlen($lang) - 1) === '-' . $lang)
        ));

        // Check if the slug already has the language suffix
        if (substr($slug, -strlen($lang) - 1) === '-' . $lang) {
            $this->logger->debug('Slug already has language suffix', array(
                'slug' => $slug,
                'lang' => $lang
            ));
            return $slug;
        }

        // PRIMARY PATTERN: Try the expected pattern first (e.g., "english-2-es")
        // This is the correct pattern for new translations
        $suffixed_slug = $slug . '-' . $lang;

        $this->logger->debug('Checking for post with primary pattern', array(
            'original_slug' => $slug,
            'suffixed_slug' => $suffixed_slug,
            'lang' => $lang,
            'pattern' => 'slug-lang'
        ));

        // Try to find the post with this slug
        $post = get_page_by_path($suffixed_slug, OBJECT, get_post_types());

        if ($post) {
            $this->logger->debug('Found post with primary pattern', array(
                'suffixed_slug' => $suffixed_slug,
                'post_id' => $post->ID,
                'post_status' => $post->post_status,
                'post_type' => $post->post_type,
                'returning_slug' => $suffixed_slug
            ));
            return $suffixed_slug;
        }

        // FALLBACK PATTERN: Try old pattern for backward compatibility (e.g., "english-es-2")
        // This handles translations created before the slug generation fix
        // Check if slug contains a number (e.g., "english-2")
        if (preg_match('/^(.+)-(\d+)$/', $slug, $matches)) {
            $base = $matches[1];  // e.g., "english"
            $number = $matches[2]; // e.g., "2"

            // Try pattern: base-lang-number (e.g., "english-es-2")
            $fallback_slug = $base . '-' . $lang . '-' . $number;

            $this->logger->debug('Trying fallback pattern for old translations', array(
                'original_slug' => $slug,
                'base' => $base,
                'number' => $number,
                'fallback_slug' => $fallback_slug,
                'pattern' => 'base-lang-number'
            ));

            $post = get_page_by_path($fallback_slug, OBJECT, get_post_types());

            if ($post) {
                $this->logger->debug('Found post with fallback pattern', array(
                    'fallback_slug' => $fallback_slug,
                    'post_id' => $post->ID,
                    'post_status' => $post->post_status,
                    'post_type' => $post->post_type,
                    'returning_slug' => $fallback_slug
                ));
                return $fallback_slug;
            }
        }

        $this->logger->debug('Post not found with any pattern', array(
            'searched_primary' => $suffixed_slug,
            'searched_fallback' => isset($fallback_slug) ? $fallback_slug : 'n/a',
            'returning_original' => $slug,
            'reason' => 'might_be_default_language_or_not_translated'
        ));

        // If not found, return original (might be default language or not translated)
        return $slug;
    }

    /**
     * Get URL for a specific language version
     *
     * @param int    $post_id Post ID
     * @param string $lang    Language code
     * @return string|false Translation URL or false if not found
     */
    public function get_translation_url($post_id, $lang) {
        $clone_manager = new Clone_Manager();
        $translations = $clone_manager->get_translations($post_id);

        if (!isset($translations[$lang])) {
            return false;
        }

        $translation_id = $translations[$lang];
        $url = get_permalink($translation_id);

        if (!$url) {
            return false;
        }

        // The filter_post_link will handle making it clean
        return $url;
    }

    /**
     * Get current language from URL or query string
     *
     * @return string Current language code
     */
    public function get_current_language() {
        // Return cached value if available
        if (null !== $this->current_language) {
            return $this->current_language;
        }

        // Check query string first (fallback method)
        // Only use get_query_var if WordPress is fully loaded
        global $wp_query;
        if ($wp_query) {
            $lang = get_query_var('lang');
            if ($lang && in_array($lang, $this->languages, true)) {
                $this->current_language = $lang;
                $this->current_language_source = 'query_var';
                $this->logger->debug('Current language detected from query var', array(
                    'lang' => $lang,
                    'source' => 'query_var'
                ));
                return $this->current_language;
            }
        } elseif (isset($_GET['lang']) && in_array($_GET['lang'], $this->languages, true)) {
            // Fallback if WordPress not fully loaded yet
            $this->current_language = sanitize_text_field($_GET['lang']);
            $this->current_language_source = 'get_param';
            $this->logger->debug('Current language detected from GET param', array(
                'lang' => $this->current_language,
                'source' => 'get_param'
            ));
            return $this->current_language;
        }

        // Check URL path (sanitize to prevent injection)
        $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw($_SERVER['REQUEST_URI']) : '';
        $path = trim(parse_url($request_uri, PHP_URL_PATH), '/');

        // Empty path means homepage - use default language
        if (empty($path)) {
            $this->current_language = $this->default_language;
            $this->current_language_source = 'url_default_homepage';
            $this->logger->debug('Current language detected as default (homepage)', array(
                'lang' => $this->default_language,
                'source' => 'url_default_homepage'
            ));
            return $this->current_language;
        }

        // If we have a path, check if it starts with a language code
        $parts = explode('/', $path);

        // Check if first part is a language code
        if (!empty($parts[0]) && in_array($parts[0], $this->languages, true)) {
            $this->current_language = $parts[0];
            $this->current_language_source = 'url_path';
            $this->logger->debug('Current language detected from URL path', array(
                'lang' => $parts[0],
                'source' => 'url_path',
                'path' => $path
            ));
            return $this->current_language;
        }

        // If path doesn't start with a language code, it's default language
        // This handles URLs like /hello/ which are English (default) pages
        $this->current_language = $this->default_language;
        $this->current_language_source = 'url_default';
        $this->logger->debug('Current language detected as default (no language prefix in URL)', array(
            'lang' => $this->default_language,
            'source' => 'url_default',
            'path' => $path
        ));
        return $this->current_language;

        // Check session/cookie for language preference (only for homepage without path)
        if (isset($_COOKIE['st_language']) && in_array($_COOKIE['st_language'], $this->languages, true)) {
            $this->current_language = $_COOKIE['st_language'];
            $this->current_language_source = 'cookie';
            $this->logger->debug('Current language detected from cookie', array(
                'lang' => $this->current_language,
                'source' => 'cookie'
            ));
            return $this->current_language;
        }

        // Default to site's default language
        $this->current_language = $this->default_language;
        $this->current_language_source = 'default';
        $this->logger->debug('Current language defaulted', array(
            'lang' => $this->default_language,
            'source' => 'default',
            'request_uri' => $request_uri
        ));
        return $this->current_language;
    }

    /**
     * Get language switcher URLs for the current page
     *
     * Returns an array of language code => URL pairs for all enabled languages
     *
     * @return array Language codes mapped to URLs
     */
    public function get_switcher_urls() {
        $urls = array();
        $current_post = get_queried_object();

        $this->logger->debug('Getting switcher URLs', array(
            'current_post_id' => $current_post ? (isset($current_post->ID) ? $current_post->ID : 'no-id') : 'null',
            'current_post_type' => $current_post ? (isset($current_post->post_type) ? $current_post->post_type : 'no-type') : 'null'
        ));

        // If we have a post, get its language
        $current_post_lang = null;
        if ($current_post && isset($current_post->ID)) {
            $current_post_lang = get_post_meta($current_post->ID, '_language', true);
            if (!$current_post_lang) {
                $current_post_lang = $this->default_language;
            }

            $this->logger->debug('Current post language', array(
                'post_id' => $current_post->ID,
                'language' => $current_post_lang
            ));
        }

        foreach ($this->languages as $lang) {
            // If this is the current post's language, use current permalink
            if ($current_post && isset($current_post->ID) && $lang === $current_post_lang) {
                $urls[$lang] = get_permalink($current_post->ID);

                $this->logger->debug('Switcher URL for current language', array(
                    'lang' => $lang,
                    'post_id' => $current_post->ID,
                    'url' => $urls[$lang]
                ));
            } elseif ($current_post && isset($current_post->ID)) {
                // Different language - look up translation
                $plugin = \SimpleTranslator\Plugin::get_instance();
                $translation_id = $plugin->clone_manager->get_translation($current_post->ID, $lang);

                if ($translation_id) {
                    $urls[$lang] = get_permalink($translation_id);

                    $this->logger->debug('Switcher URL for language (translation found)', array(
                        'lang' => $lang,
                        'source_post_id' => $current_post->ID,
                        'source_lang' => $current_post_lang,
                        'translation_id' => $translation_id,
                        'url' => $urls[$lang]
                    ));
                } else {
                    // No translation exists, link to home page for that language
                    if ($lang === $this->default_language) {
                        $urls[$lang] = home_url('/');
                    } else {
                        $urls[$lang] = trailingslashit(home_url()) . $lang . '/';
                    }

                    $this->logger->debug('Switcher URL for language (no translation)', array(
                        'lang' => $lang,
                        'source_post_id' => $current_post->ID,
                        'source_lang' => $current_post_lang,
                        'url' => $urls[$lang]
                    ));
                }
            } else {
                // Not a post/page, link to language home
                if ($lang === $this->default_language) {
                    $urls[$lang] = home_url('/');
                } else {
                    $urls[$lang] = trailingslashit(home_url()) . $lang . '/';
                }

                $this->logger->debug('Switcher URL for language (home)', array(
                    'lang' => $lang,
                    'url' => $urls[$lang]
                ));
            }
        }

        return $urls;
    }

    /**
     * Filter post queries to show only current language
     *
     * @param \WP_Query $query WordPress query object
     */
    public function filter_queries($query) {
        // Don't filter admin queries
        if (is_admin()) {
            return;
        }

        // Only filter main query
        if (!$query->is_main_query()) {
            return;
        }

        // Get current language
        $current_lang = $this->get_current_language();

        $this->logger->debug('Filtering main query by language', array(
            'current_lang' => $current_lang,
            'lang_source' => $this->current_language_source,
            'query_vars' => array(
                'pagename' => $query->get('pagename'),
                'name' => $query->get('name'),
                'page_id' => $query->get('page_id'),
                'p' => $query->get('p'),
            ),
            'is_singular' => $query->is_singular(),
            'is_page' => $query->is_page(),
        ));

        // CRITICAL: Only filter by language if it was explicitly specified in the URL
        // Don't filter when language comes from cookie or default, as this causes 404s
        // for pages accessed without language prefix (e.g., /hello/ instead of /es/hello/)
        if (!in_array($this->current_language_source, array('query_var', 'get_param', 'url_path'), true)) {
            $this->logger->debug('Skipping language filter - language not explicit in URL', array(
                'source' => $this->current_language_source,
                'current_lang' => $current_lang
            ));
            return;
        }

        // Get existing meta query
        $meta_query = $query->get('meta_query');
        if (!is_array($meta_query)) {
            $meta_query = array();
        }

        // Add language filter
        $meta_query[] = array(
            'relation' => 'OR',
            array(
                'key'     => '_language',
                'value'   => $current_lang,
                'compare' => '='
            ),
            array(
                'key'     => '_language',
                'compare' => 'NOT EXISTS'
            )
        );

        $query->set('meta_query', $meta_query);

        $this->logger->debug('Applied language meta query', array(
            'current_lang' => $current_lang,
            'meta_query_count' => count($meta_query)
        ));
    }

    /**
     * Modify permalink for translations to show clean URLs
     * CRITICAL: Removes language suffix from URLs
     *
     * @param string  $permalink Post permalink
     * @param \WP_Post $post      Post object
     * @return string Modified permalink
     */
    public function filter_post_link($permalink, $post) {
        // Handle case where $post is an integer (post ID) instead of object
        if (is_numeric($post)) {
            $post_id = (int) $post;
            $post = get_post($post_id);

            $this->logger->debug('filter_post_link received post ID, loading post object', array(
                'post_id' => $post_id,
                'post_loaded' => !empty($post),
                'permalink' => $permalink
            ));

            if (!$post) {
                $this->logger->warning('Could not load post from ID', array(
                    'post_id' => $post_id,
                    'permalink' => $permalink
                ));
                return $permalink;
            }
        }

        // Defensive check: Ensure we have a valid post object
        if (!$post || !is_object($post)) {
            $this->logger->warning('filter_post_link called with invalid post', array(
                'post_type' => gettype($post),
                'permalink' => $permalink
            ));
            return $permalink;
        }

        // If post ID is missing, try to load the post
        if (empty($post->ID)) {
            // Try to extract post ID from permalink
            $post_id = url_to_postid($permalink);

            if ($post_id) {
                $post = get_post($post_id);
                $this->logger->debug('Loaded post from permalink', array(
                    'extracted_post_id' => $post_id,
                    'post_loaded' => !empty($post)
                ));
            }

            // If still no valid post, return original permalink
            if (!$post || empty($post->ID)) {
                $this->logger->warning('Could not load post object', array(
                    'permalink' => $permalink,
                    'tried_url_to_postid' => $post_id ?? 'failed'
                ));
                return $permalink;
            }
        }

        // Get post language
        $lang = get_post_meta($post->ID, '_language', true);

        $this->logger->debug('Filtering permalink - input', array(
            'post_id' => $post->ID,
            'post_slug' => $post->post_name,
            'post_type' => $post->post_type,
            'post_status' => $post->post_status,
            'lang_meta' => $lang,
            'default_lang' => $this->default_language,
            'original_permalink' => $permalink
        ));

        // If no language set or default language, return original
        if (!$lang || $lang === $this->default_language) {
            $this->logger->debug('No permalink filtering needed', array(
                'reason' => !$lang ? 'no_language_meta' : 'is_default_language',
                'post_id' => $post->ID
            ));
            return $permalink;
        }

        // Remove the language suffix from the URL (e.g., -es, -fr)
        $after_suffix_removal = $permalink;
        $after_suffix_removal = str_replace('-' . $lang . '/', '/', $after_suffix_removal);
        $after_suffix_removal = str_replace('-' . $lang, '', $after_suffix_removal);

        $this->logger->debug('Removed language suffix from permalink', array(
            'before' => $permalink,
            'after' => $after_suffix_removal,
            'lang_suffix' => '-' . $lang
        ));

        // Add language prefix
        $home_url = trailingslashit(home_url());
        $permalink = str_replace($home_url, $home_url . $lang . '/', $after_suffix_removal);

        $this->logger->debug('Added language prefix to permalink', array(
            'before' => $after_suffix_removal,
            'after' => $permalink,
            'home_url' => $home_url,
            'lang_prefix' => $lang . '/'
        ));

        // Special handling for homepage
        $page_on_front = get_option('page_on_front');
        if ($page_on_front && $post->ID) {
            $clone_manager = new Clone_Manager();
            $translations = $clone_manager->get_translations($page_on_front);

            if (isset($translations[$lang]) && $translations[$lang] == $post->ID) {
                // This is a homepage translation
                $this->logger->debug('Homepage translation detected', array(
                    'post_id' => $post->ID,
                    'page_on_front' => $page_on_front,
                    'lang' => $lang,
                    'homepage_url' => $home_url . $lang . '/'
                ));
                return $home_url . $lang . '/';
            }
        }

        $this->logger->debug('Filtering permalink - final result', array(
            'post_id' => $post->ID,
            'post_slug' => $post->post_name,
            'lang' => $lang,
            'final_permalink' => $permalink
        ));

        return $permalink;
    }

    /**
     * Get home URL for specific language
     *
     * @param string $lang Language code
     * @return string
     */
    public function get_home_url($lang = null) {
        if (null === $lang) {
            $lang = $this->get_current_language();
        }

        $home_url = home_url('/');

        if ($lang !== $this->default_language) {
            $home_url = trailingslashit($home_url) . $lang . '/';
        }

        return $home_url;
    }

    /**
     * Remove language prefix from URL
     *
     * @param string $url URL to clean
     * @return string Cleaned URL
     */
    public function remove_language_prefix($url) {
        $home_url = trailingslashit(home_url());

        foreach ($this->languages as $lang) {
            if ($lang === $this->default_language) {
                continue;
            }

            $lang_url = $home_url . $lang . '/';
            if (strpos($url, $lang_url) === 0) {
                $url = str_replace($lang_url, $home_url, $url);
                break;
            }
        }

        return $url;
    }

    /**
     * Set language cookie
     *
     * @param string $lang Language code
     */
    public function set_language_cookie($lang) {
        if (!in_array($lang, $this->languages, true)) {
            return;
        }

        setcookie(
            'st_language',
            $lang,
            time() + (86400 * 30), // 30 days
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true // HttpOnly
        );
    }

    /**
     * Get available languages
     *
     * @return array
     */
    public function get_languages() {
        return $this->languages;
    }

    /**
     * Get default language
     *
     * @return string
     */
    public function get_default_language() {
        return $this->default_language;
    }

    /**
     * Check if current request is for a specific language
     *
     * @param string $lang Language code to check
     * @return bool
     */
    public function is_language($lang) {
        return $this->get_current_language() === $lang;
    }

    /**
     * Check if current request is for default language
     *
     * @return bool
     */
    public function is_default_language() {
        return $this->get_current_language() === $this->default_language;
    }

    /**
     * Redirect to correct language URL if needed
     */
    public function redirect_to_language() {
        // Only redirect on frontend
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        global $post;

        if (!$post) {
            return;
        }

        // Get post language
        $post_lang = get_post_meta($post->ID, '_language', true);

        if (!$post_lang) {
            return;
        }

        // Get current language from URL
        $current_lang = $this->get_current_language();

        // If languages don't match, redirect to correct URL
        if ($post_lang !== $current_lang) {
            $correct_url = $this->get_translation_url($post->ID, $post_lang);

            if ($correct_url && $correct_url !== get_permalink($post->ID)) {
                wp_safe_redirect($correct_url, 301);
                exit;
            }
        }
    }

    /**
     * Get language from post ID
     *
     * @param int $post_id Post ID
     * @return string Language code
     */
    public function get_post_language($post_id) {
        $lang = get_post_meta($post_id, '_language', true);
        return $lang ? $lang : $this->default_language;
    }

    /**
     * Set front page for language-only URLs
     * Maps /es/ to the Spanish homepage
     *
     * @param array $query_vars Query variables
     * @return array Modified query variables
     */
    public function set_front_page_for_language($query_vars) {
        // Check if we have just a language and nothing else
        if (isset($query_vars['lang']) && 
            !isset($query_vars['pagename']) && 
            !isset($query_vars['name']) &&
            !isset($query_vars['category_name']) &&
            !isset($query_vars['tag'])) {
            
            $lang = $query_vars['lang'];
            
            // Get the front page ID
            $front_page_id = get_option('page_on_front');
            
            if ($front_page_id) {
                // Get the translation of the front page
                $clone_manager = new Clone_Manager();
                $translation_id = $clone_manager->get_translation($front_page_id, $lang);
                
                if ($translation_id) {
                    // Set the page_id to show the translated front page
                    $query_vars['page_id'] = $translation_id;
                    unset($query_vars['lang']); // Remove lang to prevent query conflicts
                }
            }
        }
        
        return $query_vars;
    }
}