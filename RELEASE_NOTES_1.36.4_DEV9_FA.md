# Sokna Cafe 1.36.4-dev.9 — Test RC

این Build برای بستن Regressionهای موبایل و پاک‌سازی بازخورد تسویه آیتمی پس از تست واقعی dev.8 است.

## تغییرات
- Overview موبایل میزها: کنترل‌های مستقیم فیلتر/مرتب‌سازی؛ Select فقط Desktop.
- Itemized: بدون Toast تکراری بعد از پرداخت جزئی؛ Notice داخل Sheet تنها Owner موفقیت است.
- Itemized CTA: تعداد واحد با «عدد» نمایش داده می‌شود.
- Stepper: + در Max دیده می‌شود ولی Disabled است؛ Geometry ثابت می‌ماند.
- Late accounting resume: یک Notice کوتاه، بدون Toast/Success card تکراری.
- هیچ Migration دیتابیس ندارد.

## Update
مسیر تستی رسمی: `1.36.4-dev.8 → 1.36.4-dev.9`.

## UAT_REQUIRED
- گوشی واقعی 360/390/412 و Chrome/PWA.
- مسیر میز → پرداخت جزئی → قلم جاافتاده → پرداخت بعدی.
- چاپ واقعی و Network interruption.
