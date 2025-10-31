# Changelog

All notable changes to Simple Translator will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-10-31

### Initial Release

#### Added
- **Core Translation System**
  - Post cloning for translations (no runtime translation)
  - Translation group management via UUID
  - Translation status tracking (not_started, in_progress, completed, needs_update)
  - Support for posts, pages, and custom post types

- **Admin Interface**
  - Translation metabox in post editor
  - AJAX-powered translation creation
  - Translation status column in post list
  - Visual translation progress indicators
  - Quick edit language support
  - Admin notices for translation guidance

- **ACF Integration**
  - Automatic cloning of ACF fields
  - Flexible content support
  - Repeater field support
  - Group field support
  - Image and gallery field support
  - Relationship field detection (manual review)

- **Form Plugin Detection**
  - Gravity Forms detection
  - Contact Form 7 detection
  - FormAssembly detection
  - Admin notices for forms needing translation

- **URL Management**
  - Language prefix URLs (`/es/about/`)
  - Fallback query parameter (`?lang=es`)
  - Automatic language detection from URL
  - Language cookie support
  - Permalink filtering by language

- **SEO Features**
  - Automatic hreflang tags
  - Canonical URL management
  - HTML lang attribute updates
  - Language-specific body classes
  - Open Graph meta tags
  - Yoast SEO compatibility
  - Rank Math compatibility

- **Menu Translation**
  - Language-specific menu locations
  - Automatic menu switching
  - Menu cloning functionality
  - Language switcher in menus (optional)

- **Language Switcher**
  - Widget implementation
  - Shortcode support
  - Multiple display formats (dropdown, list, flags)
  - Customizable options
  - Template function support

- **Search & Filtering**
  - Language-specific search results
  - Automatic query filtering
  - Search form language field

- **Frontend**
  - Language switcher widget
  - Frontend CSS styling
  - Accessibility enhancements
  - RTL support
  - Dark mode support

- **Performance**
  - Transient caching (1 hour)
  - Automatic cache invalidation
  - Minimal database queries (<5 per page)
  - Zero runtime translation overhead

- **Multisite Support**
  - Network-wide activation
  - Per-site language configuration
  - Network admin settings
  - Site-specific post type settings

- **Developer Features**
  - Template helper functions
  - Action and filter hooks
  - Debug logging system
  - PSR-4 autoloading
  - Comprehensive documentation

- **Uninstall**
  - Clean removal of plugin data
  - Optional data retention
  - Proper cleanup of transients
  - Log file removal

### Technical Specifications
- WordPress 5.8+ required
- PHP 7.4+ required
- ~6,000 lines of code
- Zero external dependencies
- WordPress coding standards compliant

### Files Created
- Core: 7 PHP files (Plugin, Clone Manager, URL Manager, SEO Manager, etc.)
- Admin: 5 files (Translation Admin, views, CSS, JS)
- Frontend: 5 files (Frontend, Language Switcher, assets)
- Supporting: helpers.php, uninstall.php, .gitignore
- Documentation: README.md, CHANGELOG.md, LICENSE

### Known Limitations
- Page builder compatibility (partially supported)
- No translation memory (planned for 1.1)
- No bulk operations (planned for 1.1)
- Manual relationship field review required
- Form translation requires manual setup

---

## [Unreleased]

### Planned for 1.1
- Translation memory for common phrases
- Bulk translation creation
- Translation statistics dashboard
- CSV import/export
- Improved page builder support

### Planned for 1.2
- Optional machine translation API integration
- Translation workflow (review/approve)
- User role permissions
- Translation diff viewer
- WooCommerce full support

### Future Considerations
- String translation (theme/plugin text)
- Media library translation
- Custom post type archive translation
- Taxonomy translation
- REST API endpoints
- Migration tools from WPML/Polylang

---

## Version History

[1.0.0]: https://github.com/yourusername/simple-translator/releases/tag/v1.0.0
