# امنیت و محدودیت‌های جاری Sokna 1.36.0

## کنترل‌های فعال
- Prepared Statement و Transaction
- CSRF و Role/Capability checks
- Password hashing
- HTML escaping
- Session/Cookie controls و Guard مستقیم HTTP برای runtime storage (Session/Updater/Backup)
- MIME/size validation برای Upload و جلوگیری از اجرای PHP در uploads
- Server-side price/inventory/time/order validation
- Idempotency در مسیرهای حساس سفارش/Integration
- CSV safe-cell handling
- Update package manifest + SHA-256 + protected paths + rollback/health checks
- Push transactional outbox؛ شبکه خارجی در Request سفارش اجرا نمی‌شود
- Backup/Stage خارج از مسیر عمومی و Restore fail-closed
- Audit عملیاتی/مالی برای رویدادهای حساس

## محدودیت‌های شناخته‌شده
- Login اصلی Rate Limit سبک در سطح Application دارد (IP+حساب و سقف IP). این کنترل جای WAF/Rate limit لبه شبکه را در برابر حملات توزیع‌شده نمی‌گیرد.
- غیرفعال‌کردن حساب لزوماً Session باز قبلی را فوراً revoke نمی‌کند.
- امضای دیجیتال بسته Update وجود ندارد؛ بسته باید فقط از منبع مورد اعتماد دریافت شود.
- Web Push به HTTPS، مجوز مرورگر، سرویس Push و دسترسی شبکه هاست وابسته است.
- Sokna برای کافه تک‌شعبه و مقیاس متعارف طراحی شده؛ Multi-branch/Microservice هدف فعلی نیست.
- Integration اقامتگاه به سرویس خارجی وابسته است و نتیجه نامشخص باید طبق قرارداد Retry/Follow-up مدیریت شود.
- Print Agent جریان پذیرش مستقل دارد؛ PASS شدن Web/PWA به‌تنهایی محیط واقعی Windows/Printer را Production-certified نمی‌کند.

## داده و حریم عملیاتی
- تاریخچه مالی اصلی حذف نمی‌شود.
- Analytics رفتار مهمان Aggregate و بدون هویت مستقیم مشتری نگهداری می‌شود.
- Secretها نباید در HTML/JavaScript/Log یا بسته Release افشا شوند.
