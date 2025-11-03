<?php
/**
 * Clone Manager Class - FIXED VERSION
 *
 * Handles post cloning and translation management
 * This version properly handles WordPress slug constraints
 *
 * @package SimpleTranslator
 */

namespace SimpleTranslator;

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Clone Manager - Core translation cloning functionality
 */
class Clone_Manager {

    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize logger
        $this->logger = new Logger();
        $this->logger->init();
    }

    /**
     * Create a translation by cloning a post
     * FIXED: Properly handles slugs with language suffixes
     *
     * @param int    $source_id   Source post ID
     * @param string $target_lang Target language code
     * @return int|WP_Error New post ID on success, WP_Error on failure
     */
    public function create_translation($source_id, $target_lang) {
        // Validate source post
        $source = get_post($source_id);
        if (!$source) {
            return new \WP_Error('invalid_source', __('Source post not found', 'simple-translator'));
        }

        // Validate language code
        $enabled_languages = get_option('st_enabled_languages', array('en', 'es'));
        if (!in_array($target_lang, $enabled_languages, true)) {
            return new \WP_Error('invalid_language', __('Invalid target language', 'simple-translator'));
        }

        // Get or create translation group ID
        $group_id = get_post_meta($source_id, '_translation_group_id', true);
        if (!$group_id) {
            $group_id = wp_generate_uuid4();
            update_post_meta($source_id, '_translation_group_id', $group_id);

            // Set source post language if not set
            $source_lang = get_post_meta($source_id, '_language', true);
            if (!$source_lang) {
                $default_lang = get_option('st_default_language', 'en');
                update_post_meta($source_id, '_language', $default_lang);
                update_post_meta($source_id, '_translation_status', 'completed');
            }
        }

        // Check if translation already exists
        $existing = $this->get_translation($source_id, $target_lang);
        if ($existing) {
            return new \WP_Error(
                'translation_exists',
                __('Translation already exists for this language', 'simple-translator')
            );
        }

        // FIXED: Generate proper slug with language suffix
        $base_slug = $this->get_base_slug($source->post_name);
        $new_slug = $this->generate_translation_slug($base_slug, $target_lang, $source->post_type);

        // Debug logging for slug generation
        $this->logger->debug('Creating translation - slug generation', array(
            'source_id' => $source_id,
            'source_slug' => $source->post_name,
            'base_slug' => $base_slug,
            'generated_slug' => $new_slug,
            'target_lang' => $target_lang,
            'post_type' => $source->post_type
        ));

        // Clone the post
        $new_post = array(
            'post_title'    => $source->post_title, // Keep original title for user to translate
            'post_name'     => $new_slug,           // Use properly suffixed slug
            'post_content'  => $source->post_content,
            'post_excerpt'  => $source->post_excerpt,
            'post_status'   => 'draft', // Always start as draft
            'post_type'     => $source->post_type,
            'post_author'   => get_current_user_id(),
            'menu_order'    => $source->menu_order,
            'post_password' => $source->post_password,
            'post_parent'   => $this->get_translated_parent($source->post_parent, $target_lang),
            'comment_status' => $source->comment_status,
            'ping_status'   => $source->ping_status,
        );

        // Insert the cloned post
        $new_id = wp_insert_post($new_post, true);

        if (is_wp_error($new_id)) {
            return $new_id;
        }

        // Set translation metadata
        update_post_meta($new_id, '_language', $target_lang);
        update_post_meta($new_id, '_translation_group_id', $group_id);
        update_post_meta($new_id, '_translation_status', 'not_started');
        update_post_meta($new_id, '_source_post_id', $source_id);
        update_post_meta($new_id, '_translation_last_sync', current_time('mysql'));

        // Debug: Verify metadata was saved correctly
        $saved_lang = get_post_meta($new_id, '_language', true);
        $this->logger->debug('Translation metadata saved', array(
            'new_post_id' => $new_id,
            'target_lang' => $target_lang,
            'saved_lang_meta' => $saved_lang,
            'meta_saved_successfully' => ($saved_lang === $target_lang),
            'group_id' => $group_id,
            'source_id' => $source_id
        ));

        // Clone taxonomies if enabled
        if (get_option('st_sync_taxonomies', true)) {
            $this->clone_taxonomies($source_id, $new_id);
        }

        // Clone featured image
        $this->clone_featured_image($source_id, $new_id);

        // Clone custom fields
        $this->clone_custom_fields($source_id, $new_id);

        // Clone ACF fields if available
        if (function_exists('acf_get_field_groups')) {
            $this->clone_acf_fields($source_id, $new_id);
        }

        // Clear translation cache
        $this->clear_translation_cache($source_id);
        $this->clear_translation_cache($new_id);

        // Log the creation
        do_action('st_translation_created', $new_id, $source_id, $target_lang);

        return $new_id;
    }

    /**
     * Get base slug without language suffixes
     *
     * @param string $slug Current slug
     * @return string Base slug
     */
    private function get_base_slug($slug) {
        // Remove any existing language suffixes (2-3 letter codes)
        $base = preg_replace('/-[a-z]{2,3}$/', '', $slug);
        
        // Also remove numeric suffixes that WordPress might have added
        $base = preg_replace('/-\d+$/', '', $base);
        
        return $base;
    }

    /**
     * Generate a unique translation slug with language suffix
     *
     * @param string $base_slug Base slug without suffixes
     * @param string $lang Language code
     * @param string $post_type Post type
     * @return string Unique slug with language suffix
     */
    private function generate_translation_slug($base_slug, $lang, $post_type) {
        // For default language, use base slug
        $default_lang = get_option('st_default_language', 'en');

        $this->logger->debug('Generating translation slug - input', array(
            'base_slug' => $base_slug,
            'lang' => $lang,
            'post_type' => $post_type,
            'default_lang' => $default_lang,
            'is_default_lang' => ($lang === $default_lang)
        ));

        if ($lang === $default_lang) {
            $result = wp_unique_post_slug($base_slug, 0, 'draft', $post_type, 0);
            $this->logger->debug('Generated slug for default language', array(
                'input_slug' => $base_slug,
                'unique_slug' => $result
            ));
            return $result;
        }

        // For other languages, create slug with proper number placement
        // Pattern: base-NUMBER-lang (e.g., "english-2-es") NOT "english-es-2"

        // First, try the basic pattern (no number)
        $slug = $base_slug . '-' . $lang;

        $this->logger->debug('Checking if basic slug exists', array(
            'base_slug' => $base_slug,
            'lang' => $lang,
            'checking_slug' => $slug
        ));

        // Check if this slug is already taken
        $existing = get_page_by_path($slug, OBJECT, $post_type);

        if (!$existing) {
            // Slug is available, use it
            $this->logger->debug('Basic slug available, using it', array(
                'slug' => $slug
            ));
            return $slug;
        }

        // Slug is taken, find next available number
        // Try: base-2-lang, base-3-lang, base-4-lang, etc.
        $number = 2;
        $max_attempts = 100; // Safety limit

        while ($number <= $max_attempts) {
            $numbered_slug = $base_slug . '-' . $number . '-' . $lang;

            $this->logger->debug('Trying numbered slug', array(
                'attempt' => $number,
                'trying_slug' => $numbered_slug
            ));

            $existing = get_page_by_path($numbered_slug, OBJECT, $post_type);

            if (!$existing) {
                // Found available slug
                $this->logger->debug('Found available numbered slug', array(
                    'slug' => $numbered_slug,
                    'number' => $number
                ));
                return $numbered_slug;
            }

            $number++;
        }

        // Fallback: if we somehow exceeded max attempts, let WordPress handle it
        $this->logger->warning('Could not find available slug after max attempts, falling back to wp_unique_post_slug', array(
            'base_slug' => $base_slug,
            'lang' => $lang,
            'max_attempts' => $max_attempts
        ));

        return wp_unique_post_slug($base_slug . '-' . $lang, 0, 'draft', $post_type, 0);
    }

    /**
     * Get translated parent ID if exists
     *
     * @param int    $parent_id Parent post ID
     * @param string $lang Target language
     * @return int Translated parent ID or 0
     */
    private function get_translated_parent($parent_id, $lang) {
        if (!$parent_id) {
            return 0;
        }
        
        $parent_translation = $this->get_translation($parent_id, $lang);
        return $parent_translation ? $parent_translation : 0;
    }

    /**
     * Get translation for a specific language
     *
     * @param int    $post_id Post ID
     * @param string $lang    Language code
     * @return int|false Translation post ID or false
     */
    public function get_translation($post_id, $lang) {
        $translations = $this->get_translations($post_id);
        return isset($translations[$lang]) ? $translations[$lang] : false;
    }

    /**
     * Get all translations for a post
     *
     * @param int $post_id Post ID
     * @return array Array of language => post_id pairs
     */
    public function get_translations($post_id) {
        // Check cache first
        $cache_key = 'st_translations_' . $post_id;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        // Get translation group ID
        $group_id = get_post_meta($post_id, '_translation_group_id', true);
        if (!$group_id) {
            return array();
        }

        global $wpdb;

        // Get all posts in the same translation group
        $posts = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, pm_lang.meta_value as language
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_group ON p.ID = pm_group.post_id
            INNER JOIN {$wpdb->postmeta} pm_lang ON p.ID = pm_lang.post_id
            WHERE pm_group.meta_key = '_translation_group_id'
            AND pm_group.meta_value = %s
            AND pm_lang.meta_key = '_language'
            AND p.post_status != 'trash'",
            $group_id
        ));

        $translations = array();
        foreach ($posts as $post) {
            $translations[$post->language] = (int) $post->ID;
        }

        // Cache for 1 hour
        set_transient($cache_key, $translations, HOUR_IN_SECONDS);

        return $translations;
    }

    /**
     * Clear translation cache
     *
     * @param int $post_id Post ID
     */
    public function clear_translation_cache($post_id) {
        $cache_key = 'st_translations_' . $post_id;
        delete_transient($cache_key);

        // Also clear cache for all translations in the same group
        $group_id = get_post_meta($post_id, '_translation_group_id', true);
        if ($group_id) {
            $translations = $this->get_translations($post_id);
            foreach ($translations as $lang => $trans_id) {
                if ($trans_id !== $post_id) {
                    delete_transient('st_translations_' . $trans_id);
                }
            }
        }
    }

    /**
     * Clone taxonomies from source to target post
     *
     * @param int $source_id Source post ID
     * @param int $target_id Target post ID
     */
    private function clone_taxonomies($source_id, $target_id) {
        // Get all taxonomies for the post type
        $post_type = get_post_type($source_id);
        $taxonomies = get_object_taxonomies($post_type);

        foreach ($taxonomies as $taxonomy) {
            // Get terms from source post
            $terms = wp_get_object_terms($source_id, $taxonomy, array('fields' => 'ids'));

            if (!empty($terms) && !is_wp_error($terms)) {
                wp_set_object_terms($target_id, $terms, $taxonomy);
            }
        }
    }

    /**
     * Clone featured image from source to target post
     *
     * @param int $source_id Source post ID
     * @param int $target_id Target post ID
     */
    private function clone_featured_image($source_id, $target_id) {
        $thumbnail_id = get_post_thumbnail_id($source_id);
        if ($thumbnail_id) {
            set_post_thumbnail($target_id, $thumbnail_id);
        }
    }

    /**
     * Clone custom fields from source to target post
     *
     * @param int $source_id Source post ID
     * @param int $target_id Target post ID
     */
    private function clone_custom_fields($source_id, $target_id) {
        // Get all custom fields
        $custom_fields = get_post_custom($source_id);

        if (!$custom_fields) {
            return;
        }

        // Fields to skip
        $skip_fields = array(
            '_language',
            '_translation_group_id',
            '_translation_status',
            '_source_post_id',
            '_translation_last_sync',
            '_edit_lock',
            '_edit_last',
            '_wp_page_template',
        );

        foreach ($custom_fields as $key => $values) {
            // Skip private WordPress meta and translation meta
            if (in_array($key, $skip_fields, true)) {
                continue;
            }

            // Handle underscore-prefixed meta keys
            if (strpos($key, '_') === 0) {
                // Check if this is an ACF field reference key
                // ACF reference keys look like: _field_name, _field_name_0_subfield
                // They're critical for ACF to recognize field data
                $is_acf_reference = false;

                if (function_exists('acf_get_field_groups')) {
                    // Check common ACF reference key patterns
                    // Keys that start with underscore but contain non-underscore version in custom_fields
                    $non_underscore_key = substr($key, 1);
                    $is_acf_reference = isset($custom_fields[$non_underscore_key]);
                }

                // Skip non-ACF underscore keys (WordPress internal meta)
                if (!$is_acf_reference) {
                    continue;
                }

                // Allow ACF reference keys to be copied
            }

            // Clone the meta value
            foreach ($values as $value) {
                add_post_meta($target_id, $key, maybe_unserialize($value));
            }
        }
    }

    /**
     * Clone ACF fields with special handling
     *
     * @param int $source_id Source post ID
     * @param int $target_id Target post ID
     */
    private function clone_acf_fields($source_id, $target_id) {
        // Get all field groups for this post
        $field_groups = acf_get_field_groups(array('post_id' => $source_id));

        if (!$field_groups) {
            return;
        }

        foreach ($field_groups as $group) {
            $fields = acf_get_fields($group['key']);

            if (!$fields) {
                continue;
            }

            foreach ($fields as $field) {
                $this->clone_acf_field($field, $source_id, $target_id);
            }
        }
    }

    /**
     * Clone individual ACF field
     *
     * @param array $field      ACF field array
     * @param int   $source_id  Source post ID
     * @param int   $target_id  Target post ID
     */
    private function clone_acf_field($field, $source_id, $target_id) {
        // IMPORTANT: Use field KEY instead of NAME for flexible content
        // This ensures proper field reference keys are maintained
        $field_key = $field['key'];
        $field_name = $field['name'];
        
        // Get the raw value using the field key
        $value = get_field($field_key, $source_id, false);
        
        // Log field cloning attempt
        error_log(sprintf(
            'ST ACF Clone - Field: %s (Key: %s), Type: %s, Source: %d, Target: %d',
            $field_name,
            $field_key,
            $field['type'],
            $source_id,
            $target_id
        ));

        // Handle different field types
        switch ($field['type']) {
            case 'relationship':
            case 'post_object':
                // Don't clone post relationships initially
                update_post_meta($target_id, '_acf_relationships_need_review', true);
                error_log('ST ACF Clone - Skipped relationship field, marked for review');
                break;

            case 'flexible_content':
                // Clone flexible content layouts
                if (is_array($value) && !empty($value)) {
                    // Use the field KEY, not name, for flexible content
                    $result = update_field($field_key, $value, $target_id);
                    
                    // Also ensure the field reference key is properly set
                    update_post_meta($target_id, '_' . $field_name, $field_key);
                    
                    if (!$result) {
                        error_log(sprintf(
                            'ST ACF Clone - FAILED to clone flexible_content field: %s (target: %d)',
                            $field_name,
                            $target_id
                        ));
                    } else {
                        error_log(sprintf(
                            'ST ACF Clone - Successfully cloned flexible_content field: %s with %d layouts',
                            $field_name,
                            count($value)
                        ));
                    }
                }
                break;

            case 'repeater':
            case 'group':
                // For complex fields, use field key
                if (is_array($value)) {
                    $result = update_field($field_key, $value, $target_id);
                    
                    // Ensure reference key is set
                    update_post_meta($target_id, '_' . $field_name, $field_key);
                    
                    if (!$result) {
                        error_log(sprintf(
                            'ST ACF Clone - FAILED to clone %s field: %s (target: %d)',
                            $field['type'],
                            $field_name,
                            $target_id
                        ));
                    } else {
                        error_log(sprintf(
                            'ST ACF Clone - Successfully cloned %s field: %s',
                            $field['type'],
                            $field_name
                        ));
                    }
                }
                break;

            default:
                // Clone all other field types
                if (null !== $value) {
                    $result = update_field($field_key, $value, $target_id);
                    
                    if (!$result) {
                        error_log(sprintf(
                            'ST ACF Clone - FAILED to clone %s field: %s (target: %d)',
                            $field['type'],
                            $field_name,
                            $target_id
                        ));
                    } else {
                        error_log(sprintf(
                            'ST ACF Clone - Successfully cloned %s field: %s',
                            $field['type'],
                            $field_name
                        ));
                    }
                }
                break;
        }
    }

    /**
     * Handle form plugin integrations
     *
     * @param int $source_id Source post ID
     * @param int $target_id Target post ID
     */
    private function clone_form_associations($source_id, $target_id) {
        $forms_need_translation = false;

        // Get post content
        $content = get_post_field('post_content', $source_id);

        // Check for Gravity Forms
        if (class_exists('GFAPI') && has_shortcode($content, 'gravityform')) {
            $forms_need_translation = true;
        }

        // Check for Contact Form 7
        if (class_exists('WPCF7') && has_shortcode($content, 'contact-form-7')) {
            $forms_need_translation = true;
        }

        // Check for FormAssembly
        $fa_forms = get_post_meta($source_id, '_formassembly_forms', true);
        if ($fa_forms) {
            update_post_meta($target_id, '_formassembly_forms_original', $fa_forms);
            $forms_need_translation = true;
        }

        // Set flag if forms detected
        if ($forms_need_translation) {
            update_post_meta($target_id, '_forms_need_translation', true);
        }
    }

    /**
     * Sync translation from source
     *
     * @param int $target_id Target translation post ID
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function sync_from_source($target_id) {
        // Get source post ID
        $source_id = get_post_meta($target_id, '_source_post_id', true);
        if (!$source_id) {
            return new \WP_Error('no_source', __('No source post found', 'simple-translator'));
        }

        $source = get_post($source_id);
        $target = get_post($target_id);

        if (!$source || !$target) {
            return new \WP_Error('invalid_posts', __('Invalid source or target post', 'simple-translator'));
        }

        // Update post fields (but keep title and content - those are translated)
        $update = array(
            'ID' => $target_id,
            'menu_order' => $source->menu_order,
            'comment_status' => $source->comment_status,
            'ping_status' => $source->ping_status,
        );

        wp_update_post($update);

        // Re-sync taxonomies
        $this->clone_taxonomies($source_id, $target_id);

        // Re-sync featured image
        $this->clone_featured_image($source_id, $target_id);

        // Update sync timestamp
        update_post_meta($target_id, '_translation_last_sync', current_time('timestamp'));

        // Clear cache
        $this->clear_translation_cache($target_id);

        return true;
    }

    /**
     * Update translation status
     *
     * @param int    $post_id Post ID
     * @param string $status  Status (not_started, in_progress, completed, needs_update)
     * @return bool
     */
    public function update_translation_status($post_id, $status) {
        $valid_statuses = array('not_started', 'in_progress', 'completed', 'needs_update');

        if (!in_array($status, $valid_statuses, true)) {
            return false;
        }

        update_post_meta($post_id, '_translation_status', $status);

        // Clear cache
        $this->clear_translation_cache($post_id);

        return true;
    }

    /**
     * Delete a translation and its relationships
     *
     * @param int $post_id Post ID to delete
     * @return bool
     */
    public function delete_translation($post_id) {
        // Clear cache first
        $this->clear_translation_cache($post_id);

        // Delete the post
        $result = wp_delete_post($post_id, true);

        return (bool) $result;
    }

    /**
     * Get translation statistics
     *
     * @return array
     */
    public function get_translation_stats() {
        global $wpdb;

        $stats = array(
            'total_groups' => 0,
            'by_status' => array(),
            'by_language' => array(),
        );

        // Count translation groups
        $groups = $wpdb->get_var(
            "SELECT COUNT(DISTINCT meta_value)
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_translation_group_id'"
        );
        $stats['total_groups'] = (int) $groups;

        // Count by status
        $statuses = $wpdb->get_results(
            "SELECT meta_value as status, COUNT(*) as count
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_translation_status'
            GROUP BY meta_value"
        );

        foreach ($statuses as $row) {
            $stats['by_status'][$row->status] = (int) $row->count;
        }

        // Count by language
        $languages = $wpdb->get_results(
            "SELECT meta_value as language, COUNT(*) as count
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_language'
            GROUP BY meta_value"
        );

        foreach ($languages as $row) {
            $stats['by_language'][$row->language] = (int) $row->count;
        }

        return $stats;
    }

    /**
     * Fix slugs for existing translations that have language suffixes
     *
     * This removes language suffixes like -es, -fr, -de from post_name (slug)
     * that were created by older versions of the plugin
     *
     * @return array Results with count of fixed posts
     */
    public function fix_translation_slugs() {
        global $wpdb;

        // Get all posts that have a language meta key
        $translation_posts = $wpdb->get_results(
            "SELECT p.ID, p.post_name, pm.meta_value as language
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE pm.meta_key = '_language'
            AND p.post_status != 'trash'"
        );

        if (empty($translation_posts)) {
            return array(
                'success' => true,
                'message' => __('No translations found to fix', 'simple-translator'),
                'fixed_count' => 0,
            );
        }

        $fixed_count = 0;
        $errors = array();

        // Pattern to match language suffixes: -es, -fr, -de, etc.
        // Matches 2-3 letter language codes at the end of the slug
        $pattern = '/-([a-z]{2,3})$/i';

        foreach ($translation_posts as $post) {
            $original_slug = $post->post_name;
            $lang = $post->meta_value;

            // Check if the slug ends with the language code
            if (preg_match($pattern, $original_slug, $matches)) {
                $suffix = strtolower($matches[1]);

                // Only remove if the suffix matches the post's language
                if ($suffix === strtolower($lang)) {
                    $new_slug = preg_replace($pattern, '', $original_slug);

                    // Update the post slug
                    $result = $wpdb->update(
                        $wpdb->posts,
                        array('post_name' => $new_slug),
                        array('ID' => $post->ID),
                        array('%s'),
                        array('%d')
                    );

                    if ($result !== false) {
                        $fixed_count++;

                        // Clear post cache
                        clean_post_cache($post->ID);
                    } else {
                        $errors[] = sprintf(
                            __('Failed to update post ID %d', 'simple-translator'),
                            $post->ID
                        );
                    }
                }
            }
        }

        // Clear rewrite rules cache
        delete_option('rewrite_rules');

        return array(
            'success' => true,
            'message' => sprintf(
                __('Fixed %d translation slug(s)', 'simple-translator'),
                $fixed_count
            ),
            'fixed_count' => $fixed_count,
            'total_checked' => count($translation_posts),
            'errors' => $errors,
        );
    }
}
