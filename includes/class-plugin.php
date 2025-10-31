<?php
/**
 * Main Plugin Class
 *
 * @package SimpleTranslator
 */

namespace SimpleTranslator;

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin controller class
 */
class Plugin {

    /**
     * Single instance of the plugin
     *
     * @var Plugin
     */
    private static $instance = null;

    /**
     * Clone Manager instance
     *
     * @var Clone_Manager
     */
    public $clone_manager;

    /**
     * URL Manager instance
     *
     * @var URL_Manager
     */
    public $url_manager;

    /**
     * Translation Admin instance
     *
     * @var Translation_Admin
     */
    public $translation_admin;

    /**
     * SEO Manager instance
     *
     * @var SEO_Manager
     */
    public $seo_manager;

    /**
     * Menu Handler instance
     *
     * @var Menu_Handler
     */
    public $menu_handler;

    /**
     * Search Filter instance
     *
     * @var Search_Filter
     */
    public $search_filter;

    /**
     * Frontend instance
     *
     * @var Frontend
     */
    public $frontend;

    /**
     * Widget Handler instance
     *
     * @var Widget_Handler
     */
    public $widget_handler;

    /**
     * Logger instance
     *
     * @var Logger
     */
    public $logger;

    /**
     * Get singleton instance
     *
     * @return Plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor to enforce singleton
     */
    private function __construct() {
        // Constructor is private to prevent direct instantiation
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Initialize components
        $this->init_components();

        // Setup hooks
        $this->setup_hooks();

        // Load admin functionality
        if (is_admin()) {
            $this->init_admin();
        }

        // Load frontend functionality
        if (!is_admin()) {
            $this->init_frontend();
        }
    }

    /**
     * Initialize core components
     */
    private function init_components() {
        // Core components
        $this->clone_manager = new Clone_Manager();
        $this->url_manager = new URL_Manager();
        $this->logger = new Logger();

        // Initialize logger
        $this->logger->init();
    }

    /**
     * Initialize admin components
     */
    private function init_admin() {
        // Admin components
        if (class_exists('SimpleTranslator\\Translation_Admin')) {
            $this->translation_admin = new Translation_Admin();
            $this->translation_admin->init();
        }

        // Network admin
        if (is_multisite() && class_exists('SimpleTranslator\\Network_Admin')) {
            $network_admin = new Network_Admin();
            $network_admin->init();
        }

        // Site admin
        if (class_exists('SimpleTranslator\\Admin')) {
            $admin = new Admin();
            $admin->init();
        }
    }

    /**
     * Initialize frontend components
     */
    private function init_frontend() {
        // SEO Manager
        if (class_exists('SimpleTranslator\\SEO_Manager')) {
            $this->seo_manager = new SEO_Manager();
            $this->seo_manager->init();
        }

        // Menu Handler
        if (class_exists('SimpleTranslator\\Menu_Handler')) {
            $this->menu_handler = new Menu_Handler();
            $this->menu_handler->init();
        }

        // Search Filter
        if (class_exists('SimpleTranslator\\Search_Filter')) {
            $this->search_filter = new Search_Filter();
            $this->search_filter->init();
        }

        // Widget Handler
        if (class_exists('SimpleTranslator\\Widget_Handler')) {
            $this->widget_handler = new Widget_Handler();
            $this->widget_handler->init();
        }

        // Frontend Controller
        if (class_exists('SimpleTranslator\\Frontend')) {
            $this->frontend = new Frontend();
            $this->frontend->init();
        }
    }

    /**
     * Setup plugin hooks
     */
    private function setup_hooks() {
        // Add rewrite rules
        add_action('init', [$this->url_manager, 'add_rewrite_rules'], 10);

        // Filter queries by language
        add_action('pre_get_posts', [$this->url_manager, 'filter_queries'], 10);

        // Add language query var
        add_filter('query_vars', [$this, 'add_query_vars']);

        // Set front page for language-only URLs (/es/, /fr/, etc.)
        add_filter('request', [$this->url_manager, 'set_front_page_for_language']);

        // Handle post deletion
        add_action('before_delete_post', [$this, 'handle_post_deletion']);

        // Handle post status changes
        add_action('transition_post_status', [$this, 'handle_status_transition'], 10, 3);
    }

