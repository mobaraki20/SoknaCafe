# هنداور توسعه Sokna 1.36.0-rc.4

## الحاقیه نهایی منتشرشده — یکپارچه‌سازی آیکن‌ها

این تغییر پس از Checkpoint RC4 و به دستور Owner روی Source کاری اعمال شد و اکنون داخل بسته کامل RC4 و بسته رسمی `RC3 → RC4` قرار دارد.

- مالک واحد آیکن‌ها `assets/icons/ui-sprite.svg` است: ۱۰۵ شناسه پایدار، همگی Tabler Icons Outline 3.46.0 با `viewBox="0 0 24 24"`، `currentColor` و ضخامت خط مشترک ۲.
- نگاشت ۵۶ دسته غذا/نوشیدنی بازطراحی شد؛ Quick Order تمام کلیدهای Registry را می‌پذیرد و آیکن‌های دسته را حذف نمی‌کند.
- `tools/build-ui-sprite.mjs` خروجی را از نسخه Pin‌شده منبع بازتولید می‌کند؛ سیاست و دستور بازسازی در `assets/icons/README_FA.md` است.
- Buttonهای Close/Refresh و Stepperهای اصلی از کاراکتر وابسته به فونت به Sprite منتقل شدند. عملیات Remove در Draft درخواست خرید و شیفت کاری از `trash` استفاده می‌کند.
- Renderer مشترک JS در `assets/js/panel-shell.js` با نام `window.SoknaIcons.markup()` تعریف شده است.
- تست‌های ایستا PASS: `v1360-icon-system-contract.py`، `v1329-defect-class-gate.py` و `staff-quick-order.py`؛ Syntax همه JSهای تغییرکرده با `node --check` PASS شد.
- محدودیت محیط: PHP و Browser/Playwright موجود نبود؛ PHP lint و UAT واقعی 320/390/412/Desktop همچنان الزامی است. Gallery همه ۵۶ آیکن دسته با Resvg رندر و از نظر وضوح، Canvas و یکپارچگی دیداری بازبینی شد؛ شاهد خروجی در `UI_ICON_GALLERY_UNPUBLISHED.png` است.
- نسخه و Cache Service Worker روی `1.36.0-rc.4` هم‌هویت‌اند. مجوز صریح Owner برای انتشار در ۲۴ اوت ۲۰۲۶ دریافت شد.

## 1. وضعیت پروژه

Sokna یک Modular Monolith با PHP 8.1+ و MySQL/MariaDB است. این نسخه Pre-Go-Live و Feature-frozen است. مقیاس مرجع ۱۰۰–۱۱۰ مهمان هم‌زمان و تیم عملیاتی ۸–۹ نفر است. تغییرات RC4 صرفاً Bugfix/Release-hardening هستند.

قواعد قفل‌شده:

- فراخوان گارسون تغییر ماهوی نکرده است.
- کل کارت محصول قابل کلیک است؛ `+/-` مستقل‌اند و دکمهٔ نمایشی «جزئیات» وجود ندارد.
- انتخاب دوباره واحد خرید Change جعلی تولید نمی‌کند.
- هیچ مقدار یا تعداد به‌صورت خودکار حدس یا Submit نمی‌شود.
- Touch target عملیاتی حداقل ۴۴px است.
- Overlay تو‌در‌تو و CSS/JS patch stacking مجاز نیست.

## 2. سه دور تصمیم و نقد

### دور اول — شواهد و ریسک

Source، گزارش‌های ۴۸۸ Render، Axe، Geometry، اسکرین‌شات‌ها و ۲۸ شناسه Handoff بررسی شدند. نتیجه: P0های Runtime و رفتارهای Purchase/Overlay پیش از بازطراحی ظاهری بسته شوند. نقد مالک/عملیات نیز این تصمیم را تأیید کرد: Fatal و ثبت مبهم خرید ریسک توقف کار دارند؛ فاصله و زیبایی در اولویت بعدی‌اند.

### دور دوم — عملیات رستوران و کنترل مالی

الگوی خرید و شمارش با مستندات رسمی Square و Toast مقایسه شد. نتیجه: دریافت جزئی/تکرارشونده باید صریح، قابل پیگیری و بدون حدس مقدار بماند؛ Draft اعلام نیاز چندقلمی و شمارش ناتمام باید قابل ادامه باشند. پیشنهاد Auto-fill/Auto-submit رد شد چون با انبار و پاسخ‌گویی مالی پروژه ناسازگار است.

