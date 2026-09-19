# SOKNA 1.36.4-dev.34 — Phase 6C

## Server-persistent Table Draft
- Added one Local-owned active draft per table.
- Added server-shared draft state for authorized staff with optimistic version conflicts.
- Normal Quick Order now uses the server draft as authority.
- Draft Save has zero canonical order/business-number/preparation/inventory/finance/receipt side effects.
- Finalize revalidates current Local state and delegates to the canonical Staff Order transaction.
- Draft lifecycle is explicit Finalize/Cancel with no auto-expiry.
- Realtime remote draft operations require reachable Local and are never Deferred-safe.
- Added multi-context browser coverage so stale staff state cannot overwrite a newer draft.

## Migration
Apply:
`docs/architecture-migration-r2/PHASE6C_LOCAL_MIGRATION.sql`

## Validation
- Phase 6C final branch gates: Windows / Public+Local MariaDB / Linux+Browser PASS.
- PR #10 merge commit: `ccf0656655702a0b175a7cb9d7521fcb808745b1`.
- Product post-merge run `35438494232`: SUCCESS.

See:
- `docs/architecture-migration-r2/PHASE6C_CHECKPOINT_FA.md`
- `docs/handoffs/PHASE6C_HANDOFF_FA.md`
