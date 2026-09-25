# Phase 8B — Seed Deployment / Setup Host Checkpoint

تاریخ: 2026-09-24
وضعیت: **SOURCE CHECKPOINT — Windows build/runtime acceptance pending**

## مسئله بسته‌شده در source
MSI مالک Live App نیست، بنابراین New/Recover به owner جدا برای deploy اولیه Seed نیاز دارد؛ در عین حال این owner حق ندارد Business/Schema/Restore state-machine دوم بسازد.

## تصمیم و پیاده‌سازی
- `setup-sokna.ps1` حالت `PreflightOnly` دارد؛ Mode واقعی New/Recover حفظ می‌شود ولی mutation عمداً متوقف می‌شود.
- `deploy-seed.ps1` فقط New/Recover را می‌پذیرد و Repair را قبول نمی‌کند.
- shell manifest و SHA-256 payloadها قبل از استفاده verify می‌شوند.
- ZIP در staging خصوصی با path-traversal protection استخراج می‌شود.
- نسخه Seed باید با `payload-manifest.json` برابر باشد.
- canonical `tools/setup-machine.php` / runtime / schema باید داخل Seed موجود باشند.
- preflight کامل روی staging قبل از ساخت/پرکردن AppRoot اجرا می‌شود.
- AppRoot درست قبل از deploy دوباره باید خالی باشد.
- mutation واقعی دوباره از `runtime/windows/setup-sokna.ps1` و `tools/setup-machine.php` عبور می‌کند.
- failure قبل از markerهای business فقط pathهای متعلق به همان Seed را rollback می‌کند؛ محتوای ناشناخته کورکورانه حذف نمی‌شود.
- `SoknaSetupHost.exe` engine نسخه‌دار و elevated است؛ plan فقط مسیر/گزینه orchestration دارد و Host هیچ DB password/admin password/shared secret را parse نمی‌کند.
- Repair به shell-owned Service Host و internal Print Worker اشاره می‌کند و Seed را روی Live App نمی‌ریزد.

## Gates محلی
- Phase 8B Windows setup contract: 19/19 PASS
- Package ownership contract: 11/11 PASS
- WiX authoring contract: 12/12 PASS
- Seed deployment contract: 14 checks PASS
- Setup Host contract: 13/13 PASS
- Phase 1 Runtime foundation: PASS
- GitHub Actions YAML parse: PASS
- `git diff --check`: PASS

## Blockerهای محیط فعلی
- `dotnet`: unavailable
- `pwsh`: unavailable
- بنابراین build واقعی `SoknaSetupHost.exe`، syntax/runtime PowerShell روی Windows و MSI/Burn build هنوز NOT_RUN هستند.

## بازمانده Phase 8B
- prerequisite/version/hash/offline contract برای PHP/Web Server/MariaDB/OpenSSL؛
- تعیین قطعی AppRoot/WebRoot و اتصال web server بدون تصاحب lifecycle آن توسط Runtime؛
- UI فارسی Setup که plan/private config را بسازد؛
- Burn wiring برای اجرای flow مناسب و rollback/log collection؛
- Signing/timestamp؛
- WIN-01..WIN-12 واقعی روی Windows clean/upgrade/repair/recover؛
- Update→Repair non-downgrade؛
- physical printer/cashier UAT.

### One-owner correction
Setup Host canonical فقط `SoknaSetupHost.exe` است. PowerShell Host موازی مجاز نیست؛ PowerShell فقط worker scriptهای `deploy-seed.ps1` و `setup-sokna.ps1` را فراهم می‌کند و C# Host exact shell manifest را پیش از orchestration verify می‌کند.

## Hardening addendum — same checkpoint line
- Repair اکنون `runtime/windows/setup-sokna.ps1` را از **Live App فعلی** اجرا می‌کند؛ shell فقط Service Host و Print Worker installer-owned را می‌دهد. بنابراین Updater-owned application logic با کپی قدیمی shell جایگزین نمی‌شود.
- `setup_config_file` و `recovery_passphrase_file` در canonical Windows setup باید ACL خصوصی داشته باشند؛ Allow برای Everyone / Authenticated Users / BUILTIN\\Users fail-closed است.
- C# Setup Host دقیقاً یک correlation session ID می‌سازد و آن را با `SOKNA_SETUP_SESSION_ID` به preflight/final setup منتقل می‌کند؛ گزارش‌های canonical همان شناسه را مصرف می‌کنند.
- duplicate session-owner در Host با contract جلوگیری می‌شود؛ Host همچنان هیچ business secret را parse نمی‌کند.

### Local regression after hardening
- PHP lint: 287/287 PASS
- JavaScript syntax: 36/36 PASS
- Core unit: 103/103 PASS
- Phase7R internal Print Worker: 30/30 PASS
- Phase8A recovery identity: 12/12 PASS
- Phase8B Setup Host: 14 checks PASS
- Phase8B Windows Setup: 21 checks PASS
- Phase8B Seed Deployment: PASS
- Package Ownership: 11/11 PASS
- WiX authoring: 12/12 PASS
- final invariants / updater / recovery current contracts: PASS

`dotnet` و PowerShell در workspace لینوکسی حاضر نیستند؛ build/runtime واقعی Setup Host و Windows installer هنوز فقط باید در Windows CI/UAT اثبات شود و در این سند PASS ادعا نمی‌شود.
