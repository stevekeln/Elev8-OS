# Elev8 OS Business Blueprint

> Architecture is the product. Code serves the architecture. Elev8 OS must still make sense ten years from now.

**Blueprint version:** 2.0  
**Established:** 2026-07-22  
**Platform release:** 19.0.0  
**Status:** Governing architecture document

## Platform Constitution

1. Architecture is the product; code serves the architecture.
2. Protect the Business Graph before building features.
3. Never duplicate authoritative data.
4. Every feature must belong to an engine.
5. Every business object must participate in the Business Graph.
6. Dashboards are role-based views, not independent systems.
7. Business policies are configurable and never hardcoded.
8. Prefer engines over modules, relationships over copies, and reusable systems over one-off solutions.
9. Prefer automation over manual effort and AI assistance over user burden.
10. AI must consult the Blueprint before recommending architecture.
11. If a feature does not reduce operational burden, reconsider building it.
12. Never sacrifice architecture for speed.

## Mission

Elev8 OS eliminates software fragmentation for service-based small businesses. It connects commerce, booking, operations, people, knowledge, communication, financial activity, automation, and intelligence through one Business Graph on WordPress and WooCommerce.

Elev8 OS is not designed as a collection of WordPress pages. It is a long-lived operating platform that helps each person immediately understand what needs attention and what should happen next.

## Platform Boundaries

### WordPress

WordPress is the platform and owns authentication, users, roles, content infrastructure, media, and extension compatibility.

### WooCommerce

WooCommerce owns products, orders, checkout, payments, taxes, subscriptions, and authoritative commerce records.

WooCommerce answers: **What did the customer buy?**  
Elev8 OS answers: **What should happen because they bought it?**

### Amelia

Amelia currently owns class scheduling, appointments, customer bookings, capacity, and authoritative booking records. Elev8 OS owns operational approvals, role-based presentation, alerts, workflows, intelligence, and follow-through around those records.

### Elev8 OS

Elev8 OS owns business operations, business relationships, business intelligence, business automation, business knowledge, and business execution. It extends authoritative systems rather than replacing or copying them.

## Engine Registry

### Major Engines

| Engine | Purpose | Owns | Must Not Duplicate |
|---|---|---|---|
| Organization | Represents businesses, brands, locations, departments, teams, reporting structure, and scoped person responsibilities. | Organization units, hierarchy, assignments, and scope relationships. | WordPress authentication, user identity, or commerce records. |
| Commerce | Interprets commerce events and coordinates operational consequences. | Commerce orchestration and relationships. | WooCommerce products, orders, payments, taxes, or checkout. |
| Sales | Manages opportunities, pipelines, follow-up, proposals, and conversion activity. | Sales execution and opportunity relationships. | CRM identities or authoritative orders. |
| Marketing | Plans campaigns, content, audiences, launches, attribution, and reusable marketing workflows. | Campaign execution and marketing intelligence. | Customer identity or commerce transactions. |
| Operations | Executes daily work, production, procedures, repairs, routes, maintenance, and service delivery. | Operational state and execution records. | Commerce, booking, or identity source records. |
| Communication | Connects every business object through conversations, email, SMS, notifications, mentions, and summaries. | Communication threads, delivery, escalation, and context links. | Persons, bookings, or jobs. |
| Booking | Coordinates booking operations, approval, capacity awareness, staffing, and follow-through. | Operational booking workflows. | Amelia authoritative booking records. |
| Financial | Interprets revenue, cost, payout, invoice, profitability, and financial obligations. | Financial operational records and analysis. | WooCommerce payments, accounting ledgers, or bank records. |
| Intelligence | Summarizes, predicts, recommends, detects risk, and supports decisions using verified data and knowledge. | Explainable intelligence and confidence. | Source business records. |

### Supporting Engines

| Engine | Purpose |
|---|---|
| Identity | People, authentication mappings, public identity, roles, access, and workspace routing. |
| CRM | Customers, vendors, partners, relationships, interactions, and lifecycle context. |
| Membership & Benefits | Configurable memberships that grant reusable benefits. |
| Knowledge | SOPs, training, policies, architectural decisions, guides, and institutional memory. |
| Inventory | Quantity, availability, reservations, movement, and reconciliation. |
| Assets | Physical and digital objects, ownership, custody, condition, and lifecycle. |
| Workflow | Reusable states, approvals, dependencies, assignments, and completion rules. |
| Automation | Trigger-condition-action rules, escalation, and confirmation-first execution. |
| Analytics | Trusted metrics, trends, comparisons, and reporting. |
| Integrations | Boundaries and synchronization with external authoritative systems. |
| Events | Event planning and operational execution connected to staffing, inventory, bookings, communications, and financial outcomes. Event applications now contribute synchronized review and delivery Work Items. |

## Dashboard Principle

Dashboards are views over shared engines and the Business Graph. CEO, owner, manager, employee, glassblower, teacher, artist, event host, volunteer, customer, bookkeeper, and administrator experiences must not become separate systems.

HR is not an engine. HR is a role-based dashboard powered by Identity, Organization, Operations, Workflow, Communication, Knowledge, Financial, Analytics, and Intelligence.

## Business Graph

### Core Objects

Person, Business, Brand, Location, Department, Product, Order, Inventory, Asset, Maintenance Record, Observation, Task, SOP, Workflow, Communication, Event, Booking, Membership, Benefit, Donation, Volunteer, Artist, Employee, Vendor, Customer, Invoice, Payout, Route, Campaign, Knowledge, Production Job, Repair, Memorial Case, Class, Application, Reservation, and Workspace.

### Relationship Rules

- Relationships are more important than isolated records.
- Every object has one authoritative owner.
- Elev8 OS links to authoritative records instead of cloning them.
- A Person may participate in many businesses, brands, locations, departments, and roles.
- One owner may operate many businesses with shared people, customers, inventory, assets, vendors, and knowledge.
- Communications, tasks, workflows, files, and intelligence must connect to the business object they concern.
- Historical snapshots are allowed when required for payroll, costing, audit, or compliance, but snapshots never replace the authoritative source.

## Existing Capability Map

| Existing Elev8 OS Capability | Primary Engine | Supporting Engines |
|---|---|---|
| Access Service, public profiles, role landing | Identity | Organization |
| Businesses, brands, locations, departments, teams, person assignments | Organization | Identity, Workflow |
| Glass production, repairs, memorials | Operations | Assets, Workflow, Financial |
| Production Catalog and Fast Pay | Operations | Commerce, Financial, Knowledge |
| Conversations and notifications | Communication | Identity, Automation, Knowledge |
| Teaching Calendar and Class Approvals | Booking | Communication, Workflow, Integrations |
| Business Memory and Employee Guides | Knowledge | Communication, Intelligence |
| Work, Actions, Relationships, Workspaces | Workflow | Operations, Automation |
| CRM contacts, vendors, students | CRM | Identity, Sales, Communication |
| Events, reservations, applications | Events | Booking, Workflow, Financial |
| CEO Brief, Workspace Health, recommendations | Intelligence | Analytics, Knowledge |
| WooCommerce response workflows | Commerce | Operations, Financial, Integrations |

## Workflow Registry

### Customer Books a Class

**Trigger:** Amelia creates a pending booking.  
**Objects:** Person, Booking, Class, Communication, Task.  
**Engines:** Booking, Communication, Workflow, Integration, Intelligence.  
**Outcome:** Correct manager or teacher is alerted, approves, moves, or cancels the booking, and Amelia remains authoritative.

### Glass Production and Payout

**Trigger:** A production need is created or completed work is entered.  
**Objects:** Product, Production Job, Person, Payout, Material, Task, Communication.  
**Engines:** Operations, Financial, Inventory, Workflow, Communication.  
**Outcome:** Work is assigned, completed, quality checked, and approved pay is traceable to the source work.

### Repair and Memorial Custody

**Trigger:** A customer item or memorial remains are received.  
**Objects:** Customer, Asset, Repair, Memorial Case, Production Job, Communication, Payout.  
**Engines:** Operations, Assets, Workflow, Communication, Financial.  
**Outcome:** Custody, approvals, production, reconciliation, and release remain auditable.

### Development Session

**Trigger:** A new Elev8 OS coding session begins.  
**Required sequence:** Read Blueprint → classify engines and objects → inspect existing ownership → design → implement → validate → update Blueprint → deliver changed files.  
**Outcome:** Architecture remains coherent and decisions remain durable.

## Architecture Decision Records

### ADR-0001 — Architecture Is the Product

**Status:** Accepted  
**Decision:** Code must serve a coherent platform architecture that remains understandable ten years from now.  
**Consequence:** A fast one-off solution must be rejected when it weakens engine ownership or the Business Graph.

### ADR-0002 — WooCommerce Owns Commerce Records

