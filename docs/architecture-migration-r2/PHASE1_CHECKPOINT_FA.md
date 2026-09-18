# Phase 1 Checkpoint — 1.36.4-dev.27

Status: **Foundation code complete; environment-specific service/DB UAT remains explicitly blocked.**

## Implemented
- Baseline 0A fixes and green static/browser regression evidence.
- Local Runtime supervisor with single-instance lock, atomic state, worker health and maintenance pause.
- Existing worker Business owners preserved; Runtime only orchestrates `--once` entrypoints.
- Correlation ID response header and JSONL logs with secret redaction.
- Windows target data root `%ProgramData%\SOKNA`, dev fallback `storage/`.
- `sokna.local` TLS provisioning script + Apache template.
- Runtime self-check/health commands.

## Automated evidence
- Phase 1 structural contract: PASS.
- Phase 1 runtime unit/integration (filesystem + proc supervisor): PASS.
- PHP lint/JS syntax/unit and non-browser regression contracts: PASS through the project gate; slow gate was split only because the execution tool has per-call timeout.
- Browser regressions rechecked after foundation changes: Modules, UI Conformance, Guest, Quick Order, Itemized Settlement and Updater: PASS.

## Not falsely marked PASS
- Real MariaDB fresh/update/restore: BLOCKED_ENVIRONMENT (pdo_mysql/mysqli unavailable).
- Windows Service Control Manager hosting: UAT_REQUIRED on Windows packaging environment.
- Local certificate provisioning against actual Apache/Windows trust store: UAT_REQUIRED.
- Physical printer/Print Agent: UAT_REQUIRED and intentionally not internalized until Phase 7.

## Rollback
No DB migration exists. Rollback is file-level to accepted dev.26 plus its service-worker cache identity. Runtime data/log files are additive and can be ignored/removed after service stop.
