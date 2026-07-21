## 13.8.1 — Clean Role Preview & Glass Manager Return Fix
- Role Preview now opens in a separate browser window.
- Clean Preview hides the WordPress administrator toolbar, sidebar, update notices, and other owner-only chrome so the preview matches the selected person's Elev8 OS application experience.
- Preview navigation preserves clean mode across dashboard and role-specific shortcuts.
- The universal header now routes My Dashboard through the Preview Context Service.
- Glass Manager Classes now returns to Glass Operations instead of the public home page.
- Existing preview notification suppression and centralized capability simulation remain intact.

## 13.8.0 — Team Communications & Smart Conversations

- Added a searchable recipient picker with multi-person selection and one-click team selection.
- Ensured owners and conversation managers such as Steve remain available as recipients even when they are not in a normal assignment group.
- Made Conversations respect Role Preview identity so previewing Becca, Jeff, Scott, Nick, or another role shows the recipients that person can actually message.
- Added file attachments to new conversations and replies, stored through the WordPress Media Library.
- Added conversation pinning to Business Memory for authorized managers and owners.
- Added unread Conversations to the shared Attention Center so CEO and manager dashboards can surface messages needing review.
- Preserved threaded replies, mentions, read/unread tracking, workspace links, and close-conversation controls.
- Kept notification delivery suppressed during Preview Mode.

## 13.7.1 — Role-Aware Teaching Calendar Hotfix

- Fixed Role Preview so My Classes uses the previewed Elev8 OS identity instead of the authenticated owner account.
- Added a Glass Manager class scope that shows all verified Amelia glassblowing classes rather than the owner’s personal teaching schedule.
- Added teacher names to class cards and calendar events.
- Added an All Glass Teachers filter for Glass Managers.
- Added Glass Classes to the Glass Manager preview shortcuts.
- Granted the Glass Manager role class-view access through the centralized Access Service.
- Preserved Amelia as the scheduling and booking source of truth; service scope is resolved from configurable service IDs or verified glassblowing service/category keywords.
- No hardcoded Amelia service IDs or WordPress user IDs were added.

## 13.7.0 — Teaching Calendar & Booking View
- Added shared Agenda, Week, and Month calendar views to My Classes for Glass Managers, teachers, and teaching artists.
- Added day-of-week labels to calendar events, class detail cards, and the Next Class dashboard card.
- Added verified Amelia-backed booked, capacity, and seats-left context directly inside calendar events.
- Added previous/next period navigation, Today shortcuts, phone-friendly agenda behavior, and direct links from calendar events to class details.
- Preserved Amelia as the scheduling and booking source of truth and continued displaying Unavailable when values cannot be verified.


## 13.6.0 — Universal Role Preview Mode
- Replaced the single Artist Dashboard Preview concept with a founder-only Role Preview tool for CEO, Shop Manager, Glass Manager, Glassblower, Artist, Teacher, Event Host, Volunteer, and Retail Employee experiences.
- Added role-filtered user selection, one-click dashboard or public-profile preview, a persistent Preview Mode banner, workspace jump links, and one-click exit.
- Integrated preview identity with the centralized Access Service and role-aware dashboard renderer while preserving the owner’s WordPress authentication.
- Added a universal Preview action to the Elev8 OS application header and user menu.
- Suppressed WordPress email and Elev8 OS notification delivery during preview requests to prevent accidental test messages.
- Added responsive founder tooling for faster desktop and phone testing without passwords, logout, or Incognito windows.
# Changelog

## 13.5.0 — Repair & Memorial Production Engine

- Added repair intake with damage description, evaluation, quote, approval, payment, risk and intake-photo tracking.
- Added memorial intake with secure storage, container description, amount received/used/returned and required reconciliation before completion.
- Added immutable memorial chain-of-custody events with user, time, location, notes and optional attachment.
- Added case-specific workflow statuses while preserving Glass Operations jobs, production lines, QC, pay and assignments as sources of truth.
- Added reusable customer-update templates for receipt, quote/ashes confirmation, production, QC and release.
- Added Glass Manager attention signals for incomplete custody, overdue repair approval and missing ashes reconciliation.
- Added multi-photo uploads through the WordPress Media Library and preserved Universal Activity records.

