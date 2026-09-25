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

Phase 8B Windows setup اکنون `runtime/windows/SoknaRuntimeService.cs` را به‌عنوان ServiceBase host واقعی به این entrypoint متصل می‌کند. SCM هرگز مستقیماً `php.exe` را به‌عنوان سرویس ثبت نمی‌کند.

TLS محلی: `runtime/windows/provision-local-https.ps1` CA و certificate محلی را برای `sokna.local` می‌سازد و CA را در trust store سیستم نصب می‌کند. Apache template فقط از مسیر data/secrets استفاده می‌کند. Phase 8B wiring اولیه را از طریق `runtime/windows/setup-sokna.ps1` انجام می‌دهد؛ takeover هویت/Public در Phase 8C باقی می‌ماند.

Canonical Windows setup owner: `runtime/windows/setup-sokna.ps1`; این اسکریپت ownerهای موجود را compose می‌کند و هیچ state machine تجاری را دوباره پیاده نمی‌کند.

### Phase 8B Windows hardening
- Build `runtime/windows/build-service-host.ps1` in Windows CI and ship its `SoknaRuntimeService.exe`; target setup no longer compiles source.
- Run `setup-sokna.ps1` with `-ServiceHostExe` pointing to that artifact. `Validate` checks dependencies in a private TEMP session without changing the target or SCM.
- Each invocation prints a diagnostics directory containing `summary.json`, `events.jsonl` and an allowlisted `support.zip`. Credentials are removed before export; raw configs/backups/keys are never bundled.
- New/Recover preflight checks the canonical setup owner with `--validate-only`. Later failure after business setup must use Repair, not repeat New.
- Repair preserves service configuration and restores the old service binary/running state if service installation fails; existing valid TLS identity is reused. Partial/invalid TLS identity requires explicit recovery.
- Print Worker یک component داخلی SOKNA Local است؛ Setup مستقل یا `PrintAgentSha256` ندارد. build داخلی باید provenance/version manifest معتبر داشته باشد و Windows CI آن را قبل از بسته‌بندی SOKNA تولید و تست کند.
- MSI/Burn, shortcuts, Installed apps, full prerequisite acquisition and end-to-end HTTP/DB health remain packaging acceptance work; hosted SCM tests do not prove cashier/printer UAT.
