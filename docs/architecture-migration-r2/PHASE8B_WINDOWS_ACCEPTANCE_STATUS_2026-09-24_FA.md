# Phase 8B — وضعیت پذیرش Windows Installer

تاریخ: 2026-09-24
مبنای این checkpoint: `1.36.4-dev.39` روی شاخه محلی `work/phase8b-closure-dev39`؛ implementation checkpoint `268957d42d281b0f677de5a32be6c41e36b108f5` (baseline ورودی `a579fcf`)
وضعیت کلی: **SOURCE HARDENED — WINDOWS PROVIDER FREEZE / RC / PACKAGE / UAT EVIDENCE PENDING**

این سند جایگزین معیارهای `WINDOWS_INSTALLER_ACCEPTANCE_FA.md` نیست؛ فقط وضعیت اجرای همان WIN-01..WIN-12 را ثبت می‌کند.

| Gate | وضعیت فعلی | شاهد / کار باز |
|---|---|---|
| WIN-01 Setup.exe/version/checksum | SOURCE READY / CI PENDING | WiX 7 MSI+Burn authoring، mapping نسخه، `SHA256SUMS.json` و `PACKAGE_MANIFEST.json` وجود دارد. Build واقعی فقط در Windows workflow با پذیرش صریح WiX EULA انجام می‌شود. Signing/timestamp production هنوز باز است. |
| WIN-02 Desktop/Start shortcuts | SOURCE READY / CI PENDING | shortcut سامانه، UI فارسی راه‌اندازی/تعمیر و میانبر پشتیبانی author شده‌اند؛ package runtime test وجود، target و Repair آن‌ها را روی Windows بررسی می‌کند. |
| WIN-03 Installed apps / Uninstall | SOURCE READY / CI PENDING | MSI/Burn ARP authoring موجود است؛ runtime package acceptance ثبت/نسخه/ناشر و uninstall را بررسی می‌کند. |
| WIN-04 Repair واقعی | SOURCE READY / CI PENDING | Runtime/Print Worker repair rollback و MSI `/fa` برای فایل/shortcut installer-owned تست شده در source؛ live payload و Business Data نباید تغییر کنند. Windows run هنوز لازم است. |
| WIN-05 Preflight قبل mutation | SOURCE IMPLEMENTED / HOSTED TESTS PARTIAL | OS/x64/Admin/disk/pending reboot/PHP+extensions/Apache/OpenSSL/port/DB target checks وجود دارد. Apache integration owner قبل از mutation discovery/module/syntax/root-separation را Validate می‌کند. PHP DB runtime tests در workspace فعلی به‌علت نبود PDO SQLite/MySQL قابل اجرا نیستند. Windows hosted CI لازم است. |
| WIN-06 prerequisites / offline | PROVIDERS PINNED / WINDOWS FREEZE PENDING | `provider-candidate.json` برای dev.39 چهار artifact PHP/Apache/MariaDB/VC++ را با HTTPS source و expected SHA pin می‌کند. `freeze-prerequisite-lock.ps1` روی Windows exact size/version و signer policy را از binary واقعی می‌گیرد و evidence + frozen lock review candidate می‌سازد. End-user Setup/Runtime دانلود یا نصب خودکار dependency ندارد. lock هنوز authority نشده تا Windows Freeze PASS و روی exact release head commit شود. |
| WIN-07 incomplete install / rollback | SOURCE HARDENED / CI PENDING | Service Host و Print Worker rollback/fail injection، Seed rollback محدود و Apache managed-config rollback وجود دارد. `deploy-seed.ps1` اکنون exit `20/21/3010` را بعد از business commit بدون collapse به خطای عمومی به Host/UI منتقل می‌کند؛ Repair بعدی باید HTTPS login marker را probe کند. package-level fail injection/reboot scenarios روی Windows هنوز لازم است. |
| WIN-08 unified logging | SOURCE IMPLEMENTED / CI PENDING | `session_id` مشترک، summary/events، stable Burn/MSI log naming و redaction وجود دارد؛ Windows package run باید خروجی واقعی را اثبات کند. |
| WIN-09 one-step support package | SOURCE IMPLEMENTED / CI PENDING | `support.zip` allowlisted شامل summary/events/components و لاگ‌های redacted در صورت دسترسی است. `collect-support.ps1` آخرین Setup session و لاگ‌های اخیر installer را یک‌مرحله‌ای جمع می‌کند؛ Start Menu میانبر فارسی دارد. |
| WIN-10 preserve data on uninstall | SOURCE TEST AUTHORED / CI PENDING | package acceptance markerهای updater-owned live payload و business data را قبل/بعد MSI/Burn uninstall hash-check می‌کند. پاک‌سازی Business Data در MSI author نشده است. |
| WIN-11 Update→Repair non-downgrade | PARTIAL / CI PENDING | ownership contract و Repair marker/hash test مانع overwrite شدن live payload می‌شوند. سناریوی کامل «Updater واقعی N→N+1 سپس Windows Repair» هنوز باید در hosted acceptance اجرا شود. |
| WIN-12 Recover | FULL-STACK HARNESS AUTHORED / WINDOWS RUN PENDING | Seed deployment + canonical `tools/setup-machine.php` + empty-target/exact-version/fresh identity contract موجود است. RC harness از frozen PHP/Apache/MariaDB/VC bundle، New و Recovery Set و Recover روی DB خالی و identity جدید را end-to-end اجرا می‌کند؛ اجرای واقعی Windows هنوز لازم است و takeover 8C جداست. |

