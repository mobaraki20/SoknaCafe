# Sokna 1.36.4-dev.19

این نسخه یک checkpoint پیش‌عملیاتی غیرچاپی است و روی `1.36.4-dev.18` ساخته شده است. هیچ تغییر Schema یا Migration دیتابیس ندارد.

## تغییرات

- Stepperهای «تعداد نهایی» و «تعداد آماده‌شده» در اصلاح تعداد به یک Owner هندسی مشترک منتقل شدند؛ Rule موازی قدیمی حذف شد تا در موبایل و دسکتاپ هم‌تراز باقی بمانند.
- Rule فشرده Stepper کارت محصول در Quick Order فقط به خود کارت محصول محدود شد؛ Stepper سبد دسکتاپ 132×44 و Touch Targetهای 44px را حفظ می‌کند.
- دانلود مستقیم `.tar.gz` از صفحه Maintenance حذف شد. نسخهٔ خارج از سرور اکنون با رمز بازیابی‌ای که مدیر همان لحظه تعیین می‌کند به `.skb` رمزگذاری‌شده تبدیل می‌شود.
- فرمت `.skb` از Argon2id برای KDF و XChaCha20-Poly1305 SecretStream برای رمزنگاری authenticated/chunked استفاده می‌کند. رمز در DB/Settings/Audit ذخیره نمی‌شود.
- Upload فایل `.skb` با رمز بازیابی پشتیبانی می‌شود و پس از decrypt موفق، همان validator و همان مسیر Restore موجود را مصرف می‌کند. `.tar.gz` قدیمی فقط برای Import/Recovery سازگاری قبل از Go-Live باقی مانده است؛ Export جدید plaintext نیست.
- Sodium به preflight نصب و manifest رسمی Update اضافه شد. اگر این extension در محیط مقصد فعال نباشد، Install/Update باید پیش از اعمال تغییر fail شود.
- Catalog/Menu/Search/Filter/Sort، `/menu/` canonical و مدل audience/membership dev.18 بازنویسی نشدند؛ Audit نشان داد معماری موجود برای مقیاس سکنا مناسب است و فقط Regression لازم دارد.

## سازگاری

Upgrade رسمی: `1.36.4-dev.18 → 1.36.4-dev.19`، بدون migration دیتابیس.

Backup داخلی روی سرور همچنان `sokna-backup-v3` است. فایل `.skb` فقط envelope امن برای جابه‌جایی خارج از سرور است. برای Restore فایل امن، رمز بازیابی لازم است و گم‌شدن آن قابل جبران توسط سکنا نیست.

## وضعیت پذیرش

Gateهای مستقل از محیط باید قبل از تحویل اجرا شوند. MySQL/MariaDB واقعی، Restore روی نصب دوم، و UAT انسانی/دستگاه واقعی همچنان جداگانه `UAT_REQUIRED` هستند.
