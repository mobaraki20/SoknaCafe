# هنداور توسعه Sokna 1.36.3

## وضعیت Release
- نسخه مقصد: `1.36.3`
- Baseline Update: Full Project رسمی `1.36.2`
- Updater Engine: `1.5.3`
- Migration دیتابیس: ندارد
- Lifecycle: PRE-OPERATIONAL / Test
- Pre-Go-Live policy: Legacy بدون Requirement واقعی قابل حذف است؛ اصلاحات باید Root-Cause/Owner محور باشند و «کد روی کد»/Patch Stacking یا مسیر موازی به‌عنوان راه‌حل پذیرفته نیست. مرجع: `../ROOT_CAUSE_REFACTOR_POLICY_FA.md`.
- Refactor safety: تا قبل از Approval صریح برای Redesign/Behavior Change، Cleanupها باید UI/Behavior-preserving باشند؛ ظاهر، جایگاه Actionها، Workflow و نتیجه مشاهده‌پذیر Freeze است. مرجع: `../UI_BEHAVIOR_PRESERVATION_CONTRACT_FA.md`.

## Ownerهای تغییرکرده
### Printing
- `admin/printing.php`: تنها Surface مدیریتی چاپ؛ سه tab عملیاتی `overview/settings/diagnostics` و alias سازگار برای tabهای قدیمی.
- `assets/css/panel-components.css`: Owner واحد `print5-*` برای Operations Console؛ Hero/Wizard دائمی حذف شده و Job row owner تکراری پاک شده است.
- `assets/js/printing-settings.js`: mapping Windows Printer و close editor؛ Listener بستن editor مستقل از Token clipboard است.
- `includes/printing.php`: Recommended Agent=`6.1.0`، Minimum Agent=`6.0.0`، Protocol v4 بدون تغییر.
- `assets/icons/ui-sprite.svg` و `tools/build-ui-sprite.mjs`: آیکن‌های `share` و `download` در Sprite مرکزی.

### Quick Order
- `assets/css/quick-order.css`: Takeaway Stepper در <=1023px دیگر grow نمی‌کند (`flex:0 0 auto`). Renderer/State Owner جدید ساخته نشده است.

### Purchasing
- `admin/purchases.php`: Action share به `ui_icon('share') + اشتراک` تبدیل شد.
- `assets/css/inventory.css`: share action compact و touch-safe؛ هیچ stylesheet/override جدیدی ساخته نشده است.

## Agent 6.1
- مخزن Source of Truth: `mobaraki20/Pagent`
- PR تثبیت 6.1 با Squash روی `main` merge شده است.
- Release توزیع: `v6.1.0`
- Setup: `Sokna-Print-Agent-6.1.0-Setup.exe`
- Setup SHA256: `3ccdbd286b1176657b44ab512cb23488325c6d404b17bb6505802e01ff29e655`
- Cafe هیچ binary/source Agent را Bundle نمی‌کند؛ فقط به Release asset نسخه‌دار لینک می‌دهد.
- Physical printer UAT مستقل از Web release باقی است.

## قراردادهای حفظ‌شده
- Print API v4 و no-auto-duplicate/explicit ambiguity resolution حفظ شده‌اند.
- `submitted` فقط Spooler acceptance است، نه physical paper confirmation.
- Finance/Inventory/Settlement/Order state machine خارج از Scope تغییر نکرده‌اند.
- Update رسمی این Release فقط `1.36.2 → 1.36.3` است.

## Gate
گزارش نهایی در `docs/TEST_REPORT_V1.36.3_FINAL_FA.md` ثبت می‌شود. Host/DB/Service Worker و چاپگر فیزیکی تا زمان اجرای واقعی `UAT_REQUIRED` می‌مانند.

## Pre-Operational Root-Cause Cleanup — Batch A

پس از Release 1.36.3 و قبل از Go-Live، Batch A پاک‌سازی ریشه‌ای با قرارداد `UI_BEHAVIOR_PRESERVATION_CONTRACT_FA.md` اجرا شد.

