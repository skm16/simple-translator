<?php
/**
 * Menu Handler Class
 *
 * Handles menu translation and language switching
 *
 * @package SimpleTranslator
 */

namespace SimpleTranslator;

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Menu Handler - Menu translation functionality
 */
class Menu_Handler {

    /**
     * Initialize the menu handler
     */
    public function init() {
        // Switch menus by language
        add_filter('wp_nav_menu_args', array($this, 'switch_menu_by_language'));

        // Add language switcher to menu
        add_filter('wp_nav_menu_items', array($this, 'add_language_switcher_to_menu'), 10, 2);

        // Add menu locations for each language
        add_action('init', array($this, 'register_language_menu_locations'));
    }

    /**
     * Register menu locations for each language
     */
    public function register_language_menu_locations() {
        $languages = get_option('st_enabled_languages', array('en', 'es'));
        $default_lang = get_option('st_default_language', 'en');

        // Get all registered menu locations
        $locations = get_registered_nav_menus();

        if (empty($locations)) {
            return;
        }

        // Register language-specific locations for non-default languages
        foreach ($languages as $lang) {
            if ($lang === $default_lang) {
                continue;
            }

            foreach ($locations as $location => $description) {
                $lang_location = $location . '_' . $lang;
                $lang_description = $description . ' (' . strtoupper($lang) . ')';

                register_nav_menu($lang_location, $lang_description);
            }
        }
    }

    /**
     * Switch menu based on current language
     *
     * @param array $args Menu arguments
     * @return array Modified menu arguments
     */
    public function switch_menu_by_language($args) {
        // Get current language
        $url_manager = new URL_Manager();
        $current_lang = $url_manager->get_current_language();
        $default_lang = get_option('st_default_language', 'en');

        // If we're on the default language, use default menu
        if ($current_lang === $default_lang) {
            return $args;
        }

        // Check if menu location is set
        $location = isset($args['theme_location']) ? $args['theme_location'] : '';
        if (!$location) {
            return $args;
        }

        // Build language-specific location name
        $lang_location = $location . '_' . $current_lang;

        // Check if a language-specific menu is assigned
        $locations = get_nav_menu_locations();
        if (isset($locations[$lang_location]) && !empty($locations[$lang_location])) {
            $args['theme_location'] = $lang_location;
        }

        return $args;
    }

    /**
     * Add language switcher to menu
     *
     * @param string $items Menu items HTML
     * @param object $args  Menu arguments
     * @return string Modified menu items
     */
    public function add_language_switcher_to_menu($items, $args) {
        // Check if option is enabled
        if (!get_option('st_add_switcher_to_menu', false)) {
            return $items;
        }

        // Only add to specific menu locations if set
        $menu_locations = get_option('st_switcher_menu_locations', array());
        if (!empty($menu_locations)) {
            $theme_location = isset($args->theme_location) ? $args->theme_location : '';
            if (!in_array($theme_location, $menu_locations, true)) {
                return $items;
            }
        }

        // Get language switcher HTML
        if (class_exists('SimpleTranslator\\Language_Switcher')) {
            $switcher = new Language_Switcher();
            $switcher_html = $switcher->render(array(
                'format' => 'menu',
                'show_flags' => get_option('st_switcher_show_flags', true),
                'show_names' => get_option('st_switcher_show_names', true),
            ));

            // Wrap in menu item
            $items .= '<li class="menu-item menu-item-language-switcher">' . $switcher_html . '</li>';
        }

        return $items;
    }

