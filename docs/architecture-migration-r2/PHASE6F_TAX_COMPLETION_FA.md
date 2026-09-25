# Phase 6F Completion — Tax Domain

تاریخ: 2026-09-24
Baseline توسعه: `1.36.4-dev.38` / commit پایه `a27df0b5115e71ed8328d92127a78def76dd3d06`
وضعیت: **LOCAL IMPLEMENTATION COMPLETE / STATIC+BROWSER GATES PASS / REAL DB+WINDOWS+PHYSICAL UAT PENDING**

## 1. قرارداد محصول

پیاده‌سازی این فاز مستقیماً از Frozen Tax Contract در R2 تبعیت می‌کند:

- Tax یک Business Module اختیاری است و در نصب جدید پیش‌فرض خاموش است.
- قیمت Catalog پیش از مالیات باقی می‌ماند؛ معنای تاریخی `orders.total_amount` تغییر داده نشده است.
- ترتیب مالی canonical: `Items -> Discount -> Taxable Base -> Tax -> Final Total -> Payments`.
- سیاست Tax قلم: `inherit_default` / `exempt` / `custom_rate`.
- نرخ‌ها و سیاست‌های قلم effective-dated و append-only هستند.
- هر `order_item` snapshot سیاست/نرخ و version owner مربوط را نگه می‌دارد؛ تغییر نرخ آینده تاریخ را بازنویسی نمی‌کند.
- رسید مالی مشتری Tax را نشان می‌دهد؛ Preparation/Kitchen ticket عمداً هیچ فیلد مالیاتی ندارد.
- Integrations ناسازگار باید deterministic fail کنند، نه اینکه Tax را حذف یا مبلغ را جعل کنند.

## 2. Ownerها

Canonical Tax owner:
- `includes/tax.php`

Manager surface:
- `admin/tax.php`

Order/settlement consumers فقط از Ownerهای canonical استفاده می‌کنند و محاسبه موازی صفحه‌ای ندارند:
- Guest: `includes/guest_order_service.php`, `includes/guest_order_manage_service.php`
- Staff: `includes/staff_order_service.php`, `staff/api_quick_order.php`
- Operator: `operator/api_bill.php`, `operator/api_table_session.php`
- Settlement: `includes/settlement.php`, `includes/settlement_allocations.php`
- Reporting: `includes/reporting.php`
- Printing: `includes/printing.php` + Internal Print Worker renderer

## 3. Schema و Migration

Clean Install owner:
- `database/schema.sql`

Update migration source:
- `docs/architecture-migration-r2/PHASE6F_LOCAL_MIGRATION.sql`

Schema additions:
- `tax_rate_versions`
- `tax_item_policy_versions`
- immutable Tax snapshot fields روی `order_items`
- `checkout_taxable`, `checkout_tax` روی `table_sessions`
- Tax/remaining-tax روی `settlement_records`
- taxable/rate/tax/final روی `settlement_record_lines`

تاریخچه قدیمی Tax=0 باقی می‌ماند. `settlement_record_lines.final_amount` برای داده قبلی از `net_amount` backfill می‌شود؛ تاریخ مالی به Tax جدید reinterpret نمی‌شود.

Update Package باید فقط از builder canonical ساخته شود و migration بالا با `--migration` وارد manifest شود. Updater قبل از migration Restore Point دیتابیس می‌سازد و failure path دیتابیس را از Restore Point بازمی‌گرداند. در این checkpoint چون شماره Release نهایی بعدی هنوز تعیین نشده، Update ZIP نهایی ساخته/ادعا نشده است.

## 4. محاسبه و Rounding

Tax با integer amount و basis points انجام می‌شود؛ محاسبه floating-point در سند مالی وجود ندارد.

- تخفیف قبل از Tax تخصیص می‌گیرد.
- پایه مشمول هر line بعد از سهم تخفیف همان line تعیین می‌شود.
- rounding مالک واحد دارد.
- Itemized Settlement Tax-aware از allocation version 2 استفاده می‌کند.
- اسناد legacy بدون Tax allocation v1 را حفظ می‌کنند.
- پرداخت جزئی cumulative/deterministic است؛ ترتیب پرداخت نباید Tax نهایی یک فاکتور را تغییر دهد.
- reversal snapshot Tax همان سند را برمی‌گرداند و نرخ جاری را دوباره محاسبه نمی‌کند.

## 5. Guest / Public

R2 الزام می‌کند قبل از Submit نهایی مهمان این پنج مقدار دیده شود:
- جمع اقلام
- تخفیف
- پایه مشمول مالیات
- مالیات
- مبلغ نهایی

برای جلوگیری از duplicated business calculation در Public/JavaScript، endpoint read-only canonical اضافه شده است:
- Local: `api/order_quote.php`
- Owner: `includes/guest_order_quote_service.php`
- Public compat: `public_edge/api/v1/guest/compat/order_quote.php`
- Relay kind: `guest_order.quote`

کلیک اول Submit quote مالی authoritative از Local می‌گیرد؛ فقط پس از تأیید همان quote mutation انجام می‌شود. Quote signature به draft همان سبد متصل است. Public بدون Local تازه fail-closed است و success جعلی تولید نمی‌کند.

