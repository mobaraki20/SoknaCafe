# SOKNA — GitHub Handoff Protocol

این پوشه مرجع رسمی ادامه پروژه برای هر ایجنت بعدی است.

## نقطه شروع اجباری
هر ایجنت جدید بعد از اتصال به GitHub باید به این ترتیب بخواند:
1. `docs/handoffs/CURRENT_STATUS_FA.md`
2. handoff آخرین Phase/Subphase معرفی‌شده در همان فایل
3. `DEVELOPER_READ_FIRST_FA.md`
4. `docs/architecture-migration-r2/IMPLEMENTATION_PLAN_FA.md`
5. checkpoint همان Phase در `docs/architecture-migration-r2/`

## قانون به‌روزرسانی
از Phase 6A به بعد، هر PR مربوط به Phase/Subphase باید قبل از Merge:
- یک handoff اختصاصی در این پوشه بسازد/به‌روز کند.
- `CURRENT_STATUS_FA.md` را به commit/PR/CI و نقطه ادامه جدید به‌روزرسانی کند.
- تصمیم‌های باز، UATهای باقی‌مانده و فایل‌های owner را صریح ثبت کند.
- هیچ claim «کامل شد» بدون CI سبز روی head نهایی و CI post-merge روی `main` ثبت نشود.

## هدف
اگر دسترسی به ایجنت قبلی قطع شد، ایجنت بعدی نباید به تاریخچه ChatGPT نیاز داشته باشد؛ GitHub باید برای ادامه کافی باشد.