    /**
     * Add custom query vars
     *
     * @param array $vars Query vars
     * @return array
     */
    public function add_query_vars($vars) {
        $vars[] = 'lang';
        return $vars;
    }

    /**
     * Handle post deletion to clean up related translations
     *
     * @param int $post_id Post ID being deleted
     */
    public function handle_post_deletion($post_id) {
        // Clear translation cache for this post
        $this->clone_manager->clear_translation_cache($post_id);

        // Get all translations
        $translations = $this->clone_manager->get_translations($post_id);

        // Update related posts to remove this translation from their group
        foreach ($translations as $lang => $trans_id) {
            if ($trans_id !== $post_id) {
                // Clear cache for related translations
                $this->clone_manager->clear_translation_cache($trans_id);
            }
        }

        // Log the deletion
        $this->logger->log(sprintf(
            'Post %d deleted. Translation group affected.',
            $post_id
        ), 'info');
    }

    /**
     * Handle post status transitions
     *
     * @param string $new_status New status
     * @param string $old_status Old status
     * @param \WP_Post $post Post object
     */
    public function handle_status_transition($new_status, $old_status, $post) {
        // Only handle posts with translations
        $group_id = get_post_meta($post->ID, '_translation_group_id', true);
        if (!$group_id) {
            return;
        }

        // If publishing a post, check translation status
        if ($new_status === 'publish' && $old_status !== 'publish') {
            $translation_status = get_post_meta($post->ID, '_translation_status', true);

            // Warn if publishing untranslated content
            if ($translation_status === 'not_started') {
                $this->logger->log(sprintf(
                    'Post %d published with translation status: not_started',
                    $post->ID
                ), 'warning');
            }
        }

        // Clear translation cache
        $this->clone_manager->clear_translation_cache($post->ID);
    }

    /**
     * Plugin activation
     *
     * @param bool $network_wide Network-wide activation
     */
    public static function activate($network_wide) {
        // Check if this is a multisite network activation
        if (is_multisite() && $network_wide) {
            // Network-wide activation
            self::activate_network();
        } else {
            // Single site activation
            self::activate_site();
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Activate on network
     */
    private static function activate_network() {
        // Set network-wide options
        $network_options = array(
            'simple_translator_languages' => array('en', 'es'),
            'simple_translator_version' => ST_VERSION,
            'simple_translator_translation_memory' => array(),
        );

        foreach ($network_options as $key => $value) {
            update_site_option($key, $value);
        }

        // Activate for all existing sites
        $sites = get_sites(array('number' => 999));
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            self::activate_site();
            restore_current_blog();
        }
    }

    /**
     * Activate on single site
     */
    private static function activate_site() {
        // Set default options
        $default_options = array(
            'st_enabled_languages' => array('en', 'es'),
            'st_default_language' => 'en',
            'st_url_structure' => 'path',
            'st_post_types' => array('post', 'page'),
            'st_auto_clone' => false,
            'st_sync_taxonomies' => true,
            'st_add_switcher_to_menu' => false,
        );

        foreach ($default_options as $key => $value) {
            if (false === get_option($key)) {
                add_option($key, $value);
            }
        }

        // Create a flag to indicate first-time activation
        add_option('st_activation_redirect', true);
    }

    /**
     * Plugin deactivation
     *
     * @param bool $network_wide Network-wide deactivation
     */
    public static function deactivate($network_wide) {
        // Flush rewrite rules
        flush_rewrite_rules();

        // Clear all translation caches
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        // Delete transients
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
            WHERE option_name LIKE '_transient_st_%'
            OR option_name LIKE '_transient_timeout_st_%'"
        );
    }

    /**
     * Get plugin version
     *
     * @return string
     */
    public function get_version() {
        return ST_VERSION;
    }

    /**
     * Get enabled languages for current site
     *
     * @return array
     */
    public function get_enabled_languages() {
        return get_option('st_enabled_languages', array('en'));
    }

    /**
     * Get default language for current site
     *
     * @return string
     */
    public function get_default_language() {
        return get_option('st_default_language', 'en');
    }

    /**
     * Check if a post type is translatable
     *
     * @param string $post_type Post type name
     * @return bool
     */
    public function is_post_type_translatable($post_type) {
        $translatable_types = get_option('st_post_types', array('post', 'page'));
        return in_array($post_type, $translatable_types, true);
    }
}
