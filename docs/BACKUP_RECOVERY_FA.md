# قرارداد Backup/Recovery سکنا

مرجع عملیاتی: `RECOVERY_OPERATIONS_FA.md`.

قرارداد نسخه جاری:
- یک Backup قابل‌حمل و یک UI؛ DB + uploads + هویت داخلی لازم برای بازیابی کامل.
- ساخت دستی و خودکار یک Owner و یک Validator دارند.
- cadence داخلی ۶ ساعت و نگهداری ۲۸ Recovery Point عادی؛ نقطه بازگشت Restore فقط internal است.
- Client filename، MIME و پسوند موقت Upload مورد اعتماد نیستند؛ فایل ابتدا با نام کنترل‌شده `.tar.gz` stage و سپس validate می‌شود.
- Backup نهایی فقط بعد از Exact Archive Validation و SHA-256 Entryها سالم اعلام می‌شود.
- Restore fail-closed است، قبل از اعمال Snapshot بازگشت داخلی می‌سازد و پس از موفقیت Session را باطل می‌کند.
- DB credential، URL و سایر تنظیمات environment سرور مقصد در Restore overwrite نمی‌شوند.
- Restore فقط روی Web version/schema یکسان مجاز است؛ Upgrade عملیات جداست.
- فایل Backup محرمانه است و باید خارج از سرور نیز در محل امن نگهداری شود.
