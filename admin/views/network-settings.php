<?php
/**
 * Network Settings Page Template
 *
 * Network admin settings page for Simple Translator (Multisite)
 *
 * @package SimpleTranslator
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Variables available from render_network_settings_page:
// $network_languages - Network languages array
// $network_version - Plugin version
// $translation_memory - Translation memory array
// $network_stats - Network statistics array
?>

<div class="wrap">
    <h1><?php esc_html_e('Simple Translator Network Settings', 'simple-translator'); ?></h1>

    <?php if (isset($_GET['updated']) && $_GET['updated'] === 'true') : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Network settings saved successfully.', 'simple-translator'); ?></p>
        </div>
    <?php endif; ?>

    <div class="st-admin-wrapper">
        <div class="st-admin-main">
            <!-- Network Settings Form -->
            <form method="post" action="<?php echo esc_url(network_admin_url('edit.php?action=st_network_settings')); ?>">
                <?php wp_nonce_field('st_network_settings'); ?>

                <!-- General Network Settings -->
                <div class="st-card">
                    <h2><?php esc_html_e('General Network Settings', 'simple-translator'); ?></h2>
                    <p class="description">
                        <?php esc_html_e('These settings apply network-wide across all sites.', 'simple-translator'); ?>
                    </p>

                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e('Available Languages', 'simple-translator'); ?></label>
                            </th>
                            <td>
                                <fieldset>
                                    <?php
                                    $available_languages = array(
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

                                    foreach ($available_languages as $code => $name) :
                                        $checked = in_array($code, $network_languages, true) ? 'checked' : '';
                                        ?>
                                        <label>
                                            <input type="checkbox" name="st_network_languages[]" value="<?php echo esc_attr($code); ?>" <?php echo $checked; ?>>
                                            <?php echo esc_html($name); ?> (<?php echo esc_html(strtoupper($code)); ?>)
                                        </label><br>
                                    <?php endforeach; ?>
                                </fieldset>
                                <p class="description">
                                    <?php esc_html_e('Select languages available across the entire network. Individual sites can choose which of these to enable.', 'simple-translator'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="st_network_default_language"><?php esc_html_e('Network Default Language', 'simple-translator'); ?></label>
                            </th>
                            <td>
                                <select name="st_network_default_language" id="st_network_default_language">
                                    <?php
                                    $network_default = get_site_option('simple_translator_default_language', 'en');
                                    foreach ($network_languages as $code) :
                                        if (isset($available_languages[$code])) :
                                            ?>
                                            <option value="<?php echo esc_attr($code); ?>" <?php selected($network_default, $code); ?>>
                                                <?php echo esc_html($available_languages[$code]); ?> (<?php echo esc_html(strtoupper($code)); ?>)
                                            </option>
                                        <?php
                                        endif;
                                    endforeach;
                                    ?>
                                </select>
                                <p class="description">
                                    <?php esc_html_e('Default language for new sites in the network.', 'simple-translator'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="st_network_switcher_enabled"><?php esc_html_e('Network-wide Switcher', 'simple-translator'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="st_network_switcher_enabled" id="st_network_switcher_enabled" value="1"
                                        <?php checked(get_site_option('simple_translator_network_switcher_enabled', false), true); ?>>
                                    <?php esc_html_e('Enable language switcher network-wide', 'simple-translator'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('When enabled, the language switcher will be available on all sites.', 'simple-translator'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="st_network_debug_mode"><?php esc_html_e('Network Debug Mode', 'simple-translator'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="st_network_debug_mode" id="st_network_debug_mode" value="1"
                                        <?php checked(get_site_option('simple_translator_network_debug_mode', false), true); ?>>
                                    <?php esc_html_e('Enable debug logging network-wide', 'simple-translator'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Enable detailed logging for troubleshooting across all sites.', 'simple-translator'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button(__('Save Network Settings', 'simple-translator'), 'primary large'); ?>
                </div>
            </form>

            <!-- Network Sites Overview -->
            <div class="st-card">
                <h2><?php esc_html_e('Sites in Network', 'simple-translator'); ?></h2>

                <form method="post" action="<?php echo esc_url(network_admin_url('edit.php?action=st_network_settings')); ?>">
                    <?php wp_nonce_field('st_network_settings'); ?>

                    <div class="st-bulk-actions">
                        <select name="bulk_action">
                            <option value=""><?php esc_html_e('Bulk Actions', 'simple-translator'); ?></option>
                            <option value="enable_translation"><?php esc_html_e('Enable Translation', 'simple-translator'); ?></option>
                            <option value="disable_translation"><?php esc_html_e('Disable Translation', 'simple-translator'); ?></option>
                            <option value="sync_settings"><?php esc_html_e('Sync Network Settings', 'simple-translator'); ?></option>
                            <option value="clear_cache"><?php esc_html_e('Clear Cache', 'simple-translator'); ?></option>
                        </select>
                        <?php submit_button(__('Apply', 'simple-translator'), 'secondary', 'submit', false); ?>
                    </div>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <td class="manage-column column-cb check-column">
                                    <input type="checkbox" id="select-all">
                                </td>
                                <th><?php esc_html_e('Site', 'simple-translator'); ?></th>
                                <th><?php esc_html_e('Default Language', 'simple-translator'); ?></th>
                                <th><?php esc_html_e('Enabled Languages', 'simple-translator'); ?></th>
                                <th><?php esc_html_e('Translation Groups', 'simple-translator'); ?></th>
                                <th><?php esc_html_e('Translations', 'simple-translator'); ?></th>
                                <th><?php esc_html_e('Status', 'simple-translator'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sites_data = $this->get_sites_with_translation_info();

                            foreach ($sites_data as $site_data) :
                                ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="sites[]" value="<?php echo esc_attr($site_data['blog_id']); ?>">
                                    </th>
                                    <td>
                                        <strong>
                                            <a href="<?php echo esc_url($site_data['url']); ?>" target="_blank">
                                                <?php echo esc_html($site_data['name']); ?>
                                            </a>
                                        </strong>
                                        <br>
                                        <span class="description"><?php echo esc_html($site_data['domain'] . $site_data['path']); ?></span>
                                    </td>
                                    <td>
                                        <span class="st-lang-badge">
                                            <?php echo esc_html(strtoupper($site_data['default_language'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        if (!empty($site_data['enabled_languages'])) {
                                            foreach ($site_data['enabled_languages'] as $lang) {
                                                echo '<span class="st-lang-badge-small">' . esc_html(strtoupper($lang)) . '</span> ';
                                            }
                                        } else {
                                            echo '<span class="description">' . esc_html__('None', 'simple-translator') . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo esc_html(number_format_i18n($site_data['translation_groups'])); ?></td>
                                    <td><?php echo esc_html(number_format_i18n($site_data['translations'])); ?></td>
                                    <td>
                                        <?php if ($site_data['translation_enabled']) : ?>
                                            <span class="st-status-active"><?php esc_html_e('Active', 'simple-translator'); ?></span>
                                        <?php else : ?>
                                            <span class="st-status-inactive"><?php esc_html_e('Disabled', 'simple-translator'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
            </div>
        </div>

        <div class="st-admin-sidebar">
            <!-- Network Statistics -->
            <div class="st-card st-stats-card">
                <h2><?php esc_html_e('Network Statistics', 'simple-translator'); ?></h2>

                <div class="st-stat-item">
                    <div class="st-stat-label"><?php esc_html_e('Total Sites', 'simple-translator'); ?></div>
                    <div class="st-stat-value"><?php echo esc_html(number_format_i18n($network_stats['total_sites'])); ?></div>
                </div>

                <div class="st-stat-item">
                    <div class="st-stat-label"><?php esc_html_e('Sites with Translations', 'simple-translator'); ?></div>
                    <div class="st-stat-value"><?php echo esc_html(number_format_i18n($network_stats['sites_with_translations'])); ?></div>
                </div>

                <div class="st-stat-item">
                    <div class="st-stat-label"><?php esc_html_e('Total Translation Groups', 'simple-translator'); ?></div>
                    <div class="st-stat-value"><?php echo esc_html(number_format_i18n($network_stats['total_translation_groups'])); ?></div>
                </div>

                <div class="st-stat-item">
                    <div class="st-stat-label"><?php esc_html_e('Total Translations', 'simple-translator'); ?></div>
                    <div class="st-stat-value"><?php echo esc_html(number_format_i18n($network_stats['total_translations'])); ?></div>
                </div>

                <?php if (!empty($network_stats['by_language'])) : ?>
                    <div class="st-stat-group">
                        <h3><?php esc_html_e('By Language', 'simple-translator'); ?></h3>
                        <?php foreach ($network_stats['by_language'] as $lang => $count) : ?>
                            <div class="st-stat-item-small">
                                <span class="st-lang-badge"><?php echo esc_html(strtoupper($lang)); ?></span>
                                <span class="st-stat-count"><?php echo esc_html(number_format_i18n($count)); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Network Information -->
            <div class="st-card st-info-card">
                <h2><?php esc_html_e('Network Information', 'simple-translator'); ?></h2>

                <div class="st-info-item">
                    <strong><?php esc_html_e('Plugin Version:', 'simple-translator'); ?></strong>
                    <span><?php echo esc_html($network_version); ?></span>
                </div>

                <div class="st-info-item">
                    <strong><?php esc_html_e('WordPress:', 'simple-translator'); ?></strong>
                    <span><?php echo esc_html(get_bloginfo('version')); ?></span>
                </div>

                <div class="st-info-item">
                    <strong><?php esc_html_e('PHP:', 'simple-translator'); ?></strong>
                    <span><?php echo esc_html(phpversion()); ?></span>
                </div>

                <div class="st-info-item">
                    <strong><?php esc_html_e('Network Sites:', 'simple-translator'); ?></strong>
                    <span><?php echo esc_html(number_format_i18n($network_stats['total_sites'])); ?></span>
                </div>
            </div>

            <!-- Network Actions -->
            <div class="st-card">
                <h2><?php esc_html_e('Network Actions', 'simple-translator'); ?></h2>

                <div class="st-network-actions">
                    <a href="<?php echo esc_url(add_query_arg('action', 'export_settings', network_admin_url('settings.php?page=' . $this->page_slug))); ?>"
                       class="button button-secondary button-large">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Export Settings', 'simple-translator'); ?>
                    </a>

                    <a href="<?php echo esc_url(add_query_arg('action', 'clear_network_cache', network_admin_url('settings.php?page=' . $this->page_slug))); ?>"
                       class="button button-secondary button-large"
                       onclick="return confirm('<?php esc_attr_e('Clear cache for all sites in the network?', 'simple-translator'); ?>');">
                        <span class="dashicons dashicons-trash"></span>
                        <?php esc_html_e('Clear All Cache', 'simple-translator'); ?>
                    </a>
                </div>
            </div>

            <!-- Support -->
            <div class="st-card st-support-card">
                <h2><?php esc_html_e('Support', 'simple-translator'); ?></h2>

                <ul class="st-support-links">
                    <li>
                        <a href="https://github.com/yourusername/simple-translator/wiki" target="_blank">
                            <span class="dashicons dashicons-book"></span>
                            <?php esc_html_e('Documentation', 'simple-translator'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="https://github.com/yourusername/simple-translator/issues" target="_blank">
                            <span class="dashicons dashicons-sos"></span>
                            <?php esc_html_e('Report an Issue', 'simple-translator'); ?>
                        </a>
                    </li>
                </ul>
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

.st-bulk-actions {
    margin-bottom: 15px;
    display: flex;
    gap: 10px;
    align-items: center;
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

.st-lang-badge-small {
    display: inline-block;
    padding: 2px 6px;
    background: #f0f0f1;
    color: #555;
    border-radius: 2px;
    font-size: 10px;
    font-weight: 500;
    margin-right: 3px;
}

.st-status-active {
    color: #00a32a;
    font-weight: 500;
}

.st-status-inactive {
    color: #999;
}

.st-stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f1;
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

.st-stat-count {
    font-weight: 600;
    color: #555;
}

.st-info-item {
    padding: 8px 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.st-network-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.st-network-actions .button {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

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
</style>

<script>
jQuery(document).ready(function($) {
    // Select all checkbox
    $('#select-all').on('change', function() {
        $('input[name="sites[]"]').prop('checked', this.checked);
    });

    // Individual checkboxes
    $('input[name="sites[]"]').on('change', function() {
        var all_checked = $('input[name="sites[]"]:checked').length === $('input[name="sites[]"]').length;
        $('#select-all').prop('checked', all_checked);
    });
});
</script>
