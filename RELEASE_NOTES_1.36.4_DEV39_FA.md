# SOKNA Cafe 1.36.4-dev.39 — Phase 8B Closure Candidate

تاریخ: 2026-09-24
وضعیت: **Engineering checkpoint / Windows RC source candidate — Production-ready نیست**
مبنای مستقیم: `1.36.4-dev.38` lineage + checkpoint محلی `a579fcf21878413998deb7158eb9a5a29fd8d32a`.

## هدف نسخه
`dev.39` اولین هویت یکتای قابل نصب پس از checkpointهای Phase 8B/R2 روی lineage `dev.38` است. این bump برای جلوگیری از ساخت چند artifact قابل نصب با یک شماره نسخه انجام شد؛ هیچ ادعای UAT/Production از روی شماره نسخه ایجاد نمی‌کند.

## تغییرات این checkpoint
- مسیر WIN-06 از template عمومی به **Provider Candidate → Freeze Evidence → Frozen Release Lock → Verified Offline Bundle** تبدیل شد.
- providerهای candidate ویندوز برای PHP/Apache/MariaDB/VC++ با URL دقیق HTTPS و SHA-256 مورد انتظار ثبت شدند؛ candidate خودش authority انتشار نیست.
- `freeze-prerequisite-lock.ps1` فقط در release engineering اجرا می‌شود، باینری واقعی را hash می‌کند، size دقیق را از خود فایل می‌گیرد، ProductVersion لازم را استخراج می‌کند و policy امضای اجباری را بررسی می‌کند.
- Windows CI یک job مستقل برای تولید `release-lock.json` و evidence قابل بازبینی دارد. lock تولیدشده باید پیش از RC نهایی review و روی همان release commit ثبت شود.
- End-user Setup همچنان dependency را silent download/install نمی‌کند؛ shared dependency ownership برای PHP/Apache/OpenSSL/MariaDB/VC Runtime external باقی می‌ماند.
- OpenSSL جداگانه به‌عنوان artifact جدید تحمیل نشده است؛ Setup می‌تواند executable معتبر داخل Apache مورد تأیید یا OpenSSL از پیش نصب‌شده را استفاده کند.
- `deploy-seed.ps1` اکنون exit codeهای canonical پس از commit (`20` reload-required، `21` HTTPS-health، `3010` reboot-required) را بدون تبدیل به خطای عمومی به Setup Host/UI برمی‌گرداند.
- Windows RC full-stack acceptance جدید فقط روی ماشین disposable اجرا می‌شود و از frozen prerequisite bundle واقعی زنجیره `New → Apache reload → Repair/HTTPS → Recovery Set → Recover → Repair/HTTPS` را با MariaDB Windows service واقعی اثبات می‌کند.

## ریسک lifecycle ثبت‌شده
PHP 8.2 برای بستن Phase 8B عمداً ثابت مانده تا closure نصب با migration runtime مخلوط نشود. با این حال شاخه PHP 8.2 فقط تا **2026-12-31** پشتیبانی امنیتی دارد؛ qualification رسمی PHP 8.3 باید به‌عنوان کار جداگانه پیش از release بلندمدت/پیش از آن تاریخ بسته شود.

## معیار خروج از این checkpoint
این نسخه فقط وقتی Windows RC قابل پذیرش است که حداقل evidence زیر روی exact Git head ثبت شود:
1. Provider Freeze روی Windows با hash/size/signature واقعی PASS؛
2. frozen `release-lock.json` بازبینی و commit شود؛
3. MSI/Burn build روی Windows PASS؛
4. Install/Repair/Uninstall package acceptance PASS؛
5. New/Recover با MariaDB/Apache/HTTPS واقعی و reboot/health evidence اجرا شود؛
6. signing/timestamp وضعیت مشخص داشته باشد؛
7. UAT صندوق/چاپگر/شبکه همچنان جداگانه ثبت شود.

## مواردی که این Release Note ادعا نمی‌کند
- Production-ready بودن؛
- PASS بودن Windows CI قبل از run واقعی؛
- خودکار نصب‌کردن prerequisiteها؛
- تکمیل Phase 8C Takeover؛
- تکمیل field UAT چاپگر/صندوق/شبکه.