### دور سوم — مقیاس، دسترس‌پذیری و نقد خصمانه

Reflow، Target Size، Dialog/Menu APG و Window Size Class بررسی شد. ریشه چند نقص ۱۰۲۴px، عرض Viewport نبود؛ Workspace پس از Sidebar حدود ۷۰۰px بود. نقطه شکست Sidebar به ۱۱۸۰ منتقل شد، ۱۱۸۱ دسکتاپ نگه داشته شد و Item row با Container Query به عرض واقعی Component پاسخ می‌دهد. صف چاپ نیز به ۲۰ ردیف در صفحه محدود شد.

منابع مرجع:

- [WCAG Reflow](https://www.w3.org/WAI/WCAG22/Understanding/reflow)
- [WCAG Target Size](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html)
- [WAI-ARIA Dialog Pattern](https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/)
- [WAI-ARIA Menu Button Pattern](https://www.w3.org/WAI/ARIA/apg/patterns/menu-button/)
- [Android Window Size Classes](https://developer.android.com/develop/ui/views/layout/use-window-size-classes)
- [Square Purchase Orders](https://squareup.com/help/us/en/article/8258-create-purchase-orders-with-square-for-retail)
- [Square Inventory](https://squareup.com/help/us/en/article/6110-manage-inventory-with-the-retail-pos-app)
- [Toast Inventory Count Lists](https://support.toasttab.com/en/article/xtraCHEF-Inventory-Getting-Started)
- [Square Printer Troubleshooting](https://squareup.com/help/us/en/article/5515-printer-troubleshooting)

## 3. وضعیت ۲۸ شناسه

| شناسه | نتیجه Source | آزمون/ریسک باقی‌مانده |
|---|---|---|
| SOK-UI-001 | `csp_nonce()` از سه مسیر حذف؛ P0 بسته شد | PHP lint در محیط مرجع |
| SOK-UI-002 | Card در ≤350px Price/Action را Stack می‌کند | Browser geometry |
| SOK-UI-003 | دکمه واقعی `.menu-item-hit` برای کارت‌های ثابت و Search؛ `+/-` مستقل | Keyboard/Touch browser UAT |
| SOK-UI-004 | Handler دلیل حذف فاکتور یک Owner/Binding | Operator browser UAT |
| SOK-UI-005 | Action Menu مشترک و Mobile sheet موجود حفظ شد | Focus/Escape/Backdrop browser UAT |
| SOK-UI-006 | Stepper بیرون‌بر 44px و متن «تأیید» | 320/340px device UAT |
| SOK-UI-007 | `panel:choice-commit` برای انتخاب تکراری؛ Focus فقط Desktop/non-touch | Purchase browser + DB UAT |
| SOK-UI-008 | Backdrop خرید non-interactive؛ Focus روی کنترل واقعی | Dialog APG browser UAT |
| SOK-UI-009 | متن لغو/شکست خرید صریح و دارای اثر عملیات | Human copy UAT |
| SOK-UI-010 | Owner پایه Purchase CSS یکی شد | Visual regression |
| SOK-UI-011 | Draft تا ۲۰ قلم، restore، duplicate highlight، chips اختیاری، یک Submit | DB error round-trip UAT |
| SOK-UI-012 | منطق stale/new/partial/replay دست‌نخورده و contract ایستا PASS | DB concurrency UAT اجباری |
| SOK-UI-013 | Printing workspace حداکثر 1380px و Responsive | 1024–1920 browser UAT |
| SOK-UI-014 | Queue فیلتر وضعیت و Pagination 20تایی؛ عرض 1120px | داده حجیم + Print UAT |
| SOK-UI-015 | Sidebar تا 1180 Overlay؛ Component بر اساس Workspace | 1180/1181 geometry UAT |
| SOK-UI-016 | Items با LIMIT/OFFSET پنجاه‌تایی و Pager؛ Container Query | داده 128+ و browser UAT |
| SOK-UI-017 | کارت‌های رنگ تمام‌عرض و حداقل 150px | Settings visual UAT |
| SOK-UI-018 | 1024px با Sidebar overlay فضای کامل Analytics/Tables می‌گیرد | Table browser UAT |
| SOK-UI-019 | عرض Categories/Marketing/Users/Help/Printing محدود شد | Wide-screen visual UAT |
| SOK-UI-020 | Sidebar/Cart/Dialog بسته Inert؛ Focus restore فقط Keyboard | Screen reader/browser UAT |
| SOK-UI-021 | Label پویا برای Form group و Triggerهای بی‌نام | Axe rerun لازم |
| SOK-UI-022 | Date ARIA روی Trigger؛ Image Picker یک Dialog؛ Choice keyboard | APG browser UAT |
| SOK-UI-023 | Recovery مشترک HTML/JSON برای 404/409 با مسیر بازگشت | Authenticated HTTP UAT |
| SOK-UI-024 | Filter شمارش‌نشده، Next، Progress live، Scroll restore | Touch/DB UAT |
| SOK-UI-025 | فایل رسمی Vazirmatn v33.003 و مجوز OFL داخل پروژه است؛ Runtime بازیابی نسخه قفل‌شده را حفظ می‌کند | Hash/WOFF2 static PASS؛ Browser render UAT |
| SOK-UI-026 | `!important` از 290 به 282؛ Contract سقف اضافه شد | کاهش بیشتر، P2 پس از UAT |
| SOK-UI-027 | دو نسبت گزارش‌شده به 4.83 و 5.01 رسیدند | Axe/forced-colors UAT |
| SOK-UI-028 | H1 معنادار منو و File picker فارسی/نام فایل | Browser/Screen reader UAT |

## 4. فایل‌های محوری تغییر

- Guest: `index.php`, `assets/js/menu.js`, `assets/css/guest-menu.css`
- Purchase/Supply/Inventory: `admin/purchases.php`, `operator/supply-needs.php`, `admin/inventory_count.php`, `admin/items.php`, `assets/js/supply-*.js`, `assets/js/inventory-form-flow.js`, `assets/css/inventory.css`
- Panel/A11y: `includes/functions.php`, `includes/modules.php`, `includes/panel_layout.php`, `assets/js/panel-*.js`, `assets/css/panel-*.css`
- Printing: `admin/printing.php`, `assets/css/panel-components.css`
- Font: `assets/fonts/Vazirmatn-Variable.woff2`, `assets/fonts/OFL.txt`, `assets/fonts/README_FA.md`, `includes/font_runtime.php`
- Release/tests/docs: `VERSION.txt`, `service-worker.js`, `CHANGELOG_FA.md`, `tests/v1360-rc4-ui-handoff-contract.py`, گزارش RC4 و این Handoff.

`release-manifest.json` بسته Update رسمی، ۱۰۴ فایل تغییرکرده و صفر Delete را برای Source جاری شامل فونت محلی و سامانه آیکن یکپارچه نسبت به Full Project مرجع RC3 پوشش می‌دهد. بسته کامل ۵۶۳ فایل دارد و از همین Source ساخته شده است.

## 5. آزمون و محدودیت محیط

نتیجه کامل در `docs/TEST_REPORT_V1.36.0_RC4_FA.md` ثبت شده است. قرارداد ZIP، Hash/Size مانیفست و هم‌ارزی ایستای بسته رسمی بررسی شده‌اند. در محیط تحویل PHP/DB/Chromium وجود نداشت؛ بنابراین هیچ ادعای PHP lint، اجرای واقعی Updater، DB PASS، Browser PASS، Print PASS یا Production readiness نشده است.

## 6. دستور کار ایجنت بعدی

1. روی محیط PHP 8.1+ دستور `tests/run-1360-dev-gate.sh` را اجرا کند.
2. Source کامل RC3 مرجع را Clone کند و بسته منتشرشده `RC3 → RC4` را با Updater واقعی اعمال کند.
3. `RC3 + Update == RC4 Source` را با Missing/Extra/HashDiff صفر ثابت کند.
4. MySQL/MariaDB تازه و نصب موجود را تست کند؛ سناریوهای Purchase stale/new/partial/replay اجباری‌اند.
5. Browser matrix ثبت‌شده و Axe را روی تمام ۵۷ Entry point اجرا کند و بارگذاری فایل محلی Vazirmatn را در Network/Computed Fonts تأیید کند.
6. Windows Print Agent/Printer، Push، Center و دستگاه Touch واقعی را UAT کند.
7. فقط پس از صفرشدن Blockerها، RC4 را Candidate قابل Pilot اعلام کند؛ نه Production Final.

## 7. Rollback و ایمنی

- RC4 Migration ندارد؛ Rollback فایل‌ها از Restore Point Updater RC3 انجام می‌شود.
- `config.php`, `storage/`, `uploads/`, `install.lock` و `.git` مسیرهای محافظت‌شده‌اند و نباید داخل Update overwrite/delete شوند.
- هیچ Deployment یا تغییر دیتابیس Production در این تحویل انجام نشده است.
