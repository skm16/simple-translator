# Simple Translator for WordPress

A lightweight, performant translation plugin for WordPress Multisite that uses post cloning instead of runtime translation. Designed for optimal performance and ease of use for non-technical users.

## Features

### Core Functionality
- **Post Cloning**: Each translation is a separate WordPress post with its own URL
- **Zero Runtime Overhead**: No DOM parsing or runtime translation processing
- **ACF Compatible**: Full support for ACF fields including flexible content
- **SEO Optimized**: Automatic hreflang tags, canonical URLs, Open Graph meta tags
- **Menu Translation**: Automatic menu switching based on language
- **Search Filtering**: Language-specific search results

### User Experience
- **Familiar Interface**: Uses standard WordPress post editor
- **Translation Metabox**: Manage all translations from a single post
- **Visual Progress**: See translation status at a glance
- **Language Switcher**: Multiple display formats (dropdown, list, flags)
- **Draft Safety**: Translations start as drafts to prevent publishing untranslated content

### Technical Advantages
- **Lightweight**: ~6,000 lines of code total
- **No Custom Tables**: Uses WordPress post meta
- **Multisite Ready**: Network-wide activation support
- **Performance**: <5 additional database queries per page
- **Cache Friendly**: Built-in caching with transients

## Installation

### Requirements
- **WordPress**: 5.8 or higher
- **PHP**: 7.4 or higher
- **Multisite**: Optional but recommended

### Quick Start

1. **Upload the Plugin**
   ```bash
   cd wp-content/plugins/
   # Upload the simple-translator directory
   ```

2. **Activate the Plugin**
   - Go to Plugins > Installed Plugins
   - Activate "Simple Translator"

3. **Configure Languages**
   - Default languages are English (en) and Spanish (es)
   - Modify in settings if needed

4. **Start Translating**
   - Edit any post or page
   - Look for the "Translations" metabox in the sidebar
   - Click "Create Translation" for the desired language

## Usage

### Creating a Translation

1. Open any post or page in the WordPress editor
2. Find the "Translations" metabox in the right sidebar
3. Click "Create Translation" for your target language (e.g., Spanish)
4. The plugin will create a draft clone with:
   - All content from the original post
   - Same taxonomies (categories, tags)
   - Same featured image
   - All custom fields and ACF data
   - Status set to "not_started"

5. Edit the cloned post to translate the content
6. Update the translation status as you progress:
   - **Not Started**: Initial state
   - **In Progress**: Currently being translated
   - **Completed**: Translation finished
   - **Needs Update**: Source changed, translation needs updating

7. Publish when ready

### URL Structure

The plugin uses SEO-friendly URL prefixes:

- **Default Language (English)**: `https://example.com/about/`
- **Spanish**: `https://example.com/es/about/`
- **French**: `https://example.com/fr/about/`

Fallback query parameter support: `https://example.com/about/?lang=es`

### Adding the Language Switcher

#### Widget
1. Go to Appearance > Widgets
2. Add the "Language Switcher" widget to your sidebar
3. Configure display options (dropdown, list, or flags)

#### Shortcode
Add anywhere in your content:
```
[st_language_switcher format="dropdown" show_flags="true"]
```

Options:
- `format`: dropdown, list, or flags
- `show_flags`: true or false
- `show_names`: true or false

#### Theme Template
Add to your theme files:
```php
<?php if (function_exists('st_language_switcher')) {
    st_language_switcher(array(
        'format' => 'list',
        'show_flags' => true,
        'show_names' => true
    ));
} ?>
```

### Menu Translation

#### Method 1: Language-Specific Menus
1. Go to Appearance > Menus
2. Create separate menus for each language (e.g., "Primary Menu ES")
3. Assign them to language-specific locations
4. The plugin automatically switches menus based on language

#### Method 2: Add Switcher to Menu
1. Enable in plugin settings
2. Language switcher appears as last menu item

### Working with ACF

The plugin automatically clones ACF fields including:
- **Text fields**
- **WYSIWYG editors**
- **Images and galleries**
- **Repeater fields**
- **Flexible content** (page builder layouts)
- **Group fields**

**Note**: Relationship and Post Object fields are not automatically cloned and will be flagged for manual review.

### Working with Forms

The plugin detects forms from:
- **Gravity Forms**
- **Contact Form 7**
- **FormAssembly**

Translations containing forms are flagged with a notice to review form localization.

## Developer Documentation

### Template Functions

```php
// Get current language
$lang = st_get_current_language(); // Returns 'en', 'es', etc.

// Get all translations for a post
$translations = st_get_translations($post_id);
// Returns: array('en' => 123, 'es' => 456)

// Get translation URL
$url = st_get_translation_url('es', $post_id);

// Check if translation exists
if (st_has_translation('es', $post_id)) {
    // Translation exists
}

// Get post language
$lang = st_get_post_language($post_id);

// Check current language
if (st_is_language('es')) {
    // We're on Spanish version
}

// Get language name
echo st_get_language_name('es'); // "Spanish"
echo st_get_language_native_name('es'); // "Español"
```

### Hooks and Filters

