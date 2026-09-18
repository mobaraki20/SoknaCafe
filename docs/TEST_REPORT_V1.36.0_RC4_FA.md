# گزارش آزمون Sokna 1.36.0-rc.4

## دامنه و محیط

- مبنا: Full Project Handover نسخه `1.36.0-rc.3`
- نوع تغییر: Bugfix / UI release hardening
- دیتابیس: بدون Migration
- محیط این ممیزی: Node.js 24 و Python 3.12 در دسترس؛ PHP، MySQL/MariaDB، Chromium و Playwright در دسترس نبودند.

## نتایج قابل اجرا

- JavaScript syntax: PASS برای تمام فایل‌های `assets/js/*.js` و `service-worker.js`.
- Python/static contracts: `146 PASS / 86 ENV_BLOCKED / 0 FAIL` در اجرای کامل ۲۳۲ فایل؛ موارد ENV_BLOCKED به PHP/Chromium/Playwright نیاز داشتند.
- Contract جدید `v1360-rc4-ui-handoff-contract.py`: PASS.
- `v1360-final-invariants.py`: PASS.
- قراردادهای UI conformance، UI language، Supply، Module inventory، Settlement، Accommodation، Updater و Recovery static: PASS.
- JavaScript syntax در Gate نهایی: PASS برای ۳۶ فایل؛ ۳۶ Contract مستقل Python نیز PASS شد و ۹ Contract وابسته به PHP/Browser به‌درستی `ENV_BLOCKED` ماندند؛ صفر شکست مستقل از محیط ثبت شد.
- Update archive رسمی شامل ۱۰۴ فایل تغییرکرده، صفر Delete، فونت محلی و سامانه آیکن است؛ Manifest/Hash/Size و هم‌ارزی ایستای `RC3 + Update == RC4 Source` در زمان ساخت نهایی بررسی شد.
- Full Source archive رسمی از همان Source و شامل ۵۶۳ فایل، با حذف مسیرهای محافظت‌شده ساخته و فهرست محتوا/CRC آن بررسی شد.
- فراخوان `csp_nonce()`: صفر مورد در Source جاری.
- Handler تغییر دلیل حذف ردیف فاکتور: یک Binding.
- فرم تحویل خرید: یک Owner پایه CSS.
- `!important`: از ۲۹۰ در RC3 به ۲۸۲ در RC4 کاهش یافت.
- Contrast محاسبه‌شده: `#626c66` روی `#f4f1eb` برابر 4.83:1 و `#5f7469` روی سفید برابر 5.01:1.
- فونت محلی Vazirmatn v33.003: PASS — WOFF2 معتبر با اندازه ۱۱۱۱۵۲ بایت و SHA-256 برابر `4e3fa217d38fdafc1fea4414ceb58ca5e662cf0ab5fa735a8c8c20e8b42cad92`؛ مجوز OFL نیز داخل پروژه است.

## NOT TESTED / UAT_REQUIRED

- PHP lint و تمام Unit/Contractهای نیازمند PHP.
- Fresh install و اجرای واقعی بسته منتشرشده `RC3 → RC4` از داخل Updater روی Clone مرجع؛ هم‌ارزی استاتیک بسته PASS است، اجرای PHP هنوز انجام نشده.
- MySQL/MariaDB واقعی و سناریوهای stale/new/partial/replay خرید.
- ماتریس Route × 320/390/412/768/1024/1180/1181/1366/1440/1920 در Chromium.
- Axe، Focus trap/restore، Virtual Keyboard و Touch روی دستگاه واقعی.
- Windows Print Agent و پرینتر فیزیکی، شامل Offline/Retry/Unknown/Recovery.
- Push و Sokna Center واقعی.
- FontConfig (`fc-scan`) خانواده Vazirmatn، وزن‌ها و Variable font را PASS کرد. Parse موازی با FontTools به‌دلیل نبود افزونه Python Brotli اجرا نشد؛ Render واقعی در Browser UAT باقی می‌ماند.

## تصمیم انتشار

بسته کامل RC4 و بسته Update با مجوز صریح Owner منتشر شدند. این انتشار، تحویل Artifact است و به‌معنای استقرار Production نیست؛ Promotion به Production تا اجرای تمام موارد UAT_REQUIRED در محیط مرجع مجاز نیست. نبود ابزار محیطی PASS محسوب نشده است.
