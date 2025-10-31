<?php
/**
 * Translation Admin Class
 *
 * Handles translation metabox and admin interface
 *
 * @package SimpleTranslator
 */

namespace SimpleTranslator;

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Translation Admin - Metabox and admin functionality
 */
class Translation_Admin {

    /**
     * Initialize the admin functionality
     */
    public function init() {
        // Add metabox to edit screens
        add_action('add_meta_boxes', array($this, 'add_translation_metabox'));

        // Enqueue admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        // AJAX handlers
        add_action('wp_ajax_st_create_translation', array($this, 'ajax_create_translation'));
        add_action('wp_ajax_st_update_status', array($this, 'ajax_update_status'));
        add_action('wp_ajax_st_sync_translation', array($this, 'ajax_sync_translation'));

        // Post list columns
        add_filter('manage_posts_columns', array($this, 'add_translation_column'));
        add_filter('manage_pages_columns', array($this, 'add_translation_column'));
        add_action('manage_posts_custom_column', array($this, 'render_translation_column'), 10, 2);
        add_action('manage_pages_custom_column', array($this, 'render_translation_column'), 10, 2);

        // Admin notices
        add_action('admin_notices', array($this, 'show_translation_notices'));

        // Quick edit
        add_action('quick_edit_custom_box', array($this, 'quick_edit_custom_box'), 10, 2);
        add_action('save_post', array($this, 'save_quick_edit'), 10, 2);
    }

    /**
     * Add translation metabox to post edit screens
     */
    public function add_translation_metabox() {
        $post_types = get_option('st_post_types', array('post', 'page'));

        foreach ($post_types as $post_type) {
            add_meta_box(
                'st_translations',
                __('Translations', 'simple-translator'),
                array($this, 'render_metabox'),
                $post_type,
                'side',
                'high'
            );
        }
    }

    /**
     * Render translation metabox
     *
     * @param \WP_Post $post Post object
     */
    public function render_metabox($post) {
        // Add nonce for security
        wp_nonce_field('st_translation_nonce', 'st_nonce');

        // Get translation data
        $current_lang = get_post_meta($post->ID, '_language', true);
        if (!$current_lang) {
            $current_lang = get_option('st_default_language', 'en');
        }

        $status = get_post_meta($post->ID, '_translation_status', true);
        $clone_manager = new Clone_Manager();
        $translations = $clone_manager->get_translations($post->ID);
        $enabled_languages = get_option('st_enabled_languages', array('en', 'es'));

        // Check for forms and ACF relationships
        $forms_need_translation = get_post_meta($post->ID, '_forms_need_translation', true);
        $acf_needs_review = get_post_meta($post->ID, '_acf_relationships_need_review', true);

        // Include the metabox template
        include ST_PLUGIN_DIR . 'admin/views/metabox-translations.php';
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_scripts($hook) {
        // Only load on post edit screens
        if (!in_array($hook, array('post.php', 'post-new.php', 'edit.php'), true)) {
            return;
        }

        // Check if current post type is translatable
        global $post;
        if ($post) {
            $post_types = get_option('st_post_types', array('post', 'page'));
            if (!in_array($post->post_type, $post_types, true)) {
                return;
            }
        }

        // Enqueue CSS
        wp_enqueue_style(
            'st-admin',
            ST_PLUGIN_URL . 'admin/assets/css/admin.css',
            array(),
            ST_VERSION
        );

        wp_enqueue_style(
            'st-metabox',
            ST_PLUGIN_URL . 'admin/assets/css/metabox.css',
            array(),
            ST_VERSION
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'st-translation-admin',
            ST_PLUGIN_URL . 'admin/assets/js/translation-admin.js',
            array('jquery'),
            ST_VERSION,
            true
        );

        // Localize script
        wp_localize_script('st-translation-admin', 'stAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('st_translation_nonce'),
            'strings' => array(
                'creating' => __('Creating...', 'simple-translator'),
                'created' => __('Translation created successfully', 'simple-translator'),
                'error' => __('Error creating translation', 'simple-translator'),
                'confirmDelete' => __('Are you sure you want to delete this translation?', 'simple-translator'),
                'syncing' => __('Syncing...', 'simple-translator'),
                'synced' => __('Translation synced successfully', 'simple-translator'),
            ),
        ));
    }

