# Phase 6A Checkpoint — Preparation Permission Split

Version: `1.36.4-dev.32`

## Frozen behavior satisfied
- Preparation visibility and actionability are separate server-side concepts.
- `shift_supervision` alone = global read-only Preparation monitor.
- `preparation` alone = visibility/action only for assigned preparation areas.
- `shift_supervision + preparation` = global visibility, mutation only for assigned areas.
- Admin role alone never grants Preparation operational mutation.
- Browser never infers action authority from role labels.
- Preparation feed is side-effect free; opening/refreshing the page does not mutate adjustment state.
- Mutation route revalidates current server authority and area on every action.

## Primary implementation files
- `includes/preparation_permissions.php`
- `bootstrap.php`
- `waiter/api_feed.php`
- `waiter/api_action.php`
- `waiter/index.php`
- `assets/js/waiter.js`

## Tests
- `tests/phase6a-preparation-permissions-contract.php`
- `tests/phase6a-preparation-local.php`
- `tests/phase6a-preparation-area-browser.py`
- full existing dev gate

## No schema migration
Phase 6A reuses:
- `user_capabilities`
- `user_preparation_areas`
- existing Preparation claim/adjustment tables.

## Next subphase
Phase 6B — explicit sellable kind:
- add explicit `menu_item | service_item` type
- remove service behavior inference from category/station
- preserve historical order semantics
