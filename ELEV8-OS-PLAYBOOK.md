# Elev8 OS Project Playbook

> **Permanent project reference and single source of truth**  
> **Owner / Product Lead:** Steve Kelnhofer  
> **Product:** Elev8 OS — Founders Edition  
> **Platform:** WordPress plugin  
> **Status:** Active development  
> **Last updated:** July 15, 2026

---

## 1. Purpose of This Playbook

This playbook is the permanent operating manual for Elev8 OS. It defines the product vision, architecture, engineering standards, business rules, development workflow, roadmap, release history, backlog, and long-term direction.

A developer joining the project should be able to read this document and understand:

- what Elev8 OS is;
- who it serves;
- how the system is structured;
- where business logic belongs;
- how integrations must be handled;
- which rules are verified and which are still pending;
- how milestones are planned, built, validated, packaged, and released;
- what has already been built;
- what should be built next;
- what must never be guessed or silently changed.

This document must evolve with the product. Every milestone that changes architecture, persistence, business rules, integration boundaries, workflow, or roadmap direction must update this playbook in the same milestone.

When this playbook conflicts with an old planning document, chat, prototype, or code comment, the conflict must be resolved explicitly. Do not silently choose one source. The approved resolution must then be recorded here.

---

## 2. Executive Summary

Elev8 OS is becoming an AI-powered Business Operating System for experience-based businesses.

It began as a WordPress plugin supporting an art center and creative studio. Its long-term purpose is broader: help owners of classes, workshops, tours, studios, wellness experiences, events, and other appointment- or booking-driven businesses understand what is happening, what needs attention, and what action is most likely to improve the business.

Elev8 OS should eventually help answer questions such as:

- What should I do today?
- What needs my attention?
- Which classes should I promote?
- Which instructors are succeeding?
- Which offerings are underperforming?
- Where am I losing money?
- What opportunities am I missing?
- How can I increase profit?
- Should I add another session?
- Which customers are likely to return?
- Which location, instructor, or experience needs support?

The architectural foundation is **Business Intelligence**. Dashboards, recommendations, automation, reporting, notifications, and future AI features must consume the same trusted Business Intelligence and domain services rather than duplicating calculations.

WordPress owns Elev8 OS identity, business rules, configuration, mappings, and long-term data strategy. Amelia is the first booking integration, not the permanent owner of Elev8 OS logic. WooCommerce, future POS systems, CRMs, payment providers, accounting systems, communication tools, and AI services must connect through modular adapters.

---

## 3. Product Vision

### 3.1 Vision statement

Elev8 OS gives an experience-based business owner one trusted operating system for understanding performance, managing people and offerings, identifying opportunities, and deciding what to do next.

### 3.2 Product promise

Elev8 OS should convert disconnected operational data into clear, reliable action without requiring the owner to understand databases, spreadsheets, booking-system internals, or artificial intelligence.

### 3.3 Core outcomes

The system should help a business:

1. understand current performance;
2. identify urgent operational issues;
3. see demand and capacity opportunities;
4. evaluate instructors, services, classes, and locations fairly;
5. calculate payouts and profitability consistently;
6. reduce repetitive management work;
7. create useful recommendations grounded in verified data;
8. automate approved actions safely;
9. preserve an auditable explanation of how each result was produced.

### 3.4 Target businesses

Elev8 Arts is the first real-world operating environment, but Elev8 OS must be designed for many experience-based businesses, including:

- art centers and creative studios;
- glassblowing, pottery, painting, stained glass, and maker studios;
- cooking and beverage classes;
- wellness classes and workshops;
- fitness and movement experiences;
- tours and guided experiences;
- music, performance, and community programs;
- children’s enrichment programs;
- private events and group experiences;
- instructors or collectives operating across multiple locations.

Business-specific terminology should be configurable. Internal architecture should prefer neutral terms such as **provider**, **offering**, **session**, **booking**, **customer**, and **location**, while the user interface may display business-friendly labels such as artist, instructor, class, student, or studio.

---

## 4. Primary Users and Roles

### 4.1 Owner / CEO

Needs:

- business-wide performance;
- alerts and risks;
- priorities for today;
- revenue, demand, capacity, and profitability;
- instructor and offering performance;
- recommendations and opportunities;
- consolidated and location-specific reporting;
- auditability and confidence in every metric.

### 4.2 Manager

Needs:

- daily schedule and staffing visibility;
- unresolved operational items;
- class health and attendance risk;
- waitlist and capacity opportunities;
- cancellations, refunds, and customer follow-up;
- actionable tasks with clear priority.

### 4.3 Artist / Instructor / Experience Provider

Needs:

- upcoming sessions;
- bookings and attendance;
- public profile and booking link;
- personal statistics;
- payout and earnings information;
- tax documents;
- referral information;
- notifications and required actions;
- a self-service portal without direct Amelia administration access.

### 4.4 Staff / Employee / Glassblower

Needs:

- assigned sessions or work;
- schedule and customer information appropriate to role;
- employee-specific compensation rules;
- operational tasks and notifications.

### 4.5 System Administrator / Developer

Needs:

- integration diagnostics;
- schema and capability discovery;
- employee/provider mappings;
- release and migration health;
- logs and audit records;
- safe support tooling.

---

## 5. Non-Negotiable Development Principles

### 5.1 Business Intelligence is the foundation

Business Intelligence is a reusable domain layer, not merely a dashboard.

The CEO Dashboard, Artist Dashboard, monthly reports, recommendations, alerts, automations, and AI features must consume shared BI services and domain engines. They must not independently reconstruct calculations.

### 5.2 Never guess data

If a value cannot be verified, Elev8 OS must display **Unavailable** rather than zero, an estimate, or a misleading number.

A valid zero and unavailable data are different states.

Every unavailable metric should provide a safe explanation and, where appropriate, administrator diagnostics.

### 5.3 Build small, production-quality milestones

Each milestone must:

- have a narrow, testable objective;
- preserve working functionality;
- be independently testable;
- contain complete production files, not partial code fragments;
- build successfully;
- pass PHP syntax validation;
- be packaged as one complete installable WordPress plugin ZIP;
- update this playbook when relevant.

### 5.4 Think beyond the immediate request

Before implementing a milestone, evaluate whether the requested approach creates duplication, tight coupling, migration problems, or future limitations.

When a simpler, safer, more scalable, or more maintainable approach exists:

1. explain the tradeoffs;
2. recommend the stronger approach;
3. record the approved decision in this playbook when it affects architecture.

### 5.5 Preserve backward compatibility

Existing working modules, shortcodes, page slugs, user metadata, mappings, settings, public URLs, and initialization behavior must remain intact unless an approved migration explicitly changes them.

