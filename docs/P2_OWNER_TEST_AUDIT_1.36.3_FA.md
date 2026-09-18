# P2 Owner / Test Audit — Sokna 1.36.3

## وضعیت

این مرحله فقط بدهی P2 را لمس می‌کند و تحت قرارداد حفظ UI/Behavior اجرا شده است.

## P2-1 — Shared Functions Hotspot

`includes/functions.php` پیش از این مرحله حدود 139KB و 2783 خط بود و 204 تابع global را در یک Owner مشترک نگه می‌داشت.

Rewrite کلی انجام نشد. استخراج به‌صورت دو مرحله incremental انجام شد و فقط خوشه‌هایی با Owner روشن منتقل شدند:

- `includes/function_domains/jalali.php` — تبدیل/Parse/Format تاریخ جلالی و timezone.
- `includes/function_domains/media.php` — آپلود/کتابخانه/اعتبارسنجی تصویر و image picker.
- `includes/function_domains/favicon.php` — مسیرها، revision، ذخیره و حذف favicon.
- `includes/function_domains/audit.php` — snapshot و writeهای Audit، شامل strict/best-effort owner.
- `includes/function_domains/messages.php` — registry و resolve پیام‌های مهمان/کارکنان.
- `includes/function_domains/events.php` — presentation و lifecycle رویداد.

`includes/functions.php` همچنان قرارداد include عمومی پروژه است و Ownerها را `require_once` می‌کند. Call siteهای محصول تغییر نکرده‌اند و هیچ Route/Page مستقیماً `function_domains/*` را include نمی‌کند.

پس از مرحله اول، فایل به حدود 109KB و 2149 خط رسید. پس از ادامه P2 و استخراج Audit/Messages/Events، `includes/functions.php` به حدود 86KB و 1906 خط کاهش یافت. هدف کوچک‌کردن فایل به هر قیمت نبود؛ Order/Finance/Inventory/Capabilityها به‌دلیل وابستگی و حساسیت بیشتر عمداً در این Batch جابه‌جا نشدند.

### نکته Root Path

Media و Favicon قبلاً داخل `includes/functions.php` از `dirname(__DIR__)` برای ریشه پروژه استفاده می‌کردند. بعد از انتقال به یک پوشه عمیق‌تر، این وابستگی مکانی در تست Favicon آشکار شد. Ownerهای جدید اکنون صریحاً از `dirname(__DIR__, 2)` استفاده می‌کنند تا مسیر ریشه با محل فایل اشتباه نشود.

## P2-2 — Historical Test Naming Debt

در زمان Audit، 114 فایل تست با نام نسخه‌ای `v...` وجود داشت.

- 75 فایل مستقیماً در Dev/Release gateهای جاری نام برده شده‌اند.
- تعدادی دیگر به‌طور غیرمستقیم توسط ماتریس UI یا تست‌های دیگر استفاده می‌شوند.
- گروه باقی‌مانده historical/manual است و مستقیم در gate جاری نیست.

### تصمیم

- Mass rename انجام نشد؛ تغییر نام 100+ فایل بدون تغییر معنا، churn و ریسک بی‌فایده ایجاد می‌کند.
- Mass delete انجام نشد؛ نبودن یک تست در runner به‌تنهایی اثبات obsolete بودن آن نیست.
- از این مرحله به بعد **تست جدید باید نام Semantic/Domain-based داشته باشد** و Prefix نسخه‌ای جدید فقط با دلیل صریح historical/reproduction مجاز است.
- هنگام لمس یک تست نسخه‌دار، اگر invariant آن هنوز فعال است، نام/Owner آن در همان Scope می‌تواند canonical شود؛ اگر superseded است، فقط پس از اثبات پوشش معادل حذف می‌شود.

## تست‌های Source-sensitive اصلاح‌شده

چند Contract قدیمی به محل فیزیکی توابع داخل `includes/functions.php` وابسته بودند. فقط همان Contractها به Owner واقعی متصل شدند:

- `v13210-schedule-contracts.py`
- `v13210-defect-class-gate.py`
- `small-cafe-flow.py`
- `panel-keyboard-input-contract.py`
- `guest-message-contract.py`
- `messages-v2-contract.py`
- `guest-copy-domain-parity.py`
- `v13220-human-reference-ui.py`
- `v13220-audit-timeline.py`
- `v1360-final-invariants.py`
- `prelaunch-cleanup.py`

این تغییر semantics محصول را عوض نمی‌کند؛ تست دیگر محل فایل را با وجود capability اشتباه نمی‌گیرد.

## Regression شناخته‌شده خارج از Scope

`tests/media-picker-browser.py` در assertion مربوط به autofocus کتابخانه تصویر روی Baseline قبل از P2 نیز Fail می‌شود. بنابراین Regression این استخراج نیست و در این Batch تغییر داده نشده است.

## Rule برای ادامه P2

- Owner extraction فقط هنگام وجود مرز Domain روشن.
- هیچ Business Logic جدیدی به `includes/functions.php` افزوده نشود مگر واقعاً cross-domain باشد.
- Refactor بعدی باید یک Domain را جدا لمس کند، نه اینکه `functions.php` یک‌باره به چندین فایل خرد شود.


## شواهد Verification

- PHP lint در Dev Gate پس از ادامه P2: 161 فایل PASS.
- JS syntax: 36 فایل PASS.
- Unit: 95/95 PASS.
- Dev Gate تمام Contractهای غیرمرورگری تا Browser بدون Assertion Fail اجرا شد؛ اجرای یک‌جای Browser به سقف زمانی محیط رسید.
- Core/Domain contractهای غیرمرورگری Release Gate نیز در دو بخش اجرا و PASS شدند؛ اجرای یک‌جای Gate به‌دلیل سقف زمانی محیط شکسته شد، نه Assertion محصول.
- Browserهای مستقیم ادامه P2: `messages-v2-browser.py`، `event-meta-browser.py`، `guest-1276-browser.py` و `v1360-ui-conformance-browser.py` PASS.
- Browserهای مستقل مرحله اول: Jalali، Guest، UI Conformance، Favicon، Shared Sheet و Icon System PASS.
- `media-picker-browser.py` روی Baseline قبل از P2 نیز در autofocus assertion Fail است؛ به این Batch نسبت داده نمی‌شود.
- Diff نسبت به Batch B: هیچ CSS، JavaScript یا PHP صفحه‌ای در admin/operator/staff/waiter/Guest تغییر نکرده است.
