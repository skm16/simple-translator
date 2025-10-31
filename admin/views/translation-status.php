<?php
/**
 * Translation Status Dashboard View
 *
 * Provides an overview of translation status across all posts
 *
 * @package SimpleTranslator
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Get enabled languages
$enabled_languages = get_option('st_enabled_languages', array('en', 'es'));
$default_language = get_option('st_default_language', 'en');
$post_types = get_option('st_post_types', array('post', 'page'));

// Get translation statistics
$stats = array();
$total_posts = 0;

foreach ($post_types as $post_type) {
    // Get all posts of this type with language set
    $query = new WP_Query(array(
        'post_type' => $post_type,
        'post_status' => 'any',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => '_language',
                'compare' => 'EXISTS'
            )
        ),
        'fields' => 'ids'
    ));

    $posts = $query->posts;
    $total_posts += count($posts);

    // Calculate statistics per language
    foreach ($enabled_languages as $lang) {
        if (!isset($stats[$lang])) {
            $stats[$lang] = array(
                'total' => 0,
                'not_started' => 0,
                'in_progress' => 0,
                'completed' => 0,
                'needs_update' => 0,
            );
        }

        // Count posts by status
        foreach ($posts as $post_id) {
            $post_lang = get_post_meta($post_id, '_language', true);
            if ($post_lang === $lang) {
                $stats[$lang]['total']++;
                $status = get_post_meta($post_id, '_translation_status', true) ?: 'completed';
                if (isset($stats[$lang][$status])) {
                    $stats[$lang][$status]++;
                }
            }
        }
    }
}

// Get recent translations
$recent_translations = new WP_Query(array(
    'post_type' => $post_types,
    'post_status' => 'any',
    'posts_per_page' => 10,
    'meta_query' => array(
        array(
            'key' => '_translation_last_sync',
            'compare' => 'EXISTS'
        )
    ),
    'meta_key' => '_translation_last_sync',
    'orderby' => 'meta_value_num',
    'order' => 'DESC'
));

// Get posts needing attention
$needs_attention = new WP_Query(array(
    'post_type' => $post_types,
    'post_status' => array('draft', 'pending'),
    'posts_per_page' => 20,
    'meta_query' => array(
        array(
            'key' => '_translation_status',
            'value' => array('not_started', 'in_progress', 'needs_update'),
            'compare' => 'IN'
        )
    )
));
?>

<div class="wrap st-translation-status-dashboard">
    <h1><?php _e('Translation Status Dashboard', 'simple-translator'); ?></h1>

    <!-- Language Statistics -->
    <div class="st-stats-grid">
        <?php foreach ($enabled_languages as $lang): ?>
            <?php
            $lang_stats = isset($stats[$lang]) ? $stats[$lang] : array(
                'total' => 0,
                'not_started' => 0,
                'in_progress' => 0,
                'completed' => 0,
                'needs_update' => 0,
            );
            $lang_name = st_get_language_name($lang);
            $completion_rate = $lang_stats['total'] > 0
                ? round(($lang_stats['completed'] / $lang_stats['total']) * 100)
                : 0;
            ?>
            <div class="st-stat-card">
                <div class="st-stat-header">
                    <h3><?php echo esc_html($lang_name); ?> (<?php echo esc_html(strtoupper($lang)); ?>)</h3>
                    <?php if ($lang === $default_language): ?>
                        <span class="st-badge st-badge-primary"><?php _e('Default', 'simple-translator'); ?></span>
                    <?php endif; ?>
                </div>

                <div class="st-stat-number">
                    <?php echo number_format($lang_stats['total']); ?>
                    <span class="st-stat-label"><?php _e('Total Posts', 'simple-translator'); ?></span>
                </div>

                <div class="st-progress-bar">
                    <div class="st-progress-fill" style="width: <?php echo esc_attr($completion_rate); ?>%"></div>
                </div>
                <p class="st-progress-text"><?php echo esc_html($completion_rate); ?>% <?php _e('Complete', 'simple-translator'); ?></p>

                <div class="st-stat-breakdown">
                    <div class="st-stat-item">
                        <span class="st-status-badge st-status-completed"></span>
                        <span><?php echo number_format($lang_stats['completed']); ?> <?php _e('Completed', 'simple-translator'); ?></span>
                    </div>
                    <div class="st-stat-item">
                        <span class="st-status-badge st-status-in_progress"></span>
                        <span><?php echo number_format($lang_stats['in_progress']); ?> <?php _e('In Progress', 'simple-translator'); ?></span>
                    </div>
                    <div class="st-stat-item">
                        <span class="st-status-badge st-status-needs_update"></span>
                        <span><?php echo number_format($lang_stats['needs_update']); ?> <?php _e('Needs Update', 'simple-translator'); ?></span>
                    </div>
                    <div class="st-stat-item">
                        <span class="st-status-badge st-status-not_started"></span>
                        <span><?php echo number_format($lang_stats['not_started']); ?> <?php _e('Not Started', 'simple-translator'); ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Two Column Layout -->
    <div class="st-dashboard-columns">
        <!-- Recent Translations -->
        <div class="st-dashboard-column">
            <div class="st-dashboard-box">
                <h2><?php _e('Recently Updated Translations', 'simple-translator'); ?></h2>

                <?php if ($recent_translations->have_posts()): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Title', 'simple-translator'); ?></th>
                                <th><?php _e('Language', 'simple-translator'); ?></th>
                                <th><?php _e('Status', 'simple-translator'); ?></th>
                                <th><?php _e('Last Updated', 'simple-translator'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($recent_translations->have_posts()): $recent_translations->the_post(); ?>
                                <?php
                                $post_id = get_the_ID();
                                $lang = get_post_meta($post_id, '_language', true);
                                $status = get_post_meta($post_id, '_translation_status', true) ?: 'completed';
                                $last_sync = get_post_meta($post_id, '_translation_last_sync', true);
                                ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <a href="<?php echo get_edit_post_link($post_id); ?>">
                                                <?php echo esc_html(get_the_title()); ?>
                                            </a>
                                        </strong>
                                        <div class="row-actions">
                                            <span class="edit">
                                                <a href="<?php echo get_edit_post_link($post_id); ?>"><?php _e('Edit', 'simple-translator'); ?></a>
                                            </span>
                                            |
                                            <span class="view">
                                                <a href="<?php echo get_permalink($post_id); ?>" target="_blank"><?php _e('View', 'simple-translator'); ?></a>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="st-lang-tag"><?php echo esc_html(strtoupper($lang)); ?></span>
                                    </td>
                                    <td>
                                        <span class="st-status-badge st-status-<?php echo esc_attr($status); ?>">
                                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $status))); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        if ($last_sync) {
                                            echo human_time_diff($last_sync, current_time('timestamp')) . ' ' . __('ago', 'simple-translator');
                                        } else {
                                            echo '—';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endwhile; wp_reset_postdata(); ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="st-empty-message"><?php _e('No translations found.', 'simple-translator'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Posts Needing Attention -->
        <div class="st-dashboard-column">
            <div class="st-dashboard-box">
                <h2><?php _e('Needs Attention', 'simple-translator'); ?></h2>

                <?php if ($needs_attention->have_posts()): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Title', 'simple-translator'); ?></th>
                                <th><?php _e('Language', 'simple-translator'); ?></th>
                                <th><?php _e('Status', 'simple-translator'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($needs_attention->have_posts()): $needs_attention->the_post(); ?>
                                <?php
                                $post_id = get_the_ID();
                                $lang = get_post_meta($post_id, '_language', true);
                                $status = get_post_meta($post_id, '_translation_status', true) ?: 'not_started';
                                ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <a href="<?php echo get_edit_post_link($post_id); ?>">
                                                <?php echo esc_html(get_the_title()); ?>
                                            </a>
                                        </strong>
                                    </td>
                                    <td>
                                        <span class="st-lang-tag"><?php echo esc_html(strtoupper($lang)); ?></span>
                                    </td>
                                    <td>
                                        <span class="st-status-badge st-status-<?php echo esc_attr($status); ?>">
                                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $status))); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; wp_reset_postdata(); ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="st-empty-message"><?php _e('No posts need attention. Great job!', 'simple-translator'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Stats Summary -->
    <div class="st-quick-stats">
        <div class="st-quick-stat">
            <div class="st-quick-stat-number"><?php echo number_format($total_posts); ?></div>
            <div class="st-quick-stat-label"><?php _e('Total Posts', 'simple-translator'); ?></div>
        </div>
        <div class="st-quick-stat">
            <div class="st-quick-stat-number"><?php echo count($enabled_languages); ?></div>
            <div class="st-quick-stat-label"><?php _e('Active Languages', 'simple-translator'); ?></div>
        </div>
        <div class="st-quick-stat">
            <div class="st-quick-stat-number"><?php echo $needs_attention->found_posts; ?></div>
            <div class="st-quick-stat-label"><?php _e('Needs Attention', 'simple-translator'); ?></div>
        </div>
        <div class="st-quick-stat">
            <div class="st-quick-stat-number"><?php echo count($post_types); ?></div>
            <div class="st-quick-stat-label"><?php _e('Post Types', 'simple-translator'); ?></div>
        </div>
    </div>
</div>

<style>
/* Translation Status Dashboard Styles */
.st-translation-status-dashboard {
    max-width: 1400px;
}

