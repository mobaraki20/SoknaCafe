# R2 Implementation Reconciliation — 2026-09-24

## هدف
شماره Phase یا merge تاریخی به‌تنهایی Completion نیست. این سند تفاوت «چیزی که R2 الزام کرده» با «چیزی که در سورس dev.38/8B وجود دارد» را برای ادامه توسعه ثبت می‌کند.

## وضعیت فازها
| Scope | وضعیت کد فعلی | تصمیم ادامه |
|---|---|---|
| Phase 0 Audit/Stabilization | IMPLEMENTED | حفظ و rerun gates |
| Phase 1 Runtime | IMPLEMENTED / real Windows UAT pending | حفظ owner؛ تکمیل UAT در Phase 8/9 |
| Phase 2 Relay | IMPLEMENTED | حفظ Local authority/idempotency |
| Phase 3 Guest/Public | IMPLEMENTED | UI باید به DS جدید migrate شود |
| Phase 4 Remote Reads | IMPLEMENTED | stale/read-only UX در DS جدید standard شود |
| Phase 5 Deferred-safe | IMPLEMENTED | state presentation باید canonical شود |
| Phase 6A Preparation permissions | IMPLEMENTED | server-authoritative؛ UI migrate شود |
| Phase 6B Sellable kind | IMPLEMENTED | preserve |
| Phase 6C Table Draft | IMPLEMENTED | preserve canonical staff order owner؛ UI state standard شود |
| Phase 6D Batch Purchase | **IMPLEMENTED LOCALLY / STATIC GATES PASS / DB UAT PENDING** | ثبت گروهی چندردیفی اضافه شد؛ همه ردیف‌ها پیش از mutation lock/validate می‌شوند، transaction واحد است، هر ردیف از `supply_receive_preparing_locked()` و Inventory movement canonical عبور می‌کند؛ اقلام خارج از فهرست عمداً ابتدا در مسیر تک‌ردیفی هویت انبار می‌گیرند |
| Phase 6E Expenses | **IMPLEMENTED LOCALLY / CONTRACT GATES PASS / DB UAT PENDING** | Owner ایجاد/برگشت/اصلاح append-only، UI مدیر فارسی، جلوگیری از mutation عادی دوره بسته و summary دوره مالی اضافه شد؛ خرید انبار دوباره به‌عنوان هزینه عمومی شمرده نمی‌شود |
| Phase 6F Tax | **IMPLEMENTED LOCALLY / STATIC+BROWSER GATES PASS / REAL DB+WINDOWS+PHYSICAL UAT PENDING** | Owner/effective-dated rates/item policy/order snapshots/settlement allocation v2/Guest authoritative quote/Public profile/reporting/customer receipt پیاده شد؛ House API 2.0 برای Tax>0 deterministic block می‌شود؛ migration واقعی MariaDB و Windows/print UAT هنوز لازم است |
| Phase 7 Notifications | IMPLEMENTED direction | runtime failure isolation validate شود |
| Phase 7 Accommodation/Center | IMPLEMENTED | contract validation حفظ شود |
| Phase 7 Printing | **RECONCILED LOCALLY / STATIC+PHP GATES PASS / WINDOWS CI + PHYSICAL UAT PENDING** | R2 ownership اعمال شد: Print Worker component داخلی SOKNA Local است؛ source عملیاتی 6.2.5 با provenance داخلی شده، Setup/Control مستقل حذف شده و Setup/Repair/Recovery مالک lifecycle/provisioning هستند. Print API v4/SQLite/reconciliation/Winspool بازنویسی نشده‌اند. Windows build/service/rollback و چاپ فیزیکی هنوز PASS ادعا نمی‌شوند |
| Phase 8A Identity/Recovery | IMPLEMENTED | preserve |
| Phase 8B Windows setup | IN PROGRESS | بعد از reconciliation گپ‌ها ادامه یابد؛ installer acceptance هنوز نهایی نیست |
| Phase 8C Takeover | OPEN | real replacement/takeover/revoke evidence لازم |
| Phase 9 Full migration/UAT/release | OPEN | cleanup + full regression + physical UAT + packaging |

## شواهد کلیدی
- Phase 6F Tax اکنون با `includes/tax.php`، `admin/tax.php`، `docs/architecture-migration-r2/PHASE6F_LOCAL_MIGRATION.sql`، immutable order snapshots، allocation v2، Guest authoritative quote و Internal Print Worker receipt integration در workspace پیاده شده است؛ Real MariaDB/Windows/physical UAT هنوز باز است.
- Phase 6E در workspace محلی با `admin/expenses.php` و ownerهای append-only تکمیل شده است؛ runtime DB acceptance روی MySQL/MariaDB واقعی هنوز لازم است.
- Phase 6D در workspace محلی با `supply_receive_batch_locked()` + `admin/purchases_batch.php` پیاده شده است؛ runtime DB acceptance روی MySQL/MariaDB واقعی هنوز لازم است.
- Printing ownership در workspace محلی با `runtime/print-worker/source/` + `tools/print-runtime-worker.php` + SOKNA Windows Setup reconcile شده است؛ external installer/download دیگر active authority نیست. Windows CI و physical UAT هنوز لازم‌اند.

## قاعده Completion جدید
هیچ مورد `COMPLETE` نیست مگر:
- owner/schema/API/business invariants پیاده باشد؛
- automated contracts اجرا و PASS باشند؛
- UI مربوطه DS/Persian/RTL/A11y/Responsive gates را PASS کند؛
- dependency محیط واقعی اگر لازم است با UAT evidence بسته شود؛
- Handoff دقیق و rollback/recovery note وجود داشته باشد.
