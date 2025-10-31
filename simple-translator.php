<?php
/**
 * Plugin Name: Simple Translator
 * Plugin URI: https://rarediseases.org
 * Description: Lightweight translation plugin using post cloning for WordPress multisite
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Network: true
 * Author: Sean Roberts
 * Author URI: https://rarediseases.org
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: simple-translator
 * Domain Path: /languages
 */

// Security check
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('ST_VERSION', '1.0.0');
define('ST_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ST_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ST_PLUGIN_FILE', __FILE__);
define('ST_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * PSR-4 Autoloader for plugin classes
 */
spl_autoload_register(function ($class) {
    // Only autoload classes in our namespace
    $prefix = 'SimpleTranslator\\';

    // Check if the class uses the namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);

    // Replace namespace separators with directory separators
    $relative_class = str_replace('\\', DIRECTORY_SEPARATOR, $relative_class);

    // Convert class name to file name (PSR-4 style with class- prefix)
    // SimpleTranslator\Clone_Manager becomes class-clone-manager.php
    $class_parts = explode(DIRECTORY_SEPARATOR, $relative_class);
    $class_name = array_pop($class_parts);
    $class_file = 'class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';

    // Directories to search for class files
    $directories = array('includes', 'admin', 'public');

    // Try each directory until we find the file
    foreach ($directories as $directory) {
        $file = ST_PLUGIN_DIR . $directory . DIRECTORY_SEPARATOR;
        if (!empty($class_parts)) {
            $file .= implode(DIRECTORY_SEPARATOR, $class_parts) . DIRECTORY_SEPARATOR;
        }
        $file .= $class_file;

        // If the file exists, require it and stop searching
        if (file_exists($file)) {
            require $file;
            return;
        }
    }
});

// Load helper functions
require_once ST_PLUGIN_DIR . 'includes/helpers.php';

/**
 * Initialize the plugin
 */
function simple_translator_init() {
    // Load plugin text domain for translations
    load_plugin_textdomain('simple-translator', false, dirname(ST_PLUGIN_BASENAME) . '/languages');

    // Initialize the main plugin class
    if (class_exists('SimpleTranslator\\Plugin')) {
        $plugin = SimpleTranslator\Plugin::get_instance();
        $plugin->init();
    }
}
add_action('plugins_loaded', 'simple_translator_init');

/**
 * Plugin activation hook
 */
function simple_translator_activate($network_wide) {
    if (class_exists('SimpleTranslator\\Plugin')) {
        SimpleTranslator\Plugin::activate($network_wide);
    }
}
register_activation_hook(__FILE__, 'simple_translator_activate');

/**
 * Plugin deactivation hook
 */
function simple_translator_deactivate($network_wide) {
    if (class_exists('SimpleTranslator\\Plugin')) {
        SimpleTranslator\Plugin::deactivate($network_wide);
    }
}
register_deactivation_hook(__FILE__, 'simple_translator_deactivate');

/**
 * Check minimum requirements
 */
function simple_translator_check_requirements() {
    $required_php_version = '7.4';
    $required_wp_version = '5.8';

    if (version_compare(PHP_VERSION, $required_php_version, '<')) {
        deactivate_plugins(ST_PLUGIN_BASENAME);
        wp_die(
            sprintf(
                __('Simple Translator requires PHP version %s or higher. You are running version %s.', 'simple-translator'),
                $required_php_version,
                PHP_VERSION
            )
        );
    }

    global $wp_version;
    if (version_compare($wp_version, $required_wp_version, '<')) {
        deactivate_plugins(ST_PLUGIN_BASENAME);
        wp_die(
            sprintf(
                __('Simple Translator requires WordPress version %s or higher. You are running version %s.', 'simple-translator'),
                $required_wp_version,
                $wp_version
            )
        );
    }
}
register_activation_hook(__FILE__, 'simple_translator_check_requirements');
