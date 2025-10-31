# WordPress Multisite Translation Plugin - Complete Build Instructions

## Project Overview

Build a lightweight WordPress multisite translation plugin that uses post cloning instead of runtime translation. This eliminates DOM parsing complexity while providing better performance and easier content management for non-technical users.

Key Principle Each translation is a separate WordPress post linked via metadata. No runtime translation processing.

## Core Requirements

### Performance Goals

- Zero runtime translation overhead (currently 160ms with XLIFF approach)
- Clone creation 2 seconds
- Admin interface response 500ms
- No impact on non-translated sites

### Technical Constraints

- WordPress 5.8+ compatible
- PHP 7.4+ required
- Multisite network compatible
- No external API dependencies
- WordPress coding standards compliance

### User Requirements

- Non-technical users can manage translations
- Familiar WordPress editor for translations
- Visual language switcher
- SEO-compliant URLs and tags
- Works with Gutenberg and Classic Editor

## Architecture Specifications

### Data Storage

Use WordPress post meta (no custom tables)

```php
 Post meta structure for each postpage
_language 'en'  'es'  'fr'  etc.
_translation_group_id 'uuid-string'  Links related translations
_translation_status 'not_started'  'in_progress'  'completed'  'needs_update'
_translation_last_sync timestamp
_source_post_id integer  ID of original post (for tracking)
```

### URL Structure

Implement hybrid approach

- Primary `esabout`, `frcontact` (SEO-friendly)
- Fallback `lang=es` (compatibility)
- Default language has no prefix `about`

### Network Settings (wp_sitemeta)

```php
- simple_translator_languages array of available language codes
- simple_translator_version plugin version
- simple_translator_translation_memory common translations across network
```

### Site Settings (wp_options)

```php
- simple_translator_enabled_languages array of enabled languages for this site
- simple_translator_default_language default language code
- simple_translator_url_structure 'path'  'query'
- simple_translator_post_types array of enabled post types
- simple_translator_auto_clone boolean
- simple_translator_sync_taxonomies boolean
```

## File Structure

```
simple-translator
├── simple-translator.php              # Main plugin file
├── README.md                          # User documentation
├── CHANGELOG.md                       # Version history
├── uninstall.php                      # Cleanup on uninstall
├── LICENSE                            # GPL v2
│
├── includes
│   ├── class-plugin.php              # Main plugin class
│   ├── class-clone-manager.php       # Core cloning logic
│   ├── class-url-manager.php         # URL rewriting
│   ├── class-translation-admin.php   # Admin interface
│   ├── class-seo-manager.php         # SEO features
│   ├── class-language-detector.php   # Language detection
│   ├── class-translation-status.php  # Status tracking
│   ├── class-menu-handler.php        # Menu translation
│   ├── class-widget-handler.php      # Widget visibility
│   ├── class-search-filter.php       # Search filtering
│   ├── class-form-integration.php    # Form plugin compatibility
│   ├── class-logger.php              # Debug logging
│   └── helpers.php                   # Utility functions
│
├── admin
│   ├── class-admin.php               # Admin settings
│   ├── class-network-admin.php       # Network admin
│   ├── views
│   │   ├── settings-page.php         # Site settings
│   │   ├── network-settings.php      # Network settings
│   │   ├── metabox-translations.php  # Translation metabox
│   │   └── translation-status.php    # Status dashboard
│   └── assets
│       ├── css
│       │   ├── admin.css             # Admin styles
│       │   └── metabox.css           # Metabox styles
│       └── js
│           ├── translation-admin.js   # AJAX handlers
│           └── quick-translate.js    # Quick actions
│
├── public
│   ├── class-language-switcher.php   # Language switcher
│   ├── class-frontend.php            # Frontend controller
│   └── assets
│       ├── css
│       │   └── frontend.css          # Frontend styles
│       └── js
│           └── language-switcher.js  # Switcher JS
│
├── languages                        # Plugin translations
│   └── simple-translator.pot         # Translation template
│
└── tests                            # Unit tests
    ├── test-clone-manager.php
    ├── test-url-manager.php
    └── bootstrap.php
```

## Component Specifications

