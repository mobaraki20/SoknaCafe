# نقشه پروژه Sokna 1.36.0

- `admin/` — مدیریت، مالی، گزارش، تنظیمات، QR و زیرساخت
- `operator/` — API و جریان کار روزانه/حساب میز
- `staff/` — Quick Order و جریان کارکنان
- `waiter/` — آماده‌سازی
- `api/` — APIهای عمومی/مهمان
- `includes/` — منطق مشترک، مالی، زمان کسب‌وکار، Integration و UI shell
- `assets/` — CSS/JS/Icon/Frontend owners
- `database/schema.sql` — Schema جاری Fresh Install
- Migration Update در صورت نیاز داخل Artifact همان Release حمل می‌شود؛ آرشیو تاریخی در Git است
- `includes/updater_engine/1.5.3/` — موتور جاری RC4؛ در مسیر `RC3 → RC4` همان Engine فعال باقی می‌ماند. `1.5.2` و `1.5.1` fallbackهای immutable نسل‌های قبلی‌اند.
- `tools/` — Release builder، Workerها و ابزارهای نگهداری
- `tests/` — Unit/Contract/Browser/Responsive/Release tests
- `print-agent/` — جریان مستقل Agent Windows؛ جریان مستقل Agent Windows با تست و پذیرش جدا از Web/PWA
- `docs/` — مستندات فعال پروژه