    /**
     * Clone a menu for translation
     *
     * @param int    $menu_id     Source menu ID
     * @param string $target_lang Target language code
     * @return int|WP_Error New menu ID or WP_Error on failure
     */
    public function clone_menu($menu_id, $target_lang) {
        // Get source menu
        $source_menu = wp_get_nav_menu_object($menu_id);
        if (!$source_menu) {
            return new \WP_Error('invalid_menu', __('Source menu not found', 'simple-translator'));
        }

        // Create new menu with language suffix
        $new_menu_name = $source_menu->name . ' (' . strtoupper($target_lang) . ')';
        $new_menu = wp_create_nav_menu($new_menu_name);

        if (is_wp_error($new_menu)) {
            return $new_menu;
        }

        // Get all menu items from source
        $menu_items = wp_get_nav_menu_items($menu_id);

        if (!$menu_items) {
            return $new_menu;
        }

        // Map old item IDs to new item IDs (for parent relationships)
        $item_map = array();

        // Clone each menu item
        foreach ($menu_items as $item) {
            $new_item_data = array(
                'menu-item-db-id' => 0,
                'menu-item-object-id' => $item->object_id,
                'menu-item-object' => $item->object,
                'menu-item-parent-id' => 0, // Will update after all items are created
                'menu-item-position' => $item->menu_order,
                'menu-item-type' => $item->type,
                'menu-item-title' => $item->title,
                'menu-item-url' => $item->url,
                'menu-item-description' => $item->description,
                'menu-item-attr-title' => $item->attr_title,
                'menu-item-target' => $item->target,
                'menu-item-classes' => implode(' ', $item->classes),
                'menu-item-xfn' => $item->xfn,
                'menu-item-status' => 'publish',
            );

            // If this is a post/page menu item, try to link to translation
            if (in_array($item->type, array('post_type'), true)) {
                $clone_manager = new Clone_Manager();
                $translation_id = $clone_manager->get_translation($item->object_id, $target_lang);

                if ($translation_id) {
                    $new_item_data['menu-item-object-id'] = $translation_id;
                }
            }

            $new_item_id = wp_update_nav_menu_item($new_menu, 0, $new_item_data);

            // Store mapping
            $item_map[$item->ID] = $new_item_id;

            // Copy custom meta
            $meta_keys = array('_menu_item_type', '_menu_item_menu_item_parent', '_menu_item_object_id',
                              '_menu_item_object', '_menu_item_target', '_menu_item_classes',
                              '_menu_item_xfn', '_menu_item_url');

            foreach ($meta_keys as $meta_key) {
                $meta_value = get_post_meta($item->ID, $meta_key, true);
                if ($meta_value) {
                    update_post_meta($new_item_id, $meta_key, $meta_value);
                }
            }
        }

        // Update parent relationships
        foreach ($menu_items as $item) {
            if ($item->menu_item_parent && isset($item_map[$item->menu_item_parent])) {
                $new_parent_id = $item_map[$item->menu_item_parent];
                $new_item_id = $item_map[$item->ID];

                update_post_meta($new_item_id, '_menu_item_menu_item_parent', $new_parent_id);
            }
        }

        return $new_menu;
    }

    /**
     * Get menus by language
     *
     * @param string $lang Language code
     * @return array Array of menu objects
     */
    public function get_menus_by_language($lang) {
        $menus = wp_get_nav_menus();
        $lang_menus = array();

        $lang_suffix = ' (' . strtoupper($lang) . ')';

        foreach ($menus as $menu) {
            if (strpos($menu->name, $lang_suffix) !== false) {
                $lang_menus[] = $menu;
            }
        }

        return $lang_menus;
    }

    /**
     * Translate menu item if linked post has translation
     *
     * @param object $menu_item Menu item object
     * @param string $lang      Target language
     * @return object Modified menu item
     */
    public function translate_menu_item($menu_item, $lang) {
        // Only translate post type menu items
        if ($menu_item->type !== 'post_type') {
            return $menu_item;
        }

        // Get translation for this post
        $clone_manager = new Clone_Manager();
        $translation_id = $clone_manager->get_translation($menu_item->object_id, $lang);

        if ($translation_id) {
            $menu_item->object_id = $translation_id;
            $menu_item->url = get_permalink($translation_id);

            // Update title if desired
            if (get_option('st_translate_menu_titles', false)) {
                $translated_post = get_post($translation_id);
                if ($translated_post) {
                    $menu_item->title = $translated_post->post_title;
                }
            }
        }

        return $menu_item;
    }

    /**
     * Filter menu items by language
     *
     * @param array  $items Menu items
     * @param object $menu  Menu object
     * @param array  $args  Menu arguments
     * @return array Filtered menu items
     */
    public function filter_menu_items_by_language($items, $menu, $args) {
        $url_manager = new URL_Manager();
        $current_lang = $url_manager->get_current_language();

        foreach ($items as $key => $item) {
            // Translate the item if possible
            $items[$key] = $this->translate_menu_item($item, $current_lang);
        }

        return $items;
    }
}
