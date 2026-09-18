# Sokna 1.36.4-dev.17 — Menu / Recovery Consolidation

## تغییرات اصلی
- Catalog واحد برای منوی مهمان، QR میز و سفارش سریع؛ حذف Query/قواعد موازی Catalog.
- مدل داده‌محور `menus / menu_categories / menu_items` با منوهای اولیه کافه، صبحانه و ناهار.
- دسته‌های canonical با `category_key` پایدار و audience مستقل؛ preparation station از taxonomy نمایشی جداست.
- Source of Truth منو با 128 آیتم ارسالی مالک، 109 فعال و 19 غیرفعال.
- Menu Manager یکپارچه با Search، Filter، Sort، Bulk membership و چیدمان دسته/آیتم؛ صفحات قدیمی ترتیب حذف شدند.
- مسیر canonical منوی عمومی/میز فقط `/Menu/`؛ route قدیمی lowercase حذف شد.
- Import/Export منو بر مبنای `category_key` و `menu_keys`، نه نام نمایشی دسته.
- Backup/Restore یکپارچه: یک فرمت پشتیبان کامل، Upload مستقیم، cadence شش‌ساعته و recovery point داخلی پیش از Restore.
- حفظ خودکار identity لازم برای Secretهای برنامه هنگام انتقال سرور، بدون درگیرکردن مدیر با `app.key`.
- UI چاپ با Agent 6.2.0 همگام شد؛ Reorder CSS و تاریخچه قالب موبایل/RTL اصلاح شد.
- Migration dev.16→dev.17 شامل Menu schema/data backfill و repair محدود provenance قالب استاندارد چاپ است.

## مرز اعتبارسنجی
- تست‌های source/static/browser قابل اجرای محیط توسعه باید قبل از بسته‌بندی PASS شوند.
- اجرای واقعی Migration/Restore روی MySQL/MariaDB در محیط فعلی در دسترس نیست و `NOT_RUN` است.
- Printer/Spooler/Local Network Access/Android UAT واقعی `UAT_REQUIRED` است.
- این نسخه تا عبور از UATهای محیط واقعی Production-ready اعلام نمی‌شود.
