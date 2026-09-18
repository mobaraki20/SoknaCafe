# گزارش آزمون Sokna 1.36.2

تاریخ ساخت: ۲۵ اوت ۲۰۲۶  
مبنا: Full Project منتشرشده `1.36.1`  
مقصد: `1.36.2`  
Updater Engine: `1.5.3`  
Migration دیتابیس: ندارد

## دامنه Release

- Root Fix مرور سفارش مهمان، Quick Order بیرون‌بر و Purchase waiting list.
- Print Agent transport-health diagnostics داخل Print API v4 موجود و `health_json` موجود.
- اصلاح Contract تست چندقلمی Supply از Copy قدیمی به ساختار واقعی Workflow.
- Release identity، Service Worker/Cache، Help metadata و مسیر Update استاندارد `1.36.1 → 1.36.2`.

## PASS — Source / Contract

- PHP lint برای تمام فایل‌های PHP Runtime.
- Node syntax برای تمام JavaScriptهای `assets/js` و `service-worker.js`.
- ۸۵ Contract/Domain gate رسمی بدون Skip، شامل Orders, Finance, Inventory, Supply, Modules, Notifications, Updater, Print v4 و UI ownership.
- `v1340-operations-purchase-permissions.py`: قرارداد چندقلمی Supply بر مبنای Form/JS fields/20-line bound/upsert واقعی PASS.
- `v1361-ui-root-fixes-contract.py`: Guest/Quick Order/Purchases Root Ownerها و عدم Patch stacking PASS.
- `print-v4-agent-health-diagnostics.py`: فیلدهای اختیاری Transport diagnostics و UI پنل چاپ PASS.
- `print-v4-fault-load-model.py`: burst 100، بار 25/min برای 30 دقیقه و mixed 2000 با zero silent loss / zero automatic duplicate / FIFO invariant PASS.
- پنج PHP runtime test تکمیلی هزینه انبار، Subscriber fail-soft، Login security، Settlement signature و Print Template runtime PASS.

## PASS — Browser blocker matrix

۲۷ Browser blocker رسمی بدون Skip در Chromium PASS شدند، شامل:

- Login picker, shared reorder, Messages v2, Print Template v2.
- Operator, Quick Order Undo/Swipe و Staff Quick Order در 320/360/390/412/768/1366/1440.
- Reporting, Finance, Center entitlement, Panel experience/sheets/time picker/notifications.
- Keyboard input, PWA app mode, Updater 1.5.3, visual quality.
- Purchases/Supply در 320/390/412/768/1366 و UI density root fixes در 390/768/1024.
- Modules, UI conformance و Icon system.

Visual promotion تکمیلی Quick Order، Financial Filter و Activity نیز PASS شد.


## PASS — Release package / Updater

- Canonical builder `tools/build-release.php` بسته `sokna-release-v2` را برای مسیر `1.36.1 → 1.36.2` با Updater Engine `1.5.3` ساخت.
- Manifest شامل ۴۰ فایل تغییرکرده، ۰ حذف و بدون Migration است.
- `tests/updater-release-acceptance.php` روی Clone تازه Full Project رسمی `1.36.1` PASS شد و نسخه نصب‌شده را به `1.36.2` رساند.
- پس از Update، ۵۷۹ فایل غیرمحافظت‌شده نصب‌شده با Source نهایی `1.36.2` بایت‌به‌بایت برابر بودند: missing=0, extra=0, diff=0.

## UAT_REQUIRED — نه Failure

- Human-approved visual baselines برای صفحات داده‌دار/حالت‌های خاص هنوز نیازمند مشاهده انسانی هستند.
- `pdo_mysql` در محیط ساخت حاضر نیست؛ DB runtime probe authenticated اجرا نشده است.
- URL/session staging برای HTTP critical-route probe در محیط ساخت ارائه نشده است.
- Update واقعی روی Host کاربر، گوشی واقعی، Service Worker پس از Update و چاپگر فیزیکی باید در UAT انجام شوند.
- Agent 6.1 Release مستقل است و Print UAT ویندوز/پرینتر آن با این Web Release جایگزین نمی‌شود.

## نتیجه

Source 1.36.2 از Gateهای قابل اجرای محلی عبور کرده و برای ساخت Full Project و بسته Update استاندارد از `1.36.1` قابل قبول است. این نتیجه به معنی Go-Live/Production approval نیست؛ پروژه همچنان PRE-OPERATIONAL است تا Owner صریحاً خلاف آن را اعلام کند.