- Route کار روزانه فقط `operator/index.php` است؛ `admin/operator.php` برنگردد.
- Route قدیمی `staff/accommodation_charge.php` و alias مرده `sokna_center_sign_payload()` حذف شده‌اند.
- اصلاح quantity سفارش فقط قرارداد دقیق `prepared_removed_quantity` دارد؛ payload بولی `prepared` و ستون `prepared_before_adjustment` legacy محسوب می‌شوند و نباید برگردند.
- تست Supply باید Semantic باشد و به عبارت UI تاریخی وابسته نشود.
- Gateهای `tests/refactor-preservation-policy-contract.py` و `tests/v1363-batch-a-root-cleanup.py` قبل از هر Cleanup بعدی باید سبز بمانند.
- تغییر UI/Behavior در Refactor بدون Approval صریح ممنوع است.
- Baseline Browser defectهای Guest/Jalali بعد از Batch A بسته شدند: Guest detail interaction در تست باید از Owner واقعی `[data-open-item-detail]` استفاده کند؛ کلیک روی متن زیر Full-card button تست معتبر نیست.
- Jalali یک Picker Owner دارد، اما هر Activation Surface واقعی (Input و Calendar trigger) باید `aria-haspopup` و `aria-expanded` هماهنگ با همان Picker را اعلام کند.
- Browser Gateهای جاری Dev Gate بعد از این اصلاح‌ها مستقل PASS شده‌اند؛ تغییر بصری در Guest یا Jalali برای بستن این دو Defect انجام نشده است.


## Pre-Operational Root-Cause Cleanup — Batch B

Batch B پس از سبزشدن Baseline Browser اجرا شد و UI/Behavior Freeze حفظ شده است.

- `cafe_tables.table_number` در Clean Schema اجباری/`NOT NULL` است؛ repair/backfill و fallback هویت میز برنگردد.
- Snapshot زمان عملیاتی برای `table_sessions/orders/waiter_calls/settlement_records` در Clean Schema `NOT NULL` است؛ Read Model نباید به timestamp خام fallback کند.
- Inventory فقط یک Open Count Owner دارد: `inventory_open_count_session()`؛ UI چند Draft Legacy برنگردد.
- `includes/schema_health.php` Nullability این Invariantها را روی DB واقعی read-only بررسی می‌کند.
- هیچ CSS در Batch B تغییر نکرده است؛ Redesign خارج از Scope بود.
- Contract ضدبازگشت: `tests/batch-b-schema-root-contract.py`.
- مرجع: `../BATCH_B_ROOT_CAUSE_CLEANUP_1.36.3_FA.md`.
- دیتابیس Pre-Batch-B در صورت نیاز به حفظ، باید با Migration کنترل‌شده Release هم‌راستا شود؛ Compatibility branch دائمی برای داده آزمایشی برنگردد.


## P2 incremental owner cleanup
- `includes/functions.php` همچنان public aggregator است؛ Jalali/Media/Favicon/Audit/Messages/Events اکنون در `includes/function_domains/` Owner مستقل دارند.
- فایل shared از حدود 139KB/2783 خط پیش از P2 به حدود 86KB/1906 خط رسیده است.
- این extraction behavior-preserving است؛ call siteهای محصول نباید مستقیماً `function_domains/*` را include کنند.
- Order/Finance/Inventory/Capabilityها در این Batch عمداً extract نشده‌اند؛ برای آن‌ها refactor جدا و حساس‌تر لازم است.
- تست‌های source-sensitive باید capability را از Owner canonical بخوانند، نه اینکه حضور تابع داخل `functions.php` را به‌عنوان behavior فرض کنند.
- تست جدید با نام Semantic/Domain-based ساخته شود؛ version-prefixed naming جدید ایجاد نکن مگر reproduction تاریخی مستند.
- مرجع: `docs/P2_OWNER_TEST_AUDIT_1.36.3_FA.md`.
