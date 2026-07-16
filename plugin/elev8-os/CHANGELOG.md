# 7.2.2 — Storefront Cart Navigation

- Fixed the Artist Storefront Add to Cart action so it opens the WooCommerce Cart page immediately after adding an artwork item.
- Added a persistent View Cart button to public artist storefronts.
- Added a direct Checkout link beside the cart control.
- Kept WooCommerce as the owner of cart and checkout behavior while Elev8 OS continues to own asset and inventory state.
- Preserved artist sale notifications, existing products, artwork, orders, and live data.

# 7.2.1 — Artist Sale Notifications

- Added a reusable Asset Engine sale-notification service.
- Sends the owning artist an HTML email after WooCommerce confirms a paid artwork order.
- Includes artwork image, title, asset number, sale price, order number, sold date, and inventory status.
- Uses the shared Elev8 OS Notification Service rather than direct module-level email logic.
- Prevents duplicate artist emails when an order moves through both Processing and Completed.
- Adds a private WooCommerce order note recording successful or failed artist notification delivery.
- Notifies the site administrator when an artwork owner does not have a valid email address.
- Does not expose customer payment details or private customer information to the artist.
- Preserves all existing Asset Engine, storefront, inventory, checkout, and live artwork data.

# Elev8 OS Changelog

## 7.2.0 — Inventory & WooCommerce Checkout

### Added
- Inventory Foundation fields: stable asset number/SKU, physical location, quantity, received date, public visibility, online checkout control, and private internal notes.
- Reserved inventory status for checkout protection.
- Automatic WooCommerce simple-product creation and synchronization from the Elev8 OS Asset record.
- Clickable public product cards, product detail links, and Add to Cart checkout buttons.
- WooCommerce stock management and sold-individually protection for one-of-a-kind artwork.
- Order lifecycle synchronization: checkout reserves the asset, paid orders mark it sold, and failed/cancelled/refunded reservations return it to available inventory.
- Asset QR-code links and WooCommerce connection status inside My Artwork.
- Automatic migration and synchronization for artwork created in earlier Asset Engine versions.

### Architecture
- Elev8 OS remains the source of truth for asset identity, artist ownership, inventory state, location, and future business intelligence.
- WooCommerce remains the source of truth for cart, checkout, payment, order, and commerce stock transactions.
- One Asset record powers the public artist store and WooCommerce product without duplicate entry.

### Compatibility
- Existing artwork and live artist pages are preserved.
- When WooCommerce is unavailable or an item has no price, the public page safely falls back to inquiry/unavailable messaging.

# 7.1.2 — Responsive Asset Storefront Grid

- Changed the public artist storefront to a consistent three-column product grid on desktop.
- Added a two-column tablet and smaller-desktop layout.
- Added a single-column mobile layout for readable product cards and touch-friendly actions.
- Preserved the existing Asset Engine source of truth, product details, sold state, and inquiry workflow.
- No database changes or asset data migration are required.

# 7.1.1 — Asset Storefront Integration

- Replaced the separate public image gallery with an Asset Engine-powered artist storefront.
- Public artist pages now display artwork and products directly from My Artwork without duplicate entry.
- Added reusable public-asset queries that keep Draft and Archived records private.
- Added product cards with image, title, verified price, medium, dimensions, description, availability, and sold state.
- Added an inquiry action using the artist's existing public contact information when available.
- Changed newly created artwork to default to Available so it can power the artist storefront immediately.
- Removed gallery URL entry from artist website editing while preserving all legacy gallery data for backward compatibility.
- Kept WooCommerce product linkage, checkout, inventory synchronization, and individual public asset pages as future shared-service integrations.

# 7.1.0 — Asset Engine: My Artwork Foundation

- Added a WordPress-owned Asset Engine database service as the single source of truth for artist artwork.
- Added the My Artwork Artist Portal page and navigation item.
- Added secure create, edit, list, and remove workflows scoped to the logged-in artist.
- Added image uploads through the WordPress Media Library.
- Added title, description, medium, dimensions, verified optional price, and lifecycle status fields.
- Added artwork summary metrics and explicit Price unavailable and Image unavailable states.
- Preserved Amelia, Opportunity Engine, CRM, Class Requests, Business Intelligence, and all 7.0.1 behavior.
- Established the foundation for future website publishing, inventory, sales, and artwork reporting without duplicating asset data.

# 7.0.1 — Recurring Class Discovery Hotfix

