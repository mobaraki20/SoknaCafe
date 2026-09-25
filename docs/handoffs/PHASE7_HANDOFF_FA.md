> **Historical printing handoff.** Deployment/ownership statements below that describe a separately installed Print Agent were superseded on 2026-09-24 by `docs/architecture-migration-r2/PHASE7R_PRINTING_RECONCILIATION_FA.md`. Current authority: Print Worker is an internal SOKNA Local component; preserve protocol/state-machine semantics but do not revive a separate installer/product.

# Phase 7 Handoff — Printing / Notifications / Integrations

Status: COMPLETE
Release: 1.36.4-dev.37

## Phase 7A — Runtime-owned Printing / Notifications
- PR #12
- merge commit: 215949cd3507bcf2d20860bd6a8c1c6ef67c51b3
- post-merge CI: 35439861511 — SUCCESS
- Runtime supervises the installed stable Windows Print Agent service through tools/print-runtime-worker.php.
- Print API v4, Agent SQLite, renderer, submission fence and Winspool state machine remain owned by the mature Agent.
- Push queue processing is explicitly Runtime-owned while push_event_queue remains canonical.

## Phase 7B — Accommodation transport adapter
- PR #13
- merge commit: c83df6bdfd240a29a8c8bcf6653f2b69886d2954
- post-merge CI: 35440573461 — SUCCESS
- HTTPS mechanics live in includes/accommodation_transport.php.
- business classification, settlement, ambiguity and local recovery remain in includes/accommodation.php.
- no schema change and no Public business authority.

## Phase 7C — Center outbound adaptation
- PR #14
- merge commit: b29cf18aea52228fc44e08aac5e2a7c521295f98
- post-merge CI: 35441174227 — SUCCESS
- Runtime owns capability-gated Cafe→Center user projection through tools/center-projection-worker.php.
- strict Probe must advertise capabilities.user_projection_v1=true before outbound projection activates.
- projection carries only local_user_id, display_name, role, active, updated_at.
- no username/password/session/CSRF/API credential or HR data is projected.
- legacy Center→Cafe user directory remains compatibility fallback until Center-side migration is confirmed.
- Public is not a personnel database.

## Final Phase 7 validation
All three required jobs PASS after the final product merge:
- Windows runtime/TLS.
- Public + Local MariaDB integration.
- Linux full regression + browser gate.

## UAT still not proven by hosted CI
- real Windows Print Agent service lifecycle on cafe cashier PC.
- physical printer/Winspool/paper-out/offline/restart/50-print/soak scenarios.
- real Center deployment advertising user_projection_v1 and accepting /api/s2s/cafe_users_sync.php.
- real Accommodation peer/network behavior.

## Exact next phase
Phase 8 — Setup / Recovery / Backup / Takeover.

Start from current main. Do not reopen Phase 7 branches.
Phase 8 frozen work:
- Windows install new/recover flows.
- optional Public pairing/printer/offsite/push setup.
- enriched Recovery Set/PITR.
- machine replacement + fresh identity + Public takeover.
- checkpoint requires restore + takeover drill.