**Status:** Accepted  
**Decision:** WooCommerce owns products, orders, checkout, payments, taxes, and subscriptions.  
**Consequence:** Elev8 OS reacts to commerce and coordinates operations without creating competing commerce records.

### ADR-0003 — Dashboards Are Views

**Status:** Accepted  
**Decision:** Role dashboards are views over shared engines and relationships.  
**Consequence:** Business logic must live in shared services, never inside a dashboard template.

### ADR-0004 — Business Policies Are Configurable

**Status:** Accepted  
**Decision:** Elev8 schedules, payout rules, locations, approval thresholds, and other business-specific policies may not be hardcoded into platform services.  
**Consequence:** Platform capabilities remain reusable across thousands of businesses.

### ADR-0005 — Verified Data or Unavailable

**Status:** Accepted  
**Decision:** Business Intelligence must display **Unavailable** when data cannot be verified.  
**Consequence:** Elev8 OS never creates persuasive but misleading business information.

### ADR-0006 — Uploaded Repository Is Source of Truth

**Status:** Accepted  
**Decision:** The uploaded repository is the only source of truth for development.  
**Consequence:** Never recreate the repository, duplicate plugin folders, or return the whole repository.

### ADR-0007 — Blueprint Governs Development

**Status:** Accepted  
**Decision:** Every development session begins by consulting this Blueprint and ends by updating it.  
**Consequence:** Chat history and developer memory are supporting context, not the architectural source of truth.


### ADR-0008 — Organization Structure Is Configurable Graph Data

**Status:** Accepted  
**Decision:** Businesses, brands, locations, departments, teams, and person responsibilities are Organization Engine records and relationships. WordPress remains authoritative for user identity.  
**Consequence:** Operational engines may scope access and responsibility through Organization assignments without duplicating users or hardcoding a single-business hierarchy.


### ADR-0009 — Business Graph Ownership Is Explicit and Enforced

**Status:** Accepted  
**Decision:** Every Business Graph object type declares one owning engine, one authoritative system, its organization-scope behavior, and the relationship types it may participate in. New explicit relationships must pass the shared Business Graph Registry before storage.  
**Consequence:** Engines may extend the registry through documented filters, but they may not invent unregistered object ownership or persist invalid graph links. Elev8 OS links authoritative records and records relationship context without cloning source data.



### ADR-0011 — Universal Work Item Is the Canonical Operational Execution Object

**Status:** Accepted  
**Decision:** The Operations Engine owns the canonical Work Item used to represent operational execution. Production, repair, memorial, teaching, maintenance, inventory, route, event, and approval work are configurable work types over the same execution object. Workflow owns reusable state, approval, dependency, and completion mechanics.  
**Consequence:** Existing source records are not replaced or migrated destructively. Engines contribute work through stable references, and role dashboards present filtered views of one shared Work Inbox.


### ADR-0014 — Booking Decisions Contribute Work Without Owning Bookings

**Status:** Accepted  
**Decision:** Amelia-backed approval states contribute synchronized Work Items through a Booking-to-Operations adapter. Amelia remains authoritative for booking status, schedule, provider, customer, and service data.  
**Consequence:** Elev8 OS may assign, escalate, evidence, and close operational approval work, but it may not create a competing booking record or parallel approval state.


### ADR-0020 — Human Review Governs Intelligence Promotion

**Status:** Accepted  
**Decision:** Verified source observations may be generated automatically, but leaders must be able to confirm, correct, or dismiss them before higher-confidence recommendations or automation rely on them.  
**Consequence:** Review state, reviewer identity, timestamp, and notes are stored on the Observation rather than copied back into authoritative source records.

## Roadmap by Engine

| Engine | Current Foundation | Next Architectural Focus |
|---|---|---|
| Knowledge | Business Memory, Employee Guides, Business Blueprint | Structured SOP execution and knowledge relationships |
| Organization | Organization units, hierarchy, scoped person assignments, CEO company map | Shared resources and organization-aware engine access across registered graph objects |
| Operations | Universal Work Item, Work Inbox, glass production, repairs, memorials, and daily operations | Work adapters, Workbench execution, reusable SOP execution, and approval orchestration |
| Communication | Conversations, alerts, mentions | Unified delivery, preferences, escalation, and customer communication |
| Booking | Amelia calendar, approval center, and class approval Operations Contributor | General booking orchestration, staffing rules, and post-approval execution adapters |
| Financial | Production costing and payouts | Unified obligations, profitability, invoices, and accounting integration |
| Commerce | WooCommerce integration | Order-to-operation orchestration |
| Sales | Opportunities foundation | Reusable pipeline and sales operating home |
| Marketing | Content and marketing foundations | Campaign engine and attribution |
| Intelligence | Observation Engine, Observation Review, Pattern Detection, Recommendation Promotion, Outcome Tracking, Executive Intelligence, scheduled Executive Brief delivery, attention governance, and workspace health | Configurable classification, ROI adapters, decision follow-through, and forecasting |

## Technical Debt and Risks

- The current Site Layout Guard was introduced as an OS-level response to a likely theme/footer issue. Theme ownership must be resolved before expanding that guard.
- Some legacy modules still contain presentation and routing assumptions that should move into shared engine services.
- Existing engine boundaries are now declared in the Business Graph Registry, but legacy modules must progressively register their concrete source adapters and organization scope.
- The Blueprint is initially repository-backed and read-oriented; structured synchronization and controlled editing are future work.

## Open Questions

1. How should shared assets, inventory, customers, and vendors be scoped across organization units without duplicating their authoritative records?
2. Which accounting system will be authoritative for ledgers, reconciliation, and payroll export?
3. How should Elev8 OS synchronize the repository Blueprint with structured in-app architecture records without creating two sources of truth?
4. Which existing modules should be renamed or consolidated as engine capabilities?
5. Which legacy modules should be migrated first to concrete registered object adapters beyond the current Workspace types?

## Development Session Protocol

### Session Start

1. Read `BUSINESS_BLUEPRINT.md` from the uploaded repository.
2. Identify the owning engine and supporting engines.
3. Identify authoritative systems, objects, and relationships.
4. Check existing architectural decisions and open questions.
5. State how the work reduces operational burden and strengthens the Business Graph.

### Session End

Every build response and Blueprint update must include:

- Architecture Updates
- Engines Changed
- Business Graph Updates
- Architectural Decisions
- Open Questions
- Next Development Session

## Architecture Updates

### 2026-07-22 — Elev8 OS 16.0.0 — Business Blueprint Engine Foundation

**What changed:** Established the Platform Constitution, engine registry, Business Graph registry, workflow registry, architecture decisions, engine roadmap, technical debt, open questions, and development-session protocol. Added an in-app Blueprint workspace that reads this canonical repository document.

**Why:** Elev8 OS had reached the point where continued feature growth without explicit engine ownership risked fragmentation. The Blueprint becomes the durable architectural memory that future sessions must consult.

**Engines changed:** Knowledge and Intelligence. Organization, Operations, Communication, Booking, Financial, Commerce, Sales, Marketing, and supporting engines were formally registered.

**Business Graph changes:** Introduced the Blueprint as a Knowledge object connected to Engines, Workflows, Architecture Decisions, Business Objects, Roadmap items, Technical Debt, and Development Sessions.

**Architectural decisions:** Accepted ADR-0007, making this file the governing development source.

**Open questions:** Structured in-app editing and repository synchronization remain intentionally postponed.

**Next development session:** Define the Organization Engine and canonical multi-business Business Graph before expanding additional engines.

## Development Session History

### 2026-07-22 — Release 16.1.0

**Purpose:** Establish the Organization Engine foundation before expanding the Business Graph.  
**Architecture updates:** Added configurable organization units, hierarchy, person assignments, scoped access foundation, Organization workspace integration, and Organization workspaces.  
**Engines changed:** Organization (primary), Identity and Workflow (supporting).  
**Business Graph changes:** Added explicit Business, Brand, Location, Department, and Team nodes; added Person → Organization assignment relationships with responsibility, assignment type, dates, active state, and primary designation.  
**Architectural decisions:** Accepted ADR-0008. WordPress users remain authoritative Person identities; Organization Engine owns only their organizational relationships.  
**Open questions:** Shared-resource scoping and inheritance rules remain intentionally postponed.  
**Next recommended session:** Build the Business Graph Registry and relationship enforcement layer so engines can declare object ownership and organization scope consistently.
### 2026-07-22 — Release 16.1.1

**Purpose:** Correct the Organization Engine creation workflow discovered during initial company-map testing.

**Architecture updates:** Organization creation and organization editing now use explicit interface modes. Selecting an existing organization no longer prevents the owner from starting a separate organization record.

**Engines changed:** Organization Engine.

