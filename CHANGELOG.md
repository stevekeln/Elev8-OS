
## 20.0.0 - Production Operations Workspace Foundation

- Added the reusable Production Operations Workspace as a governed read-and-coordinate layer over authoritative operational records.
- Added the first Glass configuration using existing Glass Operations jobs, assignments, status, due dates, and progress.
- Added production metrics for ready, running, waiting, blocked, late, quality review, and completed-today work.
- Added filtered queue management and governed status/assignment updates through the existing Glass Operations service.
- Registered the Production portal page, command-palette entry, admin workspace, and shared application-shell navigation.
- Recorded ADR-0044: Production is a configurable workspace, not a new engine or duplicate production ledger.
## 19.10.0 — Credential Requirement and Renewal Workflow Governance

### Added
- Credential and training evidence requirements on canonical Universal Work Items.
- Explainable owner-readiness checks showing matched, missing, and expired evidence.
- Duplicate-protected credential renewal Work Items created through the shared Operations Engine.
- Expired evidence receives urgent renewal priority while upcoming expiration uses high priority.
- Safe evidence boundaries that prohibit passwords, secret keys, access codes, license keys, and full credential numbers.

### Architecture
- Accepted ADR-0043: credential requirements are Workflow evidence, not permission or professional licensing authority.
- Reused bounded Credential Evidence References and Universal Work rather than creating an HR, certification, or parallel task system.
- Preserved human assignment and handoff acknowledgement boundaries.

## 19.8.0 — Team Availability Calendar & Skill Verification Governance

### Added
- Recurring weekly coordination availability windows using explicit day and time ranges.
- Conflict-aware handoff suggestions that compare Work Item due dates with recurring availability evidence.
- Manager-confirmed skill evidence for declared coordination skills, including reviewer, timestamp, and note.
- Verified-skill boosts in explainable handoff-fit scoring while preserving declared skills as separate evidence.
- Team Coordination controls for personal recurring calendars and operational-leader skill verification.

### Architecture
- Accepted ADR-0041: recurring availability and skill verification are bounded coordination evidence, not attendance, payroll, leave, booking availability, access control, or professional certification.
- Reused Universal Work Items, Organization assignments, Team Capacity, and acknowledgement-based handoffs.
- No HR, scheduling, certification, staffing, or automatic assignment system was introduced.

## 19.7.0 — Team Availability & Skill-Aware Coordination

### Added
- Personal coordination availability states with optional end dates and notes.
- Configurable skill relationships for assignment-eligible people without changing WordPress roles or permissions.
- Work Item skill requirements used only to explain handoff fit.
- Skill-, availability-, Organization-assignment-, and capacity-aware reassignment suggestions.
- Transparent fit scores and explanations while preserving acknowledgement-based handoffs.

### Architecture
- Accepted ADR-0040: availability and skills are advisory Organization evidence, not scheduling, certification, permission, or automatic assignment.
- Reused Universal Work Items, Organization assignments, capacity projections, and governed handoff requests.
- No separate staffing, scheduling, employee, or project-management system was introduced.

## 19.6.0 — Team Capacity & Handoff Governance

### Added
- Configurable per-person workload-capacity targets used only for planning and coordination visibility.
- Explainable capacity projections based on active, urgent, overdue, and blocked Work Items.
- Governed handoff requests that require acknowledgement before Work Item ownership changes.
- Accept and decline evidence for handoff requests, with decision notes and timestamps.
- Dependency-change notifications through the existing Communication Engine email boundary.
- Explainable reassignment suggestions for over-capacity owners without automatic assignment changes.

### Architecture
- Accepted ADR-0039: capacity is advisory policy and handoffs require acknowledgement.
- Organization owns configurable capacity policy; Operations owns handoff evidence; Workflow owns dependencies.
- Capacity scores and reassignment suggestions are read models and cannot modify operational priority or ownership.

## 19.5.0 — Team Coordination & Work Dependencies

### Added
- A managed Team Coordination portal workspace for workload visibility, waiting-on relationships, bottleneck detection, and recent handoffs.
- Governed Work Item dependency relationships with circular-dependency protection and open-dependency detection.
- Explicit Work Item handoffs that reuse the canonical Operations owner field while preserving assignment-transfer evidence and notes.
- Explainable bottleneck scoring based on open dependencies, blocked downstream work, urgency, and overdue state.
- Role-aware coordination visibility: operational leaders see their permitted team scope while other users see coordination around their own assigned work.

### Architecture
- Accepted ADR-0038: Team Coordination extends Universal Work Items rather than creating a separate project-management system.
- Workflow owns Work Dependency relationships; Operations owns assignment and Handoff evidence.
- Dependencies, workload projections, and bottleneck scores do not rewrite authoritative source records or automatically complete work.

## 19.4.0 — Focus Intelligence & Organization Policy Governance

### Added
- Explainable Focus Scores in the Today workspace with a visible breakdown of source evidence, severity, organization policy, and personal presentation feedback.
- Per-user usefulness feedback for focus items: Helpful, Already handled, and Not relevant.
- Organization Unit focus-policy weights for Work, Attention, Coaching, Conversations, severity, overdue work, due-today work, executive attention, and Pattern evidence.
- Stable, bounded presentation scoring that never changes the priority or state of authoritative source records.

### Architecture
- Accepted ADR-0037: focus ranking must be explainable and governed as a read-model policy.
- Organization policies and user feedback influence presentation only; they cannot close Work Items, dismiss Recommendations, or alter source evidence.
- Reused Organization, Intelligence, Operations, Communication, Analytics, and Experience boundaries without creating a new Engine.

# Elev8 OS Changelog

## 19.3.0 — IT Support Operations Capability

### Added
- A configurable IT Support workspace for reporting computers, internet, printers, devices, account access, software, security, setup, installation, website/email, and recurring technology issues.
- Configurable IT Support assignment without requiring a formal IT department.
- Technology incidents implemented as specialized Maintenance Records that automatically contribute Work Items, SOP evidence, escalation, service history, Observations, and Daily Assistant focus.
- Critical operational-impact handling for checkout, payments, internet, security, and other essential systems.
- Reporter and IT queue views, assignment email through the Communication Engine boundary, resolution evidence, and organization/location context.

### Architecture
- Accepted ADR-0036: IT Support is a configurable Operations capability, not a new Engine or parallel ticketing system.
- Reused Assets, Maintenance, Operations, Workflow, Communication, Organization, Automation, and Intelligence boundaries.
- Preserved Maintenance Record authority for service lifecycle and Universal Work Item authority for execution.

## 19.2.0 — Daily Assistant Preferences & Delivery Governance

### Added
- Personal Daily Assistant delivery preferences for enabled state, delivery hour, weekdays or every day, focus categories, reminder emphasis, and permitted channels.
- Governed hourly delivery scheduling through WordPress Cron and the existing Communication Engine email boundary.
- In-app delivery timestamps and optional email delivery without creating duplicate tasks, decisions, or business facts.
- A test-delivery control and visible last-delivery status inside the Today workspace.
- Preference-aware Daily Assistant projections that can include or exclude Work, Conversations, Attention, and Coaching.

