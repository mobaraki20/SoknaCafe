> **Historical prelaunch baseline — Superseded for deployment/ownership by `docs/architecture-migration-r2/PHASE7R_PRINTING_RECONCILIATION_FA.md`.**
> Print API v4 invariants remain valid; the old external-product packaging rule is no longer active.

# Pre-launch Print v4 Clean Baseline

وضعیت: **WORKING / NOT RELEASE**

## تصمیم
سامانه هنوز عملیاتی نشده است؛ بنابراین compatibility با Print API v3 و Agentهای قدیمی ارزش عملیاتی ندارد و فقط ریسک duplicate ownership، مسیر تست اضافی و نگهداری بیشتر ایجاد می‌کند.

Baseline پیش از اولین انتشار عملیاتی:

- Cafe فقط `print-agent/v4/api.php` را نگه می‌دارد.
- `print-agent/api.php` (v3) وجود ندارد.
- `protocol_version` در DB وجود ندارد؛ همه credentialهای Agent برای v4 هستند.
- نصب تازه فقط از `database/schema.sql` انجام می‌شود.
- migrationهای pre-release صرفاً مربوط به Print (`1.32.18-print-template-v2`, `1.33.0-print-agent-v4`, hardening follow-up) وارد baseline نهایی نمی‌شوند؛ schema نهایی مستقیماً Template v2 و Print v4 را می‌سازد.
- Source/Binary Agent داخل Cafe نیست؛ مرجع Agent فقط `mobaraki20/Pagent` است.
- `submitted` همچنان فقط پذیرش Windows Spooler است، نه اثبات چاپ فیزیکی.
- `unknown/recovery_hold` auto-reprint نمی‌شوند.

## تست سیستم موجود
چون محیط فعلی عملیاتی نیست، می‌توان قبل از UAT نهایی داده‌های Print را پاک و Agent/Mapping را از صفر ساخت. Reset باید فقط روی محیط تست و با backup/confirmation صریح انجام شود؛ ابزار destructive داخل Release عمومی نگهداری نمی‌شود.
