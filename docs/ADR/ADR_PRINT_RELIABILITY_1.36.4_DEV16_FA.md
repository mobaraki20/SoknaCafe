# ADR — بازطراحی قابلیت اطمینان چاپ در 1.36.4-dev.16

تاریخ: 2026-09-10 — وضعیت: Accepted for RC

## تصمیم

مرجع سفارش و Print Intent سرور سکناست. Print Intent لازم داخل همان تراکنش business ثبت می‌شود و هیچ HTTP/Spooler call داخل تراکنش انجام نمی‌شود. Agent coordinator واحد برای Poll، Wake، recovery، Accept/Start و Worker است.

مرورگر صندوق بعد از پاسخ موفق فقط Agent محلی را روی loopback بیدار می‌کند. Wake Job یا Attempt تازه نمی‌سازد و FIFO/مالکیت مرکزی را دور نمی‌زند. Poll fallback همیشه باقی می‌ماند.

## State و ambiguity

- Claim/Accept/Start identity باید durable و قابل replay باشد.
- `attempt_status` برای reconciliation نتیجه مبهم اضافه شده است.
- پس از احتمال Spooler، retry خودکار ممنوع است.
- `submitted` فقط پذیرش Spooler است، نه خروج قطعی کاغذ.

## Retry

`attempt_no` تاریخچه صعودی است. Retry دستی `retry_cycle` جدید می‌سازد و سقف خودکار هر چرخه با `cycle_attempt_no` اعمال می‌شود؛ تاریخچه reset نمی‌شود.

## زمان

Timestampهای محاسباتی API روی wire به UTC ISO-8601 با `Z` تبدیل می‌شوند. Server مرجع lease است.

## Bridge

Bridge فقط روی `127.0.0.1` با pairing محدود و Origin سایت کار می‌کند. Secret اصلی Agent و lease token وارد JavaScript نمی‌شود. Wake failure سفارش موفق را fail نمی‌کند.

## Preview

حالت دقیق از همان Renderer Agent/Worker و geometry مقصد تصویر می‌گیرد. fallback وب فقط تقریبی است و صریحاً برچسب می‌خورد.

## Template

identity، revision، origin و active جدا هستند. Origin از نام version استنتاج نمی‌شود. حذف active با replacement اتمیک انجام می‌شود و snapshot Jobهای قبلی ثابت می‌ماند.
