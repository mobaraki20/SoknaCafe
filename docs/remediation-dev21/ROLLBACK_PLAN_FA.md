# Rollback Plan — Sokna 1.36.4-dev.21

تاریخ: 2026-09-12

## قبل از Rollback

1. چاپ‌های در وضعیت `reserved/claimed/started/unknown/recovery_hold` را Drain/Reconcile کنید؛ هیچ state یا history را پاک نکنید.
2. از دیتابیس و کد نسخه جاری backup/restore point معتبر بگیرید.
3. routeهای Primary/Fallback و audit مربوط به Promote را ثبت کنید.

## Rollback کد Web

- بسته کد 1.36.4-dev.20 را restore کنید.
- ستون `last_heartbeat_at` و index جدید را **حذف نکنید**؛ dev.20 ستون اضافه را نادیده می‌گیرد و حفظ ستون از تخریب evidence جلوگیری می‌کند.
- Rollback به dev.20 رفتار قدیمی freshness را برمی‌گرداند؛ بنابراین تا برگشت مجدد به dev.21، Failover/Promote را Production-ready فرض نکنید.

## Rollback دیتابیس

- Rollback پیش‌فرض destructive نیست. DROP COLUMN/INDEX خودکار ارائه نمی‌شود.
- اگر در محیط آزمایش جداگانه الزام فنی به حذف schema additive وجود داشت، فقط بعد از backup و تأیید عدم وابستگی نسخه‌های جدید اقدام شود.

## Agent

- در این تحویل Agent 6.2.3 ساخته نشده است؛ بنابراین rollback installer Agent موضوع این بسته نیست.
- queue.db، local_jobs، outcome/history یا pending claim برای rollback پاک نشوند.
