> **مرجع فعلی تطبیق — 2026-09-25:** شاخه محلی `work/reconcile-dev39`، نسخه سورس dev.39، مبتنی بر تاریخچه گیت‌هاب و پیشرفت‌های بسته. ابتدا `docs/handoffs/DEV39_RECONCILIATION_2026-09-25_FA.md` را بخوانید (مسیر نسبت به ریشه مخزن). ادامه‌ها و دستورهای متعارض زیر سوابق تاریخی‌اند؛ Phase8B کامل نشده، WiX مجوز اجرا نگرفته و Windows CI جدید انجام نشده است.

# SOKNA — Master Project Handoff

Latest verified diagnostic extension: source `0557e64336d1ac1be958787ebe76f895df4647a2`, CI `35504980480` all three gates SUCCESS. Read CURRENT_STATUS and DELIVERY_STATUS for exact scope and outstanding deliverables. Independent Windows Agent repository: `mobaraki20/Pagent`, published v6.2.5 observed; integration still requires validation.

## Active continuation — 2026-09-20
Phase 8B is already implemented in part on `phase/8b-windows-setup`, draft PR #18. Continue that branch, not a fresh branch from main. Read CURRENT_STATUS and PHASE8B_IN_PROGRESS_HANDOFF on the branch for the newest CI evidence. The completed release checkpoint below remains 8A; 8B and the final Windows installer are not complete.

New canonical setup/platform owners on 8B:
- `includes/setup_install.php`, `tools/setup-machine.php`
- `runtime/windows/setup-sokna.ps1`, `setup-support.psm1`
- `runtime/windows/SoknaRuntimeService.cs`, `build-service-host.ps1`
- existing `runtime/windows/provision-local-https.ps1` now preserves validated TLS identity.

Verified 8B hardening source: `2fdb500d3240a1c2adde30b291fe4377d78fca44`; CI `35477813459` SUCCESS (all three gates). This is a branch checkpoint, not a completed/merged Phase 8B release.

Installer requirements: `docs/architecture-migration-r2/WINDOWS_INSTALLER_ACCEPTANCE_FA.md`.

Packaging design checkpoint: `docs/architecture-migration-r2/WINDOWS_PACKAGING_OWNERSHIP_FA.md`. Platform-only Inno packaging enforces the separation from active application files in the verified preview. Clean-machine web stack deployment and full application repair remain open. Owner resolved budget: no mandatory cost, current use is testing. Inno Setup replaces WiX MSI/Burn; explicit Repair remains required. No licensing question is pending and no purchase was made.

Native platform preview source `a3435d187717ffdc1d2fc2914ab81a341e7742b3` is verified by CI `35504331920`: Windows installer lifecycle, Linux and MariaDB all SUCCESS. Artifact `sokna-platform-preview-unsigned` is available in that run. Actual install/shortcuts/Installed apps/cached Repair/owned-service uninstall and data preservation passed on a disposable Windows fixture. The preview assumes an already configured application and is not the complete installer; clean-machine prerequisites, same-version full app repair, unified diagnostics and UAT remain open. Read the latest phase handoff before continuing.

> **CURRENT AUTHORITY BANNER — 2026-09-24**
> Current Phase 8B Closure source is `1.36.4-dev.39` on `work/phase8b-closure-dev39`. Implementation checkpoint `268957d42d281b0f677de5a32be6c41e36b108f5` closes the source hardening; exact packaged head must still be obtained with `git rev-parse HEAD`. Historical GitHub branch/SHAs below are provenance only.
> Product engineering identity for this closure batch is `1.36.4-dev.39`; this is not a Production-ready claim. The 2026-09-19 branch/main instructions later in this file are **HISTORICAL / SUPERSEDED**.
> Sole Design Authority: `docs/ui-design-system/CANONICAL_DESIGN_SYSTEM_CONTRACT_FA.md` (`SCDS-CANONICAL-2026-R1`).
> Print Worker remains internal to SOKNA Local; Phase 8B remains incomplete until real Windows/.NET/WiX/MariaDB/physical acceptance evidence exists.


## Addendum تاریخی reconciliation — SUPERSEDED by dev.39 banner above
> این بخش روندی را ثبت می‌کند که از snapshot `dev.38` به وضعیت فعلی رسید. برای تعیین نسخه/مرحله/اقدام بعدی از banner بالا و `CURRENT_STATUS_FA.md` استفاده کنید.

