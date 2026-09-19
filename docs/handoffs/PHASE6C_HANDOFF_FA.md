# Phase 6C Handoff — Server-persistent Table Draft

Status: **COMPLETE**
Release: `1.36.4-dev.34`
Merged PR: #10
Final branch head: `ea46ecf6209ff14329438684fb94720445ce2403`
Merge commit: `ccf0656655702a0b175a7cb9d7521fcb808745b1`
Product post-merge CI: `35438494232` — SUCCESS
Branch: `phase/6c-table-draft`

## What changed
- Added Local-owned `table_drafts` and `table_draft_items`.
- Enforced one active draft per table with optimistic `version` concurrency.
- Extracted canonical staff-order commit behavior into `includes/staff_order_service.php`.
- Added canonical draft lifecycle owner `includes/table_draft.php`.
- Added Local endpoint `staff/api_table_draft.php`.
- Migrated normal Quick Order draft authority from browser-only persistence to the server draft.
- Added authenticated Realtime Table Draft kinds/adapters with Local actor revalidation.
- Table Draft remains unavailable through Deferred-safe paths.

## Critical semantics
- Save/Edit Draft never creates an `orders` row.
- No business order number exists before Finalize.
- No preparation ticket/work, inventory event, finance/settlement mutation or receipt exists before Finalize.
- Draft has no auto-expiry; only explicit Finalize/Cancel closes active lifecycle.
- Finalize revalidates table/session, permissions, catalog, current price, availability and fulfillment.
- Finalize delegates to canonical Staff Order transaction; there is no second order-commit owner.
- duplicate/retried Finalize resolves idempotently to the finalized order rather than creating order #2.
- stale editor receives conflict/current version and cannot overwrite newer server state.
- remote mutation is Realtime/Local-required; Public is not business authority.

## Main files
- `database/schema.sql`
- `docs/architecture-migration-r2/PHASE6C_LOCAL_MIGRATION.sql`
- `docs/architecture-migration-r2/PHASE6C_DESIGN_NOTES_FA.md`
- `includes/staff_order_service.php`
- `includes/table_draft.php`
- `includes/relay_actor.php`
- `includes/relay_protocol.php`
- `includes/relay_dispatch.php`
- `staff/api_quick_order.php`
- `staff/api_table_draft.php`
- `staff/quick-order.php`
- `assets/js/staff-quick-order.js`

## Validation
Final branch head:
- push run `35438283739`: Windows PASS / Public+Local MariaDB PASS / Linux+Browser PASS.
- PR run `35438286634`: Windows PASS / Public+Local MariaDB PASS / Linux+Browser PASS.

After PR #10 merge:
- main run `35438494232`: SUCCESS on all three required jobs.

Dedicated coverage includes:
- `tests/phase6c-table-draft-contract.php`
- `tests/phase6c-table-draft-local.php`
- `tests/phase6c-table-draft-http.php`
- `tests/phase6c-table-draft-browser.py`
- owner-aware regressions for settlement, inventory and order hardening after Staff Order extraction.

Browser coverage proves two staff contexts share one server draft and a stale version cannot overwrite the newer quantity.

## Migration
Apply:
`docs/architecture-migration-r2/PHASE6C_LOCAL_MIGRATION.sql`

## UAT not proven by hosted CI
- real cashier/staff multi-device behavior on café LAN.
- Wi-Fi/mobile connectivity transitions with real Public/Local pairing.
- actual Windows service/install/upgrade environment.
- physical printer behavior remains a Phase 7/real-device concern.

## Exact next phase
Phase 7 — Printing / Notifications / Integrations.

Start with an audit. Preserve the printing state machine and only internalize worker ownership under Runtime incrementally.
