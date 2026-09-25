# Phase 8B — Apache Integration Source Checkpoint

تاریخ: 2026-09-24
وضعیت: **SOURCE IMPLEMENTED / WINDOWS RUNTIME + FIELD UAT PENDING**

## مرز مالکیت
- Apache/PHP همچنان prerequisite خارجی هستند؛ SOKNA Local Runtime مالک lifecycle آن‌ها نیست.
- `runtime/windows/configure-apache.ps1` فقط integration/configuration محدود SOKNA را مالک است.
- هیچ `Start-Service` / `Stop-Service` / `Restart-Service` / `httpd -k restart|graceful` در این owner وجود ندارد.
- اگر config واقعاً از نظر bytes تغییر کند، `reload_required=true` گزارش می‌شود؛ اجرای تکراری بدون تغییر به `false` همگرا می‌شود. اجرای reload/restart توسط SOKNA انجام نمی‌شود.
- Windows Setup در حالت `reload_required` با exit code `20` موفقیت نهایی اعلام نمی‌کند. پس از reload خارجی و اجرای Repair، HTTPS صفحه ورود واقعی SOKNA باید probe شود؛ failure با code `21` جدا ثبت می‌شود.

## رفتار source-level
- Apache root و main config از خروجی خود Apache (`-V`) کشف می‌شوند، نه path حدسی.
- moduleهای لازم قبل از mutation بررسی می‌شوند.
- AppRoot/WebRoot و DataRoot باید جدا باشند؛ DataRoot در vhost deny می‌شود.
- include با marker صریح SOKNA مدیریت می‌شود و فایل هم‌نامِ بدون ownership marker overwrite نمی‌شود.
- candidate config قبل از mutation با `httpd -t` بررسی می‌شود.
- mutation main config/include دارای backup و rollback byte-level است.
- تکرار apply idempotent است و no-change/no-reload گزارش می‌دهد.
- Completion gate علاوه بر runtime self-check، local HTTPS login marker را بعد از reload واقعی لازم می‌داند.

## Evidence
- `tests/phase8b-apache-integration-contract.py`: static contract.
- `tests/phase8b-apache-integration-runtime.ps1`: Windows fixture برای no-mutation Validate، apply، idempotency، rollback و root separation.
- CI Windows باید runtime fixture را اجرا کند؛ این محیط Linux PowerShell/Apache runtime ندارد، بنابراین runtime PASS محلی ادعا نمی‌شود.
