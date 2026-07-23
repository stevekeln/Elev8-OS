# Codex Implementation Guide for Elev8 OS

Codex acts as an engineering worker. The architecture chat remains the platform architect. The uploaded repository is the sole source of truth.

## First assignments
1. Inventory every dashboard, stylesheet, navigation implementation, breakpoint, and theme dependency.
2. Produce a migration map into Elev8 UI Framework components without changing business behavior.
3. Add automated role-route checks for CEO, Shop Manager, Shop Employee, Glass Manager, Artist, Teacher, Volunteer, and Event Host.
4. Migrate one workspace per pull request, beginning with Glass Manager Operational Home.
5. Identify CSS made obsolete by each migration; do not delete it until route and role regression tests pass.

## Non-negotiable rules
- Never create a second permission system.
- Never move business logic into templates or CSS.
- Never recreate the repository.
- Preserve WordPress and WooCommerce ownership boundaries.
- Keep changes scoped, reviewable, reversible, and rollback ready.
- Update BUILD_VERSION, changelog, Business Blueprint, and tests when required.

## Definition of done
- PHP syntax passes.
- Repository builder passes.
- Desktop and 360px mobile layouts pass.
- Actual-user preview matches permissions.
- No horizontal page overflow.
- No new hardcoded business policy.