### Architecture
- Accepted ADR-0035: Daily Assistant delivery is personal preference governance over a read model, not a notification, task, or automation authority.
- Reused the Communication Engine for email transport and Automation scheduling for delivery timing.
- Preserved all Operations, Intelligence, Conversation, Coaching, and source-record ownership boundaries.

## 19.1.0 — Proactive Daily Assistant

### Added
- A managed Today workspace at `/elev8-today/` that answers what each signed-in user should focus on now.
- A role-aware personal briefing combining permitted Attention items, assigned Work, unread Conversations, and Business Coaching.
- A ranked focus list with direct links to the governed source records.
- Personal start-of-day metrics for due work, overdue work, unread Conversations, and active coaching.
- Quick access to the user’s real role-based dashboard, Work, Conversations, Coaching, and Business Memory when permitted.
- Today navigation in the shared Elev8 OS application shell and command palette.

### Architecture
- Accepted ADR-0034: the Proactive Daily Assistant is a personal read model, not a second dashboard, task system, or Executive Brief.
- Preserved Operations, Communication, Intelligence, Identity, Organization, and access-control ownership boundaries.
- The Daily Assistant cannot create Work Items, approve Recommendations, alter Conversations, or rewrite source evidence.

## 19.0.0 — Business Coaching Engine Foundation

### Added
- A role-aware Business Coaching workspace available as a managed Elev8 OS portal page and administration view.
- Explainable coaching cards derived from governed Work Items, confirmed Patterns, and Recommendations.
- Role filters for owners, shop managers, glass managers, glassblowers, artists, teachers, event hosts, retail employees, volunteers, and team members.
- Personal coaching-card states: unread, read, pinned, needs follow-up, and dismissed.
- Evidence explanations and direct links back to Operations or Intelligence records.
- Coaching summary metrics and shared application-shell navigation.

### Architecture
- Accepted ADR-0033: Business Coaching is explainable guidance, not a new source of facts, recommendations, approvals, or work.
- Coaching owns only its personal presentation state; source intelligence and operations records remain authoritative.
- Reused the existing Observation, Pattern, Recommendation, Operations, Organization, Identity, Analytics, and Workflow boundaries.

## 18.12.0 — Administrator-Confirmed Migration Plans

### Added
- Durable hidden Plugin Migration Plan records managed from Elev8 OS → Compatibility.
- Administrator-confirmed ownership states for external authority, shared boundaries, Elev8 OS replacement plans, and retirement candidates.
- Migration-plan fields for capability ownership, authoritative data, target Elev8 OS Engine, data migration, pages and workflows, external dependencies, retirement blockers, Local rehearsal evidence, validation results, rollback instructions, and final approval notes.
- Migration stages from discovery through ownership confirmation, Local rehearsal, final approval, and retirement.
- Plan-completeness guidance and per-plugin links from dependency discovery.
- Migration plans included in the exported compatibility JSON report.

### Architecture
- Accepted ADR-0032: administrator confirmation governs plugin ownership and retirement planning.
- Preserved the read-only plugin boundary; this release cannot activate, deactivate, update, delete, or configure plugins.
- Reused the Integrations Engine compatibility workspace instead of creating a parallel migration system.

## 18.11.0 — Plugin Usage Discovery & Migration Readiness

### Added
- Read-only Platform Compatibility workspace under Elev8 OS administration.
- Installed-plugin inventory with active state, version, disposition, and migration-readiness guidance.
- Discovery of shortcodes and block markup used in up to 5,000 content records.
- Discovery of non-core database tables, scheduled cron hooks, registered blocks, and custom post types.
- Per-plugin evidence matching with known namespace aliases for important companion systems.
- Manual fresh-scan control, six-hour cache, and exportable JSON evidence report.

### Architecture
- Accepted ADR-0030: plugin retirement requires evidence and a tested migration boundary.
- Accepted ADR-0031: dependency discovery is read-only evidence, not permission to deactivate software.
- Preserved WordPress, WooCommerce, Amelia, SMTP, Google, and other authoritative ownership boundaries.
- No plugin is activated, deactivated, updated, deleted, or reconfigured by this release.

## 18.10.0 — Executive Learning Dashboard & Calibration Health

### Added
- Executive Learning Health view inside the governed Intelligence workspace.
- Organization-scope filtering for calibration evidence.
- Per-classification readiness for risk, opportunity, decision, achievement, follow-up, and information Recommendations.
- Explainable counts for measured positive, neutral, and negative outcomes.
- Missing-outcome visibility for approved Recommendations and completed Executive Decision follow-through.
- Calibration coverage percentage and leadership guidance for improving learning quality.

### Architecture
- Accepted ADR-0029: learning health is a governed read model, not a second scoring or outcome system.
- Preserved the existing three-outcome minimum and ±15-point confidence boundary.
- The dashboard cannot alter confidence, approve Recommendations, create Work Items, or rewrite Outcomes.

## 18.9.0 — Decision Learning & Confidence Calibration

- Added an explainable Decision Learning service that calibrates Recommendation confidence from measured Recommendation Outcomes and Executive Decision Outcomes.
- Calibration is organization-specific and classification-specific, requires at least three comparable measured outcomes, and is capped at ±15 confidence points.
- Preserved the original Pattern-derived confidence as the base score while exposing the calibrated score and a plain-language explanation in Intelligence Review.
- Excluded unknown and unmeasured outcomes from learning and prevented confidence history from authorizing work or rewriting source evidence.
- Registered the Business Graph relationship from measured outcomes to future Recommendation confidence.

## 18.8.0 — Executive Follow-through Completion & Decision Effectiveness

### Added
- Automatic completion synchronization from delegated-review and scheduled-follow-up Work Items back to their Executive Decision Follow-through records.
- Governed Executive Decision Outcome records for measuring effective, partially effective, unchanged, ineffective, or unknown results.
- Optional before/after metric evidence and executive effectiveness notes.
- Decision-effectiveness controls and completion state in the Executive Intelligence follow-through timeline.
- Executive decision effectiveness summary service for future scoring and learning.

### Architecture
- Accepted ADR-0027: execution completion and decision effectiveness are separate evidence.
- Recommendation-backed actions continue to use the existing Recommendation Outcome object rather than creating duplicate executive outcomes.
- Work Items remain authoritative for execution completion; Executive Decision Outcomes measure the leadership result.

### Database changes
- Added hidden `elev8_exec_outcome` posts for decision effectiveness not already governed by Recommendation Outcome.


## 18.7.0 — Executive Decision Follow-through

### Added
- Governed Executive Decision Follow-through records for acknowledged attention items.
- Four intentional follow-through paths: formal decision, delegated review, approved operational action, and scheduled follow-up.
- Delegated reviews and scheduled follow-ups create normal Operations Work Items with assignment, due date, and source traceability.
- Approved operational actions route through the existing Recommendation approval boundary and reuse its duplicate-protected Work Item.
- Executive follow-through timeline in the Executive Intelligence workspace.

### Architecture
- Accepted ADR-0026: executive attention may become follow-through only through an explicit leader action.
- Formal decisions remain governance evidence and do not create work.
- Delegated review and scheduled follow-up reuse the Operations Engine rather than creating a second task system.
- Recommendation execution continues to require the existing Recommendation approval path.

