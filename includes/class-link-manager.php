<?php
/**
 * Link Manager Class
 *
 * Handles automatic transformation of internal links to maintain language context
 *
 * @package SimpleTranslator
 */

namespace SimpleTranslator;

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Link Manager - Automatic link transformation for language preservation
 */
class Link_Manager {

    /**
     * URL Manager instance
     *
     * @var URL_Manager
     */
    private $url_manager;

    /**
     * Clone Manager instance
     *
     * @var Clone_Manager
     */
    private $clone_manager;

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Site home URL (cached)
     *
     * @var string
     */
    private $home_url;

    /**
     * Constructor
     *
     * @param URL_Manager   $url_manager   URL Manager instance
     * @param Clone_Manager $clone_manager Clone Manager instance
     * @param Logger        $logger        Logger instance
     */
    public function __construct($url_manager, $clone_manager, $logger) {
        $this->url_manager = $url_manager;
        $this->clone_manager = $clone_manager;
        $this->logger = $logger;
        $this->home_url = trailingslashit(home_url());
    }

    /**
     * Initialize link transformation filters
     */
    public function init() {
        // Only run on frontend
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }

        // NOTE: home_url filter disabled - too invasive, causes 404s and triple prefixes
        // Site logo will link to root, language detection will handle redirects if needed

        // Transform internal links in content
        add_filter('the_content', array($this, 'transform_content_links'), 20);

        // Transform links in widgets
        add_filter('widget_text_content', array($this, 'transform_content_links'), 20);
        add_filter('widget_block_content', array($this, 'transform_content_links'), 20);

