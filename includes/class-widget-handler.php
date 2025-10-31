<?php
/**
 * Widget Handler Class
 *
 * Handles widget visibility based on language
 *
 * @package SimpleTranslator
 */

namespace SimpleTranslator;

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Widget Handler - Controls widget visibility by language
 */
class Widget_Handler {

    /**
     * Current language
     *
     * @var string
     */
    private $current_language;

    /**
     * URL Manager instance
     *
     * @var URL_Manager
     */
    private $url_manager;

    /**
     * Initialize the widget handler
     */
    public function init() {
        $this->url_manager = new URL_Manager();
        $this->current_language = $this->url_manager->get_current_language();

        // Add language options to widget form
        add_filter('widget_form_callback', [$this, 'add_language_options'], 10, 2);

        // Save language options
        add_filter('widget_update_callback', [$this, 'save_language_options'], 10, 4);

        // Filter widgets by language
        add_filter('widget_display_callback', [$this, 'filter_widget_by_language'], 10, 3);

        // Add language class to widgets
        add_filter('dynamic_sidebar_params', [$this, 'add_widget_language_class']);
    }

    /**
     * Add language options to widget form
     *
     * @param array     $instance Widget instance
     * @param \WP_Widget $widget   Widget object
     * @return array
     */
    public function add_language_options($instance, $widget) {
        // Get enabled languages
        $enabled_languages = get_option('st_enabled_languages', array('en', 'es'));
        $widget_languages = isset($instance['st_languages']) ? $instance['st_languages'] : array();

        // Add language selection field
        ?>
        <p class="st-widget-languages">
            <label>
                <strong><?php _e('Display for languages:', 'simple-translator'); ?></strong>
            </label>
            <br>
            <?php foreach ($enabled_languages as $lang): ?>
                <label style="display: block; margin: 5px 0;">
                    <input type="checkbox"
                           name="<?php echo esc_attr($widget->get_field_name('st_languages')); ?>[]"
                           value="<?php echo esc_attr($lang); ?>"
                           <?php checked(in_array($lang, $widget_languages, true)); ?>>
                    <?php echo esc_html(strtoupper($lang)); ?>
                    <?php
                    // Get language name from helper
                    $lang_name = st_get_language_name($lang);
                    if ($lang_name !== $lang) {
                        echo ' - ' . esc_html($lang_name);
                    }
                    ?>
                </label>
            <?php endforeach; ?>
            <small style="display: block; margin-top: 5px; color: #666;">
                <?php _e('Leave empty to display for all languages', 'simple-translator'); ?>
            </small>
        </p>
        <?php

        return $instance;
    }

    /**
     * Save language options
     *
     * @param array     $instance     Widget instance
     * @param array     $new_instance New widget instance
     * @param array     $old_instance Old widget instance
     * @param \WP_Widget $widget       Widget object
     * @return array
     */
    public function save_language_options($instance, $new_instance, $old_instance, $widget) {
        // Save language settings
        if (isset($new_instance['st_languages']) && is_array($new_instance['st_languages'])) {
            $instance['st_languages'] = array_map('sanitize_text_field', $new_instance['st_languages']);
        } else {
            $instance['st_languages'] = array();
        }

        return $instance;
    }

    /**
     * Filter widget display based on language
     *
     * @param array     $instance Widget instance
     * @param \WP_Widget $widget   Widget object
     * @param array     $args     Widget arguments
     * @return array|false Widget instance or false to hide
     */
    public function filter_widget_by_language($instance, $widget, $args) {
        // If no language restrictions, show widget
        if (!isset($instance['st_languages']) || empty($instance['st_languages'])) {
            return $instance;
        }

        // Check if current language is in allowed languages
        if (in_array($this->current_language, $instance['st_languages'], true)) {
            return $instance;
        }

        // Hide widget for this language
        return false;
    }

    /**
     * Add language class to widget wrapper
     *
     * @param array $params Widget parameters
     * @return array
     */
    public function add_widget_language_class($params) {
        // Add language class to before_widget
        if (isset($params[0]['before_widget'])) {
            $class = 'st-widget-lang-' . esc_attr($this->current_language);
            $params[0]['before_widget'] = str_replace(
                'class="',
                'class="' . $class . ' ',
                $params[0]['before_widget']
            );

            // If no class attribute exists, add one
            if (strpos($params[0]['before_widget'], 'class=') === false) {
                $params[0]['before_widget'] = str_replace(
                    '>',
                    ' class="' . $class . '">',
                    $params[0]['before_widget']
                );
            }
        }

        return $params;
    }

    /**
     * Get widgets for a specific language in a sidebar
     *
     * @param string $sidebar_id Sidebar ID
     * @param string $language   Language code
     * @return array Widget IDs
     */
    public function get_sidebar_widgets_for_language($sidebar_id, $language) {
        $sidebars_widgets = wp_get_sidebars_widgets();

        if (!isset($sidebars_widgets[$sidebar_id])) {
            return array();
        }

        $widgets = array();
        foreach ($sidebars_widgets[$sidebar_id] as $widget_id) {
            // Parse widget ID (e.g., "text-3" => base: "text", number: 3)
            if (preg_match('/^(.+)-(\d+)$/', $widget_id, $matches)) {
                $base = $matches[1];
                $number = intval($matches[2]);

                // Get widget options
                $widget_options = get_option('widget_' . $base);
                if (isset($widget_options[$number])) {
                    $instance = $widget_options[$number];

                    // Check language settings
                    if (!isset($instance['st_languages']) || empty($instance['st_languages'])) {
                        // No language restriction
                        $widgets[] = $widget_id;
                    } elseif (in_array($language, $instance['st_languages'], true)) {
                        // Widget allowed for this language
                        $widgets[] = $widget_id;
                    }
                }
            }
        }

        return $widgets;
    }

    /**
     * Check if a widget should be displayed for current language
     *
     * @param string $widget_id Widget ID
     * @return bool
     */
    public function should_display_widget($widget_id) {
        // Parse widget ID
        if (preg_match('/^(.+)-(\d+)$/', $widget_id, $matches)) {
            $base = $matches[1];
            $number = intval($matches[2]);

            // Get widget options
            $widget_options = get_option('widget_' . $base);
            if (isset($widget_options[$number])) {
                $instance = $widget_options[$number];

                // Check language settings
                if (!isset($instance['st_languages']) || empty($instance['st_languages'])) {
                    // No language restriction
                    return true;
                }

                // Check if current language is allowed
                return in_array($this->current_language, $instance['st_languages'], true);
            }
        }

        // Default: display widget
        return true;
    }
}