### Database changes
- Added hidden `elev8_exec_follow` posts for durable Executive Decision Follow-through records.


## 18.6.0 — Executive Brief Delivery & Attention Governance

### Added
- Configurable personal Executive Brief email delivery through the shared Communication Engine notification boundary.
- Daily or weekday delivery schedules with a configurable local delivery hour.
- Manual test delivery from the Executive Intelligence workspace.
- Executive attention acknowledgement, deferral, resolution, and reopening controls.
- Stable attention keys connecting governance decisions to the underlying Pattern or Recommendation.
- Executive decision timeline with user, date, status, notes, and defer-until evidence.
- Hourly reconciliation that delivers each enabled user no more than one brief per scheduled day.

### Architecture
- Accepted ADR-0025: executive attention governance is evidence about a leader's response to a read-model item; it does not rewrite the underlying intelligence.
- Scheduled summaries use the Communication Engine boundary and preserve Intelligence as the source of ranked evidence.
- Deferred and resolved items are removed from the actionable brief while their decision history remains auditable.
- Delivery policies are configurable per user and do not hardcode business names, recipients, or delivery times.

### Database changes
- Added hidden `elev8_exec_attention` posts for durable attention governance and decision timelines.
- Added user metadata for Executive Brief delivery preferences and last successful delivery time.


## 18.5.0 — Executive Intelligence

### Added
- Executive Intelligence view inside the governed Intelligence workspace.
- Explainable ranking of active risks and opportunities using severity, recurrence, trend, confidence, and governance state.
- Executive attention queue combining urgent Patterns, proposed Recommendations, and Outcomes awaiting measurement.
- Recommendation performance score and supporting explanations in the executive view.
- Executive Intelligence command-palette destination.

### Architecture
- Accepted ADR-0024: Executive attention is a ranked view over governed Business Graph evidence, not a new source of truth.
- Executive Intelligence does not create Observations, Patterns, Recommendations, Work Items, or Outcomes.
- Ranking never changes authoritative records or bypasses human review and approval.

### Database changes
- None. Executive Intelligence is a read model assembled from existing governed Intelligence objects.


## 18.4.0 — Recommendation Outcome Tracking

### Added
- Canonical Recommendation Outcome object linked to one approved Recommendation and its completed Work Item.
- Automatic outcome creation when recommendation execution reaches Completed.
- Human-governed outcome states: successful, partially successful, no measurable change, unsuccessful, and unknown.
- Optional before/after metric evidence and outcome notes in the Intelligence workspace.
- Explainable recommendation-performance score derived only from measured outcomes.

### Architecture
- Accepted ADR-0023: Intelligence learns from governed outcomes, not from assumed success.
- Recommendations, Work Items, and Outcomes remain separate Business Graph objects.
- Completing work records that execution occurred; a leader still determines whether the business result was successful.

### Database changes
- None. Outcomes use a private WordPress post type and post metadata.


## 18.3.0 — Recommendation Promotion & Governed Execution

### Added
- Canonical Intelligence Engine Recommendation object promoted from an acknowledged Pattern.
- Explainable recommendation evidence, confidence, expected benefit, suggested action, and suggested owner.
- Recommendations tab in the Intelligence Review workspace.
- Explicit leader decisions to keep proposed, approve execution, or reject.
- One linked Operations Work Item created only after approval; repeated approvals do not duplicate work.

### Architecture
- Accepted ADR-0022: Recommendations require explicit promotion and execution approval.
- Patterns, Recommendations, and Work Items remain separate Business Graph objects with distinct ownership.
- Approval never modifies the Pattern, supporting Observations, or authoritative source records.

### Database changes
- None. Recommendations use a private WordPress post type and post metadata.


## 18.2.0 — Cross-Source Pattern Detection

### Added
- Canonical Intelligence Engine Pattern object.
- Daily and on-demand pattern scans using confirmed and corrected Observations.
- Stable pattern fingerprints grouped by shared Business Graph object, meaningful tag, organization scope, and classification.
- Pattern frequency, first and last occurrence, confidence, severity, and trend metadata.
- Pattern Review tab inside the Intelligence Review workspace.
- Human controls to acknowledge, dismiss, resolve, or reactivate a Pattern.

### Architecture
- Accepted ADR-0021: Patterns summarize confirmed facts without becoming actions.
- Patterns retain supporting Observation IDs and never rewrite authoritative source records.
- Pattern detection does not automatically create Work Items.

### Database changes
- None. Patterns use a private WordPress post type and post metadata.

## 18.1.0 — Intelligence Review & Cross-Engine Observations

- Added the frontend Observation Review workspace inside the shared Elev8 OS portal shell.
- Added confirm, correct, dismiss, and return-to-review states with reviewer notes and audit metadata.
- Added filters for classification, severity, source engine, and review status.
- Added verified Observation contributors for Inventory Signals, Maintenance Records, Event Applications, Amelia booking decisions, and Conversations.
- Preserved authoritative ownership: contributors summarize source state and never duplicate inventory, maintenance, event, booking, or conversation records.
- Added the Observation Review command to the shared command palette for authorized leaders.

## 18.0.0 — 2026-07-22

- Added the canonical Observation object and Intelligence Engine service.
- Added idempotent source-key synchronization, classification, severity, confidence, tags, organization scope, related-object references, and query summaries.
- Added the Daily Operations Observation Contributor for manager, retail, artist, vendor, event, maintenance, and configurable operating logs.
- Explicit follow-up fields can create one stable Universal Work Item; informational observations do not create tasks.
- Added verified Observation counts, risks, and opportunities to the Daily Executive Brief.
- Added Business Graph relationships for observed facts and evidence.
- Accepted ADR-0019: Facts Precede Actions.

## 17.6.0 — 2026-07-22

- Added the canonical Maintenance Record service for equipment, facilities, asset repairs, preventive maintenance, inspections, and safety checks.
- Added the Maintenance Operations Contributor with shared Work Item, SOP evidence, approval, escalation, and completion contracts.
- Connected the existing Maintenance Log to the canonical maintenance source instead of creating a parallel task path.
- Added recurring service scheduling, daily due-date reconciliation, overdue priority escalation, and asset service-history queries.
- Added ADR-0018: maintenance condition and execution remain separate from authoritative asset and facility records.

## 17.5.0 — 2026-07-22

- Added the Inventory Signal service as the canonical Elev8 OS record for operational inventory exceptions.
- Added automatic WooCommerce low-stock and out-of-stock signal synchronization without copying product or stock authority.
- Added the Inventory Operations Contributor for low stock, receiving, cycle counts, discrepancies, and event inventory reservations.
- Added daily low-stock reconciliation and immediate synchronization when WooCommerce stock changes.
- Added shared execution contracts, approvals, escalation, and completion rules for inventory work.
- Added ADR-0017: inventory authority and inventory execution remain separate.

## 17.4.0 — 2026-07-22

