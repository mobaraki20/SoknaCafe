# SOKNA Windows Installer — Phase 8B

این پوشه owner سورس بسته ویندوز است. وضعیت فعلی **authoring/source candidate** است؛ تا Build/Install/Repair/Uninstall واقعی روی Windows و Gateهای WIN-01..WIN-09 انجام نشود Production-ready نیست.

## Toolchain
- WiX Toolset: `7.0.0` (pin شده در `.wixproj`ها)
- x64 / per-machine
- Burn + MSI
- پذیرش EULA/OSMF WiX در سورس خودکار نشده و باید در محیط Build/Release به‌صورت صریح انجام شود.

## Ownership
MSI فقط Installer Shell/cache، shortcut/ARP، Runtime Service Host و bundle داخلی Print Worker را ship می‌کند. Live app تحت مالکیت Updater است و MSI آن را harvest/repair نمی‌کند. مرجع: `docs/architecture-migration-r2/PHASE8B_PACKAGE_OWNERSHIP_FA.md`.

`prepare-shell-payload.ps1` یک `SoknaAppPayload.zip` نسخه‌دار برای New/Recover می‌سازد، اما آن ZIP cache/seed است و نباید توسط MSI مستقیم روی live AppRoot expand شود.

Requirementهای Windows در `runtime/windows/prerequisites.json` owner واحد دارند. `prerequisites/provider-candidate.json` انتخاب providerهای checkpoint جاری را ثبت می‌کند، اما تا اجرای `freeze-prerequisite-lock.ps1` روی Windows و review/commit شدن `release-lock.json`، Release Authority نیست. دانلود end-user همچنان ممنوع است و Setup فقط dependency نصب‌شده یا cache آفلاین verify‌شده را ارائه/validate می‌کند.

## هنوز باز
- اجرای Provider Freeze واقعی روی Windows و commit frozen `release-lock.json` همان release؛
- signing/timestamp production؛
- Windows CI artifact + install/repair/uninstall acceptance روی exact dev.39 head؛
- New/Recover واقعی با MariaDB + Apache/HTTPS و reboot health gate؛
- Update→Repair non-downgrade gate با predecessor واقعی؛
- physical cashier/printer/network UAT.

## Setup Host / Seed deployment
- `setup-host/Sokna.SetupHost.csproj` یک engine self-contained x64 و installer-owned است؛ Business secret را parse نمی‌کند و فقط plan نسخه‌دارِ حاوی مسیرها/گزینه‌های orchestration را می‌خواند.
- New/Recover به `deploy-seed.ps1` واگذار می‌شود: manifest/hash verification → استخراج امن در staging → canonical preflight-only → deploy به AppRoot خالی → canonical setup owner.
- Repair مستقیماً `setup-sokna.ps1` را با Service Host و Print Worker داخلی shell اجرا می‌کند و Seed قدیمی را روی Live App نمی‌ریزد.
- `SoknaSetupUi.exe` UI رسمی فارسی/RTL برای New/Recover/Repair است. این UI فقط ورودی‌ها را در پوشه ACL-private به config/plan تبدیل می‌کند و `SoknaSetupHost.exe` را elevated اجرا می‌کند؛ هیچ schema/restore/service/business mutation مستقلی ندارد.
## Diagnostics / Support
- Setup هر اجرا یک `session_id` مشترک و `support.zip` با allowlist صریح می‌سازد: summary، events، snapshot سرویس‌ها/نسخه/health و در صورت ارائه، نسخه redacted لاگ Burn/MSI.
- `collect-support.ps1` ابزار فقط‌خواندنی و installer-owned برای ساخت یک بسته پشتیبانی از آخرین Setup session و لاگ‌های اخیر `SOKNA-Cafe-Setup*.log` است. این ابزار کل ProgramData، backup، کلیدها، config یا queue database را archive نمی‌کند.
- Start Menu یک میانبر فارسی «گزارش پشتیبانی سکنا» برای همین collector دارد؛ اجرای آن نباید مرورگر یا PowerShell را elevated کند.

## RC full-stack evidence
پس از Provider Freeze و commit شدن `prerequisites/release-lock.json`، job دستی `windows-rc-full-stack` باید روی همان exact Git head اجرا شود. این job فقط release-engineering/disposable است و زنجیره واقعی New → Apache reload → Repair/HTTPS → Recovery Set → Recover را از artifactهای frozen می‌سنجد؛ End-user installer همچنان shared prerequisiteها را silent install نمی‌کند.
