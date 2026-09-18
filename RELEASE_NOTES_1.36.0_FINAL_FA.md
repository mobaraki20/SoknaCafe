# یادداشت انتشار Sokna 1.36.0

`1.36.0` نسخه نهایی خط انتشار 1.36 است. این Promotion از `1.36.0-rc.4` انجام می‌شود و هیچ Migration دیتابیس یا تغییر تازه‌ای در Business Logic ندارد.

## محتوای تثبیت‌شده

- فونت محلی رسمی Vazirmatn v33.003 همراه مجوز OFL.
- سامانه یکپارچه ۱۰۵ آیکن Tabler Outline و نگاشت معنایی ۵۶ دسته غذا و نوشیدنی.
- اصلاح نمایش استپرها، دکمه‌های حذف، منوی عملیات و کنترل‌های Quick Order.
- گردش چندقلمی درخواست خرید، صف در انتظار خرید و وضعیت‌های تحویل.
- اصلاحات Accessibility، Focus/Inert، Responsive layout و خطاهای بازیابی ۴۰۴/۴۰۹.
- حفظ قراردادهای مالی، Settlement، Inventory Ledger، Print Agent و فراخوان گارسون.

## مسیر ارتقا

`1.36.0-rc.4 → 1.36.0` با Updater Engine `1.5.3` و بدون Migration دیتابیس.

## فایل‌های انتشار

- `Sokna-1.36.0-Final-Full-Project.zip`
- `Sokna-1.36.0-rc.4-to-1.36.0-Final-Update.zip`

## محدودیت محیط تحویل

ساختار ZIP، Manifest، Hash/Size و هم‌ارزی بایتی بسته‌ها بررسی می‌شود. PHP، MariaDB/MySQL، Chromium، دستگاه واقعی و Printer در محیط ساخت حاضر نیستند؛ تست‌های وابسته به آن‌ها `UAT_REQUIRED` باقی می‌مانند و انتشار Artifact به‌معنای استقرار Production نیست.
