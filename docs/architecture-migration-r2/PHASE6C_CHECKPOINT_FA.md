# Phase 6C Checkpoint — Server-persistent Table Draft

Version: `1.36.4-dev.34`
Status: COMPLETE
PR: #10
Final branch head: `ea46ecf6209ff14329438684fb94720445ce2403`
Merge commit: `ccf0656655702a0b175a7cb9d7521fcb808745b1`
Product post-merge CI: `35438494232` — SUCCESS

## Frozen behavior implemented
- one active draft per table.
- Local/server persistence shared across authorized staff.
- optimistic version conflict protection.
- zero canonical order side effects before Finalize.
- explicit Finalize/Cancel; no auto-expiry.
- Finalize revalidates current business state and uses canonical Staff Order owner.
- normal browser draft is no longer the authority.
- Realtime remote draft requires reachable Local and is never Deferred-safe.

## Schema
- `table_drafts`
- `table_draft_items`
- nullable active-table guard enforcing one active draft/table.

Migration:
- `docs/architecture-migration-r2/PHASE6C_LOCAL_MIGRATION.sql`

## Canonical owners
- `includes/staff_order_service.php`
- `includes/table_draft.php`
- `staff/api_table_draft.php`
- relay adapter/actor owners under `includes/relay_*.php`.

## Validation
- final head push `35438283739`: all three jobs PASS.
- final head PR `35438286634`: all three jobs PASS.
- post-merge main `35438494232`: all three jobs PASS.
- multi-context browser test proves shared state + stale-write rejection.
- Local/MariaDB test proves one-active invariant, lifecycle, idempotent finalize and zero pre-finalize side effects.
- Public/Local HTTP test proves remote actor/permission and Local-required boundary.

## Next phase
Phase 7 — Printing / Notifications / Integrations.
Printing state machine is preserved; Phase 7 begins with audit and preservation contracts before worker internalization.
