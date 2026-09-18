# Sokna 1.36.4-dev.22 — Guest/Menu & Operations Integrity

این نسخه ادامه مستقیم `1.36.4-dev.21` است و تغییرات آن عمداً در چند فاز مستقل پیاده‌سازی و Gate شده‌اند تا منطق چاپ dev.21 و سایر دامنه‌ها با هم مخلوط نشوند.

## فاز ۱ — Guest Menu Integrity
- اصلاح Flow واقعی فراخوان گارسون: بعد از انتخاب میز، دکمه تأیید همان لحظه فعال می‌شود.
- یکپارچه‌سازی Policy فراخوان گارسون عمومی و میز بین UI و API.
- اصلاح لینک «نمایش منوی مهمان» از `/index.php` به مالک canonical یعنی `/menu/`.
- حفظ `menu` و `table` هنگام رفت/برگشت صفحه About.
- سازگاری URLهای قدیمی `index.php?table=...` / `index.php?menu=...` با redirect به `/menu/`.
- افزودن Regression Browser واقعی برای Open → Select Table → Confirm بدون Close/Reopen.

## فاز ۲ — Accommodation Transfer Integrity
- `reservation_not_chargeable` و خطاهای قطعی مشابه دیگر Retry نمی‌شوند.
- حذف تکرار متن «این رزرو امکان ثبت هزینه ندارد» از پیام خطا.
- Business Rejection دیگر Connection Health اقامتگاه را خراب نمی‌کند.
- رکورد قطعی همچنان نیازمند رسیدگی انسانی است، با Action روشن «تسویه با روش دیگر».

## فاز ۳ — شماره سفارش روز عملیاتی
- افزودن `business_order_number` با reset بر اساس `business_date`، نه نیمه‌شب تقویمی.
- allocator تراکنشی و قفل‌شده با `SELECT ... FOR UPDATE` برای جلوگیری از شماره تکراری در سفارش‌های هم‌زمان.
- Global `orders.id` و شناسه‌های canonical دست‌نخورده می‌مانند.
- همه مسیرهای ایجاد سفارش (Guest / Quick Order / Operator Add Item when opening order) از allocator واحد استفاده می‌کنند.
- Migration رسمی: `release/1.36.4-dev.22-order-business-number.sql`.

## فاز ۴ — Operator Mobile UX
- نمایش «آخرین سفارش X پیش» روی کارت میز و جزئیات حساب.
- Back گوشی: ابتدا لایه/Modal باز، سپس حساب میز، سپس Navigation واقعی مرورگر.
- Scroll و Sort/Filter لیست میزها هنگام برگشت حفظ می‌شود.
- تاریخچه برای تعویض بین میزهای باز stack غیرضروری ایجاد نمی‌کند.

## فاز ۵ — PWA و توزیع Agent
- Manifest دیگر یک ساعت stale نمی‌ماند؛ با ETag و `must-revalidate` بازاعتبارسنجی می‌شود.
- URL Manifest با Release + favicon revision نسخه‌دار می‌شود.
- favicon پویا از Service Worker precache حذف شد تا آیکن قبلی قفل نشود.
- Guest Menu دیگر Staff Service Worker را register نمی‌کند؛ response kick runtime مهمان حفظ شده است.
- صفحه چاپ نسخه و Setup را از آخرین **Stable GitHub Release** مخزن `mobaraki20/Pagent` می‌خواند.
- Metadata حداکثر هر ۶ ساعت refresh و به‌صورت Last-Known-Good cache می‌شود.
- قطع GitHub یا Rate Limit عملیات چاپ/سفارش را مختل نمی‌کند؛ stale cache و در نبود آن fallback رسمی `6.2.2` استفاده می‌شود.
- بعد از انتشار Stable Agent بعدی، Web برای تغییر Version/Download Link نیازمند Update Package تازه نیست.

## Migration
Update از `1.36.4-dev.21` به `1.36.4-dev.22` باید Migration زیر را اجرا کند:

`release/1.36.4-dev.22-order-business-number.sql`

Migration شماره‌های تاریخی هر روز عملیاتی را بر اساس `created_at, id` backfill و sequence روزانه را از بیشترین شماره همان روز seed می‌کند.

## نکات سازگاری
- Print API/Fallback/Heartbeat remediation نسخه dev.21 حفظ شده و این نسخه آن Contract را تضعیف نمی‌کند.
- Agent binary داخل Web Release Bundle نمی‌شود.
- `print_agent_minimum_version()` مستقل از Latest Stable Release باقی می‌ماند.
- تغییر آیکن PWA در سمت سرور فوراً قابل revalidate است؛ زمان اعمال آیکن روی Web App نصب‌شده همچنان به lifecycle مرورگر/سیستم‌عامل وابسته است.

## Gateهای محیطی
- MySQL concurrency واقعی برای allocator باید در محیط دارای `pdo_mysql`/MySQL اجرا شود؛ Source/DDL/transaction contract به‌تنهایی جای آن را نمی‌گیرد.
- این Web Release هیچ Production deployment خودکار انجام نمی‌دهد.
