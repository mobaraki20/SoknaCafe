# معماری جاری Sokna — 1.36.0


## قید مقیاس و چرخه عمر
Source of Truth این بخش: `../DEVELOPER_READ_FIRST_FA.md`. تا اعلام صریح Owner، سامانه **Pre-Operational** است و برای حداکثر حدود 50 میز، 100–110 مهمان هم‌زمان در Peak، Peak اصلی حدود 3 ماه در سال و تیم عملیاتی 8–9 نفره با مسئولیت‌های چندگانه طراحی می‌شود. معماری Enterprise/multi-branch بدون Requirement صریح مبنای تصمیم نیست.


## Modular Monolith
از شاخه توسعه 1.36، مرزهای Domain به‌صورت Modular Monolith ثبت می‌شوند. Registry مرکزی `../includes/modules.php` است و نقشه مالکیت/Dependency در `ARCHITECTURE_MODULE_MAP_1.36_FA.md` نگهداری می‌شود. این تغییر Deployment یا Database جدا ایجاد نمی‌کند. Supply/Purchasing اولین Pilot بود؛ در RC فعلی Inventory و Supply هر دو Module اختیاری end-to-end هستند. Supply فقط از Contract عمومی Inventory برای ثبت Movement استفاده می‌کند و به runtime-ready بودن Inventory وابسته است.

## هسته
Sokna یک Web Application / PWA فارسی و RTL برای عملیات یک کافه است. Backend و دیتابیس منبع حقیقت عملیات و مالی‌اند؛ UI نباید برای پنهان‌کردن ناسازگاری داده، رکورد را Deduplicate یا حذف کند.

## عملیات و مالی
- حساب میز، سفارش، تسویه و اسناد مالی تراکنشی و قابل Audit هستند.
- اسناد مالی اصلی حذف نمی‌شوند؛ اصلاح با رویداد/سند برگشتی انجام می‌شود.
- `table_number` منبع حقیقت هویت عددی میز است؛ `name` Label انسانی است.
- روز عملیاتی و شیفت هنگام رخداد Snapshot می‌شوند تا تغییر تنظیمات، تاریخ گذشته را بازنویسی نکند.
- نشست بازمانده از روز عملیاتی قبلی Exception است؛ سیستم آن را خودکار نمی‌بندد.

## Quick Order
یک Route/Controller واحد با Token idempotent و Revalidation سرور. ظاهر تثبیت‌شده محافظت می‌شود. سفارش مهمان منتظر، مستقل و همان لحظه از مسیر canonical وضعیت سفارش تأیید یا رد می‌شود؛ ثبت سفارش جدید کارکنان تا تعیین تکلیف Pending مسدود است.

## UI owners
- Compact Choice → Centered Modal
- Browse Choice → Bottom Sheet موبایل / Desktop presentation مناسب
- Date/Time → Centered Modal
- Confirm/Destructive → Centered Modal
- Contextual Action Menu → Popover دسکتاپ / Compact Action Sheet موبایل
- Workspace → Bottom Sheet/Drawer
- Long Workflow → Route
- Nested Overlay به‌صورت پیش‌فرض مجاز نیست.

## Push
Transactional outbox → CLI worker. اتصال خارجی Push داخل Request ثبت/تأیید سفارش انجام نمی‌شود.

## Backup/Recovery
Backup داده و Uploadها از Recovery کد/Update جداست. اعتبارسنجی قبل از Restore اجباری است و Restore باید Fail-closed باشد.

## چاپ
Backend چاپ و Mapping در Web باقی است، اما Print Agent یک جریان مستقل و خارج از Production Acceptance این Release است.
