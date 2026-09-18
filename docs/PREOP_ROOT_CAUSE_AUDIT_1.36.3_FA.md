# Audit ریشه‌ای Pre-Operational — Sokna 1.36.3

**وضعیت:** اجرایی / Backlog رسمی توسعه  
**تاریخ:** 2026-08-28  
**مرجع سیاست:** `ROOT_CAUSE_REFACTOR_POLICY_FA.md`

## هدف

این Audit با فرض رسمی **PRE-OPERATIONAL** بودن Sokna انجام شده است. هدف، پیدا کردن Legacy، Compatibility Path، Owner موازی، Dead Code و Patch Stacking واقعی است؛ نه بازنویسی بخش‌های سالم.

قاعده تصمیم:

- اگر مسیر فقط برای نسخه/داده قدیمیِ قبل از Go-Live وجود دارد و Requirement واقعی ندارد → **REMOVE / MERGE**.
- اگر حذف آن Invariant مالی، Audit، Recovery یا Fail-safe را تضعیف می‌کند → **KEEP**.
- اگر مشکل در Owner مرکزی قابل اصلاح است → **Root Fix**؛ Route/Handler/Schema دوم ساخته نشود.

---

# نتیجه کلان

بدهی اصلی پروژه «انبوه فایل تکراری» نیست. در Sourceهای PHP/JS/CSS خارج از تست‌ها فقط **یک جفت Exact Duplicate** پیدا شد. مسئله اصلی، چند Compatibility Path کوچک اما ریشه‌دار است که Schema، UI یا API را مجبور به پشتیبانی از وضعیت‌هایی می‌کنند که برای نصب تمیز فعلی نباید ایجاد شوند.

بنابراین پیشنهاد این Audit **Refactor هدفمند** است، نه Rewrite پروژه.

---

# P0 — اصلاح‌های کم‌ریسک و روشن

## P0-1) یک Owner برای صفحه کار روزانه

### شاهد

این دو فایل byte-for-byte یکسان هستند:

- `operator/index.php`
- `admin/operator.php`

هر دو فقط Bootstrap/Permission/Layout را Load کرده و `render_operator_page()` را صدا می‌زنند.

با این حال Call Siteهایی مثل `staff/quick-order.php` و `includes/panel_layout.php` هنوز بر اساس Role بین دو URL انتخاب می‌کنند.

### مسئله

یک Presentation Owner ولی دو Entry Point داریم. این Duplicate Route ارزش کسب‌وکاری ندارد و Navigation را بی‌دلیل دوشاخه کرده است.

### اصلاح ریشه‌ای

- Canonical route: `operator/index.php`.
- تمام Call Siteها به همان Route منتقل شوند.
- `admin/operator.php` حذف شود.
- `includes/modules.php` و Contractهای Route/Navigation به‌روز شوند.
- Redirect compatibility برای Route حذف‌شده نگه داشته نشود، مگر Requirement بیرونی واقعی اثبات شود.

**رأی:** `REMOVE DUPLICATE / MERGE TO ONE OWNER`

---

## P0-2) حذف Route صرفاً Compatibility اقامتگاه

### شاهد

`staff/accommodation_charge.php` هیچ Business Operation انجام نمی‌دهد؛ فقط Login/Capability را بررسی کرده، Flash می‌گذارد و کاربر را به Home برمی‌گرداند.

در Source فعلی، Reference عملیاتی دیگری به آن وجود ندارد و فقط در Module Registry ثبت شده است.

### اصلاح ریشه‌ای

- Route حذف شود.
- Entry point از `includes/modules.php` حذف شود.
- تستی که آن را به‌عنوان redirect-only compatibility entry می‌شناسد به قرارداد Canonical Settlement/Accommodation تغییر کند.

**رأی:** `REMOVE`

---

## P0-3) حذف Dead compatibility helper مرکز سکنا

### شاهد

`includes/sokna_center.php`:

```php
function sokna_center_sign_payload(...)
```

با Comment صریح «Backward name kept...» تعریف شده ولی خارج از Definition هیچ Call Site ندارد. مسیرهای واقعی از `sokna_center_build_token()` / canonical compact signing استفاده می‌کنند.

### اصلاح ریشه‌ای