- Fixed the shared recipient directory so active Organization Engine employees appear under Shop Employees even when their WordPress role has not yet received the legacy assignment capability.
- Centralized assignment eligibility so recipient selection and Work Item assignment use the same rule.
- Added the Event Operations Contributor for event application review, approval, planning, delivery, and follow-up.
- Replaced the legacy parallel Takeover workflow generator with the shared Operations Contributor adapter.
- Preserved Person and Relationship graph links on contributor-generated event Work Items.

## 17.3.0 — 2026-07-22

- Added the Amelia-backed Class Approval Operations Contributor.
- Pending Amelia class bookings now create or synchronize one stable approval Work Item.
- Approving, moving, or cancelling a class booking synchronizes the existing Work Item without duplicating Amelia data.
- Added teacher assignment mapping through the existing Amelia employee user mapping.
- Added configurable organization scope filtering for Amelia booking work.

## 17.2.0 — SOP Execution & Completion Evidence

### Added
- Added a reusable SOP Execution service attached to canonical Work Items.
- Added stable checklist completion state, approval evidence, approval notes, actor identity, timestamps, and an execution audit timeline.
- Added execution controls directly to the Universal Work Inbox for contributed operational work.
- Added contract reconciliation so contributor updates preserve compatible completion evidence instead of erasing operational history.

### Changed
- Contributed Work Items cannot be marked complete until every required checklist step and approval is satisfied.
- Work Item responses now expose a normalized execution state and completion readiness signal.

### Architecture
- Operations continues to own the Work Item; Workflow owns reusable SOP execution mechanics and evidence.
- Authoritative source records remain unchanged and are never copied into execution evidence.
- Added ADR-0013: execution contracts and execution evidence are separate architectural concerns.

## 17.0.0 — Operations Engine & Universal Work Inbox

### Added
- Added the canonical Operations Engine over the existing Work Item source, avoiding a second task system.
- Added a frontend `/operations/` workspace with My Work and Team Work inboxes.
- Added reusable operational work types for production, repairs, memorials, teaching, inventory, routes, maintenance, events, approvals, and general work.
- Added organization scope, requester, customer, start time, completion time, and operational type metadata to Work Items.
- Added a manager-friendly create flow, shared filters, operational metrics, and Universal Workspace links.
- Added read-only connected-system signals for production, repairs, memorials, class decisions, and operations logs.
- Added Operations navigation to the universal application shell and Command Palette.

### Architecture
- Operations owns operational execution records. Workflow supplies reusable states, approvals, dependencies, and completion rules.
- Existing production, repair, memorial, booking, commerce, and identity records remain owned by their authoritative engines or source systems.
- Dashboards contribute work to one inbox instead of creating separate task systems.
- Added ADR-0011: the Universal Work Item is the canonical operational execution object.

## 16.3.0 — Integration Engine Adapters & Organization Reliability

### Fixed
- Fixed the Organization Workspace fatal error caused by the organization file provider returning text instead of an attachment array.
- Organization workspaces now open safely and display assigned people through the shared Workspace Engine.
- Added a searchable organization person selector that filters WordPress users by display name, email address, or username.

### Added
- Added the first read-only Integration Engine adapters for WooCommerce products and orders.
- Added the first read-only Integration Engine adapters for Amelia bookings and classes.
- Added an Integration Engine diagnostics workspace showing connection status and authoritative record counts.
- Products, orders, bookings, and classes can now participate in Universal Workspaces as stable references without copying authoritative source data.
- Added organization-scope extension points for connected authoritative records.

### Architecture
- WooCommerce remains authoritative for products and orders.
- Amelia remains authoritative for appointments, services, and customer bookings.
- Elev8 OS owns only relationships, work, conversations, intelligence, and operational follow-through around those records.

## 16.2.0 — Business Graph Registry & Ownership Enforcement

- Added a canonical Business Graph object registry declaring owning engine, authoritative system, source type, organization scope, and architectural notes.
- Added a relationship registry defining permitted source and target object types for explicit graph links.
- Added a CEO-facing Business Graph workspace with object, engine, authority, and relationship diagnostics.
- Enforced the registry when new explicit relationships are created, preventing unregistered or invalid graph links.
- Added engine, authority, and organization-scope metadata to new relationship records without copying source records.
- Connected Workspace type labels and normalization to the shared Business Graph registry.
- Updated the Business Blueprint with ADR-0009 and the 16.2.0 development session.

## 16.1.1 — Organization Add-Unit Hotfix

- Fixed the Organization workspace Add Organization Unit button after an existing organization is selected.
- Added an explicit new-unit mode so creating another business, brand, location, department, or team never reopens the selected unit's edit form.
- Preserved existing organization records, assignments, hierarchy, and scoped access behavior.

# Elev8 OS 16.1.0 — Organization Engine Foundation

- Added configurable Business, Brand, Location, Department, and Team organization units.
- Added parent-child organization hierarchy and a CEO-facing Company Map workspace.
- Added scoped Person assignments with assignment type, responsibility, primary status, effective dates, and active state.
- Added Organization workspace support and Activity history.
- Added Organization-aware access foundation through `user_can_in_scope()`.
- Added Organization navigation and Command Palette access.
- Updated the Business Blueprint with ADR-0008 and the 16.1.0 development session.

## 16.0.0 - Business Blueprint Engine Foundation

### Added
- Added the canonical repository-level `BUSINESS_BLUEPRINT.md` governing Elev8 OS architecture and development.
- Added the Platform Constitution, engine registry, Business Graph registry, workflow registry, architecture decisions, roadmap, technical debt, open questions, and session protocol.
- Added a CEO-facing Business Blueprint workspace with section navigation, architecture summary, command-palette access, and Blueprint download.
- Added a bundled read-only Blueprint copy for installed plugin environments while keeping the repository file authoritative during development.

### Architecture
- Knowledge Engine: introduced the Blueprint as durable platform and development knowledge.
- Intelligence Engine: future architectural recommendations must consult the Blueprint before proposing implementation.
- Business Graph: connected Engines, Business Objects, Workflows, Architecture Decisions, Roadmap items, Technical Debt, and Development Sessions through one governing Knowledge object.
- Every development session must begin by reading the Blueprint and end by updating it.

### Open Questions
- Structured in-app editing and repository synchronization remain intentionally postponed.
- The canonical multi-business Organization Engine model is the next architectural milestone.

## 15.0.3 - Site-Wide Mobile Layout Guard

### Fixed
- Prevented the Elev8 Arts site, theme footer, and Elev8 OS workspaces from drifting horizontally or creating an off-screen mobile canvas.
- Added safe width constraints for Neve and common WordPress page, content, container, row, and footer elements.
- Added safe-area-aware mobile padding for the universal Elev8 OS header.
- Ensured long links, notes, table cells, and user-entered content wrap instead of widening the page.

### Preserved
- Intentional horizontal scrolling remains available inside production boards, intake boards, teaching calendars, and wide data tables.
- Existing theme styling, bottom navigation, role routing, class notifications, and Employee Guides remain unchanged.

### Architecture
- Added one reusable Site Layout Guard module loaded across the public Elev8 Arts frontend and all Elev8 OS frontend workspaces.
- No theme-file edits and no database changes.

## 15.0.2 - Simplified Staff Access & Knowledge Base Foundation

