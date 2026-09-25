# به‌روزرسانی و بازگشت Sokna

## مسیر واحد

همه عملیات فقط از این مسیر انجام می‌شوند:

`/admin/update/`

کاربر یک بسته ZIP استاندارد را انتخاب می‌کند، بررسی را می‌بیند، یک تیک تأیید می‌زند و نصب را آغاز می‌کند. cPanel، File Manager و Runtime جداگانه بخشی از فرایند عادی نیستند.

## معماری ایمن

1. Loader ثابت، موتور فعال و موتور قبلی را می‌شناسد.
2. بسته، فایل‌ها و موتور مقصد را در Stage جداگانه قرار می‌دهد.
3. Manifest، Hash، Syntax، نسخه مبدا، فضای دیسک و دسترسی نوشتن بررسی می‌شوند.
4. Restore Point فقط از فایل‌های درگیر ساخته می‌شود؛ دیتابیس فقط در صورت Migration پشتیبان می‌شود.
5. Maintenance کوتاه فعال و فایل‌های برنامه نصب می‌شوند.
6. Health Check اجرا می‌شود؛ علاوه بر فایل‌ها و DB پایه، Contract خواندنی Schema جاری نیز در صورت وجود بررسی می‌شود.
7. موتور جدید پس از موفقیت، اتمی فعال می‌شود.
8. در شکست، Rollback خودکار انجام می‌شود؛ Loader می‌تواند موتور قبلی را اجرا کند.

## ممنوعیت‌ها

- بسته عادی نباید فایل‌های موتور فعال را حذف یا جایگزین کند.
- مسیرهای `admin/update.php` و `includes/updater_runtime.php` بازنشسته و ممنوع‌اند.
- هیچ Patch دستی یا Runtime cPanel در انتشار عادی ارائه نمی‌شود.

## سیاست نسخه‌های توسعه‌ای پیش از Go-Live

تا قبل از راه‌اندازی عملیاتی، Sokna تعهدی به نگهداری مسیرهای Legacy یا داده آزمایشی قدیمی ندارد؛ فقط مسیر ارتقا از **آخرین نسخه‌ای که واقعاً نصب شده** باید سالم بماند.

هر Artifact توسعه‌ای که قابل نصب است باید شماره نسخه یکتا داشته باشد. استفاده دوباره از یک شماره مثل `1.36.0-dev` برای چند Build ممنوع است، چون Updater مقصد هم‌نسخه را عمداً رد می‌کند.

زنجیره نسخه‌های پیش‌عملیاتی تا checkpoint جاری در تاریخچه Git و Release Notes نگهداری می‌شود؛ سند عملیاتی نباید با فهرست‌کردن دستی تمام نسخه‌های قدیمی منسوخ شود.

checkpoint جاری engineering/installable: `1.36.4-dev.39`

آخرین مسیر تاریخی Web/PWA که در این سند migration آن صریحاً ثبت شده بود:

`1.36.4-dev.30 → 1.36.4-dev.31`

از `dev.38 → dev.39` شماره نسخه برای Windows Phase 8B Closure یکتا شده است، اما این به‌تنهایی به معنی آماده‌بودن Web Updater package نیست. Update Package dev.39 فقط پس از Freeze شدن migration/package manifest همان release ساخته می‌شود؛ Installer/Repair نباید از روی این متن migration قدیمی حدس بزند.

این checkpoint **Migration دیتابیس دارد**:
- Local: جداول Expenses، Deferred receipt/review و Financial Close Override.
- Public: جدول مستقل `deferred_work`.
- migration source فعلی Local برای ساخت Update Package در `docs/architecture-migration-r2/PHASE5_LOCAL_MIGRATION.sql` نگه‌داری می‌شود؛ آرشیو migration تاریخی در Source فعال ساخته نمی‌شود.
- Public schema migration: `public_edge/database/migrations/005_phase5_deferred_work.sql`.

قانون نصب: Update Package باید قبل از activation Restore Point بسازد و Local schema migration را اتمیک اجرا کند. Public component باید schema/edge سازگار Phase 5 داشته باشد؛ در صورت عدم دسترسی Public، Local core بالا می‌ماند اما normal financial-period close تا شناخته‌شدن وضعیت Public طبق قرارداد Phase 5 مسدود می‌شود.

Open table/order/account به‌تنهایی blocker Update نیست؛ فقط commit درحال‌پرواز باید برای cutover کوتاه quiesce شود.
