<?php
/**
 * Search Filter Class
 *
 * Handles search filtering by language
 *
 * @package SimpleTranslator
 */

namespace SimpleTranslator;

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Search Filter - Filter search results by language
 */
class Search_Filter {

    /**
     * Initialize the search filter
     */
    public function init() {
        // Filter search results by language
        add_action('pre_get_posts', array($this, 'filter_search_by_language'));

        // Add language field to search form
        add_filter('get_search_form', array($this, 'add_language_to_search_form'));

        // Filter widget visibility
        add_filter('widget_display_callback', array($this, 'filter_widget_visibility'), 10, 3);
    }

    /**
     * Filter search results by current language
     *
     * @param \WP_Query $query WordPress query object
     */
    public function filter_search_by_language($query) {
        // Only filter search queries on the frontend
        if (!$query->is_search() || is_admin()) {
            return;
        }

        // Get current language
        $url_manager = new URL_Manager();
        $current_lang = $url_manager->get_current_language();

        // Get existing meta query
        $meta_query = $query->get('meta_query');
        if (!is_array($meta_query)) {
            $meta_query = array();
        }

        // Add language filter
        $meta_query[] = array(
            'relation' => 'OR',
            array(
                'key'     => '_language',
                'value'   => $current_lang,
                'compare' => '='
            ),
            array(
                'key'     => '_language',
                'compare' => 'NOT EXISTS'
            )
        );

        $query->set('meta_query', $meta_query);
    }

    /**
     * Add hidden language field to search form
     *
     * @param string $form Search form HTML
     * @return string Modified search form HTML
     */
    public function add_language_to_search_form($form) {
        $url_manager = new URL_Manager();
        $current_lang = $url_manager->get_current_language();
        $default_lang = get_option('st_default_language', 'en');

        // Only add for non-default languages
        if ($current_lang === $default_lang) {
            return $form;
        }

        // Create hidden language input
        $input = sprintf(
            '<input type="hidden" name="lang" value="%s" />',
            esc_attr($current_lang)
        );

        // Insert before closing form tag
        $form = str_replace('</form>', $input . '</form>', $form);

        return $form;
    }

    /**
     * Filter widget visibility by language
     *
     * @param array     $instance Widget instance
     * @param object    $widget   Widget object
     * @param array     $args     Widget arguments
     * @return array|false Widget instance or false to hide
     */
    public function filter_widget_visibility($instance, $widget, $args) {
        // Check if widget has language settings
        if (!isset($instance['st_languages'])) {
            return $instance;
        }

        $url_manager = new URL_Manager();
        $current_lang = $url_manager->get_current_language();

        // Check if widget should be shown in current language
        if (!empty($instance['st_languages']) && !in_array($current_lang, $instance['st_languages'], true)) {
            return false;
        }

        return $instance;
    }

    /**
     * Filter posts widget by language
     *
     * @param array $args Widget query arguments
     * @return array Modified arguments
     */
    public function filter_posts_widget($args) {
        $url_manager = new URL_Manager();
        $current_lang = $url_manager->get_current_language();

        if (!isset($args['meta_query'])) {
            $args['meta_query'] = array();
        }

        $args['meta_query'][] = array(
            'relation' => 'OR',
            array(
                'key'     => '_language',
                'value'   => $current_lang,
                'compare' => '='
            ),
            array(
                'key'     => '_language',
                'compare' => 'NOT EXISTS'
            )
        );

        return $args;
    }

    /**
     * Filter category widget by language
     *
     * @param string $output   Widget output HTML
     * @param array  $args     Widget arguments
     * @param array  $instance Widget instance
     * @return string Modified output
     */
    public function filter_categories_widget($output, $args, $instance) {
        // This would filter categories to show only those
        // that have posts in the current language
        return $output;
    }

    /**
     * Get search results count for language
     *
     * @param string $search_query Search query
     * @param string $lang         Language code
     * @return int Number of results
     */
    public function get_search_results_count($search_query, $lang) {
        $args = array(
            's' => $search_query,
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key'     => '_language',
                    'value'   => $lang,
                    'compare' => '='
                ),
                array(
                    'key'     => '_language',
                    'compare' => 'NOT EXISTS'
                )
            )
        );

        $query = new \WP_Query($args);
        return $query->found_posts;
    }
}