### Changed
- Removed the floating Install App/Open App experience and all install prompts while preserving the Experience Engine phone navigation dock.
- Replaced mobile installation guidance with simple browser access, bookmarking, and staff-resource guidance.
- Preserved the service-worker support used by class browser notifications without presenting Elev8 OS as an installable app.

### Added
- Added a shared frontend Employee Guides & Knowledge Base workspace at `/elev8-resources/`.
- Added the Welcome to Elev8 OS staff quick-start PDF with a clickable access link and scannable QR code.
- Added an interim link to the existing Elev8Glass.com knowledge base while operational documentation is migrated into Elev8 OS.
- Added Resources to the universal header, user menu, command palette, and Mobile Home.

### Architecture
- Core Platform Capability: reusable Knowledge Base and Employee Guides foundation for all current and future businesses.
- Existing bottom navigation, role routing, conversations, class alerts, and mobile-responsive dashboards remain intact.
- No database changes.


## 15.0.1 — Universal Install Helper

### Fixed
- Kept the floating Install App control fully inside mobile viewports with iPhone and Android safe-area support.
- Removed desktop-only positioning assumptions and dynamically lifts the helper above the Experience Engine phone dock and other protected floating controls.
- Temporarily hides the floating helper while the mobile keyboard is open so it cannot cover forms or action buttons.

### Changed
- Added a shared Install Helper Service so every frontend dashboard uses the same role-neutral install state, labels, workspace URL, and protected-region configuration.
- Installed browser sessions now show a compact Open App icon; the helper is hidden when Elev8 OS is already running as a standalone PWA.
- Improved responsive install instructions for Android Chrome, Samsung Internet, iPhone Safari, and desktop Chromium browsers.

### Architecture
- Core Platform Capability: reusable PWA Install Helper shared by all current and future Elev8 OS Operational Homes.
- No database changes.

## 15.0.0 — Experience Engine

### Added
- Added a shared Experience Service that remembers each user's last verified Elev8 OS frontend workspace without changing module ownership or duplicating business logic.
- Added a dedicated frontend Glass Workbench at `/glass-workbench/` for the Elev8 Glassblower role.
- Added a universal phone navigation dock with role-aware shortcuts for Home, Actions, Conversations, Production Board, Class Approvals, Workbench, or Classes.
- Added reusable role shortcuts and role-home resolution through the centralized Workspace Resolver.

### Changed
- Glassblowers now land in the Glass Workbench instead of the shared Artist Dashboard page.
- The universal Elev8 OS header resumes the user's last allowed workspace while preserving a stable role-home shortcut.
- Workspace memory is ignored during founder Preview Mode and is cleared when WordPress roles change.
- Glass Managers continue to land in the frontend Glass Manager Suite.

### Architecture
- Core Platform Capability: Experience Engine, workspace memory, mobile application navigation, and Workbench framework.
- Configurable Business Module: Glass Workbench presentation and role-specific shortcuts.
- Existing Production, Classes, Conversations, Actions, and Access Service remain the sources of truth.

### Database changes
- None. Uses WordPress user metadata for last-workspace memory.

## 14.3.1 - Universal Role Landing & Workspace Routing

- Added one centralized Workspace Resolver for login, Universal Header, Preview Mode, command palette, and legacy dashboard routes.
- Glass Managers now land in the frontend Glass Manager Suite instead of the Artist Dashboard.
- Added CEO-only Workspace Diagnostics and automatic routing-memory reset when operational roles change.

## 14.3.0 — Class Approval & Teacher Notification Center
- Added frontend Class Approval Center for pending Amelia glass-class bookings.
- Added approve, move-date, and cancel actions with immutable Activity history.
- Added urgent booking indicators, customer-safe details, and weekday/date formatting.
- Added five-minute pending-booking detection with email fallback to Glass Managers.
- Added browser/PWA notification permission, test alerts, app badges, one-minute foreground polling, and deep links.
- Added Class Approvals to the Frontend Glass Manager Suite.


## 14.2.0 — Frontend Glass Manager Suite
- Added a dedicated frontend Glass Manager application at `/glass-manager/`.
- Exposed Production Board, Jobs, Fast Pay, Glassblower Team, Repair and Memorial Intake without WordPress admin access.
- Exposed Production Products, Materials, Compensation Profiles, Catalog Manager and Import Wizard through the same frontend suite.
- Reused existing Glass Operations and Production Catalog services and forms; no duplicate business logic or data stores were introduced.
- Updated Glass Manager dashboard and Role Preview routing to use the frontend suite.


## 14.1.0 — Production Catalog Manager
- Added Active, Draft, and Archived lifecycle states so obsolete production products can be removed from new work without deleting historical job, pay, and costing records.
- Added bulk lifecycle and family/category controls to the Production Catalog.
- Added a Catalog Manager workspace with family navigation, ignored workbook-row visibility, duplicate detection, and safe duplicate merging.
- Added Import Wizard decisions for Import, Skip This Time, Ignore Forever, and Restore.
- Added revision-history visibility on production product records.
- Preserved historical versions, job snapshots, pay records, and original workbook source references.

## 14.0.1 — Smart Workbook Parser Hotfix

- Replaced silent workbook-analysis failures with explicit upload, workbook, sheet, and parser diagnostics.
- Added upload-error reporting, including the current WordPress upload-size limit, and support for servers that identify Excel workbooks as ZIP archives.
- Added durable wizard-session storage through user meta as a fallback when transients or object caches drop the parsed workbook.
- Replaced rigid row assumptions with label-based financial-row discovery, merged-heading detection, dynamic family blocks, nested subtype/variant detection, and alternate pay-tier grouping.
- Added a workbook diagnostics panel showing detected source rows, merged ranges, cell counts, last used column, family blocks, and skipped empty columns.
- Fixed malformed Blower Pay input markup in the family review screen and added source-column traceability.
- Preserved Production Catalog imports, Fast Glass Pay, financial snapshots, and legacy migration behavior.

## 14.0.0 — Glass Catalog Import Wizard

- Added a workbook-first Glass Catalog Import Wizard that reads the original Production Information sheet without modifying it.
- Detects product families from merged spreadsheet headings and presents one family at a time for review.
- Preserves blower pay, production time, retail, wholesale, distributor, Premier, material and total-cost values.
- Allows managers to edit catalog names, aliases, pay method and blower pay before import.
- Adds duplicate-safe create/update behavior using workbook source codes and preserves source sheet/column traceability.
- Replaces the difficult 205-row cleanup workflow with family-based review aligned to how the glass team understands production.

## 13.9.1 — Production Catalog Migration & Financial Model
- Added a normalized Step 1 migration workbook generated from the legacy Production Information sheet, with one reviewable row per production item and source-column traceability.
- Added a built-in Production Catalog Migration workspace with preview, selective import, duplicate-safe import codes, and update-existing controls.
- Added searchable aliases so Fast Glass Pay can find families and variants from terms such as knob, color knob, custom knob, SSV wand, CON wand, and other practical names.
- Preserved the source financial model for each product: Actual Retail, Dist Profit @ Retail, Dist Additional Cost, Suggested Retail, Dist Profit (WS), Premier Profit, Actual Wholesale, Suggested Wholesale, Sold to Distributor @, Material Cost, Total Cost, blower pay, estimated production time, instructions, and training video.
- Added financial-model and source-identity fields to Production Catalog editing and version snapshots.
- Moved Copy Previous Day into Advanced Tools while preserving it for future repetitive workflows.
- Kept Fast Glass Pay, Production Jobs, historical snapshots, and existing glass workflows backward-compatible.

