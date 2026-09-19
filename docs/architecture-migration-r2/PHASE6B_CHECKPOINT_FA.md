# Phase 6B Checkpoint — Explicit Sellable Kind

Version: `1.36.4-dev.33`

Status: VALIDATION / PENDING MERGE

## Frozen behavior implemented
- Every sellable has explicit `sellable_kind`: `menu_item` or `service_item`.
- New/legacy unspecified items default to `menu_item`.
- Service classification is never inferred from category, name, preparation station, staff-only flag, recipe state, or fulfillment mode.
- Service properties remain separate explicit settings: a service *may* be staff-only, recipe-less, or no-preparation, but kind does not silently force those properties.
- UI exposes `نوع مورد: آیتم منو | خدمت`.
- Invalid write-time kind is rejected; read-time normalization remains tolerant for legacy/null data.
- Known default services `SERVICE-TAKEAWAY` and `SERVICE-CAKE` are explicitly seeded/migrated as `service_item`.
- `SERVICE-TAKEAWAY` remains a manual staff-only packaging service. Takeaway fulfillment never auto-adds it.
- New order lines snapshot `sellable_kind_snapshot`.
- Historical committed order rows are not backfilled/re-written by migration.
- Kind changes are included in menu-item audit snapshots.

## Schema
Local:
- `items.sellable_kind VARCHAR(20) NOT NULL DEFAULT 'menu_item'`
- `order_items.sellable_kind_snapshot VARCHAR(20) NULL`

Migration:
- `docs/architecture-migration-r2/PHASE6B_LOCAL_MIGRATION.sql`

The migration:
- adds the two additive fields,
- marks only documented service item codes explicitly,
- does not infer from category/station,
- does not rewrite historical order-line snapshots.

## Canonical owner/helper
- `includes/sellable.php`
  - `sellable_kinds()`
  - `normalize_sellable_kind()`
  - `require_sellable_kind()`
  - `sellable_kind_label()`
  - `sellable_item_is_service()`

## Main write/read paths updated
- `admin/item_form.php`
- `admin/items.php`
- `includes/menu_catalog.php`
- `includes/functions.php::order_catalog_items_locked()`
- `includes/guest_order_service.php`
- `includes/guest_order_manage_service.php`
- `staff/api_quick_order.php`
- `operator/api_bill.php`
- `includes/default_menu_seed.php`
- `database/default_menu.json`
- `includes/function_domains/audit.php`

## Tests
- `tests/phase6b-sellable-kind-contract.php`
- `tests/phase6b-sellable-kind-local.php`
- full Linux regression/browser gate
- Windows lint/contracts/runtime gate
- Local/Public MariaDB integration job

## Explicit non-behavior
This subphase does **not** make `service_item` automatically:
- staff-only,
- preparation-less,
- recipe-less,
- tax-exempt,
- takeaway-only.

Those remain independent explicit domain settings.

## Next subphase
Phase 6C — Server-persistent Table Draft.

Frozen target:
- one active draft per table,
- persistent/authorized shared draft,
- no order number/preparation/inventory/finance before finalize,
- explicit finalize/cancel,
- finalize revalidates catalog/prices/availability/table/permissions,
- remote access only while Local reachable,
- never deferred-safe.