- Working source آن مرحله snapshot `phase/8b-windows-setup` / `1.36.4-dev.38` بود؛ این دیگر current installable identity نیست.
- `dev.26` فقط Business Behavior + UI DNA/provenance است؛ Working Source نیست.
- همه نسخه‌های قبلی **SOKNA Design System** به تصمیم صریح مالک **REJECTED** هستند. UI فعلی dev.38 نیز Design Authority نهایی نیست. Design System جدید باید از نقاط درست dev.26 + اصلاح ایرادها + اصول استاندارد Persian-first/RTL-first ساخته و روی همه Surfaceهای لمس‌شده enforce شود.
- Printing deployment/ownership قدیمی این فایل Superseded است: طبق R2، **Print Worker component داخلی SOKNA Local است و محصول جدا نصب نمی‌شود**. مرجع جاری: `docs/architecture-migration-r2/PHASE7R_PRINTING_RECONCILIATION_FA.md`.
- Print API v4، durable SQLite queue، reconciliation/submission fence/Winspool semantics بالغ حفظ می‌شوند و در PHP بازنویسی نمی‌شوند. Source baseline داخلی فعلی از Pagent `6.2.5` با provenance ثبت‌شده گرفته شده است؛ Setup/Control مستقل جزو محصول SOKNA نیستند.

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

Updated: 2026-09-19
Repository: `mobaraki20/SoknaCafe`
Current verified release checkpoint: `1.36.4-dev.38`
Current verified product main checkpoint: `485db60b71b40c475c931a9d6d056ce8a07d0df2`

این فایل مرجع سطح‌بالای ادامه پروژه است. هر ایجنت جدید باید قبل از هر تغییر کد، این فایل را کامل بخواند. هدف این است که ادامه پروژه بدون نیاز به تاریخچه ChatGPT یا پرسیدن مجدد تصمیم‌های قبلی ممکن باشد.

---

## 1) Source of Truth

ترتیب اعتبار منابع:

1. **Current packaged Git head (`1.36.4-dev.39`)** = رفتار واقعی و canonical code/business owners؛ `dev.38` predecessor/provenance است. UI موجود فقط current implementation است، نه Design Authority نهایی.
2. **Handover R2** در `docs/architecture-migration-r2/` = تصمیم‌های Frozen معماری/محصول.
3. **Latest phase handoff** در `docs/handoffs/` = نقطه ادامه، CI، PR/SHA و scope جاری.
4. Release notes/checkpoint هر فاز = شواهد پیاده‌سازی همان فاز.

اگر بین این منابع تناقضی بود:
- رفتار کد فعلی را نادیده نگیر.
- تصمیم Frozen در R2 را نقض نکن.
- قبل از ساخت Business owner موازی، owner فعلی را پیدا و refactor کن.
- UI فعلی را کورکورانه حفظ نکن؛ تصمیم صریح مالک درباره Design System جدید و Persian-first/RTL-first بالاتر از UI legacy/current است.

---

## 2) Historical GitHub recovery recipe — NOT current package recovery authority

> برای بسته محلی فعلی از Full Repo/Git Bundle داخل Handoff و `README_START_HERE_FA.md` استفاده کنید. دستورات GitHub زیر provenance دوره 2026-09-19 هستند و نباید checkpoint محلی dev.39 را با main قدیمی جایگزین کنند.

Repository:
`https://github.com/mobaraki20/SoknaCafe`

برای ادامه از وضعیت دقیق این handoff:

```bash
git clone https://github.com/mobaraki20/SoknaCafe.git
cd SoknaCafe
git checkout main
git pull
cat VERSION.txt
```

اگر لازم است checkpoint فعلی را دقیقاً بازسازی کنی:
```bash
git checkout 485db60b71b40c475c931a9d6d056ce8a07d0df2
```

اما در حالت عادی همیشه از آخرین `main` شروع کن و سپس `docs/handoffs/CURRENT_STATUS_FA.md` را بخوان.

---

## 3) Frozen Architecture — Never Violate

### Authority
- SOKNA Local = **تنها Business Authority**.
- Public هرگز full Business DB یا admin clone نیست.
- Public فقط guest edge، remote staff gateway، durable relay، safe projections، deferred-safe pending work و emergency/recovery surface محدود است.

### Queue split
دو state machine جدا هستند و **هرگز merge نشوند**:
1. Realtime Relay: order submit, waiter call, settlement, preparation mutation, edit/cancel committed order, table draft finalize.
2. Deferred-safe: supply need/status/receipt, waste, stock-count draft, pending subscriber payment, general expense.