.st-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin: 30px 0;
}

.st-stat-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.st-stat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.st-stat-header h3 {
    margin: 0;
    font-size: 18px;
}

.st-badge {
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.st-badge-primary {
    background: #0073aa;
    color: #fff;
}

.st-stat-number {
    font-size: 36px;
    font-weight: 700;
    color: #0073aa;
    margin: 10px 0;
}

.st-stat-label {
    display: block;
    font-size: 14px;
    color: #666;
    font-weight: 400;
}

.st-progress-bar {
    background: #f0f0f1;
    height: 8px;
    border-radius: 4px;
    overflow: hidden;
    margin: 15px 0 5px;
}

.st-progress-fill {
    background: linear-gradient(90deg, #00a32a 0%, #4ab866 100%);
    height: 100%;
    transition: width 0.3s ease;
}

.st-progress-text {
    text-align: right;
    font-size: 12px;
    color: #666;
    margin: 0 0 15px;
}

.st-stat-breakdown {
    border-top: 1px solid #f0f0f1;
    padding-top: 15px;
}

.st-stat-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 0;
    font-size: 13px;
}

.st-status-badge {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

.st-status-badge.st-status-completed {
    background: #00a32a;
}

.st-status-badge.st-status-in_progress {
    background: #dba617;
}

.st-status-badge.st-status-needs_update {
    background: #d63638;
}

.st-status-badge.st-status-not_started {
    background: #999;
}

.st-dashboard-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin: 30px 0;
}

.st-dashboard-box {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.st-dashboard-box h2 {
    margin-top: 0;
    font-size: 16px;
    border-bottom: 1px solid #f0f0f1;
    padding-bottom: 10px;
}

.st-lang-tag {
    display: inline-block;
    background: #0073aa;
    color: #fff;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.st-empty-message {
    text-align: center;
    color: #666;
    padding: 40px 20px;
}

.st-quick-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin: 30px 0;
}

.st-quick-stat {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
}

.st-quick-stat-number {
    font-size: 32px;
    font-weight: 700;
    color: #0073aa;
}

.st-quick-stat-label {
    font-size: 13px;
    color: #666;
    margin-top: 5px;
}

@media (max-width: 1200px) {
    .st-dashboard-columns {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .st-quick-stats {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