## 13.9.0 — Fast Glass Pay Entry
- Added a keyboard-friendly daily blower pay sheet with type-ahead Production Catalog search.
- Added live hourly and piecework calculations using blower compensation profiles and catalog payout rules.
- Added inline creation of new pay items without leaving the pay sheet, with an explicit warning when full cost details remain incomplete.
- Added recent items, manager favorites, copy-previous-day drafts, daily totals, draft submission, approval controls, and print-friendly output.
- Preserved Production Jobs as the preferred source for order-driven work while allowing quick after-the-fact entry into the same payout records.
- Added direct Production Catalog snapshots to quick pay entries so rate history remains explainable.

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
# Elev8 OS Changelog

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

- Added batch artwork QR label selection for artists and administrators.
- Added Select All, Clear All, live selection totals, and copies-per-label controls.
- Added full letter-sheet layouts for six 3 × 3 labels or sixteen 3 × 1 labels per page.
- Added multi-page printing for larger selections while preserving one tracked QR code per artwork.
- Added ownership and artist validation so labels cannot be mixed across unauthorized profiles.
- Preserved all existing single-label, artist-card, Production Board, Production Jobs, and pay-sheet workflows.

## 13.2.0 — Glass Production Board

- Added a visual production board that groups active glass jobs by workflow status.
- Added drag-and-drop status movement with server-side permission, nonce and status validation.
- Added quick blower assignment directly on production cards.
- Added filters for search, blower, source, priority and overdue work.
- Added live workload summaries for each active glassblower and unassigned work.
- Added overdue and due-today indicators without changing the underlying job source of truth.
- Added accessible non-drag status controls so the board remains usable on phones and by keyboard.
- Preserved Production Catalog snapshots, automatic pay sheets, QC controls and Glassblower Operational Homes.

## 13.1.0 — Production Jobs, Glassblower Team & Pay Sheets

- Added catalog-backed production job lines with compensation, material-cost and product-version snapshots.
- Added configurable job sources for Shipping, Head Shop, Cremation, Website, Wholesale, Repair, Internal Inventory and Custom work.
- Added Glassblower Team roster management; production dropdowns now show only active roster members with the Glassblower role and compensation profile.
- Added automatic foundation matching for Nick (shimkus92@gmail.com) and Adam (adamelev8@gmail.com) at $18/hour with piecework eligibility, without hardcoded user IDs.
- Added manager QC, completion, time and payroll approval controls for production lines.
- Added catalog/profile-driven hourly and piecework calculations and automatic pay-sheet review.
- Added a role-aware Glassblower Operational Home showing assigned work, QC status, pending/approved pay and Conversations access.
- Preserved existing Glass Operations, Production Catalog, Workspace, Relationship and Intelligence engines.

## 13.0.0 — Glass Production Catalog & Compensation Engine

- Added a new Elev8 OS Production Catalog designed for manual entry rather than importing the legacy spreadsheet as a database.
- Added production-product definitions with codes, categories, descriptions, skill level, department, active status, and estimated production time.
- Added product-controlled compensation methods: Hourly, Piecework, Either (manager chooses on the future job), and Included in another production item.
- Added piecework payout rates and payout units for piece, pair, set, batch, or job.
- Added reusable material records with units and unit costs, plus product bills of materials, waste allowance, and cost snapshots.
- Added direct consumable, packaging, and other production-cost fields with estimated labor costing.
- Added glassblower compensation profiles that support hourly pay and product-specific piecework at the same time.
- Added configurable initial $18/hour compensation-profile setup for uniquely matched Nick and Adam users, without hardcoded user IDs.
- Added immutable production-product version snapshots so future jobs can preserve the rates and costs in effect when they were created.
- Added a dedicated Production Catalog administration workspace for Products, Materials, and Compensation Profiles.
- Preserved the existing Glass Operations jobs and payout tools; 13.0.0 establishes the trusted definitions that future Production Jobs and Pay Sheets will consume.

## 12.3.0 — Workspace Executive Intelligence

- Added an explainable Workspace Intelligence Service for every supported Universal Workspace.
- Added a verified 0–100 workspace health score using actions, overdue work, blockers, dependencies, conversations, relationships, and timeline activity.
- Added rule-based health states: Healthy, Needs Attention, and Action Required.
- Added verified risks, opportunities, confidence, and a recommended next step inside each workspace.
- Added a Why? explanation that shows the exact verified signals behind every health conclusion.
- Preserved confirmation-first automation; no autonomous actions or generative-AI guessing were introduced.
- Kept source records owned by their existing engines and used the Workspace and Relationship Engines as read-only context.

## 12.2.0 — Workspace Automation Engine

- Added rule-based suggested actions inside Universal Workspaces.
- Added confirmation-first automations for manager notes, event applications, reservations, conversations, and work completion.
- Added Explain Why details for every suggested automation.
- Created connected Work items without modifying source records.
- Added activity audit entries and relationship links for executed automations.
- Added dependency signals for Blocks and Depends On relationships.

# Changelog

## 12.1.0 - Relationship Engine

- Added a shared Relationship Engine for explicit, trusted links between Elev8 OS records.
- Added two-way workspace relationships without duplicating authoritative source data.
- Added relationship types for related records, dependencies, blockers, follow-up, support, and participation.
- Added relationship impact metrics to Universal Workspaces.
- Added a workspace connector for authorized owners and managers.
- Added relationship notes, removal controls, permission checks, and immutable Activity records.
- Preserved inferred workspace links while allowing explicit Business Graph relationships to supplement them.

## 11.3.2 — Public Profile Type Save Hotfix

### Fixed
- Fixed public profile type checkboxes so unchecked types are removed when the profile is saved.
- Preserved an explicitly saved empty profile-type selection instead of re-inferring types from WordPress roles or legacy artist data.
- Prevented legacy artist compatibility logic from silently adding Artist back after it was intentionally unchecked.
- Synchronized the legacy public artist webpage only when Artist remains selected and the shared public profile is published.

### Compatibility
- Existing profiles that have never saved shared profile types still receive the original one-time type inference.
- Artist and Teacher combinations can now be changed safely to Teacher only, Artist only, multiple types, or no selected type.
- Publishing still requires at least one selected public profile type.

## 11.3.1 — Public Profile Eligibility Hotfix

### Added
- Added explicit public-profile eligibility states: Eligible, Customer, Bot / Spam, Archived, and Not Yet Classified.
- Added eligibility controls to the CEO Public Profile editor without changing the user’s WordPress role or customer history.
- Added Public Profiles filters for eligible team members, excluded accounts, individual exclusion types, and all WordPress users.
- Added weighted profile completeness based on public name, profile type, headline, biography, and profile photo.

