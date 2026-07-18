# Changelog

## 10.2.5
- Added the artist-facing Growth Studio as the single navigation destination for content, marketing, print, and QR tools.
- Added goal-first shortcuts for promoting artwork, classes, events, and artist profiles.
- Preserved the existing Marketing and Content Studio engines and their legacy page URLs.
- Replaced the artist growth experience's dark blue styling with a coordinated purple, lavender, and teal palette.
- Standardized white text on dark purple and teal buttons for readability.
- Kept owner-facing Artist Growth Center separate from the artist Growth Studio.


- Added a prominent Print & QR action card inside the artist Marketing Center.
- Added the same Print & QR action card inside the artist Content Studio.
- Added direct links to My Print Center and My Artwork from both growth workflows.
- Centralized the shared Print Center entry card in the existing Artist Print Center module to avoid duplicated UI logic.
- Preserved the existing dashboard and portal navigation links.
- Database changes: none.

## 10.2.3 — Artist Identity System Integration

- Merged the Artist Identity System into the 10.2.2 source without removing newer modules.
- Added lavender, minimal, and ink-safe artist identity themes.
- Added 5 × 7 artist table displays.
- Added 3 × 1 artist labels and 16-label letter sheets.
- Added 3 × 1 artwork labels and 16-label letter sheets.
- Added a large standalone artist profile QR display.
- Refined public artist profiles with a clean white-and-lavender portfolio presentation.
- Preserved all 10.2.2 Artist Website Navigation, Growth Center, Business GPS, Artist Success, marketing, and Content Studio functionality.
- Database changes: none.

## 10.2.2 — Artist Website Navigation

- Added a permanent website navigation bar to every Elev8 OS Artist Portal screen.
- Added direct links to Elev8 Arts Home, the logged-in artist's public page, Book a Class, Events, Shop, and Log Out.
- Added a prominent public-page link beneath the dashboard welcome message.
- Artist public-page links are derived from the verified artist mapping, with saved profile links taking priority.
- WooCommerce remains the source of truth for the Shop URL.
- Log Out safely returns the artist to the Elev8 Arts home page.
- Added responsive mobile navigation styling.
- No database changes.

## 10.1.0 — Brand Experience

- Rebuilt the universal email renderer into a polished, mobile-friendly branded marketing layout.
- Added automatic Elev8 Arts logo support with a visual WordPress Media Library picker and theme-logo fallback.
- Added configurable brand tagline, Book a Class URL, Events URL, Artist Directory URL, mission message, address, and social links.
- Added permanent Book a Class and Upcoming Events discovery cards using the requested Elev8 Arts URLs.
- Added campaign-aware secondary calls to action for artwork, class, and event campaigns.
- Added a nonprofit mission panel and refined branded footer with tasteful Powered by Elev8 OS credit.
- Preserved campaign-specific primary CTA controls and existing smart-section checkboxes.
- No database changes. Existing brand settings remain backward compatible.

## 10.0.0 — Artist Success

- Added the Artist Success welcome experience with time-aware greeting and motivational guidance.
- Added verified weekly goals based on meaningful business activity.
- Added Momentum status without inventing unsupported historical comparisons.
- Added Artist Journey levels from Beginning Artist through Master Artist.
- Added a prioritized 30-minute action plan using Business GPS opportunities.
- Added Celebrate Wins cards sourced from the normalized Business Event Engine.
- Added owner-level Gallery Health with organization-wide score categories and support signals.
- Reused Business GPS, Opportunity Engine, achievements, Content Studio launcher, Amelia, and WooCommerce data.
- No database changes.

# Changelog

## 9.4.0 — Business GPS & Intelligence Foundation

