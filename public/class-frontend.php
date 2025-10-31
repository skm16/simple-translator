<?php
/**
 * Frontend Class
 *
 * Handles frontend functionality
 *
 * @package SimpleTranslator
 */

namespace SimpleTranslator;

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend Controller - Frontend functionality
 */
class Frontend {

    /**
     * Initialize frontend functionality
     */
    public function init() {
        // Enqueue frontend styles and scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));

        // Register language switcher widget
        add_action('widgets_init', array($this, 'register_widgets'));

        // Add language switcher shortcode
        add_shortcode('st_language_switcher', array($this, 'language_switcher_shortcode'));

        // Filter permalink for translations
        add_filter('post_link', array($this, 'filter_post_permalink'), 10, 2);
        add_filter('page_link', array($this, 'filter_post_permalink'), 10, 2);

        // Add language parameter to pagination links
        add_filter('paginate_links', array($this, 'filter_pagination_links'));

        // Handle language cookie
        add_action('template_redirect', array($this, 'handle_language_cookie'));
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        // Enqueue frontend CSS
        wp_enqueue_style(
            'st-frontend',
            ST_PLUGIN_URL . 'public/assets/css/frontend.css',
            array(),
            ST_VERSION
        );

        // Enqueue frontend JavaScript
        wp_enqueue_script(
            'st-language-switcher',
            ST_PLUGIN_URL . 'public/assets/js/language-switcher.js',
            array('jquery'),
            ST_VERSION,
            true
        );

        // Localize script
        wp_localize_script('st-language-switcher', 'stFrontend', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'currentLang' => st_get_current_language(),
            'defaultLang' => st_get_default_language(),
        ));
    }

    /**
     * Register widgets
     */
    public function register_widgets() {
        if (class_exists('SimpleTranslator\\Language_Switcher')) {
            register_widget('SimpleTranslator\\Language_Switcher_Widget');
        }
    }

    /**
     * Language switcher shortcode
     *
     * Usage: [st_language_switcher format="dropdown" show_flags="true"]
     *
     * @param array $atts Shortcode attributes
     * @return string Switcher HTML
     */
    public function language_switcher_shortcode($atts) {
        $atts = shortcode_atts(array(
            'format' => 'dropdown',
            'show_flags' => 'true',
            'show_names' => 'true',
            'show_current' => 'true',
        ), $atts);

        // Convert string booleans
        $atts['show_flags'] = filter_var($atts['show_flags'], FILTER_VALIDATE_BOOLEAN);
        $atts['show_names'] = filter_var($atts['show_names'], FILTER_VALIDATE_BOOLEAN);
        $atts['show_current'] = filter_var($atts['show_current'], FILTER_VALIDATE_BOOLEAN);

        if (!class_exists('SimpleTranslator\\Language_Switcher')) {
            return '';
        }

        $switcher = new Language_Switcher();
        return $switcher->render($atts);
    }

    /**
     * Filter post permalink to include language
     *
     * @param string   $permalink Post permalink
     * @param \WP_Post $post      Post object
     * @return string Modified permalink
     */
    public function filter_post_permalink($permalink, $post) {
        // Get post language
        $lang = get_post_meta($post->ID, '_language', true);

        if (!$lang) {
            return $permalink;
        }

        // Get default language
        $default_lang = get_option('st_default_language', 'en');

        // Check if this post is (or its source is) the front page
        $source_id = get_post_meta($post->ID, '_source_post_id', true);
        $front_page_id = (int) get_option('page_on_front');

        if ($front_page_id && get_option('show_on_front') === 'page') {
            // Check if this is the front page or a translation of the front page
            if ($post->ID == $front_page_id || $source_id == $front_page_id) {
                // For front page, return language-specific home URL
                if ($lang === $default_lang) {
                    return home_url('/');
                }
                return trailingslashit(home_url()) . $lang . '/';
            }
        }

        // If post is in default language, return original permalink
        if ($lang === $default_lang) {
            return $permalink;
        }

        // Add language prefix to URL
        $home_url = trailingslashit(home_url());
        $permalink = str_replace($home_url, $home_url . $lang . '/', $permalink);

        return $permalink;
    }

    /**
     * Filter pagination links to include language parameter
     *
     * @param string $link Pagination link
     * @return string Modified link
     */
    public function filter_pagination_links($link) {
        $url_manager = new URL_Manager();
        $current_lang = $url_manager->get_current_language();
        $default_lang = get_option('st_default_language', 'en');

        // Only add for non-default languages
        if ($current_lang === $default_lang) {
            return $link;
        }

        // Check if link already has language prefix
        $home_url = trailingslashit(home_url());
        $lang_url = $home_url . $current_lang . '/';

        if (strpos($link, $lang_url) === false) {
            // Add language prefix
            $link = str_replace($home_url, $lang_url, $link);
        }

        return $link;
    }

    /**
     * Handle language cookie setting
     */
    public function handle_language_cookie() {
        // Check if language parameter is in URL
        $lang = get_query_var('lang');

        if ($lang) {
            $url_manager = new URL_Manager();
            $url_manager->set_language_cookie($lang);
        }
    }

    /**
     * Get available languages for frontend display
     *
     * @return array Array of language data
     */
    public function get_available_languages() {
        $languages = get_option('st_enabled_languages', array('en', 'es'));
        $current_lang = st_get_current_language();
        $data = array();

        foreach ($languages as $lang) {
            $data[$lang] = array(
                'code' => $lang,
                'name' => st_get_language_name($lang),
                'native_name' => st_get_language_native_name($lang),
                'flag' => st_get_language_flag($lang),
                'url' => $this->get_language_url($lang),
                'is_current' => ($lang === $current_lang),
            );
        }

        return $data;
    }

    /**
     * Get URL for a specific language
     *
     * @param string $lang Language code
     * @return string Language URL
     */
    private function get_language_url($lang) {
        if (is_singular()) {
            global $post;
            if ($post) {
                $url_manager = new URL_Manager();
                $url = $url_manager->get_translation_url($post->ID, $lang);

                if ($url) {
                    return $url;
                }
            }
        }

        // Fallback to homepage in that language
        $url_manager = new URL_Manager();
        return $url_manager->get_home_url($lang);
    }

    /**
     * Check if current page has translations
     *
     * @return bool
     */
    public function has_translations() {
        if (!is_singular()) {
            return false;
        }

        global $post;
        if (!$post) {
            return false;
        }

        $translations = st_get_translations($post->ID);
        return count($translations) > 1;
    }

    /**
     * Get translation completeness percentage
     *
     * @return int Percentage (0-100)
     */
    public function get_translation_completeness() {
        if (!is_singular()) {
            return 0;
        }

        global $post;
        if (!$post) {
            return 0;
        }

        $languages = get_option('st_enabled_languages', array('en', 'es'));
        $translations = st_get_translations($post->ID);

        if (empty($languages) || empty($translations)) {
            return 0;
        }

        return round((count($translations) / count($languages)) * 100);
    }
}
