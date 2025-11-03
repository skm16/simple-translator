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
     *
     * Note: Menu locations are registered in Plugin::init_components()
     * This method only sets up frontend filters
     */
    public function init() {
        // Switch menus by language
        add_filter('wp_nav_menu_args', array($this, 'switch_menu_by_language'));

        // Add language switcher to menu
        add_filter('wp_nav_menu_items', array($this, 'add_language_switcher_to_menu'), 10, 2);
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
     * @return array|WP_Error Array with 'menu_id' and 'locations' keys, or WP_Error on failure
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
            // Still try to assign to location even if no items
            $assigned_locations = $this->assign_menu_to_language_locations($menu_id, $new_menu, $target_lang);
            return array(
                'menu_id' => $new_menu,
                'locations' => $assigned_locations,
            );
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

                // Clear the URL so WordPress generates it fresh from the object_id
                // This prevents double language prefixes (e.g., /es/es/page/)
                $new_item_data['menu-item-url'] = '';
            }

            $new_item_id = wp_update_nav_menu_item($new_menu, 0, $new_item_data);

            // Store mapping
            $item_map[$item->ID] = $new_item_id;

            // Copy custom meta (skip URL for post_type items to avoid double prefixes)
            $meta_keys = array('_menu_item_type', '_menu_item_menu_item_parent', '_menu_item_object_id',
                              '_menu_item_object', '_menu_item_target', '_menu_item_classes',
                              '_menu_item_xfn', '_menu_item_url');

            foreach ($meta_keys as $meta_key) {
                // Skip URL meta for post_type items - let WordPress generate it
                if ($meta_key === '_menu_item_url' && $item->type === 'post_type') {
                    continue;
                }

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

        // Assign cloned menu to language-specific locations
        $assigned_locations = $this->assign_menu_to_language_locations($menu_id, $new_menu, $target_lang);

        return array(
            'menu_id' => $new_menu,
            'locations' => $assigned_locations,
        );
    }

    /**
     * Assign a cloned menu to language-specific locations
     *
     * @param int    $source_menu_id Source menu ID
     * @param int    $new_menu_id    New cloned menu ID
     * @param string $target_lang    Target language code
     * @return array Array of locations where menu was assigned
     */
    private function assign_menu_to_language_locations($source_menu_id, $new_menu_id, $target_lang) {
        // Get all current menu location assignments
        $locations = get_nav_menu_locations();
        $assigned_locations = array();

        // Debug logging
        error_log('ST Menu Assignment - Source menu ID: ' . $source_menu_id);
        error_log('ST Menu Assignment - New menu ID: ' . $new_menu_id);
        error_log('ST Menu Assignment - Target language: ' . $target_lang);
        error_log('ST Menu Assignment - Current locations: ' . print_r($locations, true));

        if (!$locations) {
            $locations = array();
        }

        // Get registered locations for debugging
        $registered_locations = get_registered_nav_menus();
        error_log('ST Menu Assignment - Registered locations: ' . print_r($registered_locations, true));

        // Find which locations the source menu is assigned to
        foreach ($locations as $location => $menu_id) {
            error_log("ST Menu Assignment - Checking location '$location' with menu ID '$menu_id' against source ID '$source_menu_id'");

            if ($menu_id == $source_menu_id) {
                // Create language-specific location name
                $lang_location = $location . '_' . $target_lang;
                error_log("ST Menu Assignment - Found match! Creating language location: $lang_location");

                // Check if this language-specific location exists
                if (isset($registered_locations[$lang_location])) {
                    // Assign the new menu to this language-specific location
                    $locations[$lang_location] = $new_menu_id;
                    $assigned_locations[] = $lang_location;
                    error_log("ST Menu Assignment - Assigned menu $new_menu_id to location $lang_location");
                } else {
                    error_log("ST Menu Assignment - WARNING: Language location $lang_location is not registered!");
                }
            }
        }

        // Save the updated location assignments
        if (!empty($assigned_locations)) {
            set_theme_mod('nav_menu_locations', $locations);
            error_log('ST Menu Assignment - Saved ' . count($assigned_locations) . ' location assignments');
        } else {
            error_log('ST Menu Assignment - No locations were assigned (source menu not found in any location)');
        }

        return $assigned_locations;
    }

    /**
     * Reassign menu locations for already-cloned menus
     *
     * Useful for fixing menus that were cloned before auto-assignment was added
     *
     * @param int    $menu_id     Menu ID to reassign
     * @param string $target_lang Language code for this menu
     * @return array|WP_Error Array of assigned locations or WP_Error on failure
     */
    public function reassign_menu_locations($menu_id, $target_lang) {
        // Get the menu
        $menu = wp_get_nav_menu_object($menu_id);
        if (!$menu) {
            return new \WP_Error('invalid_menu', __('Menu not found', 'simple-translator'));
        }

        // Try to find the source menu by removing language suffix from name
        $lang_suffix = ' (' . strtoupper($target_lang) . ')';
        $source_name = str_replace($lang_suffix, '', $menu->name);

        // Find source menu by name
        $all_menus = wp_get_nav_menus();
        $source_menu_id = null;

        foreach ($all_menus as $m) {
            if ($m->name === $source_name) {
                $source_menu_id = $m->term_id;
                break;
            }
        }

        if (!$source_menu_id) {
            return new \WP_Error('source_not_found', __('Could not find source menu', 'simple-translator'));
        }

        // Assign to language-specific locations
        $assigned = $this->assign_menu_to_language_locations($source_menu_id, $menu_id, $target_lang);

        if (empty($assigned)) {
            return new \WP_Error('no_assignment', __('Source menu is not assigned to any locations', 'simple-translator'));
        }

        return $assigned;
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