### 1. Main Plugin File (simple-translator.php)

```php
php

  Plugin Name Simple Translator
  Plugin URI rarediseases.org
  Description Lightweight translation plugin using post cloning
  Version 1.0.0
  Network true
  Author Sean Roberts
  Text Domain simple-translator
  Domain Path languages


 Security check
if (!defined('ABSPATH')) {
    exit;
}

 Define constants
define('ST_VERSION', '1.0.0');
define('ST_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ST_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ST_PLUGIN_FILE', __FILE__);

 Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'SimpleTranslator';
    if (strpos($class, $prefix) === 0) {
        $relative = str_replace($prefix, '', $class);
        $file = ST_PLUGIN_DIR . 'includesclass-' .
                strtolower(str_replace('_', '-', $relative)) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    }
});

 Initialize plugin
add_action('plugins_loaded', function() {
    $plugin = new SimpleTranslatorPlugin();
    $plugin-init();
});

 ActivationDeactivation hooks
register_activation_hook(__FILE__, ['SimpleTranslatorPlugin', 'activate']);
register_deactivation_hook(__FILE__, ['SimpleTranslatorPlugin', 'deactivate']);
```

### 2. Clone Manager (class-clone-manager.php)

```php
namespace SimpleTranslator;

class Clone_Manager {


      Create a translation by cloning a post

    public function create_translation($source_id, $target_lang) {
        $source = get_post($source_id);
        if (!$source) {
            return new WP_Error('invalid_source', 'Source post not found');
        }

         Get or create translation group ID
        $group_id = get_post_meta($source_id, '_translation_group_id', true);
        if (!$group_id) {
            $group_id = wp_generate_uuid4();
            update_post_meta($source_id, '_translation_group_id', $group_id);
            update_post_meta($source_id, '_language', $this-get_default_language());
        }

         Check if translation already exists
        if ($this-get_translation($source_id, $target_lang)) {
            return new WP_Error('exists', 'Translation already exists');
        }

         Clone the post
        $new_post = [
            'post_title' = $source-post_title . ' (' . strtoupper($target_lang) . ')',
            'post_content' = $source-post_content,
            'post_excerpt' = $source-post_excerpt,
            'post_status' = 'draft',
            'post_type' = $source-post_type,
            'post_author' = get_current_user_id(),
            'menu_order' = $source-menu_order,
            'post_password' = $source-post_password,
            'post_parent' = 0,  Don't clone parent relationships initially
        ];

         Insert the cloned post
        $new_id = wp_insert_post($new_post);

        if (is_wp_error($new_id)) {
            return $new_id;
        }

         Set translation metadata
        update_post_meta($new_id, '_language', $target_lang);
        update_post_meta($new_id, '_translation_group_id', $group_id);
        update_post_meta($new_id, '_translation_status', 'not_started');
        update_post_meta($new_id, '_source_post_id', $source_id);
        update_post_meta($new_id, '_translation_last_sync', current_time('timestamp'));

         Clone taxonomies
        $this-clone_taxonomies($source_id, $new_id);

         Clone featured image
        $this-clone_featured_image($source_id, $new_id);

         Clone custom fields (excluding private meta)
        $this-clone_custom_fields($source_id, $new_id);

         Clone ACF fields if present
        if (class_exists('ACF')) {
            $this-clone_acf_fields($source_id, $new_id);
        }

         Handle form associations (Gravity Forms, FormAssembly)
        $this-clone_form_associations($source_id, $new_id);

         Fire action for extensibility
        do_action('st_after_create_translation', $new_id, $source_id, $target_lang);

         Clear caches
        $this-clear_translation_cache($source_id);

        return $new_id;
    }


      Get all translations for a post

    public function get_translations($post_id) {
        $group_id = get_post_meta($post_id, '_translation_group_id', true);
        if (!$group_id) {
            return [];
        }

         Try cache first
        $cache_key = 'st_translations_' . $post_id;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

         Query all posts with same group ID
        $args = [
            'post_type' = 'any',
            'post_status' = 'any',
            'posts_per_page' = -1,
            'meta_query' = [
                [
                    'key' = '_translation_group_id',
                    'value' = $group_id,
                    'compare' = '='
                ]
            ],
            'fields' = 'ids'
        ];

        $posts = get_posts($args);
        $translations = [];

        foreach ($posts as $id) {
            $lang = get_post_meta($id, '_language', true);
            if ($lang) {
                $translations[$lang] = $id;
            }
        }

         Cache for 1 hour
        set_transient($cache_key, $translations, HOUR_IN_SECONDS);

        return $translations;
    }


      Clone taxonomies

    private function clone_taxonomies($source_id, $target_id) {
        $taxonomies = get_object_taxonomies(get_post_type($source_id));

        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($source_id, $taxonomy, ['fields' = 'ids']);
            if (!empty($terms)) {
                wp_set_object_terms($target_id, $terms, $taxonomy);
            }
        }
    }


      Clone ACF fields with special handling

    private function clone_acf_fields($source_id, $target_id) {
        $field_groups = acf_get_field_groups(['post_id' = $source_id]);

        foreach ($field_groups as $group) {
            $fields = acf_get_fields($group['key']);

            foreach ($fields as $field) {
                $value = get_field($field['name'], $source_id);

                 Special handling for relationship fields
                if ($field['type'] === 'relationship'  $field['type'] === 'post_object') {
                     Don't clone post relationships initially
                    continue;
                }

                update_field($field['name'], $value, $target_id);
            }
        }
    }


      Handle form plugin integrations

    private function clone_form_associations($source_id, $target_id) {
         Gravity Forms
        if (class_exists('GFAPI')) {
            $content = get_post_field('post_content', $source_id);
            if (has_shortcode($content, 'gravityform')) {
                 Add note in post meta about forms needing review
                update_post_meta($target_id, '_forms_need_translation', true);
            }
        }

         FormAssembly
        $fa_forms = get_post_meta($source_id, '_formassembly_forms', true);
        if ($fa_forms) {
            update_post_meta($target_id, '_formassembly_forms_original', $fa_forms);
            update_post_meta($target_id, '_forms_need_translation', true);
        }
    }
}
```

