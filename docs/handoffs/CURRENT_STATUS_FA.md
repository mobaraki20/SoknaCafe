> **مرجع فعلی تطبیق — 2026-09-25:** شاخه منتشرشده `work/reconcile-dev39` در PR #19، نسخه سورس dev.39، مبتنی بر تاریخچه گیت‌هاب و پیشرفت‌های بسته. ابتدا `docs/handoffs/DEV39_RECONCILIATION_2026-09-25_FA.md` را بخوانید (مسیر نسبت به ریشه مخزن). ادامه‌ها و دستورهای متعارض زیر سوابق تاریخی‌اند؛ Phase8B کامل نشده، WiX مجوز اجرا نگرفته و شواهد CI جدید و کارهای باز در مرجع فوق و جدول پذیرش 2026-09-25 ثبت شده‌اند.

# SOKNA — Current Project Status

Updated: 2026-09-20

## Current recovery authority — 2026-09-24
- Recovery baseline entering this batch: `a579fcf21878413998deb7158eb9a5a29fd8d32a`.
- Latest implementation checkpoint closed in this batch: `268957d42d281b0f677de5a32be6c41e36b108f5` (`phase8b: harden dev39 Windows RC closure`).
- Active continuation branch: `work/phase8b-closure-dev39`.
- Product engineering identity: `1.36.4-dev.39`; this is a unique installable checkpoint, **not** a Production release claim.
- Phase 8B closure source is hardened. Immediate external evidence sequence: Windows Provider Freeze → review/commit frozen prerequisite lock → Windows RC full-stack → MSI/Burn package acceptance/signing.
- End-user prerequisite silent download/install remains forbidden; shared dependency ownership remains external.
- Historical `7542107`, GitHub `main` / `phase/8b-windows-setup` SHAs and the older sections below are provenance only, not continuation authority.
- Sole UI Design Authority: `SCDS-CANONICAL-2026-R1`; legacy `docs/UI_DESIGN_SYSTEM_FA.md` is non-authoritative compatibility material.


## Historical local workspace note — SUPERSEDED by dev.39 authority above
> این بخش فقط provenance مسیر قبل از `a579fcf` است. **از این بخش برای ادامه توسعه، نسخه فعلی، وضعیت Tax/Print یا تعیین اقدام بعدی استفاده نکنید.**

This section records the earlier offline reconciliation run and no longer supersedes the dev.39 authority at the top of this file.
- Working snapshot: `phase/8b-windows-setup` / `1.36.4-dev.38`, tracked in local Git branch `work/r2-reconciliation-ui-foundation`.
- Valid local commits already closed: UI foundation/Persian language, Phase 6D Batch Purchase, Phase 6E Expenses.
- Phase 7 Printing is being reconciled to frozen R2: Print Worker is now an **internal SOKNA Local component** based on audited Pagent 6.2.5 source; standalone Setup/Control/download are not active product owners.
- Local PHP/static contracts are required before checkpoint commit; Windows .NET build/service rollback and physical printer evidence remain `WINDOWS_CI_REQUIRED / UAT_REQUIRED` until actually executed.
- Phase 6F Tax remains intentionally deferred until Phase7R is committed on the correct internal-print architecture.
- All previous SOKNA Design System versions are rejected. New UI work is Persian-first/RTL-first and uses dev.26 only as UI DNA/provenance, correcting its defects rather than copying them.

## انتقال و بازبینی 2026-09-19 — شاخه فعال را از نو نسازید
- main مشاهده‌شده: `98607d87d50c7913a1143d621e60f807965bae53` (checkpoint محصول همچنان Phase 8A / dev.38).
- شاخه فعال موجود: `phase/8b-windows-setup`.
- head بررسی‌شده: `6a3e183ca0ea544046c09bf3b9c8de7ab038ca5b`.
- CI `35445104275`: completed/success؛ هر سه job Windows runtime، Public+Local MariaDB و Linux regression موفق‌اند.
- هنگام بازبینی PR باز وجود نداشت؛ 8B merge نشده و COMPLETE نیست.
- کد نصب مشترک، machine recovery، service host واقعی C# و PowerShell orchestration موجود است. هنداور اولیه شاخه درباره WinSW قدیمی شده؛ کد C# منبع فعلی است.
- الزامات مالک، انتخاب فنی پیشنهادی، شکاف‌های واقعی و معیارهای پذیرش: `docs/architecture-migration-r2/WINDOWS_INSTALLER_ACCEPTANCE_FA.md`.
- اقدام بعدی: همان شاخه فعال را fetch و بررسی کن؛ قبل از ادامه تغییرات احتمالی جدید را بخوان. نصب‌کننده نهایی هنوز تأیید نشده است.
- این checkpoint فقط بررسی و مستندسازی است؛ هیچ تغییر runtime یا ارتقای نسخه‌ای انجام نشده و CI فوق متعلق به head کد 8B است، نه commit مستندات جدید.

### قرارداد تحویل هر مرحله
پیش از پایان هر گام، تغییرات را در GitHub ثبت کن؛ CURRENT_STATUS و هنداور فاز باید شامل branch/head، کار انجام‌شده، تست واقعی و run ID، موارد باز و اولین اقدام بعدی باشند. نقطه شروع root و MASTER باید به شاخه فعال اشاره کنند. شاخه‌ای با CI سبز اما بدون merge/post-merge CI را COMPLETE ننام. بسته ZIP تاریخی را بر GitHub فعلی مقدم ندان.

---

## Historical GitHub checkpoint — SUPERSEDED / provenance only
> اطلاعات زیر وضعیت مشاهده‌شده در 2026-09-19 است و continuation authority فعلی نیست.

Updated: 2026-09-19
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

First audit/compose:
- existing `install.php` fresh install contract.
- Runtime Windows service + `provision-local-https.ps1`.
- internal Print Worker component/build/provisioning contract (`runtime/print-worker/source/`, Phase7R).
- backup import/restore and updater recovery owners.
- optional Public pairing, off-server backup and Push setup.

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
