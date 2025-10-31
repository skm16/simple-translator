<?php
/**
 * Translation Metabox View
 *
 * Template for the translations metabox
 *
 * @package SimpleTranslator
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Variables available from the render_metabox method:
// $post - WP_Post object
// $current_lang - Current post language
// $status - Translation status
// $translations - Array of language => post_id pairs
// $enabled_languages - Array of enabled language codes
// $forms_need_translation - Boolean
// $acf_needs_review - Boolean
?>

<div class="st-translations-metabox">
    <!-- Current Language -->
    <div class="st-current-language">
        <strong><?php esc_html_e('Current Language:', 'simple-translator'); ?></strong>
        <span class="language-tag"><?php echo esc_html(strtoupper($current_lang)); ?></span>
        <span class="language-name"><?php echo esc_html(st_get_language_native_name($current_lang)); ?></span>

        <?php if ($status) : ?>
            <div class="translation-status status-<?php echo esc_attr($status); ?>">
                <?php echo esc_html(ucfirst(str_replace('_', ' ', $status))); ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Translations List -->
    <div class="st-translations-list">
        <strong><?php esc_html_e('Translations:', 'simple-translator'); ?></strong>

        <?php foreach ($enabled_languages as $lang) : ?>
            <?php if ($lang === $current_lang) continue; ?>

            <div class="translation-item" data-lang="<?php echo esc_attr($lang); ?>">
                <div class="translation-header">
                    <span class="language-label"><?php echo esc_html(strtoupper($lang)); ?></span>
                    <span class="language-name-small"><?php echo esc_html(st_get_language_native_name($lang)); ?></span>
                </div>

                <?php if (isset($translations[$lang])) : ?>
                    <?php
                    $trans_id = $translations[$lang];
                    $trans_post = get_post($trans_id);
                    $trans_status = get_post_meta($trans_id, '_translation_status', true);
                    $trans_modified = get_post_modified_time('U', false, $trans_id);
                    $trans_modified_date = human_time_diff($trans_modified, current_time('timestamp'));
                    ?>

                    <div class="translation-exists status-<?php echo esc_attr($trans_status); ?>">
                        <div class="translation-title">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php echo esc_html($trans_post->post_title); ?>
                        </div>

                        <div class="translation-meta">
                            <span class="translation-status-badge status-<?php echo esc_attr($trans_status); ?>">
                                <?php echo esc_html(ucfirst(str_replace('_', ' ', $trans_status))); ?>
                            </span>
                            <span class="translation-modified">
                                <?php
                                printf(
                                    /* translators: %s: time difference */
                                    esc_html__('Modified %s ago', 'simple-translator'),
                                    esc_html($trans_modified_date)
                                );
                                ?>
                            </span>
                        </div>

                        <div class="translation-actions">
                            <a href="<?php echo esc_url(get_edit_post_link($trans_id)); ?>"
                               class="button button-small">
                                <span class="dashicons dashicons-edit"></span>
                                <?php esc_html_e('Edit', 'simple-translator'); ?>
                            </a>
                            <a href="<?php echo esc_url(get_permalink($trans_id)); ?>"
                               class="button button-small"
                               target="_blank">
                                <span class="dashicons dashicons-visibility"></span>
                                <?php esc_html_e('View', 'simple-translator'); ?>
                            </a>
                            <?php if ($trans_status !== 'completed') : ?>
                                <button class="button button-small st-sync-translation"
                                        data-target="<?php echo esc_attr($trans_id); ?>"
                                        title="<?php esc_attr_e('Sync from source', 'simple-translator'); ?>">
                                    <span class="dashicons dashicons-update"></span>
                                    <?php esc_html_e('Sync', 'simple-translator'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php else : ?>

                    <div class="translation-missing">
                        <div class="translation-missing-text">
                            <span class="dashicons dashicons-warning"></span>
                            <?php esc_html_e('Not translated', 'simple-translator'); ?>
                        </div>

                        <button class="button button-primary button-small st-create-translation"
                                data-source="<?php echo esc_attr($post->ID); ?>"
                                data-lang="<?php echo esc_attr($lang); ?>">
                            <span class="dashicons dashicons-plus-alt"></span>
                            <?php esc_html_e('Create Translation', 'simple-translator'); ?>
                        </button>
                    </div>

                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Status Update (if this is a translation) -->
    <?php if ($status && st_is_translation($post->ID)) : ?>
        <div class="st-status-update">
            <label>
                <strong><?php esc_html_e('Update Status:', 'simple-translator'); ?></strong>
                <select class="st-status-select widefat" data-post="<?php echo esc_attr($post->ID); ?>">
                    <option value="not_started" <?php selected($status, 'not_started'); ?>>
                        <?php esc_html_e('Not Started', 'simple-translator'); ?>
                    </option>
                    <option value="in_progress" <?php selected($status, 'in_progress'); ?>>
                        <?php esc_html_e('In Progress', 'simple-translator'); ?>
                    </option>
                    <option value="completed" <?php selected($status, 'completed'); ?>>
                        <?php esc_html_e('Completed', 'simple-translator'); ?>
                    </option>
                    <option value="needs_update" <?php selected($status, 'needs_update'); ?>>
                        <?php esc_html_e('Needs Update', 'simple-translator'); ?>
                    </option>
                </select>
            </label>
        </div>
    <?php endif; ?>

    <!-- Warning Notices -->
    <?php if ($forms_need_translation) : ?>
        <div class="st-metabox-notice notice-warning">
            <span class="dashicons dashicons-info"></span>
            <p><?php esc_html_e('This page contains forms that may need translation.', 'simple-translator'); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($acf_needs_review) : ?>
        <div class="st-metabox-notice notice-info">
            <span class="dashicons dashicons-info"></span>
            <p><?php esc_html_e('ACF relationship fields need manual review.', 'simple-translator'); ?></p>
        </div>
    <?php endif; ?>

    <!-- Translation Progress -->
    <?php
    $total_langs = count($enabled_languages) - 1; // Exclude current language
    $translated_langs = 0;
    foreach ($enabled_languages as $lang) {
        if ($lang !== $current_lang && isset($translations[$lang])) {
            $translated_langs++;
        }
    }
    $progress_percent = $total_langs > 0 ? round(($translated_langs / $total_langs) * 100) : 0;
    ?>

    <?php if ($total_langs > 0) : ?>
        <div class="st-translation-progress">
            <div class="st-progress-label">
                <?php
                printf(
                    /* translators: 1: number of translated languages, 2: total languages */
                    esc_html__('Translation Progress: %1$d of %2$d languages', 'simple-translator'),
                    $translated_langs,
                    $total_langs
                );
                ?>
            </div>
            <div class="st-progress-bar-wrap">
                <div class="st-progress-bar" style="width: <?php echo esc_attr($progress_percent); ?>%;">
                    <span class="st-progress-percent"><?php echo esc_html($progress_percent); ?>%</span>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