**Business Graph changes:** None. The fix changes only the creation workflow for existing Organization Unit objects.

**Architectural decision:** Creation actions must navigate to an explicit clean creation state rather than relying on an anchor that may not exist in an edit-state view.

**Open questions:** None introduced.

**Next recommended session:** Continue with the Business Graph Registry and Ownership Enforcement Layer after validating creation of multiple businesses and child organization units.



### 2026-07-22 — Elev8 OS 16.2.0 — Business Graph Registry & Ownership Enforcement

**What changed:** Added a canonical runtime registry for Business Graph objects, owning engines, authoritative systems, organization scope, and permitted relationship types. Added a CEO Business Graph workspace and enforced registry validation when explicit relationships are created. New relationship records preserve engine, authority, and organization-scope context without copying source records.

**Why:** The Organization Engine established the company structure, but the platform still needed one enforceable contract defining who owns each object and which links are architecturally valid. This prevents future engines from creating duplicate ownership or disconnected relationship models.

**Engines changed:** Organization, Workflow, Identity, Knowledge, and Integrations. All engines now have a shared registry contract.

**Business Graph changes:** Registered the initial canonical object types and relationship types. Added ownership, authority, and organization-scope metadata to newly created explicit graph relationships.

**Architectural decision:** Accepted ADR-0009.

**Open questions:** Concrete adapters for WooCommerce orders/products, Amelia bookings/classes, production jobs, repairs, memorial cases, and financial obligations must be progressively connected to the registry without duplicating those records.

**Next development session:** Build the first Integration Engine adapters for WooCommerce and Amelia so authoritative Commerce and Booking records participate in the Business Graph through stable references and organization scope.


### ADR-0010 — External Records Participate Through Read-Only Adapters

**Status:** Accepted  
**Decision:** WooCommerce products and orders and Amelia bookings and classes participate in the Business Graph through stable read-only adapter references. Elev8 OS may attach relationships, work, communication, organization scope, and intelligence, but it must not copy or replace authoritative source records.  
**Consequence:** Integration adapters must degrade to **Unavailable** when a source system or record cannot be verified.

## Development Session — 16.3.0

### Architecture Updates
Introduced the first Integration Engine adapters and repaired the Organization Workspace boundary. Organization assignments remain Identity-to-Organization relationships, while connected WooCommerce and Amelia records remain source-owned references.

### Engines Changed
Primary: Integrations. Supporting: Organization, Commerce, Booking, Identity, Workflow.

### Business Graph Updates
Product, Order, Booking, and Class can now resolve a trusted workspace summary from their authoritative systems. No source records were duplicated.

### Open Questions
The organization unit attached to each WooCommerce or Amelia record still needs configurable mapping rules by business, location, department, service, and sales channel.

### Next Development Session
Build configurable Integration Scope Mapping so WooCommerce stores/products/orders and Amelia services/locations/providers resolve into the correct Business, Brand, Location, and Department nodes.


### 2026-07-22 — Elev8 OS 17.0.0 — Operations Engine & Universal Work Inbox

**What changed:** Added a canonical Operations Engine service and frontend Work Inbox over the existing Work Item record. Added operational types, organization scope, requester/customer context, lifecycle timestamps, role-aware My Work and Team Work views, operational metrics, and connected-system signals.

**Why:** Operational work was distributed across modules and dashboards. The platform needed one reusable execution object and one answer to “What should happen next?” without duplicating production, booking, commerce, repair, memorial, or identity records.

**Engines changed:** Operations (primary), Workflow, Organization, Identity, Communication, Booking, and Integrations (supporting).

**Business Graph changes:** Work Item ownership moved from Workflow to Operations. Work Items may now carry organization scope and stable source references while Workflow continues to own reusable lifecycle mechanics.

**Architectural decision:** Accepted ADR-0011.

**Open questions:** Existing production jobs, repairs, memorial cases, class decisions, events, and recurring procedures still need explicit contributor adapters that create or expose Work Items without duplicating source state.

**Next development session:** Build Operations Contributor Adapters and SOP Execution so authoritative engine records can generate standardized work, checklists, approvals, and completion conditions through the shared Operations Engine.

### ADR-0012 — Operational Sources Contribute Work Through Adapters

**Status:** Accepted  
**Decision:** Authoritative operational systems contribute execution requirements to the Operations Engine through registered contributor adapters. Adapters create or synchronize stable Work Items, checklists, approvals, completion rules, due dates, assignments, escalation contracts, and timeline-ready source context while the source system continues to own its record and state.  
**Consequence:** Modules must not create parallel task systems for operational work. Contributor synchronization must be idempotent, source-referenced, and safe when source records are unavailable.

### 2026-07-22 — Elev8 OS 17.1.0 — Operations Contributor Adapter Foundation

**Architecture Updates:** Added the reusable Operations Contributor registry and synchronization service. Added the first adapter for Glass Production, Repairs, and Memorials. Glass job creation and updates now publish source-change events that synchronize one canonical Work Item instead of creating duplicate operational records.

**Why:** Production jobs, repairs, and memorial cases already contain authoritative operational state, but managers and contributors still need those records to appear automatically in the Universal Work Inbox with a clear execution contract.

**Engines Changed:** Operations (primary). Workflow, Assets, Financial, Organization, Identity, Communication, Automation, and Glass Operations (supporting).

**Business Graph Updates:** Added Operations Contributor as a registered graph object. Added stable relationships from Glass Job → contributed Work Item through source type, source ID, contributor key, workflow key, and step key. Work Items now expose checklist, approval, completion-rule, escalation, source-status, and last-synchronized context.

**Architectural Decisions:** Accepted ADR-0012. Source systems retain ownership; contributor adapters own translation into executable work. Synchronization is idempotent and closes contributed work when the source is completed or cancelled.

**Open Questions:** Organization-unit mapping for glass jobs remains intentionally postponed until configurable location/department scope mapping is available. Checklist item completion state and approval evidence need a reusable SOP Execution model rather than module-specific fields. Existing historical glass jobs are not automatically backfilled in this release.

**Next Development Session:** Build SOP Execution and checklist evidence for contributed Work Items, then add the Class Approval contributor adapter using Amelia references without duplicating bookings.

### ADR-0013 — Execution Contracts and Evidence Are Separate

**Status:** Accepted  
**Decision:** Operations Contributor adapters define immutable execution requirements, while the Workflow Engine records checklist completion, approval evidence, actor identity, timestamps, and audit history against the canonical Work Item. Source systems continue to own operational state.  
**Consequence:** Contributor synchronization may revise the current contract but must preserve compatible evidence. A Work Item cannot complete until its current execution contract is satisfied.

### 2026-07-22 — Elev8 OS 17.2.0 — SOP Execution & Completion Evidence

**Architecture Updates:** Added a reusable SOP Execution service for contributed Work Items. Execution contracts now produce persistent checklist progress, approval evidence, approval notes, actor identity, timestamps, and timeline entries. Contributor synchronization reconciles contracts without discarding compatible evidence. Completion is blocked when required evidence is incomplete.

**Why:** Version 17.1.0 could describe required work but could not prove it happened. A shared execution layer eliminates module-specific checklist fields and creates auditable operational completion across production, repairs, memorials, classes, maintenance, inventory, and events.

**Engines Changed:** Workflow (primary execution mechanics) and Operations (canonical Work Item lifecycle). Supporting engines: Knowledge, Identity, Communication, Automation, Assets, and Glass Operations.

**Business Graph Updates:** Added SOP Execution as a registered graph object linked to one canonical Work Item. Checklist evidence and approvals now carry actor and time context without duplicating the authoritative Glass Job, Repair, Memorial Case, or future source record.

**Architectural Decisions:** Accepted ADR-0013. Execution requirements and proof of execution are separate. Contributor adapters declare requirements; SOP Execution records evidence; Operations controls completion.

**Open Questions:** Approval authority is currently validated through Work Item access rather than per-approval capability policies. File/photo evidence and reusable SOP templates remain intentionally postponed. Historical contributed work can be reconciled when next synchronized but is not bulk-backfilled automatically.

**Next Development Session:** Add the Amelia-backed Class Approval Operations Contributor so pending class decisions automatically create synchronized approval and follow-through work without copying Amelia booking data.



### 2026-07-22 — Elev8 OS 17.3.0 — Class Approval Operations Contributor

**What changed:** Connected pending Amelia class bookings to the Operations Contributor framework. Each pending booking contributes one stable approval Work Item with assignment, urgency, checklist, approval evidence, completion rules, escalation, and source synchronization. Approve, move, cancel, and scheduled scans all synchronize the same Work Item.

**Why:** Class Approval already solved the authoritative booking decision, while Operations already solved shared work execution. Connecting them removes parallel task behavior and makes pending decisions visible in the Universal Work Inbox without copying Amelia data.

