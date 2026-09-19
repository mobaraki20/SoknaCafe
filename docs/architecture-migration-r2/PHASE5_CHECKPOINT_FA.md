# Phase 5 Checkpoint — Deferred-safe + Financial Reconciliation

Version: `1.36.4-dev.31`

## Frozen behavior satisfied
- Deferred store and Realtime store are physically/logically separate.
- Deferred enqueue is not Business success.
- Local is the only commit authority.
- `occurred_at` is preserved separately from commit time.
- Current Local permission/state/version is revalidated.
- Stock-count finalize remains Local-only.
- Subscriber payment becomes official only after Local ledger commit; reversal stays Local-only.
- Expense creation is Deferred-safe; reversal/correction is not.
- Closed-period event creates one review candidate and never silently back-posts.
- Normal period close blocks on Deferred blockers or paired Public unknown state.
- Override requires Admin + explicit reason + durable audit.
- Duplicate/lost-ACK paths return the persisted canonical Local result.

## Primary implementation files
Local:
- `includes/deferred.php`
- `includes/expenses.php`
- `includes/inventory.php`
- `modules/Supply/domain.php`
- `tools/deferred-worker.php`
- `admin/financial_periods.php`
- `admin/inventory_count.php`
- `database/schema.sql`

Public:
- `public_edge/api/v1/deferred/*`
- `public_edge/api/v1/local/deferred/*`
- `public_edge/database/schema.sql`
- `public_edge/database/migrations/005_phase5_deferred_work.sql`
- `public_edge/staff/*`

## Tests
- `tests/phase5-deferred-boundary-contract.php`
- `tests/phase5-deferred-http.php`
- `tests/phase5-deferred-local.php`
- full existing dev gate

## Rollback / recovery boundary
Database changes are additive. A code rollback must use the mandatory Recovery Point when it would return to a version whose code does not understand the new schema/Deferred state. Never manually delete Deferred receipt/review or expense history during rollback. Public old edge must not be used with pending Phase5 work without an explicit recovery decision.

## Next phase
Phase 6 internal order:
1. Preparation visible/actionable permission split
2. explicit sellable kind
3. server Table Draft
4. batch purchase
5. Expenses UI completion + correction/reversal
6. Tax owner/calculator/snapshots/UI
