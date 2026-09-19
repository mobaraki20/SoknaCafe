# Phase 8B In-Progress Handoff — Windows New / Recover Setup

Status: IN PROGRESS
Base main: `98607d87d50c7913a1143d621e60f807965bae53`
Branch: `phase/8b-windows-setup`

## Frozen decisions
- Do not rewrite mature backup/restore/updater/print state machines.
- Fresh web install and Windows setup will share one setup owner.
- Machine Recover is allowed only on an uninstalled app + verified empty DB.
- Recover requires exact application version and validated Backup v3 / secure SKB.
- A fresh installation identity must exist before restoring business state.
- The old machine installation private key is never restored.
- Portable `app.key` remains restorable because it decrypts existing integration secrets.
- Windows SCM must never point directly at `php.exe`.
- Runtime service will use a real Windows Service host that supervises only `runtime/sokna-runtime.php`.
- DB password and recovery passphrase must come from protected files, not CLI secret arguments.
- Optional Public pairing / Print Agent / off-server backup / Push setup are orchestration concerns, not new state machines.

## Planned durable commits
1. shared setup owner + web installer delegation.
2. empty-target machine recovery + secure setup CLI.
3. Windows Service host + PowerShell orchestrator + hosted contracts.
4. CI/version/docs finalization.

## Audit refinement
- `runtime/sokna-runtime.php` is a correct long-running supervisor entrypoint, but Windows SCM host/packaging is intentionally absent.
- `runtime/windows/provision-local-https.ps1` already owns local CA/certificate/hosts provisioning.
- direct `sc.exe create ... php.exe` remains forbidden.
- Phase 8B will use a real Windows service wrapper contract (WinSW supplied/pinned by packaging; not downloaded ad-hoc by runtime).
- Recover will use a protected plan file; DB password and recovery passphrase are file-backed secrets and never command-line values.
- `includes/maintenance.php` remains restore owner; 8B only adds an explicit fresh-target compatibility path so an empty replacement DB does not pretend to have the old migration fingerprint.
- Print Agent optional installation delegates to the official Pagent installer package and never recreates its service/rollback logic.

## Current audit result
Existing owners to compose:
- `install.php`
- `runtime/sokna-runtime.php`
- `runtime/windows/provision-local-https.ps1`
- `includes/maintenance.php`
- `includes/updater_engine/1.5.3/`
- stable Print Agent Setup contract from `mobaraki20/Pagent`.

If interrupted, continue from the newest commit on this branch and this document; do not restart Phase 8A.
