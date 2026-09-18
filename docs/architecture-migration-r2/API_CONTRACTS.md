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

## 4) Deferred-safe
State model Frozen:
`pending_sync | committed | needs_review | rejected`

Allowed kinds:
- purchase need/statusهای safe
- purchase/receipt
- waste
- inventory count draft (finalize ممنوع)
- pending customer payment (reversal Local)
- general expense

قواعد:
- `occurred_at` جدا از commit time.
- Local expected version/state و current financial period را revalidate می‌کند.
- closed-period event به correction/review می‌رود، نه silent mutation.
- duplicate request همان result canonical را برمی‌گرداند.

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
هر snapshot دارای `revision/version`, `generated_at`, `last_sync_at`, scope/capability markers است. UI در stale mode زمان آخرین sync را نشان می‌دهد و actionهای unsafe را disable می‌کند.

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
