> **مرجع فعلی تطبیق — 2026-09-25:** شاخه منتشرشده `work/reconcile-dev39` در PR #19، نسخه سورس dev.39، مبتنی بر تاریخچه گیت‌هاب و پیشرفت‌های بسته. ابتدا `docs/handoffs/DEV39_RECONCILIATION_2026-09-25_FA.md` را بخوانید (مسیر نسبت به ریشه مخزن). ادامه‌ها و دستورهای متعارض زیر سوابق تاریخی‌اند؛ Phase8B کامل نشده، WiX مجوز اجرا نگرفته و شواهد CI جدید و کارهای باز در مرجع فوق و جدول پذیرش 2026-09-25 ثبت شده‌اند.

# SOKNA Cafe — Workspace Start Here

**تاریخ:** 2026-09-24
**وضعیت:** توسعه محلی فعال؛ GitHub در این ورک‌اسپیس read/write مستقیم نیست.
**Canonical recovery baseline:** `75421078d8e944a33f58cbab96723c63480c4f5e` روی `work/r2-reconciliation-ui-foundation`.
**Active continuation branch:** `work/win06-handoff-hardening` (baseline + reviewed WIN-06 continuation).
**Product version:** `1.36.4-dev.38`.
**GitHub note:** historical upstream snapshots داخل handoff فقط provenance هستند؛ `git rev-parse HEAD` و handoff نهایی مرجع ادامه‌اند.

## ترتیب مرجع
1. تصمیم‌های مالک ثبت‌شده در `docs/handoffs/LOCAL_WORKSPACE_BASELINE_2026-09-24_FA.md` و اسناد همین ادامه.
2. کد فعلی این ورک‌اسپیس.
3. R2 Architecture contracts در `docs/architecture-migration-r2/` و بسته مستقل R2 در Handoff خارجی.
4. `dev.26` فقط برای Business Behavior و UI DNA / provenance؛ نه Working Source.

## تصمیم قطعی UI
تمام نسخه‌ها و بسته‌های قبلی با عنوان **SOKNA Design System** رد شده‌اند و Design Authority نیستند.
Design System جدید مخصوص **SOKNA Cafe** از DNA مفید `dev.26`، نیازهای واقعی Workflow، قواعد فارسی/RTL، accessibility و استانداردهای مهندسی ساخته می‌شود.
هیچ ایراد UI در dev.26 یا در migration فعلی حق انتقال کورکورانه ندارد: `Audit -> Correct -> Standardize -> Migrate`.

## شروع توسعه
ابتدا بخوان:
- `docs/handoffs/LOCAL_WORKSPACE_BASELINE_2026-09-24_FA.md`
- `docs/architecture-migration-r2/R2_IMPLEMENTATION_RECONCILIATION_2026-09-24_FA.md`
- `docs/ui-design-system/CANONICAL_DESIGN_SYSTEM_CONTRACT_FA.md`
- `docs/ui-design-system/UI_DEBT_BASELINE.json`
- `docs/ui-design-system/COMPONENT_REGISTRY.json`

## قرارداد persistence
هر checkpoint معنادار باید شامل commit محلی، تست واقعی، Handoff، SHA256 و در تحویل خارجی ZIP کامل + `git bundle` باشد. Workspace موقت Source of Truth دائمی نیست.