### 3. URL Manager (class-url-manager.php)

```php
namespace SimpleTranslator;

class URL_Manager {

    private $languages = [];
    private $default_language = 'en';

    public function __construct() {
        $this-languages = get_option('st_enabled_languages', ['en']);
        $this-default_language = get_option('st_default_language', 'en');
    }


      Add rewrite rules for language prefixes

    public function add_rewrite_rules() {
        $languages = array_diff($this-languages, [$this-default_language]);

        if (empty($languages)) {
            return;
        }

        $lang_regex = implode('', array_map('preg_quote', $languages));

         Add language prefix to all WordPress rewrite rules
        add_rewrite_rule(
            '^(' . $lang_regex . ')$',
            'index.phplang=$matches[1]',
            'top'
        );

        add_rewrite_rule(
            '^(' . $lang_regex . ')(.)$',
            'index.phplang=$matches[1]&pagename=$matches[2]',
            'top'
        );

         Register the 'lang' query variable
        add_filter('query_vars', function($vars) {
            $vars[] = 'lang';
            return $vars;
        });
    }


      Get URL for a specific language version

    public function get_translation_url($post_id, $lang) {
        $translations = (new Clone_Manager())-get_translations($post_id);

        if (!isset($translations[$lang])) {
            return false;
        }

        $url = get_permalink($translations[$lang]);

         Add language prefix if not default language
        if ($lang !== $this-default_language) {
            $home_url = home_url('');
            $url = str_replace($home_url, $home_url . $lang . '', $url);
        }

        return $url;
    }


      Detect current language from URL

    public function get_language_from_url() {
         Check query string first (fallback)
        if (isset($_GET['lang']) && in_array($_GET['lang'], $this-languages)) {
            return $_GET['lang'];
        }

         Check URL path
        $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '');
        $parts = explode('', $path);

        if (!empty($parts[0]) && in_array($parts[0], $this-languages)) {
            return $parts[0];
        }

         Return default language
        return $this-default_language;
    }


      Filter post queries to show only current language

    public function filter_queries($query) {
        if (is_admin()  !$query-is_main_query()) {
            return;
        }

        $current_lang = $this-get_language_from_url();

         Add meta query for language
        $meta_query = $query-get('meta_query')  [];
        $meta_query[] = [
            'key' = '_language',
            'value' = $current_lang,
            'compare' = '='
        ];

        $query-set('meta_query', $meta_query);
    }
}
```

