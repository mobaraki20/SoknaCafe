# هنداور Source کاری اصلاح ریشه‌ای UI پس از 1.36.0

وضعیت: **PUBLISHED AS 1.36.1 FINAL**  
نسخه فایل‌ها: `VERSION.txt = 1.36.1`  
Migration دیتابیس: ندارد  
Artifactهای رسمی: Full Project و Update استاندارد از `1.36.0`.

الحاقیه Source: **PUBLISHED AS 1.36.1 FINAL**. فهرست «در انتظار خرید» در Mobile داخل Owner موجود `assets/css/inventory.css` متراکم شده است؛ Nested card، Gap و Padding اضافی حذف شده، Header و Actionهای هر قلم فشرده شده‌اند و Desktop/Workflow بدون تغییر مانده‌اند. اصلاحات بعد از RC همراه نسخه نهایی `1.36.1` منتشر شده‌اند.

در Quick Order نیز ردیف‌های دارای دو استپر از طریق state معنایی `has-takeaway-stepper` شناسایی می‌شوند. در Mobile، Copy تمام عرض ردیف اول را می‌گیرد و کنترل‌های Touch-safe در ردیف بعد قرار می‌گیرند و در عرض بسیار کم Wrap می‌شوند. این Patch فقط Renderer و Owner موجود `quick-order.css` را تغییر داده و منطق تعداد، بیرون‌بر، قیمت و Submit دست‌نخورده است.

شیت ابزارهای سه‌نقطه سراسری اکنون از Owner ژست مشترک `CafeUI.bindSwipeDismiss` استفاده می‌کند و Swipe-down فقط روی شیت باز موبایل فعال است. در مسیر اعلان دستگاه نیز `device-notifications.js` فقط متن `<span>` را به‌روزرسانی می‌کند؛ SVG موجود در دکمه دیگر با `textContent` حذف نمی‌شود. این اصلاحات نیز منتشرنشده‌اند.

Action سریع فراخوان در Push دیگر وضعیت میانی `accepted` تولید نمی‌کند. عنوان نمایشی آن «رسیدگی شد» است و Endpoint در همان Transaction فراخوان را `done` می‌کند، `active_table_guard` را آزاد می‌کند و `waiter_call.completed` را با Source اعلان ثبت می‌کند. شناسه عمومی `accept_call` فقط برای سازگاری با Service Worker و اعلان‌های کوتاه‌عمر نصب‌شده حفظ شده است؛ مسیر دو‌مرحله‌ای پذیرش/تکمیل داخل خود پنل بدون تغییر مانده است.

## درخواست و معیار پذیرش

این دور برای رفع ریشه‌ای موارد گزارش‌شده با اسکرین‌شات واقعی آغاز شد:

1. لمس دکمه سه‌نقطه نباید فقط Backdrop تار نشان دهد؛ Sheet باید داخل Viewport دیده شود، بسته شود و Focus/ARIA صحیح بماند.
2. همه آیکن‌های کنشی باید از Sprite مشترک استفاده کنند؛ Category Picker باید از حالت خط ساده و پراکنده به Tile همسان و خوانا تبدیل شود.
3. استپر سبد Quick Order باید دقیقاً سه خانه هم‌اندازه ۴۴ پیکسلی داشته باشد و `+`/`−` به‌صورت SVG وسط‌چین دیده شوند.
4. صفحه چاپ و طراحی قالب در Desktop نباید تمام عرض مانیتور را بکشند؛ Workspace مرجع حداکثر ۱۱۲۰ پیکسل است.
5. اصطلاح UI «درخواست خرید» است؛ حذف ردیف با سطل زباله انجام می‌شود، نوار ثبت روی ورودی‌ها نمی‌افتد و فهرست انتظار خرید Responsive است.

## تشخیص ریشه و تصمیم معماری

### منوهای سه‌نقطه

Sheet با `position: fixed` داخل Topbar دارای `backdrop-filter` باقی مانده بود، در حالی که Backdrop مستقیماً زیر `body` ساخته می‌شد. نتیجه می‌توانست Backdrop قابل‌مشاهده و Sheet خارج از Viewport باشد. Ownerهای `panel-shell.js` و `panel-menus.js` اکنون Popover را هنگام بازشدن به `body` Portal و هنگام بستن کنار Marker اصلی بازمی‌گردانند. CSS نمایش Popover ردیفی نیز دیگر به کلاس والد جداشده وابسته نیست.

### استپر

`quick-order.css` هم‌زمان عرض‌های ۱۲۲، ۱۰۶ و ۱۰۲ پیکسل و Media Query چهارمی را برای یک Component اعمال می‌کرد. قواعد منسوخ حذف شدند؛ فقط `.quick-order-line-qty` با عرض ۱۳۲ و ستون‌های `44px 44px 44px` باقی است. اندازه و Line box خود SVG نیز صریح شده است.

