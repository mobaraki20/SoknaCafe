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

## Hardening batch — implemented; latest verification below
Scope: Windows orchestration reliability; no POS UI/business behavior change.
- Shared setup preflight checks empty DB/input without creating config/schema/identity.
- Windows checks dependencies and prebuilt service host before application mutation; CI now builds the host and publishes a binary artifact.
- Private ACL before credential file writes; redacted per-session JSON summary/events and allowlisted support ZIP.
- Repair preserves existing service configuration; failure restores previous service binary and running state. No delete/recreate of an existing service.
- Valid TLS keys/certs are reused byte-for-byte; partial/mismatched/expired identity fails closed. Conflicting hosts entries are not erased.
- Agent payload requires SHA256 from a trusted manifest.
- Tests added: real Windows SCM install/repair plus injected start failure rollback; native quoting, private ACL, preflight failure, diagnostics allowlist, TLS repair/partial failure; MariaDB read-only preflight.
- Local checks: Phase 1 + Phase 8B Python contracts PASS, git diff --check PASS. PHP/Windows runtime unavailable in this Linux workspace; hosted CI evidence is recorded in the latest verification section below.
- Next: inspect CI for this batch, fix any failures, then complete packaging acceptance (MSI/Burn/updater ownership/shortcuts/ARP/full prerequisites/end-to-end health). Phase 8B remains IN PROGRESS.

## 2026-09-20 — PR #18 hardening continuation
- PR: https://github.com/mobaraki20/SoknaCafe/pull/18 (draft; not merged).
- Prior source `8e5ba018` passed Linux and MariaDB, but Windows runtime tests exposed test-environment issues. Do not mistake that run for full PASS.
- Windows PowerShell array serialization was removed from argument assertions; arguments are checked individually, including Persian and trailing backslashes.
- SCM fixture PIDs are recorded directly because CIM CommandLine can be null for SYSTEM processes. The test checks old and new generations and requires exactly one Runtime plus one fixture child.
- Service lifecycle now serializes monitor/stop and terminates the Runtime process tree during repair; service state/config and binary rollback are tested with an intentionally failing replacement host.
- Actual OpenSSL Unicode-path failure was fixed by using ASCII relative file arguments with a Unicode native working directory. Do not remove the Persian-path TLS test.
- Setup summary includes OS, service status and recent service-host events; malformed JSON errors never echo the unparsed credential file. Support export remains an explicit allowlist.
- Latest code before this checkpoint: `6701949524f44420c061f62e226381a0c7d9480e`; additional malformed-JSON fix and its test are included in this checkpoint. Read PR checks for this commit's final CI; do not inherit PASS from earlier heads.
- Build artifact `sokna-runtime-service-host` is ONLY the Runtime service binary, not the final Setup.exe.
- Remaining phase work: MSI/Burn packaging, updater/repair ownership, Desktop/Start shortcuts, Installed apps uninstall, full prerequisite manifest/acquisition, HTTP+DB acceptance and real-device UAT. Phase 8B is still IN PROGRESS.

## Verified hardening checkpoint — 2026-09-20
- Tested source: `2fdb500d3240a1c2adde30b291fe4377d78fca44` on `phase/8b-windows-setup`.
- CI: https://github.com/mobaraki20/SoknaCafe/actions/runs/35477813459 — completed / SUCCESS.
- All three gates passed: Windows runtime/TLS + SCM repair/failure-injection tests; Public/Local MariaDB including recovery and read-only preflight; full Linux/browser regression.
- PR: https://github.com/mobaraki20/SoknaCafe/pull/18 — draft, not merged. No post-merge validation or complete installer release is claimed.
- Source batch is verified. Full Phase 8B remains IN PROGRESS because native installer packaging and acceptance are still open.
- This documentation records evidence for the tested source SHA above; it does not assign those results to a later documentation commit.

### Exact next action
Fetch current main's handoff and the existing 8B branch. Preserve the verified setup/service/TLS owners. Resolve MSI versus application-updater file ownership before adding package authoring: Repair must never restore an older application payload over an updated release. Then build the MSI/Burn package, its shortcut/Installed-apps lifecycle and versioned prerequisite manifest, and exercise the acceptance table. Do not mistake the CI service-host artifact for Setup.exe. Keep PR #18 draft until its declared scope is ready; recheck current head and CI before any promotion.

