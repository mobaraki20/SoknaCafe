# SOKNA — Current Project Status

Updated: 2026-09-19
Repository: `mobaraki20/SoknaCafe`
Current completed release checkpoint: `1.36.4-dev.36`
Active validation target: `1.36.4-dev.37` / Phase 7C

## Completed architecture migration
- Phase 0 — Source Audit.
- Phase 1 — Runtime Foundation.
- Phase 2 — Public Edge + Realtime Relay. PR #1.
- Phase 3 — Guest/Public Snapshot Runtime. PR #2.
- Phase 4 — Permission-aware Remote Read Models. PR #3.
- Phase 5 — Deferred-safe + Financial Reconciliation. PR #4.
- Phase 6A — Preparation Permission Split. PR #5.
- Phase 6B — Explicit Sellable Kind. PR #7.
- Phase 6C — Server-persistent Table Draft. PR #10.

## Latest verified product checkpoint
Phase 6C merged to `main`:
`ccf0656655702a0b175a7cb9d7521fcb808745b1`

Phase 6C final branch head:
`ea46ecf6209ff14329438684fb94720445ce2403`

Product post-merge GitHub Actions:
`35438494232` — SUCCESS

All three jobs PASS:
- Windows runtime/TLS.
- Public + Local MariaDB integration.
- Linux full regression + browser gates.

Read latest handoff:
`docs/handoffs/PHASE6C_HANDOFF_FA.md`

## Frozen behavior now
- exactly one active Table Draft per table.
- Table Draft is persisted on Local and shared between authorized staff.
- browser normal-draft state is server-authoritative; stale versions cannot overwrite newer drafts.
- Draft Save creates no canonical order, business number, preparation work, inventory/finance side effect or receipt.
- Draft lifecycle has explicit Finalize/Cancel and no auto-expiry.
- Finalize revalidates current Local state and delegates to `includes/staff_order_service.php`.
- remote Table Draft operations require reachable Local Realtime and are never Deferred-safe.
- Public does not become Table Draft business authority.

## Active work
Phase 7C — Center outbound adaptation.

Branch: `phase/7c-center-outbound`

Current scope:
- add capability-negotiated Runtime-owned Cafe→Center user projection.
- preserve the old Center→Cafe directory endpoint as compatibility fallback.
- keep Cafe as user authority and Center as HR authority; Public stays out of personnel data.

Checkpoint: docs/architecture-migration-r2/PHASE7C_CHECKPOINT_FA.md

Phase 7A complete: PR #12 / main CI 35439861511 SUCCESS.
Phase 7B complete: PR #13 / main CI 35440573461 SUCCESS.
After 7C post-merge PASS, finalize Phase 7 handoff.

## Phase 7 frozen direction

Frozen direction:
- internalize Print Worker under Runtime without replacing the mature printing state machine.
- move Notification processing under Runtime while preserving outbox/retry semantics.
- adapt Accommodation transport while preserving its business contract.
- refactor Center integration toward outbound Local-authoritative transport.

Do not start Phase 7 by rewriting printing. Audit canonical owners/state machine first and add preservation contracts before moving worker ownership.

## New-agent startup
1. Fetch current `main`.
2. Read `NEXT_AGENT_START_HERE.md`.
3. Read `docs/handoffs/MASTER_HANDOFF_FA.md`.
4. Read this file.
5. Read `docs/handoffs/PHASE6C_HANDOFF_FA.md`.
6. Read R2 Implementation Plan / Schema / Risk Phase 7 sections.
7. Check latest GitHub Actions.
8. Create a new Phase 7 branch from current `main`; do not reuse `phase/6c-table-draft`.
9. Audit printing/notification/accommodation/center owners before changing code.
