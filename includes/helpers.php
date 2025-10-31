<?php
/**
 * Helper Functions
 *
 * Utility functions for use throughout the plugin and in themes
 *
 * @package SimpleTranslator
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get the plugin instance
 *
 * @return SimpleTranslator\Plugin
 */
function st_get_plugin() {
    return SimpleTranslator\Plugin::get_instance();
}

/**
 * Get current language
 *
 * @return string Current language code
 */
function st_get_current_language() {
    $plugin = st_get_plugin();
    return $plugin->url_manager->get_current_language();
}

/**
 * Get default language
 *
 * @return string Default language code
 */
function st_get_default_language() {
    return get_option('st_default_language', 'en');
}

/**
 * Get enabled languages
 *
 * @return array Array of enabled language codes
 */
function st_get_languages() {
    return get_option('st_enabled_languages', array('en', 'es'));
}

/**
 * Get translations for a post
 *
 * @param int $post_id Post ID (optional, defaults to current post)
 * @return array Array of language => post_id pairs
 */
function st_get_translations($post_id = null) {
    if (null === $post_id) {
        $post_id = get_the_ID();
    }

    if (!$post_id) {
        return array();
    }

    $plugin = st_get_plugin();
    return $plugin->clone_manager->get_translations($post_id);
}

/**
 * Get translation URL for a specific language
 *
 * @param string $lang    Language code
 * @param int    $post_id Post ID (optional, defaults to current post)
 * @return string|false Translation URL or false if not found
 */
function st_get_translation_url($lang, $post_id = null) {
    if (null === $post_id) {
        $post_id = get_the_ID();
    }

    if (!$post_id) {
        return false;
    }

    $plugin = st_get_plugin();
    return $plugin->url_manager->get_translation_url($post_id, $lang);
}

/**
 * Check if a translation exists for a language
 *
 * @param string $lang    Language code
 * @param int    $post_id Post ID (optional, defaults to current post)
 * @return bool
 */
function st_has_translation($lang, $post_id = null) {
    if (null === $post_id) {
        $post_id = get_the_ID();
    }

    if (!$post_id) {
        return false;
    }

    $translations = st_get_translations($post_id);
    return isset($translations[$lang]);
}

/**
 * Get post language
 *
 * @param int $post_id Post ID (optional, defaults to current post)
 * @return string Language code
 */
function st_get_post_language($post_id = null) {
    if (null === $post_id) {
        $post_id = get_the_ID();
    }

    if (!$post_id) {
        return st_get_default_language();
    }

    $lang = get_post_meta($post_id, '_language', true);
    return $lang ? $lang : st_get_default_language();
}

/**
 * Check if current request is for a specific language
 *
 * @param string $lang Language code
 * @return bool
 */
function st_is_language($lang) {
    return st_get_current_language() === $lang;
}

/**
 * Check if current request is for default language
 *
 * @return bool
 */
function st_is_default_language() {
    return st_get_current_language() === st_get_default_language();
}

/**
 * Get home URL for a specific language
 *
 * @param string $lang Language code (optional, defaults to current language)
 * @return string
 */
function st_get_home_url($lang = null) {
    $plugin = st_get_plugin();
    return $plugin->url_manager->get_home_url($lang);
}

/**
 * Get language name from code
 *
 * @param string $code Language code (e.g., 'en', 'es')
 * @return string Language name (e.g., 'English', 'Spanish')
 */
function st_get_language_name($code) {
    $languages = array(
        'en' => __('English', 'simple-translator'),
        'es' => __('Spanish', 'simple-translator'),
        'fr' => __('French', 'simple-translator'),
        'de' => __('German', 'simple-translator'),
        'it' => __('Italian', 'simple-translator'),
        'pt' => __('Portuguese', 'simple-translator'),
        'ru' => __('Russian', 'simple-translator'),
        'ja' => __('Japanese', 'simple-translator'),
        'zh' => __('Chinese', 'simple-translator'),
        'ar' => __('Arabic', 'simple-translator'),
        'nl' => __('Dutch', 'simple-translator'),
        'pl' => __('Polish', 'simple-translator'),
        'tr' => __('Turkish', 'simple-translator'),
        'ko' => __('Korean', 'simple-translator'),
        'sv' => __('Swedish', 'simple-translator'),
        'da' => __('Danish', 'simple-translator'),
        'no' => __('Norwegian', 'simple-translator'),
        'fi' => __('Finnish', 'simple-translator'),
    );

    return isset($languages[$code]) ? $languages[$code] : strtoupper($code);
}

/**
 * Get language native name from code
 *
 * @param string $code Language code
 * @return string Native language name
 */
function st_get_language_native_name($code) {
    $languages = array(
        'en' => 'English',
        'es' => 'Español',
        'fr' => 'Français',
        'de' => 'Deutsch',
        'it' => 'Italiano',
        'pt' => 'Português',
        'ru' => 'Русский',
        'ja' => '日本語',
        'zh' => '中文',
        'ar' => 'العربية',
        'nl' => 'Nederlands',
        'pl' => 'Polski',
        'tr' => 'Türkçe',
        'ko' => '한국어',
        'sv' => 'Svenska',
        'da' => 'Dansk',
        'no' => 'Norsk',
        'fi' => 'Suomi',
    );

    return isset($languages[$code]) ? $languages[$code] : strtoupper($code);
}

