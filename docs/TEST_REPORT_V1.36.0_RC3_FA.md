# گزارش تست توسعه Sokna 1.36.0-rc.3

## دامنه
RC3 یک Release-hardening بدون Migration جدید است. تمرکز آن UI/UX conformance، زبان عملیاتی، Interactionهای موبایل/RTL، Icon System و Regression روی Failure Classهای قبلی است.

## نتیجه Environment-independent
- PHP lint: PASS
- JavaScript syntax: PASS
- Unit/domain contracts: PASS
- Security/route/module contracts: PASS
- Inventory/Supply optional-module contracts: PASS
- Settlement race/idempotency contracts: PASS
- Print v4 reliability/fault model: PASS
- Panel UI inventory: PASS — 57 UI entrypoint در 13 خانواده ثبت شده‌اند.
- UI conformance contract/browser: PASS — 320/390/412 + desktop؛ geometry، overlays، Select RTL، Badge، Confirm و touch actions.
- Icon system contract/browser: PASS — 105 symbol یکتا؛ 56 دسته فعلی/آتی بدون clipping/overflow.
- UI language contract: PASS
- Updater Engine: PASS — `1.5.3` side-by-side؛ Engineهای پذیرفته‌شده `1.5.1/1.5.2` byte-identical و immutable مانده‌اند.
- Release package acceptance: PASS — بسته واقعی `RC2 → RC3` روی Clone تمیز RC2 با Engine `1.5.2` اجرا شد، `1.5.3` پس از Health Check فعال و `1.5.2` به‌عنوان previous حفظ شد.
- Release equivalence: PASS — `RC2 + Update == RC3 Source` با `Missing=0 / Extra=0 / HashDiff=0`.
- Browser family matrix: PASS؛ تمام Browser testهای ثبت‌شده در UI registry اجرا شدند.
- Hostile regression: Timeout/False Failهای مشاهده‌شده جداگانه بازتولید شدند؛ موارد مربوط به Harness اصلاح و تست مستقل PASS شد.

## UAT_REQUIRED
موارد زیر عمداً PASS اعلام نشده‌اند چون محیط واقعی لازم دارند:
- Fresh install و Update واقعی `1.36.0-rc.2 → 1.36.0-rc.3` روی MySQL/MariaDB واقعی.
- Inventory/Supply disable/re-enable و شمارش کامل روی DB واقعی.
- Settlement race روی دو دستگاه واقعی.
- Sokna Center handoff واقعی.
- Push روی دستگاه واقعی.
- Windows Print Agent 6.0.0 و پرینتر واقعی، شامل failure/recovery.
- Human visual UAT برای baselineهای تصویری ثبت‌شده.

## Release decision
Source gate برای RC3 قابل قبول است. Promotion به Final/Production تا اجرای UATهای بالا مجاز نیست.