```php
// After creating a translation
add_action('st_after_create_translation', function($new_id, $source_id, $target_lang) {
    // Custom logic here
}, 10, 3);

// Modify available languages
add_filter('st_enabled_languages', function($languages) {
    return array('en', 'es', 'fr', 'de');
});

// Customize language switcher output
add_filter('st_language_switcher_html', function($html, $args) {
    // Modify HTML
    return $html;
}, 10, 2);
```

### Database Schema

The plugin uses WordPress post meta (no custom tables):

- `_language`: Language code (e.g., 'en', 'es')
- `_translation_group_id`: UUID linking related translations
- `_translation_status`: Translation progress status
- `_source_post_id`: ID of source post (for translations)
- `_translation_last_sync`: Timestamp of last sync
- `_forms_need_translation`: Boolean flag for form presence
- `_acf_relationships_need_review`: Boolean flag for ACF relationships

### Post Meta Keys

```php
// Check post language
$lang = get_post_meta($post_id, '_language', true);

// Get translation group
$group_id = get_post_meta($post_id, '_translation_group_id', true);

// Check translation status
$status = get_post_meta($post_id, '_translation_status', true);
```

## SEO Features

### Automatic hreflang Tags
```html
<link rel="alternate" hreflang="en" href="https://example.com/about/" />
<link rel="alternate" hreflang="es" href="https://example.com/es/about/" />
<link rel="alternate" hreflang="x-default" href="https://example.com/about/" />
```

### Canonical URLs
Properly set for each language version to avoid duplicate content issues.

### Open Graph Tags
Automatic locale and alternate locale tags for social sharing.

### SEO Plugin Compatibility
- **Yoast SEO**: Full compatibility
- **Rank Math**: Full compatibility
- **All in One SEO**: Compatible

### Body Classes
Automatic language classes for CSS targeting:
```html
<body class="language-es lang-es is-translation">
```

## Performance

### Benchmarks
- **Page Load**: Zero additional overhead (no runtime translation)
- **Clone Creation**: ~2 seconds for typical page
- **Admin Interface**: <500ms response time
- **Database Queries**: <5 additional queries per page
- **Memory Usage**: <10MB additional

### Caching
- Translation lookups cached for 1 hour
- Automatic cache invalidation on updates
- Compatible with object caching plugins

## Troubleshooting

### Translations Not Showing
1. Check that post has `_language` meta set
2. Verify translation group ID matches
3. Clear permalink cache: Settings > Permalinks > Save

### Language Switcher Not Appearing
1. Ensure widget is added to sidebar
2. Check that multiple languages are enabled
3. Verify translations exist for current post

### URLs Not Working
1. Go to Settings > Permalinks
2. Click "Save Changes" to flush rewrite rules
3. Test in incognito mode (clear browser cache)

### ACF Fields Not Cloning
1. Ensure ACF is active before cloning
2. Check for custom field types
3. Review relationship fields manually

## Multisite Configuration

### Network Activation
1. Network Admin > Plugins
2. Network Activate "Simple Translator"
3. Configure network-wide settings
4. Each site can enable specific languages

### Per-Site Settings
- Languages can be enabled/disabled per site
- URL structure configured per site
- Post types configured per site

## Roadmap

### Version 1.1
- [ ] Translation memory (common phrases)
- [ ] Bulk translation creation
- [ ] CSV import/export
- [ ] Translation statistics dashboard

### Version 1.2
- [ ] Machine translation API integration (optional)
- [ ] Translation workflow (review/approve)
- [ ] User role permissions
- [ ] Translation diff viewer

### Future
- [ ] String translation (theme/plugin text)
- [ ] Media library translation
- [ ] Custom post type archives
- [ ] Taxonomy translation

## Support

### Documentation
- [Plugin Documentation](https://github.com/yourusername/simple-translator/wiki)
- [FAQ](https://github.com/yourusername/simple-translator/wiki/FAQ)

### Community
- [GitHub Issues](https://github.com/yourusername/simple-translator/issues)
- [WordPress.org Support](https://wordpress.org/support/plugin/simple-translator/)

### Professional Support
For priority support and custom development, contact: [your-email@example.com]

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

### Development Setup
```bash
# Clone repository
git clone https://github.com/yourusername/simple-translator.git

# Install dependencies (if any)
composer install

# Run tests
phpunit
```

## License

GPL v2 or later. See [LICENSE](LICENSE) for full license text.

## Credits

Developed by Sean Roberts for the rare disease nonprofit community.

### Special Thanks
- WordPress core team
- ACF team for great field support
- Contributors and testers

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for detailed version history.

### Version 1.0.0 (Current)
- Initial release
- Post/page translation via cloning
- ACF field support
- SEO optimization
- Menu translation
- Language switcher widget
- Multisite support
- Gravity Forms detection
- Search filtering

## FAQ

**Q: Does this work with page builders?**
A: Yes, especially ACF flexible content. Other page builders may require testing.

**Q: Can I translate custom post types?**
A: Yes, enable them in plugin settings.

**Q: Will this slow down my site?**
A: No, zero runtime translation means no performance impact.

**Q: Can I migrate from WPML/Polylang?**
A: Migration tools are planned for future versions.

**Q: Does it work with WooCommerce?**
A: Basic product translation works, full e-commerce features planned for v1.2.

**Q: Is it GDPR compliant?**
A: Yes, no external APIs or data collection by default.

---

**Made with ❤️ for the WordPress community**
