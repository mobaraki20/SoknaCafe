> **مرجع فعلی تطبیق — 2026-09-25:** شاخه محلی `work/reconcile-dev39`، نسخه سورس dev.39، مبتنی بر تاریخچه گیت‌هاب و پیشرفت‌های بسته. ابتدا `docs/handoffs/DEV39_RECONCILIATION_2026-09-25_FA.md` را بخوانید (مسیر نسبت به ریشه مخزن). ادامه‌ها و دستورهای متعارض زیر سوابق تاریخی‌اند؛ Phase8B کامل نشده، WiX مجوز اجرا نگرفته و Windows CI جدید انجام نشده است.

# Phase 8B In-Progress Handoff — Windows New / Recover Setup

## Reconciliation addendum — 2026-09-24
Printing ownership in the older text below has been superseded by R2 reconciliation: **Print Worker is an internal SOKNA Local component, not an optional separately installed Pagent product.** See `docs/architecture-migration-r2/PHASE7R_PRINTING_RECONCILIATION_FA.md`. Protocol/state-machine behavior is preserved; packaging/provisioning ownership moves into SOKNA Setup/Repair/Recovery.

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
- Optional Public pairing / **internal Print Worker provisioning** / off-server backup / Push setup are orchestration concerns, not new business state machines.

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
- Print Worker is bundled internally with SOKNA Local; Setup/Repair/Recover owns its service lifecycle while preserving the audited 6.2.5 queue/reconciliation/Winspool semantics.

## Current audit result
Existing owners to compose:
- `install.php`
- `runtime/sokna-runtime.php`
- `runtime/windows/provision-local-https.ps1`
- `includes/maintenance.php`
- `includes/updater_engine/1.5.3/`
- internal Print Worker source/provenance under `runtime/print-worker/source/` plus the Phase7R reconciliation contract.

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
- Open risks: updater/MSI file ownership, target-side compilation, secret-file ACL before copy, private internal Print Worker provisioning, service repair failure recovery and diagnostics. See the acceptance contract for evidence requirements.
- This update changes documentation only. The CI result above belongs to the reviewed code SHA, not this new documentation commit.
- Every further step must persist branch/SHA, actual test results, open work and exact next action in GitHub handoffs.

## Hardening batch — implemented; latest verification below
Scope: Windows orchestration reliability; no POS UI/business behavior change.
- Shared setup preflight checks empty DB/input without creating config/schema/identity.
- Windows checks dependencies and prebuilt service host before application mutation; CI now builds the host and publishes a binary artifact.
- Private ACL before credential file writes; redacted per-session JSON summary/events and allowlisted support ZIP.
- Repair preserves existing service configuration; failure restores previous service binary and running state. No delete/recreate of an existing service.
- Valid TLS keys/certs are reused byte-for-byte; partial/mismatched/expired identity fails closed. Conflicting hosts entries are not erased.
- Internal Print Worker build output requires a trusted component manifest/provenance; credentials travel only through private ACL-protected provision files, never CLI secrets.
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

Implementation source: `67b32f767bd254bd26bb845302c8490223698e71`; PR CI `35491459618` is in progress at this checkpoint. Inspect its actual result before continuing. No PASS is assigned yet.

CI `35491459618`: Linux and MariaDB PASS; Inno compiler/build PASS; installer lifecycle FAIL with GUI exit 2 and no stdout. Next diagnostic revision adds explicit per-attempt Inno logs to the fixture; no success claim and no speculative product fix.

Native diagnostic result from CI `35503912231` / source `c848490165c2d54eab69f861993e1f386a94faeb`: preflight and Inno file/shortcut/registry creation worked; post-install bridge read the wrong registry view. Inno 7 defaults to an x86 setup process even in 64-bit install mode, and Exec launched x86 PowerShell. Fix: explicit `SetupArchitecture=x64`, plus bridge rejects a non-64-bit process. Do not add a second registration key or disable registry checks. Lifecycle must pass on the new head before acceptance.

CI `35504078070` confirmed the x64 fix: native installation, TLS provisioning and Runtime start succeeded. The fixture then rejected ARP DisplayName because Inno defaults to AppVerName (name plus version). Set UninstallDisplayName explicitly and independently check DisplayVersion/Publisher. Repair/uninstall assertions had not yet executed in that run.

