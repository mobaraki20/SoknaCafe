# SOKNA — GitHub Handoff Protocol

این پوشه بخشی از مرجع ادامه پروژه است. برای snapshot بازیابی‌شده 2026-09-24، `WORKSPACE_START_HERE_FA.md` و `START_HERE_NEXT_AGENT_FA.md` بر متن‌های GitHub-only تاریخی مقدم‌اند.

## نقطه شروع اجباری
هر ایجنت جدید باید ابتدا exact source را از handoff نهایی بازیابی و `git rev-parse HEAD` / `git status` را بررسی کند؛ سپس به این ترتیب بخواند:
1. `WORKSPACE_START_HERE_FA.md`
2. `docs/handoffs/START_HERE_NEXT_AGENT_FA.md`
3. `docs/handoffs/MASTER_HANDOFF_FA.md`
4. `docs/handoffs/CURRENT_STATUS_FA.md`
5. handoff آخرین Phase/Subphase معرفی‌شده در همان فایل
6. `DEVELOPER_READ_FIRST_FA.md`
7. `docs/architecture-migration-r2/IMPLEMENTATION_PLAN_FA.md`
8. checkpoint همان Phase در `docs/architecture-migration-r2/`

## قانون به‌روزرسانی
از Phase 6A به بعد، هر PR مربوط به Phase/Subphase باید قبل از Merge:
- یک handoff اختصاصی در این پوشه بسازد/به‌روز کند.
- `CURRENT_STATUS_FA.md` را به commit/PR/CI و نقطه ادامه جدید به‌روزرسانی کند.
- تصمیم‌های باز، UATهای باقی‌مانده و فایل‌های owner را صریح ثبت کند.
- هیچ claim «کامل شد» بدون CI سبز روی head نهایی و CI post-merge روی `main` ثبت نشود.

## هدف
اگر دسترسی به ایجنت قبلی قطع شد، ایجنت بعدی نباید به تاریخچه ChatGPT نیاز داشته باشد؛ GitHub باید برای ادامه کافی باشد.
