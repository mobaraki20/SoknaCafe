# Schema Change Plan

این سند DDL نهایی نیست؛ قرارداد migration و ownership است. DDL هر phase فقط هنگام implementation همان phase نهایی می‌شود و باید migration + rollback/recovery notes داشته باشد.

## اصول
- Local DB مرجع نهایی Business است.
- Public DB فقط relay/projection/snapshot/pending-safe/telemetry.
- additive-first؛ حذف column/table فقط Phase 9 پس از regression.
- historical financial/order rows هرگز برای semantics جدید rewrite نمی‌شوند مگر backfill صریح و audit-safe.
- هر remote mutation یک idempotency/request identity پایدار دارد.
- `occurred_at` از `committed_at` جداست.

## Checkpoint 0A
Schema business تغییر نمی‌کند. فقط Registry ownership `print_claim_reconciliations` با schema موجود هم‌راستا می‌شود.

## Phase 1 — Runtime / installation identity
Local:
- installation/runtime metadata و health state ترجیحاً در config/secrets + minimal DB metadata؛ private key در DB plaintext ممنوع.
- correlation/outbox metadata در صورت نیاز با migration additive.

## Phase 2 — Public/Relay
Public schema پیشنهادی:
- `edge_config` / active Local installation binding
- `projected_users` + projected capabilities/areas با حداقل اطلاعات لازم
- `remote_sessions`
- `realtime_requests` (request_id, kind, actor/session, payload, created_at, expires_at, state, claim lease, expected version)
- `realtime_results` (request_id unique, terminal result/error, committed entity refs)
- `local_telemetry` / heartbeat
- emergency/break-glass audit records + remote kill switch state

Local:
- `remote_request_receipts` یا equivalent idempotency ledger برای جلوگیری از duplicate commit
- Public link/sync metadata بدون کپی Business state

## Phase 3 — Published Guest Snapshot
Public:
- `published_revisions`
- revision-scoped menu/read-model rows یا versioned JSON blobs با indexهای لازم
- `published_media`/asset manifest
- theme package/revision metadata
- operational availability snapshot جدا از publish revision

Local:
- publish job/outbox state + last acknowledged Public revision
- media/theme metadata در صورت نیاز؛ فایل‌های canonical Local باقی می‌مانند.

## Phase 4 — Remote Read Models
Public:
- versioned read-model snapshots با `source_version`, `generated_at`, `last_sync_at`
- projectionها فقط داده لازم برای role/capability؛ no full admin DB.

## Phase 5 — Deferred-safe
Public:
- `deferred_work` با unique request id، kind، occurred_at، expected_version/state، payload، state (`pending_sync`,`committed`,`needs_review`,`rejected`), result metadata.

Local:
- durable deferred receipt/reconciliation ledger
- conflict/review records
- late-correction/review linkage به financial period
- close override audit table (actor/reason/connectivity snapshot/timestamp)
- period reconciliation summary/checkpoint fields اگر برای gate لازم شود.

Business adapters:
- Purchase/Receipt/Waste از canonical ownerهای فعلی استفاده می‌کنند.
- Inventory Count فقط draft remotely editable؛ finalize Local.
- Pending customer payment و reversal طبق owner finance Local.
- Expenses در همین phase می‌تواند schema اولیه بگیرد اما owner نهایی در Phase 6 ثبت می‌شود.

## Phase 6 — Domain additions/refactors
### Table Draft
- `table_drafts`: table_id، state، version، created/updated by، timestamps؛ unique active draft per table با invariant service-level + index/locking.
- `table_draft_items`: item snapshot refs/qty/note/fulfillment و optimistic version data.
Finalize داخل transaction canonical Orders انجام می‌شود؛ فقط در finalize order number/inventory/finance/prep side effects ایجاد می‌شوند.

### Sellable type
- `items.sellable_kind` enum-like varchar با default/backfill `menu_item`.
- service-specific behavior از explicit type مشتق می‌شود، نه حدس از preparation_station.
- `order_items` تاریخی rewrite نمی‌شود؛ برای audit آینده در صورت نیاز `sellable_kind_snapshot` additive از زمان migration به بعد.

### Expenses
- `expense_categories` seeded stable keys یا registry-backed categories.
- `expenses`: amount, category, occurred_at, description, actor, committed_at, source request, status.
- correction/reversal append-only (`reverses_expense_id` یا separate immutable correction table). Hard delete committed records ممنوع.

### Tax
- effective-dated `tax_rate_versions`/tax configuration.
- item tax mode: `INHERIT_DEFAULT|EXEMPT|CUSTOM_RATE` + version/reference لازم.
- settlement/order financial snapshots شامل taxable base، allocated discount، rate، tax amount، final amount؛ historical pre-tax rows tax=0 semantics خود را حفظ می‌کنند.
- rounding/allocation algorithm یک owner واحد و test vectors ثابت دارد.

### Batch Purchase
تا حد ممکن از `inventory_supply_receipts` موجود استفاده می‌شود. در صورت نیاز یک `purchase_batches`/`purchase_batch_lines` برای atomic command/audit اضافه می‌شود؛ receipt/movement authority موجود duplicate نمی‌شود.

### Preparation Permissions
Schema `user_preparation_areas` حفظ می‌شود؛ تغییر اصلی service/API contract است، نه data duplication.

## Phase 7
Printing tables/state machine فعلی حفظ می‌شوند؛ runtime/agent identity fields فقط اگر برای worker داخلی لازم باشد additive می‌شوند. Notifications در صورت نبود persistent in-app truth، `notifications` + `notification_user_state` به‌صورت canonical Local اضافه می‌شوند. Accommodation schema حفظ؛ Center projection/outbox فقط به اندازه transport جدید.

## Phase 8
Recovery metadata شامل Public binding، installation identity metadata، media/theme manifests، integration metadata و encrypted secrets references می‌شود. Old private key در machine replacement clone نمی‌شود.

## Phase 9
فقط بعد از UAT و migration evidence مسیرها/tables/fields منسوخ حذف می‌شوند. هیچ delete/destructive migration قبل از این phase مجاز نیست مگر schema کاملاً unused و recoverable باشد.
