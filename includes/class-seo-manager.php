<?php
/**
 * SEO Manager Class
 *
 * Handles SEO-related functionality for translations
 *
 * @package SimpleTranslator
 */

namespace SimpleTranslator;

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SEO Manager - SEO and hreflang functionality
 */
class SEO_Manager {

    /**
     * Initialize the SEO manager
     */
    public function init() {
        // Add hreflang tags
        add_action('wp_head', array($this, 'add_hreflang_tags'), 1);

        // Add canonical tag (if no SEO plugin present)
        add_action('wp_head', array($this, 'add_canonical_tag'), 1);

        // Filter language attributes
        add_filter('language_attributes', array($this, 'filter_language_attributes'));

        // Add body classes
        add_filter('body_class', array($this, 'add_body_classes'));

        // Yoast SEO compatibility
        add_filter('wpseo_canonical', array($this, 'filter_yoast_canonical'));
        add_filter('wpseo_opengraph_url', array($this, 'filter_yoast_canonical'));
        add_filter('wpseo_metadesc', array($this, 'filter_yoast_metadesc'));

        // Rank Math compatibility
        add_filter('rank_math/frontend/canonical', array($this, 'filter_rankmath_canonical'));
        add_filter('rank_math/opengraph/facebook/og_url', array($this, 'filter_rankmath_canonical'));

        // Open Graph meta tags
        add_action('wp_head', array($this, 'add_opengraph_tags'), 5);

        // XML Sitemap compatibility
        add_filter('wp_sitemaps_posts_query_args', array($this, 'filter_sitemap_query'));
    }

    /**
     * Add hreflang tags for all translations
     */
    public function add_hreflang_tags() {
        if (!is_singular()) {
            return;
        }

        global $post;
        if (!$post) {
            return;
        }

        $clone_manager = new Clone_Manager();
        $translations = $clone_manager->get_translations($post->ID);

        if (empty($translations)) {
            return;
        }

        $url_manager = new URL_Manager();
        $default_lang = get_option('st_default_language', 'en');

        // Output hreflang for each translation
        foreach ($translations as $lang => $post_id) {
            $url = $url_manager->get_translation_url($post->ID, $lang);

            if ($url) {
                printf(
                    '<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
                    esc_attr(st_sanitize_language_code($lang)),
                    esc_url($url)
                );
            }
        }

        // Add x-default for default language
        if (isset($translations[$default_lang])) {
            $default_url = $url_manager->get_translation_url($post->ID, $default_lang);
            if ($default_url) {
                printf(
                    '<link rel="alternate" hreflang="x-default" href="%s" />' . "\n",
                    esc_url($default_url)
                );
            }
        }
    }

    /**
     * Add canonical tag if no SEO plugin is present
     */
    public function add_canonical_tag() {
        // Skip if SEO plugin is active
        if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION')) {
            return;
        }

        if (!is_singular()) {
            return;
        }

        global $post;
        if (!$post) {
            return;
        }

        $url_manager = new URL_Manager();
        $lang = $url_manager->get_post_language($post->ID);
        $canonical_url = $url_manager->get_translation_url($post->ID, $lang);

        if (!$canonical_url) {
            $canonical_url = get_permalink($post->ID);
        }

