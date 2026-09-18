# هنداور توسعه Sokna 1.36.1-rc.1

## وضعیت انتشار

- نسخه مقصد: `1.36.1-rc.1`
- Baseline رسمی: Full Project منتشرشده `1.36.0`
- Updater Engine: `1.5.3`
- Migration دیتابیس: ندارد
- نوع تحویل: Full Project + Update استاندارد + SHA256SUMS
- استقرار Production: انجام نشده

## مسئله و اصلاح ریشه‌ای

### منوی سه‌نقطه

ریشه خطا قرارگرفتن Sheet ثابت داخل والد دارای Blur/Containing Block و Backdrop در سطح `body` بود. `panel-shell.js` و `panel-menus.js` اکنون Popover را هنگام بازشدن به `body` Portal و هنگام بسته‌شدن کنار Marker اصلی بازمی‌گردانند. Backdrop، Focus و `aria-expanded` همچنان یک Owner دارند. قانون CSS وابسته به والد جداشده حذف شده است.

### آیکن و Cache

همه Rendererهای درگیر از Sprite مشترک استفاده می‌کنند. مسیر بدون نسخه در `menu.js` و `menu-preview.js` حذف شده و `index.php` URL تولیدشده توسط `asset()` را تزریق می‌کند. `asset()` علاوه بر نسخه، Digest کوتاه محتوای CSS/JS/SVG را وارد Query می‌کند. Glyph دستی Refresh و سه‌نقطه نیز حذف شده و Sprite دارای Revision صریح Category است.

### استپر Quick Order

تعریف‌های متداخل ۱۲۲/۱۰۶/۱۰۲ پیکسلی حذف و فقط یک Grid با عرض ۱۳۲ و سه ستون ۴۴ پیکسلی نگه داشته شد. SVGهای مثبت و منفی اندازه و Line box ثابت دارند.

### درخواست خرید

اصطلاح رابط به «درخواست خرید» همسان شد. حذف ردیف با آیکن `trash` انجام می‌شود. نوار Submit موبایل در Flow عادی فرم است و Waiting list روی Desktop دو ستون و روی Mobile یک ستون دارد.

### چاپ Desktop

مالکیت عرض صفحات چاپ و طراحی قالب در `.panel-section-printing .panel-content` متمرکز و `max-width:1120px` شده است.

## فایل‌های Runtime اصلی تغییرکرده

- `assets/js/panel-shell.js`, `assets/js/panel-menus.js`
- `assets/css/panel-components.css`, `assets/css/quick-order.css`, `assets/css/inventory.css`
- `assets/js/menu.js`, `assets/js/menu-preview.js`, `assets/js/operator.js`, `assets/js/supply-needs.js`
- `assets/icons/ui-sprite.svg`, `tools/build-ui-sprite.mjs`
- `includes/functions.php`, `includes/panel_layout.php`, `includes/modules.php`, `includes/help_topics.php`
- `index.php`, `operator/supply-needs.php`, `admin/purchases.php`, `waiter/index.php`
- `VERSION.txt`, `service-worker.js`

## قراردادهای ضدبازگشت

- `tests/v1361-ui-root-fixes-contract.py`
- `tests/panel-header-cache.py`
- `tests/v1360-ui-language-contract.py`
- `tests/v1360-icon-system-contract.py`
- `tests/panel-css-ownership.py`
- `tests/visual-layout-contracts.py`

نتیجه و محدودیت‌های آزمون در `docs/TEST_REPORT_V1.36.1_RC1_FA.md` ثبت شده‌اند.

## UAT ایجنت بعدی

1. بسته Update را روی Clone واقعی `1.36.0` از `/admin/update/` اجرا کند.
2. هم‌هویتی `VERSION.txt`، Service Worker و Cache را بررسی کند.
3. سه‌نقطه سراسری و ردیفی را در عرض‌های ۳۲۰، ۳۹۰، ۴۱۲ و Desktop باز و بسته کند.
4. Quick Order را با تعداد ۱، ۲ و ۱۰، Note و بیرون‌بر بررسی کند.
5. Category Picker و منوی مهمان را پس از پاک‌سازی کنترل‌شده Cache بررسی کند.
6. فرم درخواست خرید سه‌ردیفی و Waiting list دارای ۵+ قلم را روی Mobile/Desktop بررسی کند.
7. صفحات چاپ و طراحی قالب را در عرض‌های ۱۳۶۶، ۱۴۴۰ و ۱۹۲۰ و چاپ واقعی ۵۸/۸۰ میلی‌متر آزمایش کند.
8. فقط پس از PASS شدن PHP/DB/Browser/Print و Promotion Gate، برای نسخه نهایی تصمیم بگیرد.
