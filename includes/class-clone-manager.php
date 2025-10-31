<?php
/**
 * Clone Manager Class
 *
 * Handles post cloning and translation management
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
     * Create a translation by cloning a post
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

        // Clone the post
        $new_post = array(
            'post_title'    => $source->post_title, // Keep original title (user can translate)
            'post_name'     => $source->post_name,  // Explicitly copy slug to prevent auto-generation
            'post_content'  => $source->post_content,
            'post_excerpt'  => $source->post_excerpt,
            'post_status'   => 'draft', // Always start as draft
            'post_type'     => $source->post_type,
            'post_author'   => get_current_user_id(),
            'menu_order'    => $source->menu_order,
            'post_password' => $source->post_password,
            'post_parent'   => 0, // Don't clone parent relationships initially
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
        update_post_meta($new_id, '_translation_last_sync', current_time('timestamp'));

        // Clone taxonomies
        $this->clone_taxonomies($source_id, $new_id);

        // Clone featured image
        $this->clone_featured_image($source_id, $new_id);

        // Clone custom fields (excluding private meta)
        $this->clone_custom_fields($source_id, $new_id);

        // Clone ACF fields if present
        if (function_exists('acf_get_field_groups')) {
            $this->clone_acf_fields($source_id, $new_id);
        }

        // Handle form associations
        $this->clone_form_associations($source_id, $new_id);

        // Fire action for extensibility
        do_action('st_after_create_translation', $new_id, $source_id, $target_lang);

        // Clear caches
        $this->clear_translation_cache($source_id);
        $this->clear_translation_cache($new_id);

        return $new_id;
    }

    /**
     * Get translation for a specific language
     *
     * @param int    $post_id Post ID
     * @param string $lang    Language code
     * @return int|false Translation post ID or false if not found
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
        // Get translation group ID
        $group_id = get_post_meta($post_id, '_translation_group_id', true);
        if (!$group_id) {
            return array();
        }

        // Try cache first
        $cache_key = 'st_translations_' . $post_id;
        $cached = get_transient($cache_key);
        if (false !== $cached) {
            return $cached;
        }

        // Query all posts with same group ID
        $args = array(
            'post_type'      => 'any',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'meta_query'     => array(
                array(
                    'key'     => '_translation_group_id',
                    'value'   => $group_id,
                    'compare' => '='
                )
            ),
            'fields' => 'ids'
        );

        $posts = get_posts($args);
        $translations = array();

        foreach ($posts as $id) {
            $lang = get_post_meta($id, '_language', true);
            if ($lang) {
                $translations[$lang] = $id;
            }
        }

        // Cache for 1 hour
        set_transient($cache_key, $translations, HOUR_IN_SECONDS);

        return $translations;
    }

    /**
     * Clear translation cache for a post
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

            // Skip ACF meta (handled separately)
            if (strpos($key, '_') === 0 && function_exists('acf_get_field_groups')) {
                continue;
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
        $value = get_field($field['name'], $source_id, false);

        // Handle different field types
        switch ($field['type']) {
            case 'relationship':
            case 'post_object':
                // Don't clone post relationships initially
                // Add a note for manual review
                update_post_meta($target_id, '_acf_relationships_need_review', true);
                break;

            case 'flexible_content':
                // Clone flexible content layouts
                if (is_array($value)) {
                    update_field($field['name'], $value, $target_id);
                }
                break;

            case 'repeater':
                // Clone repeater fields
                if (is_array($value)) {
                    update_field($field['name'], $value, $target_id);
                }
                break;

            case 'group':
                // Clone group fields
                if (is_array($value)) {
                    update_field($field['name'], $value, $target_id);
                }
                break;

            case 'gallery':
            case 'image':
            case 'file':
                // Clone media fields (reference same media)
                if ($value) {
                    update_field($field['name'], $value, $target_id);
                }
                break;

            default:
                // Clone all other field types
                if (null !== $value) {
                    update_field($field['name'], $value, $target_id);
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