### 4. Translation Admin (class-translation-admin.php)

```php
namespace SimpleTranslator;

class Translation_Admin {

    public function init() {
        add_action('add_meta_boxes', [$this, 'add_translation_metabox']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_st_create_translation', [$this, 'ajax_create_translation']);
        add_action('wp_ajax_st_update_status', [$this, 'ajax_update_status']);
        add_filter('manage_posts_columns', [$this, 'add_translation_column']);
        add_action('manage_posts_custom_column', [$this, 'render_translation_column'], 10, 2);
        add_action('admin_notices', [$this, 'show_translation_notices']);
    }


      Add translation metabox to post editor

    public function add_translation_metabox() {
        $post_types = get_option('st_post_types', ['post', 'page']);

        foreach ($post_types as $post_type) {
            add_meta_box(
                'st_translations',
                __('Translations', 'simple-translator'),
                [$this, 'render_metabox'],
                $post_type,
                'side',
                'high'
            );
        }
    }


      Render translation metabox

    public function render_metabox($post) {
        wp_nonce_field('st_translation_nonce', 'st_nonce');

        $current_lang = get_post_meta($post-ID, '_language', true)  'en';
        $status = get_post_meta($post-ID, '_translation_status', true);
        $translations = (new Clone_Manager())-get_translations($post-ID);
        $enabled_languages = get_option('st_enabled_languages', ['en']);

        include ST_PLUGIN_DIR . 'adminviewsmetabox-translations.php';
    }


      AJAX handler for creating translations

    public function ajax_create_translation() {
        check_ajax_referer('st_translation_nonce', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die('Unauthorized');
        }

        $source_id = intval($_POST['source_id']);
        $target_lang = sanitize_text_field($_POST['target_lang']);

        $clone_manager = new Clone_Manager();
        $result = $clone_manager-create_translation($source_id, $target_lang);

        if (is_wp_error($result)) {
            wp_send_json_error($result-get_error_message());
        }

        wp_send_json_success([
            'message' = __('Translation created successfully', 'simple-translator'),
            'edit_url' = get_edit_post_link($result),
            'post_id' = $result
        ]);
    }


      Add translation status column to post list

    public function add_translation_column($columns) {
        $columns['translations'] = __('Translations', 'simple-translator');
        return $columns;
    }


      Render translation status in post list

    public function render_translation_column($column, $post_id) {
        if ($column !== 'translations') {
            return;
        }

        $translations = (new Clone_Manager())-get_translations($post_id);
        $enabled_languages = get_option('st_enabled_languages', ['en']);

        echo 'div class=st-translation-indicators';
        foreach ($enabled_languages as $lang) {
            $class = isset($translations[$lang])  'exists'  'missing';
            $status = isset($translations[$lang])
                      get_post_meta($translations[$lang], '_translation_status', true)
                      'not_started';

            echo sprintf(
                'span class=st-lang-indicator st-%s st-status-%s title=%s%sspan',
                esc_attr($class),
                esc_attr($status),
                esc_attr(ucfirst($lang)),
                esc_html(strtoupper($lang))
            );
        }
        echo 'div';
    }


      Show admin notices for translation management

    public function show_translation_notices() {
        $screen = get_current_screen();
        if ($screen-base !== 'post') {
            return;
        }

        global $post;
        if (!$post) {
            return;
        }

         Check if this is a translation needing attention
        $status = get_post_meta($post-ID, '_translation_status', true);
        $forms_need_translation = get_post_meta($post-ID, '_forms_need_translation', true);

        if ($status === 'not_started') {
            echo 'div class=notice notice-warningp';
            echo __('This is a translation draft. Please translate the content before publishing.', 'simple-translator');
            echo 'pdiv';
        }

        if ($forms_need_translation) {
            echo 'div class=notice notice-infop';
            echo __('This page contains forms that may need translation or localization.', 'simple-translator');
            echo 'pdiv';
        }
    }
}
```

