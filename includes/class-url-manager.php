<?php
/**
 * URL Manager Class - FIXED VERSION
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
     * Constructor
     */
    public function __construct() {
        $this->languages = get_option('st_enabled_languages', array('en', 'es'));
        $this->default_language = get_option('st_default_language', 'en');
    }

    /**
     * Add rewrite rules for language prefixes
     * FIXED: Properly maps clean URLs to suffixed slugs
     */
    public function add_rewrite_rules() {
        // Only add rules for non-default languages
        $languages = array_diff($this->languages, array($this->default_language));

        if (empty($languages)) {
            return;
        }

        // Add rules for each language
        foreach ($languages as $lang) {
            // Homepage with language: /es/
            add_rewrite_rule(
                '^' . $lang . '/?$',
                'index.php?lang=' . $lang,
                'top'
            );

            // Single level pages: /es/about/ -> about-es
            add_rewrite_rule(
                '^' . $lang . '/([^/]+)/?$',
                'index.php?pagename=$matches[1]&lang=' . $lang,
                'top'
            );

            // Multi-level pages: /es/parent/child/ -> parent-es/child-es
            add_rewrite_rule(
                '^' . $lang . '/(.+?)/?$',
                'index.php?pagename=$matches[1]&lang=' . $lang,
                'top'
            );

            // Blog posts with dates: /es/2024/01/post-name/
            add_rewrite_rule(
                '^' . $lang . '/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/([^/]+)/?$',
                'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&name=$matches[4]&lang=' . $lang,
                'top'
            );

            // Category archives: /es/category/news/
            add_rewrite_rule(
                '^' . $lang . '/category/(.+?)/?$',
                'index.php?category_name=$matches[1]&lang=' . $lang,
                'top'
            );

            // Tag archives: /es/tag/news/
            add_rewrite_rule(
                '^' . $lang . '/tag/(.+?)/?$',
                'index.php?tag=$matches[1]&lang=' . $lang,
                'top'
            );
        }
    }

    /**
     * Parse request to map clean URLs to actual post slugs with language suffixes
     * FIXED: This is the key method that makes clean URLs work with suffixed slugs
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

        // Handle pagename (pages and hierarchical post types)
        if (isset($query_vars['pagename']) && !empty($query_vars['pagename'])) {
            $query_vars['pagename'] = $this->add_language_suffix_to_path($query_vars['pagename'], $lang);
        }

        // Handle name (posts)
        if (isset($query_vars['name']) && !empty($query_vars['name'])) {
            $query_vars['name'] = $this->add_language_suffix($query_vars['name'], $lang);
        }

        return $query_vars;
    }

    /**
     * Add language suffix to a URL path (handles hierarchical pages)
     *
     * @param string $path Path like "parent/child"
     * @param string $lang Language code
     * @return string Path with language suffixes like "parent-es/child-es"
     */
    private function add_language_suffix_to_path($path, $lang) {
        // Split path into segments
        $segments = explode('/', $path);
        
        // Add language suffix to each segment
        $suffixed_segments = array();
        foreach ($segments as $segment) {
            if (!empty($segment)) {
                $suffixed_segments[] = $this->add_language_suffix($segment, $lang);
            }
        }
        
        return implode('/', $suffixed_segments);
    }

    /**
     * Add language suffix to a single slug
     *
     * @param string $slug Slug without suffix
     * @param string $lang Language code
     * @return string Slug with language suffix
     */
    private function add_language_suffix($slug, $lang) {
        // Check if the slug already has the language suffix
        if (substr($slug, -strlen($lang) - 1) === '-' . $lang) {
            return $slug;
        }
        
        // Check if a post with the suffixed slug exists
        $suffixed_slug = $slug . '-' . $lang;
        
        // Try to find the post with this slug
        $post = get_page_by_path($suffixed_slug, OBJECT, get_post_types());
        
        if ($post) {
            return $suffixed_slug;
        }
        
        // If not found, return original (might be default language or not translated)
        return $slug;
    }

    /**
     * Filter post permalink to show clean URLs without language suffixes
     * FIXED: Removes language suffix from URLs for clean display
     *
     * @param string  $permalink Post permalink
     * @param \WP_Post $post      Post object
     * @return string Modified permalink
     */
    public function filter_post_link($permalink, $post) {
        // Get post language
        $lang = get_post_meta($post->ID, '_language', true);

        // If no language set or default language, return original
        if (!$lang || $lang === $this->default_language) {
            return $permalink;
        }

        // Remove language suffix from the URL
        $permalink = $this->remove_language_suffix_from_url($permalink, $lang);

        // Add language prefix
        $home_url = trailingslashit(home_url());
        $permalink = str_replace($home_url, $home_url . $lang . '/', $permalink);

        return $permalink;
    }

    /**
     * Remove language suffix from URL
     *
     * @param string $url URL with language suffix
     * @param string $lang Language code
     * @return string Clean URL without suffix
     */
    private function remove_language_suffix_from_url($url, $lang) {
        // Pattern to match language suffix before trailing slash or end of URL
        $pattern = '/-' . preg_quote($lang, '/') . '(\/|$)/';
        
        // Replace suffix with just the slash or end
        $url = preg_replace($pattern, '$1', $url);
        
        return $url;
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
        
        // Get the permalink (will be filtered by filter_post_link)
        $url = get_permalink($translation_id);

        return $url;
    }

    /**
     * Set front page for language-only URLs like /es/
     * FIXED: Properly handles homepage translations
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
        global $wp_query;
        if ($wp_query && method_exists($wp_query, 'get')) {
            $lang = $wp_query->get('lang');
            if ($lang && in_array($lang, $this->languages, true)) {
                $this->current_language = $lang;
                return $this->current_language;
            }
        }

        // Check URL path
        $request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw($_SERVER['REQUEST_URI']) : '';
        $path = trim(parse_url($request_uri, PHP_URL_PATH), '/');

        if ($path) {
            $parts = explode('/', $path);

            // Check if first part is a language code
            if (!empty($parts[0]) && in_array($parts[0], $this->languages, true)) {
                $this->current_language = $parts[0];
                return $this->current_language;
            }
        }

        // Default to site's default language
        $this->current_language = $this->default_language;
        return $this->current_language;
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
    }

    /**
     * Modify permalink for translations
     *
     * @param string  $permalink Post permalink
     * @param \WP_Post $post      Post object
     * @return string Modified permalink
     */
    public function filter_post_link($permalink, $post) {
        // Get post language
        $lang = get_post_meta($post->ID, '_language', true);

        // If no language set or default language, return original
        if (!$lang || $lang === $this->default_language) {
            return $permalink;
        }

        // Add language prefix
        $home_url = trailingslashit(home_url());
        $permalink = str_replace($home_url, $home_url . $lang . '/', $permalink);

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
     * Generate language switcher URLs
     *
     * @return array Array of language => URL pairs
     */
    public function get_switcher_urls() {
        $urls = array();

        // If we're on a singular post/page, get translations
        if (is_singular()) {
            global $post;

            $clone_manager = new Clone_Manager();
            $translations = $clone_manager->get_translations($post->ID);

            foreach ($this->languages as $lang) {
                if (isset($translations[$lang])) {
                    $urls[$lang] = $this->get_translation_url($post->ID, $lang);
                } else {
                    // No translation available, link to homepage in that language
                    $urls[$lang] = $this->get_home_url($lang);
                }
            }
        } else {
            // For archives and other pages, just link to homepages
            foreach ($this->languages as $lang) {
                $urls[$lang] = $this->get_home_url($lang);
            }
        }

        return $urls;
    }

    /**
     * Set front page for language-only URLs
     *
     * When visiting /es/ with no specific page, show the translated front page
     *
     * @param array $query_vars Query variables
     * @return array Modified query variables
     */
    public function set_front_page_for_language($query_vars) {
        // Check if we have a language query var but no specific content
        if (isset($query_vars['lang']) &&
            empty($query_vars['pagename']) &&
            empty($query_vars['name']) &&
            empty($query_vars['page_id']) &&
            empty($query_vars['p'])) {

            // Only handle if WordPress is set to use a static front page
            if (get_option('show_on_front') === 'page') {
                $front_page_id = (int) get_option('page_on_front');

                if ($front_page_id) {
                    // Find the translation of the front page
                    $clone_manager = new Clone_Manager();
                    $translations = $clone_manager->get_translations($front_page_id);
                    $lang = $query_vars['lang'];

                    if (isset($translations[$lang])) {
                        // Set the translated page as the page to display
                        $query_vars['page_id'] = $translations[$lang];
                        $query_vars['pagename'] = '';

                        // Remove language from query vars so it doesn't interfere
                        // We'll still track it via post meta
                        unset($query_vars['lang']);
                    }
                }
            }
        }

        return $query_vars;
    }
}