**Engines changed:** Booking (primary); Operations, Workflow, Integrations, Identity, Communication, and Organization (supporting).

**Business Graph changes:** Added the operational relationship Amelia Booking → Class Approval Contributor → Work Item. Provider assignment maps to a WordPress user only through the existing Amelia employee mapping. Organization scope remains configurable through a filter until a formal mapping policy is selected.

**Architectural decision:** Accepted ADR-0014. Booking decisions may contribute work, but Amelia remains authoritative for booking data and decision state.

**Open questions:** Automatic post-approval teaching preparation, customer communication confirmation, and location-to-organization mapping remain intentionally postponed.

**Next development session:** Build the Event Operations Contributor so event applications and scheduled events use the same Work Item, SOP evidence, assignment, and escalation architecture.


### ADR-0015 — Organization Assignments Grant Operational Reachability

**Status:** Accepted  
**Decision:** An active Organization Engine assignment makes a WordPress person eligible for internal communication and Work Item assignment, even when a legacy role capability has not yet been applied. Access to screens and privileged actions remains governed by the Centralized Access Service.  
**Consequence:** Organization membership and assignment eligibility no longer drift apart, while WordPress remains authoritative for identity and permissions.

### ADR-0016 — Event Applications Contribute Work Through Operations

**Status:** Accepted  
**Decision:** Event Applications remain authoritative for application state. Review, approval, planning, delivery, and follow-up are represented through the shared Operations Contributor and SOP Execution architecture. The former Takeover-specific workflow generator delegates to this adapter.  
**Consequence:** Events no longer maintain a parallel task workflow, and application-linked Work Items preserve Person and Relationship graph connections.

### 2026-07-22 — Elev8 OS 17.4.0 — Organization Recipient Alignment & Event Operations Contributor

**Architecture Updates:** Unified internal recipient selection and Work Item owner validation under one assignment-eligibility policy. Added the Event Operations Contributor and retired the parallel Takeover task generator by delegating it to the shared adapter.

**Why:** Shop employees entered through the Organization Engine were valid employees but invisible to Conversations because communication relied only on a legacy role capability. Event Applications also created a separate workflow outside the contributor architecture. Both issues represented duplicate policies that could drift.

**Engines Changed:** Events (primary contributor milestone); Organization, Identity, Communication, Operations, Workflow, CRM, and Automation (supporting).

**Business Graph Updates:** Active Organization Assignment now establishes operational reachability for its Person. Added Event Application → Event Operations Contributor → Work Item, preserving links to Person and Relationship without copying application data.

**Architectural Decisions:** Accepted ADR-0015 and ADR-0016.

**Open Questions:** Organization assignment type-to-communication-group policies remain configurable future work. Scheduled event records beyond applications still need a canonical event object adapter. Event inventory reservations and financial outcome reconciliation remain intentionally postponed.

**Next Development Session:** Build the Inventory Operations Contributor and define canonical stock-exception workflows for low stock, receiving, cycle counts, and event reservations without duplicating WooCommerce product data.

### ADR-0017 — Inventory Authority and Inventory Execution Are Separate

**Status:** Accepted  
**Decision:** WooCommerce or another configured inventory provider remains authoritative for product identity and quantity. Elev8 OS owns canonical Inventory Signals for exceptions that require human action, and those signals contribute execution through the shared Operations and SOP architecture.  
**Consequence:** Low stock, receiving, cycle counts, discrepancies, and event reservations use one execution model without cloning product records or creating a parallel stock ledger.

### 2026-07-22 — Elev8 OS 17.5.0 — Inventory Operations Contributor

**Architecture Updates:** Added the Inventory Signal service and Inventory Operations Contributor. WooCommerce stock changes and a daily reconciliation scan create, update, or resolve stable low-stock signals. The same signal model supports receiving, cycle counts, discrepancies, and event inventory reservations through a public service API.

**Why:** Inventory exceptions previously appeared only as notes or future recommendations. Managers need those exceptions to become assigned, auditable work while WooCommerce continues to own product and stock data.

**Engines Changed:** Inventory (primary); Commerce, Integrations, Operations, Workflow, Organization, Identity, Communication, Automation, and Events (supporting).

**Business Graph Updates:** Added Inventory Signal as a canonical Elev8 OS graph object. Added WooCommerce Product → Inventory Signal → Work Item. Event reservations may connect an Event reference to the same Inventory Signal without copying the Event or Product.

**Architectural Decisions:** Accepted ADR-0017. Inventory data authority and inventory execution are separate. Product quantity remains in the configured authority; exception state, assignment, checklist evidence, approval, escalation, and timeline belong to Elev8 OS.

**Open Questions:** A configurable inventory-provider registry is still needed for businesses that do not use WooCommerce stock. Location-specific stock requires an authoritative multi-location provider before location quantities can be automated. The first release exposes receiving, cycle-count, discrepancy, and event-reservation creation through the Inventory Signal service; dedicated management screens remain intentionally postponed.

**Next Development Session:** Build the Maintenance Operations Contributor so assets, equipment, facilities, recurring inspections, repair requests, and preventive maintenance contribute work through the same Operations and SOP execution architecture.

### ADR-0018 — Maintenance Condition and Execution Are Separate

**Status:** Accepted  
**Decision:** Authoritative asset and facility systems own identity, location, custody, and base condition. Elev8 OS owns the canonical Maintenance Record for reported conditions, service schedules, inspection findings, repair history, recurrence, and operational state. Maintenance Records contribute execution through the shared Operations Contributor and SOP Execution architecture.  
**Consequence:** Equipment repairs, facility issues, preventive maintenance, inspections, and safety checks use one auditable execution model without turning the Asset Engine into a second task system or copying facility records.

### 2026-07-22 — Elev8 OS 17.6.0 — Maintenance Operations Contributor

**Architecture Updates:** Added the Maintenance Record service and Maintenance Operations Contributor. Existing Maintenance Logs now create canonical Maintenance Records that synchronize one stable Work Item. The service also supports asset repairs, preventive maintenance, recurring inspections, safety checks, daily due reconciliation, overdue escalation, and service-history queries.

**Why:** Maintenance issues were captured as logs or generic Work Items without a canonical maintenance lifecycle. A shared maintenance source preserves condition and service history while Operations handles execution, assignment, evidence, approval, and escalation.

**Engines Changed:** Operations (primary); Assets, Workflow, Organization, Identity, Knowledge, Communication, and Automation (supporting).

**Business Graph Updates:** Added Maintenance Record as a canonical graph object. Added Asset or Facility → Maintenance Record → Work Item → SOP Execution Evidence. Recurring service creates a new maintenance instance linked to the prior record rather than reopening historical evidence.

**Architectural Decisions:** Accepted ADR-0018. Asset and facility identity remain authoritative in their owning engines; maintenance condition, schedule, service history, and execution state belong to Elev8 OS.

**Open Questions:** A dedicated equipment/facility registry and user interface remain intentionally postponed. Recurrence currently uses day intervals; calendar-based rules and vendor service agreements need a reusable scheduling policy. Photo/file evidence continues to depend on the future shared evidence attachment capability.

**Next Development Session:** Build the Daily Operations Log Contributor so manager, retail, artist, vendor, and event logs can extract explicit follow-up obligations into synchronized Work Items without turning every observation into a task or duplicating the original log.

### ADR-0019 — Facts Precede Actions

**Status:** Accepted  
**Decision:** Engines contribute verified facts as Observations before Intelligence or Operations interprets them. An Observation is not a task. A Work Item is created only when an authoritative source explicitly declares follow-up or operational action.  
**Consequence:** Intelligence can analyze risks, opportunities, achievements, decisions, and trends without flooding the Universal Work Inbox or duplicating source records.

### 2026-07-22 — Elev8 OS 18.0.0 — Observation Engine & Daily Operations Intelligence Foundation

**What changed:** Added the canonical Observation object and service, stable source-key synchronization, classification, severity, confidence, organization scope, related-object references, Intelligence queries, Daily Operations Log contribution, explicit follow-up Work Item creation, and Observation summaries in the Daily Executive Brief.

**Why:** Elev8 OS needed one shared language for verified business facts before cross-engine intelligence, recommendations, pattern detection, and AI reasoning could mature. Daily logs now contribute facts without automatically treating every note as work.

**Engines changed:** Intelligence (primary); Operations, Workflow, Knowledge, Organization, Analytics, Automation, and Communication (supporting).

**Business Graph changes:** Added Source → Observation, Observation → Work Item, and Observation → Knowledge/Conversation evidence relationships. Operations Logs remain authoritative source records; Observations store structured interpretation and Work Items store only explicit execution.

**Architectural decisions:** Accepted ADR-0019, Facts Precede Actions.

