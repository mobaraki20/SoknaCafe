> **مرجع فعلی تطبیق — 2026-09-25:** شاخه محلی `work/reconcile-dev39`، نسخه سورس dev.39، مبتنی بر تاریخچه گیت‌هاب و پیشرفت‌های بسته. ابتدا `docs/handoffs/DEV39_RECONCILIATION_2026-09-25_FA.md` را بخوانید (مسیر نسبت به ریشه مخزن). ادامه‌ها و دستورهای متعارض زیر سوابق تاریخی‌اند؛ Phase8B کامل نشده، WiX مجوز اجرا نگرفته و Windows CI جدید انجام نشده است.

# SOKNA — NEXT AGENT START HERE

Completed product checkpoint: Phase 8A / 1.36.4-dev.38.
Active work: Phase 8B on `phase/8b-windows-setup`, draft PR #18.

> **CURRENT CONTINUATION AUTHORITY — 2026-09-24**
> متن 2026-09-19 در پایین این فایل فقط سابقه تاریخی است و برای انتخاب branch/head/Design Authority نباید استفاده شود.

- Canonical committed recovery baseline: `75421078d8e944a33f58cbab96723c63480c4f5e`.
- Canonical local branch before WIN-06 continuation: `work/r2-reconciliation-ui-foundation`.
- Active continuation branch in this recovered workspace: `work/win06-handoff-hardening`.
- Product version remains `1.36.4-dev.38`; Phase 8B is **not** Production-ready.
- Do **not** restart from GitHub `main`, `phase/8b-windows-setup`, dev.26 or the historical SHAs below. Recover from the final handoff/full repo/bundle that contains `7542107`, then inspect current Git HEAD/status.
- WIN-06 direction: verified offline prerequisite acquisition for release-build use only; end-user runtime download/install remains forbidden.
- Print Worker is an internal SOKNA Local component; do not revive standalone Pagent Setup/Control/product ownership.
- Sole Design Authority for new/migrated UI: `docs/ui-design-system/CANONICAL_DESIGN_SYSTEM_CONTRACT_FA.md` (`SCDS-CANONICAL-2026-R1`). `docs/UI_DESIGN_SYSTEM_FA.md` is legacy compatibility/reference material only.

Read in this order:
1. `WORKSPACE_START_HERE_FA.md`
2. `docs/handoffs/START_HERE_NEXT_AGENT_FA.md`
3. `docs/handoffs/CURRENT_STATUS_FA.md`
4. `docs/architecture-migration-r2/R2_IMPLEMENTATION_RECONCILIATION_2026-09-24_FA.md`
5. `docs/architecture-migration-r2/PHASE8B_WINDOWS_ACCEPTANCE_STATUS_2026-09-24_FA.md`
6. `docs/ui-design-system/CANONICAL_DESIGN_SYSTEM_CONTRACT_FA.md`

---

## HISTORICAL / SUPERSEDED CONTINUATION TEXT
The following content is retained for provenance only.

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

Do not restart 8B from main. Fetch the existing branch and check the newest head/CI:
https://github.com/mobaraki20/SoknaCafe/pull/18

Read in order:
1. `DEVELOPER_READ_FIRST_FA.md`
2. `docs/handoffs/MASTER_HANDOFF_FA.md`
3. `docs/handoffs/CURRENT_STATUS_FA.md`
4. `docs/handoffs/PHASE8B_IN_PROGRESS_HANDOFF_FA.md` on the active branch
5. `docs/architecture-migration-r2/WINDOWS_INSTALLER_ACCEPTANCE_FA.md`
6. `docs/architecture-migration-r2/WINDOWS_PACKAGING_OWNERSHIP_FA.md` — Inno Setup selected; owner requires no mandatory cost and current test use.
7. Phase 8 design notes and frozen R2 contracts.

Verified hardening source: `2fdb500d3240a1c2adde30b291fe4377d78fca44`; CI `35477813459` SUCCESS on all three gates. This batch implements setup preflight, service/TLS-safe repair, diagnostics and real Windows fault tests. Continue with Inno Setup packaging and explicit Repair after reading the acceptance contract; recheck CI if the branch has advanced. The service-host artifact is not a complete installer; shortcuts, Installed apps lifecycle, prerequisites and updater ownership remain acceptance gates.
Persist every next step in GitHub handoffs, with tested SHA/run and the precise next action.

Latest implementation: Inno platform preview, source `67b32f767bd254bd26bb845302c8490223698e71`, CI `35491459618` pending at checkpoint. Inspect/fix that run before starting further work. Preview requires an already configured app; clean-machine installation remains open.
