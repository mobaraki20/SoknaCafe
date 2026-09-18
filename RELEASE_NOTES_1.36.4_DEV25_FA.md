# Sokna 1.36.4-dev.25 — Print Report Nullable Evidence Compatibility

این نسخه Hotfix رسمی برای نصب تستی `1.36.4-dev.24` است و از مسیر استاندارد `/admin/update/` نصب می‌شود. Migration دیتابیس ندارد.

## رفع Report ACK پس از Submission

- فیلدهای اختیاری `spooler_job_id`، `error_code` و `error_message` در Print API v4 اکنون JSON `null` را به‌عنوان «Evidence موجود نیست» می‌پذیرند.
- گزارش موفق `submitted` همچنان بدون `spooler_job_id` معتبر پذیرفته نمی‌شود و رفتار `spooler_job_id_required` حفظ شده است.
- گزارش‌های `failed` / `unknown` / `recovery_hold` می‌توانند Evidence اختیاری را به‌صورت `null` ارسال کنند بدون اینکه با `invalid_field_type` در Outbox Agent گیر کنند.
- هیچ State Transition، Retry Policy یا Submission Fence تغییر نکرده است؛ این Hotfix فقط قرارداد wire nullable را با رفتار واقعی Agent هم‌راستا می‌کند.

## سازگاری

- مبدا رسمی Update: `1.36.4-dev.24`.
- Agent پیشنهادی: `6.2.4` و نیازی به نصب مجدد Agent نیست.
- Migration دیتابیس: ندارد.
