<?php
/**
 * Language Switcher Class
 *
 * Handles language switcher display
 *
 * @package SimpleTranslator
 */

namespace SimpleTranslator;

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Language Switcher - Language selection interface
 */
class Language_Switcher {

    /**
     * Render language switcher
     *
     * @param array $args Switcher arguments
     * @return string Switcher HTML
     */
    public function render($args = array()) {
        $defaults = array(
            'format' => 'dropdown', // dropdown, list, flags
            'show_flags' => true,
            'show_names' => true,
            'show_current' => true,
            'class' => '',
        );

        $args = wp_parse_args($args, $defaults);

        // Get language data
        $url_manager = new URL_Manager();
        $languages = get_option('st_enabled_languages', array('en', 'es'));
        $current_lang = $url_manager->get_current_language();
        $switcher_urls = $url_manager->get_switcher_urls();

        // Start output buffering
        ob_start();

        // Render based on format
        switch ($args['format']) {
            case 'dropdown':
                $this->render_dropdown($languages, $current_lang, $switcher_urls, $args);
                break;

            case 'list':
                $this->render_list($languages, $current_lang, $switcher_urls, $args);
                break;

            case 'flags':
                $this->render_flags($languages, $current_lang, $switcher_urls, $args);
                break;

            case 'menu':
                $this->render_menu($languages, $current_lang, $switcher_urls, $args);
                break;

            default:
                $this->render_dropdown($languages, $current_lang, $switcher_urls, $args);
                break;
        }

        return ob_get_clean();
    }

    /**
     * Render dropdown switcher
     *
     * @param array  $languages     Available languages
     * @param string $current_lang  Current language
     * @param array  $switcher_urls Language URLs
     * @param array  $args          Arguments
     */
    private function render_dropdown($languages, $current_lang, $switcher_urls, $args) {
        $class = 'st-language-switcher st-dropdown ' . esc_attr($args['class']);
        ?>
        <div class="<?php echo $class; ?>">
            <select class="st-language-select" onchange="window.location.href=this.value">
                <?php foreach ($languages as $lang) : ?>
                    <option value="<?php echo esc_url($switcher_urls[$lang]); ?>"
                            <?php selected($lang, $current_lang); ?>>
                        <?php
                        if ($args['show_flags']) {
                            echo st_get_language_flag($lang) . ' ';
                        }
                        if ($args['show_names']) {
                            echo esc_html(st_get_language_native_name($lang));
                        } else {
                            echo esc_html(strtoupper($lang));
                        }
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
    }

