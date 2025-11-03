<?php
/**
 * Menu Translation View
 *
 * Template for the menu translation box on nav-menus.php
 *
 * @package SimpleTranslator
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Variables available from the render_menu_translation_box method:
// $menus - Array of menu objects
// $enabled_languages - Array of enabled language codes
// $language_options - Array of language_code => language_name pairs
?>

<div class="st-menu-translation-content">
    <p class="description">
        <?php esc_html_e('Clone a menu and automatically translate its items for a target language.', 'simple-translator'); ?>
    </p>

            <?php if (empty($menus)) : ?>
                <div class="notice notice-warning inline">
                    <p>
                        <?php esc_html_e('No menus found. Please create a menu first.', 'simple-translator'); ?>
                    </p>
                </div>
            <?php else : ?>
                <div class="st-menu-clone-form">
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row">
                                    <label for="st-source-menu">
                                        <?php esc_html_e('Source Menu:', 'simple-translator'); ?>
                                    </label>
                                </th>
                                <td>
                                    <select id="st-source-menu" class="widefat">
                                        <option value="">
                                            <?php esc_html_e('-- Select a menu --', 'simple-translator'); ?>
                                        </option>
                                        <?php foreach ($menus as $menu) : ?>
                                            <option value="<?php echo esc_attr($menu->term_id); ?>">
                                                <?php echo esc_html($menu->name); ?>
                                                (<?php
                                                $count = wp_count_terms('nav_menu', array('include' => array($menu->term_id)));
                                                printf(
                                                    /* translators: %d: number of items */
                                                    esc_html(_n('%d item', '%d items', $count, 'simple-translator')),
                                                    $count
                                                );
                                                ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">
                                        <?php esc_html_e('Select the menu you want to clone.', 'simple-translator'); ?>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="st-target-language">
                                        <?php esc_html_e('Target Language:', 'simple-translator'); ?>
                                    </label>
                                </th>
                                <td>
                                    <select id="st-target-language" class="widefat">
                                        <option value="">
                                            <?php esc_html_e('-- Select a language --', 'simple-translator'); ?>
                                        </option>
                                        <?php foreach ($language_options as $lang_code => $lang_name) : ?>
                                            <option value="<?php echo esc_attr($lang_code); ?>">
                                                <?php echo esc_html($lang_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">
                                        <?php esc_html_e('The new menu will be created for this language.', 'simple-translator'); ?>
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="st-menu-clone-actions">
                        <button type="button" id="st-clone-menu-btn" class="button button-primary">
                            <span class="dashicons dashicons-admin-page"></span>
                            <?php esc_html_e('Clone Menu', 'simple-translator'); ?>
                        </button>
                        <span class="spinner"></span>
                    </div>

                    <div id="st-menu-clone-message" class="st-message" style="display: none;">
                        <!-- Status messages will appear here -->
                    </div>
                </div>

                <div class="st-menu-info">
                    <h4><?php esc_html_e('How it works:', 'simple-translator'); ?></h4>
                    <ul>
                        <li><?php esc_html_e('The source menu will be duplicated with a language suffix', 'simple-translator'); ?></li>
                        <li><?php esc_html_e('Menu items linking to translated pages will be automatically linked', 'simple-translator'); ?></li>
                        <li><?php esc_html_e('The new menu will be assigned to the language-specific menu location', 'simple-translator'); ?></li>
                        <li><?php esc_html_e('You can then customize the cloned menu as needed', 'simple-translator'); ?></li>
                    </ul>
                </div>
            <?php endif; ?>
</div>