## Verified native platform preview — 2026-09-20
- Tested source: `a3435d187717ffdc1d2fc2914ab81a341e7742b3` on `phase/8b-windows-setup`.
- CI: https://github.com/mobaraki20/SoknaCafe/actions/runs/35504331920 — all three jobs completed SUCCESS: Windows, Linux/browser regression and Public/Local MariaDB.
- Actual Windows lifecycle PASS: missing prerequisite blocks before extraction/registration; native x64 install starts Runtime; Installed apps name/version/publisher and cached Modify/Repair; Desktop/Start shortcuts; deleted platform module and shortcut restored by cached Repair; simulated newer active app/config/TLS preserved; foreign service command blocks uninstall; normal uninstall removes owned service/registration/shortcut/platform scripts and preserves app/business sentinel/TLS key.
- Artifact: `sokna-platform-preview-unsigned`, ID `10603552045`, available from the CI run while retained. Contains Setup.exe, compiler license and source/hash manifest. It is unsigned, uses no paid certificate, and is only for an already configured app with PHP/OpenSSL/web stack present.
- Fixture tests do not prove real updater execution, HTTP/database readiness through this installer, clean-machine installation, Persian-path full lifecycle, printer acceptance or human UAT.
- This verifies the exact source above; subsequent documentation commits are not assigned that source's CI result. PR #18 remains draft/unmerged; full Phase 8B is IN PROGRESS.

### Exact next action after this checkpoint
Continue the existing branch. Complete consolidated installer/owner diagnostics and their failure-path tests, then clean-machine prerequisites/web stack/database acquisition and New/Recover orchestration using the existing owners. Full application Repair must use a complete same-version payload, never an older seed. Complete Persian installer copy and the acceptance table before promotion; require final-head and post-merge CI. Preserve the no-mandatory-cost decision. Do not restart WiX/MSI/Burn research or ask the settled budget question again.

## Installer diagnostic consolidation — implementation awaiting CI
The canonical setup owner now accepts the native installer log path, reads a bounded snapshot while the log is open, redacts it and includes it alongside summary.json/events.jsonl in the existing private support.zip. Inno passes its documented {log} constant through the bridge. Summary reports bundle path/status, log inclusion status and snapshot scope. Missing/oversized/unreadable log must not replace the original setup result. This is a snapshot through owner completion, not the final Inno log; early wizard/bridge failures and post-owner file-removal failures still need coverage before claiming complete diagnostic consolidation. Windows tests exercise held-open log, credential canary, original failure preservation and real native preflight/install/Repair bundles. New code is NOT verified until its own CI passes.

## Verified installer diagnostic snapshot — 2026-09-20
Source `0557e64336d1ac1be958787ebe76f895df4647a2`; CI https://github.com/mobaraki20/SoknaCafe/actions/runs/35504980480 — Windows, Linux/browser and MariaDB all completed SUCCESS.

The existing private support.zip now includes a redacted native installer log snapshot, summary.json and events.jsonl. Tests passed for a held-open log, credential canary redaction, missing-log preservation of the original setup failure, and actual native preflight/install/Repair ZIP contents. Summary exposes bundle path/status and snapshot scope. The snapshot ends at setup-owner completion; early wizard/bridge failures and later Inno finalization/removal failures remain open. Do not call this full installer diagnostic coverage.

Delivery/source map: [DELIVERY_STATUS_FA.md](DELIVERY_STATUS_FA.md). Print Agent's independent repository was located at https://github.com/mobaraki20/Pagent ; published release v6.2.5 provides Setup.exe and source.zip. Release metadata reports target `11708956df922e02ad8067ad281950dae33bab64` and Setup SHA256 `d34241a4b3ed8b3d1cf5106eedee39d97d905766b067013b9402f3f18e8f0929`. These are observed release metadata, not a new binary hash verification or local-installer compatibility test. Do not recreate the Agent or assume old AGENT_DEPENDENCIES notes describe the latest release.

Next: finish diagnostics outside the owner window, then complete the clean-machine prerequisite/web stack/database and New/Recover package, Public deployment bundle, exact Agent integration and same-version application repair. Full Phase 8B remains IN PROGRESS; PR #18 is draft/unmerged. User is not responsible for manually assembling missing packages. Full initial-handoff traceability and real-device UAT remain open.


