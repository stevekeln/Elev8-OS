# Changelog

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
