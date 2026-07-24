# Elev8 OS Unified Dashboard Migration

## Governing Decision

Elev8 OS has one application dashboard route. The shared shell remains stable while the active workspace, widgets, actions, permissions, and context change for the signed-in person.

## Architecture

User → Roles → Accessible Workspaces → Active Workspace → Widgets and Actions → Shared Engines

Pages do not own business capability. Engines and services own capability. The unified dashboard assembles the correct experience.

## Migration Method

Every existing surface is classified as one of:

1. Widget
2. Action
3. Workflow
4. Report
5. Settings screen
6. Obsolete

Old pages remain available as governed legacy bridges until their replacement is proven. They are not deleted merely to make the interface appear unified.

## Execution Order

1. Establish one canonical dashboard route.
2. Send every operational login and shell Home link to that route.
3. Register role-aware workspace definitions.
4. Limit the home experience to the highest-value actions and widgets.
5. Move one functional area at a time into native widgets/actions.
6. Redirect and retire a legacy page only after parity is verified.

## Definition of Done for a Migrated Surface

- Business logic remains in an engine or shared service.
- Permissions use the centralized Access Service.
- The capability renders inside the shared shell.
- Phone and desktop behavior are verified.
- The legacy URL redirects safely.
- No business records are lost or duplicated.


## 21.6.0 Studio Home Migration

The old `/glass-manager/` default dashboard now redirects to the canonical Studio Workspace. Tool-specific URLs remain as temporary bridges. The Studio Workspace exposes verified Studio Pulse data, four primary starting actions, and less common tools behind More tools.
