# SOKNA — Current Project Status

Updated: 2026-09-19
Repository: `mobaraki20/SoknaCafe`
Current verified main checkpoint: Phase 6A / `1.36.4-dev.32`
Active branch: `phase/6b-explicit-sellable-kind`
Active release candidate: `1.36.4-dev.33`
Active status: VALIDATION / PENDING MERGE

## Completed on main
- Phase 0 — Source Audit.
- Phase 1 — Runtime Foundation.
- Phase 2 — Public Edge + Realtime Relay. PR #1.
- Phase 3 — Guest/Public Snapshot Runtime. PR #2.
- Phase 4 — Permission-aware Remote Read Models. PR #3.
- Phase 5 — Deferred-safe + Financial Reconciliation. PR #4.
- Phase 6A — Preparation Permission Split. PR #5.

Latest completed handoff:
`docs/handoffs/PHASE6A_HANDOFF_FA.md`

## Active work: Phase 6B
Read:
`docs/handoffs/PHASE6B_HANDOFF_FA.md`

Implemented:
- explicit `items.sellable_kind = menu_item | service_item`,
- explicit admin selector,
- strict write validation,
- shared catalog/order propagation,
- new-order `sellable_kind_snapshot`,
- explicit known service migration/seed,
- audit coverage,
- no automatic takeaway service insertion.

Validation files:
- `tests/phase6b-sellable-kind-contract.php`
- `tests/phase6b-sellable-kind-local.php`

Do **not** mark Phase 6B complete until all three final-head CI jobs pass, branch is merged to main, and post-merge main CI succeeds.

## Next work after Phase 6B
Phase 6C — Server-persistent Table Draft.

Frozen constraints:
- one active draft per table,
- no prep/inventory/finance/order number/receipt before finalize,
- finalize revalidates catalog, prices, availability, table and permissions,
- remote only while Local reachable,
- not deferred-safe.

## New-agent startup
1. Fetch `main` and active branch if listed above.
2. Read this file.
3. Read latest active/completed handoff.
4. Read `DEVELOPER_READ_FIRST_FA.md` and R2 implementation plan.
5. Inspect latest GitHub Actions results.
6. Continue from the exact active status; never restart a completed phase.