**Open questions:** Reusable classifier registration, controlled historical backfill, observation review/correction workflows, and cross-source pattern thresholds remain intentionally postponed.

**Next development session:** Build the Observation Registry and Intelligence Review workspace, then connect Inventory Signals, Maintenance Records, Events, Booking decisions, Conversations, and commerce events as verified Observation contributors.

## Development History — 18.1.0

- Added the Intelligence Review workspace and Observation Registry.
- Added review audit metadata and lifecycle states.
- Connected Inventory, Maintenance, Events, Booking, and Communication as verified Observation contributors.
- Preserved source authority and stable source keys to prevent duplicate facts.


### ADR-0021 — Patterns Summarize Confirmed Facts Without Becoming Actions

**Decision:** The Intelligence Engine may detect a Pattern only from confirmed or corrected Observations. A Pattern stores the supporting Observation relationships, frequency, time range, confidence, severity, and trend. Patterns do not modify authoritative source records and do not create Work Items automatically.  
**Why:** Repetition is useful intelligence, but automatically converting repetition into execution would bypass human governance and create task overload.  
**Consequence:** Leaders acknowledge, dismiss, or resolve Patterns in the Intelligence Review workspace before future recommendation or automation layers use them.

### 2026-07-22 — Elev8 OS 18.2.0 — Cross-Source Pattern Detection

**What changed:** Added the canonical Pattern object, a daily and on-demand detector, stable pattern fingerprints, grouping by shared Business Graph object, meaningful tag, or source type, and a Pattern Review view inside the Intelligence workspace. Patterns show supporting occurrence count, first and last occurrence, confidence, severity, trend, and review state.

**Engines changed:** Intelligence (primary), Analytics, Organization, Inventory, Operations, Events, Booking, Communication, and Workflow (supporting).

**Business Graph changes:** Added Observation → Pattern evidence relationships. Authoritative records remain the source of facts; Observations remain the verified interpretation layer; Patterns summarize repetition across those facts.

**Open questions:** Configurable thresholds, semantic topic grouping, cross-organization comparisons, pattern-to-recommendation promotion rules, and AI-assisted pattern naming remain intentionally postponed.

**Next development session:** Build the Recommendation Promotion layer so acknowledged Patterns can produce explainable, evidence-linked recommendations without automatically creating Work Items or changing authoritative records.

### ADR-0022 — Recommendations Require Explicit Promotion and Execution Approval

**Decision:** An acknowledged Pattern may be promoted into one stable Recommendation containing supporting Observation evidence, confidence, expected benefit, a suggested owner, and a suggested next action. A Recommendation remains an Intelligence object until a leader explicitly approves execution. Approval creates one linked Operations Work Item; rejection creates no work and changes no source record.

**Consequence:** Elev8 OS preserves a governed chain from fact to action: authoritative record → Observation → human review → Pattern → acknowledgement → Recommendation → execution approval → Work Item. Intelligence may explain and propose, but Operations acts only after explicit authorization.

### 2026-07-22 — Elev8 OS 18.3.0 — Recommendation Promotion & Governed Execution

**What changed:** Added the canonical Recommendation object, stable promotion from acknowledged Patterns, evidence-linked action and benefit explanations, suggested ownership, a Recommendations view in the Intelligence workspace, and explicit approve/reject governance. Approved Recommendations create one Operations Work Item without duplication.

**Engines changed:** Intelligence (primary); Operations, Workflow, Organization, Analytics, and Automation (supporting).

**Business Graph changes:** Added Pattern → Recommendation and Recommendation → Work Item relationships. Recommendations preserve supporting Observation IDs and do not alter Patterns or authoritative records.

**Open questions:** Configurable recommendation templates, organization-specific approval policies, owner suggestion rules, financial value estimates, and recommendation expiration remain intentionally postponed.

**Next development session:** Build Recommendation outcome tracking so Elev8 OS can compare approved actions with completed work and measured results before introducing automated recommendation ranking.
### ADR-0023 — Intelligence Learns From Governed Outcomes

**Status:** Accepted  
**Decision:** Completing an approved Recommendation's Work Item proves execution, but it does not prove business success. Elev8 OS creates one Recommendation Outcome when execution completes, then requires a leader to record the measured result and optional before/after evidence. Recommendation performance scores use only measured Outcomes.  
**Consequence:** Intelligence can learn from organizational history without assuming that completed work was effective or rewriting the supporting Pattern, Observations, Recommendation, or authoritative source records.

### 2026-07-22 — Elev8 OS 18.4.0 — Recommendation Outcome Tracking

**What changed:** Added the canonical Recommendation Outcome object, automatic creation after linked Work Item completion, human-governed outcome evaluation, optional before/after metric evidence, and an explainable recommendation-performance score service.

**Why:** The intelligence chain could identify facts, detect Patterns, propose Recommendations, and authorize execution, but it could not distinguish completed activity from an effective business result. Outcome tracking closes that loop without allowing Intelligence to invent success.

**Engines changed:** Intelligence (primary); Analytics, Operations, Workflow, Organization, and Financial (supporting).

**Business Graph changes:** Added Recommendation → Recommendation Outcome and Recommendation Outcome → Work Item evidence relationships. Outcomes preserve the measured result while the Work Item remains the execution record.

**Open questions:** Automatic metric-provider adapters, financial ROI calculation, outcome review reminders, and confidence recalibration remain intentionally postponed until enough measured history exists.

**Next development session:** Build the Executive Intelligence layer so leaders can see the highest-priority risks, strongest opportunities, recommendation performance, and the best use of executive attention from governed Business Graph evidence.


### ADR-0024 — Executive Attention Is a Governed Read Model

**Status:** Accepted  
**Decision:** Executive Intelligence ranks existing governed Observations, Patterns, Recommendations, Work Items, and measured Outcomes into an explainable attention view. It is not an authoritative system and may not create facts, approve execution, infer success, or rewrite source records. Ranking uses transparent factors including severity, recurrence, trend, confidence, governance status, and whether a decision or outcome is still waiting.

**Consequence:** Leaders receive a prioritized view of where attention is most valuable without introducing another dashboard-owned business system. Every executive item remains traceable to the governed Business Graph evidence that produced it.

### 2026-07-22 — Elev8 OS 18.5.0 — Executive Intelligence

**What changed:** Added an Executive Intelligence view to the Intelligence workspace, explainable risk and opportunity ranking, a best-use-of-attention queue, Recommendation decision and outcome-measurement visibility, recommendation-performance scoring, and confidence explanations.

**Why:** Elev8 OS could govern facts, detect repetition, recommend action, execute approved work, and measure results, but leaders still had to review each intelligence layer separately. Executive Intelligence unifies those governed layers into a decision-ready read model without creating a competing source of truth.

**Engines changed:** Intelligence (primary); Analytics, Operations, Workflow, Organization, Communication, and Financial (supporting).

**Business Graph changes:** No new authoritative object was introduced. Added an executive read path across Observation → Pattern → Recommendation → Work Item → Recommendation Outcome while preserving every existing ownership boundary.

**Open questions:** Organization-specific executive weighting, department comparisons, financial ROI adapters, attention-item snoozing, and scheduled executive delivery remain intentionally postponed.

**Next development session:** Build the Executive Brief delivery and attention governance layer so leaders can receive a scheduled summary, acknowledge or defer attention items, and retain a decision timeline without changing the underlying intelligence evidence.

### ADR-0025 — Executive Attention Decisions Are Governed Evidence

**Status:** Accepted  
**Decision:** A leader may acknowledge, defer, resolve, or reopen an Executive Intelligence attention item without changing the Pattern, Recommendation, Observation, Work Item, Outcome, or authoritative source that produced it. Each decision is stored as durable governance evidence with a stable attention key, user, timestamp, optional note, and defer-until date. Executive Brief delivery uses the Communication Engine boundary and configurable per-user policies.

**Consequence:** The executive attention queue can support reminders and scheduled summaries without becoming a second task system. Deferred and resolved items leave the actionable queue while preserving a complete decision timeline. Delivery never changes intelligence evidence or approves operational execution.

### 2026-07-22 — Elev8 OS 18.6.0 — Executive Brief Delivery & Attention Governance

**What changed:** Added configurable Executive Brief email delivery, daily or weekday schedules, manual test delivery, stable attention-item governance, acknowledge/defer/resolve/reopen controls, actionable-queue filtering, and an executive decision timeline.

**Why:** Executive Intelligence could rank what deserved attention, but it could not preserve how a leader responded or deliver that governed view on a useful schedule. This release closes that operational gap without turning the executive read model into a competing task or notification system.

**Engines changed:** Intelligence (primary); Communication, Automation, Organization, Identity, Analytics, Operations, and Workflow (supporting).

