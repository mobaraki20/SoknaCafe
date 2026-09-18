# Batch B — پاک‌سازی ریشه‌ای Schema و Domain Contract — Sokna 1.36.3

## وضعیت

- Lifecycle سامانه: `PRE-OPERATIONAL`
- نوع تغییر: Root-Cause / Owner-based cleanup
- قرارداد رابط: `UI_BEHAVIOR_PRESERVATION_CONTRACT_FA.md`
- Redesign: انجام نشده
- CSS: بدون تغییر

این Batch سه Compatibility Model باقی‌مانده را از Source جاری حذف می‌کند. هدف این است که Clean Schema و Runtime یک Contract واحد داشته باشند و هیچ Read/UI branch برای داده‌ای که قبل از Go-Live دیگر معتبر نیست، نگه‌داری نشود.

## B1 — هویت Canonical میز

`cafe_tables.table_number` در Clean Schema اکنون `NOT NULL` است.

مسیرهای repair/backfill میز بدون شماره از `admin/tables.php` حذف شده‌اند و Helper قدیمی استخراج شماره از نام میز نیز دیگر Owner هویت میز نیست. Sort/QR/Guest Preview/Quick Order از `table_number` canonical استفاده می‌کنند.

در حالت معتبر جاری، ساختار بصری و Flow صفحه میزها تغییر نکرده است. فقط Surface مربوط به داده Legacy نامعتبر حذف شده است.

## B2 — Snapshot قطعی زمان عملیاتی

چهار Domain زیر اکنون در Clean Schema برای Snapshot زمان عملیاتی مقدار `NOT NULL` دارند:

- `table_sessions`
- `orders`
- `waiter_calls`
- `settlement_records`

فیلدهای canonical:

- `business_date`
- `business_shift_key`
- `business_shift_label`
- `business_cutoff_snapshot`

Writerهای جاری از قبل این Snapshot را ثبت می‌کردند. Batch B fallbackهای Read Model به `started_at` یا `settled_at` را حذف کرده است تا گزارش، Invoice، Carryover و Operator همگی همان Snapshot ثبت‌شده را بخوانند.

Snapshot ناقص دیگر به‌طور ضمنی بازسازی نمی‌شود؛ این وضعیت Schema/Data defect محسوب می‌شود.

## B3 — یک Owner برای شمارش باز انبار

Contract Domain اکنون صریحاً «حداکثر یک Draft شمارش باز» است.

`inventory_open_count_sessions()` و UIهای چند Draft قدیمی حذف شده‌اند و Owner واحد `inventory_open_count_session()` باقی مانده است. Mutex/Transaction موجود با کلید `inventory_count_start_guard` همچنان Owner جلوگیری از Start همزمان است؛ معماری جدید یا Constraint پیچیده اضافه نشده است.

Flow عادی شروع/ادامه/لغو شمارش برای یک Draft باز حفظ شده است.

## Schema Health

`includes/schema_health.php` اکنون علاوه بر Invariantهای مالی قبلی، این موارد را read-only بررسی می‌کند:

- `cafe_tables.table_number` واقعاً `NOT NULL` باشد.
- هر چهار Snapshot زمان عملیاتی در هر چهار جدول Owner واقعاً `NOT NULL` باشند.

بنابراین دیتابیس Pre-Batch-B با Schema قدیمی به‌صورت ساکت پذیرفته نمی‌شود.

## سیاست دیتابیس آزمایشی قبل از Go-Live

`database/schema.sql` Source of Truth نصب تمیز است. آرشیو Migrationهای آزمایشی در Working Tree نگه‌داری نمی‌شود.

اگر یک دیتابیس آزمایشی قدیمی باید حفظ شود، تغییر Schema آن باید به‌صورت یک Migration کنترل‌شده و idempotent در Release Artifact مربوط انجام شود. در غیر این صورت، چون سامانه هنوز عملیاتی نیست، نصب/دیتابیس آزمایشی می‌تواند قبل از Go-Live از Clean Schema بازسازی شود. Compatibility branch دائمی برای داده Pre-Operational برگردانده نشود.

## Preservation Evidence

در Diff نسبت به Baseline قبل از Batch B هیچ فایل CSS تغییر نکرده است.

Browser Gateهای مستقیم Surfaceهای درگیر و همسایه PASS شده‌اند، شامل:

- Tables layout
- Inventory workflow
- Inventory hotfix regression
- Guest Menu
- Jalali
- Panel Navigation
- UI Conformance

همچنین PHP lint، JS syntax، Unit `95/95` و Contractهای Domain/Finance/Printing/Permission/Navigation جاری PASS مانده‌اند.

Release Contractهای غیرمرورگری کامل PASS شدند. اجرای یک‌جای ماتریس بزرگ Browser Release در محیط فعلی به سقف زمان اجرا می‌رسد؛ بنابراین برای آن ادعای «اجرای کامل» ثبت نمی‌شود. Browserهای مرتبط با Scope به‌صورت مستقل اجرا و PASS شده‌اند.

## Anti-regression

`tests/batch-b-schema-root-contract.py` به Dev/Release Gate متصل شده است و بازگشت این موارد را رد می‌کند:

- Nullable table identity
- Legacy table-number repair path
- Nullable business snapshot contract / null fallback
- Multiple-open-count compatibility owner

## خارج از Scope

- Redesign یا تغییر Layout
- تغییر CSS
- تغییر State Machine مالی
- تغییر رفتار Print
- تغییر Permission model
- Refactor عمومی `includes/functions.php`
