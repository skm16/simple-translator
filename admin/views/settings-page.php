<?php
/**
 * Settings Page Template
 *
 * Admin settings page for Simple Translator
 *
 * @package SimpleTranslator
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Variables available from render_settings_page:
// $stats - Translation statistics array
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php settings_errors(); ?>

    <div class="st-admin-wrapper">
        <div class="st-admin-main">
            <form method="post" action="options.php">
                <?php
                settings_fields('st_settings');
                do_settings_sections('simple-translator-settings');
                ?>

                <div class="st-save-section">
                    <?php submit_button(__('Save Settings', 'simple-translator'), 'primary large'); ?>
                </div>
            </form>

            <!-- Quick Actions -->
            <div class="st-card st-quick-actions">
                <h2><?php esc_html_e('Quick Actions', 'simple-translator'); ?></h2>
                <div class="st-actions-grid">
                    <a href="<?php echo admin_url('edit.php'); ?>" class="st-action-button">
                        <span class="dashicons dashicons-translation"></span>
                        <span><?php esc_html_e('Manage Translations', 'simple-translator'); ?></span>
                    </a>
                    <a href="<?php echo wp_nonce_url(admin_url('options-general.php?page=simple-translator-settings&action=flush_rewrite'), 'st_quick_action_flush_rewrite'); ?>"
                       class="st-action-button"
                       onclick="return confirm('<?php esc_attr_e('This will flush rewrite rules. Continue?', 'simple-translator'); ?>');">
                        <span class="dashicons dashicons-update"></span>
                        <span><?php esc_html_e('Flush Rewrite Rules', 'simple-translator'); ?></span>
                    </a>
                    <a href="<?php echo wp_nonce_url(admin_url('options-general.php?page=simple-translator-settings&action=clear_cache'), 'st_quick_action_clear_cache'); ?>"
                       class="st-action-button"
                       onclick="return confirm('<?php esc_attr_e('This will clear all translation caches. Continue?', 'simple-translator'); ?>');">
                        <span class="dashicons dashicons-trash"></span>
                        <span><?php esc_html_e('Clear Translation Cache', 'simple-translator'); ?></span>
                    </a>
                    <a href="<?php echo wp_nonce_url(admin_url('options-general.php?page=simple-translator-settings&action=fix_slugs'), 'st_quick_action_fix_slugs'); ?>"
                       class="st-action-button"
                       onclick="return confirm('<?php esc_attr_e('This will fix translation slugs that have language suffixes (e.g., -es, -fr). Continue?', 'simple-translator'); ?>');">
                        <span class="dashicons dashicons-admin-tools"></span>
                        <span><?php esc_html_e('Fix Translation Slugs', 'simple-translator'); ?></span>
                    </a>
                    <a href="<?php echo admin_url('widgets.php'); ?>" class="st-action-button">
                        <span class="dashicons dashicons-admin-appearance"></span>
                        <span><?php esc_html_e('Configure Widgets', 'simple-translator'); ?></span>
                    </a>
                </div>
            </div>

            <!-- Usage Instructions -->
            <div class="st-card st-usage-instructions">
                <h2><?php esc_html_e('How to Use', 'simple-translator'); ?></h2>
                <ol class="st-steps">
                    <li>
                        <strong><?php esc_html_e('Enable Languages:', 'simple-translator'); ?></strong>
                        <?php esc_html_e('Select which languages you want to use on your site above.', 'simple-translator'); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Edit a Post or Page:', 'simple-translator'); ?></strong>
                        <?php esc_html_e('Open any post or page in the editor.', 'simple-translator'); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Create Translation:', 'simple-translator'); ?></strong>
                        <?php esc_html_e('Look for the "Translations" metabox in the sidebar and click "Create Translation".', 'simple-translator'); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Translate Content:', 'simple-translator'); ?></strong>
                        <?php esc_html_e('Edit the cloned post to translate the content.', 'simple-translator'); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Add Language Switcher:', 'simple-translator'); ?></strong>
                        <?php esc_html_e('Add the "Language Switcher" widget to your sidebar or use the shortcode [st_language_switcher].', 'simple-translator'); ?>
                    </li>
                </ol>
            </div>
        </div>

        <div class="st-admin-sidebar">
            <!-- Translation Statistics -->
            <div class="st-card st-stats-card">
                <h2><?php esc_html_e('Translation Statistics', 'simple-translator'); ?></h2>

                <div class="st-stat-item">
                    <div class="st-stat-label"><?php esc_html_e('Translation Groups', 'simple-translator'); ?></div>
                    <div class="st-stat-value"><?php echo esc_html(number_format_i18n($stats['total_groups'])); ?></div>
                </div>

                <?php if (!empty($stats['by_language'])) : ?>
                    <div class="st-stat-group">
                        <h3><?php esc_html_e('By Language', 'simple-translator'); ?></h3>
                        <?php foreach ($stats['by_language'] as $lang => $count) : ?>
                            <div class="st-stat-item-small">
                                <span class="st-lang-badge"><?php echo esc_html(strtoupper($lang)); ?></span>
                                <span class="st-stat-count"><?php echo esc_html(number_format_i18n($count)); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($stats['by_status'])) : ?>
                    <div class="st-stat-group">
                        <h3><?php esc_html_e('By Status', 'simple-translator'); ?></h3>
                        <?php foreach ($stats['by_status'] as $status => $count) : ?>
                            <div class="st-stat-item-small">
                                <span class="st-status-badge status-<?php echo esc_attr($status); ?>">
                                    <?php echo esc_html(ucfirst(str_replace('_', ' ', $status))); ?>
                                </span>
                                <span class="st-stat-count"><?php echo esc_html(number_format_i18n($count)); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Plugin Information -->
            <div class="st-card st-info-card">
                <h2><?php esc_html_e('Plugin Information', 'simple-translator'); ?></h2>

                <div class="st-info-item">
                    <strong><?php esc_html_e('Version:', 'simple-translator'); ?></strong>
                    <span><?php echo esc_html(ST_VERSION); ?></span>
                </div>

                <div class="st-info-item">
                    <strong><?php esc_html_e('WordPress:', 'simple-translator'); ?></strong>
                    <span><?php echo esc_html(get_bloginfo('version')); ?></span>
                </div>

                <div class="st-info-item">
                    <strong><?php esc_html_e('PHP:', 'simple-translator'); ?></strong>
                    <span><?php echo esc_html(phpversion()); ?></span>
                </div>

                <?php if (is_multisite()) : ?>
                    <div class="st-info-item">
                        <strong><?php esc_html_e('Multisite:', 'simple-translator'); ?></strong>
                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Support & Documentation -->
            <div class="st-card st-support-card">
                <h2><?php esc_html_e('Support & Documentation', 'simple-translator'); ?></h2>

                <ul class="st-support-links">
                    <li>
                        <a href="<?php echo esc_url('https://github.com/yourusername/simple-translator/wiki'); ?>" target="_blank">
                            <span class="dashicons dashicons-book"></span>
                            <?php esc_html_e('Documentation', 'simple-translator'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url('https://github.com/yourusername/simple-translator/issues'); ?>" target="_blank">
                            <span class="dashicons dashicons-sos"></span>
                            <?php esc_html_e('Report an Issue', 'simple-translator'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo esc_url('https://github.com/yourusername/simple-translator'); ?>" target="_blank">
                            <span class="dashicons dashicons-github"></span>
                            <?php esc_html_e('View on GitHub', 'simple-translator'); ?>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Shortcodes Reference -->
            <div class="st-card st-shortcodes-card">
                <h2><?php esc_html_e('Shortcodes', 'simple-translator'); ?></h2>

                <div class="st-shortcode-item">
                    <code>[st_language_switcher]</code>
                    <p class="description"><?php esc_html_e('Display the language switcher', 'simple-translator'); ?></p>
                </div>

                <div class="st-shortcode-item">
                    <code>[st_language_switcher format="dropdown"]</code>
                    <p class="description"><?php esc_html_e('Dropdown format', 'simple-translator'); ?></p>
                </div>

                <div class="st-shortcode-item">
                    <code>[st_language_switcher format="list" show_flags="true"]</code>
                    <p class="description"><?php esc_html_e('List with flags', 'simple-translator'); ?></p>
                </div>
            </div>

            <!-- Template Functions -->
            <div class="st-card st-template-card">
                <h2><?php esc_html_e('Template Functions', 'simple-translator'); ?></h2>

                <div class="st-template-item">
                    <code>st_get_current_language()</code>
                    <p class="description"><?php esc_html_e('Get current language code', 'simple-translator'); ?></p>
                </div>

                <div class="st-template-item">
                    <code>st_get_translations($post_id)</code>
                    <p class="description"><?php esc_html_e('Get all translations', 'simple-translator'); ?></p>
                </div>

                <div class="st-template-item">
                    <code>st_language_switcher()</code>
                    <p class="description"><?php esc_html_e('Display switcher in theme', 'simple-translator'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.st-admin-wrapper {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 20px;
    margin-top: 20px;
}

@media (max-width: 1200px) {
    .st-admin-wrapper {
        grid-template-columns: 1fr;
    }
    .st-admin-sidebar {
        order: -1;
    }
}

.st-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
}

.st-card h2 {
    margin-top: 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #e5e5e5;
    font-size: 16px;
}

.st-card h3 {
    margin: 15px 0 10px;
    font-size: 14px;
    color: #555;
}

.st-save-section {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e5e5e5;
}

/* Quick Actions */
.st-actions-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin-top: 15px;
}

