# Pre-Launch Cleanup — Sokna 1.36.0-dev.2

## قاعده
تا قبل از اعلام صریح Go-Live، Git/Tag مرجع تاریخچه انتشار است؛ Working Tree فقط قرارداد جاری را نگه می‌دارد. سازگاری با Releaseهای آزمایشی قدیمی الزام نیست.

## حذف‌شده از Source جاری
- آرشیو `database/migrations/` و `migrations/`؛ `database/schema.sql` منبع نصب تمیز است و Migration هر Update فقط داخل همان بسته Release حمل می‌شود.
- موتورهای Updater قبل از `1.5.1` حذف شده‌اند؛ در dev.9 موتور جاری `1.5.2` و `1.5.1` فقط fallback بلافصل است.
- اسناد Release/Scope/Test/Go-Live نسخه‌های قدیمی؛ تاریخچه در Git باقی می‌ماند.
- fallback پارامتر گزارش `?days=`، مسیر قدیمی دانلود Template چاپ، تبدیل قدیمی هزینه رویداد از متن ورود و legacy setting/defaultهای متن مهمان.
- metadata مربوط به Tableهای بازنشسته در Module Registry.
- Compatibility-only helperهای Quick Order، پارامتر بلااستفاده Session و Group Key قدیمی `need:{id}` در Supply.

## عمداً حذف‌نشده
- تاریخچه مالی، Audit، Settlement و اسناد عملیاتی جاری؛ این‌ها Legacy نیستند و بعد از Go-Live برای کنترل و حسابرسی لازم‌اند.
- منطق Recovery/Backup و ابهام چاپ؛ حذف آن‌ها قابلیت اطمینان را کم می‌کند.
- fallbackهای ایمنی‌ای که به Failure واقعی محیط مربوط‌اند، نه Compatibility نسخه قدیمی.
- Updater Engine `1.5.1` درجا تغییر نکرد؛ dev.9 قابلیت جدید را در Engine جانبی `1.5.2` می‌آورد و 1.5.1 تا تأیید فعال‌سازی 1.5.2 به‌عنوان fallback حفظ می‌شود.

## Updater
Release builder از این نسخه می‌تواند فایل موتورهای Updater قدیمی را در Update حذف کند، اما Loader و Engine در حال اجرای `1.5.1` حین نصب محافظت می‌شوند؛ مقصد `1.5.2` side-by-side نصب می‌شود. `min_updater` همچنان `1.5.1` است.

## وضعیت RC3
در RC3، Engineهای `1.5.1` و `1.5.2` برای ایمنی بازیابی بدون تغییر نگه داشته شده‌اند. تغییرات جدید Updater در `1.5.3` قرار دارند و در مسیر `RC2 → RC3` به‌صورت side-by-side نصب و فقط پس از Health Check فعال می‌شوند.

## وضعیت RC4

RC4 موتور Updater یا Schema را تغییر نمی‌دهد. مسیر جاری `RC3 → RC4` با Engine فعال `1.5.3` اجرا می‌شود؛ Engineهای fallback همچنان immutable هستند. این نسخه فقط Handoff رابط، قراردادهای دسترس‌پذیری و تراکم عملیاتی را harden می‌کند.
