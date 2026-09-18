# Release Notes — Sokna Cafe 1.36.4-dev.14

تاریخ: 2026-09-06

## Scope
این checkpoint روی Baseline `1.36.4-dev.13` دو Owner محدود را اصلاح می‌کند:
1. Print Reliability / Print Operations
2. استاندارد Stepper در Modal اصلاح تعداد Operator

هیچ Redesign تأییدنشده‌ای وارد Release نشده است.

## Print Reliability
- مقصدهای آماده‌سازی سفارشی اکنون دکمه «حذف مقصد» دارند.
- مقصد سیستمی `customer_receipt` و `prep_shared` قابل حذف نیستند.
- حذف فیزیکی مقصد فقط وقتی مجاز است که هیچ `print_jobs` تاریخی به آن وابسته نباشد؛ در غیر این صورت برای حفظ Audit فقط غیرفعال می‌شود.
- Activity Queue برای Jobهای پشت FIFO، Job قبلیِ مسدودکننده را نمایش می‌دهد؛ Problem blocker به‌صورت صریح مشخص می‌شود.
- Failover واقعی Print API v4 تکمیل شد: Primary اولویت دارد و Fallback فقط وقتی Primary operational نیست Claim می‌کند؛ Queue جایگزین داخل Attempt snapshot ثبت می‌شود.
- چاپ آزمایشی فقط وقتی فعال است که یک Route واقعی (Primary یا Fallback) در همان لحظه Agent/Printer آماده داشته باشد.

## پاکسازی یک‌باره داده‌های چاپ تستی
Update `dev.13 → dev.14` یک Migration یک‌باره و صریح دارد که فقط این داده‌های آزمایشی را پاک می‌کند:
- `print_attempts`
- `print_claim_requests`
- `print_jobs`

و `AUTO_INCREMENT` همین سه جدول را Reset می‌کند.

Migration به این موارد دست نمی‌زند:
- `print_agents`
- `print_destinations`
- `print_templates`
- تنظیمات چاپ
- سفارش، مالی، موجودی، کاربران یا هر Domain دیگر

**قبل از Update، Windows Print Agent را Stop و Windows Spooler را از Jobهای تستی خالی کنید.** Queue محلی Agent روی ویندوز از طریق Migration سرور قابل پاکسازی نیست.

## Operator / Stepper
- Stepper «تعداد نهایی» و «تعداد آماده‌شده» در Modal اصلاح تعداد روی همان `quantity-stepper` استاندارد Consolidate شدند.
- Handler جداگانه `data-bill-prepared-step` حذف شد.
- هر دو Stepper از `data-bill-step + data-stepper-for` و Geometry مشترک 44px Touch Target استفاده می‌کنند.
- Redesign حرفه‌ای‌تر Composition این Modal عمداً وارد این نسخه نشده و نیازمند تأیید Visual جداگانه است.

## پس از نصب
1. در `چاپ → تنظیمات چاپ → مسیرهای چاپ`، مقصد سند مشتری و مقصد آماده‌سازی را به رایانه `صندوق سکنا` و نام دقیق Printer ویندوز وصل کنید.
2. Agent را Start کنید و Diagnostics را Refresh کنید.
3. یک چاپ آزمایشی آماده‌سازی و سپس یک چاپ آزمایشی سند مشتری انجام دهید.
4. فقط وقتی هر دو به Windows Spooler تحویل شدند و کاغذ فیزیکی خارج شد، Print UAT را PASS اعلام کنید.

## Test discipline
`Test not run = Not tested`.
