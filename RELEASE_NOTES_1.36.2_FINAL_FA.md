# یادداشت انتشار Sokna 1.36.2

`1.36.2` نسخه اصلاحی روی `1.36.1` است. این Release فقط اصلاحات ریشه‌ای رابط و Observability چاپ را جمع می‌کند و Migration دیتابیس ندارد.

## اصلاحات اصلی

- مرور سفارش مهمان: بخش «بیرون‌بر» تفکیک بصری ملایم‌تری دارد و «افزودن یادداشت سفارش» به یک ردیف فشرده و قابل لمس تبدیل شده است؛ Owner تکراری CSS یادداشت حذف شده است.
- ثبت سریع کارکنان: در حالت ویرایش بیرون‌بر، تعداد کل سفارش Read-only است و فقط تعداد بیرون‌بر تغییر می‌کند؛ Action اصلی «تأیید» در ترتیب صحیح RTL قرار گرفته و Layout تا Tablet بدون فشردن عنوان قلم پایدار است.
- خرید: فهرست «در انتظار خرید» از Card-in-Card و ارتفاع اجباری خارج شده و به ردیف‌های content-driven و متراکم تبدیل شده است؛ در عرض بسیار کم برای خوانایی Stack می‌شود.
- Print Agent Diagnostics: Heartbeat v4 می‌تواند اطلاعات اختیاری سلامت Transport را در همان `health_json` موجود ثبت کند و پنل چاپ وضعیت Healthy/Degraded/Attention را بدون ایجاد Owner یا Schema جدید نمایش می‌دهد.
- Contract تست Supply اصلاح شد تا قابلیت واقعی درخواست چندقلمی را از روی Form/Renderer/Processing بررسی کند، نه Copy قدیمی رابط.
- Release identity tests از hard-code نسخه قبلی جدا شدند و `VERSION.txt` مرجع هویت جاری باقی می‌ماند.

## مسیر ارتقا

`1.36.1 → 1.36.2` از مسیر `/admin/update/` با Updater Engine `1.5.3` و بدون Migration دیتابیس.

## Print Agent

این Release، Agent را داخل Cafe bundle نمی‌کند و نسخه رسمی Agent را به‌صورت خودکار جلو نمی‌برد. قرارداد Print API v4 حفظ شده و فیلدهای Health جدید اختیاری و backward-compatible هستند. Release مستقل Agent 6.1 تابع UAT ویندوز/پرینتر است.

## محدودیت پذیرش

PASS Source/Browser/Updater gate جای UAT واقعی روی Host، گوشی، Service Worker و پرینتر فیزیکی را نمی‌گیرد. بسته‌ها برای نصب کنترل‌شده و تست آماده‌اند؛ Go-Live تا اعلام صریح Owner انجام نشده است.