### Mutation safety
- success فقط بعد از Local commit معتبر.
- lost ACK نباید duplicate business mutation بسازد.
- ambiguous request نباید surprise-commit شود.
- expected state/version باید Local revalidate شود.
- late event برای period بسته هرگز silent back-post یا auto reopen نمی‌کند.

### Permissions
- همان `users/user_capabilities/user_preparation_areas` authority اصلی است.
- permission system دوم نساز.
- `shift_supervision` فقط global Preparation monitor read-only است.
- `preparation` mutation فقط در assigned area.
- admin role به‌تنهایی Preparation mutation نمی‌دهد.

### Guest/Public
- renderer مهمان Local/Public مشترک است.
- Public menu از snapshot/versioned publish می‌خواند.
- Local-down: آخرین publish خواندنی می‌ماند، mutationهای unsafe متوقف می‌شوند.
- QR در حالت paired به Public guest route می‌رود.

### Sellables
- `sellable_kind` فقط `menu_item | service_item`.
- هرگز از category/station/name/recipe inference نکن.
- `SERVICE-TAKEAWAY` manual packaging service است؛ هیچ auto-fee برای takeaway وجود ندارد.

### Financial periods
- pending/needs-review deferred همان period normal close را block می‌کند.
- Public unknown/unreachable normal close را block می‌کند.
- override فقط audited manager/admin با reason + actor + timestamp + connectivity snapshot.
- closed report تا correction explicit دست‌نخورده می‌ماند.

### Printing / Notifications / Integrations
- Print Worker component داخلی SOKNA Local است؛ Setup/Repair/Recovery خود SOKNA آن را build/package/provision/manage می‌کند و محصول/installer/download جداگانه ندارد. Print API v4، SQLite durable queue، renderer، submission fence، reconciliation و Winspool state machine بالغ حفظ می‌شوند و نباید در PHP duplicate شوند.
- Push queue processing زیر Runtime است؛ `push_event_queue` durable truth می‌ماند و request-time drain فقط accelerator/fallback است.
- Accommodation transport از business/settlement/recovery owner جدا است.
- Center outbound user projection capability-gated است؛ legacy inbound directory تا اثبات migration سمت Center حذف نمی‌شود.
- business receipt tax دارد؛ preparation ticket tax ندارد.

### UI/DS
- همه SOKNA Design Systemهای قبلی REJECTED هستند و Design Authority نیستند.
- `dev.26` مرجع DNA و رفتار خوب UI است، نه template مقدس؛ هر ایراد باید قبل از migration اصلاح شود.
- Design System جدید SOKNA باید Persian-first / RTL-first باشد و tokens/components/behavior/responsive/a11y را canonical و قابل Gate کند.
- legacy CSS/markup را زنده نکن و UI ضعیف dev.26/dev.38 را کورکورانه کپی نکن.
- fork renderer یا duplicate route نساز.

---

## 4) Phase History — What Has Actually Been Completed

### Phase 0 — Source Audit
Baseline source: `1.36.4-dev.26`.

خروجی‌های اصلی:
- `docs/architecture-migration-r2/SOURCE_AUDIT_REPORT_FA.md`
- `ARCHITECTURE_GAP_ANALYSIS_FA.md`
- `MIGRATION_MATRIX.csv`
- `SCHEMA_CHANGE_PLAN.md`
- `API_CONTRACTS.md`
- `RISK_REGISTER.md`
- `BASELINE_TEST_REPORT.md`

یافته‌های مهم:
- canonical guest order transaction در `api/create_order.php` / بعداً service extraction.
- guest renderer از Local DB مستقیم می‌خواند.
- Table Draft نبود.
- explicit sellable kind نبود.
- Supply/Inventory/Settlement/Printing/Notifications mature بودند و باید preserve می‌شدند.
- Preparation permission debt تأیید شد.
- Financial close deferred/Public gates نداشت.

### Phase 1 — Runtime Foundation
Checkpoint اولیه GitHub قبل از PR #1.

Main owners:
- `includes/observability.php`
- `includes/runtime.php`
- `runtime/sokna-runtime.php`
- `runtime/windows/provision-local-https.ps1`

Outcome:
- ProgramData-oriented data root.
- correlation IDs/log redaction/health.
- supervised background workers.
- Local HTTPS provisioning foundation.
- no premature print-worker rewrite.

### Phase 2 — Public Edge + Realtime Relay
PR #1
Merge commit: `f47bde53cd4ae29c4368a5bfb62584691f2dc478`