### Changed
- Removed the automatic Staff fallback for unknown WordPress users.
- Customers, bots, archived users, and unclassified accounts are excluded from the normal Public Profiles workspace and CEO profile-attention section.
- Excluding an account clears public profile types, prevents publication, and unpublishes any connected legacy artist page.
- Public profile publishing now requires at least one profile type and a biography.

### Compatibility
- Existing explicitly assigned public profile types remain eligible.
- Existing legacy artist profiles remain eligible and continue to synchronize publication state.
- WordPress roles, customer records, orders, and account history are not modified.

## 11.3.0 — Identity Media Uploads

- Replaced public-profile image URL fields with direct WordPress Media Library upload controls.
- Added reusable profile photo and cover-image selectors with preview, replace, and remove actions.
- Stored WordPress attachment IDs as the trusted image references while preserving legacy image URLs for backward compatibility.
- Added a public cover image to published profiles and retained existing profile-photo behavior.
- Added shared media-control styling and JavaScript for frontend and CEO profile editors.
- Updated profile completeness and legacy artist synchronization to continue using verified resolved image URLs.
- Preserved unified profile types, publication controls, CEO oversight, artist compatibility, and existing public URLs.

## 11.2.0 — Unified Public Profiles & CEO Oversight

- Consolidated artists, teachers, event hosts, managers, volunteers, and staff under the shared Public Profiles service.
- Added multi-type public identities without changing private WordPress roles.
- Added a CEO Public Profiles workspace with type, status, and search filters.
- Added Published, Draft/Unpublished, Missing Profile, and Incomplete summaries.
- Added direct CEO edit, preview, publish, and unpublish workflows for any user, including Event Hosts who are not artists.
- Added CEO Dashboard attention cards for unpublished or incomplete profiles.
- Enlarged and clarified the legacy artist public-page activation control.
- Added backward-compatible synchronization between legacy artist publication and the shared Public Profiles service.
- Preserved artist storefront, class, payout, and artwork-specific data in the Artist module.

# Elev8 OS Changelog

## 11.1.0 — Public Profiles Foundation

- Added a shared Public Profile Service for Event Hosts, Artists, Managers, and future roles.
- Added a frontend My Public Profile editor with draft, preview, publish, and unpublish controls.
- Added public profile routes at `/people/{profile-slug}/`.
- Connected the Event Host profile warning and coaching recommendation to the working editor.
- Updated the universal user menu and command palette to open the public profile workspace.
- Preserved the existing private WordPress account settings link separately.

## 11.0.0 — Action Engine Foundation
- Added a frontend My Actions workspace so non-admin users no longer get redirected away from Work.
- Added direct status, due-date, notes, completion, and team action controls.
- Added Manager Logs to the universal header for authorized leaders.
- Made manager-log activity in the Daily Executive Brief clickable.
- Added shared Action Service and reusable Action Center portal page.

## 10.25.0 — Conversation Engine Foundation

- Added a shared threaded Conversation Service for questions, decisions, replies, and operational follow-up.
- Added a phone-friendly frontend Conversation Center available from the Universal Application Shell.
- Added capability-aware conversation access for owners, managers, event hosts, artists, teachers, volunteers, retail employees, and glass team members.
- Added multi-person conversations, replies, open/closed status, unread tracking, participant visibility, and @username participant inclusion.
- Added immutable Activity records for conversation creation and replies so communication contributes to Business Memory and future intelligence.
- Added Conversations to the universal header, user menu, and Command Palette with unread counts.
- Preserved existing dashboards, Universal Search, Attention Center, Coaching, Executive Brief, Workflow Engine, and centralized access rules.

## 10.24.0 — Universal Application Shell Frontend Compatibility Fix

- Fixed the Universal Application Shell not appearing on the frontend Event Host / DJ Operational Home.
- Added a safe `wp_footer` fallback for themes that do not call WordPress `wp_body_open()`.
- Added direct portal-slug detection so dashboard pages remain recognized even when saved page IDs are stale or unavailable.
- Added frontend shell placement logic that moves fallback markup to the top of the page while preserving the WordPress admin bar.
- Applied the same compatibility behavior to CEO, Manager, Event Host, Artist, Teacher, Volunteer, and future frontend Operational Homes.
- Preserved Universal Search, notifications, the user menu, Install App, dashboard actions, and centralized access rules.

## 10.23.0 — Universal Search & Command Palette

- Added a shared, role-aware Elev8 OS command palette to the Universal Application Shell.
- Added Search and Ctrl/Cmd+K access on supported Elev8 OS frontend and admin workspaces.
- Added keyboard navigation, mobile presentation, Escape-to-close, and quick-action discovery.
- Added capability-aware commands for dashboards, work, Business Memory, Business Intelligence, reservations, event tools, opportunities, and artist workspaces.
- Added secure AJAX search for work items, scoped to the current user unless team-work permission is granted.
- Added owner-only team-member search and an extension filter for future module search providers.
- Preserved the existing Universal Application Header, notifications, user menu, dashboards, and access rules.

## 10.22.0 — Universal Application Shell

### Added
- Universal Elev8 OS application header across frontend portal pages and Elev8 OS admin workspaces.
- Capability-aware navigation for Dashboard, Work, and Business Memory.
- Consistent user menu with My Dashboard, My Profile, Notifications, Settings, Help, Return to Elev8Arts.com, and Log Out.
- Role labels for Owner, Shop Manager, Glass Manager, Event Host, Teacher, Artist, Volunteer, and other team members.
- Shared notification badge backed by the Attention Service.
- Responsive mobile menu and sticky application header.

### Preserved
- Existing CEO, Manager, Event Host, Artist, Teacher, and Volunteer dashboards.
- Existing Install App control and dashboard actions.
- Existing Access Service permissions and Attention Service data.

### Database changes
- None.

# Elev8 OS Changelog

## 10.21.0 — Role-Aware Coaching Foundation

- Added a shared, deterministic Coaching Service for CEO, Manager, and Event Host Operational Homes.
- Added recommendation cards with verified reasons, confidence, one-tap actions, and “Why am I seeing this?” explanations.
- Distinguished recommendations from raw information and attention items.
- Preserved user control: recommendations never perform autonomous changes and do not call an external AI provider.
- Added extension hooks so future Artist, Teacher, Retail, Maintenance, and LLM coaching providers can reuse the same contract.

## 10.20.0 — Executive Brief Engine

- Added a reusable, rule-based Daily Brief Service built from verified Elev8 OS sources.
- Added a phone-friendly Daily Executive Brief with Yesterday, Business Pulse, Attention, Wins, Risks, Opportunities, Today’s Focus, and an expandable timeline.
- Added confidence indicators based on available trusted sources.
- Added reusable Why explanations for the daily summary, Business Pulse, and confidence level.
- Preserved existing CEO intelligence, workflow health, manager-note notifications, event applications, reservations, and KPI calculations.
- Missing or unconnected data remains explicitly Unavailable rather than inferred.


## 10.19.0 — Manager Operational Home