Helper حذف شود و Contractها فقط API canonical را قفل کنند.

**رأی:** `REMOVE DEAD CODE`

---

## P0-4) اصلاح Contract قدیمی Supply، نه بازگرداندن Copy قدیمی UI

### شاهد

Smoke Gate فعلی در:

`tests/v1360-operations-purchase-permissions.py`

به‌خاطر Assertion زیر Fail می‌شود:

- وجود متن `اعلام نیاز`
- وجود متن `ثبت نیازها`

در حالی که صفحه فعلی `operator/supply-needs.php` Flow چندقلمی واقعی را دارد، Form و Draft List دارد و CTA canonical آن `ثبت درخواست خرید` است.

### نتیجه

این Fail نشانه‌ی شکستن Domain نیست؛ تست به Copy قدیمی UI وابسته شده است.

### اصلاح ریشه‌ای

Contract باید Semantic باشد، مثلاً وجود موارد زیر را بررسی کند:

- `supplyNeedForm`
- آرایه‌های `inventory_item_id[]` / `quantity_major[]`
- Submit canonical
- Owner تابع `supply_request_upsert_locked(...)`

و به یک عبارت فارسی متغیر وابسته نباشد.

**رأی:** `FIX TEST OWNER / DO NOT PATCH UI COPY`

---

## P0-5) حذف API compatibility قدیمی اصلاح تعداد سفارش

### شاهد

`operator/api_bill.php` هنوز Payload قدیمی boolean با کلید `prepared` را می‌پذیرد و آن را به `prepared_removed_quantity` تبدیل می‌کند.

در Client فعلی هیچ ارسال `prepared` پیدا نشد؛ Client جدید quantity دقیق آماده‌شده را می‌فرستد.

Schema نیز ستون:

`order_item_adjustments.prepared_before_adjustment`

را نگه داشته که در Source فقط هنگام Insert پر می‌شود و هیچ Reader دیگری ندارد.

### اصلاح ریشه‌ای

در یک تغییر واحد:

1. Payload قدیمی `prepared` حذف شود.
2. API فقط `prepared_removed_quantity` canonical را بپذیرد.
3. ستون `prepared_before_adjustment` از Clean Schema حذف شود، اگر Contract/Audit نیاز مستقلی برای آن اثبات نشود.
4. Audit/Report از quantity canonical استفاده کند.

**رأی:** `REMOVE API SHIM + REDUNDANT COLUMN`

---

# P1 — Schema باید وضعیت Legacy را اصولاً غیرممکن کند

## P1-1) `cafe_tables.table_number` باید Canonical و NOT NULL باشد — ✅ انجام شد در Batch B

### شاهد

UI ساخت/ویرایش میز همین حالا شماره ۱ تا ۹۹۹۹ را اجباری می‌کند و Bulk Create نیز همیشه Number می‌نویسد؛ اما Schema هنوز:

```sql
table_number SMALLINT UNSIGNED NULL
```

است.

برای جبران همین Nullable بودن، `admin/tables.php` شامل این Legacy Surfaceهاست:

- `analyze_legacy_table_numbers()`
- `backfill_unambiguous_table_numbers()`
- Action `repair_legacy_numbers`
- Warning/UI مخصوص میزهای بدون شماره
- Queryهای `table_number IS NULL`

و `includes/functions.php` نیز `allowLegacyFallback` را برای نمایش میز نگه داشته است.

### اصلاح ریشه‌ای

- Clean Schema: `table_number ... NOT NULL UNIQUE`.
- Legacy repair path کامل حذف شود.
- UI حالت «شماره نامشخص» حذف شود.
- `table_display_token()` / `table_medallion_html()` فقط هویت canonical را بگیرند؛ fallback استخراج شماره از Name حذف شود اگر Call Site معتبر دیگری نماند.
- تست نصب تمیز تضمین کند هیچ میز بدون Number قابل ایجاد نیست.

**رأی:** `SCHEMA ROOT FIX`

---

## P1-2) Business Time Snapshotها از Nullable Legacy خارج شوند — ✅ انجام شد در Batch B

### شاهد

تمام Writerهای فعلی برای این Domainها business snapshot را تولید می‌کنند:

- `table_sessions`
- `orders`
- `waiter_calls`
- `settlement_records`