**Business Graph changes:** Added Executive Attention Decision as governance evidence connected by a stable key to a Pattern or Recommendation attention projection. Added Executive Brief Delivery as a Communication event derived from the current governed read model. No authoritative Intelligence object changes ownership.

**Open questions:** Organization-scoped recipient policies, multi-channel delivery, digest localization, escalation recipients, and delivery analytics remain intentionally postponed.

**Next development session:** Build the Executive Decision Follow-through layer so acknowledged attention can intentionally become a decision record, delegated review, or approved operational action while preserving the existing Recommendation and Work Item governance boundaries.

### ADR-0026 — Executive Follow-through Reuses Existing Governance Boundaries

**Status:** Accepted  
**Decision:** An acknowledged Executive Intelligence attention item may intentionally become a formal decision record, delegated review, approved operational action, or scheduled follow-up. Formal decisions remain governance evidence. Delegated reviews and scheduled follow-ups create standard Operations Work Items. Approved operational actions are available only for governed Recommendations and must route through the existing Recommendation approval service.

**Consequence:** Executive Intelligence can move from attention to accountable follow-through without creating a second task, approval, or decision system. Every follow-through record retains a stable connection to the attention projection and underlying Pattern or Recommendation, while Operations and Recommendation services remain authoritative for execution.

### 2026-07-22 — Elev8 OS 18.7.0 — Executive Decision Follow-through

**What changed:** Added durable Executive Decision Follow-through records, formal decision capture, delegated reviews, scheduled follow-ups, approved Recommendation actions, duplicate protection, linked Work Items, and a follow-through timeline inside Executive Intelligence.

**Why:** Executive attention governance could preserve acknowledgement, deferral, and resolution, but it could not intentionally convert leadership attention into accountable follow-through. This release closes that gap while reusing the existing Recommendation and Operations boundaries.

**Engines changed:** Intelligence (primary); Operations, Workflow, Organization, Identity, Communication, and Automation (supporting).

**Business Graph changes:** Added Executive Decision Follow-through as governance evidence connected to a Pattern or Recommendation attention projection. Added an optional relationship from Executive Decision Follow-through to Work Item for delegated review and scheduled follow-up. Approved operational actions continue to use Recommendation → Work Item.

**Open questions:** Follow-through completion synchronization, organization-specific decision templates, escalation rules, decision attachments, and meeting or calendar integration remain intentionally postponed.

**Next development session:** Build Executive Follow-through Completion and Decision Effectiveness so completed delegated reviews and scheduled follow-ups can close their governance records and feed measurable executive decision outcomes without duplicating Work Item or Recommendation Outcome data.

### ADR-0027 — Execution Completion and Decision Effectiveness Are Separate Evidence

**Status:** Accepted  
**Decision:** Completion of a delegated review or scheduled executive follow-up closes its Executive Decision Follow-through record because the Operations Work Item is authoritative for execution. Elev8 OS then creates one Executive Decision Outcome awaiting leader measurement. Approved Recommendation actions continue to use Recommendation Outcome and may not create a duplicate Executive Decision Outcome.

**Consequence:** Leadership can distinguish whether assigned follow-through was completed from whether the executive decision was effective. The Work Item remains execution evidence, Recommendation Outcome remains authoritative for Recommendation-backed actions, and Executive Decision Outcome measures only follow-through that has no existing governed outcome object.

### 2026-07-22 — Elev8 OS 18.8.0 — Executive Follow-through Completion & Decision Effectiveness

**What changed:** Added automatic Work Item completion synchronization, completed follow-through states, governed Executive Decision Outcomes, before/after metric evidence, effectiveness review controls, and effectiveness summaries.

**Why:** Executive follow-through could create accountable work, but completing that work did not close the governance record or establish whether the leadership decision produced the intended result. This release closes the loop without equating completed activity with business effectiveness.

**Engines changed:** Intelligence (primary); Operations, Workflow, Analytics, Organization, and Automation (supporting).

**Business Graph changes:** Added Executive Decision Follow-through → Executive Decision Outcome and Executive Decision Outcome → Work Item evidence relationships. Recommendation-backed actions continue to use Recommendation → Recommendation Outcome → Work Item.

**Open questions:** Decision-effectiveness reminder timing, financial ROI adapters, attachment evidence, organization-specific effectiveness measures, and confidence recalibration remain intentionally postponed.

**Next development session:** Build Decision Learning and Confidence Calibration so measured Recommendation Outcomes and Executive Decision Outcomes can influence future recommendation confidence through transparent organization-specific evidence rather than opaque AI scoring.

### ADR-0028 — Confidence Calibration Uses Measured Comparable Outcomes

**Decision:** Recommendation confidence may be adjusted only by measured Recommendation Outcomes and Executive Decision Outcomes that share the same organization scope and intelligence classification. The original Pattern-derived confidence remains preserved as the base score. Calibration requires at least three comparable measured outcomes, excludes unknown results, and is capped at plus or minus 15 points.

**Consequence:** Elev8 OS learns from the business's own measured history without opaque scoring. A calibrated confidence score remains advisory evidence only; it cannot approve a Recommendation, create a Work Item, alter an Observation or Pattern, or rewrite an Outcome.

### 2026-07-22 — Elev8 OS 18.9.0 — Decision Learning & Confidence Calibration

**What changed:** Added organization-specific and classification-specific outcome learning, transparent confidence adjustment, minimum evidence thresholds, bounded calibration, and visible confidence explanations in the Recommendation workspace.

**Why:** Elev8 OS could measure results but could not yet use those results to improve future intelligence. This release closes that learning loop while preserving human governance and explainability.

**Engines changed:** Primary: Intelligence. Supporting: Analytics, Organization, Operations, Workflow.

**Business Graph changes:** Added measured Recommendation Outcome and Executive Decision Outcome evidence as a governed calibration input for future Recommendations. No new authoritative object or duplicate scoring ledger was introduced.

**Open questions:** Whether future calibration should incorporate time decay, location-level scope, financial value, or configurable evidence weights remains intentionally postponed until enough real outcome history exists.

**Next development session:** Build the Executive Learning Dashboard and calibration health controls so leaders can inspect evidence coverage, identify where outcomes are missing, and understand which recommendation classes have enough history to support dependable learning.
### ADR-0029 — Learning Health Is a Governed Read Model

**Status:** Accepted  
**Decision:** Calibration health must be calculated from existing Recommendation Outcomes and Executive Decision Outcomes without creating a parallel score ledger. Readiness is organization-specific and classification-specific, uses the same minimum evidence threshold as Decision Learning, and exposes missing measurement rather than inferring success from completed work.

**Consequence:** Leaders can see where Elev8 OS has dependable evidence and where it cannot yet learn responsibly. The Learning Health view cannot change confidence, approve Recommendations, create Work Items, or modify Outcomes.

### 2026-07-22 — Elev8 OS 18.10.0 — Executive Learning Dashboard & Calibration Health

**What changed:** Added an Executive Learning Health workspace with organization-scope filtering, calibration coverage, classification-level readiness, positive/neutral/negative result history, missing-outcome counts, and explicit leadership guidance.

**Why:** Confidence calibration existed, but leaders could not see whether its evidence was dependable or where missing measurements prevented learning. This release makes the quality and limits of organizational learning visible.

**Engines changed:** Intelligence (primary); Analytics, Organization, Operations, and Workflow (supporting).

**Business Graph changes:** Added no new authoritative object. Recommendation Outcomes and Executive Decision Outcomes now feed a governed Learning Health read model that explains calibration coverage.

**Open questions:** Time decay, financial-value weighting, location-level scope, configurable evidence thresholds, and classification-specific weights remain intentionally postponed until sufficient real outcome history exists.

**Next development session:** Build Outcome Measurement Governance and reminders so completed actions awaiting measurement are assigned, surfaced, and followed up through the existing Communication and Automation engines without treating missing measurement as operational failure.

## Development History — 18.11.0

### Plugin Usage Discovery and Migration Readiness

The Integrations Engine now provides a read-only Platform Compatibility workspace. It inventories installed plugins and collects dependency evidence from page and post shortcodes, serialized block markup, registered block namespaces, custom post types, non-core database tables, and scheduled cron hooks. Evidence is grouped against installed plugins using stable plugin identity and explicit namespace aliases where plugin marketing names do not match their technical namespaces.

This capability never activates, deactivates, updates, deletes, or configures plugins. A missing finding is not treated as proof that a plugin is safe to remove because runtime PHP calls, remote webhooks, theme dependencies, and external automations may exist outside the discoverable WordPress registries.

**Primary engine:** Integrations.

**Supporting engines:** Identity, Booking, Commerce, Communication, Inventory, Knowledge, Automation, Analytics, and Organization.

