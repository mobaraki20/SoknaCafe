# SOKNA — Current Project Status

Updated: 2026-09-20
Repository: `mobaraki20/SoknaCafe`

## Latest continuation: native platform preview
Source `67b32f767bd254bd26bb845302c8490223698e71` on the existing 8B branch implements the Inno platform preview and its lifecycle tests. PR CI `35491459618` is IN PROGRESS at this checkpoint; inspect current checks. No new PASS is claimed.

Scope: existing configured app only; platform files, Desktop/Start shortcuts, ARP, cached Repair and owned-service uninstall/data preservation. This is not clean-machine New/Recover or the finished installer. Read the latest phase handoff for remaining acceptance.

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
- Verified source `2fdb500d3240a1c2adde30b291fe4377d78fca44`: CI `35477813459` SUCCESS on all three gates. Any later code head needs its own validation.

## Remaining acceptance
MSI/Burn packaging and updater file ownership; Desktop/Start shortcuts; Installed apps uninstall; complete prerequisite manifest/download/offline behavior; end-to-end HTTP/DB health; cashier/printer UAT. Runtime binary artifact is NOT Setup.exe.

Contract: `docs/architecture-migration-r2/WINDOWS_INSTALLER_ACCEPTANCE_FA.md`.
Phase 8C takeover/replacement drill follows the Phase 8B gate. Do not recreate 8A or fork another setup/backup/updater owner.

## Handoff rule
Each step must record branch/head, actual CI run/SHA and results, remaining issues and exact next action in GitHub. COMPLETE requires final-head and post-merge CI; hosted tests do not imply real-device UAT.

## Verified hardening checkpoint — 2026-09-20
- Tested source: `2fdb500d3240a1c2adde30b291fe4377d78fca44` on `phase/8b-windows-setup`.
- CI: https://github.com/mobaraki20/SoknaCafe/actions/runs/35477813459 — completed / SUCCESS.
- All three gates passed: Windows runtime/TLS + SCM repair/failure-injection tests; Public/Local MariaDB including recovery and read-only preflight; full Linux/browser regression.
- PR: https://github.com/mobaraki20/SoknaCafe/pull/18 — draft, not merged. No post-merge validation or complete installer release is claimed.
- Source batch is verified. Full Phase 8B remains IN PROGRESS because native installer packaging and acceptance are still open.
- This documentation records evidence for the tested source SHA above; it does not assign those results to a later documentation commit.

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
