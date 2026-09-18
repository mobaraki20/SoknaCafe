# نصب و ارتقای Sokna 1.36.3

> پروژه هنوز **Pre-Operational** است. نصب Production فقط پس از PASS شدن Promotion Gate روی MariaDB/MySQL واقعی، HTTP احراز هویت‌شده، UAT موبایل/Push و تست Print واقعی مجاز است.

## Fresh install آزمایشی
برای نصب تازه فقط از Source کامل `1.36.3` استفاده شود. `database/schema.sql` منبع Schema نصب تمیز است و آرشیو Migrationهای Pre-Launch داخل Working Tree نگه‌داری نمی‌شود.

## Update آزمایشی
مسیر استاندارد این RC فقط از آخرین نسخه منتشرشده است:

- `1.36.2` → `1.36.3`

Update فقط از مسیر `/admin/update/` انجام می‌شود. این RC **Migration دیتابیس جدید ندارد**؛ Schema مالی تغییر نکرده و Health Check جاری همچنان قراردادهای قبلی را Fail-closed بررسی می‌کند.

- `VERSION.txt` و Service Worker باید `1.36.3` باشند.
- این Update با Engine فعال `1.36.0` یعنی `1.5.3` اجرا می‌شود و همان Engine مقصد `1.5.3` را حفظ می‌کند؛ فایل‌های موتور فعال محافظت‌شده‌اند.
- Update واقعی از `1.36.2` به `1.36.3` روی Host کاربر تا زمان اجرای واقعی `UAT_REQUIRED` است.
- قبل از Promotion، `SOKNA_RELEASE_PROMOTION=1 bash tests/run-release-gate.sh` باید در محیط دارای `pdo_mysql`، MariaDB/MySQL واقعی و Session احراز هویت‌شده Staging اجرا شود.
- `GO_LIVE_CHECKLIST_FA.md` مرجع جاری پذیرش و UAT پیش از Go-Live است.
