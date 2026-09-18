# Release Notes — Sokna 1.36.3

## هدف Release
این Release روی `1.36.2` ساخته شده و بدون Migration دیتابیس، رابط عملیات چاپ و چند Root UI گزارش‌شده را جمع‌بندی می‌کند. معماری Modular Monolith، Print API v4 و Ownerهای اصلی سامانه حفظ شده‌اند.

## چاپ و Print Agent
- صفحه `admin/printing.php` به Operational Console با سه سطح «نمای کلی»، «تنظیمات چاپ» و «عیب‌یابی» تبدیل شد.
- Incident/Jobهای نیازمند رسیدگی و فعالیت اخیر در نمای روزمره بر تنظیمات فنی مقدم‌اند.
- Agent و مقصدهای چاپ در حالت عادی Summary خواندنی دارند؛ ویرایش مقصد Edit-on-demand است.
- «تنظیمات پیشرفته چاپ» داخل «تنظیمات چاپ» و به‌صورت disclosure باقی مانده است.
- Diagnostics فنی Agent/API و Print Test واقعی در بخش «عیب‌یابی» جدا شده‌اند.
- Recommended Agent به `6.1.0` pin شده و دانلود فقط به GitHub Release نسخه‌دار `v6.1.0` متصل است. Minimum سازگار `6.0.0` باقی مانده و Protocol همچنان v4 است.
- UI همچنان `submitted` را «تحویل به صف چاپ رایانه» می‌داند و هیچ ادعای تأیید چاپ فیزیکی ندارد؛ `unknown/recovery_hold` نیازمند تصمیم انسانی‌اند.

## Quick Order
- `flex-grow` ناخواسته Stepper بیرون‌بر در Mobile/Tablet از Root حذف شد؛ کادر Stepper فقط به اندازه کنترل خودش می‌ماند.
- رفتار Read-only تعداد کل در Takeaway editing که در 1.36.2 اضافه شده بود حفظ شده است.

## خرید
- Action طولانی «اشتراک فهرست در حال خرید» به آیکن مرکزی Share + متن کوتاه «اشتراک» تبدیل شد.
- Action اشتراک Secondary و compact است و «درخواست خرید» وزن عملیاتی اصلی خود را حفظ می‌کند.

## Update
- Baseline رسمی: `1.36.2`
- مقصد: `1.36.3`
- Updater Engine: `1.5.3`
- Migration دیتابیس: ندارد

## UAT مستقل Agent
Agent 6.1.0 از GitHub Release رسمی نسخه‌دار توزیع می‌شود و CI Build/Test/Windows install gate را گذرانده است. UAT چاپگر فیزیکی شامل RTL، Kitchen/Bar/Customer، Paper Out/Offline، Spooler/Windows restart، network recovery، 50 چاپ متوالی و soak همچنان قبل از Production rollout الزامی است.