.st-action-button {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: #f6f7f7;
    border: 1px solid #dcdcde;
    border-radius: 4px;
    text-decoration: none;
    color: #2271b1;
    transition: all 0.2s;
    text-align: center;
}

.st-action-button:hover {
    background: #fff;
    border-color: #2271b1;
    color: #135e96;
}

.st-action-button .dashicons {
    font-size: 32px;
    width: 32px;
    height: 32px;
    margin-bottom: 8px;
}

/* Usage Instructions */
.st-steps {
    margin: 15px 0;
    padding-left: 20px;
}

.st-steps li {
    margin-bottom: 12px;
    line-height: 1.6;
}

/* Statistics */
.st-stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f1;
}

.st-stat-item:last-child {
    border-bottom: none;
}

.st-stat-label {
    font-weight: 500;
    color: #555;
}

.st-stat-value {
    font-size: 24px;
    font-weight: 600;
    color: #2271b1;
}

.st-stat-group {
    margin-top: 15px;
}

.st-stat-item-small {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
}

.st-lang-badge {
    display: inline-block;
    padding: 3px 8px;
    background: #2271b1;
    color: #fff;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.st-status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 500;
}

.st-status-badge.status-not_started {
    background: #f0f0f1;
    color: #666;
}

.st-status-badge.status-in_progress {
    background: #fff3cd;
    color: #856404;
}