اما Schema ستون‌های `business_date`, `business_shift_key`, `business_shift_label`, `business_cutoff_snapshot` را Nullable نگه داشته است.

به همین علت Compatibility Readهایی مثل زیر باقی مانده‌اند:

- `settlement_today()` fallback از `business_date IS NULL` به `settled_at`
- Carryover Session fallback از `business_date IS NULL` به `started_at`
- Invoice grouping fallback به تاریخ `settled_at`
- Operator/Reporting fallbackهای مشابه

### اصلاح ریشه‌ای

- برای رکوردهای جدید Clean Schema این Snapshotها NOT NULL شوند، هرجا Domain contract واقعاً همیشه آن‌ها را تعیین می‌کند.
- Null-fallbackهای نسخه‌های قبلی حذف شوند.
- Reversal/Settlement نیز همان Snapshot canonical را تضمین کند.
- Contract نصب تمیز Null business snapshot را رد کند.

**نکته:** این تغییر باید یکجا و Owner-based انجام شود؛ فقط حذف یک `OR business_date IS NULL` کافی نیست.

**رأی:** `SCHEMA + READ MODEL ROOT FIX`

---

## P1-3) Legacy Multiple Draft Count از مدل ذهنی حذف شود — ✅ انجام شد در Batch B

### شاهد

`inventory_open_count_sessions()` عمداً Array برمی‌گرداند و پیام/UI برای «چند شمارش قدیمی باز» دارد، با Comment صریح Legacy 1.32.5.

در عین حال `inventory_count_start()` امروز با یک Mutex در `settings` از Start همزمان جلوگیری می‌کند؛ یعنی سیستم جاری قصد دارد فقط یک Draft باز داشته باشد.

### اصلاح ریشه‌ای

- Contract نهایی «حداکثر یک Draft باز» به‌صورت صریح تثبیت شود.
- UI و Message branch مربوط به multiple legacy drafts حذف شود.
- APIهای مصرف‌کننده به `?single open count` ساده شوند.
- اگر MySQL invariant قابل اتکا و ساده‌ای برای enforce کردن One-Draft وجود دارد، در Schema اضافه شود؛ در غیر این صورت Mutex/transaction owner فعلی حفظ شود و Test race آن را قفل کند.

**رأی:** `SIMPLIFY DOMAIN CONTRACT`، بدون اختراع معماری پیچیده.

### نتیجه اجرای Batch B

سه مورد P1 بالا به‌صورت Owner-based اجرا شدند. Clean Schema، Read Model و UI branchهای Legacy مربوط یکجا هم‌راستا شدند. `includes/schema_health.php` نیز Invariantهای جدید را read-only کنترل می‌کند تا دیتابیس قدیمی با Schema ناسازگار ساکت پذیرفته نشود.

مرجع اجرای کامل و شواهد تست: `BATCH_B_ROOT_CAUSE_CLEANUP_1.36.3_FA.md`.

---

# P2 — بدهی معماری که باید تدریجی حل شود

## P2-1) `includes/functions.php` Shared Legacy Hotspot است، اما Rewrite نشود

فایل حدود 139KB است و خود Architecture Map نیز آن را Shared Legacy Hotspot معرفی کرده است.

### تصمیم

- هیچ Rewrite کلی انجام نشود.
- هر بار Domain مشخصی لمس می‌شود، فقط Functionهای همان Domain در صورت وجود Owner روشن به فایل Owner منتقل شوند.
- Business Logic جدید به این فایل اضافه نشود مگر واقعاً Cross-domain/shared باشد.

**وضعیت 1.36.3 P2:** سه Owner کم‌ریسک Jalali/Media/Favicon استخراج شدند؛ مرجع: `P2_OWNER_TEST_AUDIT_1.36.3_FA.md`.

**رأی:** `INCREMENTAL EXTRACTION ONLY`

---

## P2-2) Test suite دارای Historical Naming Debt است

- 273 فایل Test در Root تست‌ها وجود دارد (شامل Contract جدید P2).
- 114 فایل Prefix نسخه‌ای `v...` دارند.
- بیشتر آن‌ها هنوز Regression ارزشمندند و نباید کورکورانه حذف شوند.
- Fail فعلی Supply نشان می‌دهد حداقل بخشی از Contractهای قدیمی Semantic Drift دارند.

