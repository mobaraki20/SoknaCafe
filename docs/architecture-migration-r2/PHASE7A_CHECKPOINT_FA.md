# Phase 7A Checkpoint — Runtime-owned Print/Notification Processing

Version target: `1.36.4-dev.35`
Status: VALIDATION

## Printing
- SOKNA Runtime registers a bounded `printing` supervisor worker.
- Supervisor only queries/starts the installed `Sokna Print Agent 6` Windows Service.
- Missing Agent is `not_installed` and does not break unrelated Runtime work.
- No Print API v4 state transition, SQL, renderer, SQLite, Winspool or submission-fence logic is duplicated.
- Stable Agent distribution remains external via `mobaraki20/Pagent`.

## Notifications
- Runtime `push` worker is the canonical processing owner.
- `push_event_queue` remains durable truth.
- request-time drains remain accelerator/fallback only.

## Tests
- `tests/phase7-runtime-services-contract.py`
- Windows CI executes `tools/print-runtime-worker.php --once`.
- existing notification hybrid/security regressions remain active.

## Next
After 7A merge + post-merge CI, continue Phase 7B Accommodation transport adaptation.
