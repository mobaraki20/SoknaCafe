# SOKNA 1.36.4-dev.35 — Phase 7A

## Runtime-owned Printing / Notifications
- Local Runtime supervises the installed stable Windows Print Agent service.
- Supervisor may recover a stopped installed Agent but never duplicates Print API v4, renderer, spooler, SQLite or print state-machine behavior.
- Missing Agent remains an installation/readiness state.
- Stable Agent distribution remains the official `mobaraki20/Pagent` Setup release.
- Notification queue processing is explicitly Runtime-owned; transactional Push outbox remains canonical and request-time drains remain accelerators only.

## Validation
See:
- `docs/architecture-migration-r2/PHASE7_DESIGN_NOTES_FA.md`
- `docs/architecture-migration-r2/PHASE7A_CHECKPOINT_FA.md`