    /**
     * Render list switcher
     *
     * @param array  $languages     Available languages
     * @param string $current_lang  Current language
     * @param array  $switcher_urls Language URLs
     * @param array  $args          Arguments
     */
    private function render_list($languages, $current_lang, $switcher_urls, $args) {
        $class = 'st-language-switcher st-list ' . esc_attr($args['class']);
        ?>
        <ul class="<?php echo $class; ?>">
            <?php foreach ($languages as $lang) : ?>
                <?php
                $is_current = ($lang === $current_lang);
                $item_class = $is_current ? 'st-lang-item st-current' : 'st-lang-item';
                ?>
                <li class="<?php echo esc_attr($item_class); ?>">
                    <a href="<?php echo esc_url($switcher_urls[$lang]); ?>"
                       class="st-lang-link"
                       data-lang="<?php echo esc_attr($lang); ?>"
                       <?php if ($is_current) echo 'aria-current="true"'; ?>>
                        <?php
                        if ($args['show_flags']) {
                            echo '<span class="st-flag">' . st_get_language_flag($lang) . '</span>';
                        }
                        if ($args['show_names']) {
                            echo '<span class="st-lang-name">' . esc_html(st_get_language_native_name($lang)) . '</span>';
                        } else {
                            echo '<span class="st-lang-code">' . esc_html(strtoupper($lang)) . '</span>';
                        }
                        ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    /**
     * Render flags switcher
     *
     * @param array  $languages     Available languages
     * @param string $current_lang  Current language
     * @param array  $switcher_urls Language URLs
     * @param array  $args          Arguments
     */
    private function render_flags($languages, $current_lang, $switcher_urls, $args) {
        $class = 'st-language-switcher st-flags ' . esc_attr($args['class']);
        ?>
        <div class="<?php echo $class; ?>">
            <?php foreach ($languages as $lang) : ?>
                <?php
                $is_current = ($lang === $current_lang);
                $item_class = $is_current ? 'st-flag-link st-current' : 'st-flag-link';
                ?>
                <a href="<?php echo esc_url($switcher_urls[$lang]); ?>"
                   class="<?php echo esc_attr($item_class); ?>"
                   data-lang="<?php echo esc_attr($lang); ?>"
                   title="<?php echo esc_attr(st_get_language_native_name($lang)); ?>"
                   <?php if ($is_current) echo 'aria-current="true"'; ?>>
                    <span class="st-flag"><?php echo st_get_language_flag($lang); ?></span>
                    <?php if ($args['show_names']) : ?>
                        <span class="st-flag-label"><?php echo esc_html(strtoupper($lang)); ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Render menu-style switcher (for adding to nav menus)
     *
     * @param array  $languages     Available languages
     * @param string $current_lang  Current language
     * @param array  $switcher_urls Language URLs
     * @param array  $args          Arguments
     */
    private function render_menu($languages, $current_lang, $switcher_urls, $args) {
        ?>
        <ul class="st-menu-language-switcher">
            <?php foreach ($languages as $lang) : ?>
                <?php
                $is_current = ($lang === $current_lang);
                $item_class = $is_current ? 'menu-item st-current' : 'menu-item';
                ?>
                <li class="<?php echo esc_attr($item_class); ?>">
                    <a href="<?php echo esc_url($switcher_urls[$lang]); ?>">
                        <?php
                        if ($args['show_flags']) {
                            echo st_get_language_flag($lang) . ' ';
                        }
                        if ($args['show_names']) {
                            echo esc_html(st_get_language_native_name($lang));
                        } else {
                            echo esc_html(strtoupper($lang));
                        }
                        ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }
}

/**
 * Language Switcher Widget
 */
class Language_Switcher_Widget extends \WP_Widget {

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct(
            'st_language_switcher',
            __('Language Switcher', 'simple-translator'),
            array(
                'description' => __('Allows visitors to switch between language versions', 'simple-translator'),
                'classname' => 'widget_st_language_switcher',
            )
        );
    }

    /**
     * Front-end display of widget
     *
     * @param array $args     Widget arguments
     * @param array $instance Widget instance
     */
    public function widget($args, $instance) {
        $title = !empty($instance['title']) ? $instance['title'] : '';
        $title = apply_filters('widget_title', $title, $instance, $this->id_base);

        $format = !empty($instance['format']) ? $instance['format'] : 'dropdown';
        $show_flags = isset($instance['show_flags']) ? (bool) $instance['show_flags'] : true;
        $show_names = isset($instance['show_names']) ? (bool) $instance['show_names'] : true;

        echo $args['before_widget'];

        if ($title) {
            echo $args['before_title'] . esc_html($title) . $args['after_title'];
        }

        $switcher = new Language_Switcher();
        echo $switcher->render(array(
            'format' => $format,
            'show_flags' => $show_flags,
            'show_names' => $show_names,
        ));

        echo $args['after_widget'];
    }

    /**
     * Back-end widget form
     *
     * @param array $instance Widget instance
     */
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('Languages', 'simple-translator');
        $format = !empty($instance['format']) ? $instance['format'] : 'dropdown';
        $show_flags = isset($instance['show_flags']) ? (bool) $instance['show_flags'] : true;
        $show_names = isset($instance['show_names']) ? (bool) $instance['show_names'] : true;
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">
                <?php esc_html_e('Title:', 'simple-translator'); ?>
            </label>
            <input class="widefat"
                   id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>"
                   type="text"
                   value="<?php echo esc_attr($title); ?>">
        </p>

        <p>
            <label for="<?php echo esc_attr($this->get_field_id('format')); ?>">
                <?php esc_html_e('Display Format:', 'simple-translator'); ?>
            </label>
            <select class="widefat"
                    id="<?php echo esc_attr($this->get_field_id('format')); ?>"
                    name="<?php echo esc_attr($this->get_field_name('format')); ?>">
                <option value="dropdown" <?php selected($format, 'dropdown'); ?>>
                    <?php esc_html_e('Dropdown', 'simple-translator'); ?>
                </option>
                <option value="list" <?php selected($format, 'list'); ?>>
                    <?php esc_html_e('List', 'simple-translator'); ?>
                </option>
                <option value="flags" <?php selected($format, 'flags'); ?>>
                    <?php esc_html_e('Flags Only', 'simple-translator'); ?>
                </option>
            </select>
        </p>

        <p>
            <input class="checkbox"
                   type="checkbox"
                   <?php checked($show_flags); ?>
                   id="<?php echo esc_attr($this->get_field_id('show_flags')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('show_flags')); ?>">
            <label for="<?php echo esc_attr($this->get_field_id('show_flags')); ?>">
                <?php esc_html_e('Show Flags', 'simple-translator'); ?>
            </label>
        </p>

        <p>
            <input class="checkbox"
                   type="checkbox"
                   <?php checked($show_names); ?>
                   id="<?php echo esc_attr($this->get_field_id('show_names')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('show_names')); ?>">
            <label for="<?php echo esc_attr($this->get_field_id('show_names')); ?>">
                <?php esc_html_e('Show Language Names', 'simple-translator'); ?>
            </label>
        </p>
        <?php
    }

    /**
     * Sanitize widget form values as they are saved
     *
     * @param array $new_instance New instance values
     * @param array $old_instance Old instance values
     * @return array Updated instance values
     */
    public function update($new_instance, $old_instance) {
        $instance = array();

        $instance['title'] = !empty($new_instance['title'])
            ? sanitize_text_field($new_instance['title'])
            : '';

        $instance['format'] = !empty($new_instance['format'])
            ? sanitize_text_field($new_instance['format'])
            : 'dropdown';

        $instance['show_flags'] = !empty($new_instance['show_flags']) ? 1 : 0;
        $instance['show_names'] = !empty($new_instance['show_names']) ? 1 : 0;

        return $instance;
    }
}
