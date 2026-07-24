# Elev8 OS Constitution v2

## Mission
Elev8 OS exists to reduce the burden of entrepreneurship. Every capability must reduce work, reduce stress, or increase opportunity. If it does none of these, it should be challenged.

## Governing Principles
1. Protect the Business Graph.
2. Architecture is the product; code serves the architecture.
3. Business capability matters more than software.
4. Migrate business knowledge, not legacy software.
5. Keep architecture stable and implementation adaptable.
6. Every feature should make the next feature easier to build.
7. Build for real Elev8 businesses first, then generalize through configuration.

## Data Sovereignty
Business data belongs to the organization. Personal data belongs to the individual.

Personal information must never enter Business Intelligence, Product Intelligence, company reporting, company AI, employer exports, or business analytics unless the individual deliberately creates a governed business record from it. Employers may access only business data authorized by role, scope, purpose, and policy.

## Business Graph
Relationships are more important than isolated records. Every object has one authoritative owner. Elev8 OS links authoritative records instead of cloning them. Dashboards, modules, reports, automation, and AI consume shared engines and governed relationships; they do not own duplicate business logic.

## Engine and Module Discipline
Engines contain reusable business capability. Modules are configurable experiences that consume engines. Dashboards are views over shared engines. New engines require broadly reusable ownership that cannot responsibly live in an existing engine.

## Configuration First
Business policies, forms, readiness cards, experience standards, workflows, notifications, permissions, approval paths, and thresholds should be configurable whenever practical. Elev8-specific policy must not be hardcoded as platform architecture.

## Business Memory and Product Intelligence
Never lose useful business knowledge. Operational logs, meetings, support conversations, customer feedback, incidents, decisions, actions, and lessons should become governed Business Memory. Repeated reports should increase one issue's occurrence count and priority rather than create disconnected noise.

## Shared Platform Services
Commerce Integration, Digital Assets, AI Gateway, QR/Capture, Telephony Integration, Persistent Device Sessions, Push Notifications, and Report a Problem are shared services. External systems remain replaceable through adapters.

## Commerce, Booking, and Payments
WooCommerce currently owns products, orders, checkout, payments, taxes, and subscriptions. Commerce integrations must use adapters so WooCommerce, BigCommerce, Shopify, and future providers can be connected without redefining operations.

Amelia remains authoritative during gradual Booking Engine replacement. Never perform a destructive migration. Extract business rules, relationships, and workflow knowledge while replacing capability incrementally.

Never store or transcribe payment card data. Employees request payment through a PCI-compliant provider; customers complete payment independently.

## Mobile Reliability
Elev8 OS should behave like a real mobile application through trusted-device login, persistent sessions, safe refresh, device registration, remote logout, push notifications, and deep links. Security remains stronger than convenience.

## Human Data Entry
People use clear controls and human-readable formats. Internally, Elev8 OS stores normalized values optimized for accounting, reporting, automation, and reliable calculation. Formatting belongs at the UI boundary.

## AI Governance
AI reduces cognitive load by organizing, summarizing, drafting, prioritizing, and detecting patterns. AI must not bypass engine rules, invent authoritative facts, silently execute high-impact actions, or expose personal data. Important decisions remain explainable and human-governed.

## Live-Beta and Release Discipline
The real business is the laboratory. Deploy small, safely, reversibly, and to controlled roles. Every release must deliver real business value, preserve or strengthen architecture, be independently testable, be rollback-ready, and record what changed.

## Permanent Development Rhythm
Architecture → Real Feature → Architecture Improvement → Real Feature.

Every development session reads the Constitution and Business Blueprint, classifies the capability, inspects current ownership, implements against the uploaded source of truth, validates the release, updates architectural memory, and returns exact changed files.

## Operational Reality and Defect Learning
Elev8 OS evolves through real business use. Operational experience is a primary source of architectural truth, not an inconvenience to be hidden. Every defect must improve the platform's ability to prevent, detect, explain, isolate, or recover from similar defects in the future. A one-line repair is incomplete when the same class of failure remains possible elsewhere.

## One Application, Contextual Experience
Elev8 OS is one application with one governed shell and one canonical dashboard route. Roles describe work, titles describe people, permissions authorize actions, and workspaces organize context. Engines and services own capability; workspaces, widgets, and actions present it. New standalone operational dashboards, headers, menus, and navigation systems are prohibited.

## Registered Navigation and Safe Legacy Retirement
All application navigation must resolve through registered routes or governed workspace actions. Shell navigation may not point to unknown, optional, or unvalidated module methods. Legacy pages are inventoried and redirected only after their business capability and data ownership are preserved. No user-visible menu item may be allowed to produce a fatal error merely because the path is rarely used.

## Architecture and Business Progress
Development maintains a deliberate rhythm: strong architecture enables small practical wins, and real use of those wins exposes the next architectural improvement. Releases should remain focused when combining architecture and feature work would increase risk.


## Platform Kernel Principle

Elev8 OS shall have one governed platform bootstrap and extension boundary. The Platform Kernel coordinates registration and boot order for shared platform capabilities, but it never owns business logic or business data. Engines and services remain independently testable and reusable. New platform services must register through the kernel rather than creating disconnected boot paths.

The kernel must evolve through focused migrations. Existing capability is moved into it only when the migration is independently testable, rollback-ready, and protected by build-time validation.