**Business Graph changes:** Added a read-only dependency-evidence projection connecting an installed Plugin to discovered Shortcode Usage, Block Usage, Custom Post Type, Database Table, Scheduled Hook, and Registered Block. These are migration-planning relationships, not new authoritative business records.

### ADR-0030 — Plugin Changes Require an Evidence-Based Migration Boundary

A plugin may be retired only after capability ownership, authoritative data, stored records, public-page dependencies, scheduled work, external integrations, migration steps, Local validation, and rollback have been documented. Elev8 OS may recommend a migration sequence but must not silently change the installed stack.

### ADR-0031 — Dependency Discovery Is Evidence, Not Permission

Automated discovery can identify visible dependencies and reduce audit effort, but it cannot prove the absence of runtime or external dependencies. Therefore a clean scan may qualify a plugin for manual review, but never authorizes deactivation or deletion.

**Technical debt:** Plugin-to-namespace matching currently uses deterministic token and alias rules. Future releases should support administrator-confirmed ownership mappings and integration-adapter declarations.

**Open questions:** Theme-level PHP calls, remote Make scenarios, webhook consumers, license dependencies, and network-level multisite behavior require separate evidence sources before automated migration plans can be considered complete.

**Next milestone:** Build administrator-confirmed dependency ownership and migration-plan records, then add Local-only retirement rehearsals with validation checklists and rollback evidence.

## Development History — 18.12.0

### Administrator-Confirmed Dependency Ownership and Migration Plans

The Integrations Engine now stores durable Plugin Migration Plan records for installed plugins. Administrators can confirm the current capability owner, authoritative data boundary, intended Elev8 OS replacement Engine, required migration, pages and workflows to test, external dependencies, retirement blockers, Local rehearsal evidence, validation results, rollback instructions, and final approval notes.

Dependency discovery remains read-only evidence. The new plan is governed human documentation layered over that evidence. Neither a discovery result nor a complete plan can activate, deactivate, update, delete, or configure a plugin.

**Primary engine:** Integrations.

**Supporting engines:** Organization, Identity, Booking, Commerce, Communication, Inventory, Knowledge, Workflow, Automation, Analytics, and every Engine selected as a planned replacement owner.

**Business Graph changes:** Added the governed Plugin Migration Plan object and relationships from Installed Plugin to Confirmed Capability Owner, Authoritative Data Boundary, Replacement Engine, Migration Evidence, Local Rehearsal, Rollback Plan, and Final Approval.

### ADR-0032 — Administrator Confirmation Governs Plugin Ownership and Retirement Planning

**Status:** Accepted  
**Decision:** Automated dependency discovery may suggest evidence, but an administrator must explicitly document capability ownership, authoritative data, replacement responsibility, migration steps, test scope, blockers, Local rehearsal, rollback, and approval before retirement can be considered. A plan record is evidence and authorization history; it is not an execution mechanism.

**Consequence:** Elev8 OS gains a durable, auditable migration boundary without becoming a plugin manager. Retirement remains a separate intentional action performed only after successful Local validation.

**Technical debt:** Migration plans currently use administrator-entered evidence. Future integration adapters should declare their ownership boundaries and migration capabilities so plans can be prefilled without becoming automatically approved.

**Open questions:** Whether final retirement approval should require two people, whether Local and Live environments should be cryptographically linked, and how multisite network plugins should be governed remain intentionally postponed.

**Next milestone:** Build Local-only retirement rehearsal governance with required validation checklists, environment confirmation, before-and-after evidence, and rollback verification.

## Development History — 19.0.0

### Business Coaching Engine Foundation

The Intelligence Engine now provides a role-aware Business Coaching read model. Coaching projects governed Work Items, confirmed Patterns, and Recommendations into practical guidance for owners, managers, employees, artists, teachers, event hosts, volunteers, and glass teams. Every coaching card explains why it exists and links back to the governed evidence that produced it.

Coaching owns only per-user presentation state: unread, read, pinned, needs follow-up, or dismissed. It cannot create or alter Observations, Patterns, Recommendations, Work Items, approvals, outcomes, or authoritative business records.

**Primary engine:** Intelligence.

**Supporting engines:** Operations, Organization, Identity, Analytics, Workflow, Communication, CRM, and Knowledge.

**Business Graph changes:** Added a non-authoritative Coaching Projection relationship from Pattern, Recommendation, and Work Item evidence to a role-specific user view. No duplicate fact, recommendation, or task object was introduced.

### ADR-0033 — Business Coaching Is Explainable Guidance

**Status:** Accepted  
**Decision:** Business Coaching is a personalized read model over governed Business Graph evidence. It may prioritize, explain, and present suggested next steps, but it may not create facts, promote Patterns, approve Recommendations, create Work Items, or modify authoritative records. Only personal presentation state belongs to Coaching.

**Consequence:** Elev8 OS can proactively guide each role without creating another recommendation or task system. Every coaching card remains traceable to Operations or Intelligence evidence, and users may organize their own view without changing shared business truth.

**Technical debt:** The initial coaching rules are deterministic and role-based. Future releases should add configurable coaching policies, organization-scoped preferences, delivery timing, and measured coaching usefulness before adding language-model generation.

**Open questions:** Whether a needs-follow-up coaching state should optionally propose a governed Work Item, how coaching should be delivered on mobile, and how users should rate usefulness remain intentionally postponed.

**Next milestone:** Build the Proactive Daily Assistant, combining role-aware Coaching, assigned Work, conversations, and permitted attention signals into a concise personal start-of-day briefing without duplicating the Executive Brief.
## Development History — 19.1.0

### Proactive Daily Assistant

The Experience layer now provides a personal start-of-day briefing that combines permitted Attention items, assigned Work, unread Conversations, and role-aware Business Coaching into one concise answer to “What should I focus on today?” The briefing ranks existing governed evidence and links users back to the authoritative Operations, Communication, or Intelligence record.

The Daily Assistant is intentionally different from the Executive Brief. The Executive Brief summarizes business-wide leadership intelligence and may be delivered on a schedule. The Daily Assistant is an on-demand personal projection for every role and is limited by that user’s permissions.

**Primary engine:** Intelligence.

**Supporting engines:** Operations, Communication, Organization, Identity, Workflow, Analytics, and Experience.

**Business Graph changes:** Added a non-authoritative Daily Assistant Projection relationship from Work, Conversation, Attention Projection, and Coaching Projection to a personal start-of-day view. No new business fact, task, conversation, or recommendation object was introduced.

### ADR-0034 — The Daily Assistant Is a Personal Governed Read Model

**Status:** Accepted  
**Decision:** The Proactive Daily Assistant may rank and present evidence the user is already permitted to see, but it may not create Work Items, approve Recommendations, change Conversations, or replace the user’s role-based Operational Home. It is a personal read model and owns only its last-viewed presentation timestamp.

**Consequence:** Every role receives a useful start-of-day experience without duplicating the Executive Brief, Coaching Engine, Attention Center, Conversations, or Operations logic. Source systems remain authoritative and all focus items remain traceable.

**Technical debt:** The initial focus ranking is deterministic. Future releases should add user preference controls, configurable start-of-day delivery, usefulness feedback, and organization-specific focus policies before any language-model summarization.

**Open questions:** Whether Today should optionally become a user-selected landing page, whether reminders should be delivered through the Communication Engine, and how often the briefing should refresh automatically remain intentionally postponed.

**Next milestone:** Build Daily Assistant preferences and delivery governance so users can choose permitted delivery timing, focus categories, and notification channels without turning the assistant into an automatic task or decision system.
## Development History — 19.2.0

### Daily Assistant Preferences and Delivery Governance

The Proactive Daily Assistant now supports personal delivery preferences for timing, weekdays or daily cadence, permitted focus categories, reminder emphasis, and in-app or email channels. Scheduled delivery uses WordPress Cron through the Automation boundary and email uses the shared Communication Engine notification service. The Today workspace remains available on demand regardless of delivery settings.

The delivery layer stores only personal preferences and delivery timestamps. It does not create Work Items, change Conversations, alter Coaching, approve Recommendations, or copy source evidence. Preference-aware briefing generation filters the existing Daily Assistant projection rather than creating a second briefing model.

**Primary engine:** Intelligence.

**Supporting engines:** Communication, Automation, Operations, Organization, Identity, Workflow, Analytics, and Experience.

**Business Graph changes:** Added a governed projection relationship from Daily Assistant Projection to Communication Delivery. Delivery timing and channel records remain personal governance evidence and do not become authoritative business objects.

### ADR-0035 — Daily Assistant Delivery Is Personal Preference Governance

**Status:** Accepted  
**Decision:** Daily Assistant delivery may schedule and transport a user-permitted briefing through existing Automation and Communication boundaries. It may not create actions, escalate source evidence, modify shared records, or infer that a delivered briefing was read or acted upon.

