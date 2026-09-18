# گزارش آزمون Sokna 1.36.1-rc.1

تاریخ ساخت: ۲۴ اوت ۲۰۲۶  
مبنا: Full Project منتشرشده `1.36.0`  
مقصد: `1.36.1-rc.1`  
Migration دیتابیس: ندارد

## PASS محلی

- قرارداد اختصاصی اصلاحات ریشه‌ای UI و هویت Release.
- قرارداد Cache و Content Digest آیکن‌ها.
- قراردادهای زبان رابط، همگرایی UI، سامانه آیکن و Inventory/Panel.
- مالکیت CSS و قراردادهای Layout استاتیک.
- Syntax همه JavaScriptهای تغییرکرده و Service Worker با Node.
- ساختار امن ZIP، Manifest، Hash و Size فایل‌های Update.
- هم‌ارزی بایتی Baseline `1.36.0` پس از اعمال Update با Source مقصد، با Missing/Extra/HashDiff صفر.

## UAT_REQUIRED / NOT TESTED

- PHP lint، تست‌های PHP و اجرای واقعی Updater؛ executable `php` در محیط ساخت موجود نیست.
- Fresh install و Update واقعی روی MariaDB/MySQL.
- Browser automation و Geometry واقعی؛ Playwright و Browser executable موجود نیستند.
- Cache/Service Worker روی گوشی واقعی، چاپ Desktop و چاپگر ۵۸/۸۰ میلی‌متر.
- استقرار، Health، Log و Smoke Test روی Production انجام نشده‌اند.

## نتیجه

Artifactهای Full Project و Update برای UAT قابل تحویل‌اند. این نتیجه مجوز Go-Live نیست؛ Promotion به نسخه نهایی منوط به اجرای موارد UAT_REQUIRED روی Clone و محیط مرجع است.
