# Phase 6C Design Notes — Server-persistent Table Draft

Release target: `1.36.4-dev.34`  
Branch: `phase/6c-table-draft`

## Frozen target
- exactly one active draft per table.
- server-persistent and shared between authorized staff.
- no `orders` row before finalize.
- no business order number before finalize.
- no Preparation/print/inventory/finance side effect before finalize.
- no auto-expiry.
- explicit finalize/cancel only.
- finalize revalidates table/session, current catalog, current price, availability, fulfillment and current permission.
- finalize delegates to canonical Staff Order transaction; no duplicate SQL owner.
- remote operations are Realtime/Local-required only; never Deferred-safe.

## Audit findings
- Current Quick Order browser draft is only `sessionStorage` in `assets/js/staff-quick-order.js`.
- Current canonical staff-order commit is embedded in `staff/api_quick_order.php`.
- `table_sessions` already uses nullable unique guard `live_table_guard`; Table Draft should reuse this compatible pattern with `active_table_guard`.
- Relay vocabulary/capability already reserves:
  - `table_draft.create`
  - `table_draft.edit`
  - `table_draft.finalize`
  - `table_draft.cancel`
  - projected capability `orders.table_draft`
- Quick Order normal mode authority is admin or `orders_floor`.
- Quick Order has mature idempotency, session conflict, pending guest-order, itemized settlement, accommodation-block, catalog/price/fulfillment validation, print/preparation, push and inventory semantics. These must be extracted, not copied.

## Planned canonical owners
### Staff order commit
New: `includes/staff_order_service.php`
- `staff_order_commit_tx(PDO $pdo,array $payload,array $user): array`
- requires open transaction.
- owns the existing Quick Order commit semantics.
- both Quick Order API and Draft Finalize call it.

### Table Draft
New: `includes/table_draft.php`
- one active draft/table through `active_table_guard` unique guard + row locks.
- optimistic `version`.
- create/get/edit/cancel/finalize.
- full-draft replace for edit.
- server snapshots item name/price/kind for rendering only; finalize never trusts snapshots.

## Planned schema
`table_drafts`
- id
- table_id
- state: active/finalized/cancelled
- active_table_guard nullable unique
- version
- expected_session_id nullable
- note
- created_by_user_id / updated_by_user_id
- finalized_by_user_id / cancelled_by_user_id
- final_order_id nullable
- timestamps

`table_draft_items`
- draft_id
- item_id
- item_name_snapshot
- unit_price_snapshot
- sellable_kind_snapshot
- quantity
- item_note
- fulfillment_mode
- sort_order
- unique (draft_id,item_id,fulfillment_mode)

## Concurrency
- lock table first.
- then active draft.
- edit requires expected_version == current version.
- finalize locks table/draft and calls Staff Order owner in same transaction.
- duplicate finalize returns finalized order result, never creates order #2.
- stale edit is rejected with current version.

## Browser migration
- server draft becomes authoritative.
- local browser storage remains only for ambiguous transport recovery until server result is known.
- selecting a table loads active server draft.
- edits save to server with optimistic version.
- cancel explicitly removes active draft.
- final submit calls Draft Finalize, not direct Quick Order POST.
- late_accounting remains direct existing Quick Order path and is not a Table Draft flow.

## Remote
- Public must not store Table Draft Business state.
- authenticated Public remote endpoint enqueues realtime draft kinds.
- Local revalidates projected actor against current Local user/capability.
- no Local heartbeat => request rejected/disabled, never queued as Deferred.

## Tests required before merge
- schema/source boundary contract.
- Local MariaDB: one-active invariant, shared get/edit, optimistic conflict, cancel, zero side-effects before finalize, finalization, duplicate finalize.
- Quick Order regression proves same staff-order owner.
- Relay/Public HTTP test for Local-required draft mutation and actor permission.
- browser test proves two staff contexts see same draft and stale version cannot overwrite.
- full Windows/Public-MariaDB/Linux+Browser gates.