### 5. SEO Manager (class-seo-manager.php)

```php
namespace SimpleTranslator;

class SEO_Manager {

    public function init() {
        add_action('wp_head', [$this, 'add_hreflang_tags']);
        add_action('wp_head', [$this, 'add_canonical_tag']);
        add_filter('language_attributes', [$this, 'filter_language_attributes']);
        add_filter('body_class', [$this, 'add_body_classes']);
        add_filter('wpseo_canonical', [$this, 'filter_yoast_canonical']);
        add_filter('rank_mathfrontendcanonical', [$this, 'filter_rankmath_canonical']);
    }


      Add hreflang tags for all translations

    public function add_hreflang_tags() {
        if (!is_singular()) {
            return;
        }

        global $post;
        $translations = (new Clone_Manager())-get_translations($post-ID);

        if (empty($translations)) {
            return;
        }

        $url_manager = new URL_Manager();

        foreach ($translations as $lang = $post_id) {
            $url = $url_manager-get_translation_url($post-ID, $lang);
            if ($url) {
                echo sprintf(
                    'link rel=alternate hreflang=%s href=%s ' . n,
                    esc_attr($lang),
                    esc_url($url)
                );
            }
        }

         Add x-default for default language
        $default_lang = get_option('st_default_language', 'en');
        if (isset($translations[$default_lang])) {
            $default_url = $url_manager-get_translation_url($post-ID, $default_lang);
            echo sprintf(
                'link rel=alternate hreflang=x-default href=%s ' . n,
                esc_url($default_url)
            );
        }
    }


      Add proper canonical tag

    public function add_canonical_tag() {
        if (!is_singular()) {
            return;
        }

         Let SEO plugins handle this if present
        if (defined('WPSEO_VERSION')  defined('RANK_MATH_VERSION')) {
            return;
        }

        echo sprintf(
            'link rel=canonical href=%s ' . n,
            esc_url(get_permalink())
        );
    }


      Filter language attributes for HTML tag

    public function filter_language_attributes($attributes) {
        $current_lang = (new URL_Manager())-get_language_from_url();
        return sprintf('lang=%s', esc_attr($current_lang));
    }


      Add language body classes

    public function add_body_classes($classes) {
        $current_lang = (new URL_Manager())-get_language_from_url();
        $classes[] = 'language-' . $current_lang;
        $classes[] = 'lang-' . $current_lang;
        return $classes;
    }
}
```

### 6. Menu Handler (class-menu-handler.php)

```php
namespace SimpleTranslator;

class Menu_Handler {

    public function init() {
        add_filter('wp_nav_menu_args', [$this, 'switch_menu_by_language']);
        add_filter('wp_nav_menu_items', [$this, 'add_language_switcher_to_menu'], 10, 2);
    }


      Switch menu based on current language

    public function switch_menu_by_language($args) {
        $current_lang = (new URL_Manager())-get_language_from_url();

        if ($current_lang === get_option('st_default_language', 'en')) {
            return $args;
        }

         Check if language-specific menu location exists
        $location = $args['theme_location']  '';
        if ($location) {
            $lang_location = $location . '_' . $current_lang;
            $locations = get_nav_menu_locations();

            if (isset($locations[$lang_location])) {
                $args['theme_location'] = $lang_location;
            }
        }

        return $args;
    }


      Add language switcher to menu

    public function add_language_switcher_to_menu($items, $args) {
        if (get_option('st_add_switcher_to_menu', false)) {
            $switcher = new Language_Switcher();
            $items .= 'li class=menu-item-language-switcher';
            $items .= $switcher-render(['format' = 'dropdown']);
            $items .= 'li';
        }

        return $items;
    }
}
```

### 7. Search Filter (class-search-filter.php)

