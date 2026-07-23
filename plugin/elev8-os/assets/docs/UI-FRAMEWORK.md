# Elev8 UI Framework

## Governing rule
Engines expose data, actions, permissions, notifications, and business rules. Engines never control presentation. The UI Framework decides how capability is presented.

## Foundation
- Design tokens: spacing, typography, color, radius, shadow, touch sizing, and content width.
- Components: cards, buttons, forms, badges, notices, grids, dialogs, and navigation items.
- Shell contexts: Business, Executive, Studio, Retail, Artist, and Event.
- Theme packs: brandable visual choices layered over the same components.
- Legacy bridge: temporary compatibility rules while old screens migrate.

## Migration rule
Move one real workspace at a time. Do not rewrite all dashboards simultaneously. Every migrated workspace must deliver a business win, use shared components, pass role-access testing, and work at 360px mobile width before legacy CSS is retired.
