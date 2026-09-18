# Handoff — Sokna Cafe 1.36.4-dev.13 / Compact Order Review

## Baseline
- From: `1.36.4-dev.12`
- To: `1.36.4-dev.13`
- Status: Pilot-ready checkpoint; Production Go-Live هنوز نیازمند UAT واقعی است.
- Updater Engine: `1.5.3`
- DB Migration: ندارد.

## تصمیم Product/UX
ظاهر و Flow «مرور سفارش» Redesign نشد. فقط اطلاعات جمع‌وجورتر شد تا در موبایل اقلام بیشتری بدون کاهش Hit Area عملیات اصلی قابل مشاهده باشند.

قواعد نهایی:
- Quantity Stepper همان Touch Target حداقل 44px را حفظ می‌کند.
- نام، قیمت خط، `تعداد × قیمت واحد` و Badge بیرون‌بر همچنان hierarchy قبلی را دارند.
- Dine-in ساکت است؛ فقط Takeaway به‌عنوان Exception نمایش داده می‌شود.
- Footer Summary: `N عدد بیرون‌بر`.
- هیچ Toast جدیدی برای Fulfillment اضافه نشده است.

## Root fixes
### Keyboard focus
`assets/js/menu.js` قبل از Quantity mutation فقط در Interaction کیبورد identity کنترل فعال `data-inc/data-dec` را ثبت می‌کند و بعد از `render()` Focus را روی همان کنترل بازمی‌گرداند؛ اگر خط حذف شده باشد Focus داخل Cart روی کنترل معتبر بعدی/Close باقی می‌ماند.

### Fulfillment state purity
`assets/js/fulfillment-policy.js` اکنون read selector `takeaway()` را بدون mutation اجرا می‌کند. Normalization خواندنی در `normalizedTakeaway()` است و mutation فقط با `clamp()` صریح انجام می‌شود.

### CSS ownership
قواعد unconditional مربوط به Footer شیت بیرون‌بر در Owner اولیه ادغام شدند؛ override زنجیره‌ای بعدی حذف شد. Media queryهای responsive همچنان variation همان Owner هستند و duplicate owner محسوب نمی‌شوند.

## فایل‌های Domain تغییرکرده
- `assets/css/guest-menu.css`
- `assets/js/menu.js`
- `assets/js/fulfillment-policy.js`
- `menu.php`

## Regression contracts تغییرکرده
- `tests/guest-1276-browser.py`
- `tests/v1332-cart-owner-contract.py`
- `tests/takeaway-services-contract.py`

## مواردی که نباید Regression بگیرند
- `/menu` public/table owner contract
- فراخوان گارسون عمومی
- QR/SEO/noindex
- Guest order create/edit/conflict
- Quick Order
- Table open context
- Settlement/Itemized/Late Accounting
- Accommodation/Subscriber
- Printing

## UAT
هر مورد محیط واقعی که اجرا نشده است باید `UAT_REQUIRED` باقی بماند: MySQL/MariaDB، staging authenticated HTTP، چند اپراتور، PWA/device lifecycle، Push، Windows Print Agent/Printer، House/Subscriber واقعی و Human Visual UAT.

## Test / Release status
### PASS
- PHP syntax + JS syntax
- `102/102` Unit checks
- `tests/guest-1276-browser.py` در 320/390/412؛ Density حداکثر ردیف، Focus کیبورد و Fulfillment summary
- `tests/v1332-cart-owner-contract.py`
- `tests/takeaway-services-contract.py`
- تمام Core/Domain blockerهای Release Gate در اجرای قطعه‌بندی‌شده
- تمام Browser blockerهای Release Gate در اجرای قطعه‌بندی‌شده
- Quick Order production / Undo-Swipe
- Mobile Cashier / Mobile Table Overview
- Startup `open_table` no-flash
- Itemized Settlement / Late Accounting
- Desktop Invoice / Printing operations
- Accommodation API/HTTP/Boundary contracts در محیط mock/contract
- Updater Acceptance واقعی بسته: `1.36.4-dev.12 → 1.36.4-dev.13`, Engine `1.5.3`

### NOT CLAIMED AS PASS
اجرای یک‌تکه `tests/run-release-gate.sh` به timeout محیط ابزار رسید. طبق `Test not run = Not tested` آن invocation Full PASS محسوب نمی‌شود؛ suiteهای باقی‌مانده از همان Gate جداگانه اجرا شدند و PASS دادند.

### UAT_REQUIRED
- Human-approved visual promotion baselines
- MySQL/MariaDB critical-route runtime (`pdo_mysql` در محیط حاضر موجود نیست)
- Authenticated staging HTTP smoke (URL/session موجود نیست)
- PWA/device lifecycle واقعی، Push واقعی، Windows Print Agent/Printer، House/Subscriber واقعی و multi-operator pilot