## 13.4.0 — Glass Manager Operational Home

- Rebuilt the Glass Manager dashboard as a role-specific operational briefing instead of a passive job list.
- Added a rule-based Studio Pulse using overdue and urgent jobs, ready work without assignments, QC/rework, and pending pay approvals.
- Added a prioritized Needs Your Attention queue with direct actions into source jobs, the Production Board, and Pay Sheets.
- Added verified production KPIs for open jobs, overdue and due-today work, unassigned work, QC/rework, pending pay, and jobs ready for pickup or shipping.
- Added live Glassblower Workload summaries and a data-driven Before You Leave studio closeout checklist.
- Added manager Quick Actions for job creation, board assignment, pay review, roster management, Production Catalog, and cremation-order import.
- Preserved Production Catalog, Production Jobs, Glassblower dashboards, automatic pay sheets, Production Board, and QR Label Sheet Composer.

## 13.3.0 — QR Label Sheet Composer

- Added batch selection for all or selected artwork QR labels.
- Added full letter-sheet layouts for six 3 × 3 labels or sixteen 3 × 1 labels.
- Added Select All, Clear All, selection totals, copies-per-label, and multi-page output.
- Added the same sheet composer to the artist Print Center and the administrator Gallery Print Center.
- Preserved tracked artwork URLs, ownership validation, single-label printing, Production Board, Production Jobs, and pay sheets.

## 10.12.7 — Operational Home
- Added a shared Dashboard Service as the single source for verified role-aware operational counts.
- Added CEO Operational Home sections for Needs Attention, My Work, Team Work, Reservations, and Event Applications.
- Added role-aware Mobile Home priority summaries so users immediately see what requires action.
- Added explicit Unavailable states for sales, upcoming events, and notifications until trusted shared services are connected.
- Preserved existing dashboard navigation and work, reservation, and application records.


## 10.12.6 — Workflow & Tasks Foundation
- Added reusable Work Service and role-aware My Work / Team Work interfaces.
- Added task ownership, due dates, priorities, statuses, notes, source links, duplicate protection, and activity history.
- Added an Elev8 Takeover workflow template and application workspace with automatic work generation when approved or scheduled.
- Added CEO and Mobile Home work ticklers for overdue, due-today, active, and unassigned work.
- Added centralized work capabilities through the Access Service.

## 10.12.5 — Event Applications & Elev8 Takeover Intake
- Added reusable Event Applications workflow and public Elev8 Takeover form.
- Creates/updates CRM relationship and person records, Unified Intake items, activity history, and owner notifications.
- Added role-capability protected admin review, assignment, follow-up, and status workflow.
- Added CEO Dashboard tickler for event applications.

## 10.12.4 — Unified Reservations & Dashboard Ticklers
- Renamed the Bingo Reservations administration experience to Reservations while preserving existing URLs and data.
- Added reusable reservation types for Bingo, Open Mic, classes/workshops, and other events.
- Added simplified reservation statuses, comment attention badges, assignment support, and role-aware reservation visibility.
- Added reservation attention and upcoming-event ticklers to the CEO Dashboard and role-aware Mobile Home cards.
- Added centralized reservation capabilities while keeping legacy Bingo capability compatibility.

# 10.12.3 — Manager Sales Fields and Optional Follow-Up

- Added optional structured sales fields for HEMP and Elev8 Glass Gallery to the Manager Operations Log.
- Made “What still needs to be completed?” optional and removed the instruction to enter “None.”
- Preserved existing Manager Operations Log submissions and field data.

## 10.12.2 — Centralized Permissions & Access Foundation

- Added one capability-driven Elev8 OS Access Service as the source of truth for business access.
- Fixed Shop Manager access to the Manager Operations Log so card visibility, form access, and submission permission use the same capability.
- Added Elev8 Event Staff, Elev8 Owner, Elev8 Artist, and Elev8 Volunteer role foundations.
- Added reusable role-to-capability mapping, per-user allow/deny overrides, inactive-user support, and assignment eligibility.
- Migrated Mobile Home, Check-In Center, Daily Operations, Bingo, Unified Intake, Artist Portal administration, and Glass Operations to the centralized service.
- Replaced the Unified Intake assignment list with grouped operational users only.
- Preserved legacy manager, Open Mic DJ, editor, author, and contributor access while capabilities are migrated.