Owners:
- `includes/relay_protocol.php`
- `includes/relay_client.php`
- `includes/relay_dispatch.php`
- `includes/relay_projection.php`
- `includes/relay_actor.php`
- `public_edge/`
- `tools/relay-worker.php`

Outcome:
- outbound Local→Public claim/ack model.
- signed/authenticated requests.
- durable realtime queue.
- Local canonical commit + idempotency.
- minimal auth projection.
- no inbound Internet dependency on Local.

### Phase 3 — Guest/Public Snapshot Runtime
PR #2
Merge commit: `25e01c0d1465a7b7f0b20115a82cfedc6a8c018d`

Key owners:
- `includes/guest_menu_view.php`
- `includes/guest_publish.php`
- `public_edge/guest/`
- `public_edge/api/v1/guest/compat/`
- `assets/js/menu.js`

Outcome:
- Local/Public share canonical guest renderer/runtime.
- immutable published snapshot/media manifest.
- degraded read-only menu when Local stale/offline.
- realtime guest actions route to Local canonical services.
- lost ACK + post-TTL reconciliation hardened.
- QR cutover to Public when paired.
- forked Public guest runtime removed.

### Phase 4 — Permission-aware Remote Read Models
PR #3
Merge commit: `124b569b7488ccce707a78733967581e65793a18`

Owners:
- `includes/remote_read_models.php`
- `tools/remote-read-worker.php`
- `public_edge/api/v1/remote/`
- `public_edge/staff/`
- `public_edge/database/migrations/004_phase4_remote_read_models.sql`

Read models:
- operations
- preparation
- inventory
- inventory_cost
- reports

Outcome:
- same Local accounts/capabilities.
- preparation area filtering.
- supervisor global monitor read-only.
- explicit stale/last-sync/connectivity.
- no canonical Orders/Finance/Inventory DB clone on Public.

### Phase 5 — Deferred-safe + Financial Reconciliation
PR #4
Merge commit: `652a950e5909a755a1aa80279c97a645dc537dfe`

Owners:
- `includes/deferred.php`
- `includes/expenses.php`
- `tools/deferred-worker.php`
- `public_edge/api/v1/deferred/`
- `public_edge/api/v1/local/deferred/`
- financial-period hooks in Local.

Outcome:
- Public `deferred_work` separate from realtime.
- states: `pending_sync / committed / needs_review / rejected`.
- Local receipt/idempotency/revalidation.
- supply need/status/receipt, waste, stock-count draft, subscriber payment, general expense.
- count finalize remains Local-only.
- late event on closed period => `late_correction_review`.
- normal close blocked on pending/review/Public unknown.
- audited close override.
- no silent back-post/reopen.

Checkpoint/release:
- `docs/architecture-migration-r2/PHASE5_CHECKPOINT_FA.md`
- `RELEASE_NOTES_1.36.4_DEV31_FA.md`

### Phase 6A — Preparation Permission Split
Release: `1.36.4-dev.32`
PR #5
Merge commit: `32e9b3b239ad3320a7af2ec168fb7f19acbd589c`
Post-merge CI: `35422830131` SUCCESS

Canonical owner:
- `includes/preparation_permissions.php`

Final semantics:
- preparation only → assigned visible/actionable.
- shift_supervision only → all visible, none actionable.
- both → all visible, assigned actionable.
- admin alone → no operational mutation.
- browser uses server-authored scopes.

Handoff:
- `docs/handoffs/PHASE6A_HANDOFF_FA.md`

### Phase 6B — Explicit Sellable Kind
Release: `1.36.4-dev.33`
PR #7
Merge commit: `afa84a333ca34d8405af58f2bb3287e6aebfca39`
Post-merge CI: `35424762946` SUCCESS

Canonical owner:
- `includes/sellable.php`

Schema:
- `items.sellable_kind`
- `order_items.sellable_kind_snapshot`

Migration:
- `docs/architecture-migration-r2/PHASE6B_LOCAL_MIGRATION.sql`

Handoff:
- `docs/handoffs/PHASE6B_HANDOFF_FA.md`

### Phase 6C — Server-persistent Table Draft
Release: `1.36.4-dev.34`
PR #10
Merge commit: `ccf0656655702a0b175a7cb9d7521fcb808745b1`
Product post-merge CI: `35438494232` SUCCESS

Canonical owners:
- `includes/staff_order_service.php` — canonical Staff Quick Order commit transaction.
- `includes/table_draft.php` — Table Draft lifecycle and concurrency owner.
- `staff/api_table_draft.php` — Local HTTP surface.
- `includes/relay_actor.php` + relay dispatch/protocol — authenticated remote adapter.

