# Phase 6A Handoff — Preparation Permission Split

Status: COMPLETE
Release: `1.36.4-dev.32`
Merged PR: #5
Merge commit: `32e9b3b239ad3320a7af2ec168fb7f19acbd589c`
Post-merge CI: `35422830131` — SUCCESS

## Problem solved
Legacy Preparation mixed visibility and mutation authority:
- supervisor-only users could not reliably enter the feed.
- supervisor + preparation could be restricted to assigned areas for visibility when global visibility was required.
- role/capability inference risked granting mutation too broadly.

## Canonical owner
`includes/preparation_permissions.php`

Main API:
- `preparation_access_context()`
- `preparation_visible_areas()`
- `preparation_actionable_areas()`
- `preparation_can_mutate_area()`

## Final semantics
- Preparation only → assigned areas visible/actionable.
- Shift supervision only → all areas visible; none actionable.
- Shift supervision + Preparation → all areas visible; only assigned actionable.
- Admin → all areas visible; none actionable from role alone.

## Routes
Read:
- `waiter/api_feed.php`
  - accepts `orders_floor | preparation | shift_supervision`.
  - has no Preparation write side effect.
  - emits explicit visible/actionable scopes.

Mutation:
- `waiter/api_action.php`
  - does not accept supervisor-only access.
  - re-evaluates canonical access context every request.
  - area mutation requires current actionable-area authority.

UI:
- `waiter/index.php`
- `assets/js/waiter.js`
Browser consumes server-authored scopes and does not infer rights from role labels.

## Tests
- `tests/phase6a-preparation-permissions-contract.php`
- `tests/phase6a-preparation-local.php`
- `tests/phase6a-preparation-area-browser.py`
- full project gate.

## Schema
No migration. Existing:
- `user_capabilities`
- `user_preparation_areas`
- Preparation claim/adjustment tables.

## Next exact task
Phase 6B — explicit Sellable Kind. Start by auditing:
- `items` schema and catalog CRUD.
- menu/admin item forms.
- order normalization/catalog loaders.
- preparation/recipe assumptions.
- manual packaging service («سرویس بیرون‌بر»).
Do not infer service kind from category or preparation station.
