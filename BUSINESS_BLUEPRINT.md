# Elev8 OS Business Blueprint

> Architecture is the product. Code serves the architecture. Elev8 OS must still make sense ten years from now.

**Blueprint version:** 1.4  
**Established:** 2026-07-22  
**Platform release:** 17.0.0  
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
| Events | Event planning and operational execution connected to staffing, inventory, bookings, communications, and financial outcomes. |

## Dashboard Principle

Dashboards are views over shared engines and the Business Graph. CEO, owner, manager, employee, glassblower, teacher, artist, event host, volunteer, customer, bookkeeper, and administrator experiences must not become separate systems.

HR is not an engine. HR is a role-based dashboard powered by Identity, Organization, Operations, Workflow, Communication, Knowledge, Financial, Analytics, and Intelligence.

## Business Graph

### Core Objects

Person, Business, Brand, Location, Department, Product, Order, Inventory, Asset, Task, SOP, Workflow, Communication, Event, Booking, Membership, Benefit, Donation, Volunteer, Artist, Employee, Vendor, Customer, Invoice, Payout, Route, Campaign, Knowledge, Production Job, Repair, Memorial Case, Class, Application, Reservation, and Workspace.

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


## Roadmap by Engine

| Engine | Current Foundation | Next Architectural Focus |
|---|---|---|
| Knowledge | Business Memory, Employee Guides, Business Blueprint | Structured SOP execution and knowledge relationships |
| Organization | Organization units, hierarchy, scoped person assignments, CEO company map | Shared resources and organization-aware engine access across registered graph objects |
| Operations | Universal Work Item, Work Inbox, glass production, repairs, memorials, and daily operations | Work adapters, Workbench execution, reusable SOP execution, and approval orchestration |
| Communication | Conversations, alerts, mentions | Unified delivery, preferences, escalation, and customer communication |
| Booking | Amelia calendar and approval center | General booking orchestration and staffing rules |
| Financial | Production costing and payouts | Unified obligations, profitability, invoices, and accounting integration |
| Commerce | WooCommerce integration | Order-to-operation orchestration |
| Sales | Opportunities foundation | Reusable pipeline and sales operating home |
| Marketing | Content and marketing foundations | Campaign engine and attribution |
| Intelligence | Executive brief and workspace health | Cross-engine recommendations and forecasting |

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