## Packaging research checkpoint — 2026-09-20
- Design: `docs/architecture-migration-r2/WINDOWS_PACKAGING_OWNERSHIP_FA.md`.
- Proposed ownership: MSI owns platform tooling and an immutable seed cache; the existing updater owns the active application. Never extract an older seed over an installed application during Repair.
- This is a documentation/design checkpoint only; ownership enforcement, native authoring and application repair from a same-version full cache are NOT implemented.
- Audit found the runtime service is not an HTTP server. Apache currently has a template, not clean-machine deployment. Web server/PHP/DB payload versions and acquisition remain open.
- Current WiX v7 requires explicit EULA acceptance and can carry an OSMF fee. Owner must choose whether to proceed under these terms or require a tool without mandatory fees. No EULA acceptance, purchase or CI acceptance flag has been performed.
- Read the new design before choosing/pinning the toolchain. Do not silently use an obsolete WiX version to bypass this decision.
- Verified code remains `2fdb500d3240a1c2adde30b291fe4377d78fca44`, CI `35477813459` SUCCESS. New documentation is not a new code/test result. Phase 8B and draft PR #18 remain IN PROGRESS.
- Next: obtain owner's toolchain/licensing preference; then implement the documented ownership boundary, native package, prerequisite manifest/acquisition and end-to-end acceptance. Preserve all existing setup/backup/updater owners.

## Owner decision — no mandatory cost / test use — 2026-09-20
- Owner explicitly cannot pay; current use is for testing. No further licensing/budget question is pending.
- Select Inno Setup (Setup.exe), superseding the WiX MSI/Burn proposal. Official license permits use including commercial applications; official purchase FAQ states purchase is not strictly required.
- Sources: https://jrsoftware.org/files/is/license.txt and https://jrsoftware.org/isorder.php (reviewed 2026-09-20).
- Preserve all installer acceptance requirements and updater/platform ownership separation. Inno needs explicit, tested Repair; it does not provide MSI repair semantics automatically.
- Next: pin compiler/version/hash/license and implement native authoring plus maintenance Repair using existing owners. No paid tools/subscriptions/certificates; test unsigned status must be explicit. Native installer is not yet built.
- Earlier sections asking for a licensing preference are historical and superseded by this decision. Verified runtime source/CI remain unchanged.

## Inno platform lifecycle implementation — awaiting CI
- Source files: `runtime/windows/installer/` and `tests/phase8b-installer-lifecycle.ps1`.
- Compiler pinned to Inno 7.1.0 x64, official release SHA256 plus Authenticode publisher validation before execution. Existing PNG brand assets are embedded unchanged in an ICO container.
- Preview scope is explicit: an already configured SOKNA app and installed PHP/OpenSSL/web stack. It is NOT clean-machine New/Recover packaging.
- Platform-only file list, Desktop/Start URL shortcuts, ARP registration, cached Setup via Modify/Repair, automatic installer logs; existing Repair owner is invoked with full checks.
- Shared setup owner adds ValidateRepair (read-only installed-target check) and RemovePlatform (exact service command ownership, remove service only, preserve app/data/TLS/Agent). No second service owner added.
- Hosted lifecycle fixture tests missing prerequisite/no platform mutation, registration/shortcuts, deleted platform file and shortcut repair, newer active app preservation, wrong service ownership refusal and uninstall/data/key preservation.
- Artifact is unsigned and named `sokna-platform-preview-unsigned`, with source/hash/compiler manifest. Do not publish as final SOKNA Setup or claim UAT.
- Local environment lacks PowerShell/Windows/Inno; compile and runtime status await this commit's CI. Earlier hardening PASS does not validate this new code.
- Still open: clean-machine app payload/New/Recover UI, web stack/DB deployment, prerequisite acquisition, full same-version app repair, unified installer+owner support ZIP, Persian installer copy and complete acceptance/UAT.