- Updated My Classes to consume the shared appointment-first Class Discovery service.
- Restored verified future dates from assigned Amelia service records when appointment rows do not yet exist.
- Preserved real appointment enrollment, capacity, booked value, and student links when available.
- Added a safe roster-unavailable state for service dates that Amelia has not converted into appointments yet.
- Preserved Heather Skinner, Jessica Wyant, glassblower, identity, Class Requests, and Platform Foundation behavior.

# 7.0.0 — Platform Foundation

- Added a central Identity Service with explicit mapping priority, email fallback, and request-level caching.
- Added a stable Opportunity Gateway over the verified Opportunity Engine.
- Added shared Settings, Notification, and Activity service boundaries.
- Migrated Artist Portal identity resolution and Class Requests to the shared services.
- Preserved existing QR, portal, opportunity, CRM, timeline, and Amelia behavior.
- No database migration or visible workflow change is required for this foundation release.

# 6.9.0 — Waitlist Engine Completion

- Completed the Elev8-owned pre-schedule Class Requests workflow without pulling class choices from Amelia.
- Added demand-by-class summary cards with people, requested seats, last request date, and verified potential value.
- Added full CRM editing for status, follow-up date, notes, and contacted timestamp.
- Added follow-up-needed counts and visual highlighting for new or due requests.
- Added class, status, and keyword filters for the customer queue.
- Added duplicate prevention for matching email or phone within the same class idea.
- Added verified potential-value calculations that display Unavailable when any class lacks an estimated price.
- Preserved Opportunity Engine ownership, artist mapping, activity timeline, and Amelia as a separate scheduling integration.

## 6.8.1 — Class Requests Artist Mapping Hotfix

- Updated Class Requests to use the shared Artist Portal artist resolver.
- Explicit WordPress-to-Amelia Artist Mapping now takes priority.
- Preserved email fallback for mapped artists whose direct user meta is unavailable.
- Applied the same identity resolution to Class Request permissions and opportunity access checks.
- Fixed connected artists incorrectly seeing the “not connected” message.

# Changelog

## 6.8.0 — Class Requests & Teacher Experience
- Renamed the teacher-facing Waitlist to Class Requests.
- Removed Amelia class discovery from the Class Requests page.
- Added existing Opportunity Engine class-idea selection.
- Added inline creation of a brand-new class suggestion while recording customer interest.
- Added a teacher-only class idea proposal form that does not require a customer.
- Reused Opportunity Engine customer interest, CRM status, and activity timeline data.
- Preserved the existing portal URL for backward compatibility.

## 6.7.3 — Neve Portal Header Hotfix

- Added a portal-only Neve header override that runs only on verified Elev8 OS teacher pages.
- Added support for Neve Header Builder wrapper variants including `hfg_header`, `header-main`, `nv-navbar`, and related containers.
- Preserved the Elev8 OS Artist Portal navigation as the single teacher-facing menu.
- Kept all 6.7.2 waitlist redesign and 6.7.1 Students page improvements intact.

## 6.7.2 — Artist Waitlist Redesign

- Rebuilt the teacher-facing waitlist as a responsive Artist Portal workspace.
- Added verified summary cards for waiting customers, requested seats, upcoming classes, and contacted customers.
- Replaced the cramped WordPress table with customer cards, direct email/phone actions, and clear status controls.
- Added an administrator artist-preview selector without exposing artist selection to normal teacher accounts.
- Added a single-menu portal shell so the public theme navigation no longer duplicates Elev8 OS navigation on portal pages.
- Preserved existing waitlist permissions, Amelia class verification, status updates, and deletion behavior.

# 6.7.1 — Artist Portal Polish

- Fixed Artist Portal navigation styles not loading on Students and Waitlist pages.
- Restyled the Students page as a teacher-facing roster workspace.
- Added polished header, class selector, summary cards, search, roster table, and mobile student cards.
- Hid the duplicate WordPress page title on the generated Students portal page.
- Preserved verified Amelia roster data and all Artist Dashboard 2.0 functionality.

# 6.7.0 — Artist Dashboard 2.0

- Rebuilt the artist home dashboard around verified teacher priorities.
- Added reusable My Classes dashboard snapshot service.
- Added upcoming classes, enrolled students, available seats, and booked value cards.
- Added next-class panel and direct links to classes, students, website preview, and website editor.
- Added artist checklist and clear Unavailable states for unverified data.
- Preserved all existing portal, Opportunity Engine, CRM, timeline, waitlist, and Amelia behavior.

# 6.6.1 — Customer Save Clarity Hotfix

