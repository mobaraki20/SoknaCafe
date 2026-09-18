# Database — Sokna 1.36.3 — Pre-Operational

`database/schema.sql` تنها Source of Truth نصب تمیز است.

در دوره Pre-Operational آرشیو Migrationهای نسخه‌های آزمایشی داخل Source نگه‌داری نمی‌شود. اگر یک Update جدید به تغییر Schema نیاز داشته باشد، Migration همان Release به‌صورت idempotent داخل Update ZIP حمل می‌شود و پس از عبور آن checkpoint لازم نیست به آرشیو دائمی Working Tree تبدیل شود؛ Git تاریخچه را نگه می‌دارد.

قواعد ثابت:
- عملیات مالی/Inventory حساس transactional است.
- Inventory balance از Movement contract تغییر می‌کند.
- Migration اجراشده باید idempotent و قابل rollback/recovery باشد.
- Fresh schema و Target source باید با رفتار Update نهایی هم‌ارز باشند.

## تغییر Schema در dev.8

`settlement_records.request_id` به‌صورت nullable و با Unique Index `uq_settlement_request_id` اضافه شده است. این شناسه برای Idempotency و بازیابی نتیجه نامعلوم تسویه مستقیم/مشترک است؛ سندهای قدیمی می‌توانند `NULL` بمانند. Migration بسته Update با `information_schema` وضعیت ستون/Index را بررسی می‌کند تا اجرای مجدد همان Migration destructive نباشد. اجرای آن روی MariaDB/MySQL واقعی تا زمان UAT، `UAT_REQUIRED` است.
## Health Contract در dev.9

Updater بعد از نصب dev.9 علاوه بر اتصال DB و Tableهای پایه، `includes/schema_health.php` را نیز به‌صورت مستقل اجرا می‌کند. Contract جاری به‌صورت read-only وجود و شکل `settlement_records.request_id` و Unique Index `uq_settlement_request_id` را از `information_schema` بررسی می‌کند. اگر Schema حیاتی با Source جاری هم‌خوان نباشد، Health Check موفق اعلام نمی‌شود. این کنترل جای UAT مالی را نمی‌گیرد، اما جلوی «نصب موفق ظاهری با Schema ناقص» را می‌گیرد.


## Pre-Operational Batch B schema contract

در Baseline جاری، `cafe_tables.table_number` و چهار فیلد Snapshot عملیاتی (`business_date`, `business_shift_key`, `business_shift_label`, `business_cutoff_snapshot`) برای `table_sessions`, `orders`, `waiter_calls`, `settlement_records` اجباری هستند. Runtime نباید برای رکورد ناقص به Name/`started_at`/`settled_at` fallback کند.

`includes/schema_health.php` این Nullability contract را از `information_schema` به‌صورت read-only بررسی می‌کند. اگر DB آزمایشی قدیمی هنوز Schema قبلی را داشته باشد، Health باید mismatch را نشان دهد؛ Compatibility branch داخل Runtime راه‌حل نیست.

چون سامانه هنوز Go-Live نشده است، در محیط‌های آزمایشی قابل دورریختن، بازسازی DB از `database/schema.sql` مسیر ترجیحی است. اگر حفظ دیتای آزمایشی لازم باشد، Migration همان Release باید کنترل‌شده و idempotent باشد و بعد از عبور checkpoint به آرشیو دائمی Source تبدیل نشود.