/**
 * Render language switcher
 *
 * @param array $args Switcher arguments
 */
function st_language_switcher($args = array()) {
    if (!class_exists('SimpleTranslator\\Language_Switcher')) {
        return;
    }

    $switcher = new SimpleTranslator\Language_Switcher();
    echo $switcher->render($args);
}

/**
 * Get translation status
 *
 * @param int $post_id Post ID (optional, defaults to current post)
 * @return string Status (not_started, in_progress, completed, needs_update)
 */
function st_get_translation_status($post_id = null) {
    if (null === $post_id) {
        $post_id = get_the_ID();
    }

    if (!$post_id) {
        return 'not_started';
    }

    $status = get_post_meta($post_id, '_translation_status', true);
    return $status ? $status : 'not_started';
}

/**
 * Check if a post is a translation
 *
 * @param int $post_id Post ID (optional, defaults to current post)
 * @return bool
 */
function st_is_translation($post_id = null) {
    if (null === $post_id) {
        $post_id = get_the_ID();
    }

    if (!$post_id) {
        return false;
    }

    $source_id = get_post_meta($post_id, '_source_post_id', true);
    return !empty($source_id);
}

/**
 * Get source post ID
 *
 * @param int $post_id Post ID (optional, defaults to current post)
 * @return int|false Source post ID or false if not a translation
 */
function st_get_source_post_id($post_id = null) {
    if (null === $post_id) {
        $post_id = get_the_ID();
    }

    if (!$post_id) {
        return false;
    }

    $source_id = get_post_meta($post_id, '_source_post_id', true);
    return $source_id ? (int) $source_id : false;
}

/**
 * Get translation group ID
 *
 * @param int $post_id Post ID (optional, defaults to current post)
 * @return string|false Translation group ID or false
 */
function st_get_translation_group_id($post_id = null) {
    if (null === $post_id) {
        $post_id = get_the_ID();
    }

    if (!$post_id) {
        return false;
    }

    $group_id = get_post_meta($post_id, '_translation_group_id', true);
    return $group_id ? $group_id : false;
}

/**
 * Create a translation
 *
 * @param int    $source_id   Source post ID
 * @param string $target_lang Target language code
 * @return int|WP_Error New post ID or WP_Error on failure
 */
function st_create_translation($source_id, $target_lang) {
    $plugin = st_get_plugin();
    return $plugin->clone_manager->create_translation($source_id, $target_lang);
}

/**
 * Check if a post type is translatable
 *
 * @param string $post_type Post type name (optional, defaults to current post type)
 * @return bool
 */
function st_is_post_type_translatable($post_type = null) {
    if (null === $post_type) {
        $post_type = get_post_type();
    }

    if (!$post_type) {
        return false;
    }

    $plugin = st_get_plugin();
    return $plugin->is_post_type_translatable($post_type);
}

/**
 * Get language flag emoji
 *
 * @param string $code Language code
 * @return string Flag emoji
 */
function st_get_language_flag($code) {
    $flags = array(
        'en' => '🇺🇸',
        'es' => '🇪🇸',
        'fr' => '🇫🇷',
        'de' => '🇩🇪',
        'it' => '🇮🇹',
        'pt' => '🇵🇹',
        'ru' => '🇷🇺',
        'ja' => '🇯🇵',
        'zh' => '🇨🇳',
        'ar' => '🇸🇦',
        'nl' => '🇳🇱',
        'pl' => '🇵🇱',
        'tr' => '🇹🇷',
        'ko' => '🇰🇷',
        'sv' => '🇸🇪',
        'da' => '🇩🇰',
        'no' => '🇳🇴',
        'fi' => '🇫🇮',
    );

    return isset($flags[$code]) ? $flags[$code] : '🌐';
}

/**
 * Sanitize language code
 *
 * @param string $code Language code to sanitize
 * @return string Sanitized language code
 */
function st_sanitize_language_code($code) {
    return preg_replace('/[^a-z_-]/', '', strtolower($code));
}

/**
 * Get switcher URLs for all languages
 *
 * @return array Array of language => URL pairs
 */
function st_get_switcher_urls() {
    $plugin = st_get_plugin();
    return $plugin->url_manager->get_switcher_urls();
}

/**
 * Log a message (for debugging)
 *
 * @param string $message Message to log
 * @param string $level   Log level (error, warning, info, debug)
 */
function st_log($message, $level = 'info') {
    $plugin = st_get_plugin();
    if ($plugin->logger) {
        $plugin->logger->log($message, $level);
    }
}

/**
 * Get translation statistics
 *
 * @return array Translation statistics
 */
function st_get_translation_stats() {
    $plugin = st_get_plugin();
    return $plugin->clone_manager->get_translation_stats();
}
