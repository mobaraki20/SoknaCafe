# قرارداد رسمی Cafe ↔ Sokna Center — نسخه 1

## مرز مالکیت
- Cafe: `users`, Login/Password، Role/Capability عملیاتی و active/inactive حساب کافه.
- Center: Employee, Assignment, Compensation, Payroll و تمام Authorizationهای HR.
- هیچ HR schema، Foreign Key بین دیتابیس‌ها یا Sync رمز وجود ندارد.

## Pairing Key — SC1
مدیر در Cafe فقط Pairing Key را وارد می‌کند. Prefix باید دقیقاً `SC1.` باشد.

بخش بعد از Prefix با Base64URL decode می‌شود (`-` به `+`، `_` به `/` و Padding فقط برای Decode). JSON حاصل باید حداقل شامل این فیلدها باشد:

```json
{
  "v": 1,
  "center": "https://...",
  "issuer": "cafe",
  "secret": "..."
}
```

`secret` یک مقدار opaque است و باید byte-for-byte همان مقدار داخل SC1 باشد. قبل از HMAC نباید hash، Base64، URL encode یا trim شود.

## Header و Signature
Tokenهای Pair/Probe/Handoff Header ثابت زیر را دارند:

```json
{
  "alg": "HS256",
  "typ": "SOKNA-HANDOFF",
  "v": 1
}
```

Signature دقیقاً برابر است با:

`HMAC-SHA256(base64url(header) + "." + base64url(payload), secret, binary=true)`

و خروجی binary HMAC با Base64URL encode به‌عنوان بخش سوم Token قرار می‌گیرد.

## Pair / Re-pair
Pair یک عملیات صریح مدیریتی است و تنها مسیر ثبت/تغییر Trust Relation، Origin و Return URL محسوب می‌شود.

Cafe کاندید Secret را با همان storage رمز‌شده Integration ذخیره/Reload می‌کند، fingerprint مقدار استخراج‌شده، مقدار Reload‌شده و مقدار HMAC را بدون ثبت خود Secret مقایسه می‌کند، Token را Local Verify می‌کند و سپس با POST به:

`/auth/pair.php`

ارسال می‌کند. ارتباط فعال قبلی تا قبل از پذیرش Pair توسط Center جایگزین نمی‌شود.

Payload Pair:

```json
{
  "iss": "cafe",
  "sub": "<local_user_id>",
  "context": "CAFE",
  "aud": "<center-url>",
  "purpose": "pair",
  "iat": 0,
  "exp": 0,
  "nonce": "...",
  "origin": "https://...",
  "return_url": "https://..."
}
```

`exp - iat` حداکثر 60 ثانیه است. Claimهای `issuer`, `local_user_id`, `audience`, `issued_at`, `expires_at` جایگزین claimهای canonical بالا نیستند و در Token Pair/Handoff ارسال نمی‌شوند.

پس از Pair موفق، تنظیم فعال ذخیره و دوباره decrypt/compare می‌شود و سپس Probe Strict اجرا می‌شود.

## Probe Strict
Cafe با POST به `/auth/probe.php` و `purpose=probe` همان claimهای canonical را می‌فرستد. Probe حق ساخت، Repair یا تغییر Trust Relation/Origin/Return URL را ندارد؛ mismatch باید Reject شود.

## Hint دسترسی «پرسنل و حقوق»
Authorization واقعی HR همچنان فقط در Center است و Cafe هیچ HR Role/Capability نمی‌سازد. برای اینکه Sidebar به کاربری که دسترسی مؤثر ندارد Launcher نشان ندهد، پاسخ `probe` می‌تواند برای همان `sub` یکی از این فیلدهای صریح را برگرداند:

```json
{
  "can_open_personnel": true
}
```

یا در `entitlements.can_open_personnel`. Cafe فقط همین Boolean را به‌عنوان **visibility hint** حداکثر ۱۰ دقیقه و به‌ازای همان local user cache می‌کند. Cache به fingerprint اتصال Center وابسته است و پس از تغییر Pair معتبر نیست. اگر Hint وجود نداشته/نامعتبر باشد، Sidebar fail-closed است؛ درخواست شبکه برای Refresh به‌صورت POST و non-blocking انجام می‌شود و عملیات عادی Cafe را متوقف نمی‌کند. Center در Handoff و تمام درخواست‌های بعدی همچنان Authorization نهایی را enforce می‌کند. بنابراین `admin/personnel.php` برای ساخت Handoff به Probe وابسته نیست؛ Probe فقط Hint/Diagnostic ناهمگام Sidebar است و خرابی آن نباید ورود امنی را که خود Center می‌تواند اعتبارسنجی کند متوقف کند.

## Browser Handoff
Cafe پس از Login محلی، Token با `purpose=handoff`, `context=CAFE` و همان claimهای canonical می‌سازد و آن را فقط در body یک POST browser form به `/auth/handoff.php` می‌فرستد. Token وارد URL یا Log نمی‌شود. Cafe هیچ Role/Capability مربوط به HR را داخل Token قرار نمی‌دهد.

## Diagnostics امن Pair
در Failure Pair، Cafe فقط داده‌های غیرحساس زیر را در Audit Integration ثبت می‌کند:
- fingerprint کوتاه SHA-256 Secret استخراج‌شده از SC1
- fingerprint Secret Reload‌شده از storage رمز‌شده
- fingerprint Secret واقعاً پاس‌داده‌شده به HMAC
- HTTP status
- `error.code` پاسخ Center

Secret کامل و Token کامل هرگز Log یا UI نمی‌شوند.

## User Directory: Center → Cafe
Endpoint رسمی Cafe:

`GET /api/sokna_center_users.php?page=1&per_page=50`

حداکثر `per_page=100` است. پاسخ فقط شامل این فیلدهاست:
- `local_user_id`
- `display_name`
- `role`
- `active`
- `updated_at`

`username`, password/password_hash, session, reset/CSRF/API token برگردانده نمی‌شوند.

### Authentication Directory
Header:

`Authorization: Sokna-HMAC <compact-token>`

Token با همان Secret اتصال و HMAC-SHA256 امضا می‌شود؛ Header آن `typ=SOKNA-S2S`, `alg=HS256`, `v=1` است. Payload Directory مطابق قرارداد S2S موجود است:

```json
{
  "issuer": "center",
  "audience": "cafe",
  "purpose": "user_directory",
  "context": "CAFE",
  "timestamp": 0,
  "expires_at": 0,
  "nonce": "...",
  "page": 1,
  "per_page": 50
}
```

اعتبار درخواست حداکثر 60 ثانیه است. `page/per_page` داخل Signature قرار دارند و باید با Query برابر باشند. Nonce با replay guard اتمیک در Cafe مصرف می‌شود و استفاده دوباره Reject می‌شود.

## Resilience
هیچ Call به Center در Bootstrap/Login/Order/Quick Order/Settlement/Printing/Push/Accommodation انجام نمی‌شود. Network اصلی فقط در Pair/Test و Launcher «پرسنل و حقوق» استفاده می‌شود؛ برای visibility hint پرسنل، Sidebar می‌تواند پس از انقضای cache یک Refresh غیرمسدودکننده POST انجام دهد. Failure این Refresh هیچ عملیات کافه را متوقف نمی‌کند.
