> **Historical printing handoff.** Deployment/ownership statements below that describe a separately installed Print Agent were superseded on 2026-09-24 by `docs/architecture-migration-r2/PHASE7R_PRINTING_RECONCILIATION_FA.md`. Current authority: Print Worker is an internal SOKNA Local component; preserve protocol/state-machine semantics but do not revive a separate installer/product.

# Phase 7A Handoff — Runtime-owned Printing / Notifications

Status: COMPLETE
Release: `1.36.4-dev.35`
PR: #12
Merge commit: `215949cd3507bcf2d20860bd6a8c1c6ef67c51b3`
Post-merge CI: `35439861511` — SUCCESS

## Printing
SOKNA Runtime supervises the installed stable `Sokna Print Agent 6` Windows Service through `tools/print-runtime-worker.php`. It can start a stopped installed service but does not duplicate Print API v4, Agent SQLite, renderer, submission fence or Winspool logic. Missing Agent is an installation/readiness state.

## Notifications
`tools/push-worker.php` is explicitly Runtime-owned. The transactional Push outbox remains canonical; after-response/opportunistic drains are accelerators/fallback only.

## Next
Phase 7B — isolate Accommodation HTTP transport while preserving all Local finance/recovery semantics.
