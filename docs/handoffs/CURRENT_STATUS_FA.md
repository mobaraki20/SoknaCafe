# SOKNA — Current Project Status

Updated: 2026-09-20
Repository: `mobaraki20/SoknaCafe`

## Completed release checkpoint
- Phase 0–7 and Phase 8A completed: `1.36.4-dev.38`.
- Product merge `485db60b71b40c475c931a9d6d056ce8a07d0df2`, post-merge CI `35442209529` SUCCESS.
- Machine signing identity remains distinct from portable app.key; private machine identity is never backed up.

## Active work — continue the existing branch
- Phase 8B Windows New / Recover Setup Orchestration, IN PROGRESS.
- Branch: `phase/8b-windows-setup`.
- PR: https://github.com/mobaraki20/SoknaCafe/pull/18 — draft, not merged.
- Latest phase document: `docs/handoffs/PHASE8B_IN_PROGRESS_HANDOFF_FA.md` on that branch.
- Shared setup owner, empty-target recovery, prebuilt C# SCM host, preflight, private setup inputs, diagnostics, service repair/rollback and TLS preservation are implemented on the branch.
- Windows runtime test covers native argument round trips, Persian paths, real SCM repair and injected failure rollback, process-tree cleanup, ACLs, secret redaction and TLS preservation.
- Final CI for the current head must be checked on PR #18; earlier Linux/MariaDB PASS alone is not a complete gate.

## Remaining acceptance
MSI/Burn packaging and updater file ownership; Desktop/Start shortcuts; Installed apps uninstall; complete prerequisite manifest/download/offline behavior; end-to-end HTTP/DB health; cashier/printer UAT. Runtime binary artifact is NOT Setup.exe.

Contract: `docs/architecture-migration-r2/WINDOWS_INSTALLER_ACCEPTANCE_FA.md`.
Phase 8C takeover/replacement drill follows the Phase 8B gate. Do not recreate 8A or fork another setup/backup/updater owner.

## Handoff rule
Each step must record branch/head, actual CI run/SHA and results, remaining issues and exact next action in GitHub. COMPLETE requires final-head and post-merge CI; hosted tests do not imply real-device UAT.