### 5.6 Read before write

Reporting and BI milestones should begin as read-only.

Any write operation affecting bookings, customers, payouts, notifications, schedules, or external systems requires separate validation, authorization, logging, error handling, and rollback planning.

### 5.7 Production code only

Production changes must use complete files and focused diffs. Temporary debug code, hard-coded IDs, copied schema assumptions, abandoned duplicate modules, and local-only patches must not enter a release.

---

## 6. Architectural Model

### 6.1 Dependency direction

```text
Presentation
    ↓
Application Modules
    ↓
Domain Services / Engines
    ↓
Integration Adapters and Elev8 OS Repositories
    ↓
External Systems and WordPress Persistence
```

Dependencies must not point backward.

A dashboard must not own Amelia joins. Amelia must not own payout rules. An AI recommendation must not invent its own revenue calculation. External system schemas must not leak throughout the plugin.

### 6.2 Logical layers

#### Presentation layer

Responsibilities:

- WordPress admin pages;
- front-end portal pages;
- dashboards;
- shortcodes;
- cards, tables, charts, notices, and navigation;
- CSS and JavaScript assets.

Rules:

- consume normalized services;
- contain no duplicated business rules;
- contain no direct Amelia business queries;
- escape all output;
- clearly distinguish unavailable, zero, warning, and success states.

#### Application modules

Responsibilities:

- register hooks, menus, shortcodes, routes, and assets;
- enforce capabilities and scope;
- orchestrate services;
- translate domain results into presentation-ready view models;
- coordinate approved actions.

Rules:

- remain thin;
- do not become general-purpose data-access classes;
- do not own calculations shared by other modules.

#### Domain services and engines

Responsibilities:

- business calculations;
- normalization;
- confidence and availability rules;
- reusable recommendations;
- payout, waitlist, profitability, reporting, and notification logic;
- auditable explanations.

Rules:

- independent of dashboard markup;
- reusable by multiple consumers;
- return structured contracts;
- distinguish source facts, derived metrics, and recommendations.

#### Integration adapters

Responsibilities:

- detect external integration availability;
- discover supported tables, columns, APIs, and capabilities;
- query or write through a controlled boundary;
- normalize external records into Elev8 OS contracts;
- isolate version-specific behavior.

Initial adapters:

- Amelia;
- WooCommerce.

Future adapters may include:

- alternate booking systems;
- point-of-sale systems;
- CRMs;
- payment processors;
- accounting platforms;
- email and SMS providers;
- marketing platforms;
- calendars;
- AI providers.

#### Elev8 OS persistence

Responsibilities:

- settings;
- provider mappings;
- business rules;
- payout policies;
- waitlists;
- recommendation states;
- audit logs;
- notification delivery history;
- location model;
- future normalized or cached data.

Use WordPress options and metadata for small, stable records. Use versioned custom tables when the data is relational, historical, high-volume, query-intensive, or requires an audit trail.

#### External systems

External systems are sources or execution targets. They are not permanent owners of Elev8 OS rules.

---

## 7. Proposed Canonical Domain Vocabulary

To support multiple industries, internal services should gradually normalize external terminology into these concepts:

| Canonical concept | Amelia / current example | Other possible source terms |
|---|---|---|
| Provider | Employee / Artist | Instructor, guide, coach, practitioner |
| Offering | Service / Class type | Workshop, tour, treatment, experience |
| Session | Event / Appointment date | Class date, departure, performance |
| Booking | Booking | Reservation, registration, ticket |
| Customer | Customer / Student | Guest, attendee, participant, client |
| Location | Location | Studio, venue, branch, meeting point |
| Transaction | WooCommerce order/payment | POS sale, invoice, charge |

New domain services should prefer canonical concepts even when adapters still expose legacy naming internally.

---

## 8. Repository and Plugin Structure

Canonical repository pattern:

```text
Elev8-OS/
├── .github/
│   ├── workflows/
│   ├── ISSUE_TEMPLATE/
│   └── pull_request_template.md
├── docs/
│   ├── architecture-decisions/
│   └── supporting documentation
├── plugin/
│   └── elev8-os/
│       ├── assets/
│       │   ├── css/
│       │   ├── js/
│       │   └── images/
│       ├── includes/
│       │   ├── Integrations/
│       │   ├── Modules/
│       │   ├── Services/
│       │   ├── Repositories/       # Add when persistent domain data requires it
│       │   ├── Contracts/          # Add when normalized interfaces justify it
│       │   ├── Support/
│       │   ├── class-elev8-os.php
│       │   └── class-elev8-os-loader.php
│       ├── languages/
│       ├── templates/              # Add when view templates reduce module size
│       ├── elev8-os.php
│       ├── readme.txt
│       └── uninstall.php           # Only when uninstall policy is approved
├── scripts/ or tools/
│   └── release tooling
├── ELEV8-OS-PLAYBOOK.md
└── README.md
```

### 8.1 Folder responsibilities

- **Modules:** WordPress-facing feature registration and orchestration.
- **Services:** reusable domain logic and normalized result contracts.
- **Integrations:** external-system boundaries and capability detection.
- **Repositories:** Elev8 OS-owned persistence access, when introduced.
- **Contracts:** interfaces and value contracts, when complexity justifies them.
- **Support:** logging, utilities, migrations, diagnostics, and shared infrastructure.
- **Assets:** feature-scoped CSS and JavaScript. Avoid a single global stylesheet.
- **Templates:** reusable view files only when they simplify modules without hiding logic.
- **Release tooling:** repository root only; never packaged inside the WordPress plugin.

---

## 9. Verified Current Foundation

The following foundation has previously been reported or observed as working and must be preserved unless revalidated and intentionally changed:

- WordPress plugin architecture with modular loader.
- Amelia Pro integration, initially verified against Amelia Pro 9.6.3.
- Amelia database discovery.
- System Inspector as the authoritative runtime diagnostic surface.
- Artist Portal.
- Artist Dashboard.
- Amelia Employee Mapping.
- Explicit WordPress-user-to-Amelia-employee mapping.
- Mapping stored in WordPress user meta.
- Email matching retained as a fallback.
- WooCommerce integration boundary present.
- Release Builder merged into `develop`.
- Release tooling stored outside the installable plugin directory.
- Business Intelligence service and dashboard modules have been introduced in at least one development version.
- CEO Dashboard module has been introduced in at least one development version.

Because several historical copies and restored files exist, the repository branch must be treated as authoritative for the exact current implementation. Before the next production milestone, verify the active `develop` tree and remove or archive duplicate local artifacts outside the repository workflow.

### 9.1 Loader preservation rule

