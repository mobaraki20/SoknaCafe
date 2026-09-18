# هنداور توسعه Sokna 1.36.4-dev.1

## هویت Build
- نوع: Pre-Operational test checkpoint
- نسخه مقصد: `1.36.4-dev.1`
- نسخه مبدا قابل نصب: `1.36.3`
- Updater Engine: `1.5.3`
- Migration: دارد؛ Canonical Schema مربوط به Batch B را روی نصب تستی موجود اعمال می‌کند.

## محتوای Checkpoint
- Root-Cause Cleanup Batch A و Batch B.
- Baseline Browser root fixes برای Guest/Jalali.
- استخراج Ownerهای کم‌ریسک Jalali, Media, Favicon, Audit, Messages و Events از Shared Hotspot.
- هیچ Redesign عمدی یا تغییر جایگاه/Flow رابط در Scope این Checkpoint وجود ندارد.

## قید Migration
رکوردهای آزمایشی قدیمی که Business Snapshot ندارند، بدون جعل Shift تاریخی به `outside / خارج از شیفت` منتقل می‌شوند و Business Date بر اساس Cutoff ذخیره‌شده فعلی محاسبه می‌شود. اگر چند شمارش Legacy هم‌زمان باز باشد، جدیدترین Draft حفظ و Draftهای قدیمی‌تر cancelled می‌شوند. Updater پیش از Migration Restore Point دیتابیس می‌سازد.

## ادامه توسعه
قابلیت بعدی باید از همین Checkpoint شروع شود. تغییرات مالی/Settlement نباید Owner موازی ایجاد کنند و UI/Behavior Preservation تا Approval صریح Redesign برقرار است.
