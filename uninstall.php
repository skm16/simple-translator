<?php
/**
 * Uninstall Script
 *
 * Fired when the plugin is uninstalled
 *
 * @package SimpleTranslator
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Remove plugin data on uninstall
 */
function simple_translator_uninstall() {
    global $wpdb;

    // Check if we should delete data (option to keep data)
    $delete_data = get_option('st_delete_data_on_uninstall', false);

    if (!$delete_data) {
        return; // Keep all data
    }

    // Remove all plugin options
    $options = array(
        'st_enabled_languages',
        'st_default_language',
        'st_url_structure',
        'st_post_types',
        'st_auto_clone',
        'st_sync_taxonomies',
        'st_add_switcher_to_menu',
        'st_debug_mode',
        'st_delete_data_on_uninstall',
        'st_activation_redirect',
    );

    foreach ($options as $option) {
        delete_option($option);
    }

    // Remove network options (if multisite)
    if (is_multisite()) {
        delete_site_option('simple_translator_languages');
        delete_site_option('simple_translator_version');
        delete_site_option('simple_translator_translation_memory');
    }

    // Remove all translation meta data
    $meta_keys = array(
        '_language',
        '_translation_group_id',
        '_translation_status',
        '_source_post_id',
        '_translation_last_sync',
        '_forms_need_translation',
        '_formassembly_forms_original',
        '_acf_relationships_need_review',
    );

    foreach ($meta_keys as $meta_key) {
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
                $meta_key
            )
        );
    }

    // Remove all transients
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
        WHERE option_name LIKE '_transient_st_%'
        OR option_name LIKE '_transient_timeout_st_%'"
    );

    // Remove log files
    $upload_dir = wp_upload_dir();
    $log_file = $upload_dir['basedir'] . '/simple-translator-debug.log';

    if (file_exists($log_file)) {
        unlink($log_file);
    }

    // Remove log rotations
    for ($i = 0; $i <= 3; $i++) {
        $rotation = $log_file . '.' . $i;
        if (file_exists($rotation)) {
            unlink($rotation);
        }
    }

    // Clear any cached data
    wp_cache_flush();

    // Flush rewrite rules
    flush_rewrite_rules();
}

// Run uninstall
simple_translator_uninstall();