Every loader change must preserve all verified require/include statements and initialization calls unless the milestone explicitly removes or migrates a module.

The loader is additive by default. Do not replace it from an older copy.

---

## 10. Coding Standards

### 10.1 PHP baseline

The project must declare and document its minimum supported PHP and WordPress versions before commercial hardening. Until formally decided, new code should avoid unnecessary dependence on the newest PHP features.

Current code uses typed method signatures in places. All new syntax must remain compatible with the project’s declared minimum PHP version once established.

### 10.2 WordPress standards

- Guard direct access with `defined( 'ABSPATH' ) || exit;` or equivalent.
- Use WordPress hooks and APIs appropriately.
- Use translation functions with the `elev8-os` text domain.
- Sanitize input at the boundary.
- Validate business meaning after sanitization.
- Escape output at render time using context-appropriate functions.
- Use nonces for state-changing requests.
- Perform capability checks before rendering sensitive data or processing actions.
- Use prepared values in dynamic SQL.
- Never accept table or column identifiers from request input.
- Load assets only on relevant pages.
- Use WordPress site timezone for business dates and reporting boundaries.

### 10.3 Class and file conventions

Existing code uses names such as:

```text
class-elev8-os-business-intelligence.php
Elev8_OS_Business_Intelligence
```

Preserve the established naming convention unless a future namespaced migration is approved.

Rules:

- one primary final class per file;
- public `init()` for WordPress-facing modules;
- small private methods for rendering and orchestration;
- no giant multipurpose classes;
- no business logic in CSS/JavaScript payload generation;
- no copied SQL across modules.

### 10.4 Service result contract

Metrics and reusable service results should use a consistent structure similar to:

```php
[
    'available'   => true,
    'value'       => 12,
    'confidence'  => 'high',
    'label'       => 'Classes this month',
    'reason'      => '',
    'source'      => 'amelia',
    'as_of'       => '2026-07-15T15:00:00-06:00',
    'diagnostics' => [],
]
```

For unavailable data:

```php
[
    'available'   => false,
    'value'       => null,
    'confidence'  => 'unavailable',
    'label'       => 'Booked value',
    'reason'      => 'A reliable price field could not be verified for this Amelia schema.',
    'source'      => 'amelia',
    'as_of'       => null,
    'diagnostics' => [
        'required_capability' => 'booking_price',
    ],
]
```

Recommended confidence values:

- `high`: direct verified source or deterministic calculation from verified inputs;
- `medium`: deterministic result with a known limitation clearly disclosed;
- `low`: should rarely be shown as a decision metric and must include explanation;
- `unavailable`: insufficient verified evidence.

No UI may convert unavailable to zero.

### 10.5 Error handling and logging

- User-facing failures should be clear and nontechnical.
- Administrator diagnostics may include detected capabilities and safe schema information.
- Sensitive customer data, secrets, full SQL, and payment information must not be exposed in notices.
- Expected integration limitations should return structured unavailable states, not fatal errors.
- Unexpected exceptions or failures should be logged through the Elev8 OS logger.

### 10.6 Database access

- Integration-specific SQL belongs in integration adapters or repositories.
- Runtime discovery must verify tables and columns before use.
- Dynamic values must be prepared.
- Dynamic identifiers must come only from a verified allowlist produced by discovery.
- Queries should minimize selected customer information.
- Reporting queries should be read-only unless a milestone explicitly introduces writes.
- Expensive repeated calculations should eventually use safe caching with explicit invalidation or freshness labeling.

### 10.7 Front-end standards

- Interfaces should be understandable by a business owner, not only a developer.
- Every metric needs a clear label and timeframe.
- Booked value must never be labeled revenue.
- Unavailable values must explain why.
- Owner actions should be prioritized, not presented as an undifferentiated wall of metrics.
- Responsive behavior and accessibility must be considered in every dashboard milestone.
- Charts must not hide underlying numbers or confidence.

---

## 11. Data Trust, Metric Semantics, and Time Rules

### 11.1 Data states

Every metric must represent one of these states:

1. **Available and nonzero**
2. **Available and zero**
3. **Unavailable**
4. **Available with limitation**
5. **Stale**, when cached data is older than its approved freshness period

### 11.2 Source facts versus derived metrics

A source fact comes directly from a verified integration record.

A derived metric is calculated from source facts using a documented formula.

A recommendation is a decision suggestion based on facts and derived metrics.

The system must preserve this distinction so future AI features can explain their reasoning.

### 11.3 Date rules

- Use the WordPress site timezone.
- Define “today” using the site timezone, not server UTC alone.
- Define month boundaries using the site timezone.
- Store timestamps in a consistent format and convert for display.
- Every report must state its period.
- Future multi-location support must decide whether reporting follows business, location, or user timezone; until then, site timezone is authoritative.

### 11.4 Money rules

- Use integer minor units where Elev8 OS owns stored monetary values, unless a documented reason requires another representation.
- Use source currency and WordPress/WooCommerce formatting where supported.
- Do not mix currencies in a total.
- Do not infer recognized revenue from booking price alone.
- Refunds, payment status, taxes, discounts, gift cards, fees, and partial payments require explicit treatment.

### 11.5 Booked value is not recognized revenue

**Booked value** is the scheduled or reserved value supported by booking data.

**Recognized revenue** requires an approved accounting rule and verified payment/refund information.

Dashboards must label each correctly.

---

## 12. Integration Architecture and Data Ownership

| Data or rule | Current source | Long-term owner | Rule |
|---|---|---|---|
| Schedules and bookings | Amelia | External source normalized by Elev8 OS | Runtime capability discovery required |
| Provider identity mapping | WordPress user meta plus Amelia fallback | Elev8 OS | Explicit mapping overrides email fallback |
| Products and orders | WooCommerce when used | External source normalized by Elev8 OS | Accounting interpretation belongs to Elev8 OS |
| Payout policies | Partially defined, not fully centralized | Elev8 OS Payout Engine | Never calculate independently inside dashboards |
| Waitlist | Current workaround / future module | Elev8 OS Waitlist Engine | Must not depend on incorrect post-event Amelia emails |
| Profitability inputs | Future Elev8 OS records plus integrations | Elev8 OS Profitability Engine | Include fees, supplies, ads, labor, refunds, and revenue rules |
| Locations | Future location model | Elev8 OS | Map external location IDs to stable internal identity |
| Recommendations | Future rules and AI | Elev8 OS | Must cite trusted BI inputs and retain explanation |
| Automation approvals | Future Elev8 OS workflow | Elev8 OS | AI may recommend; execution requires approved permissions and guardrails |

### 12.1 Amelia adapter requirements

