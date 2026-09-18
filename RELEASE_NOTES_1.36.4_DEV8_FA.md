# Sokna Cafe 1.36.4-dev.8 — Test RC

این نسخه فقط برای تست Pre-Operational است.

## تغییرات اصلی
- Mobile cashier: بازگشت Header/Footer حساب میز به ساختار تأییدشده، نمایش مبلغ در CTA و حذف اطلاعات تکراری.
- Itemized settlement: Mobile sheet فشرده، CTA شامل count/payable server review، تداوم پرداخت جزئی و قلم جاافتاده.
- Late accounting: Quick Order عادی موبایل Freeze؛ فقط Flow قلم جاافتاده بهینه شده است.
- Accommodation: Error classification بر اساس API 2.0 House، tracking_id end-to-end، deterministic 503ها بدون Pending کاذب.
- Root-cause cleanup: CSSهای Mobile Preview چندلایه Consolidate شده‌اند؛ مسیر دوم ساخته نشده است.
- Quick Order Desktop: اکشن پاک‌کردن سبد در منوی بیشتر با وضعیت Undo همان Owner همگام شد؛ تست قدیمی دکمه مستقیم به Contract واقعی Overflow منتقل شد.

## Update
مسیر رسمی تستی: `1.36.4-dev.7 → 1.36.4-dev.8`، بدون Migration دیتابیس Cafe.

## UAT_REQUIRED
- Charge/void واقعی House با Network interruption.
- حساب مشترک واقعی و Idempotency.
- 360/390/412 روی گوشی واقعی، Touch Desktop و 1366/1440/1920.
- Windows Print Agent و Printer.
- MySQL/MariaDB واقعی + Backup/Restore.
