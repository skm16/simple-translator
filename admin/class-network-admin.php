<?php
/**
 * Network Admin Settings Class
 *
 * Handles network-level admin settings for multisite
 *
 * @package SimpleTranslator
 */

namespace SimpleTranslator;

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Network_Admin - Network-level settings management for multisite
 */
class Network_Admin {

    /**
     * Settings page slug
     *
     * @var string
     */
    private $page_slug = 'simple-translator-network';

    /**
     * Initialize network admin functionality
     */
    public function init() {
        // Only run on multisite
        if (!is_multisite()) {
            return;
        }

        // Add network admin menu
        add_action('network_admin_menu', array($this, 'add_network_admin_menu'));

        // Handle network settings save
        add_action('network_admin_edit_st_network_settings', array($this, 'save_network_settings'));

        // Enqueue admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Add network admin menu page
     */
    public function add_network_admin_menu() {
        add_submenu_page(
            'settings.php',
            __('Simple Translator Network Settings', 'simple-translator'),
            __('Simple Translator', 'simple-translator'),
            'manage_network_options',
            $this->page_slug,
            array($this, 'render_network_settings_page')
        );
    }

    /**
     * Render network settings page
     */
    public function render_network_settings_page() {
        // Check user capabilities
        if (!current_user_can('manage_network_options')) {
            return;
        }

        // Get network options
        $network_languages = get_site_option('simple_translator_languages', array('en', 'es'));
        $network_version = get_site_option('simple_translator_version', ST_VERSION);
        $translation_memory = get_site_option('simple_translator_translation_memory', array());

        // Get network statistics
        $network_stats = $this->get_network_statistics();

        // Include the network settings page template
        include ST_PLUGIN_DIR . 'admin/views/network-settings.php';
    }

    /**
     * Save network settings
     */
    public function save_network_settings() {
        // Check user capabilities
        if (!current_user_can('manage_network_options')) {
            wp_die(__('You do not have permission to manage network settings.', 'simple-translator'));
        }

        // Verify nonce
        check_admin_referer('st_network_settings');

        // Save network languages
        if (isset($_POST['st_network_languages'])) {
            $languages = $this->sanitize_languages($_POST['st_network_languages']);
            update_site_option('simple_translator_languages', $languages);
        }

        // Save default network language
        if (isset($_POST['st_network_default_language'])) {
            $default_lang = sanitize_text_field($_POST['st_network_default_language']);
            update_site_option('simple_translator_default_language', $default_lang);
        }

        // Save network-wide switcher options
        if (isset($_POST['st_network_switcher_enabled'])) {
            update_site_option('simple_translator_network_switcher_enabled', true);
        } else {
            update_site_option('simple_translator_network_switcher_enabled', false);
        }

        // Save network debug mode
        if (isset($_POST['st_network_debug_mode'])) {
            update_site_option('simple_translator_network_debug_mode', true);
        } else {
            update_site_option('simple_translator_network_debug_mode', false);
        }

        // Update version
        update_site_option('simple_translator_version', ST_VERSION);

        // Handle bulk actions for sites
        if (isset($_POST['bulk_action']) && isset($_POST['sites'])) {
            $bulk_action = sanitize_text_field($_POST['bulk_action']);
            $sites = array_map('intval', $_POST['sites']);

            $this->handle_bulk_action($bulk_action, $sites);
        }

        // Redirect back with success message
        wp_redirect(
            add_query_arg(
                array(
                    'page' => $this->page_slug,
                    'updated' => 'true'
                ),
                network_admin_url('settings.php')
            )
        );
        exit;
    }

    /**
     * Handle bulk actions for sites
     *
     * @param string $action Bulk action
     * @param array  $sites  Site IDs
     */
    private function handle_bulk_action($action, $sites) {
        foreach ($sites as $site_id) {
            switch_to_blog($site_id);

            switch ($action) {
                case 'enable_translation':
                    update_option('st_translation_enabled', true);
                    break;

                case 'disable_translation':
                    update_option('st_translation_enabled', false);
                    break;

                case 'sync_settings':
                    // Sync network settings to site
                    $network_languages = get_site_option('simple_translator_languages', array('en', 'es'));
                    update_option('st_enabled_languages', $network_languages);
                    break;

                case 'clear_cache':
                    // Clear translation cache for site
                    global $wpdb;
                    $wpdb->query(
                        $wpdb->prepare(
                            "DELETE FROM {$wpdb->options}
                            WHERE option_name LIKE %s
                            OR option_name LIKE %s",
                            $wpdb->esc_like('_transient_st_') . '%',
                            $wpdb->esc_like('_transient_timeout_st_') . '%'
                        )
                    );
                    break;
            }

            restore_current_blog();
        }
    }

    /**
     * Get network statistics
     *
     * @return array Network-wide statistics
     */
    private function get_network_statistics() {
        $stats = array(
            'total_sites' => 0,
            'sites_with_translations' => 0,
            'total_translation_groups' => 0,
            'total_translations' => 0,
            'by_language' => array(),
        );

        $sites = get_sites(array('number' => 999));
        $stats['total_sites'] = count($sites);

        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);

            global $wpdb;

            // Count translation groups for this site
            $groups = $wpdb->get_var(
                "SELECT COUNT(DISTINCT meta_value)
                FROM {$wpdb->postmeta}
                WHERE meta_key = '_translation_group_id'"
            );

            if ($groups > 0) {
                $stats['sites_with_translations']++;
                $stats['total_translation_groups'] += (int) $groups;
            }

            // Count by language
            $languages = $wpdb->get_results(
                "SELECT meta_value as language, COUNT(*) as count
                FROM {$wpdb->postmeta}
                WHERE meta_key = '_language'
                GROUP BY meta_value"
            );

            foreach ($languages as $row) {
                if (!isset($stats['by_language'][$row->language])) {
                    $stats['by_language'][$row->language] = 0;
                }
                $stats['by_language'][$row->language] += (int) $row->count;
                $stats['total_translations'] += (int) $row->count;
            }

            restore_current_blog();
        }