- Detect Amelia availability.
- Detect supported tables and columns at runtime.
- Expose capabilities rather than forcing callers to understand schema details.
- Normalize employees, services, events, appointments, bookings, customers, locations, prices, and statuses where reliably supported.
- Keep version-specific logic inside the adapter.
- Provide diagnostics through System Inspector.
- Never hard-code one installation’s employee IDs as general business logic.

### 12.2 Future adapter contract

Each integration should eventually implement a defined capability contract, for example:

- providers available;
- offerings available;
- sessions available;
- bookings available;
- customer counts available;
- prices available;
- payment status available;
- refunds available;
- locations available;
- write capabilities available.

Consumers should ask for capabilities and normalized data, not vendor-specific tables.

---

## 13. Business Intelligence Architecture

### 13.1 Role of Business Intelligence

Business Intelligence converts normalized source data into trusted operational facts and derived metrics.

It should provide a stable interface for:

- CEO Dashboard;
- owner recommendations;
- Artist Dashboard;
- manager dashboard;
- monthly reporting;
- profitability analysis;
- waitlist decisions;
- marketing opportunities;
- alerts and notifications;
- future AI reasoning;
- future automation.

### 13.2 Initial BI metrics

The first BI generation has included or planned:

- classes or sessions today;
- classes or sessions this month;
- students/customers booked today;
- students/customers booked this month;
- upcoming session dates;
- pending bookings;
- cancelled bookings;
- cancellation rate;
- average class size;
- booked value when reliably supported;
- metric confidence and diagnostics.

### 13.3 BI exclusions until dedicated engines exist

BI dashboards must not independently invent:

- artist payouts;
- employee compensation;
- recognized revenue;
- net profit;
- refund-adjusted revenue;
- marketing attribution;
- customer lifetime value;
- AI predictions presented as facts.

### 13.4 BI output design

BI should produce both individual metrics and higher-level snapshots.

A future snapshot may contain:

```php
[
    'period' => [
        'start' => '2026-07-01T00:00:00-06:00',
        'end'   => '2026-07-31T23:59:59-06:00',
    ],
    'scope' => [
        'location_id' => null,
        'provider_id' => null,
        'offering_id' => null,
    ],
    'metrics' => [/* normalized metric results */],
    'alerts' => [/* rule-driven findings */],
    'opportunities' => [/* evidence-backed opportunities */],
    'diagnostics' => [],
]
```

### 13.5 Recommendation architecture

Recommendations must be downstream of trusted facts.

Each recommendation should eventually include:

- title;
- recommended action;
- priority;
- expected business outcome;
- evidence metrics;
- confidence;
- reason;
- affected provider/offering/location;
- expiration or review time;
- status: new, acknowledged, accepted, dismissed, completed;
- execution method, if automation is supported;
- audit history.

Example:

```text
Promote Saturday pottery class
Reason: 3 seats remain, class is 72 hours away, and similar classes historically fill after one reminder.
Confidence: Medium
Source: Verified booking capacity plus historical fill pattern
```

The recommendation engine must not claim causation without evidence.

---

## 14. Domain Engines

### 14.1 Payout Engine

Purpose:

- own artist, instructor, host, and employee compensation calculations;
- support effective dates and historical rule versions;
- apply refunds and approved adjustments;
- produce auditable statements;
- eliminate payout logic from dashboards and exports.

#### Confirmed business rules requiring formal implementation

**Standard artist-hosted classes:**

- Elev8 receives 40% and the artist receives 60% until Elev8’s cumulative share for the class reaches $100.
- After Elev8 reaches $100, Elev8 receives 15% of additional qualifying revenue and the artist receives 85%.

This rule needs an exact formula, examples, treatment of refunds, taxes, discounts, fees, and effective dates before production implementation.

**Employee glassblower classes:**

- Elev8 Arts receives a straight 15% under the currently discussed model.

This also requires clarification in the Payout Engine about what the 15% is calculated from and how employee wages interact with the class economics.

#### Payout Engine requirements

- policy types;
- provider assignment;
- offering-level override;
- effective start and end dates;
- versioned rules;
- gross basis definition;
- refund treatment;
- discount treatment;
- tax and fee treatment;
- manual adjustment with reason;
- immutable calculation snapshot;
- recalculation policy;
- statement export;
- administrator audit trail.

### 14.2 Waitlist Engine

Purpose:

- own waitlist membership;
- track desired offering, session, capacity, and customer contact;
- detect seat openings;
- notify customers in a controlled order;
- expire offers;
- recommend opening another session;
- avoid dependence on Amelia’s unsuitable post-event email behavior.

Requirements:

- customer consent and communication preference;
- duplicate prevention;
- queue order policy;
- capacity check;
- offer expiration;
- acceptance and booking verification;
- delivery log;
- admin override;
- recommendation thresholds;
- privacy and retention policy.

### 14.3 Profitability Engine

Purpose:

Calculate class-, offering-, provider-, location-, and period-level profit using verified revenue and cost inputs.

Potential inputs:

- recognized revenue;
- payout share;
- credit-card fees;
- marketplace or booking fees;
- advertising;
- supplies;
- labor;
- room or studio cost;
- refunds;
- discounts;
- taxes where appropriate;
- allocated overhead.

The system must distinguish contribution margin from fully allocated net profit.

### 14.4 Location Service

Purpose:

- create stable Elev8 OS location identity;
- map external-system location IDs;
- scope dashboards, permissions, rules, and reports;
- support consolidated and location-specific views.

### 14.5 Reporting Engine

Purpose:

- generate consistent reports from domain services;
- support monthly owner reports;
- support artist payout statements;
- support exports and scheduled delivery;
- avoid embedding report logic in dashboard HTML.

### 14.6 Notification Service

Purpose:

- centralize owner, manager, provider, staff, and customer alerts;
- support in-app, email, SMS, and future channels;
- preserve delivery and acknowledgement history;
- prevent duplicate or excessive notifications.

### 14.7 Recommendation Engine

Purpose:

- convert BI evidence into prioritized suggestions;
- begin with deterministic rules;
- later add AI-assisted synthesis and prediction;
- preserve evidence, confidence, and explanation;
- remain separate from action execution.

### 14.8 Automation Engine

Purpose:

- execute approved actions safely;
- require explicit permission and capability;
- support dry-run mode;
- log every action;
- prevent repeated execution;
- provide rollback or compensating action where possible.

AI recommendations and automation must remain separate. A recommendation may propose an action; automation executes only under approved policy.

---

## 15. Artist Portal and Dashboard Direction

### 15.1 Existing and requested profile capabilities

Provider profiles may include:

