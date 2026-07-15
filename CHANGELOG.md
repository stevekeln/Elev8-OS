## 5.5.0 — Artist Portal Foundation Hardening

- Added a central Portal Page Manager.
- Stores exact WordPress page IDs for Artist Dashboard, My Website, and Edit Website.
- Discovers existing portal pages by page ID, slug, or shortcode before creating anything.
- Automatically repairs missing shortcodes and missing portal pages.
- Added Elev8 OS → Portal Setup with page status and one-click repair.
- Portal navigation now uses saved page permalinks instead of hard-coded slugs.

# Changelog

## 5.4.1 — Version Display Hotfix

- Fixed Elev8 OS admin screens showing the old 5.0.0 version label.
- Internal version displays and asset cache versions now use the installed plugin version constant.
- Added backward compatibility for the earlier `[elev8_artist_website_editor]` shortcode.

## 5.4.0 — Manage My Website

- Activated **Edit Website** as a working Artist Portal feature.
- Added a private, artist-scoped form for public bio, specialties, experience, images, gallery, social links, contact links, payment links, and booking button.
- Artists can save their own public website without entering WordPress administration.
- Preserves administrator-owned settings such as Amelia mapping, payouts, referral percentage, W-9 status, agreements, and internal announcements.
- Added nonce, identity mapping, sanitization, cache purging, and success feedback.
- Automatically creates the `/artist-edit-website/` portal page.

## 5.3.0 — Artist Website Phase 1

- Added a private **My Website** page to the Artist Portal.
- Artists can preview their public artist profile from inside the portal.
- Added Copy Link, Open Public Page, and Edit My Profile actions.
- The portal resolves the artist through the verified WordPress-to-Amelia mapping, with email fallback.
- Automatically creates the `/artist-website/` portal page when an administrator loads WordPress.
- Keeps public profile rendering in the existing Elev8 OS profile engine rather than duplicating profile logic.

All notable changes to Elev8 OS should be documented here.

The project follows semantic versioning:

- **MAJOR:** Breaking architectural or compatibility change
- **MINOR:** New backward-compatible functionality
- **PATCH:** Backward-compatible bug fix

## [Unreleased]

### Planned

- Founders Edition source audit
- Architecture cleanup
- Waitlist design
- CEO dashboard specification
- CRM specification

## [4.99.0] — Vision Edition

### Added

- Vision-focused development center
- Product philosophy
- Roadmap and planning concepts
- Opportunity and problem framing
- Future waitlist, CRM, dashboard, and intelligence direction

### Existing foundation

- Artist partnership rules
- Artist portal
- Public artist pages
- Booking destination buttons
- Contact, payment, and social links
- Referral links
- QR code support
- Tax-document fields
- Amelia and WooCommerce integration work

### Known issues

- Existing code requires a complete audit before it is treated as a stable production foundation
- Amelia scheduling structures need a documented integration strategy
- Automated tests do not yet exist

## 5.2.0 — Artist Dashboard Schedule
- Added a practical teacher dashboard with verified upcoming classes and enrollment.
- Preserved fail-closed data behavior and the existing CEO Dashboard and portal foundation.
