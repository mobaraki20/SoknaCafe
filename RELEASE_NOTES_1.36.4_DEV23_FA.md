# Sokna 1.36.4-dev.23 — Print Claim Identity Recovery

این نسخه یک Bugfix هماهنگ Web/Agent برای تعارض durable Claim است و هیچ بازطراحی UI یا تغییر مالی/سفارش ندارد.

## رفع ریشه‌ای

- پاسخ هر Claim اکنون در `print_claim_requests.response_snapshot_json` به‌صورت immutable ذخیره می‌شود؛ `payload_json`، `content_sha256` و مقصد دیگر هنگام replay از وضعیت جاری Job بازسازی نمی‌شوند.
- Lease Token داخل Snapshot دیتابیس ذخیره نمی‌شود و در replay از هویت همان Claim دوباره مشتق می‌شود.
- قابلیت `claim_conflict_rekey_v1` اضافه شد. فقط Attemptی که `reserved` یا بر اثر پایان Lease، `expired` شده و هیچ Receipt/Accept/Start/Report/Spooler evidence ندارد قابل اصلاح خودکار است.
- Attempt متعارض حذف یا overwrite نمی‌شود: با وضعیت `expired` و Evidence پایدار حفظ می‌شود و Attempt تازه با شناسه بزرگ‌تر از سقف تاریخچه محلی Agent ساخته می‌شود.
- هر Attempt یک رکورد مستقل در `print_claim_reconciliations` دارد؛ بنابراین Claim چندآیتمی می‌تواند بیش از یک تعارض را به‌صورت idempotent رفع کند.
- تمام Attemptهای همان Job قفل می‌شوند؛ وجود Attempt جدیدتر/فعال، mismatch اثبات‌نشده یا جهش غیرعادی شناسه مسیر خودکار را متوقف می‌کند.
- در Snapshot قبلی فقط شناسه/چرخه/Lease Attempt جایگزین می‌شود و Job/Payload/Destination از داده زنده بازسازی نمی‌شوند.
- اگر هر نشانه‌ای از عبور از Accept وجود داشته باشد، API با `claim_reconciliation_unsafe` fail-closed می‌شود و تصمیم انسانی لازم می‌ماند.

## سازگاری

- Agent پیشنهادی: `6.2.4`.
- Agentهای قدیمی همچنان Claim v4 را دریافت می‌کنند، اما مسیر self-healing تعارض فقط در 6.2.4 فعال است.
- Migration رسمی: `release/1.36.4-dev.23-print-claim-reconciliation.sql`.

## محدودیت Gate

`submitted` همچنان فقط پذیرش Windows Spooler است و اثبات خروج فیزیکی کاغذ نیست. چاپ فیزیکی/UAT سخت‌افزار جداگانه باقی می‌ماند.