- first and last name;
- biography;
- image;
- up to four social links with editable labels and URLs;
- payment/contact links such as Venmo or PayPal;
- phone using `tel:` behavior;
- email using `mailto:` behavior;
- custom contact link;
- public profile URL;
- private referral link;
- QR code for public profile;
- tax-document upload;
- upcoming classes or a provider-specific booking link.

### 15.2 Dashboard direction

The Artist Dashboard should eventually consume BI and Payout Engine services to show:

- upcoming sessions;
- total classes;
- total students;
- booked value or recognized revenue with correct labels;
- amount owed to artist;
- amount owed to Elev8;
- average class size;
- refunds and adjustments;
- referral performance;
- documents and actions needed;
- notifications.

Providers must see only their mapped data unless a broader capability explicitly allows otherwise.

### 15.3 Mapping rule

Explicit WordPress-user-to-Amelia-employee mapping is authoritative.

Email matching may be used only as a fallback when no explicit mapping exists.

---

## 16. CEO and Manager Experience

### 16.1 CEO Dashboard goal

The CEO Dashboard should not be merely a collection of charts. It should become a prioritized operating view answering:

1. What is happening?
2. What is unusual?
3. What needs attention?
4. What should I do next?
5. Why is the system recommending that action?

### 16.2 Suggested dashboard hierarchy

1. **Today’s priorities**
2. **Critical alerts**
3. **Opportunities**
4. **Business pulse**
5. **Upcoming capacity and demand**
6. **Provider and offering performance**
7. **Profitability**
8. **Data confidence and integration health**

### 16.3 Manager dashboard direction

The Manager Dashboard should focus on execution:

- today’s sessions;
- missing instructor or room assignments;
- low enrollment requiring promotion;
- over-capacity or waitlist opportunity;
- cancellations and refund follow-up;
- unresolved customer issues;
- tasks assigned and overdue;
- operational notices.

---

## 17. AI Architecture and Guardrails

### 17.1 AI is a consumer, not the source of truth

AI must consume structured Elev8 OS facts, metrics, rules, and recommendations. It must not directly query arbitrary tables and invent meaning.

### 17.2 Progressive AI roadmap

#### Phase 1: Deterministic intelligence

- trusted metrics;
- rule-based alerts;
- threshold-based opportunities;
- clear explanations.

#### Phase 2: AI-assisted summaries

- daily owner summary;
- plain-language explanation of changes;
- prioritized recommendations generated from structured evidence;
- question answering over verified BI results.

#### Phase 3: Predictive support

- likely fill rate;
- cancellation risk;
- return-customer likelihood;
- promotion timing;
- capacity planning.

Predictions must be labeled as predictions, include confidence, and be evaluated against outcomes.

#### Phase 4: Approved automation

- draft promotions;
- schedule reminders;
- propose additional sessions;
- notify waitlisted customers;
- create manager tasks;
- execute actions only under approved policy.

### 17.3 AI safety and trust requirements

- Never present generated text as a verified fact without source evidence.
- Never fabricate unavailable data.
- Keep customer-sensitive data to the minimum needed.
- Record the facts supplied to an AI model when practical.
- Record model/provider/version for consequential recommendations.
- Require human approval for financial, scheduling, customer-contact, or destructive actions until an explicit automation policy permits otherwise.
- Provide a non-AI fallback for core operations.
- Design provider adapters so AI vendors can be replaced.

---

## 18. Security, Privacy, and Reliability

### 18.1 Capabilities and authorization

- Owner dashboards initially require `manage_options` unless custom capabilities are introduced.
- Future roles should use purpose-specific capabilities rather than broad administrator access.
- Provider screens must be scoped to the explicitly mapped provider.
- Every state-changing request requires nonce and capability validation.
- API routes must use permission callbacks.

### 18.2 Data minimization

- Retrieve only needed fields.
- Avoid displaying full customer information in broad dashboards.
- Do not send unnecessary personal data to AI services.
- Define retention policies for waitlists, logs, documents, and generated recommendations.

### 18.3 Secrets

- API keys must never be committed to the repository.
- Secrets should use secure WordPress configuration or an approved encrypted storage approach.
- Diagnostics must show connection state without revealing secret values.

### 18.4 Migrations

New persistence requires:

- documented schema or key;
- version number;
- idempotent migration;
- backup/rollback consideration;
- upgrade testing;
- failure logging;
- no destructive migration without explicit approval.

### 18.5 Auditability

Consequential calculations and actions should eventually record:

- rule version;
- source period;
- source record references where safe;
- calculation time;
- user or automation actor;
- adjustments;
- action result.

---

## 19. Development Workflow

### 19.1 Branch model

- `main` = stable production releases only.
- `develop` = completed, tested integration branch for active development.
- `feature/<name>` = one focused milestone or opportunity branch created from `develop`.
- `fix/<name>` = focused defect branch from the appropriate base.
- `release/<version>` = optional stabilization branch when release complexity justifies it.
- `hotfix/<name>` = urgent production fix created from `main`, then merged back into `develop`.

All ordinary development happens through `develop`. Do not develop directly on `main`.

### 19.2 Milestone planning sequence

Before writing production code:

1. define the user and business outcome;
2. describe current behavior;
3. define the milestone boundary;
4. identify trusted data sources;
5. identify unavailable or ambiguous data;
6. evaluate long-term architecture;
7. explain tradeoffs and recommend the approach;
8. list exact files to add or change;
9. define tests and acceptance criteria;
10. identify playbook sections requiring updates.

### 19.3 Required implementation sequence

1. Update local `develop`.
2. Create and publish the feature branch.
3. Verify the current repository tree and active files.
4. Implement complete production files.
5. Run PHP syntax checks on every PHP file in the plugin, not only changed files when practical.
6. Run automated tests and static checks available to the project.
7. Review changed-file list and diff.
8. Update this playbook and release notes where required.
9. Commit with a clear message.
10. Push the feature branch.
11. Open a pull request into `develop`.
12. Review the pull request and required checks.
13. Merge into `develop` only after acceptance criteria pass.
14. Build one complete installable WordPress plugin ZIP from the merged branch.
15. Install and test that ZIP in WordPress.
16. Promote to `main` only for a stable release.

### 19.4 No partial production patches

For production milestones, do not deliver isolated PHP snippets that require manual merging. Deliver the complete installable plugin ZIP and retain the exact source changes in Git.

### 19.5 Release package rules

The installable ZIP must:

- contain one top-level `elev8-os` plugin directory;
- include all runtime files required by the plugin;
- exclude repository metadata, development notes, test fixtures, release tools, and secrets;
- use the correct plugin version;
- install cleanly through WordPress;
- activate without fatal errors;
- preserve existing settings and mappings during upgrades.

