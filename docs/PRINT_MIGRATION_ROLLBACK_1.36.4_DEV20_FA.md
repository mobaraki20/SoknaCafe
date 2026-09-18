# Migration و Rollback چاپ — 1.36.4-dev.20

مبدأ رسمی این migration فقط `1.36.4-dev.19` است. تغییرات فقط ستون‌های runtime Bridge و fingerprint درخواست‌های Print API v4 را اضافه می‌کنند و هیچ Job/Attempt/Template یا شناسه تاریخی را پاک یا reset نمی‌کنند.

## پیش از اجرا

Updater رسمی باید write maintenance را فعال و Restore Point دیتابیس/فایل بسازد. وجود stateهای `pending/reserved/claimed/started/failed/unknown/recovery_hold/submitted` مانع migration schema نیست، اما پیش از rollback یا restore به snapshot قدیمی باید Print Agentها متوقف شوند تا evidence جدیدتر از backup با DB قدیمی مخلوط نشود.

## رفتار داده قدیمی

`request_hash`های مربوط به requestهایی که در dev.19 ثبت شده‌اند قابل بازسازی نیستند، چون dev.19 بدنه canonical را نگه نمی‌داشت. بنابراین ستون‌های hash در upgrade nullable هستند. از dev.20 به بعد هر mutation جدید hash خود را ثبت می‌کند؛ replay رکورد legacy همچنان از bindingهای موجود receipt/lease/state/evidence استفاده می‌کند و برای آن رکورد ادعای body-fingerprint تاریخی نمی‌شود. Fresh install ستون claim را `NOT NULL` می‌سازد چون همهٔ Claimهای جدید hash دارند.

## rollback / restore

DDL در MySQL/MariaDB اتمیک فرض نشده است. اگر updater در میانه migration fail کند، نصب نیمه‌تمام نباید به سرویس عملیاتی برگردد؛ Restore Point رسمی برای بازگشت فایل+DB استفاده شود. پیش از restore، Agent polling/Bridge متوقف گردد. پس از restore، Agent دارای evidence یا outbox جدیدتر از snapshot نباید auto-reprint کند؛ queue باید pause و با `attempt_status`/timeline انسانی reconcile شود. پاک‌کردن Job ID/Attempt ID یا صف Agent برای سبزکردن rollback ممنوع است.
