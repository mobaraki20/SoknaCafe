# خلاصه تغییرات Web 1.36.4-dev.21

- `includes/print_agent_api.php`: helperهای nullable اختصاصی Heartbeat با validation سخت برای non-null.
- `print-agent/v4/api.php`: پذیرش optional null، ثبت authoritative `last_heartbeat_at` فقط در Heartbeat، کامل‌کردن runtime columns در route lookup.
- `database/schema.sql`: ستون و index جدید `last_heartbeat_at`.
- `release/1.36.4-dev.21-print-failover.sql`: migration additive بدون backfill.
- `includes/printing.php`: canonical Agent/queue readiness؛ حذف fallback discovery به `last_seen_at`؛ route resolution واحد برای Jobها.
- `admin/printing.php`: خطاهای actionable، Promote Fallback تراکنشی، deterministic lock order، block بازنشستگی با live route، archive read-only.
- `assets/js/printing-settings.js`: جلوگیری از double-submit mutation.
- `tests/*`: contract coverage برای remediation و سازگاری تست‌های قبلی.
- `VERSION.txt` و `service-worker.js`: bump به 1.36.4-dev.21.