```php
namespace SimpleTranslator;

class Search_Filter {

    public function init() {
        add_action('pre_get_posts', [$this, 'filter_search_by_language']);
        add_filter('get_search_form', [$this, 'add_language_to_search_form']);
    }


      Filter search results by current language

    public function filter_search_by_language($query) {
        if (!$query-is_search()  is_admin()) {
            return;
        }

        $current_lang = (new URL_Manager())-get_language_from_url();

        $meta_query = $query-get('meta_query')  [];
        $meta_query[] = [
            'key' = '_language',
            'value' = $current_lang,
            'compare' = '='
        ];

        $query-set('meta_query', $meta_query);
    }


      Add hidden language field to search form

    public function add_language_to_search_form($form) {
        $current_lang = (new URL_Manager())-get_language_from_url();
        $input = sprintf(
            'input type=hidden name=lang value=%s ',
            esc_attr($current_lang)
        );

        $form = str_replace('form', $input . 'form', $form);
        return $form;
    }
}
```

### 8. Admin Views

#### metabox-translations.php

```php
div class=st-translations-metabox
    div class=st-current-language
        strongphp _e('Current Language', 'simple-translator'); strong
        span class=language-tagphp echo strtoupper($current_lang); span

        php if ($status)
            div class=translation-status status-php echo esc_attr($status);
                php echo esc_html(ucfirst(str_replace('_', ' ', $status)));
            div
        php endif;
    div

    div class=st-translations-list
        strongphp _e('Translations', 'simple-translator'); strong

        php foreach ($enabled_languages as $lang)
            php if ($lang === $current_lang) continue;

            div class=translation-item data-lang=php echo esc_attr($lang);
                span class=language-labelphp echo strtoupper($lang); span

                php if (isset($translations[$lang]))
                    php
                    $trans_post = get_post($translations[$lang]);
                    $trans_status = get_post_meta($translations[$lang], '_translation_status', true);

                    span class=translation-exists status-php echo esc_attr($trans_status);
                        php echo esc_html($trans_post-post_title);
                    span
                    div class=translation-actions
                        a href=php echo get_edit_post_link($translations[$lang]);
                           class=button button-small
                            php _e('Edit', 'simple-translator');
                        a
                        a href=php echo get_permalink($translations[$lang]);
                           class=button button-small target=_blank
                            php _e('View', 'simple-translator');
                        a
                    div
                php else
                    span class=translation-missing
                        php _e('Not translated', 'simple-translator');
                    span
                    button class=button button-primary button-small st-create-translation
                            data-source=php echo $post-ID;
                            data-lang=php echo esc_attr($lang);
                        php _e('Create Translation', 'simple-translator');
                    button
                php endif;
            div
        php endforeach;
    div

    php if ($status && $status !== 'completed')
        div class=st-status-update
            label
                php _e('Update Status', 'simple-translator');
                select class=st-status-select data-post=php echo $post-ID;
                    option value=not_started php selected($status, 'not_started');
                        php _e('Not Started', 'simple-translator');
                    option
                    option value=in_progress php selected($status, 'in_progress');
                        php _e('In Progress', 'simple-translator');
                    option
                    option value=completed php selected($status, 'completed');
                        php _e('Completed', 'simple-translator');
                    option
                    option value=needs_update php selected($status, 'needs_update');
                        php _e('Needs Update', 'simple-translator');
                    option
                select
            label
        div
    php endif;
div
```

### 9. Frontend JavaScript (translation-admin.js)

```javascript
jQuery(document).ready(function($) {
     Create translation handler
    $('.st-create-translation').on('click', function(e) {
        e.preventDefault();

        var button = $(this);
        var sourceId = button.data('source');
        var targetLang = button.data('lang');

        button.prop('disabled', true).text('Creating...');

        $.post(ajaxurl, {
            action 'st_create_translation',
            source_id sourceId,
            target_lang targetLang,
            nonce $('#st_nonce').val()
        })
        .done(function(response) {
            if (response.success) {
                alert(response.data.message);
                window.location.href = response.data.edit_url;
            } else {
                alert('Error ' + response.data);
                button.prop('disabled', false).text('Create Translation');
            }
        })
        .fail(function() {
            alert('Network error. Please try again.');
            button.prop('disabled', false).text('Create Translation');
        });
    });

     Status update handler
    $('.st-status-select').on('change', function() {
        var select = $(this);
        var postId = select.data('post');
        var status = select.val();

        $.post(ajaxurl, {
            action 'st_update_status',
            post_id postId,
            status status,
            nonce $('#st_nonce').val()
        })
        .done(function(response) {
            if (response.success) {
                select.addClass('saved');
                setTimeout(function() {
                    select.removeClass('saved');
                }, 2000);
            }
        });
    });

     Add visual feedback for translation completeness
    function updateTranslationProgress() {
        var total = $('.translation-item').length;
        var translated = $('.translation-exists').length;
        var percentage = Math.round((translated  total)  100);

        $('.st-translations-metabox').attr('data-progress', percentage);
    }

    updateTranslationProgress();
});
```

