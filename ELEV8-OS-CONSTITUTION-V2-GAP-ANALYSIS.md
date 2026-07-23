# Constitution v2 Repository Gap Analysis

## Current Strengths
- Business Graph registry and explicit ownership are already implemented.
- Central Access Service exists and is broadly adopted.
- Business Memory, Work Items, Operations, Organization, Intelligence, Integration adapters, configurable readiness cards, and mobile home already exist.
- Repository release tooling, rollback artifacts, manifests, and historical releases are present.

## Highest-Priority Gaps
1. Report a Problem was limited to technology incidents rather than a universal platform feedback service.
2. Mobile reliability still lacks a complete trusted-device session, device registry, remote logout, refresh, and deep-link foundation.
3. Push/browser notifications exist but are not yet a complete governed Push Notification Service.
4. Commerce integrations exist directly for WooCommerce and Amelia; the generic adapter boundary needs broader operational use.
5. QR scanning is not yet centralized as one Capture Service with contextual handlers.
6. Digital assets are distributed across features rather than governed through one reusable asset lifecycle.
7. Product Intelligence lacks a unified issue queue, duplicate clustering, occurrence counts, and release-quality view.
8. Booking replacement remains architectural rather than an incremental native Booking read model and workflow.
9. Personal Data Sovereignty needs enforceable classification and export/report boundaries, not documentation alone.

## First Implemented Win
Release 20.3.0 introduces the universal Report a Problem foundation. It captures normalized platform reports, groups likely duplicates by fingerprint, increases occurrence counts, records page/device context, supports attachments, and gives authorized leaders a triage queue. It does not replace IT Support; it connects general product and operating feedback to future Product Intelligence.

## Recommended Next Stage
Build the Mobile Reliability Foundation in this order: device registration, session inventory, remote logout, safe session refresh, notification deep links, then install-state and push delivery hardening.