        return $stats;
    }

    /**
     * Get all sites with translation info
     *
     * @return array Sites with translation data
     */
    public function get_sites_with_translation_info() {
        $sites = get_sites(array('number' => 999));
        $sites_data = array();

        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);

            global $wpdb;

            $site_data = array(
                'blog_id' => $site->blog_id,
                'domain' => $site->domain,
                'path' => $site->path,
                'url' => get_site_url($site->blog_id),
                'name' => get_blog_details($site->blog_id)->blogname,
                'enabled_languages' => get_option('st_enabled_languages', array()),
                'default_language' => get_option('st_default_language', 'en'),
                'translation_groups' => 0,
                'translations' => 0,
                'translation_enabled' => get_option('st_translation_enabled', true),
            );

            // Count translation groups
            $site_data['translation_groups'] = (int) $wpdb->get_var(
                "SELECT COUNT(DISTINCT meta_value)
                FROM {$wpdb->postmeta}
                WHERE meta_key = '_translation_group_id'"
            );

            // Count translations
            $site_data['translations'] = (int) $wpdb->get_var(
                "SELECT COUNT(*)
                FROM {$wpdb->postmeta}
                WHERE meta_key = '_language'"
            );

            $sites_data[] = $site_data;

            restore_current_blog();
        }

        return $sites_data;
    }

    /**
     * Sanitize languages array
     *
     * @param array $languages Languages to sanitize
     * @return array Sanitized languages
     */
    private function sanitize_languages($languages) {
        if (!is_array($languages)) {
            return array('en');
        }

        $available_languages = array_keys($this->get_available_languages());
        $sanitized = array();

        foreach ($languages as $lang) {
            $lang = sanitize_text_field($lang);
            if (in_array($lang, $available_languages, true)) {
                $sanitized[] = $lang;
            }
        }

        // Ensure at least one language is enabled
        if (empty($sanitized)) {
            $sanitized = array('en');
        }

        return $sanitized;
    }

    /**
     * Get available languages
     *
     * @return array Array of language code => name pairs
     */
    private function get_available_languages() {
        return array(
            'en' => 'English',
            'es' => 'Español (Spanish)',
            'fr' => 'Français (French)',
            'de' => 'Deutsch (German)',
            'it' => 'Italiano (Italian)',
            'pt' => 'Português (Portuguese)',
            'nl' => 'Nederlands (Dutch)',
            'ru' => 'Русский (Russian)',
            'zh' => '中文 (Chinese)',
            'ja' => '日本語 (Japanese)',
            'ko' => '한국어 (Korean)',
            'ar' => 'العربية (Arabic)',
            'pl' => 'Polski (Polish)',
            'tr' => 'Türkçe (Turkish)',
            'sv' => 'Svenska (Swedish)',
            'da' => 'Dansk (Danish)',
            'no' => 'Norsk (Norwegian)',
            'fi' => 'Suomi (Finnish)',
            'cs' => 'Čeština (Czech)',
            'el' => 'Ελληνικά (Greek)',
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our network settings page
        if ('settings_page_' . $this->page_slug . '-network' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'st-network-admin',
            ST_PLUGIN_URL . 'admin/assets/css/admin.css',
            array(),
            ST_VERSION
        );
    }

    /**
     * Export network settings
     *
     * @return array Network settings
     */
    public function export_network_settings() {
        return array(
            'languages' => get_site_option('simple_translator_languages', array('en', 'es')),
            'default_language' => get_site_option('simple_translator_default_language', 'en'),
            'version' => get_site_option('simple_translator_version', ST_VERSION),
            'translation_memory' => get_site_option('simple_translator_translation_memory', array()),
            'network_switcher_enabled' => get_site_option('simple_translator_network_switcher_enabled', false),
            'network_debug_mode' => get_site_option('simple_translator_network_debug_mode', false),
        );
    }

    /**
     * Import network settings
     *
     * @param array $settings Settings to import
     * @return bool Success status
     */
    public function import_network_settings($settings) {
        if (!is_array($settings)) {
            return false;
        }

        foreach ($settings as $key => $value) {
            update_site_option('simple_translator_' . $key, $value);
        }

        return true;
    }
}