### 10. CSS Styling (admin.css)

```css
 Translation Metabox Styles
.st-translations-metabox {
    padding 10px 0;
}

.st-current-language {
    margin-bottom 15px;
    padding-bottom 15px;
    border-bottom 1px solid #ddd;
}

.language-tag {
    display inline-block;
    background #0073aa;
    color white;
    padding 2px 8px;
    border-radius 3px;
    font-size 12px;
    margin-left 5px;
}

.translation-status {
    margin-top 10px;
    padding 5px 10px;
    border-radius 3px;
    font-size 12px;
}

.translation-status.status-not_started {
    background #f0f0f1;
    color #666;
}

.translation-status.status-in_progress {
    background #fff3cd;
    color #856404;
}

.translation-status.status-completed {
    background #d4edda;
    color #155724;
}

.translation-status.status-needs_update {
    background #f8d7da;
    color #721c24;
}

.translation-item {
    margin 10px 0;
    padding 10px;
    background #f9f9f9;
    border-radius 3px;
}

.translation-exists {
    color #155724;
    font-weight 500;
}

.translation-missing {
    color #999;
    font-style italic;
}

.translation-actions {
    margin-top 5px;
}

.translation-actions .button {
    margin-right 5px;
}

 Translation indicators in post list
.st-translation-indicators {
    display flex;
    gap 4px;
}

.st-lang-indicator {
    display inline-block;
    padding 2px 6px;
    border-radius 3px;
    font-size 11px;
    font-weight bold;
}

.st-lang-indicator.st-exists {
    background #d4edda;
    color #155724;
}

.st-lang-indicator.st-missing {
    background #f0f0f1;
    color #999;
}

.st-lang-indicator.st-status-needs_update {
    background #f8d7da;
    color #721c24;
}

 Save feedback
.st-status-select.saved {
    border-color #46b450;
    box-shadow 0 0 0 1px #46b450;
}
```

## Testing Requirements

### Unit Tests

Create PHPUnit tests for

- Clone creation and linking
- URL detection and generation
- Translation group management
- Meta data handling
- Cache operations

### Integration Tests

Test with

- WordPress 5.8, 5.9, 6.0+
- PHP 7.4, 8.0, 8.1
- Multisite network with 3+ sites
- Gutenberg editor
- Classic editor
- Popular page builders (Elementor, Divi)
- SEO plugins (Yoast, RankMath)
- Form plugins (Gravity Forms, Contact Form 7)
- ACF Pro

### Manual Testing Checklist

1. Clone Creation

   - [ ] Create translation from post
   - [ ] Create translation from page
   - [ ] Create translation from custom post type
   - [ ] Verify draft status
   - [ ] Verify taxonomies copied
   - [ ] Verify featured image copied
   - [ ] Verify custom fields copied

2. URL Handling

   - [ ] Language detection from es path
   - [ ] Language detection from lang=es
   - [ ] Correct permalinks generated
   - [ ] Language switcher links work
   - [ ] Homepage language switching

3. Admin Interface

   - [ ] Metabox displays correctly
   - [ ] Translation status updates
   - [ ] Admin column shows languages
   - [ ] Network admin settings save
   - [ ] Site-specific settings save

4. SEO

   - [ ] hreflang tags present
   - [ ] Canonical URLs correct
   - [ ] HTML lang attribute updates
   - [ ] Body classes added
   - [ ] Sitemap filtering works