**Consequence:** Every role can receive a useful briefing at a chosen time without introducing a parallel notification or workflow system. Email transport remains replaceable through the Notification Service, including future AWS SES integration.

**Technical debt:** Delivery currently uses hourly WordPress Cron resolution. Future infrastructure adapters may provide more precise scheduling and delivery telemetry without changing the preference contract.

**Open questions:** Whether users should choose Today as their optional landing page, whether mobile push should become a supported Communication channel, and how usefulness feedback should influence presentation remain intentionally postponed.

**Next milestone:** Build Daily Assistant usefulness feedback and focus-policy governance so users and organizations can improve ranking transparently before any language-model summarization is introduced.
## Development History — 19.3.0

### IT Support Operations Capability

IT Support is now a configurable business capability and role-based workspace over the existing Operations architecture. It does not create an IT Engine, formal department requirement, or parallel ticket system. Technology incidents are specialized Maintenance Records connected to an affected technology asset or system, organization scope, location, reporter, assigned support person, Work Item, SOP evidence, resolution, and service history.

Critical incidents affecting checkout, payments, internet, security, or essential operations receive urgent priority and immediate escalation rules. Assigned support users receive the same Work Inbox and Daily Assistant experience as every other operational owner, while leaders retain visibility through Operations and Intelligence.

**Primary engine:** Operations.

**Supporting engines:** Assets, Organization, Communication, Workflow, Knowledge, Automation, and Intelligence.

**Business Graph changes:** Added the IT Incident projection over the authoritative Maintenance Record and relationships to technology Asset, Location/Organization Unit, reporter, assigned support person, Work Item, SOP Evidence, Observation, Pattern, and service history. No duplicate asset, maintenance, ticket, or task record was introduced.

### ADR-0036 — IT Support Is a Configurable Operations Capability

**Status:** Accepted  
**Decision:** IT Support must reuse Asset, Maintenance, Operations, Workflow, Communication, Automation, and Intelligence services. A business may assign one person or several people to IT Support without creating a formal department.

**Consequence:** Technology incidents enter the same accountable execution and intelligence architecture as other operational conditions. Future IT dashboards, asset replacement planning, knowledge articles, and automation can extend the shared records without creating a silo.

**Technical debt:** Asset selection currently accepts a technology label and optional existing asset identifier. A dedicated technology-asset picker, software/license relationships, provider outage adapters, attachment evidence, and account-access security workflow remain future work.

**Open questions:** Whether critical incidents should support SMS or push delivery, how secrets and privileged credentials should be handled without storing them in incident notes, and which external monitoring providers should contribute automatic incidents remain intentionally postponed.

**Next milestone:** Build Daily Assistant usefulness feedback and organization focus-policy governance, including transparent ranking feedback and organizational priority rules, while allowing IT incidents to participate through existing Work and Coaching evidence.

## Development History — 19.4.0

### Focus Intelligence and Organization Policy Governance

The Proactive Daily Assistant now ranks focus items through an explainable Focus Projection. Every displayed score retains a visible breakdown of source evidence, severity, due-state, executive or Pattern context, Organization Unit policy, and the current user's presentation feedback. Leaders may configure bounded focus weights for an Organization Unit, while users may mark an item Helpful, Already handled, or Not relevant.

Focus policy and feedback are presentation governance only. They do not change Work Item priority or status, alter Conversations, dismiss Recommendations, modify Patterns, or rewrite authoritative business facts.

**Primary engine:** Intelligence.

**Supporting engines:** Organization, Operations, Communication, Workflow, Analytics, and Experience.

**Business Graph changes:** Added a non-authoritative Focus Projection relationship from Work, Conversation, Attention Projection, Coaching Projection, and Organization Policy to the Daily Assistant. No duplicate task, priority, fact, or recommendation object was introduced.

### ADR-0037 — Focus Ranking Must Be Explainable

**Status:** Accepted  
**Decision:** Every Daily Assistant focus score must be traceable to governed evidence and bounded Organization Unit policy. Personal usefulness feedback may influence only the user's future presentation. No opaque score may determine operational priority, and Focus Intelligence may not modify authoritative records.

**Consequence:** Elev8 OS can improve daily relevance without creating hidden automation or allowing presentation preferences to become business truth.

**Technical debt:** The first policy model uses numeric weights. Future releases should add named policy templates, inherited Organization policies, calibration reporting, and controlled experimentation before language-model summarization.

**Open questions:** Whether Organization policies should inherit through parent units, whether feedback should expire, and whether aggregate usefulness should be visible to leaders remain intentionally postponed.

**Next milestone:** Build Team Coordination as a shared Operations and Workflow capability for workload visibility, Work Item dependencies, waiting-on relationships, bottleneck detection, and governed handoff recommendations.

## Development History — 19.5.0

### Team Coordination and Work Dependencies

Team Coordination is now a shared Operations and Workflow capability over Universal Work Items. Leaders can see active workload distribution, overdue and urgent pressure, open dependency chains, downstream work being blocked, and recent assignment handoffs. Individual contributors see coordination evidence around the work they are permitted to access.

Work Dependencies are governed waiting-on relationships between canonical Work Items. Work Handoffs preserve the transfer from one accountable owner to another while the original Work Item remains authoritative. Bottleneck scores are explainable read-model projections based on open dependencies, downstream dependents, urgency, and overdue state; they do not modify operational priority or source-system data.

**Primary engine:** Operations.

**Supporting engines:** Workflow, Organization, Identity, Communication, Automation, Analytics, and Intelligence.

**Business Graph changes:** Added Work Dependency relationships between Work Items and Work Handoff evidence connected to the Work Item, previous owner, new owner, actor, timestamp, and note. Added a non-authoritative Team Coordination projection for workload and bottleneck visibility. No duplicate project, task, ticket, or team record was introduced.

### ADR-0038 — Team Coordination Extends Universal Work

**Status:** Accepted  
**Decision:** Team coordination must reuse Universal Work Items, Organization scope, centralized assignment eligibility, and Workflow relationships. Dependencies and handoffs may add governed relationships and evidence, but they may not create a second task or project-management system.

**Consequence:** Every operational contributor can participate in cross-team coordination without custom handoff logic. Future automation and intelligence can reason over the same Work Item graph.

**Technical debt:** The first release manages direct Work Item dependencies. Dependency templates, milestone grouping, visual dependency graphs, capacity thresholds, and automated notifications remain future work.

**Open questions:** Whether Organization Units should define workload-capacity targets, when a blocked Work Item should automatically suggest reassignment, and which handoffs require acknowledgement remain intentionally postponed.

**Next milestone:** Build Team Capacity and Handoff Governance with configurable capacity targets, handoff acknowledgement, dependency notifications, and explainable reassignment suggestions without automatic assignment changes.
## Development History — 19.6.0

### Team Capacity and Handoff Governance

Team Coordination now includes configurable workload-capacity targets, explainable capacity pressure, acknowledgement-based handoffs, dependency notifications, and governed reassignment suggestions. Capacity is calculated from active, urgent, overdue, and blocked Universal Work Items and is used only as planning evidence.

A proposed handoff no longer changes Work Item ownership immediately. The proposed recipient or an authorized operational leader must explicitly accept or decline the handoff. Accepted handoffs reuse the canonical Operations owner field and preserve both the request and decision evidence. Dependency changes notify affected owners through the existing Communication Engine boundary.

**Primary engine:** Operations.

**Supporting engines:** Workflow, Organization, Identity, Communication, Automation, Analytics, and Intelligence.

**Business Graph changes:** Added Work Capacity Policy and Work Handoff Request evidence connected to a Person and Universal Work Item. Capacity projections and reassignment suggestions remain non-authoritative read models. No duplicate employee, team, project, task, or ticket object was introduced.

### ADR-0039 — Capacity Is Advisory and Handoffs Require Acknowledgement

**Status:** Accepted  
**Decision:** Capacity targets may guide workload visibility and reassignment suggestions, but cannot automatically assign, remove, or reprioritize work. A proposed handoff must retain the current owner until the recipient or an authorized operational leader explicitly accepts it.

**Consequence:** Team Coordination can expose overload and recommend safer distribution while preserving human accountability and the Universal Work Item as the only execution record.

**Technical debt:** Capacity currently uses a per-person target and a transparent weighted point model. Organization-inherited capacity templates, skill matching, availability calendars, time estimates, and channel-specific dependency notifications remain future work.

**Open questions:** Whether capacity targets should inherit from Organization Units, whether accepted handoffs should support a short transition window, and how working schedules should influence capacity remain intentionally postponed.

**Next milestone:** Build Team Availability and Skill-Aware Coordination, using Organization assignments, working availability, and configurable skill relationships to improve handoff suggestions without automatic assignment.

