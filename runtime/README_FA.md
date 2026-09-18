# SOKNA Local Runtime — Phase 1

`runtime/sokna-runtime.php` مالک orchestration سرویس‌های پس‌زمینه Local است؛ Business logic را دوباره پیاده نمی‌کند و workerهای canonical فعلی را به‌صورت `--once` اجرا می‌کند.

در Phase 1: Push، Inventory outbox و Backup زیر این supervisor قابل اجرا هستند. Print Worker عمداً تا Phase 7 به این Runtime منتقل نمی‌شود تا state machine بالغ چاپ قبل از زمان مقرر دست‌کاری نشود.

حالت‌های Runtime در data root نوشته می‌شوند. مسیر نهایی Windows برابر `%ProgramData%\SOKNA` است؛ fallback `storage/` فقط برای توسعه/سازگاری فعلی است.

دستورهای تشخیصی بدون DB:

- `php runtime/sokna-runtime.php --self-check`
- `php runtime/sokna-runtime.php --health-json`

اجرای Runtime به DB واقعی نیاز دارد:

- `php runtime/sokna-runtime.php`
- `php runtime/sokna-runtime.php --once`

Windows Service host و packaging نهایی در Installer Phase 8 به این entrypoint متصل می‌شود. Phase 1 قرارداد process/health/logging را قفل می‌کند؛ نصب سرویس ناقص یا جعلی با `sc.exe` روی `php.exe` مجاز نیست.

TLS محلی: `runtime/windows/provision-local-https.ps1` CA و certificate محلی را برای `sokna.local` می‌سازد و CA را در trust store سیستم نصب می‌کند. Apache template فقط از مسیر data/secrets استفاده می‌کند. Setup Phase 8 مسئول wiring و renewal/takeover خواهد بود.