- Added Business GPS to the Artist Dashboard with business health, estimated monthly revenue, highest opportunity, biggest risk, and recommended first step.
- Added a reusable Business Event Service that normalizes verified Amelia, WooCommerce, artwork, QR, and achievement activity without duplicating trusted-system records.
- Added an explainable Opportunity Engine with conservative revenue estimates only when verified price and capacity data support them.
- Added a seven-day Content Calendar connected directly to Content Studio campaign goals.
- Added Predictive Scheduling guidance with explicit confidence and an Unavailable state when class history is insufficient.
- Added a unified Business Timeline for sales, classes, engagement, QR activity, and achievements.
- Added reusable service boundaries for future conversational AI coaching and owner intelligence.
- No database changes.

# Elev8 OS Changelog

## 9.3.2 — Campaign Wizard Centering Fix

- Removed the viewport-based transform that pulled the front-end Campaign Wizard off the left edge.
- Added a Content Studio page body class so the WordPress theme container can be widened safely.
- Centered the application shell with a controlled maximum width and normal document flow.
- Preserved the two-column campaign form and branded preview layout from 9.3.1.
- Improved tablet and mobile page padding.
- No database changes.

## 9.3.0 — Artist Growth Center
- Activated the reusable Artist Business Snapshot, Business Score, Recommendation Engine, Growth Plan, and Achievement services.
- Added an artist Business Score with transparent component scores.
- Added Today's Opportunities with one-click Content Studio campaign launching.
- Added verified-data achievement progress.
- Added the owner Artist Growth Center summary under Elev8 OS.
- Preserved the 9.2.2 Class Requests styling fix and all existing dashboard functionality.
- No database schema changes.

# Elev8 OS Changelog

## 9.3.1 — Campaign Wizard Layout Polish

- Fixed the front-end Content Studio being constrained by the WordPress theme content width.
- Added a full-width, theme-safe Content Studio application shell.
- Rebuilt the Campaign Wizard into a clean two-column form and preview workspace.
- Added consistent field sizing, spacing, focus states, card styling, and mobile responsiveness.
- Made the branded email preview sticky on desktop and stacked on smaller screens.
- All Growth Center campaign launch buttons now benefit from the shared wizard layout fix.
- No database changes.

## 9.2.2 — Class Requests Interface Polish

### Fixed
- Class Requests admin screen now loads its required portal and waitlist styles.
- Admin output now uses the same structured Class Requests wrapper as the Artist Portal.
- Removed the unstyled duplicate admin heading above the application interface.

### Improved
- Added a cleaner admin header, consistent cards, responsive spacing, and properly sized form controls.
- Preserved all existing Class Requests, opportunity, filtering, follow-up, and deletion behavior.

### Database changes
- None.

### Compatibility
- Backward compatible with the existing 9.2.1 Class Requests data and Content Studio schema.
- No WordPress, Amelia, or WooCommerce trusted-system data is duplicated.

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

## 10.2.0 — Shared Brand System
- Clarified that the Universal Email Brand System is the owner-controlled organization brand frame.
- Added a reusable Artist Brand Service that resolves the linked artist from the existing WordPress-to-Amelia identity mapping.
- Added artist-managed email identity settings in Manage My Website: optional logo, accent color, featured heading, short introduction, signature, and enable/disable control.
- Added a combined organization + artist email layout with artist image, story, profile link, website, social links, and signature.
- Preserved the official organization logo, mission, Book a Class link, Events link, address, social links, and footer as owner-controlled settings.
- Reused the existing artist profile option and WordPress Media Library; no new database tables were introduced.

## 10.2.1 — Artist Access Fix

- Redirects linked artists from Ultimate Member login directly to the Elev8 OS Artist Dashboard.
- Supports both WordPress native login redirects and Ultimate Member's own login flow.
- Converts legacy `/user/...` Ultimate Member profile destinations into a safe bridge to the Artist Dashboard for linked artists.
- Preserves normal WordPress behavior for administrators and users who are not linked to an Amelia artist/provider.
- Adds redirect-loop protections and keeps the public Artist Dashboard as the single artist home.
- No database changes.
