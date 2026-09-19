# Release Notes — SOKNA 1.36.4-dev.31

## Scope
Phase 5 از Handover R2: Deferred-safe + Financial Reconciliation.

## What changed
- Public `deferred_work` مستقل از Realtime با stateهای `pending_sync / committed / needs_review / rejected`.
- Local durable receipt/review ledger برای exactly-once Business effect و lost-ACK recovery.
- Deferred-safe flows: Supply Need/Status/Receipt، Inventory Waste، Count Draft، Subscriber Payment، General Expense.
- Expenses owner و دسته‌های پایه.
- Cached `deferred_context` و فرم‌های 4G permission-aware.
- Financial Period close gate بر اساس Deferred/Public state.
- Admin review/override با reason + audit.
- Late closed-period work قبل از تصمیم صریح هیچ Business mutation ندارد.
- Inventory count draft owner بین Local UI و Deferred یکپارچه شد.

## Not changed
- Printing state machine / Agent.
- Realtime queue semantics.
- Settlement، committed-order mutations، preparation mutations، count finalize، inventory correction، payment/expense reversal.
- Canonical Business authority: همچنان Local.

## Database
Local adds:
- `expense_categories`
- `expenses`
- `deferred_work_receipts`
- `deferred_review_items`
- `financial_period_close_overrides`

Public adds:
- `deferred_work`

Local migration source:
`docs/architecture-migration-r2/PHASE5_LOCAL_MIGRATION.sql`

Public migration:
`public_edge/database/migrations/005_phase5_deferred_work.sql`

## Upgrade
Accepted predecessor: `1.36.4-dev.30`.
Target: `1.36.4-dev.31`.

Update must create a Recovery Point before database migration/cutover. Open tables/orders/accounts are not blockers by themselves; only an in-flight commit requires short quiescence.

## Validation
Required final gates:
- Windows runtime + PHP/JS syntax + TLS
- Public/Local MariaDB integration including Phase 5 HTTP and Local-domain tests
- Linux full regression + browser gate
- post-merge main rerun of the same three jobs

## UAT status
Pre-Operational. Production/Pilot acceptance still requires real device/network/printer UAT where applicable.
