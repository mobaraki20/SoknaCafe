# معماری اعلان عملیاتی سکنا — Outbox خودتخلیه‌شونده + Worker اختیاری

منبع حقیقت اعلان عملیاتی `push_event_queue` است. ثبت سفارش، فراخوان، تأیید سفارش و سایر عملیات اصلی فقط Event را داخل همان Transaction ثبت می‌کنند؛ موفقیت یا شکست شبکه Push هرگز نباید عملیات اصلی را Rollback کند.

## مسیر ارسال در مقیاس فعلی سکنا
1. Domain mutation داخل Transaction انجام می‌شود.
2. Event اعلان با `push_enqueue_event_tx()` در Outbox ثبت می‌شود.
3. Transaction Commit می‌شود.
4. اگر PHP-FPM در دسترس باشد، پاسخ کاربر اول با `fastcgi_finish_request()` تمام می‌شود و فقط Event تازه به‌صورت best-effort پردازش می‌شود.
5. مستقل از FPM، Response یک Kick امضاشده و کوتاه‌عمر برای همان Queue ID دارد. `push-runtime.js` آن را در یک Request جدا و `keepalive` اجرا می‌کند.
6. صفحات فعال کارکنان هر ۲۰ ثانیه حداکثر مقدار کوچکی از Backlog را از `api/push_drain.php` پردازش می‌کنند.
7. `tools/push-worker.php` باقی می‌ماند، ولی برای مقیاس فعلی سکنا **شتاب‌دهنده اختیاری** است، نه شرط کارکرد Notification.

در نتیجه خاموش بودن Worker مستقل نباید به معنی خاموش بودن اعلان سفارش/فراخوان باشد.

## Stale / superseded guard
درست قبل از Delivery، وضعیت فعلی Domain دوباره بررسی می‌شود. Fail-safe است: اگر Actionability قابل اثبات نباشد، Push ارسال نمی‌شود.

- `waiter_call`: فقط status=`new` و حداکثر ۵ دقیقه.
- `pending_order`: فقط `new/pending_approval` و حداکثر ۱۵ دقیقه.
- `order` آماده‌سازی: فقط `accounted/completed`، حداکثر ۳۰ دقیقه و حداقل یک Area با Signature فعلی هنوز Claim نشده باشد.
- Diagnostic: حداکثر ۵ دقیقه.

Event منقضی یا superseded حذف فیزیکی نمی‌شود؛ Queue/Delivery به `expired` می‌رود تا Audit باقی بماند.

## Routing عملیاتی
- `waiter_call`: کاربران فعال دارای `orders_floor`؛ مقصد Operator/Attention.
- `pending_order`: کاربران فعال دارای `orders_floor`.
- `order`: کاربران فعال دارای `preparation` و فقط Areaهای مجاز همان کاربر.
- Admin/Owner فقط با opt-in تنظیم `push.admin_live_operations` اعلان عملیات زنده می‌گیرد.

Permission با Notification responsibility یکی نیست.

## Action روی Notification
برای فراخوان مهمان:
- `پذیرفتم`: Claim اتمیک با Token کوتاه‌عمر و `FOR UPDATE`.
- `مشاهده`: بازکردن Task مربوط.

عملیات نیازمند Context مثل رد سفارش یا «آماده شد» مستقیماً از Notification اجرا نمی‌شوند.

## In-app fallback
Push فقط کانال جلب توجه است. صف‌های Operator/Preparation و Polling داخل سامانه منبع حقیقت عملیات می‌مانند. بسته‌شدن Permission اعلان، Expire شدن Subscription یا Failure Provider نباید Task را از پنل حذف کند.

## Diagnostics
صفحه مدیریت باید این مفاهیم را جدا نمایش دهد:
- دستگاه ثبت‌شده / فعال / نیازمند بررسی؛
- ارسال خودکار: فعال؛
- Queue pending + قدیمی‌ترین Event؛
- Worker مستقل: فعال یا اختیاری؛
- Deliveryهای اخیر و Subscriptionهای منقضی.

Worker heartbeat فقط Diagnostic/Acceleration است و نبود آن نباید پیام «اعلان عملیاتی کار نمی‌کند» تولید کند.

## تست کامل مسیر اعلان
دکمه تست باید همان Outbox واقعی را استفاده کند. Diagnostic Event وارد Queue می‌شود و از مسیر self-draining/optional-worker به همان کاربر Target می‌شود. Direct call به `push_send_subscription()` از API تست ممنوع است.

PASS فقط وقتی است که Delivery واقعی `sent` تشکیل شده باشد. موفقیت تست Device به‌تنهایی مجوز دریافت اعلان عملیات برای Admin را اثبات نمی‌کند؛ Routing policy جداست.

## گیت‌های دائمی
- `notification_routing_integrity`
- `notification_pipeline_parity`
- `push_stale_event_guard`
- `notification_self_draining_outbox`

Gate باید ثابت کند:
- Mutation اصلی فقط enqueue می‌کند و Provider failure آن را Fail نمی‌کند؛
- Kick عمومی فقط Queue ID امضاشده خودش را پردازش می‌کند؛
- Drain کارکنان Auth + CSRF دارد و bounded است؛
- Event قدیمی/Claim‌شده دوباره Push نمی‌شود؛
- Worker مستقل حذف نشده، ولی dependency اجباری نیست؛
- In-app polling همچنان فعال است.