5. Search & Navigation

   - [ ] Search filtered by language
   - [ ] Menu switching works
   - [ ] Widget visibility correct
   - [ ] Archive pages filtered

6. Performance
   - [ ] Page load time unchanged
   - [ ] Clone creation 2 seconds
   - [ ] No memory leaks
   - [ ] Cache working properly

## Performance Benchmarks

Target metrics

- Page render No additional overhead (currently 160ms with XLIFF)
- Clone creation 2 seconds for typical page
- Memory usage 10MB additional
- Database queries 5 additional queries per page

## Security Checklist

- [ ] All user input sanitized
- [ ] Nonces verified on all forms
- [ ] Capabilities checked before actions
- [ ] SQL queries properly prepared
- [ ] XSS prevention in place
- [ ] CSRF protection implemented
- [ ] File uploads validated (if any)
- [ ] No direct file access
- [ ] Error messages don't leak info

## Documentation Requirements

### User Documentation

1. Installation guide
2. Quick start tutorial
3. Language setup guide
4. Translation workflow
5. Troubleshooting guide
6. FAQ section

### Developer Documentation

1. Filteraction hooks reference
2. Function reference
3. Database schema
4. REST API endpoints (if added)
5. Extension examples
6. Migration guide from WPMLPolylang

## Deployment Steps

### Phase 1 MVP (Week 1)

1. Build core components
2. Implement basic admin interface
3. Add URL handling
4. Create language switcher
5. Test on staging

### Phase 2 Polish (Week 2)

1. Add network admin
2. Implement SEO features
3. Add status tracking
4. Improve UX
5. Fix bugs from testing

### Phase 3 Launch

1. Final testing
2. Documentation completion
3. Create demo site
4. Package for distribution
5. Deploy to production

## Migration from XLIFF Plugin

### Data Migration Script

```php
function migrate_from_xliff() {
     1. Get all published posts
    $posts = get_posts(['post_type' = 'any', 'numberposts' = -1]);

    foreach ($posts as $post) {
         2. Set as default language
        update_post_meta($post-ID, '_language', 'en');

         3. Create translation group
        $group_id = wp_generate_uuid4();
        update_post_meta($post-ID, '_translation_group_id', $group_id);

         4. Check for XLIFF translations
         ... parse XLIFF files if they exist

         5. Create placeholder translations
        foreach (['es', 'fr'] as $lang) {
             Create draft clones for manual translation
        }
    }
}
```

## Success Metrics

### Performance

- ✅ Zero runtime translation overhead
- ✅ Page loads 160ms faster than XLIFF approach
- ✅ Clone creation under 2 seconds

### Usability

- ✅ Non-technical users can manage translations
- ✅ Familiar WordPress editing experience
- ✅ Visual language switching

### Code Quality

- ✅ Under 6,000 lines total (vs 15,000 for XLIFF enhancement)
- ✅ WordPress coding standards compliance
- ✅ 60% code reuse from existing plugin

### Business Value

- ✅ Works with rare disease nonprofit workflows
- ✅ Handles medicalscientific content
- ✅ Form integration compatibility
- ✅ Multisite scalability

## Notes for Claude Code

### Priority Order

1. Start with Clone Manager - this is the core
2. Then URL Manager for routing
3. Admin interface for user interaction
4. SEO Manager for compliance
5. Additional handlers as needed

### Key Design Principles

- Each translation is a separate post
- Use post meta, not custom tables
- Zero runtime translation processing
- Draft status prevents publishing untranslated content
- Manual translation ensures quality

### Watch Out For

- Flush rewrite rules on activationdeactivation
- Clear translation cache when updating
- Handle post deletion to avoid orphans
- Test with your specific form plugins
- Consider your existing content structure

### Reusable Components from XLIFF Plugin

Copy and adapt these files

- class-language-detector.php (update for path detection)
- class-language-switcher.php (update URL generation)
- class-logger.php (use as-is)
- admin structure and AJAX patterns
- Frontend CSS for switcher

This plugin will give you the performance and simplicity you need while maintaining the flexibility for your nonprofit's specific requirements.