# 10.12.0 — Unified Intake Platform Foundation

- Expanded the shared Activity Service into an immutable cross-module timeline for people, intake records, CRM, Business Memory, and future intelligence.
- Added a reusable `elev8_os_unified_intake_submit` integration boundary so future forms can create workflow items without duplicating intake business logic.
- Added idempotent origin-record matching to prevent duplicate intake cards when source hooks run more than once.
- Added automatic activity records when intake items are created or workflow fields change.
- Added an activity timeline directly to every Owner Intake Dashboard card.
- Preserved the existing compact Person activity history for backward compatibility while writing new activity records through the shared service.
- No existing source records are modified; Bingo and Daily Operations remain authoritative for their own details.

# 10.12.0 — Stabilization and Unified Intake

- Added an Owner Intake Dashboard with Trello-style workflow columns: New, Reviewed, Contacted, In Progress, Completed, and Archived.
- Unified public Bingo reservations and Check-In Center submissions into one actionable queue.
- Added assignments, follow-up dates, internal notes, source tracking, and contact links.
- Added shared Person records keyed by email with cross-module activity history.
- Added owner email notifications linking directly to new intake records.
- Preserved existing Glass Operations work while pausing its expansion.

## 10.11.2 — Bingo Reminder Opt-In

- Bingo reservation email updates are selected by default while remaining optional.
- Updated the consent wording to clearly cover upcoming Bingo Nights and other Elev8 Arts events.

## 10.11.1 — Bingo Reservations

- Added the `[elev8_bingo_reservation_form]` public shortcode.
- Stores reservations in the WordPress database with guest, date, accessibility, notes, and consent details.
- Added an Elev8 OS Bingo Reservations dashboard with filtering, guest totals, contact links, and check-in statuses.
- Added spam protection, nonce validation, input validation, and automatic upcoming first/third Friday date choices.

## 10.11.0 — Glass Operations Foundation
- Added role-aware Glass Manager Dashboard for Elev8 Premier.
- Added production and cremation job queues, assignment, status, due dates and customer details.
- Added blower work recording and manager payout approval.
- Added WooCommerce cremation-order discovery/import.
- Added reusable Glass Manager and Glass Blower WordPress roles/capabilities.

## 10.10.2 — Art Walk Vendors & Relationship Logos

### Added
- Imported 117 unique records from the Art Walk Vendors worksheet.
- Classified records with a food email as Food Vendor and all other imported records as Vendor.
- Added reusable logo storage to every Relationships CRM record using the WordPress Media Library.
- Added editable social media, product/service description, consignment interest, contact, phone, and email fields.
- Added relationship editing and type filtering from the Relationships screen.
- Added vendor and food-vendor dashboard counts.

### Data handling
- Duplicate vendor submissions are merged by matching email, phone, or normalized relationship name before seed creation.
- Vendor imports use a separate one-time seed marker, so existing dispensary records remain intact.
- Existing CRM records are updated only where fields are missing; Food Vendor classification takes priority when applicable.

# Elev8 OS Changelog

## 13.5.0 — Repair & Memorial Production Engine

- Added repair intake with damage description, evaluation, quote, approval, payment, risk and intake-photo tracking.
- Added memorial intake with secure storage, container description, amount received/used/returned and required reconciliation before completion.
- Added immutable memorial chain-of-custody events with user, time, location, notes and optional attachment.
- Added case-specific workflow statuses while preserving Glass Operations jobs, production lines, QC, pay and assignments as sources of truth.
- Added reusable customer-update templates for receipt, quote/ashes confirmation, production, QC and release.
- Added Glass Manager attention signals for incomplete custody, overdue repair approval and missing ashes reconciliation.
- Added multi-photo uploads through the WordPress Media Library and preserved Universal Activity records.