Published Guest snapshot و availability payload نیز Tax profile هر قلم را حمل می‌کنند تا cart estimate Public با وضعیت فعلی هماهنگ باشد؛ confirmation نهایی همچنان Local-authoritative است.

ویرایش/append سفارش قبلی snapshot تاریخی خط موجود را حفظ می‌کند، نه نرخ امروز را.

## 6. UI / Persian-first Design System

Tax UI به Design System جدید و قواعد فارسی/RTL جاری پروژه متصل است؛ Design Systemهای قدیمی SOKNA مرجع نیستند.

- `admin/tax.php` در UI conformance registry ثبت شده است.
- نرخ اختصاصی قلم progressive disclosure است، نه گزینه غالب صفحه.
- واژگان فارسی canonical استفاده می‌شوند.
- Quick Order و Guest از «جمع اقلام / تخفیف / مالیات / مبلغ قابل پرداخت» استفاده می‌کنند.
- `orders.total_amount` در UIهایی که raw subtotal را نشان می‌دهند فقط با برچسب «جمع اقلام» نمایش داده می‌شود و به‌عنوان مبلغ نهایی معرفی نمی‌شود.
- Guest stale/conflict پس از confirmation مالی Drawer را دوباره باز می‌کند تا پیام و recovery action داخل viewport باقی بماند.
- Jalali fields جدید از owner مشترک تاریخ استفاده می‌کنند؛ date picker موازی ساخته نشده است.

## 7. Printing

R2 Printing ownership حفظ شده است: Print Worker component داخلی SOKNA Local است، نه محصول جدا.

Customer receipt payload شامل:
- Tax amount
- taxable amount
- snapshot rate(s)

Internal Print Worker renderer Tax را روی رسید مالی نمایش می‌دهد.

Preparation payload به‌طور contract-enforced فاقد Tax/financial Tax fields است.

## 8. Accommodation compatibility

House `2.6.19-dev.13` / API 2.0 فقط invoice snapshot v1 با رابطه `subtotal - discount = total` را پشتیبانی می‌کند و Tax field ندارد.

بنابراین:
- invoice با Tax=0 به snapshot legacy v1 normalize می‌شود.
- invoice با Tax>0 قبل از ارسال به House deterministic block می‌شود و پیام فارسی روشن می‌دهد.
- Tax هرگز برای سازگارشدن با House حذف یا در `total` پنهان نمی‌شود.

تا ارتقای قرارداد House، انتقال حساب Taxدار به اقامتگاه قابلیت پشتیبانی‌شده نیست.

## 9. Reporting semantics

Tax از فروش/Revenue جدا نگه داشته می‌شود:
- Revenue = مبلغ پس از تخفیف و پیش از Tax.
- Tax = بدهی/مالیات جداگانه.
- Collected/final = Revenue + Tax.

Analytics، Financial Period، Inventory/Item profitability، Operations و Remote read models با همین معنا هماهنگ شده‌اند. Tax به اشتباه سود یا فروش قلم محسوب نمی‌شود.

## 10. Automated Evidence

آخرین evidence قبل از commit این فاز:

- PHP lint: **287/287 PASS**
- JavaScript syntax: **36/36 PASS**
- Unit: **103/103 PASS**
- Pure Tax calculation: **18/18 PASS**
- Phase 6F integration contract: **69 checks PASS**
- Itemized settlement: **33 checks PASS**
- Guest workflow: **18 checks PASS**
- Module ownership: PASS
- Phase 3 Guest Publish/Runtime: PASS
- Phase 7 Internal Print Worker contracts: PASS
- Design System canonical/regression budget/system-state/Persian language: PASS
- UI inventory/conformance: PASS; Tax/Expenses/Batch Purchase registered
- Browser Guest: **320 / 390 / 412 PASS**
- Browser UI conformance: **320 / 390 / 412 PASS**
- Browser Quick Order/Table Draft/Itemized/Mobile Cashier/Table Overview/Preparation/Desktop Invoice/Late Accounting/Updater: PASS
- `git diff --check`: PASS

## 11. Environment/UAT gates still open

این checkpoint عمداً موارد زیر را PASS اعلام نمی‌کند:

- Real MySQL/MariaDB migration from an accepted installed predecessor: **UAT REQUIRED**
- Fresh-install DB check روی MySQL/MariaDB واقعی: **UAT REQUIRED**
- `pdo_sqlite` dependent local test path در این workspace: **BLOCKED_ENVIRONMENT**
- Phase8B DB runtime test با PDO MySQL در این workspace: **BLOCKED_ENVIRONMENT**
- Internal Print Worker .NET build/test on Windows: **WINDOWS CI REQUIRED**
- Windows service/setup/repair/rollback: **WINDOWS CI/UAT REQUIRED**
- Physical customer receipt/printer: **PHYSICAL UAT REQUIRED**
- House Tax contract: **NOT SUPPORTED by House API 2.0; deterministic block remains required**

بنابراین این فاز از نظر source/architecture/static/browser به checkpoint قابل commit رسیده است، اما `Production-ready` یا `Operationally accepted` تا بستن Gateهای محیط واقعی ادعا نمی‌شود.
