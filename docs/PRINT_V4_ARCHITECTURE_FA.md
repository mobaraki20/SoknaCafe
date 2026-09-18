# معماری Print Reliability v4

## Source of Truth
در ورودی این مأموریت، Clean Install Full و Release Bundle مقایسه شدند. Full داخل Bundle با Full مستقل یکسان بود. Source اجرایی از Clean Install Full گرفته شد؛ Bundle فقط Artifact/Release metadata بود.

## معماری
`Sokna PHP/MySQL → Print API v4 → Windows Service → SQLite Local Queue → Isolated PrintWorker → Windows Spooler → Printer`

### Invariantها
1. Print Intent اجباری در همان Transaction سفارش Persist می‌شود.
2. هیچ تماس شبکه/Spooler داخل Transaction سفارش نیست.
3. نبود Mapping/Agent/Queue Job را حذف نمی‌کند؛ `blocked` ثبت می‌شود.
4. `reserved` فقط Lease است؛ Agent قبل از `accept` اجازه چاپ ندارد.
5. Agent ابتدا payload/token/hash را durable در SQLite ذخیره می‌کند، سپس accept.
6. Submission Fence محلی قبل از Worker/Spooler ثبت می‌شود.
7. پس از Fence/Worker launch اگر عدم ارسال اثبات‌پذیر نیست => unknown/recovery_hold؛ no automatic reprint.
8. submitted فقط پذیرش Windows Spooler است.
9. Reprint Job جدید است؛ Job قبلی reset نمی‌شود.
10. FIFO مقصد تا Resolution Job قبلی حفظ می‌شود.

## State Machine
- `pending`: آماده claim
- `blocked`: تنظیم/mapping/queue مشکل دارد
- `reserved`: Lease موقت، چاپ ممنوع
- `claimed`: در SQLite Agent durable و accept شده
- `submitted`: Windows Spooler Job ID داده است
- `failed`: خطای شناخته‌شده pre-submission
- `unknown`: احتمال submission وجود دارد، نتیجه قابل‌اثبات نیست
- `recovery_hold`: ownership محلی Agent مبهم است و retry خودکار خطر duplicate دارد
- `cancelled`: لغو audit شده قبل از ambiguity

## Retry
فقط خطاهای اثبات‌شده پیش از Submission Fence به retry محدود با backoff مجازند. بعد از سقف تلاش، Exception انسانی ایجاد می‌شود. claimed/unknown با heartbeat timeout به Agent دیگر واگذار نمی‌شود.

## Scale
برای 10–15 Job/min peak طراحی شده؛ claim batch کوچک (<=5)، polling 1–2s active و 3–5s idle. Broker خارجی، Redis/Kafka/RabbitMQ یا clustering اضافه نشده است.

## Transport health / Diagnostics
Heartbeat می‌تواند Evidence اختیاری `last_successful_action`, `last_api_success_at`, `last_api_error_code`, `consecutive_api_failures` و `last_api_latency_ms` حمل کند. این داده‌ها فقط داخل `print_agents.health_json` نگهداری و برای تشخیص Healthy/Degraded در پنل استفاده می‌شوند. این Evidence هیچ ownership، failover، retry یا transition چاپ ایجاد نمی‌کند و نبود آن با Agentهای سازگار قبلی backward-compatible است.

