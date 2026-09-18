# گزارش آزمون Sokna 1.36.3

## Scope
- Printing Operational Console + Agent 6.1 distribution pin
- Quick Order Takeaway Stepper geometry
- Purchase Share action
- Release identity / updater `1.36.2 → 1.36.3`

## PASS — Source / Domain Gate
- Release identity، PHP lint و JavaScript syntax PASS.
- ۸۶ Contract/Domain blocker رسمی بدون Skip PASS شدند؛ شامل Supply، Finance، Inventory، Modules، UI contracts، Print API v4، Agent distribution و Fault/Load model.
- PHP runtime testهای تکمیلی cost projection، subscriber enrichment، Login security، Settlement signature و Print Template runtime PASS شدند.
- Printing-specific regressions شامل operational language، keyboard contract، ambiguity resolution، safe retry، reroute/cancel و edit-on-demand PASS شدند.

## PASS — Browser blocker matrix
- ۲۹ Browser blocker رسمی بدون Skip در Chromium PASS شدند.
- Quick Order در 320/360/390/412/768/1366/1440 بدون overflow/geometry regression PASS شد.
- Root gate جدید 1.36.3 تأیید کرد Takeaway Stepper در 320/390/412/768 content-width می‌ماند و Purchase Share در 320/390/768/1366 compact و touch-safe است.
- Printing Operations browser در 390/1024/1440 سه سطح Overview/Settings/Diagnostics، مقصد Edit-on-demand، mapping پرینتر، Advanced disclosure و عدم overflow را PASS کرد.
- Visual promotion تکمیلی Quick Order، Financial Filter و Activity PASS شد.

## UAT_REQUIRED — نه Failure
- Human-approved visual baselines برای برخی صفحات داده‌دار/حالت‌های خاص هنوز نیازمند مشاهده انسانی هستند.
- `pdo_mysql` در محیط ساخت حاضر نیست؛ DB runtime probe authenticated اجرا نشده است.
- URL/session staging برای HTTP critical-route probe ارائه نشده است.
- Update واقعی روی Host نصب‌شده کاربر، Service Worker پس از Update و دستگاه واقعی همچنان UAT می‌خواهد.
- Agent 6.1 روی چاپگر فیزیکی باید queue visibility، RTL، Kitchen/Bar/Customer، Paper Out/Offline، Spooler/Windows restart، network loss/recovery، 50 چاپ متوالی و soak را بگذراند. Pin شدن Release Asset به معنی تأیید چاپ فیزیکی نیست.

## Release package / Updater
- Canonical builder `tools/build-release.php` بسته `sokna-release-v2` را برای مسیر `1.36.2 → 1.36.3` با Updater Engine `1.5.3` ساخت.
- Manifest شامل ۳۹ فایل تغییرکرده، ۰ حذف و بدون Migration است.
- `tests/updater-release-acceptance.php` روی Clone تازه Full Project رسمی `1.36.2` PASS شد و نسخه نصب‌شده را به `1.36.3` رساند.
- پس از Update، ۵۸۴ فایل غیرمحافظت‌شده نصب‌شده با Source نهایی `1.36.3` بایت‌به‌بایت برابر بودند: missing=0, extra=0, diff=0.
- پس از ثبت Evidence نهایی، بسته Release دوباره Build شد و Acceptance نهایی روی Baseline رسمی `1.36.2` مجدداً اجرا و تأیید شد.