## 10.10.0 — Relationships & Community Outreach
- Added a CRM-backed Relationships directory seeded with 123 cleaned dispensary records.
- Added relationship profiles, flyer permission, contact fields, notes, follow-up state, map directions, and visit history.
- Added outreach campaigns with assigned locations, delivery status, flyer quantities, and field notes.
- Added role-aware Mobile Home cards for managers, retail employees, artists/teachers, and open-mic DJs.
- Added Elev8 Teacher, Elev8 Open Mic DJ, and Elev8 Retail Employee roles.
- Preserved original spreadsheet notes and removed duplicate seed records.

# Elev8 OS Changelog

## 10.8.0 — Mobile Role-Aware Home

- Added an automatically managed `/elev8-app/` page with a mobile-first Elev8 OS home screen.
- Added permission-aware launch cards so owners, managers/employees, and mapped artists see only relevant tools.
- Added one-tap access to CEO Dashboard, quick Business Memory capture, Business Memory search, Daily Operations, Artist Dashboard, classes, Gallery Operations, and Check-In Center where permitted.
- Added mobile app metadata and an Add to Home Screen prompt so the page can be launched from a phone like an app.
- Preserved WordPress authentication and existing Elev8 OS permissions as the source of truth.

## 10.7.0 — Business Memory Foundation
- Added structured Business Memory records for conversations, events, decisions, incidents, and observations.
- Added participants, location, objective summary, decisions, action items and owners, follow-up dates, attachments, priority, tags, and record status.
- Added searchable Memory Feed, detailed record timeline, Open Follow-Ups, overdue action tracking, recurring-signal detection, risk highlighting, and rule-based next-step recommendations.
- Integrated Business Memory into Daily Operations Intelligence as a first-class CEO workspace.

## 10.6.3
- Made the selected CRM relationship unmistakable with a purple background, strong left border, selected badge, and matching avatar.
- Updated the relationship detail panel to visually connect with the selected customer.
- Renamed Relationship Profile to Customer Profile.
- Rebuilt quick actions with polished Send Email, Call, and Add Note buttons.

# Elev8 OS Changelog

## 10.6.2 — Private Elev8 Team Entry

- Kept all public check-in choices visible on the main Check-In Center.
- Removed manager, employee, artist, maintenance, vendor, and event cards from the public-facing choice grid.
- Added one centered **Elev8 Team** button at the end of the public choices.
- Team members sign in before seeing the private operations forms.
- Added a dedicated team-only choice view with a clear return link to public check-ins.

## 10.6.1 — Manager Operations Log Polish and Steve Alerts
- Makes the duty-category choices optional and displays them as compact, aligned checkboxes for useful reporting without making the manager feel forced.
- Keeps the written work summary required as the primary report.
- Reduces optional text-area height so the manager form is easier to scan and complete.
- Renames owner-attention language to Steve, including the dedicated “Message for Steve” field.
- Automatically flags a manager log for attention and emails the WordPress administration email whenever “Message for Steve” contains text.
- Includes manager, date, location, work period, message, and a direct link to the complete Elev8 OS record in the notification.
- No database changes.

## 10.6.0 - Manager Operations Log
- Rebuilt the manager form around separate completed work periods.
- Made work location mandatory with no default selection.
- Added Elev8 Glass Gallery, HEMP, Elev8 Arts, errands, appointments, remote work, and other-location choices.
- Added required start and end times, concrete work summary, duties completed, and follow-up status.
- Added staff coaching, problems, customer issues, business improvements, and owner-attention fields.
- Added checkbox-group support for structured duties and improved mobile styling.
- Updated built-in operation templates automatically while preserving custom templates.

## 10.5.5

- Added a complete public Volunteer or Get Involved form that saves directly into Elev8 OS Business Memory.
- Added a dedicated Suggest a Class form with class topic, schedule, interest level, and potential teacher information.
- Removed the duplicate Elev8 Check-In heading from the shortcode output so the WordPress page title appears only once.
- Added the new forms to the public Check-In Center and QR/link manager.

## 10.5.4

- Fixed CEO Command Center access by routing Business Memory, Class Requests, and Opportunities through the registered CEO Dashboard page.
- Removed the duplicate CEO workspace tab row; the Command Center cards are now the primary navigation.
- Registered and initialized the Opportunities module and its required services.
- Included the Daily Operations module in the source update to ensure Business Memory is available after replacement.

