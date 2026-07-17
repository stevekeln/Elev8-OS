# Elev8 OS Changelog

## 9.2.1 — Brand & Campaign Wizard

### Added
- Artist-first campaign wizard beginning with “What are you trying to accomplish today?”
- Campaign goals for filling classes, selling artwork, announcing events, bringing customers back, introducing artists, referrals, and custom campaigns
- Plain-language audience selection without exposing tags or merge-field mechanics
- Master Brand Template settings for brand name, logo attachment, colors, website, CTA defaults, and footer text
- Universal branded HTML email renderer with automatic logo, colors, CTA button, and footer
- Campaign draft storage separate from reusable template records
- Automatic-content controls for artist profile, upcoming classes, Elev8 events, and referrals
- Recent campaign draft list
- Template revision-history schema and automatic snapshot capture before template updates
- Template-selection helper that loads reusable content into the campaign wizard

### Changed
- Content Studio database version increased from 1.0.0 to 1.1.0
- Plugin version increased from 9.2.0 to 9.2.1
- Content Studio now loads its lightweight campaign interaction script

### Database changes
- Added `wp_elev8_os_content_campaigns`
- Added `wp_elev8_os_content_template_revisions`
- Existing tables are preserved and upgraded through `dbDelta`

### Compatibility
- Existing 9.2.0 content categories and templates remain unchanged
- Shared and personal template workflows remain backward compatible
- No WordPress, Amelia, or WooCommerce trusted-system data is duplicated
