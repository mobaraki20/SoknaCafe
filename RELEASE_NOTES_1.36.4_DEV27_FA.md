# Sokna 1.36.4-dev.27 — Architecture Migration Phase 1 / Local Runtime Foundation

این نسخه اولین checkpoint اجرایی بر اساس Architecture Handoff R2 است و predecessor رسمی آن `1.36.4-dev.26` است.

## Baseline Stabilization
- Web/PWA release identity با VERSION هم‌راستا شد.
- واژگان فنی چاپ از UI روزمره مدیر حذف شد.
- مالکیت `print_claim_reconciliations` در Module Registry اصلاح شد.
- contractهای تاریخی طوری اصلاح شدند که invariant را حفظ کنند ولی active version را به dev.23 قفل نکنند.

## Local Runtime Foundation
- `includes/observability.php`: correlation ID، JSONL logging و redaction داده حساس.
- `includes/runtime.php`: Runtime state/health، single-instance lock و worker registry.
- `runtime/sokna-runtime.php`: supervisor داخلی برای workerهای canonical Push/Inventory/Backup؛ بدون duplicate Business logic.
- Runtime هنگام Maintenance/Recovery workerها را pause می‌کند.
- canonical `config()` accessor برای CLI workerهای موجود اضافه شد.

## Local HTTPS
- hostname هدف `sokna.local`.
- PowerShell provisioning برای CA/certificate محلی و trust store.
- Apache HTTPS template با secrets خارج از web root هدف.

## محدودیت Checkpoint
- Windows SCM service-host binary و نصب نهایی در محیط Windows/Installer باید UAT شود؛ در این محیط Linux به‌صورت جعلی PASS نشده است.
- MariaDB واقعی و Windows Printer همچنان UAT_REQUIRED/BLOCKED_ENVIRONMENT هستند.

Business schema در این checkpoint تغییر نمی‌کند.