Outcome:
- exactly one active server-persistent draft per table.
- shared draft across authorized staff with optimistic `version` conflict protection.
- no order row/business number/preparation/inventory/finance/receipt side effect before Finalize.
- explicit cancel/finalize; no auto-expiry.
- Finalize revalidates current Local state and delegates to canonical Staff Order owner.
- normal Quick Order browser state is server-authoritative; stale editor cannot overwrite newer draft.
- remote Table Draft is Realtime/Local-required only and never Deferred-safe.

Handoff:
- `docs/handoffs/PHASE6C_HANDOFF_FA.md`

### Phase 7 — Printing / Notifications / Integrations
Release: `1.36.4-dev.37`

Phase 7A — PR #12 / merge `215949cd3507bcf2d20860bd6a8c1c6ef67c51b3` / main CI `35439861511` SUCCESS.
- Historical implementation: Runtime آن زمان Agent نصب‌شده را supervise می‌کرد. **Deployment/ownership این خط با Phase7R Superseded شده**؛ state/Winspool logic فقط به‌عنوان behavior provenance حفظ می‌شود.
- Push processing is explicitly Runtime-owned; durable outbox remains canonical.

Phase 7B — PR #13 / merge `c83df6bdfd240a29a8c8bcf6653f2b69886d2954` / main CI `35440573461` SUCCESS.
- Accommodation HTTPS mechanics extracted to `includes/accommodation_transport.php`.
- Local settlement/recovery/ambiguity semantics preserved.

Phase 7C — PR #14 / merge `b29cf18aea52228fc44e08aac5e2a7c521295f98` / main CI `35441174227` SUCCESS.
- Runtime-owned, capability-gated outbound Cafe user projection to Center.
- legacy inbound user directory retained as compatibility fallback.
- no Public personnel database.

Handoff:
- `docs/handoffs/PHASE7_HANDOFF_FA.md`

### Phase 8A — Installation Identity + Recovery Set Metadata
Release: `1.36.4-dev.38`
PR #16
Merge commit: `485db60b71b40c475c931a9d6d056ce8a07d0df2`
Post-merge CI: `35442209529` SUCCESS

Outcome:
- dedicated Ed25519 installation identity under private data root.
- installation private key is never archived.
- Backup v3 manifest enriched with safe Recovery Set metadata.
- portable app.key remains separate and portable for encrypted integration secrets.
- no schema change; mature backup/restore engine preserved.

Handoff:
- `docs/handoffs/PHASE8A_HANDOFF_FA.md`

---

## 5) Current Exact State

Current completed checkpoint:
**Phase 8A / 1.36.4-dev.38**

Latest merge:
- PR #16
- merge commit: `485db60b71b40c475c931a9d6d056ce8a07d0df2`
- post-merge CI: `35442209529` — SUCCESS
- all three gates PASS.

Current next phase:
**Phase 8B — Windows New / Recover Setup Orchestration**

Preserve and compose existing owners: fresh install, Runtime/HTTPS service setup, **internal Print Worker component owned by SOKNA Setup/Repair/Recovery**, backup import/restore, updater recovery, optional Public/off-server/push setup.

---

## 6) Canonical Source Map

### Platform / Runtime
- `bootstrap.php`
- `includes/observability.php`
- `includes/runtime.php`
- `runtime/sokna-runtime.php`

### Auth / permissions
- `includes/auth.php`
- `includes/functions.php`
- `includes/preparation_permissions.php`
- `user_capabilities`
- `user_preparation_areas`

### Orders / Tables / Settlement
- `includes/guest_order_service.php`
- `includes/guest_order_manage_service.php`
- `includes/guest_order_status_service.php`
- `includes/guest_table_context_service.php`
- `includes/staff_order_service.php`
- `includes/table_draft.php`
- `staff/api_table_draft.php`
- `staff/api_quick_order.php`
- `operator/api_bill.php`
- `operator/api_status.php`
- `includes/settlement.php`

### Catalog / Menu
- `includes/menu_catalog.php`
- `includes/sellable.php`
- `admin/item_form.php`
- `admin/items.php`

### Preparation
- `includes/preparation_permissions.php`
- `waiter/api_feed.php`
- `waiter/api_action.php`
- `waiter/index.php`
- `assets/js/waiter.js`

### Inventory / Supply
- `includes/inventory.php`
- `modules/Supply/domain.php`
- `modules/Supply/service.php`
- `admin/inventory_count.php`