- Added a capability-driven Manager Operational Home for non-owner managers.
- Added mission briefing, Business Pulse, attention queue, work summaries, daily operations status, team pulse, events and reservation coordination, verified wins, and end-of-shift closeout.
- Added a reusable Manager Dashboard Service backed by Access, Attention, Work, Daily Operations, Reservations, and Event Applications engines.
- Preserved artist, event-host, and CEO dashboard behavior.
- Added responsive phone-first manager dashboard styling.

## 10.18.0 — Workflow Engine Foundation & Explain Why

- Added a reusable Workflow Engine that listens for trusted Elev8 OS events, evaluates configurable definitions, executes registered actions, and preserves an auditable run history.
- Added initial workflow triggers for Manager Operations entries, Unified Intake submissions, and Bingo reservations.
- Added extensible workflow-definition and workflow-action filters so future business modules can participate without duplicating routing logic.
- Added idempotent workflow execution to prevent the same source record from running the same workflow twice.
- Added registered actions for immutable Activity records and shared Work Management items.
- Added Workflow Health to the CEO Dashboard with active workflows, recent runs, completed runs, failed runs, and a plain-language Why explanation.
- Added a reusable Explain Why service and Why controls to CEO financial KPI cards so users can understand source, diagnostic, and confidence information.
- Preserved current Attention, Event Application, Manager Note, reservation, Work, Business Intelligence, and Executive Intelligence behavior.

## 10.16.0 — Executive Intelligence Center

- Added a reusable rule-based Executive Intelligence Service that converts verified Attention, Dashboard, and Business Intelligence data into a concise CEO briefing without guessing.
- Added an Executive Brief with daypart greeting, current operating headline, verified changes, upcoming reservations, event application status, and booked-value context.
- Upgraded the shared Attention queue into a CEO Decision Queue with context-aware actions for manager notes, event applications, reservations, and work.
- Added Recent Wins based only on verified conditions such as no critical issues, positive booked-value movement, and upcoming reservation activity.
- Added a rule-based Opportunities panel that surfaces verified next moves and links directly into the relevant workspace.
- Added an Executive Timeline using timestamped activity already represented by the shared Attention Center.
- Added responsive Executive Intelligence widgets designed for desktop and phone use.
- Preserved existing KPI calculations, Access Service checks, Attention Service inputs, CEO tools, Event Application notifications, and System Information.

## 10.15.0 — CEO Attention Center

- Added a reusable Attention Service that gathers verified items from Daily Operations, Work Management, Reservations, and Event Applications.
- Added manager messages marked for Steve directly to the CEO Attention Center with the original author, message preview, timestamp, and direct link to the operating log.
- Replaced the CEO's generic Needs Attention list with a prioritized Waiting on Me queue using critical, high, and normal severity states.
- Added a rule-based Business Pulse showing whether the business is healthy, needs attention, or requires immediate action.
- Rebuilt the CEO workspace links as readable command cards and moved secondary navigation into a compact CEO Tools row.
- Connected the shared Dashboard Service notification state to the Attention Service instead of displaying an unavailable placeholder.
- Preserved existing Event Application notifications, reservations, work counts, verified financial KPIs, and system information.

## 10.14.1 - Event Host Access Hotfix

- Added a frontend Bingo Reservations workspace inside the Event Host Operational Home so event hosts are not redirected into a blocked WordPress admin screen.
- Corrected the Event Host Bingo Reservations action to use the verified dashboard workspace route.
- Added mobile-friendly reservation cards, filters, contact links, notes, guest counts, and status updates.
- Preserved assignment-scoped reservation visibility through the centralized Access Service.
- Kept reservation status updates on the frontend workspace after saving.
- Embedded the Install Elev8 OS control directly in the Event Host dashboard header and added a no-JavaScript visible fallback.
- Preserved the Complete Event Log action and existing event-host metrics.

## 10.14.0 — Installable App & Mission Briefing
- Added a reusable Elev8 OS app-install capability for logged-in team members on supported mobile and desktop browsers.
- Added a standards-based web app manifest and service worker so Elev8 OS can open from a phone Home screen in standalone app mode.
- Added a large first-use Install Elev8 OS prompt that collapses into a small persistent button after dismissal or installation.
- Added iPhone/iPad Safari and non-install-prompt browser instructions without pretending an automatic install is available.
- Connected the installed app start screen to the role-aware Elev8 OS Mobile Home.
- Added a clearer event-host mission briefing focused on reviewing entries, running the event, and preserving the closeout in Business Memory.
- Preserved existing role dashboards, permissions, and operational data sources.

## 10.13.0 — Event Host Operational Home
- Added a capability-driven Event Host Operational Home for Open Mic DJs and future event-host roles.
- Replaced irrelevant artist sales, class, student, and payout widgets for event hosts with verified Open Mic and Bingo operational data.
- Added an immediate public host-profile status notice while preserving the private Elev8 OS account.
- Added phone-first event actions for Open Mic check-ins, Bingo reservations, and the Event Operating Log.
- Added verified Open Mic submission metrics and recent check-ins from Daily Operations Intelligence.
- Added scoped Bingo reservation metrics using the existing Reservations engine and Access Service.
- Added a mobile-first Tonight Mode foundation ready for future event-control capabilities.
- Preserved the existing Artist Dashboard for artists and teachers.

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

## 12.0.0 — Workspace Engine Foundation
- Added a universal frontend Workspace Engine that gathers one source record's summary, status, activity timeline, actions, conversations, related records, people, and files without duplicating authoritative business data.
- Added the `/elev8-workspace/` operational page and reusable **Open Workspace** URLs for work items, conversations, manager logs, event applications, reservations, and people.
- Added capability-aware workspace permissions through the centralized Access Service.
- Added the first universal Activity Timeline, Related Record inference, and Workspace header components.
- Added **Open Workspace** actions to frontend Work and Conversation experiences.
- Added a Workspace command to Universal Search / Command Palette.
- Preserved existing dashboards, Action Center, Conversations, Business Memory, and source-record workspaces.

## 17.1.0 — Operations Contributor Adapter Foundation

### Added
- Reusable Operations Contributor registry and source synchronization service.
- First contributor adapter for Glass Production, Repairs, and Memorials.
- Standard execution contracts for checklists, required approvals, completion rules, and escalations.
- Source status and synchronization timestamps on contributed Work Items.

### Changed
- Glass job creation and updates now publish source-change events to the Operations Engine.
- Universal Work Item summaries now expose contributor execution context.

### Architecture
- Accepted ADR-0012: authoritative operational records contribute work through idempotent adapters and remain the owners of source state.
- No duplicate production, repair, memorial, commerce, booking, or identity records are created.

### Database changes
- None. Contributor contracts use existing Work Item post metadata.

## 19.9.0
- Added date-specific availability exceptions that override recurring coordination windows for a single Work Item due date.
- Added bounded credential and training evidence references with optional expiration, renewal windows, safe internal references, and WordPress Media attachment IDs.
- Added daily credential-expiration reminders through the shared Notification Service.
- Added optional expiration to manager-confirmed skill evidence and excluded expired verification from active handoff matching.
- Extended explainable handoff fit with active credential evidence while preserving human acknowledgement and access boundaries.
- Added ADR-0042 documenting that availability exceptions and credential references are coordination evidence, not HR, access control, certification, or secret storage.