## 10.5.3 — CEO Navigation Hotfix

- Fixed the CEO Business Memory link by keeping Daily Operations registered and visible in the WordPress sidebar.
- Fixed the CEO Class Requests link to use the actual registered Class Demand page slug.
- Kept both tools accessible from the CEO Command Center while preserving direct sidebar access during testing.

## 10.5.2 — Check-In Center

- Added a public `/checkin/` portal created automatically by Elev8 OS.
- Added public Art Walk, Open Mic, customer, class, and idea check-ins.
- Added role-protected manager, employee, artist, maintenance, vendor, and event check-ins.
- Added direct-link and QR management under Elev8 OS → Check-In Center.
- Added guest contact capture, invitation consent, immediate thank-you email, attachments, spam honeypot, and rate limiting.
- Routed every submission into the existing Business Memory and CEO operational intelligence system.
- Preserved existing custom templates while adding new default templates during upgrades.

## 10.5.1 - CEO Workspace Navigation
- Added a CEO command center with clear workspace tabs and launch cards.
- Moved owner access to Daily Operations Intelligence under the CEO experience.
- Kept the Daily Operations menu available to employees while removing duplicate sidebar clutter for administrators.
- Preserved all existing direct page URLs and functionality for backward compatibility.

## 10.5.0 - Daily Operations Intelligence
- Added role-based operating log templates and structured submissions.
- Added searchable operational memory, attachments, owner attention flags, and workflow statuses.
- Added Daily Executive Brief, rule-based recommendations, and 30-day signal radar.
- Added custom template creation for future business roles.

# Elev8 OS Changelog

## 10.4.10 - 2026-07-19

### Fixed
- Preserved the display-stable 10.4.3.1 public-site rendering.
- Allowed linked artists to open the real public homepage while logged in.
- Prevented Ultimate Member's homepage access callback from redirecting the front page to `/user/?redirect_to=...`.
- Scoped the compatibility change only to logged-in linked artists on the WordPress front page.
- Kept normal login redirects, Artist Dashboard routing, and Ultimate Member profile behavior unchanged.

## 10.4.3.1 — Direct Public Home Diagnostic Test

- Built directly from the display-stable 10.4.3 release.
- Removes the `wp_redirect` interception used for the public-home query flag.
- Sends the artist-page logo directly to the real WordPress homepage URL.
- Makes no other dashboard, theme, layout, or Ultimate Member changes.

## 10.4.3 — Public Website Logo Exit Fix

- Fixed the public artist-page logo sending logged-in artists back to the Artist Dashboard.
- The logo now points to the real Elev8 Arts homepage with an explicit public-site intent flag.
- Elev8 OS suppresses dashboard/profile redirects only for that intentional homepage visit.
- Normal artist login and Ultimate Member profile redirects still lead to the private dashboard.
- No database schema changes.

## 10.4.2 — Teacher Proposal Visibility

- Added a dedicated Artist Submissions section to Class Requests so teacher ideas appear even with zero customer requests.
- Added Class Idea Pipeline metrics and recent teacher proposals to the CEO Dashboard.
- Added direct owner links from proposals to the Class Demand detail record.
- Preserved Artist Growth Center as a verified linked-artist performance view; it remains empty until WordPress users are mapped to Amelia artists.
- No database schema changes.

## 10.4.1 - Class Idea Center public experience
- Rebuilt the Teach or Suggest a Class page around the native Elev8 OS Class Idea Center.
- Removed the Google Form workflow from the public experience.
- Added a polished purple, lavender, teal, and white landing page with customer and artist paths.
- Added integrated class categories, process guidance, FAQs, and stronger Elev8 OS opportunity messaging.
- Preserved customer demand capture, artist proposals, duplicate protection, rate limiting, and Amelia ownership boundaries.

# Elev8 OS Changelog

## 10.4.0 — Class Idea Center