- Renamed the CRM row action from **Save Follow-up** to **Save Customer Changes**.
- Clarifies that CRM status, follow-up date, notes, and contact timestamp are saved together.
- Preserves Activity & Opportunity Timeline logging for every saved customer change.

# Elev8 OS Changelog

## 6.6.0 — Activity & Opportunity Timeline

- Added a WordPress-owned opportunity activity table and reusable activity service.
- Added permanent timeline entries for opportunity creation and updates.
- Added timeline entries when customer interest is added, updated, contacted, or deleted.
- Added field-level summaries for opportunity and CRM changes.
- Added automatic backfill of creation events for existing opportunities.
- Added the Activity & Opportunity Timeline to each Opportunity Detail page.
- Preserved Business Intelligence as the source for demand and revenue metrics.

## 6.5.1 — Customer Interest Database Migration Hotfix

- Increased the Opportunity Engine database schema version to `1.1.0` so CRM columns are added to existing installations.
- Fixed customer-interest submissions failing silently when the live table had the pre-CRM schema.
- Added a visible error notice when an interest record cannot be saved.
- Preserved all 6.5.0 Customer Interest CRM features.

## 6.5.0 — Customer Interest CRM

- Added CRM statuses for every customer interest record.
- Added follow-up dates and last-contacted tracking.
- Added one-click email and phone links.
- Added editable customer notes directly in the opportunity detail view.
- Added a Mark Contacted action that records the verified contact time.
- Kept all customer data owned by the Opportunity Engine and available to Business Intelligence.
- Preserved the 6.4.0 Opportunity Detail View, Class Demand Manager, Waitlists, Artist Portal, and Amelia integration.

# Changelog

## 6.4.0 — Opportunity Detail View

- Added a dedicated Opportunity Detail View for every class idea.
- Added opportunity-level metrics for people interested, requested seats, potential revenue, and teacher readiness.
- Added a trusted opportunity summary with status, pricing, duration, preferred schedule, teacher information, supplies, and internal notes.
- Added contextual next-action guidance without presenting unverified data as fact.
- Moved customer interest management into the opportunity detail workflow.
- Added an explicit disabled “Convert to Amelia — Planned” action until safe creation, validation, notification, and rollback support exists.
- Preserved all existing Opportunity Engine, waitlist, Artist Portal, Business Intelligence, and Amelia functionality.

# 6.3.1 — Class Demand Management Controls

- Added safe deletion of class ideas and their attached customer interest records.
- Added deletion of individual customer interest records.
- Added confirmation prompts and success notices for destructive actions.
- Preserved all 6.3.0 Opportunity Engine and Amelia waitlist behavior.
- Updated plugin version to 6.3.1.

# 6.3.0 — Class Demand Manager

- Added the Opportunity Engine as a reusable Elev8 OS domain service.
- Added WordPress-owned opportunity and customer-interest tables with automatic upgrade handling.
- Added Class Demand Manager under Elev8 OS for creating and editing class ideas.
- Added customer interest tracking for classes that do not yet exist.
- Added teacher-needed, teacher assignment, scheduling preferences, pricing, duration, supplies, difficulty, and internal planning fields.
- Added demand metrics for people waiting, seats requested, classes needing teachers, and potential revenue.
- Routed Class Demand Manager intelligence through the centralized Business Intelligence service.
- Potential revenue displays Unavailable whenever interested customers lack verified estimated pricing.
- Preserved the existing Amelia waitlist and all 6.2.1 functionality.

# 6.2.1 — Hybrid Class Discovery

- Added one reusable class discovery service for waitlists.
- Uses real Amelia appointments first.
- Falls back to verified, artist-assigned service dates when appointments do not exist yet.
- Supports Heather's Sunbrew and Jessica's Sound Bowl schedules without inventing appointment IDs.
- Preserves live capacity and booked-seat data for appointment-backed classes.
- Validates synthetic service occurrences securely before saving a waitlist entry.

# 6.2.0 — Amelia Integrated Waitlists

- Replaced manual employee, class, date, and time entry with verified Amelia artist and class selectors.
- Added automatic service, appointment, date, time, capacity, enrolled-seat, and remaining-seat lookup.
- Added an artist filter to the administrator Waitlists screen.
- Added server-side validation that the selected class belongs to the selected artist.
- Added appointment IDs to Elev8 OS waitlist records while preserving existing Phase 1 entries.

## 6.1.0
- Added Waitlist Manager Phase 1 foundation.
- Added an Elev8-owned waitlist database table.
- Added administrator and artist waitlist screens.
- Added secure add, status-update, and remove actions.
- Added automatic Waitlist portal page management.
- Deferred class integration, recommendations, and automation to later milestones.


