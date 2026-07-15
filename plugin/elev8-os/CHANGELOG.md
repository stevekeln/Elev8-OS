# Changelog

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

## 5.1.0 — CEO Dashboard

- Expanded the CEO Dashboard with today-at-a-glance metrics.
- Added verified owner attention items.
- Added upcoming class dates and business-health metrics.
- Added responsive CEO Dashboard styling.
- Preserved the rule that unavailable data is never displayed as zero.

## 5.0.0 — Foundation

- Preserved Version 4.99 functionality.
- Added modular bootstrap and loader.
- Added integration boundaries for Amelia and WooCommerce.
- Added module scaffolds for Artist Portal, Waitlist, CRM, and Dashboard.
- Added System Status admin page.
- Standardized asset folders.

## 5.2.0
- Rebuilt the Artist Dashboard around verified upcoming Amelia appointments.
- Added upcoming class dates, service names, times, locations, booking records, and student totals when supported by the detected schema.
- Added clear empty and unavailable states instead of misleading zero values.
- Added a direct profile action while keeping payouts and tax documents visibly marked as planned.
