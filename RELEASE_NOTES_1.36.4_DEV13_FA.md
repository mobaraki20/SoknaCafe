# Release Notes — Sokna Cafe 1.36.4-dev.13

تاریخ: 2026-09-06

## Scope
این checkpoint فقط تغییرات تأییدشده صفحه «مرور سفارش» Guest/Table Menu را روی Baseline `1.36.4-dev.12` اعمال می‌کند. Route/Owner `/menu`، فراخوان گارسون عمومی، منطق سفارش، قیمت‌گذاری و Settlement تغییر دامنه‌ای ندارند.

## تغییرات
- فشرده‌سازی ملایم Header و ردیف‌های مرور سفارش با حفظ خوانایی و Touch Target کنترل تعداد در 44px.
- کاهش whitespace، فاصله و Typography ثانویه؛ Badge بیرون‌بر کوچک‌تر و هم‌سطح‌تر با اطلاعات `تعداد × قیمت واحد`.
- خلاصه Footer از `N بیرون‌بر` به `N عدد بیرون‌بر` برای رفع ابهام معنایی.
- `cartFulfillmentSummary` به Live Status (`role=status`, `aria-live=polite`, `aria-atomic=true`) تبدیل شد؛ Toast جدیدی اضافه نشده است.
- Focus کنترل `+/-` در Interaction کیبورد بعد از rerender سبد بازیابی می‌شود؛ Touch behavior و focus modality قبلی حفظ شده است.
- Selector خواندنی Fulfillment دیگر Cart state را mutate نمی‌کند؛ `clamp()` همچنان Mutation owner صریح باقی مانده است.
- CSS Footer شیت «موارد بیرون‌بر» Consolidate شد و override زنجیره‌ای unconditional حذف شد.
- Contractهای Browser/Owner/Takeaway برای Focus، Density، Live Status، wording و purity به‌روزرسانی شدند.

## تغییر نکرده
- `/menu` و `/menu?table=TOKEN`
- فراخوان گارسون عمومی و اعتبارسنجی میز
- Payload و Business Ruleهای `takeaway_quantity` / `fulfillment_mode`
- Quick Order staff
- Settlement / Finance / Inventory / Printing / Accommodation

## Test discipline
`Test not run = Not tested`. وضعیت دقیق Gateها در Handoff همین نسخه ثبت شده است.

## Migration
Migration دیتابیس ندارد.

## Verification
- PHP/JS syntax: PASS
- Unit: `102/102` PASS
- Guest cart/takeaway Browser contract: PASS در `320/390/412` شامل compact density، keyboard-focus retention و wording جدید
- Module/Finance/Inventory/Settlement/Printing/Accommodation contracts: PASS در اجرای قطعه‌بندی‌شده
- Browser blocker matrix: PASS در اجرای قطعه‌بندی‌شده؛ Quick Order، Mobile Cashier/Table Overview، Startup open_table، Itemized، Late Accounting و Desktop Invoice PASS
- Updater Acceptance: `1.36.4-dev.12 → 1.36.4-dev.13`, Engine `1.5.3`: PASS
- اجرای یک‌تکه `run-release-gate.sh` به سقف زمانی محیط ابزار رسید؛ نتیجه آن Full PASS اعلام نمی‌شود. تمام بخش‌های باقی‌مانده همان Gate جداگانه اجرا و PASS شدند.
- Human visual promotion baselines: `UAT_REQUIRED`
- MySQL/MariaDB critical route runtime: `UAT_REQUIRED` چون `pdo_mysql` در محیط حاضر نصب نیست.
- Authenticated staging HTTP route smoke: `UAT_REQUIRED` چون staging URL/session ارائه نشده است.
