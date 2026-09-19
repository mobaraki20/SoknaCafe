# SOKNA — Master Project Handoff

Updated: 2026-09-19  
Repository: `mobaraki20/SoknaCafe`  
Current verified release checkpoint: `1.36.4-dev.33`  
Current verified main at time of this handoff: `3b1c35c8bf7e06a512194559736fbba0303fec46`

این فایل مرجع سطح‌بالای ادامه پروژه است. هر ایجنت جدید باید قبل از هر تغییر کد، این فایل را کامل بخواند. هدف این است که ادامه پروژه بدون نیاز به تاریخچه ChatGPT یا پرسیدن مجدد تصمیم‌های قبلی ممکن باشد.

---

## 1) Source of Truth

ترتیب اعتبار منابع:

1. **Current `main` source** = رفتار واقعی، UI/Design System، Business behavior و canonical owners.
2. **Handover R2** در `docs/architecture-migration-r2/` = تصمیم‌های Frozen معماری/محصول.
3. **Latest phase handoff** در `docs/handoffs/` = نقطه ادامه، CI، PR/SHA و scope جاری.
4. Release notes/checkpoint هر فاز = شواهد پیاده‌سازی همان فاز.

اگر بین این منابع تناقضی بود:
- رفتار کد فعلی را نادیده نگیر.
- تصمیم Frozen در R2 را نقض نکن.
- قبل از ساخت Business owner موازی، owner فعلی را پیدا و refactor کن.
- UI فعلی owner طراحی است مگر R2 صریحاً تغییر را الزام کرده باشد.

---

## 2) How to Recover the Exact Source

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
git checkout fb929f3e62d76b98c89c1721183988d2c1ae493b
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

### Printing
- state machine بالغ چاپ را زود refactor نکن.
- target آینده: internal Print Worker، اما stability چاپ 4–5s مهم است.
- business receipt tax دارد؛ preparation ticket tax ندارد.

### UI/DS
- current source owner UI/Design System است.
- legacy CSS/markup را زنده نکن.
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

---

## 5) Current Exact State

Current completed checkpoint:
**Phase 6B / 1.36.4-dev.33**

Current in-progress work:
**Phase 6C — Server-persistent Table Draft**

Active branch: `phase/6c-table-draft`  
Active head: `756d805912351d6dd539f9922e9bd97144369b63`  
Current CI: Windows PASS / Public+MariaDB PASS / Linux FAIL.  
Read `docs/handoffs/PHASE6C_IN_PROGRESS_HANDOFF_FA.md` before changing Phase 6C.

Frozen target:
- exactly one active draft per table.
- server persistent and shared between authorized staff.
- no order row before finalize.
- no business order number before finalize.
- no preparation task before finalize.
- no inventory movement before finalize.
- no finance/settlement/receipt before finalize.
- no auto-expiry.
- explicit finalize or cancel.
- finalize revalidates table/session, permissions, catalog, price, availability and fulfillment.
- finalize must call canonical Staff Order owner; no duplicated order transaction.
- remote draft operations are realtime/local-required only.
- Table Draft is never Deferred-safe.

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

### Printing
Preserve existing mature owner/state machine until Phase 7:
- `admin/printing.php`
- print queue/state/claim code under current printing owners.
Do not redesign early.

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

Do not start by writing schema.

First audit Phase 6C:
- table/session lifecycle owners.
- current staff quick-order/cart state.
- canonical staff order commit transaction.
- table/order permission owners.
- any browser-only cart/draft persistence.
- order side effects: business number, prep claim/ticket, inventory, finance, printing.

Then write:
- `docs/architecture-migration-r2/PHASE6C_DESIGN_NOTES_FA.md`
- schema plan for draft-only tables.
- contract proving draft save has zero canonical order side effect.
- finalize adapter that delegates to existing canonical Staff Order transaction.

Exact active status is always in:
`docs/handoffs/CURRENT_STATUS_FA.md`.

For the current unmerged Phase 6C branch also read:
`docs/handoffs/PHASE6C_IN_PROGRESS_HANDOFF_FA.md`.

Never discard or restart an active phase branch merely because `main` is the last completed checkpoint. First compare the branch to main and inspect its latest CI.