### تصمیم

پس از P0/P1:

- تست‌ها بر اساس Domain/Invariant طبقه‌بندی شوند، نه شماره Release.
- Contractهای تکراری یا Copy-sensitive ادغام شوند.
- Regressionهای امنیت/مالی/Inventory/Print حذف نشوند مگر معادل canonical قوی‌تر داشته باشند.

**وضعیت 1.36.3 P2:** 114 تست نسخه‌دار Audit شدند؛ Mass rename/delete رد شد و نام‌گذاری Semantic برای تست‌های جدید ثبت شد. مرجع: `P2_OWNER_TEST_AUDIT_1.36.3_FA.md`.

**رأی:** `AUDIT TEST SEMANTICS, NOT MASS DELETE`

---

# KEEP — مواردی که فعلاً نباید با برچسب Legacy حذف شوند

## Updater Engineهای 1.5.1 / 1.5.2 / 1.5.3

این سه Engine حجم قابل توجه و شباهت زیادی دارند، اما اسناد و Contractهای فعلی عمداً آن‌ها را برای Rescue/Rollback و مسیر Update side-by-side نگه می‌دارند.

تا وقتی Update/Recovery Strategy رسماً ساده نشده، حذف آن‌ها در این Audit توصیه نمی‌شود.

**رأی:** `KEEP — SAFETY/RECOVERY OWNER`

## مالی، Settlement، Audit، Backup/Restore، Print fail-safe

History و Recovery «Legacy» نیستند. پاک‌سازی آن‌ها فقط به دلیل قدیمی یا پیچیده بودن ممنوع است.

**رأی:** `KEEP / CHANGE ONLY WITH DOMAIN PROOF`

---

# UI debt شناخته‌شده

`tests/visual_quality_baseline.json` شش صفحه دارای composition debt را صریح ثبت کرده است:

- `admin/inventory_count_start.php`
- `admin/inventory_review.php`
- `admin/item_order.php`
- `admin/maintenance.php`
- `admin/marketing.php`
- `admin/printing.php`

این‌ها Candidate بازطراحی UI هستند، اما در این Audit به‌عنوان P0 Code Cleanup محسوب نمی‌شوند.

---

# وضعیت تست هنگام Audit

## Pass قطعی

- PHP lint: **157 / 157**
- JS syntax: **36 / 36**
- Unit: **95 checks PASS**
- Contractهای قبل از نقطه شکست Smoke: اکثریت PASS
- 31 Contract/Regression بعد از تست متوقف‌شده نیز به‌صورت مستقل اجرا شدند: **31 / 31 PASS**
- Browser Gateهای اجراشده قبل از سقف زمان محیط: **5 PASS**

## Fail واقعی ثبت‌شده

`tests/v1360-operations-purchase-permissions.py`

فقط روی Copy Assertion `ثبت نیازها` متوقف شد؛ بررسی Source نشان می‌دهد Flow چندقلمی Supply موجود است و CTA فعلی `ثبت درخواست خرید` است. بنابراین طبقه‌بندی Audit: **stale/copy-coupled test assertion**.

## اجرا نشده / ادعای Pass نمی‌شود

Browser Suite کامل به‌دلیل سقف زمان محیط در اجرای `guest-1276-browser.py` متوقف شد. این مورد به‌عنوان Product Failure ثبت نمی‌شود و Pass هم فرض نمی‌شود.

---

# ترتیب اجرای پیشنهادشده

## Batch A — Canonical Routes/API/Test ✅ اجرا شد

### UI/Behavior Preservation Gate

این Batch **Behavior-preserving cleanup** است. طبق `UI_BEHAVIOR_PRESERVATION_CONTRACT_FA.md` حذف Route/Compatibility/Dead Code و اصلاح Contract تست حق تغییر ناخواسته در ظاهر، جایگاه Action، Navigation، ترتیب Workflow، Permission یا State نتیجه را ندارد. هر موردی که برای اجرا نیازمند Redesign واقعی باشد از Batch A خارج می‌شود و Task مستقل می‌گیرد.

