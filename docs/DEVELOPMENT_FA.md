# راهنمای توسعه Sokna — 1.36.0

برای محیط آماده، `LOCAL_DEVELOPMENT_FA.md` را ببینید.

## قیدهای پروژه قبل از توسعه
فایل ریشه [`DEVELOPER_READ_FIRST_FA.md`](../DEVELOPER_READ_FIRST_FA.md) Source of Truth وضعیت چرخه عمر و مقیاس فعلی است. در وضعیت فعلی **Pre-Operational**، Legacy/Compatibility بدون Requirement واقعی نباید مانع Cleanup و یکپارچه‌سازی Ownerها شود.

سیاست اجباری تغییرات کد: [`ROOT_CAUSE_REFACTOR_POLICY_FA.md`](ROOT_CAUSE_REFACTOR_POLICY_FA.md). در Sokna «کد روی کد» یا Patch Stacking راه‌حل محسوب نمی‌شود؛ تغییر باید در Root Cause/Owner اصلی انجام شود و مسیر منسوخ مرتبط، در صورت نبود Requirement واقعی، حذف گردد.

## پیش‌نیاز
- PHP 8.1+
- MySQL/MariaDB سازگار با Schema پروژه
- `pdo_mysql`, `openssl`, `fileinfo`, `json`; `gd` برای پردازش بهینه تصویر توصیه می‌شود
- Node.js برای Syntax checks
- Chromium/Playwright برای Browser regressions

## قواعد کدنویسی
- PHP با `strict_types=1`
- Prepared Statement، Transaction و `FOR UPDATE` برای مسیرهای حساس
- HTML escape با `e()`
- APIهای حساس با CSRF و Request/Idempotency ID مناسب
- Backend منبع حقیقت مالی/عملیاتی است
- پیام کاربر انسانی؛ جزئیات داخلی در Log
- مسیر موازی، Owner دوم، CSS order patch و override انباشته ممنوع

## UI contract
- Compact Choice → Modal
- Browse Choice → Mobile Bottom Sheet
- Date/Time/Confirm → Modal
- Contextual Action Menu → Popover/Action Sheet
- Workspace → Sheet/Drawer
- Long Flow → Route
- Nested Overlay پیش‌فرض ممنوع
- Primary/Confirm راست؛ Secondary/Cancel/Reject چپ
- Touch target حداقل 44px

هر صفحه حق ندارد Container سلیقه‌ای بسازد؛ رفتار باید از Owner مشترک یا semantic mode بیاید.

## بخش‌های محافظت‌شده
Quick Order visual geometry، Guest Menu، Settlement و سایر Contractهای Regression-Locked فقط برای Bug/Requirement اثبات‌شده تغییر می‌کنند. برای Quick Order هر تغییر CSS نیازمند تأیید صریح Owner است.

## Schema و Migration
- Schema جدید فقط همراه Migration idempotent و تست Upgrade
- `database/schema.sql` Source of Truth نصب تمیز است
- Migration هر Update، فقط در صورت نیاز، مستقیماً داخل بسته Release حمل می‌شود؛ آرشیو Migration تاریخی در Working Tree نگه‌داری نمی‌شود
- در وضعیت Pre-Operational، Migration/Schema/Compatibility قدیمی می‌تواند در صورت Rebaseline تمیز و تست‌شده حذف یا ادغام شود؛ زنجیره نصب/Upgrade پشتیبانی‌شده جاری باید معتبر بماند

## Release
- `VERSION.txt` و Service Worker identity هم‌زمان تغییر کنند
- Update فقط با `tools/build-release.php` ساخته شود
- Full package مسیرهای runtime/protected مثل `config.php`, `storage/`, `uploads/` را حمل نکند
- Source tree، Full artifact و نتیجه Update باید معادل باشند، به‌جز مسیرهای محافظت‌شده

## کنترل کیفیت
```bash
bash tests/run-smoke.sh
php tests/unit.php
```

علاوه بر Automation، Device/Host-sensitive changes باید روی محیط واقعی UAT شوند. Test not run = Not tested.

## Pre-Go-Live compatibility policy

- داده آزمایشی و Runtime منسوخ‌شده صرفاً برای سازگاری تاریخی حفظ نمی‌شوند.
- Cleanup مخرب Schema/Route در صورت نیاز مجاز است، به شرطی که Fresh Install و Upgrade از **نسخه Dev واقعاً نصب‌شده** تست شوند.
- هر Build قابل نصب Dev باید Version یکتا داشته باشد؛ Build هم‌نسخه منتشر نمی‌شود.
- هر checkpoint قابل نصب شماره یکتا دارد؛ پس از `1.36.0` فقط Bugfixهای RC مجازند؛ Feature جدید تا تصمیم صریح بعد از UAT وارد این شاخه نمی‌شود.
- Backward compatibility با نسخه‌های Pre-Launch که دیگر نصب نیستند، الزام محصول نیست.


## سیاست نام‌گذاری تست پس از P2

- تست جدید باید بر اساس Domain/Invariant نام‌گذاری شود، نه شماره نسخه.
- Prefix نسخه‌ای جدید فقط برای reproduction تاریخی با دلیل مستند مجاز است.
- تست نسخه‌دار موجود هنگام لمس باید از نظر semantic drift بررسی شود؛ rename/delete فقط با اثبات پوشش معادل.
