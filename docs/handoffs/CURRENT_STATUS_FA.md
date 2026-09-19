# SOKNA — Current Project Status

Updated: 2026-09-19
Repository: `mobaraki20/SoknaCafe`
Current completed release checkpoint: `1.36.4-dev.33`

## Completed architecture migration
- Phase 0 — Source Audit.
- Phase 1 — Runtime Foundation.
- Phase 2 — Public Edge + Realtime Relay. PR #1.
- Phase 3 — Guest/Public Snapshot Runtime. PR #2.
- Phase 4 — Permission-aware Remote Read Models. PR #3.
- Phase 5 — Deferred-safe + Financial Reconciliation. PR #4.
- Phase 6A — Preparation Permission Split. PR #5.
- Phase 6B — Explicit Sellable Kind. PR #7.

## Latest verified checkpoint
Phase 6B merged to `main`:
`afa84a333ca34d8405af58f2bb3287e6aebfca39`

Post-merge GitHub Actions:
`35424762946` — SUCCESS

All three jobs PASS:
- Windows runtime/TLS.
- Public + Local MariaDB integration.
- Linux full regression + browser gates.

Read latest handoff:
`docs/handoffs/PHASE6B_HANDOFF_FA.md`

## Frozen behavior now
- Sellables are explicitly `menu_item | service_item`.
- Service classification is never inferred from category/station/name/fulfillment.
- Kind changes are auditable.
- New order lines snapshot sellable kind.
- Known internal services are explicitly classified.
- No automatic takeaway packaging fee.
- Historical committed order rows are not rewritten.

## Active in-progress work
Phase 6C — Server-persistent Table Draft.

Active branch:
`phase/6c-table-draft`

Active branch head:
`756d805912351d6dd539f9922e9bd97144369b63`

Branch position at this handoff:
- 30 commits ahead of `main`
- 0 behind

Latest Phase 6C CI:
`35426707212`

Status:
- Windows runtime/TLS: PASS
- Public + Local MariaDB: PASS
- Linux full regression: FAIL

The Phase 6C boundary contract itself PASSes. The current first failing regression is:
`tests/itemized-settlement-contract.py`

Read the exact in-progress handoff before touching code:
`docs/handoffs/PHASE6C_IN_PROGRESS_HANDOFF_FA.md`

Do **not** recreate Phase 6C from main. Continue the existing branch.

Frozen target:
- exactly one active draft per table.
- server-persistent and visible/editable to authorized staff.
- no order row, business order number, preparation work, inventory movement, finance posting, or receipt before finalize.
- no auto-expiry; explicit finalize/cancel.
- finalize revalidates current catalog, price, availability, table/session and permissions.
- finalize uses canonical Staff Order owner; no duplicated order transaction.
- remote draft operations are realtime/local-required only while Local is reachable.
- Table Draft is never Deferred-safe.

## New-agent startup
1. Fetch current `main`.
2. Read `docs/handoffs/START_HERE_NEXT_AGENT_FA.md`.
3. Read `docs/handoffs/MASTER_HANDOFF_FA.md`.
4. Read this file.
5. Read `docs/handoffs/PHASE6B_HANDOFF_FA.md`.
6. Read R2 Implementation Plan/API/Schema/Risk contracts.
7. Check latest GitHub Actions state.
8. Checkout existing `phase/6c-table-draft` at `756d805912351d6dd539f9922e9bd97144369b63`.
9. Read `docs/handoffs/PHASE6C_IN_PROGRESS_HANDOFF_FA.md`.
10. Resolve current Linux regression without recreating existing Table Draft work.
11. Update this file + final Phase 6C handoff + Master source map in the same Phase 6C PR.