---

## 20. Quality Gates and Definition of Done

A milestone is not complete until all applicable items pass.

### 20.1 Architecture

- [ ] The milestone follows dependency direction.
- [ ] Shared logic is in a reusable service or engine.
- [ ] Integration-specific details remain inside adapters.
- [ ] No verified working feature was removed or silently changed.
- [ ] Long-term tradeoffs were considered and documented.

### 20.2 Data integrity

- [ ] No metric guesses unavailable data.
- [ ] Valid zero is distinguished from unavailable.
- [ ] Time period and timezone are correct.
- [ ] Money labels are accurate.
- [ ] Booked value is not mislabeled as revenue.
- [ ] Diagnostics exist for unsupported schemas or missing capabilities.

### 20.3 Security

- [ ] Capabilities are checked.
- [ ] Nonces protect writes.
- [ ] Inputs are sanitized and validated.
- [ ] Outputs are escaped.
- [ ] SQL values are prepared.
- [ ] Customer and secret data are minimized.

### 20.4 Code quality

- [ ] Complete files are committed.
- [ ] All PHP files pass syntax validation.
- [ ] No duplicate or abandoned production files are included.
- [ ] Assets load only where needed.
- [ ] Logging and failure behavior are appropriate.
- [ ] Code comments explain why, not obvious syntax.

### 20.5 Testing

- [ ] Acceptance criteria pass.
- [ ] Existing critical flows still work.
- [ ] Unsupported/missing integration behavior was tested.
- [ ] Empty, zero, and unavailable states were tested.
- [ ] Permission boundaries were tested.
- [ ] Upgrade behavior was tested when persistence changed.
- [ ] The final Release Builder ZIP was installed and tested in WordPress.

### 20.6 Documentation and release

- [ ] Playbook updated if required.
- [ ] Release history updated.
- [ ] Backlog adjusted based on discoveries.
- [ ] Version number updated correctly.
- [ ] Pull request reviewed and merged into `develop`.
- [ ] Stable release merged to `main` only after production acceptance.

---

## 21. Testing Strategy

### 21.1 Minimum immediate checks

Every milestone must include:

- PHP syntax validation;
- plugin activation test;
- relevant admin/front-end page load test;
- capability test;
- empty-data test;
- unavailable-data test;
- upgrade test when applicable;
- install test from the final ZIP.

### 21.2 Recommended automated stack

As the project matures, add:

- PHPUnit for services and engines;
- WordPress integration tests;
- PHP_CodeSniffer with WordPress coding standards;
- PHPStan or Psalm at an achievable level;
- JavaScript linting and tests when JavaScript grows;
- end-to-end tests for critical portal and dashboard flows;
- GitHub Actions for syntax, standards, tests, and ZIP build validation.

### 21.3 Integration fixtures

Avoid testing solely against one live Amelia database. Create sanitized schema fixtures or test adapters representing supported integration capabilities and missing-capability cases.

---

## 22. Versioning and Release Policy

Use Semantic Versioning as the target policy:

- **MAJOR:** incompatible architecture, public contract, or migration change;
- **MINOR:** backward-compatible feature milestone;
- **PATCH:** backward-compatible bug fix or small correction.

Pre-1.0 development may use minor versions for milestone changes, but every release must still clearly document compatibility and migration impact.

Every stable release should include:

- version;
- date;
- milestone name;
- user-visible changes;
- architecture changes;
- migration notes;
- known limitations;
- tested WordPress, PHP, Amelia, and WooCommerce versions where applicable;
- release ZIP checksum in the release record when tooling supports it.

---

## 23. Release History

This history includes known project evolution. Some early version details were developed iteratively and must be reconciled with Git tags and commits before being treated as an exact technical changelog.

### Pre-playbook development history

#### V2 — Early payout configuration

- Added base host-fee concepts.
- Began adapting class economics to real Elev8 Arts use.

#### V3 — Percentage configuration correction

- Addressed a bug where selecting 15% could save as 50%.

#### V4 — Artist Portal foundation

- Added Artist Portal basics.
- Began replacing Amelia’s “Employee” terminology with “Elev8 Member Artist” in Elev8 OS-facing experiences.

#### V4.99 — Vision Edition / pre-Founders build

- Consolidated broader portal and operating-system direction.
- Expanded profile, dashboard, and integration concepts.
- Used as the transition point toward a more formal modular architecture.

#### Architecture foundation milestone

- Established modular plugin structure.
- Added Amelia and WooCommerce integration boundaries.
- Added System Inspector.
- Added explicit Amelia Employee Mapping with email fallback.
- Added Artist Portal and Artist Dashboard.
- Added Release Builder and `develop` branch workflow.

#### Business Intelligence foundation milestone

- Defined BI as a reusable service.
- Introduced owner-facing BI dashboard work.
- Added metric availability, confidence, and diagnostics principles.

#### CEO Dashboard foundation milestone

- Introduced a CEO Dashboard module in at least one development loader version.
- Exact current status and production readiness must be verified from `develop` before the next release.

### Playbook milestone — July 15, 2026

- Created `ELEV8-OS-PLAYBOOK.md` as the permanent single source of truth.
- Expanded architecture from creative-studio plugin to modular Business Operating System for experience-based businesses.
- Established BI-first, recommendation, AI, and automation architecture.
- Consolidated coding standards, business rules, workflow, roadmap, release history, and backlog.

---

## 24. Known Issues and Technical Debt

The following items have been reported during prior development and should be verified against the current `develop` branch before closure:

- Multiple historical/restored copies of dashboard and loader files exist outside the canonical repository workflow.
- Provider social-link labels previously failed to update correctly.
- Additional custom links previously failed to display in some profile iterations.
- Phone and email links previously received unwanted `http://` behavior instead of `tel:` and `mailto:`.
- Some provider classes did not initially appear despite Amelia service assignment.
- Glassblower pages and other provider pages had inconsistent “View all classes” behavior.
- Saving some artist records produced errors in prior builds.
- Employee-specific class behavior differs from independent artist payout behavior.
- Existing Amelia waitlist workaround can send the wrong email after the event.
- Amelia terminology and Elev8 OS terminology are not fully normalized.
- Exact production status of BI Dashboard and CEO Dashboard must be verified.
- Minimum supported PHP and WordPress versions are not yet formally recorded.
- Automated test coverage is not yet sufficient for commercial hardening.
- Release history is not yet fully reconciled with Git tags.

Do not assume a historical bug remains open or fixed. Verify it, then update this section.

---

## 25. Product Roadmap

Roadmap sequencing is architectural, not a promise of dates. Each milestone must be narrowed into a testable opportunity before implementation.

