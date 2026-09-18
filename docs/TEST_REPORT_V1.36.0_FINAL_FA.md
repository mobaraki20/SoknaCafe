# گزارش آزمون Sokna 1.36.0

## دامنه و محیط

- مبنا: Full Project منتشرشده نسخه `1.36.0-rc.4`
- نوع تغییر: Final promotion / release packaging
- دیتابیس: بدون Migration
- محیط این ممیزی: Node.js 24 و Python 3.12 در دسترس؛ PHP، MySQL/MariaDB، Chromium و Playwright در دسترس نبودند.

## نتایج قابل اجرا

- JavaScript syntax: PASS برای تمام فایل‌های `assets/js/*.js` و `service-worker.js`.
- نتایج جامع RC4 در `TEST_REPORT_V1.36.0_RC4_FA.md` حفظ شده‌اند؛ Final هیچ تغییر رفتاری تازه‌ای نسبت به همان Source ندارد.
- Contract جدید `v1360-rc4-ui-handoff-contract.py`: PASS.
- `v1360-final-invariants.py`: PASS.
- قراردادهای UI conformance، UI language، Supply، Module inventory، Settlement، Accommodation، Updater و Recovery static: PASS.
- JavaScript syntax در Gate نهایی: PASS برای ۳۶ فایل؛ ۱۹ Contract کلیدی مستقل Python نیز PASS شد، یک Contract وابسته به PHP به‌درستی `ENV_BLOCKED` ماند و صفر شکست مستقل از محیط ثبت شد.
- Update archive نهایی شامل ۳۸ فایل تغییرکرده و صفر Delete است؛ Manifest/Hash/Size و هم‌ارزی ایستای `RC4 + Update == 1.36.0 Source` در ساخت نهایی PASS شد.
- Full Source archive نهایی از همان Source و شامل ۵۶۶ فایل، با حذف مسیرهای محافظت‌شده ساخته شد و فهرست محتوا/CRC آن PASS شد.
- فراخوان `csp_nonce()`: صفر مورد در Source جاری.
- Handler تغییر دلیل حذف ردیف فاکتور: یک Binding.
- فرم تحویل خرید: یک Owner پایه CSS.
- `!important`: از ۲۹۰ در RC3 به ۲۸۲ در نسخه نهایی کاهش یافته و نسبت به RC4 ثابت مانده است.
- Contrast محاسبه‌شده: `#626c66` روی `#f4f1eb` برابر 4.83:1 و `#5f7469` روی سفید برابر 5.01:1.
- فونت محلی Vazirmatn v33.003: PASS — WOFF2 معتبر با اندازه ۱۱۱۱۵۲ بایت و SHA-256 برابر `4e3fa217d38fdafc1fea4414ceb58ca5e662cf0ab5fa735a8c8c20e8b42cad92`؛ مجوز OFL نیز داخل پروژه است.

## NOT TESTED / UAT_REQUIRED

- PHP lint و تمام Unit/Contractهای نیازمند PHP.
- Fresh install و اجرای واقعی بسته `1.36.0-rc.4 → 1.36.0` از داخل Updater روی Clone مرجع؛ اجرای PHP هنوز انجام نشده.
- MySQL/MariaDB واقعی و سناریوهای stale/new/partial/replay خرید.
- ماتریس Route × 320/390/412/768/1024/1180/1181/1366/1440/1920 در Chromium.
- Axe، Focus trap/restore، Virtual Keyboard و Touch روی دستگاه واقعی.
- Windows Print Agent و پرینتر فیزیکی، شامل Offline/Retry/Unknown/Recovery.
- Push و Sokna Center واقعی.
- FontConfig (`fc-scan`) خانواده Vazirmatn، وزن‌ها و Variable font را PASS کرد. Parse موازی با FontTools به‌دلیل نبود افزونه Python Brotli اجرا نشد؛ Render واقعی در Browser UAT باقی می‌ماند.

## تصمیم انتشار

بسته کامل `1.36.0` و بسته Update از RC4 با مجوز صریح Owner منتشر می‌شوند. این انتشار، تحویل Artifact است و به‌معنای استقرار Production نیست؛ استقرار Production تا اجرای تمام موارد UAT_REQUIRED در محیط مرجع مجاز نیست. نبود ابزار محیطی PASS محسوب نشده است.
