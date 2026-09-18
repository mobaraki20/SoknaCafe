# گزارش آزمون Sokna 1.36.1

تاریخ ساخت: ۲۴ اوت ۲۰۲۶  
مبنا: Full Project منتشرشده `1.36.0`  
مقصد: `1.36.1`  
Migration دیتابیس: ندارد

## PASS محلی

- هویت یکسان `VERSION.txt`، Service Worker، Cache و Help metadata.
- قرارداد اختصاصی اصلاحات ریشه‌ای UI و اصلاحات پس از RC.
- قرارداد Cache و Content Digest آیکن‌ها.
- قرارداد Notification action، Swipe-down، آیکن پویا، Quick Order و فهرست انتظار خرید.
- Syntax همه JavaScriptهای Runtime و Service Worker با Node.
- ساختار امن ZIP، Manifest، Hash و Size فایل‌های Update.
- هم‌ارزی بایتی Baselineهای `1.36.0` و `1.36.1-rc.1` پس از اعمال مجازی Update با Source نهایی.

## UAT_REQUIRED / NOT TESTED

- PHP lint، تست‌های PHP و اجرای واقعی Updater؛ executable `php` در محیط ساخت موجود نیست.
- Fresh install و Update واقعی روی MariaDB/MySQL.
- Browser automation و Geometry واقعی؛ Playwright و Browser executable موجود نیستند.
- Cache/Service Worker روی گوشی واقعی، چاپ Desktop و چاپگر ۵۸/۸۰ میلی‌متر.
- استقرار، Health، Log و Smoke Test روی Production انجام نشده‌اند.

## نتیجه

Artifactهای Full Project و Update برای نصب کنترل‌شده و UAT قابل تحویل‌اند. انتشار Artifact به‌تنهایی اثبات Go-Live یا PASS محیط Production نیست.
