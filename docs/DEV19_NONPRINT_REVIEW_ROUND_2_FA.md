# بازبینی دور دوم dev.19 — نقد تصمیم‌های دور اول

## نقد معماری

**گزینه ردشده: سه Catalog مستقل.** مزیت ظاهری آن ساده‌شدن هر منو است، اما Source of Truth کالا را چندگانه می‌کند و برای مقیاس یک مجموعه سکنا هزینه نگهداری بیشتری از منفعت دارد. Square نیز Menu را presentation/channel/daypart جدا از Item Library و categoryهای عملیاتی نگه می‌دارد؛ Odoo نیز Product را مرکزی و POS Category را لایه سازمان‌دهی می‌کند.

**گزینه ردشده: subsystem جدید Backup.** رمزنگاری نباید scheduler/retention/restore engine دوم بسازد. envelope امن فقط در مرز Export/Import قرار می‌گیرد و داخل سرور همان `sokna-backup-v3` باقی می‌ماند.

## نقد UX

**ریسک:** اجباری‌کردن رمز برای هر Backup داخلی، عملیات روزانه را شکننده می‌کند. نتیجه: رمز فقط هنگام off-server Export لازم است. Backup خودکار و Recovery Point بدون prompt ادامه دارند.

**ریسک:** compact کردن همه Stepperها برای density، touch target را خراب می‌کند. نتیجه: compact فقط در product-card؛ cart/final correction حداقل 44px را حفظ می‌کنند.

## نقد امنیت

**گزینه ردشده: نگهداری recovery key در همان config سرور.** در compromise/ازبین‌رفتن کامل سرور، فایل off-server و key ممکن است با هم در معرض خطر یا از دسترس خارج شوند. برای این deployment کوچک، passphrase تحت کنترل مدیر و خارج از سامانه trade-off مناسب‌تری است.

**ریسک:** رمزنگاری کل فایل در RAM. نتیجه: SecretStream chunked استفاده می‌شود و اندازه/فریم‌ها قبل از پردازش محدود هستند.

**ریسک:** ciphertext دست‌کاری‌شده به tar validator برسد. نتیجه: authentication باید قبل از پذیرش archive کامل شود و فایل plaintext ناقص در خطا حذف شود.

## نقد عملیات/پشتیبانی

اضافه‌کردن Sodium می‌تواند بعضی هاست‌ها را ناسازگار کند. این یک هزینه واقعی است؛ به‌جای fallback رمزنگاری ضعیف، preflight Installer/Updater آن را آشکار و fail-fast می‌کند. چون محصول هنوز Pre-Operational است، این dependency اکنون قابل تثبیت است؛ قبل از Go-Live باید روی هاست هدف تأیید شود.
