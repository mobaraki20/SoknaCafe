# Phase 6B Handoff — Explicit Sellable Kind

Status: VALIDATION / PENDING MERGE
Release candidate: `1.36.4-dev.33`
Branch: `phase/6b-explicit-sellable-kind`

## What changed
SOKNA now has an explicit sellable classification:
- `menu_item`
- `service_item`

The classification is persisted on `items.sellable_kind`, exposed through catalog/admin surfaces, and snapshotted to new `order_items.sellable_kind_snapshot` rows.

## Critical semantics
- Do not infer service type from category, station, name, staff-only, recipe, or takeaway mode.
- `service_item` is classification, not a hidden behavior bundle.
- Existing independent properties remain independent.
- Manual «سرویس بیرون‌بر» is explicit `service_item`, staff-only, station none.
- Never auto-add `SERVICE-TAKEAWAY` to takeaway orders.
- Historical committed order rows remain untouched by migration.

## Main files
- `includes/sellable.php`
- `database/schema.sql`
- `docs/architecture-migration-r2/PHASE6B_LOCAL_MIGRATION.sql`
- `admin/item_form.php`
- `admin/items.php`
- `includes/menu_catalog.php`
- `includes/functions.php`
- `includes/guest_order_service.php`
- `includes/guest_order_manage_service.php`
- `staff/api_quick_order.php`
- `operator/api_bill.php`
- `database/default_menu.json`
- `includes/default_menu_seed.php`
- `includes/function_domains/audit.php`

## Validation
Dedicated:
- `tests/phase6b-sellable-kind-contract.php`
- `tests/phase6b-sellable-kind-local.php`

Promotion requires:
- Windows Gate PASS
- Public/Local MariaDB Gate PASS
- Linux + Browser Gate PASS
- then PR merge and post-merge CI on `main`.

## Exact next task after merge
Phase 6C — Table Draft.

Start by auditing:
- table/session lifecycle owners,
- staff quick order/cart state,
- order create/finalize canonical transaction,
- permissions for table/order operations,
- existing browser-only draft state.

Do not create an order row, business order number, preparation task, inventory movement, settlement/finance side effect, or receipt before Draft Finalize.