### Stage 0 — Repository truth and playbook

**Goal:** establish one trusted project baseline.

- Create this playbook.
- Verify the current `develop` branch tree.
- Confirm active plugin version.
- Confirm the authoritative loader and modules.
- Reconcile working features, duplicate files, and release history.
- Record minimum environment requirements.
- Ensure Release Builder produces one valid ZIP.

### Stage 1 — Business Intelligence foundation hardening

**Goal:** create a stable trusted metric layer.

- Audit the current BI service.
- Define canonical metric contracts.
- Add scope and period contracts.
- Validate Amelia capabilities at runtime.
- Standardize unavailable, confidence, source, and as-of fields.
- Add automated tests for zero, unavailable, and date boundaries.
- Ensure dashboard consumers contain no duplicate calculation logic.

### Stage 2 — CEO Dashboard v1

**Goal:** give the owner a reliable operating pulse.

- Business pulse metrics.
- Today and this month views.
- Upcoming sessions.
- pending and cancelled bookings.
- cancellation rate.
- average class size.
- booked value only when verified.
- integration-health and data-confidence display.
- no invented payout, revenue, or profit metrics.

### Stage 3 — Recommendation Engine v1

**Goal:** answer “What needs my attention?” using deterministic rules.

Initial opportunities may include:

- low-enrollment session approaching its date;
- full session with waitlist demand;
- repeated cancellations;
- offering with declining bookings;
- provider with strong demand and limited schedule;
- data/integration issue blocking owner visibility.

Each recommendation must include evidence, confidence, and expiration.

### Stage 4 — Payout Engine v1

**Goal:** centralize verified artist and employee payout rules.

- Formal policy model.
- Standard 40%/60% until Elev8 reaches $100, then 15%/85% rule.
- Employee glassblower 15% policy.
- Effective dates.
- refunds and adjustments.
- test examples.
- audit snapshots.
- artist statement data contract.

### Stage 5 — Artist Dashboard expansion

**Goal:** give providers useful self-service business visibility.

- scoped BI metrics;
- upcoming sessions and bookings;
- payout information from Payout Engine;
- profile and booking actions;
- referrals;
- documents;
- notifications.

### Stage 6 — Waitlist Engine v1

**Goal:** create an Elev8-owned waitlist independent of Amelia email limitations.

- waitlist capture;
- session and offering linkage;
- seat-open detection;
- notification workflow;
- offer expiration;
- open-another-session recommendation.

### Stage 7 — Monthly Business Dashboard and Reporting Engine

**Goal:** support recurring review and trend analysis.

- total class revenue when recognized revenue rules are approved;
- booked value;
- average attendance;
- provider and offering performance;
- most and least profitable classes when Profitability Engine exists;
- refund rate;
- new versus returning customers;
- scheduled report generation.

### Stage 8 — Profitability Engine

**Goal:** answer which experiences create profit.

- class-level economics;
- fees, ads, supplies, labor, payout, refunds;
- contribution margin;
- allocated profit;
- verified inputs and unavailable states.

### Stage 9 — Manager Operations

**Goal:** convert findings into daily work.

- operational dashboard;
- tasks;
- unresolved issues;
- staffing and session health;
- acknowledgement and completion states.

### Stage 10 — Multi-location and multi-integration architecture

**Goal:** support businesses beyond one studio and one booking system.

- stable location model;
- external identity mapping;
- location-scoped permissions;
- adapter interfaces;
- consolidated reporting;
- integration selection and capability matrix.

### Stage 11 — AI Owner Assistant

**Goal:** answer owner questions in plain language using trusted BI.

- daily summary;
- natural-language questions;
- evidence-linked answers;
- recommendation explanations;
- provider-independent AI adapter;
- privacy controls and audit metadata.

### Stage 12 — Approved Automation

**Goal:** safely perform repetitive actions.

- task creation;
- draft marketing messages;
- waitlist notifications;
- approved session reminders;
- dry-run and approval workflow;
- action logs and duplicate prevention.

### Stage 13 — Commercial platform hardening

**Goal:** make Elev8 OS supportable across many businesses.

- installer and upgrade migrations;
- custom capabilities;
- onboarding wizard;
- integration setup;
- support diagnostics;
- telemetry only with explicit consent;
- import/export;
- licensing or Founders Edition policy if later approved;
- accessibility review;
- security review;
- full automated test suite;
- documentation for administrators and developers.

---

## 26. Prioritized Backlog

### Immediate backlog

- [ ] Verify current `develop` repository contents.
- [ ] Confirm current plugin version and working release ZIP.
- [ ] Confirm which BI and CEO Dashboard files are active.
- [ ] Reconcile duplicate/restored loader and dashboard files.
- [ ] Add this playbook at repository root.
- [ ] Establish minimum PHP and WordPress versions.
- [ ] Record current Amelia and WooCommerce compatibility.
- [ ] Audit loader preservation and initialization.
- [ ] Confirm GitHub Actions and Release Builder behavior.

### Architecture backlog

- [ ] Define canonical provider/offering/session/booking contracts.
- [ ] Define integration capability interface.
- [ ] Define metric, snapshot, alert, and recommendation contracts.
- [ ] Decide when repositories and custom tables are introduced.
- [ ] Add architecture decision record template.
- [ ] Define migration framework.
- [ ] Define audit-log approach.

### Business-rule backlog

- [ ] Finalize payout calculation basis.
- [ ] Create numerical payout examples.
- [ ] Define refund, tax, discount, fee, and chargeback treatment.
- [ ] Define recognized revenue policy.
- [ ] Define class capacity and waitlist rules.
- [ ] Define profitability allocation rules.
- [ ] Define referral percentage and attribution policy.

### Portal backlog

- [ ] Reverify profile save flow.
- [ ] Reverify social labels and custom links.
- [ ] Enforce safe URL, `tel:`, and `mailto:` handling.
- [ ] Normalize class-list versus booking-link behavior.
- [ ] Add QR-code support.
- [ ] Add tax-document workflow.
- [ ] Keep referral link private.

### Quality backlog

- [ ] Add full-plugin PHP syntax workflow.
- [ ] Add coding-standard checks.
- [ ] Add service unit tests.
- [ ] Add integration fixtures.
- [ ] Add ZIP structure validation.
- [ ] Add plugin activation smoke test.
- [ ] Reconcile Git tags and release notes.

### AI and future backlog

- [ ] Define AI provider interface.
- [ ] Define data-minimization rules for model calls.
- [ ] Define recommendation evaluation metrics.
- [ ] Define feedback and dismissal workflow.
- [ ] Define human-approval levels for automation.
- [ ] Define action audit and rollback strategy.