.st-status-badge.status-completed {
    background: #d4edda;
    color: #155724;
}

.st-status-badge.status-needs_update {
    background: #f8d7da;
    color: #721c24;
}

.st-stat-count {
    font-weight: 600;
    color: #555;
}

/* Info Card */
.st-info-item {
    padding: 8px 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.st-info-item strong {
    color: #555;
}

/* Support Links */
.st-support-links {
    margin: 15px 0 0;
    padding: 0;
    list-style: none;
}

.st-support-links li {
    margin-bottom: 10px;
}

.st-support-links a {
    display: flex;
    align-items: center;
    padding: 8px;
    text-decoration: none;
    border-radius: 3px;
    transition: background 0.2s;
}

.st-support-links a:hover {
    background: #f6f7f7;
}

.st-support-links .dashicons {
    margin-right: 8px;
    color: #2271b1;
}

/* Shortcodes & Templates */
.st-shortcode-item,
.st-template-item {
    margin-bottom: 15px;
}

.st-shortcode-item code,
.st-template-item code {
    display: block;
    padding: 8px 10px;
    background: #f6f7f7;
    border-left: 3px solid #2271b1;
    font-size: 12px;
    margin-bottom: 5px;
}

.st-shortcode-item .description,
.st-template-item .description {
    margin: 5px 0 0;
    font-size: 12px;
}
</style>

<?php
// Handle quick actions
if (isset($_GET['action']) && isset($_GET['_wpnonce'])) {
    $action = sanitize_text_field($_GET['action']);

    // Verify nonce for security
    if (!wp_verify_nonce($_GET['_wpnonce'], 'st_quick_action_' . $action)) {
        wp_die(__('Security check failed. Please try again.', 'simple-translator'));
    }

    if ($action === 'flush_rewrite' && current_user_can('manage_options')) {
        flush_rewrite_rules();
        add_settings_error(
            'st_messages',
            'st_rewrite_flushed',
            __('Rewrite rules flushed successfully.', 'simple-translator'),
            'success'
        );
    }

    if ($action === 'clear_cache' && current_user_can('manage_options')) {
        global $wpdb;
        // Use prepared statement to prevent SQL injection
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                WHERE option_name LIKE %s
                OR option_name LIKE %s",
                $wpdb->esc_like('_transient_st_') . '%',
                $wpdb->esc_like('_transient_timeout_st_') . '%'
            )
        );
        add_settings_error(
            'st_messages',
            'st_cache_cleared',
            __('Translation cache cleared successfully.', 'simple-translator'),
            'success'
        );
    }

    if ($action === 'fix_slugs' && current_user_can('manage_options')) {
        $plugin = SimpleTranslator\Plugin::get_instance();
        $result = $plugin->clone_manager->fix_translation_slugs();

        if ($result['success']) {
            add_settings_error(
                'st_messages',
                'st_slugs_fixed',
                $result['message'] . ' ' . sprintf(
                    __('(%d checked, %d fixed)', 'simple-translator'),
                    $result['total_checked'],
                    $result['fixed_count']
                ),
                'success'
            );

            // Flush rewrite rules after fixing slugs
            flush_rewrite_rules();
        } else {
            add_settings_error(
                'st_messages',
                'st_slugs_error',
                __('Error fixing slugs. Please try again.', 'simple-translator'),
                'error'
            );
        }
    }
}
?>
