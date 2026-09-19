# SOKNA — Current Project Status

Updated: 2026-09-19
Repository: `mobaraki20/SoknaCafe`
Current main commit: `32e9b3b239ad3320a7af2ec168fb7f19acbd589c`
Current release checkpoint: `1.36.4-dev.32`

## Completed architecture migration
- Phase 0 — Source Audit: complete.
- Phase 1 — Runtime Foundation: complete.
- Phase 2 — Public Edge + Realtime Relay: complete. PR #1.
- Phase 3 — Guest/Public Snapshot Runtime: complete. PR #2.
- Phase 4 — Permission-aware Remote Read Models: complete. PR #3.
- Phase 5 — Deferred-safe + Financial Reconciliation: complete. PR #4.
- Phase 6A — Preparation Permission Split: complete. PR #5.

## Latest verified checkpoint
Phase 6A merged to `main` at:
`32e9b3b239ad3320a7af2ec168fb7f19acbd589c`

GitHub Actions run:
`35422830131`
Result: SUCCESS.

Behavior now frozen:
- `shift_supervision` alone = global Preparation read-only monitor.
- `preparation` alone = visibility/action only in assigned areas.
- both together = global visibility, mutations only in assigned areas.
- Admin role alone does not imply Preparation operational mutation.
- server owns `visible_preparation_areas` and `actionable_preparation_areas`.
- feed is read-only; mutation route rechecks current authority and area.

Read next:
`docs/handoffs/PHASE6A_HANDOFF_FA.md`

## Next work
Start Phase 6B — Explicit Sellable Kind.

Frozen target:
- explicit `kind = menu_item | service_item`.
- UI label: `نوع مورد: آیتم منو | خدمت`.
- default existing/new normal catalog items to `menu_item` unless explicit service migration rule is documented.
- Service Item can be staff-only, recipe-less and preparation-less but still has price/discount/tax/history.
- never infer Service Item from category name or preparation station.
- current «سرویس بیرون‌بر» remains a manual packaging service for leftovers after dine-in; it must not become an automatic fee for every takeaway.
- historical committed orders must not be rewritten.

Expected later Phase 6 subphases:
- 6C Table Draft.
- 6D Batch Purchase.
- 6E Expenses UI/correction.
- 6F Tax.

## Agent startup checklist
1. Fetch `main` and verify it contains the commit above or a descendant.
2. Read this file and the latest handoff.
3. Check latest successful GitHub Actions run on `main`.
4. Create a dedicated branch from current `main`.
5. Preserve Local authority and all R2 non-negotiables.
6. Before merge: full Windows + Public/MariaDB + Linux/browser gates.
7. Update this file and create the next handoff in the same PR.
