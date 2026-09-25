# Phase 8B — Windows Package / Updater Ownership Contract

تاریخ: 2026-09-24
وضعیت: **ACTIVE DESIGN CONTRACT — Windows build/UAT pending**

## مسئله
Updater جاری فایل‌های Live برنامه را مستقیماً در App Root با Restore Point، migration، Health Check و rollback مدیریت می‌کند. اگر MSI همان فایل‌های Live را Component مالک خود بداند، Windows Installer Repair می‌تواند پس از Update، payload قدیمی MSI را دوباره روی نسخه جدید بنویسد یا فایل حذف‌شده توسط Update را برگرداند. این رفتار ممنوع است.

## تصمیم مالکیت
### MSI / Burn owner
MSI فقط لایه نصب پایدار را مالک است:
- Setup/Bootstrap shell و فایل‌های لازم برای اجرای Setup/Repair؛
- Runtime Service Host از پیش build‌شده؛
- build bundle داخلی Print Worker و provenance/manifest آن؛
- icon و shortcutهای Desktop/Start؛
- ARP / Installed apps registration؛
- versioned **seed/cache package** که برای New/Recover استفاده می‌شود؛
- package manifest/checksum و فایل‌های diagnostics مربوط به نصب.

### Updater owner
Updater تنها owner فایل‌های **Live application payload** پس از استقرار اولیه است:
- PHP/JS/CSS/assets/routes/includes/modules و `VERSION.txt` در App Root فعال؛
- updater engine versioned طبق قرارداد خودش؛
- migration/restore point/health/rollback مربوط به نسخه برنامه.

MSI نباید Live application tree را با `<Files ...>` harvest کند یا آن را در Repair بازنویسی کند.

### Business data owner
هیچ‌کدام از MSI Repair/Uninstall نباید داده‌های mutable زیر را به‌صورت پیش‌فرض پاک یا regenerate کنند:
- Database business data؛
- `app.key` و installation identity؛
- Recovery metadata؛
- Print Worker durable queue/outbox/config/secret؛
- uploads، backupها، logs و Public pairing state.

Uninstall بسته ویندوز می‌تواند فایل‌های Installer-owned و service registration را حذف کند، اما پاک‌سازی Business Data باید عملیات جدا، صریح و تأییدشده باشد.

## New / Recover
نسخه Release باید یک Seed Package نسخه‌دار و hash شده بسازد. Setup Owner فقط در حالت New/Recover و پس از Preflight مجاز است Seed را به App Root خالی/مجاز deploy کند. پس از deploy، canonical `tools/setup-machine.php` owner نصب business/schema/restore باقی می‌ماند.

در Recover، Seed باید دقیقاً با نسخه Backup/Recovery Set سازگار باشد. هیچ «restore روی کد نسخه تصادفی» مجاز نیست.

## Repair
Repair دو کلاس resource دارد:
1. **Installer-owned**: MSI می‌تواند آن‌ها را repair کند (shortcut/icon/setup shell/runtime host/internal worker bundle cache).
2. **Updater-owned live app**: MSI حق overwrite ندارد.

اگر live app خراب یا ناقص باشد، Repair باید ابتدا Version/manifest جاری را بخواند. بازگرداندن seed قدیمی روی نسخه‌ای که Updater جلوتر برده ممنوع است. recovery باید از LKG/Restore Point یا یک package هم‌نسخه و verified انجام شود.

## Upgrade
Major Upgrade بسته Windows می‌تواند Installer-owned shell/cache را جلو ببرد، اما activation برنامه Live همچنان از قرارداد Updater/Setup Owner عبور می‌کند. Bundle/MSI و updater نباید دو state-machine مستقل برای migration برنامه بسازند.

## Print Worker
Print Worker component داخلی SOKNA Local است. MSI/Burn می‌تواند build output آن را ship/cache کند و Setup/Repair lifecycle سرویس آن را مدیریت کند، اما **هیچ Pagent MSI/Setup مستقل در Chain مجاز نیست**.

## Web stack / prerequisites
Local Runtime مالک lifecycle Apache/PHP نیست. Windows Setup فقط prerequisiteهای web stack/PHP/DB/OpenSSL را پیش از mutation تشخیص و validate می‌کند. اگر Release در آینده prerequisite package رسمی برای web stack ارائه کند، آن package باید owner مستقل و versioned داشته باشد و Burn فقط orchestration آن را انجام دهد؛ Runtime Service نباید Apache/PHP را supervise کند.

## Gates
- `tests/phase8b-package-ownership-contract.py` باید هر authoring نهایی MSI/Burn را از harvest کردن live app و chain کردن Pagent مستقل منع کند.
- Windows CI باید MSI/Burn build، Install/Repair/Uninstall، ARP، shortcut و hash/manifest artifact را اثبات کند.
- Update→Repair→Health سناریوی اجباری است: پس از Update برنامه به نسخه N+1، Repair بسته Windows نسخه shell نباید فایل‌های Live را به N برگرداند.