## کارهای source-level بسته‌شده در این batch
- Support diagnostics از دو فایل مینیمال به snapshot ساخت‌یافته service/version/Print health ارتقا یافت؛ Durable queue/config/backup/private key به archive وارد نمی‌شوند.
- `collect-support.ps1` به installer shell اضافه شد؛ فقط فایل‌های allowlisted را کپی/Redact می‌کند.
- Burn/MSI log naming پایدار در WiX authoring ثبت شد.
- Start Menu action فارسی «گزارش پشتیبانی سکنا» اضافه شد و بدون elevation اجرا می‌شود.
- `phase8b-windows-installer-package-runtime.ps1` اضافه شد: MSI install → ARP/shortcut → حذف عمدی resource → Repair → عدم downgrade Live/Data → uninstall → Burn install/uninstall.
- Windows installer workflow قبل از upload artifact باید acceptance بالا را اجرا کند.
- `SoknaSetupUi.exe` UI رسمی فارسی/RTL و UI-only برای New/Recover/Repair است؛ HTTPS/Apache preflight در UI عملیاتی اجباری است، AppRoot قابل تغییر است، overlap با DataRoot قبل از elevation رد می‌شود و secretها فقط در ACL-private temp نوشته می‌شوند.
- Apache integration owner به‌صورت bounded اضافه شده: managed vhost/include، candidate syntax check، rollback و `reload_required`; lifecycle Apache همچنان external است و SOKNA start/stop/restart انجام نمی‌دهد.
- Completion semantics سخت‌تر شد: write شدن config Apache success نیست؛ code `20` نیاز به reload خارجی را اعلام می‌کند و code `21` شکست health واقعی HTTPS را از آن جدا می‌کند.
- WIN-06 دو مرحله‌ای شد: Provider Candidate non-authoritative + Windows Provider Freeze evidence. فقط lock بازبینی/commit‌شده می‌تواند offline bundle بسازد؛ Bundle قبل از ورود به shell با frozen lock، exact metadata/hash/size/signature policy و `VERSION.txt` همان release تطبیق داده می‌شود.
- Package acceptance یک synthetic dev.38 predecessor MSI می‌سازد تا major-upgrade به dev.39 و حفظ updater-owned live payload/business data در hosted Windows CI واقعاً اجرا شود؛ این test جایگزین Updater migration واقعی نیست.
- `phase8b-windows-rc-full-stack.ps1` و job اختیاری `windows-rc-full-stack` اضافه شدند؛ job بدون frozen `release-lock.json` commit‌شده اصلاً شروع نمی‌شود و فقط روی runner disposable اجازه mutation سراسری Windows دارد.
- full-stack RC از MariaDB MSI فریز‌شده یک service موقت می‌سازد، PHP TS/Apache فریز‌شده را واقعاً configure می‌کند، New را تا exit 20 می‌برد، Apache را به‌عنوان owner خارجی فعال می‌کند، Repair/HTTPS را می‌بندد، Recovery Set می‌سازد و Recover را روی target جدید تکرار می‌کند.

## مواردی که عمداً هنوز Complete اعلام نمی‌شوند
1. اجرای `windows-prerequisite-freeze` روی dev.39، بررسی Authenticode/size/hash واقعی و commit کردن frozen `release-lock.json` روی همان exact release head؛
2. build و UAT واقعی Setup UI فارسی/RTL روی Windows، شامل DPI/نمایشگر صندوق/UAC و plan-file ACL؛
3. signing/timestamp production؛
4. build/install/repair/uninstall واقعی WiX روی Windows CI head جدید؛
5. اجرای `windows-rc-full-stack` روی exact head پس از commit شدن frozen release lock و ثبت evidence New/Repair/Recover؛
6. Update→Repair با Updater واقعی و cache-loss scenarios؛
7. Apache config/reload + cashier PC / reboot / printer / network field UAT؛
8. Phase 8C takeover.
9. PHP 8.2 security lifecycle: qualification جداگانه PHP 8.3 باید پیش از 2026-12-31/قبل از release بلندمدت بسته شود؛ این migration عمداً داخل Phase 8B closure انجام نمی‌شود.

## قانون ادامه
هیچ‌کدام از موارد `CI PENDING` یا `UAT PENDING` صرفاً به‌دلیل وجود source/test به PASS تبدیل نشوند. Release/Production-ready فقط با artifact واقعی، SHA، run reference و evidence همان head قابل اعلام است.
