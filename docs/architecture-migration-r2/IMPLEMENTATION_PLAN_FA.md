# برنامه اجرایی فازبندی‌شده SOKNA

## Checkpoint 0A — Baseline Stabilization (قبل از Phase 1)
هدف: سورس dev.26 را بدون تغییر Business Behavior به baseline سبز تبدیل کنیم.

کارها:
1. هم‌راستاسازی `service-worker.js`, README و updater docs با dev.26.
2. رفع leakage واژه `Attempt` در UI چاپ با wording کاربرپسند.
3. ثبت مالکیت `print_claim_reconciliations` در Registry.
4. اصلاح contract testهای stale که dev.23/schema count قدیمی را hard-code کرده‌اند، بدون ضعیف‌کردن invariant.
5. اجرای کامل static/unit gate و ثبت evidence.

Exit gate: lint/syntax/unit/static contracts سبز؛ DB-dependent tests فقط با وضعیت صریح BLOCKED_ENVIRONMENT مجاز به defer هستند.

## Phase 1 — Runtime Foundation
- استخراج runtime owner و Windows service skeleton
- health/log/correlation infrastructure
- managed worker lifecycle
- local HTTPS/discovery plan + implementation
- updater/recovery hooks برای runtime
Checkpoint: current Local business flows بدون worker duplication کار کنند.

## Phase 2 — Public/Relay Foundation
- Public deployable skeleton مناسب shared hosting
- installation binding/keys/auth projection
- realtime request/result + polling/lease/ACK/idempotency
- heartbeat/telemetry + kill switch + emergency console skeleton
- adapter به Local canonical business services
Checkpoint: synthetic realtime request دقیقاً once business commit؛ timeout هیچ delayed surprise commit ندارد.

## Phase 3 — Guest/Public Snapshot
- publish revision pipeline
- menu/media/theme safe snapshot
- انتقال QR primary route به Public
- degraded read behavior و disable order/waiter-call هنگام Local unavailable
Checkpoint: failed publish revision فعال قبلی را خراب نکند؛ submit فقط پس از Local commit success شود.

## Phase 4 — Remote Read Models — IMPLEMENTED in 1.36.4-dev.30
- role/capability-aware projections از همان Local user/capability/area authority
- پنج projection محدود: operations / preparation / inventory / inventory_cost / reports
- Runtime outbound sync با `tools/remote-read-worker.php`
- Public Remote Staff read-only surface در `/staff/?installation_id=...`
- stale indicators، last-sync و read-only degrade هنگام unavailable بودن Local
- Preparation monitor/assigned-area matrix بدون اعطای mutation جدید
Checkpoint: static boundary contract + HTTP/MariaDB permission/area/stale tests + full regression باید قبل از Merge سبز باشند.

## Phase 5 — Deferred-safe + Financial Reconciliation — IMPLEMENTED in 1.36.4-dev.31
- Public queue/store مستقل از Realtime
- purchase need/status/receipt، waste، count draft، subscriber payment، general expense
- Local durable receipts + lost-ACK idempotency
- expected-state/version + current permission revalidation
- one-candidate conflict/late review
- financial close pending/unknown-Public gate
- audited Admin override with reason
- cached `deferred_context` + capability-aware 4G forms/status
- Expenses canonical owner + seeded categories
- Local count-draft route consolidated onto Inventory owner

Checkpoint gates:
- static Deferred boundary contract
- Public HTTP/MariaDB lifecycle test
- Local MariaDB domain/idempotency/late-period test
- Windows runtime/lint
- Linux full regression/browser

## Phase 6 — Domain Additions/Refactors
ترتیب داخلی برای کاهش ریسک:
1. Preparation visible/actionable permission fix
2. explicit sellable kind
3. server Table Draft
4. batch purchase
5. Expenses owner/UI
6. Tax owner/calculator/snapshots/UI
Checkpoint هر زیرگام جدا؛ Tax و Table Draft بدون integration با phase بعد merge نمی‌شوند مگر gates سبز باشند.

## Phase 7 — Printing / Notifications / Integrations
- internalize Print Worker زیر Runtime بدون تعویض state machine
- move Notification processing under Runtime
- Accommodation transport adaptation
- Center outbound adaptation
Checkpoint: print restart/retry/fallback و notification failure isolation fault-tested.

## Phase 8 — Setup / Recovery / Backup / Takeover
- Windows install new/recover flows
- optional Public pairing/printer/offsite/push setup
- enriched Recovery Set/PITR
- machine replacement + fresh identity + Public takeover
Checkpoint: restore + takeover drill.

## Phase 9 — Full Migration / Cleanup / Release
- حذف owner/pathهای obsolete و push device management surface
- module enable/disable regression
- complete fault/chaos/UAT
- Clean Install + accepted predecessor Update Package + release docs
- architecture docs sync with code
Exit: no unresolved blocker.

## Decision protocol
تا وقتی هنداور تصمیم دارد، سؤال محصول مطرح نمی‌شود. Technical choices توسط تیم اجرا با test/evidence گرفته می‌شوند. فقط اگر سورس جدید رفتار Business مهمی نشان دهد که در handoff پوشش داده نشده یا دو تصمیم Frozen واقعاً با هم conflict کنند، کار همان sub-scope متوقف و تصمیم به مالک پروژه ارجاع می‌شود؛ بقیه فازها بی‌دلیل متوقف نمی‌شوند.
