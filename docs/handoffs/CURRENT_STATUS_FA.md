# SOKNA — Current Project Status

Updated: 2026-09-19
Repository: mobaraki20/SoknaCafe
Current completed release checkpoint: 1.36.4-dev.37
Active validation target: 1.36.4-dev.38 / Phase 8A

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
- Phase 7A — Runtime-owned Printing / Notifications. PR #12.
- Phase 7B — Accommodation Transport Adapter. PR #13.
- Phase 7C — Center Outbound Adaptation. PR #14.

## Latest verified product checkpoint
Phase 7 final product merge:
b29cf18aea52228fc44e08aac5e2a7c521295f98

Post-merge GitHub Actions:
35441174227 — SUCCESS

All three required jobs PASS:
- Windows runtime/TLS.
- Public + Local MariaDB integration.
- Linux full regression + browser gates.

Latest handoff:
docs/handoffs/PHASE7_HANDOFF_FA.md

## Frozen behavior now
- Runtime supervises the installed stable Print Agent service without duplicating its print state machine.
- Push processing is Runtime-owned; transactional Push outbox remains canonical.
- Accommodation HTTP transport is isolated from Local settlement/recovery semantics.
- Center outbound Cafe user projection is capability-gated and content-versioned.
- legacy Center inbound directory remains compatibility fallback.
- Cafe remains user authority; Center remains HR/payroll authority; Public is not personnel authority.

## Active work
Phase 8A — Installation Identity + Recovery Set Metadata.

Branch: phase/8a-recovery-identity

Current scope:
- create a separate non-clonable installation identity under private data root.
- enrich mature Backup v3 manifest with safe recovery metadata only.
- preserve app.key portability for encrypted integration secrets while never archiving the new installation private key.

Design: docs/architecture-migration-r2/PHASE8_DESIGN_NOTES_FA.md
Checkpoint: docs/architecture-migration-r2/PHASE8A_CHECKPOINT_FA.md

After 8A post-merge PASS, continue Phase 8B Windows new/recover setup orchestration.

## Phase 8 frozen scope

Frozen Phase 8 scope:
- Windows install new/recover flows.
- optional Public pairing/printer/offsite/push setup.
- enriched Recovery Set/PITR.
- machine replacement + fresh identity + Public takeover.
- checkpoint: restore + takeover drill.

## New-agent startup
1. Fetch current main.
2. Read NEXT_AGENT_START_HERE.md.
3. Read docs/handoffs/MASTER_HANDOFF_FA.md.
4. Read this file.
5. Read docs/handoffs/PHASE7_HANDOFF_FA.md.
6. Read R2 Implementation Plan / Schema / Risk sections for Phase 8.
7. Check latest GitHub Actions.
8. Create a dedicated Phase 8 branch from current main.
9. Audit existing updater/backup/recovery/install owners before changing code; preserve mature engines and extend them.
