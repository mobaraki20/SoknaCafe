# Sokna 1.36.4-dev.26 — Pre-Operational Print Clean Baseline

این نسخه فقط برای محیط تستی فعلی و قبل از Go-Live ساخته شده است. هدف آن پاک‌سازی کنترل‌شده تاریخچه اجرای چاپ در Server پس از Reset محلی Queue ایجنت است.

## محدوده پاک‌سازی Server

Migration یک‌باره فقط این داده‌های تستی چاپ را پاک می‌کند، با ترتیب سازگار با Foreign Keyها:

- `print_claim_reconciliations`
- `print_claim_requests`
- `print_attempts`
- `print_jobs`

قبل از حذف Jobها، `reprint_of_id` صریحاً Null می‌شود.

## داده‌هایی که حفظ می‌شوند

- `print_agents`
- `print_destinations`
- `print_templates`
- Agent/Printer Mapping
- سفارش‌ها، میزها، مالی، انبار، کاربران و سایر Domainهای سامانه

## قانون شناسه‌ها

این Migration عمداً هیچ `AUTO_INCREMENT`ای را Reset نمی‌کند. شناسه‌های Job/Attempt باید پس از Baseline تمیز نیز رو به جلو ادامه پیدا کنند تا با تاریخچه Durable قدیمی Agent دوباره Collision ایجاد نشود.

## ترتیب نصب الزامی

1. سرویس `SoknaPrintAgent6` روی Windows متوقف شود.
2. `queue.db` محلی Agent به‌صورت Backup از مسیر فعال خارج شود؛ `config.json`، `secret.dat` و `bridge-pairing.id` حفظ شوند.
3. این Update از `/admin/update/` نصب شود.
4. سرویس Agent دوباره Start شود و Queue جدید SQLite ساخته شود.
5. Diagnostics باید `backlog=0` و `conflict=0` نشان دهد.
6. فقط یک `Sokna PDF Test` برای UAT ارسال شود.

این Reset برای Production یا بعد از Go-Live مجاز نیست.
