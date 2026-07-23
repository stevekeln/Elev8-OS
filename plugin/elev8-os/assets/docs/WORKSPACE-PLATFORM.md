# Elev8 Experience Platform — 21.0 Foundation

## Decision
Business engines expose data, actions, permissions, and events. They never own responsive layout. The Experience Platform assembles those capabilities through four governed registries:

1. **Workspace Definition Registry** — role/capability-aware workspace metadata.
2. **Widget Registry** — reusable presentation projections supplied by engines.
3. **Responsive Grid Service** — one-column phone, adaptive tablet, and multi-column desktop layout.
4. **UI Framework / Shell Layer** — navigation, theme packs, components, and application chrome.

## Migration rule
Existing dashboards remain operational. Each is migrated separately without rewriting its business logic. The first target is Glass Manager, followed by CEO, Retail, Artist, and Operational Readiness.

## Extension contract
Use the `elev8_os_workspace_definitions` and `elev8_os_widgets` filters or the registry `register()` methods. New widgets must identify their source engine, access boundary, size, and render/data callbacks.

## Non-negotiable boundaries
- No duplicate business records.
- No role checks outside the centralized Access Service when a capability exists.
- No engine-specific CSS layout systems.
- No dashboard-owned business logic.
- Report a Problem remains universally reachable.


## 21.1 Live Runtime

The first real front-end workspace is available at `/elev8-workspace/`. Studio users receive verified Glass Operations metrics, workspace actions, and universal problem reporting through the shared widget grid.

## 21.2.0 — Workspace Shell v1

The live workspace route now uses a shared shell contract: one restrained role-aware header, a full-width responsive content frame, one universal problem-report control in the top application bar, and consistent phone navigation for Home, Today, Actions, and Messages. Workspace definitions continue to supply widgets and actions; engines remain authoritative for data and business rules.