1. حذف `admin/operator.php` و انتقال تمام Call Siteها به `operator/index.php`.
2. حذف `staff/accommodation_charge.php` و Registry/Test مرتبط.
3. حذف `sokna_center_sign_payload()`.
4. تبدیل Contract Supply از Copy-based به Semantic.
5. حذف payload legacy `prepared` و در صورت تأیید Contract، ستون `prepared_before_adjustment`.

پس از Batch A: PHP lint + JS syntax + Unit + Contract + Browser smoke مرتبط.


### نتیجه اجرای Batch A — 2026-08-28

- `admin/operator.php` حذف شد و Navigation/Quick Order/Module Registry به `operator/index.php` canonical منتقل شدند.
- `staff/accommodation_charge.php` حذف و Registry مرتبط پاک شد.
- `sokna_center_sign_payload()` حذف شد؛ signer canonical باقی ماند.
- تست Supply از Copy قدیمی به Contract ساختاری/Domain-based منتقل شد.
- payload بولی `prepared` حذف و Client/API روی `prepared_removed_quantity` یکپارچه شدند.
- `order_item_adjustments.prepared_before_adjustment` از Clean Schema و Writer حذف شد؛ `prepared_removed_quantity` به‌عنوان مقدار دقیق Audit باقی ماند.
- Contract اختصاصی `tests/v1363-batch-a-root-cleanup.py` اضافه و به Dev/Release Gate متصل شد.
- Browserهای مستقیم Operator/Quick Order PASS شدند. Failureهای `guest-1276-browser.py` و `panel-jalali-browser.py` روی Baseline قبل از Batch A نیز تکرار شدند؛ بنابراین به این Refactor نسبت داده نشدند.

### نتیجه Task مستقل Baseline Browser — 2026-08-28

- Guest Menu: Root Cause در Test Harness بود. Runtime عمداً `menu-item-hit` را به‌عنوان دکمه تمام‌سطح و Owner واقعی بازکردن جزئیات ایجاد می‌کند؛ تست قدیمی روی `<h3>` زیر این دکمه کلیک می‌کرد و Playwright به‌درستی Pointer Interception گزارش می‌داد. تست به `[data-open-item-detail]` منتقل شد؛ Runtime/CSS مهمان تغییر نکرد.
- Jalali: Root Cause یک شکاف Accessibility Contract بود. فیلد تاریخ روی Touch خودش Picker را باز می‌کرد اما `aria-haspopup`/`aria-expanded` فقط روی آیکن تقویم نگه‌داری می‌شد. Owner مشترک حفظ شد و هر دو Activation Surface اکنون وضعیت همان Picker را اعلام می‌کنند. هیچ تغییر بصری، Geometry یا Workflow ایجاد نشد.
- `guest-1276-browser.py` در 320/390/412 PASS شد. `panel-jalali-browser.py` در Desktop و 320/360/390/412 PASS شد.
- تمام Browser Gateهای جاری Dev Gate به‌صورت مستقل PASS شدند. PHP lint 155/155، JS syntax 36/36 و Unit 95/95 نیز PASS ماندند.

## Batch B — Canonical Schema

1. `cafe_tables.table_number NOT NULL` و حذف کامل Legacy table-number repair/fallback.
2. harden کردن Business Time snapshots و حذف Null fallbackها.
3. ساده‌سازی Inventory Count به یک Open Draft canonical.

این Batch باید با Fresh Install روی MySQL/MariaDB واقعی و Regression مالی/Order/Inventory اجرا شود.

## Batch C — Debt Reduction تدریجی

- استخراج محدود از `includes/functions.php` فقط هنگام لمس Domain.
- Semantic consolidation تست‌های version-stamped.
- بستن شش composition debt ثبت‌شده در UI baseline.

---

# Definition of Done این Cleanup

Cleanup زمانی تمام است که:

- برای هر قابلیت یک Route/Owner canonical وجود داشته باشد.
- Compatibility pathهای Pre-Go-Live بدون Requirement حذف شده باشند.
- Clean Schema وضعیت‌های Legacy حذف‌شده را دیگر نتواند تولید کند.
- تست‌ها Invariant را قفل کنند، نه متن یا پیاده‌سازی تاریخی را.
- تعداد Owner/Route/Branch کمتر یا برابر شده باشد، نه بیشتر.
- Regression مالی، سفارش، Inventory، Print، Recovery و Security سالم بماند.