### آیکن و کش

`menu.js` و `menu-preview.js` برخلاف سایر Rendererها مسیر بدون نسخه `assets/icons/ui-sprite.svg#...` داشتند. هر دو از `window.SOKNA_ICON_SPRITE` استفاده می‌کنند و `index.php` این URL را از `asset()` تزریق می‌کند. `asset()` به Release label بسنده نمی‌کند و Digest کوتاه محتوای فایل را نیز به Query می‌افزاید؛ بنابراین فایل تغییرکرده Cache key تازه دارد. Refresh و سه‌نقطه دستی باقی‌مانده در `operator.js` هم به Sprite مشترک منتقل شدند.

### درخواست خرید و فهرست

زبان صفحه Staff/Buyer/Help/Module به «درخواست خرید» همسان شد. دکمه حذف ردیف از `trash` مشترک می‌آید. Sticky bar موبایل حذف شده و Submit در Flow فرم است. Waiting list روی Desktop شبکه دو ستونه و روی Mobile یک ستون است.

### چاپ

Owner عرض در `.panel-section-printing .panel-content` از ۱۳۸۰ به ۱۱۲۰ پیکسل تغییر کرد. چون `printing.php` و `print_templates.php` هر دو Section یکسان دارند، یک قانون Owner هر دو صفحه را پوشش می‌دهد.

## فایل‌های اصلی تغییرکرده

- `assets/js/panel-shell.js`
- `assets/js/panel-menus.js`
- `assets/css/panel-components.css`
- `assets/css/quick-order.css`
- `assets/css/inventory.css`
- `assets/js/menu.js`, `assets/js/menu-preview.js`, `assets/js/operator.js`
- `assets/icons/ui-sprite.svg`, `tools/build-ui-sprite.mjs`
- `assets/js/supply-needs.js`
- `includes/functions.php`, `includes/panel_layout.php`, `includes/modules.php`, `includes/help_topics.php`
- `index.php`, `operator/supply-needs.php`, `admin/purchases.php`, `waiter/index.php`
- `tests/v1361-ui-root-fixes-contract.py`, `tests/panel-header-cache.py`, `tests/v1360-ui-language-contract.py`

## شواهد آزمون فعلی

PASS:

- `python3 tests/v1361-ui-root-fixes-contract.py`
- `python3 tests/panel-header-cache.py`
- `python3 tests/v1360-ui-language-contract.py`
- `python3 tests/v1360-ui-conformance-contract.py`
- `python3 tests/v1360-icon-system-contract.py`
- `python3 tests/v1360-panel-ui-inventory-contract.py`
- `python3 tests/panel-css-ownership.py`
- `python3 tests/visual-layout-contracts.py`
- Node syntax check فایل‌های JS تغییرکرده

BLOCKED / هنوز PASS نیست:

- PHP lint و PHP unit: executable `php` در محیط موجود نیست.
- Browser/geometry automation: ماژول Playwright و Browser executable در محیط موجود نیست.
- MySQL/MariaDB، Staging احراز هویت‌شده، گوشی واقعی و Windows Print Agent اجرا نشده‌اند.

## UAT لازم پیش از هر انتشار

روی Clone واقعی 1.36.0 و با Cache/Service Worker فعال:

1. سه‌نقطه سراسری و حداقل یک Action Menu ردیفی در عرض‌های ۳۲۰، ۳۹۰، ۴۱۲ و Desktop باز/بسته شوند؛ Sheet و Backdrop هم‌زمان قابل‌دیدن باشند.
2. صفحه درخواست خرید با سه ردیف باز شود؛ سطل زباله، نبود Overlay نوار ثبت، ورود مقدار و Submit بررسی شود.
3. صفحه خرید با ۵+ قلم Waiting روی Mobile و Desktop بررسی شود.
4. Quick Order با تعداد ۱، ۲ و ۱۰ و آیتم دارای Note/Takeaway بررسی شود؛ `+` و `−` نباید Clip یا جابه‌جا شوند.
5. Category Picker و منوی مهمان پس از Reload کنترل‌شده با Sprite URL دارای Digest بررسی شوند.
6. چاپ و طراحی قالب روی عرض‌های ۱۳۶۶، ۱۴۴۰ و ۱۹۲۰ بررسی و یک چاپ واقعی ۵۸/۸۰ میلی‌متر انجام شود.

## قاعده این انتشار

این Source با شماره یکتای `1.36.1` منتشر می‌شود. Service Worker و اسناد هم‌هویت‌اند و Update رسمی از **Full Project منتشرشده 1.36.0** ساخته می‌شود. نتیجه ساخت و محدودیت‌های محیط در `docs/TEST_REPORT_V1.36.1_FINAL_FA.md` ثبت شده است.
