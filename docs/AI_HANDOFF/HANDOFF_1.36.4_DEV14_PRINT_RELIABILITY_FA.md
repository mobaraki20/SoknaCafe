# Handoff — Sokna Cafe 1.36.4-dev.14 / Print Reliability + Stepper Consolidation

## Baseline
- From: `1.36.4-dev.13`
- To: `1.36.4-dev.14`
- Status: Pilot-ready checkpoint; Production Go-Live همچنان UAT واقعی می‌خواهد.

## تصمیم‌های قفل‌شده این نسخه
- صف چاپ FIFO حفظ شده؛ برای بازکردن صف، ترتیب را دور نمی‌زنیم.
- Activity UI اکنون blocker واقعی مقصد را نشان می‌دهد.
- مقصدهای سیستمی حذف نمی‌شوند.
- مقصد آماده‌سازی سفارشی فقط بدون سابقه چاپ قابل حذف فیزیکی است.
- Failover Print API: Primary owner اول است؛ Fallback فقط هنگام unavailable شدن عملیاتی Primary eligible می‌شود.
- Attempt همیشه Route/Queue واقعی زمان Claim را Snapshot می‌کند.
- Migration این نسخه عمداً داده‌های چاپ تستی را پاک می‌کند؛ این رفتار فقط به دلیل Pre-Operational/Pilot بودن محیط توسط Product Owner تأیید شده است و نباید در Releaseهای Production تکرار شود.
- Agent/SQLite محلی و Windows Spooler خارج از دیتابیس Cafe هستند؛ Update سرور آنها را پاک نمی‌کند.

## Migration
Update package migration: `migrations/1.36.4-dev.14-print-test-reset.sql`

Scope دقیق:
- `UPDATE print_jobs SET reprint_of_id=NULL`
- Delete all `print_attempts`
- Delete all `print_claim_requests`
- Delete all `print_jobs`
- Reset AUTO_INCREMENT همان سه جدول

Agent/Destination/Template حفظ می‌شوند.

## UAT چاپ بعد از Update
- Stop Agent قبل از Update
- Windows queue خالی باشد
- Update اجرا شود
- Mapping Primary مقصدها به Agent و Printer واقعی بررسی شود
- Agent Start
- Diagnostics: Agent healthy + destination operational
- Test preparation print
- Test customer receipt
- مشاهده خروج فیزیکی کاغذ
- Fallback فقط با سناریوی کنترل‌شده Primary unavailable تست شود

## Stepper
فقط Standardization تأییدشده وارد شد. Composition جدید Modal هنوز در Scope نیست.

## Regression areas
- Finance/Settlement/Inventory unchanged
- Guest `/menu` unchanged
- Public waiter call unchanged
- Quick Order unchanged
- House unchanged