        if ($canonical_url) {
            printf(
                '<link rel="canonical" href="%s" />' . "\n",
                esc_url($canonical_url)
            );
        }
    }

    /**
     * Filter language attributes for HTML tag
     *
     * @param string $output Language attributes
     * @return string Modified language attributes
     */
    public function filter_language_attributes($output) {
        $url_manager = new URL_Manager();
        $current_lang = $url_manager->get_current_language();

        // Replace or add lang attribute
        if (preg_match('/lang="[^"]*"/', $output)) {
            $output = preg_replace('/lang="[^"]*"/', 'lang="' . esc_attr($current_lang) . '"', $output);
        } else {
            $output .= ' lang="' . esc_attr($current_lang) . '"';
        }

        return $output;
    }

    /**
     * Add language body classes
     *
     * @param array $classes Body classes
     * @return array Modified body classes
     */
    public function add_body_classes($classes) {
        $url_manager = new URL_Manager();
        $current_lang = $url_manager->get_current_language();

        $classes[] = 'language-' . $current_lang;
        $classes[] = 'lang-' . $current_lang;

        // Add class for default language
        if ($url_manager->is_default_language()) {
            $classes[] = 'default-language';
        }

        // Add class if this is a translation
        global $post;
        if ($post && st_is_translation($post->ID)) {
            $classes[] = 'is-translation';
        }

        return $classes;
    }

    /**
     * Filter Yoast SEO canonical URL
     *
     * @param string $canonical Canonical URL
     * @return string Modified canonical URL
     */
    public function filter_yoast_canonical($canonical) {
        if (!is_singular()) {
            return $canonical;
        }

        global $post;
        if (!$post) {
            return $canonical;
        }

        $url_manager = new URL_Manager();
        $lang = $url_manager->get_post_language($post->ID);
        $translation_url = $url_manager->get_translation_url($post->ID, $lang);

        return $translation_url ? $translation_url : $canonical;
    }

    /**
     * Filter Rank Math canonical URL
     *
     * @param string $canonical Canonical URL
     * @return string Modified canonical URL
     */
    public function filter_rankmath_canonical($canonical) {
        return $this->filter_yoast_canonical($canonical);
    }

    /**
     * Filter Yoast SEO meta description
     *
     * @param string $metadesc Meta description
     * @return string Modified meta description
     */
    public function filter_yoast_metadesc($metadesc) {
        if (!is_singular()) {
            return $metadesc;
        }

        global $post;
        if (!$post) {
            return $metadesc;
        }

        // Use excerpt if available
        if ($post->post_excerpt) {
            return wp_trim_words($post->post_excerpt, 30);
        }

        return $metadesc;
    }

    /**
     * Add Open Graph meta tags
     */
    public function add_opengraph_tags() {
        // Skip if SEO plugin is handling this
        if (defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION')) {
            return;
        }

        if (!is_singular()) {
            return;
        }

        global $post;
        if (!$post) {
            return;
        }

        $url_manager = new URL_Manager();
        $lang = $url_manager->get_post_language($post->ID);
        $url = $url_manager->get_translation_url($post->ID, $lang);

        if (!$url) {
            $url = get_permalink($post->ID);
        }

        // Basic Open Graph tags - sanitize language code for security
        echo '<meta property="og:locale" content="' . esc_attr(st_sanitize_language_code($lang)) . '" />' . "\n";
        echo '<meta property="og:type" content="article" />' . "\n";
        echo '<meta property="og:title" content="' . esc_attr(get_the_title($post->ID)) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url($url) . '" />' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '" />' . "\n";

        // Description
        if ($post->post_excerpt) {
            echo '<meta property="og:description" content="' . esc_attr(wp_trim_words($post->post_excerpt, 30)) . '" />' . "\n";
        }

        // Image
        if (has_post_thumbnail($post->ID)) {
            $thumbnail = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'large');
            if ($thumbnail) {
                echo '<meta property="og:image" content="' . esc_url($thumbnail[0]) . '" />' . "\n";
                echo '<meta property="og:image:width" content="' . esc_attr($thumbnail[1]) . '" />' . "\n";
                echo '<meta property="og:image:height" content="' . esc_attr($thumbnail[2]) . '" />' . "\n";
            }
        }

        // Alternate locales
        $clone_manager = new Clone_Manager();
        $translations = $clone_manager->get_translations($post->ID);

        foreach ($translations as $trans_lang => $trans_id) {
            if ($trans_lang !== $lang) {
                echo '<meta property="og:locale:alternate" content="' . esc_attr(st_sanitize_language_code($trans_lang)) . '" />' . "\n";
            }
        }
    }

    /**
     * Filter XML sitemap query to include language meta
     *
     * @param array $args Query arguments
     * @return array Modified query arguments
     */
    public function filter_sitemap_query($args) {
        $url_manager = new URL_Manager();
        $current_lang = $url_manager->get_current_language();

        // Add meta query to filter by language
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
     * Get SEO title for current page
     *
     * @return string SEO title
     */
    public function get_seo_title() {
        if (is_singular()) {
            global $post;
            return get_the_title($post->ID);
        } elseif (is_home() || is_front_page()) {
            return get_bloginfo('name') . ' - ' . get_bloginfo('description');
        } elseif (is_category()) {
            return single_cat_title('', false);
        } elseif (is_tag()) {
            return single_tag_title('', false);
        } elseif (is_archive()) {
            return get_the_archive_title();
        } elseif (is_search()) {
            return sprintf(__('Search Results for: %s', 'simple-translator'), get_search_query());
        } elseif (is_404()) {
            return __('Page Not Found', 'simple-translator');
        }

        return get_bloginfo('name');
    }

    /**
     * Get SEO description for current page
     *
     * @return string SEO description
     */
    public function get_seo_description() {
        if (is_singular()) {
            global $post;
            if ($post->post_excerpt) {
                return wp_trim_words($post->post_excerpt, 30);
            }
            return wp_trim_words($post->post_content, 30);
        } elseif (is_home() || is_front_page()) {
            return get_bloginfo('description');
        }

        return '';
    }
}
