# SOKNA — Current Project Status

Updated: 2026-09-20
Repository: `mobaraki20/SoknaCafe`

## Verified installer diagnostic snapshot — 2026-09-20
Source `0557e64336d1ac1be958787ebe76f895df4647a2`; CI https://github.com/mobaraki20/SoknaCafe/actions/runs/35504980480 — Windows, Linux/browser and MariaDB all completed SUCCESS.

The existing private support.zip now includes a redacted native installer log snapshot, summary.json and events.jsonl. Tests passed for a held-open log, credential canary redaction, missing-log preservation of the original setup failure, and actual native preflight/install/Repair ZIP contents. Summary exposes bundle path/status and snapshot scope. The snapshot ends at setup-owner completion; early wizard/bridge failures and later Inno finalization/removal failures remain open. Do not call this full installer diagnostic coverage.

Delivery/source map: [DELIVERY_STATUS_FA.md](DELIVERY_STATUS_FA.md). Print Agent's independent repository was located at https://github.com/mobaraki20/Pagent ; published release v6.2.5 provides Setup.exe and source.zip. Release metadata reports target `11708956df922e02ad8067ad281950dae33bab64` and Setup SHA256 `d34241a4b3ed8b3d1cf5106eedee39d97d905766b067013b9402f3f18e8f0929`. These are observed release metadata, not a new binary hash verification or local-installer compatibility test. Do not recreate the Agent or assume old AGENT_DEPENDENCIES notes describe the latest release.

Next: finish diagnostics outside the owner window, then complete the clean-machine prerequisite/web stack/database and New/Recover package, Public deployment bundle, exact Agent integration and same-version application repair. Full Phase 8B remains IN PROGRESS; PR #18 is draft/unmerged. User is not responsible for manually assembling missing packages. Full initial-handoff traceability and real-device UAT remain open.

## Verified native platform preview — 2026-09-20
- Tested source: `a3435d187717ffdc1d2fc2914ab81a341e7742b3` on `phase/8b-windows-setup`.
- CI: https://github.com/mobaraki20/SoknaCafe/actions/runs/35504331920 — all three jobs completed SUCCESS: Windows, Linux/browser regression and Public/Local MariaDB.
- Actual Windows lifecycle PASS: missing prerequisite blocks before extraction/registration; native x64 install starts Runtime; Installed apps name/version/publisher and cached Modify/Repair; Desktop/Start shortcuts; deleted platform module and shortcut restored by cached Repair; simulated newer active app/config/TLS preserved; foreign service command blocks uninstall; normal uninstall removes owned service/registration/shortcut/platform scripts and preserves app/business sentinel/TLS key.
- Artifact: `sokna-platform-preview-unsigned`, ID `10603552045`, available from the CI run while retained. Contains Setup.exe, compiler license and source/hash manifest. It is unsigned, uses no paid certificate, and is only for an already configured app with PHP/OpenSSL/web stack present.
- Fixture tests do not prove real updater execution, HTTP/database readiness through this installer, clean-machine installation, Persian-path full lifecycle, printer acceptance or human UAT.
- This verifies the exact source above; subsequent documentation commits are not assigned that source's CI result. PR #18 remains draft/unmerged; full Phase 8B is IN PROGRESS.

### Exact next action after this checkpoint
Continue the existing branch. Complete consolidated installer/owner diagnostics and their failure-path tests, then clean-machine prerequisites/web stack/database acquisition and New/Recover orchestration using the existing owners. Full application Repair must use a complete same-version payload, never an older seed. Complete Persian installer copy and the acceptance table before promotion; require final-head and post-merge CI. Preserve the no-mandatory-cost decision. Do not restart WiX/MSI/Burn research or ask the settled budget question again.

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
Clean-machine Inno New/Recover package; prerequisite manifest/download/offline behavior and web stack/database deployment; complete same-version application repair; consolidated installer/owner support ZIP; Persian installer copy; end-to-end HTTP/DB health; cashier/printer UAT. Platform preview lifecycle is verified above, but it is not the complete installer.

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
