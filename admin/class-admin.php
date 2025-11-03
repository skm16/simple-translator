<?php
/**
 * Admin Settings Class
 *
 * Handles site-level admin settings and configuration
 *
 * @package SimpleTranslator
 */

namespace SimpleTranslator;

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin - Site-level settings management
 */
class Admin {

    /**
     * Settings page slug
     *
     * @var string
     */
    private $page_slug = 'simple-translator-settings';

    /**
     * Settings option group
     *
     * @var string
     */
    private $option_group = 'st_settings';

    /**
     * Initialize admin functionality
     */
    public function init() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Register settings
        add_action('admin_init', array($this, 'register_settings'));

        // Enqueue admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));

        // Add settings link on plugins page
        add_filter('plugin_action_links_' . ST_PLUGIN_BASENAME, array($this, 'add_settings_link'));

        // Handle activation redirect
        add_action('admin_init', array($this, 'activation_redirect'));

        // Menu cloning AJAX handler
        add_action('wp_ajax_st_clone_menu', array($this, 'ajax_clone_menu'));

        // Register menu translation meta box
        add_action('load-nav-menus.php', array($this, 'register_menu_translation_meta_box'));
    }

    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        // Add top-level menu
        add_menu_page(
            __('Simple Translator', 'simple-translator'),
            __('Translator', 'simple-translator'),
            'edit_posts',
            'simple-translator',
            array($this, 'render_status_dashboard'),
            'dashicons-translation',
            25
        );

        // Add dashboard submenu
        add_submenu_page(
            'simple-translator',
            __('Translation Status', 'simple-translator'),
            __('Dashboard', 'simple-translator'),
            'edit_posts',
            'simple-translator',
            array($this, 'render_status_dashboard')
        );

        // Add settings submenu
        add_submenu_page(
            'simple-translator',
            __('Translator Settings', 'simple-translator'),
            __('Settings', 'simple-translator'),
            'manage_options',
            $this->page_slug,
            array($this, 'render_settings_page')
        );

        // Also add to Settings menu for convenience
        add_options_page(
            __('Simple Translator Settings', 'simple-translator'),
            __('Simple Translator', 'simple-translator'),
            'manage_options',
            $this->page_slug,
            array($this, 'render_settings_page')
        );
    }

    /**
     * Render translation status dashboard
     */
    public function render_status_dashboard() {
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'simple-translator'));
        }

        // Include the dashboard view
        include ST_PLUGIN_DIR . 'admin/views/translation-status.php';
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        // Register settings
        register_setting(
            $this->option_group,
            'st_enabled_languages',
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_languages'),
                'default' => array('en', 'es')
            )
        );

        register_setting(
            $this->option_group,
            'st_default_language',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'en'
            )
        );

        register_setting(
            $this->option_group,
            'st_url_structure',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'path'
            )
        );

        register_setting(
            $this->option_group,
            'st_post_types',
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_post_types'),
                'default' => array('post', 'page')
            )
        );

        register_setting(
            $this->option_group,
            'st_auto_clone',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => false
            )
        );

        register_setting(
            $this->option_group,
            'st_sync_taxonomies',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => true
            )
        );

        register_setting(
            $this->option_group,
            'st_add_switcher_to_menu',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => false
            )
        );

        register_setting(
            $this->option_group,
            'st_switcher_format',
            array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => 'dropdown'
            )
        );

        register_setting(
            $this->option_group,
            'st_show_flags',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => true
            )
        );

        register_setting(
            $this->option_group,
            'st_debug_mode',
            array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => false
            )
        );

        // Add settings sections
        $this->add_settings_sections();
    }

    /**
     * Add settings sections and fields
     */
    private function add_settings_sections() {
        // General Settings Section
        add_settings_section(
            'st_general_settings',
            __('General Settings', 'simple-translator'),
            array($this, 'render_general_section'),
            $this->page_slug
        );

        // Post Types Section
        add_settings_section(
            'st_post_types_settings',
            __('Post Types', 'simple-translator'),
            array($this, 'render_post_types_section'),
            $this->page_slug
        );

        // Language Switcher Section
        add_settings_section(
            'st_switcher_settings',
            __('Language Switcher', 'simple-translator'),
            array($this, 'render_switcher_section'),
            $this->page_slug
        );

        // Advanced Settings Section
        add_settings_section(
            'st_advanced_settings',
            __('Advanced Settings', 'simple-translator'),
            array($this, 'render_advanced_section'),
            $this->page_slug
        );

        // Add fields to sections
        $this->add_settings_fields();
    }

    /**
     * Add individual settings fields
     */
    private function add_settings_fields() {
        // General Settings Fields
        add_settings_field(
            'st_enabled_languages',
            __('Enabled Languages', 'simple-translator'),
            array($this, 'render_languages_field'),
            $this->page_slug,
            'st_general_settings'
        );

        add_settings_field(
            'st_default_language',
            __('Default Language', 'simple-translator'),
            array($this, 'render_default_language_field'),
            $this->page_slug,
            'st_general_settings'
        );

        add_settings_field(
            'st_url_structure',
            __('URL Structure', 'simple-translator'),
            array($this, 'render_url_structure_field'),
            $this->page_slug,
            'st_general_settings'
        );

        // Post Types Fields
        add_settings_field(
            'st_post_types',
            __('Translatable Post Types', 'simple-translator'),
            array($this, 'render_post_types_field'),
            $this->page_slug,
            'st_post_types_settings'
        );

        add_settings_field(
            'st_auto_clone',
            __('Auto Clone', 'simple-translator'),
            array($this, 'render_auto_clone_field'),
            $this->page_slug,
            'st_post_types_settings'
        );

        add_settings_field(
            'st_sync_taxonomies',
            __('Sync Taxonomies', 'simple-translator'),
            array($this, 'render_sync_taxonomies_field'),
            $this->page_slug,
            'st_post_types_settings'
        );

        // Language Switcher Fields
        add_settings_field(
            'st_add_switcher_to_menu',
            __('Add to Menu', 'simple-translator'),
            array($this, 'render_add_to_menu_field'),
            $this->page_slug,
            'st_switcher_settings'
        );

        add_settings_field(
            'st_switcher_format',
            __('Switcher Format', 'simple-translator'),
            array($this, 'render_switcher_format_field'),
            $this->page_slug,
            'st_switcher_settings'
        );

        add_settings_field(
            'st_show_flags',
            __('Show Flags', 'simple-translator'),
            array($this, 'render_show_flags_field'),
            $this->page_slug,
            'st_switcher_settings'
        );

        // Advanced Fields
        add_settings_field(
            'st_debug_mode',
            __('Debug Mode', 'simple-translator'),
            array($this, 'render_debug_mode_field'),
            $this->page_slug,
            'st_advanced_settings'
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get translation statistics
        $clone_manager = new Clone_Manager();
        $stats = $clone_manager->get_translation_stats();

        // Include the settings page template
        include ST_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    /**
     * Render section descriptions
     */
    public function render_general_section() {
        echo '<p>' . esc_html__('Configure basic language settings for your site.', 'simple-translator') . '</p>';
    }

    public function render_post_types_section() {
        echo '<p>' . esc_html__('Choose which post types can be translated.', 'simple-translator') . '</p>';
    }

    public function render_switcher_section() {
        echo '<p>' . esc_html__('Configure the language switcher display options.', 'simple-translator') . '</p>';
    }

    public function render_advanced_section() {
        echo '<p>' . esc_html__('Advanced settings for developers and troubleshooting.', 'simple-translator') . '</p>';
    }

    /**
     * Render languages field
     */
    public function render_languages_field() {
        $enabled_languages = get_option('st_enabled_languages', array('en', 'es'));
        $available_languages = $this->get_available_languages();

        echo '<fieldset>';
        foreach ($available_languages as $code => $name) {
            $checked = in_array($code, $enabled_languages, true) ? 'checked' : '';
            printf(
                '<label><input type="checkbox" name="st_enabled_languages[]" value="%s" %s> %s (%s)</label><br>',
                esc_attr($code),
                $checked,
                esc_html($name),
                esc_html(strtoupper($code))
            );
        }
        echo '</fieldset>';
        echo '<p class="description">' . esc_html__('Select which languages are available on this site.', 'simple-translator') . '</p>';
    }

    /**
     * Render default language field
     */
    public function render_default_language_field() {
        $default_language = get_option('st_default_language', 'en');
        $enabled_languages = get_option('st_enabled_languages', array('en', 'es'));
        $available_languages = $this->get_available_languages();

        echo '<select name="st_default_language" id="st_default_language">';
        foreach ($enabled_languages as $code) {
            if (isset($available_languages[$code])) {
                printf(
                    '<option value="%s" %s>%s (%s)</option>',
                    esc_attr($code),
                    selected($default_language, $code, false),
                    esc_html($available_languages[$code]),
                    esc_html(strtoupper($code))
                );
            }
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('The default language for your site. URLs in this language will not have a language prefix.', 'simple-translator') . '</p>';
    }

    /**
     * Render URL structure field
     */
    public function render_url_structure_field() {
        $url_structure = get_option('st_url_structure', 'path');

        echo '<fieldset>';
        printf(
            '<label><input type="radio" name="st_url_structure" value="path" %s> %s</label><br>',
            checked($url_structure, 'path', false),
            esc_html__('Path-based (/es/about/)', 'simple-translator')
        );
        printf(
            '<label><input type="radio" name="st_url_structure" value="query" %s> %s</label><br>',
            checked($url_structure, 'query', false),
            esc_html__('Query-based (?lang=es)', 'simple-translator')
        );
        echo '</fieldset>';
        echo '<p class="description">' . esc_html__('Choose how language is indicated in URLs. Path-based is recommended for better SEO.', 'simple-translator') . '</p>';
    }

    /**
     * Render post types field
     */
    public function render_post_types_field() {
        $enabled_post_types = get_option('st_post_types', array('post', 'page'));
        $post_types = get_post_types(array('public' => true), 'objects');

        echo '<fieldset>';
        foreach ($post_types as $post_type) {
            // Skip attachments
            if ($post_type->name === 'attachment') {
                continue;
            }

            $checked = in_array($post_type->name, $enabled_post_types, true) ? 'checked' : '';
            printf(
                '<label><input type="checkbox" name="st_post_types[]" value="%s" %s> %s</label><br>',
                esc_attr($post_type->name),
                $checked,
                esc_html($post_type->labels->name)
            );
        }
        echo '</fieldset>';
        echo '<p class="description">' . esc_html__('Select which post types can be translated.', 'simple-translator') . '</p>';
    }

    /**
     * Render auto clone field
     */
    public function render_auto_clone_field() {
        $auto_clone = get_option('st_auto_clone', false);

        printf(
            '<label><input type="checkbox" name="st_auto_clone" value="1" %s> %s</label>',
            checked($auto_clone, true, false),
            esc_html__('Automatically create translation drafts when publishing new content', 'simple-translator')
        );
        echo '<p class="description">' . esc_html__('When enabled, draft translations will be created automatically for all enabled languages.', 'simple-translator') . '</p>';
    }

    /**
     * Render sync taxonomies field
     */
    public function render_sync_taxonomies_field() {
        $sync_taxonomies = get_option('st_sync_taxonomies', true);

        printf(
            '<label><input type="checkbox" name="st_sync_taxonomies" value="1" %s> %s</label>',
            checked($sync_taxonomies, true, false),
            esc_html__('Sync taxonomies (categories, tags) when cloning', 'simple-translator')
        );
        echo '<p class="description">' . esc_html__('When enabled, taxonomies will be copied to translation clones.', 'simple-translator') . '</p>';
    }

    /**
     * Render add to menu field
     */
    public function render_add_to_menu_field() {
        $add_to_menu = get_option('st_add_switcher_to_menu', false);

        printf(
            '<label><input type="checkbox" name="st_add_switcher_to_menu" value="1" %s> %s</label>',
            checked($add_to_menu, true, false),
            esc_html__('Add language switcher to navigation menus', 'simple-translator')
        );
        echo '<p class="description">' . esc_html__('Automatically add the language switcher as the last item in navigation menus.', 'simple-translator') . '</p>';
    }

    /**
     * Render switcher format field
     */
    public function render_switcher_format_field() {
        $format = get_option('st_switcher_format', 'dropdown');

        echo '<select name="st_switcher_format" id="st_switcher_format">';
        printf(
            '<option value="dropdown" %s>%s</option>',
            selected($format, 'dropdown', false),
            esc_html__('Dropdown', 'simple-translator')
        );
        printf(
            '<option value="list" %s>%s</option>',
            selected($format, 'list', false),
            esc_html__('List', 'simple-translator')
        );
        printf(
            '<option value="flags" %s>%s</option>',
            selected($format, 'flags', false),
            esc_html__('Flags Only', 'simple-translator')
        );
        echo '</select>';
        echo '<p class="description">' . esc_html__('Choose how the language switcher is displayed.', 'simple-translator') . '</p>';
    }

    /**
     * Render show flags field
     */
    public function render_show_flags_field() {
        $show_flags = get_option('st_show_flags', true);

        printf(
            '<label><input type="checkbox" name="st_show_flags" value="1" %s> %s</label>',
            checked($show_flags, true, false),
            esc_html__('Show flag icons in language switcher', 'simple-translator')
        );
        echo '<p class="description">' . esc_html__('Display flag icons next to language names.', 'simple-translator') . '</p>';
    }

    /**
     * Render debug mode field
     */
    public function render_debug_mode_field() {
        $debug_mode = get_option('st_debug_mode', false);

        printf(
            '<label><input type="checkbox" name="st_debug_mode" value="1" %s> %s</label>',
            checked($debug_mode, true, false),
            esc_html__('Enable debug logging', 'simple-translator')
        );
        echo '<p class="description">' . esc_html__('Log translation operations for troubleshooting. Logs are stored in wp-content/uploads/simple-translator-logs/', 'simple-translator') . '</p>';
    }

    /**
     * Sanitize languages array
     *
     * @param array $languages Languages to sanitize
     * @return array Sanitized languages
     */
    public function sanitize_languages($languages) {
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
     * Sanitize post types array
     *
     * @param array $post_types Post types to sanitize
     * @return array Sanitized post types
     */
    public function sanitize_post_types($post_types) {
        if (!is_array($post_types)) {
            return array('post', 'page');
        }

        $available_post_types = get_post_types(array('public' => true));
        $sanitized = array();

        foreach ($post_types as $post_type) {
            $post_type = sanitize_text_field($post_type);
            if (in_array($post_type, $available_post_types, true) && $post_type !== 'attachment') {
                $sanitized[] = $post_type;
            }
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
        // Load settings page assets
        if ('settings_page_' . $this->page_slug === $hook) {
            wp_enqueue_style(
                'st-admin-settings',
                ST_PLUGIN_URL . 'admin/assets/css/admin.css',
                array(),
                ST_VERSION
            );
        }

        // Load menu translation assets
        $this->enqueue_menu_translation_assets($hook);
    }

    /**
     * Add settings link to plugins page
     *
     * @param array $links Plugin action links
     * @return array Modified links
     */
    public function add_settings_link($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=' . $this->page_slug),
            __('Settings', 'simple-translator')
        );

        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Handle activation redirect
     */
    public function activation_redirect() {
        // Check if we should redirect
        if (get_option('st_activation_redirect', false)) {
            delete_option('st_activation_redirect');

            // Don't redirect on network activation or if multiple plugins activated
            if (!is_network_admin() && !isset($_GET['activate-multi'])) {
                wp_safe_redirect(admin_url('options-general.php?page=' . $this->page_slug));
                exit;
            }
        }
    }

    /**
     * AJAX handler for menu cloning
     */
    public function ajax_clone_menu() {
        // Verify nonce
        check_ajax_referer('st_clone_menu', 'nonce');

        // Check user capabilities
        if (!current_user_can('edit_theme_options')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to manage menus.', 'simple-translator')
            ));
        }

        // Get parameters
        $source_menu_id = isset($_POST['source_menu_id']) ? intval($_POST['source_menu_id']) : 0;
        $target_language = isset($_POST['target_language']) ? sanitize_text_field($_POST['target_language']) : '';

        // Validate inputs
        if (!$source_menu_id || !$target_language) {
            wp_send_json_error(array(
                'message' => __('Invalid menu ID or target language.', 'simple-translator')
            ));
        }

        // Get plugin instance and menu handler
        $plugin = \SimpleTranslator\Plugin::get_instance();
        $menu_handler = $plugin->menu_handler;

        // Clone the menu
        $result = $menu_handler->clone_menu($source_menu_id, $target_language);

        // Check for errors
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }

        // Build success message with location details
        $message = __('Menu cloned successfully!', 'simple-translator');

        if (!empty($result['locations'])) {
            $location_names = array();
            $registered_locations = get_registered_nav_menus();

            foreach ($result['locations'] as $location) {
                if (isset($registered_locations[$location])) {
                    $location_names[] = $registered_locations[$location];
                }
            }

            if (!empty($location_names)) {
                $message .= ' ' . sprintf(
                    /* translators: %s: comma-separated list of menu locations */
                    __('Assigned to: %s', 'simple-translator'),
                    implode(', ', $location_names)
                );
            }
        } else {
            $message .= ' ' . __('Note: Source menu was not assigned to any location, so the cloned menu was not auto-assigned.', 'simple-translator');
        }

        wp_send_json_success(array(
            'message' => $message,
            'new_menu_id' => $result['menu_id'],
            'assigned_locations' => $result['locations']
        ));
    }

    /**
     * Enqueue menu translation assets
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_menu_translation_assets($hook) {
        // Only load on nav-menus.php page
        if ($hook !== 'nav-menus.php') {
            return;
        }

        // Enqueue JavaScript
        wp_enqueue_script(
            'st-menu-translation',
            ST_PLUGIN_URL . 'admin/js/menu-translation.js',
            array('jquery'),
            ST_VERSION,
            true
        );

        // Localize script with nonce and AJAX URL
        wp_localize_script(
            'st-menu-translation',
            'stMenuTranslation',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('st_clone_menu'),
                'strings' => array(
                    'cloning' => __('Cloning menu...', 'simple-translator'),
                    'success' => __('Menu cloned successfully!', 'simple-translator'),
                    'error' => __('Error cloning menu.', 'simple-translator'),
                    'confirmClone' => __('This will create a copy of the selected menu for the target language. Continue?', 'simple-translator')
                )
            )
        );

        // Enqueue CSS
        wp_enqueue_style(
            'st-menu-translation',
            ST_PLUGIN_URL . 'admin/css/menu-translation.css',
            array(),
            ST_VERSION
        );
    }

    /**
     * Register menu translation meta box
     */
    public function register_menu_translation_meta_box() {
        add_meta_box(
            'st-menu-translation-box',
            __('Simple Translator - Menu Translation', 'simple-translator'),
            array($this, 'render_menu_translation_box'),
            'nav-menus',
            'side',
            'default'
        );
    }

    /**
     * Render menu translation box on nav-menus.php
     */
    public function render_menu_translation_box() {
        // Get all menus
        $menus = wp_get_nav_menus();

        // Get enabled languages from settings
        $enabled_languages = get_option('st_enabled_languages', array('en', 'es'));

        // Get language names
        $available_languages = $this->get_available_languages();

        // Prepare language options
        $language_options = array();
        foreach ($enabled_languages as $lang_code) {
            if (isset($available_languages[$lang_code])) {
                $language_options[$lang_code] = $available_languages[$lang_code];
            }
        }

        // Include the view file
        include ST_PLUGIN_DIR . 'admin/views/menu-translation.php';
    }
}