- Added a public Class Idea Center at `/teach-or-suggest-a-class/`.
- Customers can join interest for an existing class idea or suggest a new class.
- Logged-in Elev8 artists can submit class proposals from the same public page.
- Public submissions feed the existing Elev8 OS Opportunity and Class Requests systems.
- Added demand-friendly fields for requested seats, preferred days/times, contact details, class level, duration, price, supplies, and internal notes.
- Added duplicate protection, a honeypot, basic rate limiting, consent capture, and public success/error messaging.
- Added a purple, lavender, teal, and white responsive public design.
- Amelia remains the trusted scheduling and booking system; ideas are not published automatically.

## 10.3.2 — Class-Led Email Campaigns & Portal Exit Fix

- Added an upcoming-class picker to Email Marketing using verified Amelia assignments.
- Reuses each class name, date, flyer image, booking destination, and available-seat data when present.
- Added a purple/lavender/teal live email preview with the selected class flyer.
- Sent campaign emails now use the same branded visual layout shown in preview.
- Added campaign fields for promoted title, class service ID, and featured image.
- Removed the weak generic Copy Social Version workflow pending proper channel-specific publishing.
- Growth Studio class promotion now opens the class-led Email Marketing workflow.
- Elev8 Arts Home now opens in a separate tab so artists can leave the private portal without losing their dashboard.
- Plugin version increased from 10.3.1 to 10.3.2.

## 10.3.1 — Guided Publishing & Preview Polish

### Added
- Added a three-step Content Studio guide explaining how templates move from shared starting points to personalized campaigns.
- Added direct editing after an artist adds a shared template to their personal library.
- Added social-ready copy support with a one-click Copy Social Version action for Facebook and Instagram workflows.
- Added a live recipient-facing email preview inside Marketing Center.
- Added live subject, message, and promotion-link updates while composing an email.

### Changed
- Replaced the visually broken opportunity links with consistent dark-purple pill buttons and white text.
- Clarified that the Content Studio campaign builder currently creates branded email campaigns.
- Reworded Use Template to Add to My Library & Edit.
- Replaced remaining pink/blue growth styling with the approved purple, lavender, teal, and white system.
- Plugin version increased from 10.3.0 to 10.3.1.

### Notes
- Direct publishing to Facebook and Instagram still requires each artist to authorize their social account through the platform APIs. This release adds the safe copy-and-paste workflow and prepares the interface for future connected publishing.
- No database changes.

## 10.3.0 — Elev8 Design System 1.0

### Added
- Introduced shared purple, lavender, teal, white, ink, border, and shadow design tokens for the Artist Dashboard.
- Added a polished, reusable primary dashboard action style with guaranteed white text on dark purple buttons.

### Changed
- Redesigned Business GPS from a dark navy panel into a light purple, lavender, and teal business-health experience.
- Converted the Business Health score into a circular teal-and-purple visual treatment.
- Unified dashboard cards, borders, shadows, progress indicators, success panels, opportunity cards, and timeline icons.
- Rebuilt the Content Studio Create Template action as a full-size teal CTA with white text and a plus icon.
- Updated Content Studio hero styling to match Growth Studio.
- Plugin version increased from 10.2.6 to 10.3.0.

### Database changes
- None.

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

## 10.2.6
- Added a reusable Artist Shortcut Launcher to the Artist Dashboard.
- Added a compact desktop shortcut bar that appears after the normal navigation scrolls out of view.
- Added a mobile floating Shortcuts button with an accessible popup panel.
- Centralized shortcut destinations through the existing Artist Portal navigation data.
- Added quick access to Growth Studio, Artwork, Classes, Website, Print Center, Students, and Class Requests.
- Applied the purple, lavender, teal, and white Growth Studio palette to the launcher.
- No database changes.

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

## 10.2.6
- Added a reusable Artist Shortcut Launcher to the Artist Dashboard.
- Added a compact desktop shortcut bar that appears after the normal navigation scrolls out of view.
- Added a mobile floating Shortcuts button with an accessible popup panel.
- Centralized shortcut destinations through the existing Artist Portal navigation data.
- Added quick access to Growth Studio, Artwork, Classes, Website, Print Center, Students, and Class Requests.
- Applied the purple, lavender, teal, and white Growth Studio palette to the launcher.
- No database changes.

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