    /**
     * AJAX handler for creating translations
     */
    public function ajax_create_translation() {
        // Verify nonce
        check_ajax_referer('st_translation_nonce', 'nonce');

        // Check capabilities
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('You do not have permission to create translations.', 'simple-translator'));
        }

        // Get parameters
        $source_id = isset($_POST['source_id']) ? intval($_POST['source_id']) : 0;
        $target_lang = isset($_POST['target_lang']) ? sanitize_text_field($_POST['target_lang']) : '';

        if (!$source_id || !$target_lang) {
            wp_send_json_error(__('Invalid parameters.', 'simple-translator'));
        }

        // Create translation
        $clone_manager = new Clone_Manager();
        $result = $clone_manager->create_translation($source_id, $target_lang);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        // Return success with edit URL
        wp_send_json_success(array(
            'message' => __('Translation created successfully', 'simple-translator'),
            'edit_url' => get_edit_post_link($result, 'raw'),
            'post_id' => $result,
            'lang' => $target_lang,
        ));
    }

    /**
     * AJAX handler for updating translation status
     */
    public function ajax_update_status() {
        // Verify nonce
        check_ajax_referer('st_translation_nonce', 'nonce');

        // Check capabilities
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('You do not have permission to update translation status.', 'simple-translator'));
        }

        // Get parameters
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        if (!$post_id || !$status) {
            wp_send_json_error(__('Invalid parameters.', 'simple-translator'));
        }

        // Update status
        $clone_manager = new Clone_Manager();
        $result = $clone_manager->update_translation_status($post_id, $status);

        if (!$result) {
            wp_send_json_error(__('Failed to update status.', 'simple-translator'));
        }

        wp_send_json_success(array(
            'message' => __('Status updated successfully', 'simple-translator'),
            'status' => $status,
        ));
    }

    /**
     * AJAX handler for syncing translation from source
     */
    public function ajax_sync_translation() {
        // Verify nonce
        check_ajax_referer('st_translation_nonce', 'nonce');

        // Check capabilities
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('You do not have permission to sync translations.', 'simple-translator'));
        }

        // Get parameters
        $target_id = isset($_POST['target_id']) ? intval($_POST['target_id']) : 0;

        if (!$target_id) {
            wp_send_json_error(__('Invalid parameters.', 'simple-translator'));
        }

        // Sync translation
        $clone_manager = new Clone_Manager();
        $result = $clone_manager->sync_from_source($target_id);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success(array(
            'message' => __('Translation synced successfully', 'simple-translator'),
        ));
    }

    /**
     * Add translation column to post list
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_translation_column($columns) {
        // Insert after title column
        $new_columns = array();

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;

            if ($key === 'title') {
                $new_columns['st_translations'] = __('Translations', 'simple-translator');
            }
        }

        return $new_columns;
    }

    /**
     * Render translation column content
     *
     * @param string $column  Column name
     * @param int    $post_id Post ID
     */
    public function render_translation_column($column, $post_id) {
        if ($column !== 'st_translations') {
            return;
        }

        $clone_manager = new Clone_Manager();
        $translations = $clone_manager->get_translations($post_id);
        $enabled_languages = get_option('st_enabled_languages', array('en', 'es'));
        $current_lang = get_post_meta($post_id, '_language', true);

        if (!$current_lang) {
            $current_lang = get_option('st_default_language', 'en');
        }

        echo '<div class="st-translation-indicators">';

        foreach ($enabled_languages as $lang) {
            $exists = isset($translations[$lang]);
            $is_current = ($lang === $current_lang);
            $status = 'missing';

            if ($exists) {
                $trans_id = $translations[$lang];
                $status = get_post_meta($trans_id, '_translation_status', true);
                if (!$status) {
                    $status = 'completed';
                }
            }

            $class = array('st-lang-indicator');
            $class[] = $exists ? 'st-exists' : 'st-missing';
            $class[] = 'st-status-' . $status;
            if ($is_current) {
                $class[] = 'st-current';
            }

            $title = strtoupper($lang);
            if ($is_current) {
                $title .= ' (current)';
            } elseif ($exists) {
                $title .= ' - ' . ucfirst(str_replace('_', ' ', $status));
            } else {
                $title .= ' - Not translated';
            }

            printf(
                '<span class="%s" title="%s">%s</span>',
                esc_attr(implode(' ', $class)),
                esc_attr($title),
                esc_html(strtoupper($lang))
            );
        }

        echo '</div>';
    }

    /**
     * Show admin notices for translation management
     */
    public function show_translation_notices() {
        $screen = get_current_screen();

        if (!$screen || $screen->base !== 'post') {
            return;
        }

        global $post;

        if (!$post) {
            return;
        }

        // Check if this is a translation
        $status = get_post_meta($post->ID, '_translation_status', true);
        $forms_need_translation = get_post_meta($post->ID, '_forms_need_translation', true);
        $acf_needs_review = get_post_meta($post->ID, '_acf_relationships_need_review', true);

        // Notice for translation status
        if ($status === 'not_started') {
            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                esc_html__('This is a translation draft. Please translate the content before publishing.', 'simple-translator')
            );
        }

        // Notice for forms
        if ($forms_need_translation) {
            printf(
                '<div class="notice notice-info"><p>%s</p></div>',
                esc_html__('This page contains forms that may need translation or localization.', 'simple-translator')
            );
        }

        // Notice for ACF relationships
        if ($acf_needs_review) {
            printf(
                '<div class="notice notice-info"><p>%s</p></div>',
                esc_html__('This page contains ACF relationship fields that need manual review.', 'simple-translator')
            );
        }
    }

    /**
     * Add fields to quick edit
     *
     * @param string $column_name Column name
     * @param string $post_type   Post type
     */
    public function quick_edit_custom_box($column_name, $post_type) {
        if ($column_name !== 'st_translations') {
            return;
        }

        $enabled_languages = get_option('st_enabled_languages', array('en', 'es'));

        ?>
        <fieldset class="inline-edit-col-right">
            <div class="inline-edit-col">
                <label>
                    <span class="title"><?php esc_html_e('Language', 'simple-translator'); ?></span>
                    <select name="st_language" class="st-language-select">
                        <?php foreach ($enabled_languages as $lang) : ?>
                            <option value="<?php echo esc_attr($lang); ?>">
                                <?php echo esc_html(strtoupper($lang)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        </fieldset>
        <?php
    }

    /**
     * Save quick edit data
     *
     * @param int      $post_id Post ID
     * @param \WP_Post $post    Post object
     */
    public function save_quick_edit($post_id, $post) {
        // Verify this is not an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Update language if set
        if (isset($_POST['st_language'])) {
            $language = sanitize_text_field($_POST['st_language']);
            $enabled_languages = get_option('st_enabled_languages', array('en', 'es'));

            if (in_array($language, $enabled_languages, true)) {
                update_post_meta($post_id, '_language', $language);
            }
        }
    }
}