## Local checkpoint addendum — 2026-09-24 (support + package acceptance hardening)
- Structured setup support snapshot now records allowlisted service/version/health state; optional Burn/MSI logs are copied only after redaction.
- `installer/windows/scripts/collect-support.ps1` is the one-step read-only support collector and is included in the immutable installer shell; Persian Start Menu support action is authored.
- WiX authoring now uses a stable `SOKNA-Cafe-Setup` log prefix and an explicit MSI package log variable.
- A real Windows package acceptance script now covers MSI install, ARP, shortcuts, deliberate resource loss + `/fa` Repair, updater-owned Live/Data preservation, MSI uninstall, Burn install/uninstall.
- `windows-installer-package` workflow must run that acceptance before uploading the package artifact.
- Source contracts pass locally; actual Windows MSI/Burn run has NOT been executed in this Linux workspace and must remain CI pending.
- Exact gate mapping and remaining work: `docs/architecture-migration-r2/PHASE8B_WINDOWS_ACCEPTANCE_STATUS_2026-09-24_FA.md`.

## Local checkpoint addendum — Setup UI + bounded Apache integration — 2026-09-24
- `SoknaSetupUi.exe` اکنون UI رسمی فارسی/RTL برای New/Recover/Repair است؛ خودش mutation مالکانه انجام نمی‌دهد و فقط config/plan امن می‌سازد.
- UI با `asInvoker` اجرا می‌شود؛ تنها `SoknaSetupHost.exe` با UAC/elevation وارد orchestration canonical می‌شود.
- secretهای DB/admin/recovery فقط پس از ایجاد پوشه ACL-private نوشته می‌شوند و در `finally` پاک می‌شوند؛ CLI secret جدیدی اضافه نشده است.
- UI نهایی گزینه خاموش‌کردن HTTPS یا Apache preflight را expose نمی‌کند؛ `SkipHttps` فقط contract داخلی/test باقی می‌ماند.
- `runtime/windows/configure-apache.ps1` configuration owner محدود SOKNA است: Apache root/config را از `httpd -V` کشف می‌کند، module/root/syntax را قبل از mutation می‌سنجد، managed include اتمیک/قابل rollback دارد و lifecycle را اجرا نمی‌کند.
- CI source contract برای Setup UI و Apache integration اضافه شده است؛ Windows runtime/build/MSI run در این Linux workspace اجرا نشده و همچنان CI/UAT pending است.
- package acceptance حالا وجود و target میانبر «راه‌اندازی و تعمیر سکنا» و restore آن با MSI Repair را نیز الزام می‌کند.
- مرجع وضعیت دقیق WIN-01..WIN-12: `docs/architecture-migration-r2/PHASE8B_WINDOWS_ACCEPTANCE_STATUS_2026-09-24_FA.md`.

## Local checkpoint addendum — 2026-09-24 (Persian Setup UI + bounded Apache integration)
- `runtime/windows/configure-apache.ps1` owner محدود پیکربندی Apache است: config واقعی را از `httpd -V` کشف می‌کند، moduleها را verify می‌کند، managed include اختصاصی SOKNA می‌نویسد، candidate/real syntax check دارد و در failure rollback byte-preserving انجام می‌دهد. SOKNA هیچ lifecycle action روی Apache اجرا نمی‌کند؛ فقط `reload_required` گزارش می‌کند.
- Apache apply اکنون idempotent change detection دارد. اگر bytes تغییر کند Setup با exit `20` incomplete می‌ماند تا مالک خارجی Apache را reload کند؛ Repair بعدی فقط وقتی Success می‌شود که HTTPS `/login.php` marker واقعی SOKNA را برگرداند. health failure exit `21` است.
- `WebRoot = AppRoot` برای ساختار فعلی برنامه است؛ `AppRoot` قابل انتخاب و `DataRoot` باید non-overlapping باشد. مقدار `C:\SOKNA\Cafe` فقط default UI است و contract مسیر نصب نیست.
- Setup UI رسمی تحت `installer/windows/setup-ui/` فارسی/RTL و non-elevated است. فقط New/Recover/Repair را ارائه می‌دهد، ورودی‌های secret را در ACL-private temp می‌نویسد و `SoknaSetupHost.exe` را با `runas` اجرا می‌کند.
- UI عملیاتی اجازه خاموش‌کردن HTTPS یا Apache را نمی‌دهد؛ bypassهای فنی فقط برای test/CI در ownerهای پایین‌تر باقی می‌مانند.
- MSI/Burn Setup UI را در shell نصب می‌کنند، میانبر فارسی می‌سازند و Burn success page به همان executable canonical Launch می‌دهد.
- Static/source contracts و Unit محلی PASS هستند؛ build/launch MSI/Burn و Apache runtime روی Windows همان head همچنان CI pending است و نباید PASS عملیاتی ادعا شود.
- مرجع جزئی: `docs/architecture-migration-r2/PHASE8B_SETUP_UI_CHECKPOINT_FA.md`.