## 6.0.5 — Service Occurrence Parsing Hotfix

- Prevented expired one-time services from inheriting future dates from stale description content.
- Added service-scoped weekly date-range parsing for schedules such as `June 11 - 25 & July 9 - 30, 2026`.
- Added date-only upcoming occurrences when Amelia contains a verified date but no verified time.
- Preserved real appointment and event schedules as higher-priority sources.
- Kept Heather Skinner and Jessica Wyant schedules isolated to their assigned Amelia services.


## 6.0.4 — Service Schedule Isolation Hotfix

- Stopped treating Amelia assignment-table metadata dates as class schedules.
- Dates are now parsed only from the matching assigned service record.
- Prevented expired Herbal Mocktails from inheriting Sunbrew dates.
- Preserved support for Jessica Wyant's assigned Sound Bowl service and other zero-booking services.
- Added per-service schedule diagnostics.

# 6.0.3 — Amelia Service Date Accuracy Hotfix

- Keeps parsed dates attached to the Amelia service description they came from.
- Uses the primary Time & Location date to exclude expired services and stale copied dates.
- Parses all dates in the WordPress site timezone so 9:00 AM remains 9:00 AM.
- Restores the primary dated occurrence and removes duplicate service/date rows.

# 6.0.2 — Amelia Assignment Discovery Hotfix

- Replaced guessed provider-to-service table names with runtime Amelia schema discovery.
- Added compatibility for services that store assigned employees directly in JSON or serialized fields.
- Improved parsing of explicit service dates containing HTML, line breaks, and non-breaking spaces.
- Expanded administrator diagnostics with discovered assignment sources.

# Elev8 OS Changelog

## 6.0.1 — Upcoming Class Compatibility Hotfix

- Restored upcoming classes on the Artists admin screen when an Amelia service has scheduled dates but no customer bookings yet.
- Reads verified dated provider-to-service assignment rows when available.
- Adds a compatibility fallback that reads only explicit future dates written in an assigned Amelia service description.
- Added an administrator-only upcoming-class diagnostics panel showing detected Amelia sources and counts.
- Does not invent dates or create bookings.

## 6.0.0 — Admin Reorganization

- Renamed the confusing admin **Artist Portal** screen to **Artists**.
- Reorganized Elev8 OS admin navigation into a clearer daily-use order.
- Renamed the admin-only Artist Dashboard screen to **Artist Dashboard Preview**.
- Removed the duplicate legacy System Status menu entry while preserving the full System Inspector.
- Moved CEO Dashboard and Business Intelligence ahead of system and development tools.
- Kept the simple `develop` and `main` branch workflow unchanged.

## 5.9.0 — Portal Polish

- Added secure CSV downloads for artist class rosters.
- Added print-friendly student rosters.
- Added live roster search counts and cleaner roster actions.
- Improved class cards, action hierarchy, mobile layouts, and portal navigation.
- Preserved artist-scoped permissions and verified-data rules.


## 5.8.0
- Added the Artist Portal Students page and automatic portal-page management.
- Added class-specific Amelia student rosters with verified names, contact details, seats, booking status, and booking date when available.
- Added roster search and class selection.
- Added View Students actions to My Classes.
- Excludes cancelled and rejected bookings and displays Unavailable instead of guessing missing data.

# 5.7.0 — My Classes

- Activated My Classes in the Artist Portal.
- Added verified upcoming and recent past Amelia class dates.
- Added student counts, detected capacity, available seats, and booked value where supported.
- Added class booking-link actions and safe Unavailable states.
- Added the My Classes page to Portal Setup and automatic page repair.

## 5.6.0

- Added a two-column Manage My Website builder with an always-visible live preview.
- Bio, medium, specialties, experience, profile photo, cover image, gallery, social links, and booking button update instantly while the artist types.
- Preview uses the same saved profile fields as the public artist page and clearly identifies unsaved changes as preview-only.
- Added responsive behavior so the preview moves above the form on smaller screens.

## 5.5.0

- Added Artist Portal Setup under Elev8 OS.
- Stores and uses exact WordPress page IDs for Artist Dashboard, My Website, and Edit Website.
- Automatically discovers existing portal pages by saved ID, canonical slug, or shortcode.
- Automatically repairs missing shortcodes and creates missing portal pages.
- Added a one-click Check and Repair Portal Pages tool.
- Portal navigation now survives page title and permalink changes.

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