        $this->logger->debug('Link Manager initialized (home_url filter disabled)');
    }

    /**
     * Filter home_url calls to add language prefix when appropriate
     *
     * @param string      $url     The complete home URL including scheme and path
     * @param string      $path    Path relative to the home URL
     * @param string|null $scheme  Scheme to give the home URL context
     * @param int|null    $blog_id Blog ID, or null for the current blog
     * @return string Modified home URL
     */
    public function filter_home_url($url, $path, $scheme, $blog_id) {
        // Skip if in admin (but allow AJAX)
        if (is_admin() && !wp_doing_ajax()) {
            return $url;
        }

        // Skip during WordPress query parsing (prevents 404s)
        global $wp;
        if (!isset($wp->request) || doing_action('parse_request')) {
            return $url;
        }

        // Get current language
        $current_lang = $this->url_manager->get_current_language();
        $default_lang = get_option('st_default_language', 'en');

        // No change needed for default language
        if ($current_lang === $default_lang) {
            return $url;
        }

        // Check if URL already has a language prefix - avoid double-prefixing
        $languages = get_option('st_enabled_languages', array('en', 'es'));
        $url_path = parse_url($url, PHP_URL_PATH);

        foreach ($languages as $lang) {
            if (strpos($url_path, '/' . $lang . '/') === 0 || $url_path === '/' . $lang) {
                // URL already has a language prefix, don't transform
                return $url;
            }
        }

        // Check if the path parameter already has a language prefix
        if (!empty($path)) {
            $path_trimmed = ltrim($path, '/');
            foreach ($languages as $lang) {
                if (strpos($path_trimmed, $lang . '/') === 0 || $path_trimmed === $lang) {
                    // Path already has language prefix
                    return $url;
                }
            }
        }

        // Parse URL to preserve scheme and host
        $parsed = parse_url($url);
        $base = $parsed['scheme'] . '://' . $parsed['host'];

        // Add port if present
        if (isset($parsed['port'])) {
            $base .= ':' . $parsed['port'];
        }

        // Handle path
        if (empty($path) || $path === '/') {
            // Home URL - add language prefix
            $modified_url = trailingslashit($base) . $current_lang . '/';
        } else {
            // Path provided - add language before path
            $path = ltrim($path, '/');
            $modified_url = trailingslashit($base) . $current_lang . '/' . $path;
        }

        $this->logger->debug('Filtered home URL', array(
            'original' => $url,
            'modified' => $modified_url,
            'current_lang' => $current_lang,
            'path' => $path
        ));

        return $modified_url;
    }

    /**
     * Transform internal links in content to maintain language context
     *
     * @param string $content Post/page content HTML
     * @return string Modified content with transformed links
     */
    public function transform_content_links($content) {
        // Skip if no content or no links
        if (empty($content) || strpos($content, '<a ') === false) {
            return $content;
        }

        // Get current language
        $current_lang = $this->url_manager->get_current_language();
        $default_lang = get_option('st_default_language', 'en');

        // No transformation needed for default language
        if ($current_lang === $default_lang) {
            return $content;
        }

        // Check cache first
        $post_id = get_the_ID();
        if ($post_id) {
            $cache_key = "st_transformed_links_{$post_id}_{$current_lang}";
            $cached = get_transient($cache_key);

            if ($cached !== false) {
                $this->logger->debug('Using cached transformed content', array(
                    'post_id' => $post_id,
                    'lang' => $current_lang
                ));
                return $cached;
            }
        }

        // Transform links using regex
        $pattern = '/<a\s+([^>]*\s+)?href=["\']([^"\']+)["\']([^>]*)>/i';

        $transformed = preg_replace_callback($pattern, function($matches) use ($current_lang) {
            $full_tag = $matches[0];
            $href = $matches[2];

            // Skip empty hrefs
            if (empty($href)) {
                return $full_tag;
            }

            // Skip anchor-only links (#section)
            if (strpos($href, '#') === 0) {
                return $full_tag;
            }

            // Skip mailto:, tel:, javascript: etc
            if (preg_match('/^(mailto|tel|javascript|data):/i', $href)) {
                return $full_tag;
            }

            // Check if it's an internal link
            if (!$this->is_internal_url($href)) {
                return $full_tag;
            }

            // Separate anchor if present
            $anchor = '';
            if (strpos($href, '#') !== false) {
                list($href, $anchor) = explode('#', $href, 2);
                $anchor = '#' . $anchor;
            }

            // Try to get translated link
            $translated_url = $this->get_translated_link($href, $current_lang);

            if ($translated_url) {
                $new_href = $translated_url . $anchor;
                $new_tag = str_replace('href="' . $matches[2] . '"', 'href="' . $new_href . '"', $full_tag);
                $new_tag = str_replace("href='" . $matches[2] . "'", "href='" . $new_href . "'", $new_tag);

                $this->logger->debug('Transformed link', array(
                    'original' => $matches[2],
                    'translated' => $new_href,
                    'lang' => $current_lang
                ));

                return $new_tag;
            }

            return $full_tag;
        }, $content);

        // Cache the result
        if ($post_id && $transformed !== $content) {
            set_transient($cache_key, $transformed, HOUR_IN_SECONDS);

            $this->logger->debug('Cached transformed content', array(
                'post_id' => $post_id,
                'lang' => $current_lang
            ));
        }

        return $transformed;
    }

    /**
     * Check if a URL is internal (belongs to this site)
     *
     * @param string $url URL to check
     * @return bool True if internal, false otherwise
     */
    private function is_internal_url($url) {
        // Handle relative URLs
        if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            return true; // Relative URL is internal
        }

        // Get site domain
        $site_host = parse_url($this->home_url, PHP_URL_HOST);
        $link_host = parse_url($url, PHP_URL_HOST);

        // If no host in link (relative URL), it's internal
        if ($link_host === null) {
            return true;
        }

        // Compare hosts
        return ($link_host === $site_host);
    }

    /**
     * Convert URL to post ID
     *
     * @param string $url URL to convert
     * @return int|false Post ID or false if not found
     */
    private function url_to_post_id($url) {
        // Normalize URL - make it absolute if relative
        if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            $url = $this->home_url . ltrim($url, '/');
        }

        // Remove language prefix if present
        $url = $this->url_manager->remove_language_prefix($url);

        // Try WordPress native function first
        $post_id = url_to_postid($url);

        if ($post_id) {
            $this->logger->debug('URL to post ID (native)', array(
                'url' => $url,
                'post_id' => $post_id
            ));
            return $post_id;
        }

        // Fallback: Extract slug and try get_page_by_path
        $path = parse_url($url, PHP_URL_PATH);
        $slug = trim($path, '/');

        // Remove any remaining language prefix from slug
        $languages = get_option('st_enabled_languages', array('en', 'es'));
        foreach ($languages as $lang) {
            if (strpos($slug, $lang . '/') === 0) {
                $slug = substr($slug, strlen($lang) + 1);
                break;
            }
        }

        if (empty($slug)) {
            return false;
        }

        // Try to find post by slug
        $post = get_page_by_path($slug, OBJECT, get_post_types(array('public' => true)));

        if ($post) {
            $this->logger->debug('URL to post ID (fallback)', array(
                'url' => $url,
                'slug' => $slug,
                'post_id' => $post->ID
            ));
            return $post->ID;
        }

        $this->logger->debug('URL to post ID failed', array(
            'url' => $url,
            'slug' => $slug
        ));

        return false;
    }

    /**
     * Get translated URL for an internal link
     *
     * @param string $url         Original URL
     * @param string $target_lang Target language code
     * @return string|false Translated URL or false if not found
     */
    private function get_translated_link($url, $target_lang) {
        // Convert URL to post ID
        $post_id = $this->url_to_post_id($url);

        if (!$post_id) {
            return false;
        }

        // Get translation using Clone_Manager
        $translation_id = $this->clone_manager->get_translation($post_id, $target_lang);

        if (!$translation_id) {
            $this->logger->debug('No translation found for link', array(
                'post_id' => $post_id,
                'target_lang' => $target_lang
            ));
            return false;
        }

        // Get permalink for translation
        $translated_url = get_permalink($translation_id);

        $this->logger->debug('Found translated link', array(
            'original_post_id' => $post_id,
            'translation_id' => $translation_id,
            'original_url' => $url,
            'translated_url' => $translated_url,
            'target_lang' => $target_lang
        ));

        return $translated_url;
    }

    /**
     * Clear link transformation cache for a post
     *
     * @param int $post_id Post ID
     */
    public function clear_link_cache($post_id) {
        $languages = get_option('st_enabled_languages', array('en', 'es'));

        foreach ($languages as $lang) {
            delete_transient("st_transformed_links_{$post_id}_{$lang}");
        }

        $this->logger->debug('Cleared link cache', array(
            'post_id' => $post_id
        ));
    }
}