### Subscribers / Finance
- `includes/subscribers.php`
- `includes/settlement.php`
- `admin/financial_periods.php`

### Deferred
- `includes/deferred.php`
- `includes/expenses.php`
- `tools/deferred-worker.php`
- `public_edge/api/v1/deferred/`
- `public_edge/api/v1/local/deferred/`

### Public / Relay
- `includes/relay_protocol.php`
- `includes/relay_client.php`
- `includes/relay_dispatch.php`
- `includes/relay_projection.php`
- `public_edge/`

### Guest renderer
- `includes/guest_menu_view.php`
- `includes/guest_publish.php`
- `assets/js/menu.js`
- `assets/css/guest-menu.css`

### Setup / Recovery / Identity
- `includes/installation_identity.php` — machine installation identity owner.
- `includes/maintenance.php` — Backup v3 / secure export / restore / emergency recovery / Recovery Set metadata.
- `install.php` — existing fresh web install owner.
- `includes/updater_engine/1.5.3/` — updater staging/validation/recovery/rollback owner.

### Printing / Runtime
- `includes/printing.php` + `print-agent/v4/` — mature print state/API owner.
- `tools/print-runtime-worker.php` — thin Runtime supervisor for the **internal SOKNA Print Worker service**; Print state machine remains in the internal worker component.
- `includes/runtime.php` / `runtime/sokna-runtime.php` — worker orchestration.

### Notifications
- `includes/push.php`
- `tools/push-worker.php`
- `service-worker.js`

### Accommodation
- `includes/accommodation.php` — business/settlement/recovery owner.
- `includes/accommodation_transport.php` — HTTPS transport owner.

### Center / Personnel integration
- `includes/sokna_center.php` — trust/handoff/entitlement owner.
- `includes/sokna_center_projection.php` — outbound Cafe user projection.
- `tools/center-projection-worker.php` — Runtime projection worker.
- `api/sokna_center_users.php` — legacy compatibility directory until Center migration is proven.

---

## 7) Tests / Promotion Gates

A Phase/Subphase is not COMPLETE unless all required gates pass on final head and again post-merge on `main`.

Required jobs:
1. Windows runtime/TLS.
2. Public + Local MariaDB integration.
3. Linux full regression + browser gates.

Primary gate:
- `tests/run-1360-dev-gate.sh`

Phase-specific contracts must be added to this gate and CI.

When source owner moves, update tests to follow canonical owner; do not weaken invariants merely to make tests pass.

---

## 8) UAT / Environment Gaps Not Proven by Hosted CI

Do not claim these are fully production-validated until real-device UAT:
- Windows Service Control Manager host lifecycle.
- cashier PC real install/upgrade/rollback.
- physical printer/spooler quality and recovery.
- mobile LAN hostname/discovery `sokna.local`.
- iPhone PWA/WebPush.
- Wi-Fi↔4G transitions on real devices.
- shared-host production Public deployment characteristics.
- disaster recovery on replacement machine.

Hosted CI proves code/contracts, not these physical/environmental behaviors.

---

## 9) Git / Handoff Discipline

For every new Phase/Subphase:
1. create branch from current `main`.
2. audit existing canonical owners first.
3. implement minimal change without owner duplication.
4. add dedicated contract/runtime/browser tests as relevant.
5. update release identity/checkpoint if required.
6. create/update `docs/handoffs/PHASE..._HANDOFF_FA.md`.
7. update `docs/handoffs/CURRENT_STATUS_FA.md`.
8. update this Master if source map/frozen decisions/phase history changed materially.
9. require three CI gates on final head.
10. PR → merge with expected head SHA.
11. require post-merge CI on `main`.
12. only then mark COMPLETE.

---

## 10) Next Agent — Exact First Action

Phase 8A is complete. Do not recreate it.

Start Phase 8B from current `main` after reading:
- `docs/handoffs/CURRENT_STATUS_FA.md`
- `docs/handoffs/PHASE8A_HANDOFF_FA.md`
- `docs/architecture-migration-r2/PHASE8_DESIGN_NOTES_FA.md`
- R2 Phase 8 schema/risk sections.

First audit:
- `install.php` fresh install flow.
- Runtime Windows service/HTTPS provisioning.
- internal Print Worker build/package/provision/Windows acceptance.
- backup import/restore + updater recovery.
- optional Public pairing/off-server/push configuration.

Build orchestration around these owners. Do not duplicate their state machines.
