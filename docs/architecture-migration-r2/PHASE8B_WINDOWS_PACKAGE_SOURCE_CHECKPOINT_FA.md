# Phase 8B — Windows Package Source Checkpoint

تاریخ: 2026-09-24
وضعیت: **SOURCE/AUTHORING CHECKPOINT — Windows build/install/UAT هنوز انجام نشده است**

## هدف این checkpoint
این checkpoint شکاف source-level بسته ویندوز را می‌بندد بدون اینکه مالکیت Live App را از Updater بگیرد یا Runtime را مالک Apache/PHP کند.

## موارد پیاده‌سازی‌شده
- Preflight پیش از mutation برای Windows/x64، pending reboot، فضای دیسک، PHP 8.2+x64 و extensionهای لازم، OpenSSL، Runtime Service Host، Print Worker داخلی، web-server probe و مالکیت پورت HTTPS.
- pending reboot با outcome جدا و exit code `3010`.
- قرارداد صریح Package Ownership: MSI فقط shell/cache و resourceهای installer-owned را مالک است؛ Live App تحت مالکیت Updater باقی می‌ماند.
- WiX Toolset `7.0.0` source authoring برای MSI x64/per-machine و Burn bundle.
- Desktop/Start shortcut و ARP/Installed Apps authoring.
- Seed/cache versioned برای New/Recover بدون harvest کردن Live App در MSI.
- CI opt-in برای build MSI/Burn؛ build فقط با workflow dispatch و پذیرش صریح `wix7` EULA اجرا می‌شود.
- هیچ Pagent/Print Agent مستقل در Burn chain وجود ندارد؛ Print Worker component داخلی SOKNA است.

## Gates محلی
- `phase8b-windows-setup-contract.py`: 19/19 PASS
- `phase8b-package-ownership-contract.py`: 11/11 PASS
- `phase8b-wix-authoring-contract.py`: 12/12 PASS
- GitHub Actions YAML parse: PASS
- `git diff --check`: PASS

## مواردی که عمداً PASS اعلام نشده‌اند
- Build واقعی WiX روی Windows/.NET سازگار.
- Install / Upgrade / Repair / Uninstall واقعی MSI/Burn.
- WIN-01..WIN-09 acceptance کامل.
- Code signing و timestamp production.
- prerequisite chain رسمی و hash/version contract برای PHP/Web Server/DB/OpenSSL.
- Setup UX/Host نهایی برای New/Recover و deploy امن Seed به AppRoot.
- Update → Repair → Health non-downgrade acceptance.
- Windows Service/Spooler/physical printer UAT.

## تصمیم بعدی
مرحله بعد باید یک Setup Host/Orchestrator installer-owned ایجاد کند که:
1. هیچ business/schema logic جدیدی نسازد؛
2. پس از preflight و فقط برای New/Recover، Seed verified را به AppRoot مجاز deploy کند؛
3. سپس canonical `runtime/windows/setup-sokna.ps1` / `tools/setup-machine.php` را فراخوانی کند؛
4. secretها را از فایل‌های ACL-protected منتقل کند، نه command line؛
5. در failure قبل/بعد از business commit تفاوت را حفظ کند؛
6. Repair هرگز Live App جدیدترِ Updater را با Seed قدیمی downgrade نکند.

این checkpoint Production-ready نیست و صرف وجود `Setup.exe` در CI در آینده نیز به‌تنهایی برای Complete کردن Phase 8B کافی نیست.