---

## 27. Architecture Decision Records

Major decisions should be tracked here and optionally expanded in `docs/architecture-decisions/`.

### ADR-001 — WordPress owns Elev8 OS business logic

**Decision:** Payouts, mappings, reporting interpretation, referrals, operational policies, recommendations, and automation rules belong to Elev8 OS.

**Reason:** External plugins can change or be replaced without controlling core business behavior.

### ADR-002 — Explicit provider mapping overrides email matching

**Decision:** Explicit WordPress-to-external-provider mapping is authoritative. Email matching is fallback only.

**Reason:** Stable identity and backward compatibility.

### ADR-003 — Reusable services own business calculations

**Decision:** Dashboards, reports, recommendations, and AI consume shared services.

**Reason:** One calculation, many consumers, less inconsistency.

### ADR-004 — External schemas are discovered at runtime

**Decision:** Adapters verify tables, columns, APIs, and capabilities before use.

**Reason:** Prevent brittle assumptions across versions and installations.

### ADR-005 — Unavailable is different from zero

**Decision:** Missing or unverifiable data is shown as Unavailable.

**Reason:** Protect owner decisions from misleading metrics.

### ADR-006 — Booked value is not recognized revenue

**Decision:** Booking value and accounting revenue remain separate concepts.

**Reason:** Payment, refund, and accounting status matter.

### ADR-007 — Payout calculations require a Payout Engine

**Decision:** No dashboard may independently calculate payouts.

**Reason:** Rules require versioning, refunds, overrides, and auditability.

### ADR-008 — Release tooling stays outside the plugin package

**Decision:** Build scripts and repository tools remain at repository root.

**Reason:** Keep development tooling out of production installations.

### ADR-009 — Business Intelligence is the foundation for AI

**Decision:** AI receives normalized BI facts and recommendations rather than querying arbitrary integration data directly.

**Reason:** Trust, explainability, portability, and consistent answers.

### ADR-010 — Recommendations and automation are separate

**Decision:** The system may recommend an action without permission to execute it.

**Reason:** Human control, safety, and auditability.

### ADR-011 — Internal vocabulary must support multiple industries

**Decision:** Domain services should migrate toward provider, offering, session, booking, customer, and location concepts.

**Reason:** Prevent an art-center-specific architecture from blocking broader use.

### ADR-012 — Every production milestone delivers a complete ZIP

**Decision:** The final milestone artifact is one complete installable WordPress plugin ZIP built from source.

**Reason:** Eliminate manual patch drift and ensure the tested artifact matches the release.

---

## 28. Milestone Template

Use this structure before coding each milestone.

```markdown
# Milestone: [Name]

## Business outcome
What business problem does this solve?

## User story
As a [role], I want [capability], so that [outcome].

## Current behavior
What happens now?

## Proposed behavior
What will change?

## Architecture review
- Trusted data source:
- Domain service or engine:
- Integration capabilities required:
- Persistence changes:
- Security implications:
- Multi-business implications:
- Simpler or more scalable alternative:
- Recommended approach and tradeoffs:

## Scope
Included:
Excluded:

## Exact file plan
Files added:
Files changed:
Files intentionally untouched:

## Data and unavailable states
What can be verified?
What must display Unavailable?

## Acceptance criteria
- [ ] ...

## Test plan
- [ ] PHP syntax
- [ ] activation
- [ ] permissions
- [ ] zero state
- [ ] unavailable state
- [ ] upgrade
- [ ] final ZIP installation

## Documentation impact
Playbook sections updated:
Release-history entry:

## Delivery
Branch:
Version:
Installable ZIP:
```

---

## 29. Pull Request Checklist

```markdown
## Outcome
Describe the business outcome.

## Architecture
- [ ] Business logic is reusable and not duplicated in the UI.
- [ ] External schema details remain inside adapters.
- [ ] Unavailable data is not converted to zero.
- [ ] Existing working modules are preserved.

## Security
- [ ] Capabilities checked.
- [ ] Nonces used for writes.
- [ ] Input sanitized and validated.
- [ ] Output escaped.
- [ ] SQL values prepared.

## Validation
- [ ] All PHP files pass syntax validation.
- [ ] Automated tests pass.
- [ ] Empty, zero, and unavailable states tested.
- [ ] Final Release Builder ZIP installed and tested.

## Documentation
- [ ] Playbook updated if architecture, workflow, rules, persistence, or roadmap changed.
- [ ] Release history updated.
```

---

## 30. How This Playbook Is Maintained

Update this document when any of the following changes:

- product vision or target market;
- repository or plugin structure;
- integration boundary;
- data ownership;
- canonical domain vocabulary;
- service or engine contract;
- business rule;
- persistence or migration strategy;
- security or permission model;
- AI or automation policy;
- branch or release workflow;
- milestone roadmap;
- stable release history;
- known issue status when materially relevant.

Routine styling changes and minor bug fixes do not require expanding architectural sections, but they should appear in release notes when user-visible.

At the end of every milestone:

1. review this playbook;
2. update affected sections;
3. add the release-history entry;
4. close, revise, or add backlog items;
5. confirm the next milestone still follows the architecture.

The playbook is part of the product. A milestone that changes the system but leaves this document materially incorrect is incomplete.

---

## 31. Next Recommended Milestone

Before adding another major feature, perform a **Repository Baseline and BI Architecture Audit** on the current `develop` branch.

The milestone should:

- identify the authoritative active files;
- verify the current version and loader;
- verify the BI service and CEO Dashboard status;
- remove no functionality;
- document current compatibility;
- validate the Release Builder;
- produce a clean complete installable ZIP;
- establish the exact starting point for the next roadmap milestone.

This is safer than immediately extending a historical copy because the available project artifacts show multiple versions and restored files. Establishing repository truth now prevents future milestones from building on the wrong loader, dashboard, or integration implementation.

### 5.3.0 — Artist Website Phase 1
- Added the private Artist Portal “My Website” experience.
- Artists can preview the existing public profile rendering, copy their public URL, open the live page, and access the configured profile editor.
- The portal uses the verified WordPress-to-Amelia employee mapping and reuses the public profile engine instead of duplicating profile presentation logic.

### 5.4.0 — Manage My Website
- Activated the Artist Portal **Edit Website** navigation item.
- Added an artist-scoped, front-end website editor for public profile content.
- Artists can manage their bio, medium, specialties, experience, images, gallery, social links, contact links, payment links, and booking call-to-action without entering WordPress administration.
- Administrator-owned fields remain protected and are preserved during artist saves.
- Save requests require a valid WordPress login, verified Amelia mapping, matching employee ID, nonce validation, sanitization, and cache purging.
