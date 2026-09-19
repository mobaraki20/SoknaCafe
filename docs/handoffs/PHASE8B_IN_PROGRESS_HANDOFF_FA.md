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
- `runtime/sokna-runtime.php` is a correct long-running supervisor entrypoint, but SCM host is now implemented by `runtime/windows/SoknaRuntimeService.cs`; final installer packaging is still pending.
- `runtime/windows/provision-local-https.ps1` already owns local CA/certificate/hosts provisioning.
- direct `sc.exe create ... php.exe` remains forbidden.
- Current Phase 8B uses the in-repository C# Windows Service host, not WinSW. Preserve this owner; build the release binary in CI rather than requiring compilation on the cashier PC.
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

## Verified transfer checkpoint — 2026-09-19
- Reviewed code head: `6a3e183ca0ea544046c09bf3b9c8de7ab038ca5b`.
- Actions run `35445104275`: SUCCESS; Windows runtime, Public+Local MariaDB and Linux regression all passed.
- No open PR observed at review time. This phase is NOT merged or COMPLETE.
- Main documentation checkpoint: `b71ebf186d1aad775b52bb344c312187f6fa71b0`.
- Before implementation, read the owner-requested installer acceptance contract on main:
  https://github.com/mobaraki20/SoknaCafe/blob/main/docs/architecture-migration-r2/WINDOWS_INSTALLER_ACCEPTANCE_FA.md
- Bring main's documentation changes into this branch before finalization; do not recreate existing 8B code.
- Proposed packaging: WiX MSI + Burn Setup.exe. Required: Desktop/Start icons, Installed apps uninstall, genuine non-destructive Repair, preflight/prerequisite handling and unified redacted diagnostics.
- Open risks: updater/MSI file ownership, target-side compilation, secret-file ACL before copy, authenticated Agent payload, service repair failure recovery and diagnostics. See the acceptance contract for evidence requirements.
- This update changes documentation only. The CI result above belongs to the reviewed code SHA, not this new documentation commit.
- Every further step must persist branch/SHA, actual test results, open work and exact next action in GitHub handoffs.
