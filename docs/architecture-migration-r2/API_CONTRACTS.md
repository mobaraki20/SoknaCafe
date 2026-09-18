# API Contracts — Target Boundary Draft

این قرارداد بر اساس Frozen handoff نوشته شده است. نام دقیق endpointها Technical Choice است و در implementation ممکن است تغییر کند؛ semantics زیر ثابت است.

## 1) اصل ارتباط
- Browser/phone خارج LAN فقط Public را می‌بیند.
- Public هرگز handler Business مرجع نیست.
- Local اتصال outbound HTTPS به Public دارد و work را poll/claim می‌کند؛ inbound اینترنتی به Local لازم نیست.
- Realtime و Deferred-safe endpoint/store از هم جدا هستند.

## 2) Request Envelope مشترک
حداقل فیلدهای منطقی:
- `request_id` globally unique
- `kind`
- `created_at`
- `expires_at` برای Realtime
- `actor_projection_id/session_id`
- `payload`
- `expected_version`/`expected_state` وقتی mutation روی state موجود است
- `occurred_at` برای Deferred-safe
- correlation id

Public→Local payload با HMAC-SHA256 روی canonical request envelope + timestamp + nonce + method/path/body hash authenticate می‌شود؛ secret/installation key خارج web root و خارج DB plaintext نگهداری می‌شود. Replay guard durable است. الگوریتم exact canonicalization در Phase 2 با test vectors قفل می‌شود.

## 3) Realtime Relay
State model منطقی:
`queued -> claimed -> committed|rejected|expired|cancelled|unknown_review`

قواعد:
- Local claim lease محدود دارد؛ lease expiry request را eligible برای re-claim می‌کند، نه duplicate commit.
- Local canonical owner transaction را اجرا می‌کند و نتیجه با `request_id` idempotently ACK می‌شود.
- Client فقط با result terminal موفق می‌شود.
- اگر request قبل از Local commit expire/cancel شود، Local باید قبل از mutation terminal state را revalidate کند.
- ambiguity بعد از network break با result lookup حل می‌شود؛ هیچ background surprise commit پس از expiry مجاز نیست.

Realtime kinds حداقل:
- guest order final submit
- waiter call
- committed order edit/cancel
- settlement/payment commit
- preparation mutation
- Table Draft create/edit/finalize/cancel

## 4) Deferred-safe — Implemented in 1.36.4-dev.31
State model:
`pending_sync | committed | needs_review | rejected`

Public store مستقل: `deferred_work`. این store هیچ `expires_at` و هیچ claim path مشترکی با `realtime_requests` ندارد.

Kinds پیاده‌شده:
- `supply.need.create`
- `supply.status.prepare`
- `supply.status.return`
- `supply.receipt`
- `inventory.waste`
- `inventory.count_draft`
- `subscriber.payment`
- `expense.create`

Lifecycle:
1. Remote Staff با همان Public session و capability projection enqueue می‌کند.
2. Public فقط `pending_sync` را durable ثبت می‌کند؛ enqueue هرگز success نهایی Business نیست.
3. Local Runtime از path مستقل `/api/v1/local/deferred/claim.php` claim/lease می‌گیرد.
4. Local user active/capability، expected state/version، module readiness و financial period را دوباره validate می‌کند.
5. Business commit فقط از owner canonical Domain انجام می‌شود.
6. Local receipt نتیجه را قبل از ACK persist می‌کند؛ lost ACK/reclaim duplicate Business effect ایجاد نمی‌کند.
7. conflict یا closed-period event → `needs_review`.
8. review فقط با تصمیم صریح Admin + reason resolve می‌شود؛ نتیجه resolved با `reconcile.php` به Public برمی‌گردد.

Financial integrity:
- `occurred_at` از commit time جداست.
- رخداد مالی متعلق به period بسته قبل از review هیچ mutation Business ایجاد نمی‌کند.
- duplicate late event فقط یک receipt/review candidate می‌سازد.
- explicit approval silent reopen نمی‌کند و `close_summary_json` دوره بسته را rewrite نمی‌کند.
- normal close وقتی Public paired ولی unknown/unreachable باشد یا در period `pending_sync/needs_review` وجود داشته باشد block می‌شود.
- override فقط Admin با reason و `financial_period_close_overrides` + audit.

Local receipt/review owner:
- `deferred_work_receipts`
- `deferred_review_items`

Realtime-only/local-required operations همچنان از Deferred ممنوع‌اند: settlement، committed-order mutation، preparation mutation، count finalize، inventory correction، payment/expense reversal، catalog/users/theme.

## 5) Auth Projection
Public فقط minimal user projection را نگه می‌دارد:
- stable user id/public projection id
- login verifier/credential material مناسب که password plaintext/Local session secret نیست
- active state
- role/capability keys لازم
- preparation area projection لازم
- projection version/updated_at

Permission decision نهایی mutation در Local دوباره اجرا می‌شود. Public filtering فقط first gate است.

## 6) Guest Publish
`Save Local` فقط Local را تغییر می‌دهد. `Publish Guest Menu` یک immutable revision آماده و به Public upload می‌کند. Public ابتدا revision را کامل validate/store می‌کند، سپس pointer فعال را atomic جابه‌جا می‌کند. Failure pointer قبلی را نگه می‌دارد.

Availability sync یک channel سریع و جدا از content publish است.

## 7) Remote Read Models
هر snapshot دارای `source_version`, `generated_at`, `last_sync_at` است. Local Runtime snapshotها را outbound به Public می‌فرستد و Public فقط آخرین projection محدود را نگه می‌دارد.

Phase 4 در `1.36.4-dev.30` این مدل‌های read-only را پیاده می‌کند:
- `operations`: سفارش/فراخوان/میزهای فعال برای مسئولیت‌های عملیاتی مجاز.
- `preparation`: صف و اصلاح‌های آماده‌سازی؛ برای `preparation` بر اساس areaهای Local فیلتر می‌شود و `shift_supervision` فقط global monitor read دارد.
- `inventory`: مقدار/هشدار موجودی بدون cost.
- `inventory_cost`: cost/value فقط با authority جداگانه `inventory_cost_view`.
- `reports`: summary کش‌شده مدیریتی؛ در semantics فعلی فقط Admin از wildcard authority می‌بیند.

Remote Staff UI هیچ mutation عادی ندارد. وقتی heartbeat یا snapshot قدیمی است، read همچنان در دسترس می‌ماند ولی پاسخ `stale=true` و زمان آخرین sync/connectivity را صریح برمی‌گرداند. Public هنگام هر read دوباره capability و preparation-area projection را enforce می‌کند؛ این filtering جای Permission نهایی Local برای mutation را نمی‌گیرد.

## 8) Public Emergency Console
فقط break-glass: connectivity/status، disable remote access/order intake، revoke binding/token، limited recovery controls. Routine admin ممنوع. همه actions audit + reason + actor + timestamp دارند.

## 9) Local Business Adapters
Public/relay هیچ SQL business مستقیم ندارد. Adapterها باید به ownerهای canonical وصل شوند:
- Orders → canonical order services extracted from current handlers
- Finance → settlement owner
- Inventory → inventory movement owner
- Supply → `modules/Supply` owner
- Preparation → canonical permission/action owner
- Expenses/Tax → owners جدید Phase 6

## 10) Error contract
خطاها machine-readable code + safe localized message + correlation id دارند. Internal state-machine vocabulary نباید بی‌دلیل در UI نهایی نشت کند.
