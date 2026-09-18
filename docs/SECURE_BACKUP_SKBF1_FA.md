# قرارداد پشتیبان امن خارج از سرور — SKB1

`1.36.4-dev.19` فرمت داخلی Backup را تغییر نمی‌دهد. فایل‌های داخل `storage/backups` همچنان `sokna-backup-v3` و `tar.gz` هستند و توسط همان validator/restore engine کنترل می‌شوند.

برای انتقال خارج از سرور، مدیر از Maintenance یک Recovery Passphrase تعیین می‌کند. برنامه یک envelope با magic `SOKNA-SKB1`, header JSON محدود، Argon2id-derived key و XChaCha20-Poly1305 SecretStream می‌سازد. داده chunked است و هر frame authenticated می‌شود. رمز در DB، Settings یا Audit ذخیره نمی‌شود.

## قواعد بازیابی

- `.skb` فقط با همان رمز قابل بازکردن است.
- رمز اشتباه، فایل دست‌کاری‌شده، frame نامعتبر، truncation یا trailing bytes رد می‌شوند.
- خروجی موقت ناقص در هر خطا حذف می‌شود.
- پس از decrypt موفق، archive هنوز باید `maintenance_validate_archive` و compatibility check جاری را پاس کند؛ موفقیت crypto به‌تنهایی مجوز Restore نیست.
- `.tar.gz` قدیمی برای Import نسخه‌های قبلی در وضعیت Pre-Operational پذیرفته می‌شود، اما UI خروجی جدید plaintext ارائه نمی‌کند.

## Runbook مدیر

1. Backup سالم جاری را انتخاب کنید و «دانلود امن» را بزنید.
2. یک رمز بازیابی قوی و یکتا تعیین کنید و آن را جدا از فایل نگه دارید.
3. فایل `.skb` را در محل مستقل از هاست نگهداری کنید.
4. برای تست دوره‌ای، فایل را روی نصب جداگانه Upload، رمز را وارد و پس از validation فرآیند Restore را اجرا کنید.
5. گم‌شدن رمز قابل بازیابی توسط Sokna نیست.
