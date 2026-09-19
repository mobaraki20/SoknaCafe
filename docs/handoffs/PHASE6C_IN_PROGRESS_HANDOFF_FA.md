# Phase 6C In-Progress Handoff — Server-persistent Table Draft

Status: **IN PROGRESS — DO NOT RESTART**
Date: 2026-09-19
Repository: `mobaraki20/SoknaCafe`
Safe completed main: `3b1c35c8bf7e06a512194559736fbba0303fec46`
Completed release checkpoint on main: `1.36.4-dev.33`
Active branch: `phase/6c-table-draft`
Active branch head: `756d805912351d6dd539f9922e9bd97144369b63`
Branch position: **30 commits ahead of main, 0 behind**

## Frozen target
- exactly one active draft per table.
- server-persistent and shared between authorized staff.
- no canonical order row before finalize.
- no business order number before finalize.
- no preparation task/ticket before finalize.
- no inventory movement before finalize.
- no finance/settlement/receipt before finalize.
- no auto-expiry.
- explicit finalize or cancel.
- finalize revalidates table/session, permission, catalog, price, availability, fulfillment.
- finalize delegates to canonical Staff Order commit owner.
- remote Draft operations are Realtime/Local-required only.
- Table Draft is NEVER Deferred-safe.

## What is already implemented on the active branch
Do not rebuild these from scratch.

### Schema / migration
- `table_drafts`
- `table_draft_items`
- one-active-draft-per-table guard.
- additive migration:
  - `docs/architecture-migration-r2/PHASE6C_LOCAL_MIGRATION.sql`
- design notes:
  - `docs/architecture-migration-r2/PHASE6C_DESIGN_NOTES_FA.md`

### Canonical owners
- `includes/staff_order_service.php`
  - canonical staff order commit extracted from legacy Quick Order endpoint.
- `includes/table_draft.php`
  - draft lifecycle owner.
- `includes/relay_actor.php`
  - shared Local Relay actor resolution.

### Local surface
- `staff/api_table_draft.php`
- `staff/quick-order.php`
- `assets/css/quick-order.css`
- `staff/api_quick_order.php` is now a thin consumer of canonical staff-order service.

### Realtime / remote
- Table Draft realtime kinds registered in `includes/relay_protocol.php`.
- dispatch adapters in `includes/relay_dispatch.php`.
- Public realtime enqueue/result hardened.
- result lookup bound to originating actor.
- no Table Draft enqueue while Local stale.
- finalized Draft lookup exists for transport recovery.
- Table Draft is not added to Deferred-safe registry.

### Tests already added
- `tests/phase6c-table-draft-contract.php`
- `tests/phase6c-table-draft-local.php`
- Phase 6C tests are registered in full gate/CI.
- module ownership updated for new schema/routes.

## Latest CI state
Workflow: `35426707212`
Head: `756d805912351d6dd539f9922e9bd97144369b63`

PASS:
- Windows Phase 1 runtime validation.
- Phase 2/Public Relay HTTP + MariaDB integration.
- Phase 5/6A/6B contracts.
- Phase 6C Table Draft boundary contract.
- Phase 6C MariaDB/local integration in the Public/MariaDB job.

FAIL:
- Linux regression gate.
- first current failing contract: `tests/itemized-settlement-contract.py`

Failing checks:
1. `runtime edit locks`
2. `late-accounting requires active itemized session`
3. `late-accounting suppresses preparation only`
4. `late-accounting audited`

## Failure analysis — important
Three late-accounting checks are almost certainly **test owner drift**, not missing product behavior.

On active branch:
- `staff/api_quick_order.php` is now a thin route.
- the moved canonical behavior exists in `includes/staff_order_service.php`:
  - `$mode === 'late_accounting'`
  - `!$itemizedActive`
  - `$expectedSessionId !== $sessionId`
  - `$mode !== 'late_accounting' && $hasPreparation`
  - `inventory_enqueue_order_event_tx`
  - `order.late_accounting_created`
  - `preparation_suppressed'=>true`

Therefore, first update the regression test's source composition to follow the canonical owner. Do NOT move logic back into `staff/api_quick_order.php` just to satisfy the old test.

However, `runtime edit locks` requires separate verification:
- the contract expects runtime editing paths to retain settlement edit locks.
- `includes/staff_order_service.php` currently contains `settlement_session_has_active_itemized_locked`, but not the literal `settlement_assert_session_editable_locked`.
- compare the pre-extraction Quick Order behavior from safe main / parent commit with current `staff_order_service.php`.
- if a real settlement edit-lock invariant was lost during extraction, restore it in the canonical service.
- only if semantics are proven equivalent should the test be updated instead.

## Exact next-agent procedure
1. Checkout `phase/6c-table-draft` at head `756d805...`.
2. Read:
   - this file,
   - `PHASE6C_DESIGN_NOTES_FA.md`,
   - `includes/staff_order_service.php`,
   - `includes/table_draft.php`,
   - `tests/itemized-settlement-contract.py`.
3. Compare old Quick Order source on main:
   ```bash
   git show 3b1c35c8bf7e06a512194559736fbba0303fec46:staff/api_quick_order.php
   ```
4. Fix owner-aware regression assertions for moved late-accounting behavior.
5. Validate whether settlement edit lock is behaviorally preserved. Fix product only if genuinely missing.
6. Run full gate.
7. Require on final head:
   - Windows PASS
   - Public + Local MariaDB PASS
   - Linux + Browser PASS
8. Create/update:
   - `docs/handoffs/PHASE6C_HANDOFF_FA.md`
   - `docs/handoffs/CURRENT_STATUS_FA.md`
   - Master source map if ownership materially changes.
9. PR to main, merge with expected head SHA.
10. Require post-merge CI on main.
11. Only then mark Phase 6C COMPLETE.

## Never do
- never restart Phase 6C from main.
- never create a second Table Draft owner.
- never write an order row on Draft Save.
- never allocate business order number before Finalize.
- never enqueue preparation/inventory/finance on Draft Save.
- never make Table Draft Deferred-safe.
- never make Public the Table Draft authority.
- never weaken a regression assertion until you determine whether it is owner drift or a real lost invariant.
