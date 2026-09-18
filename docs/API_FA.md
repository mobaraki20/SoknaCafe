# قرارداد API — Sokna 1.36.0

اصول عمومی:
- JSON با وضعیت HTTP مناسب
- CSRF برای عملیات authenticated
- Idempotency/Request ID در عملیات حساس
- Backend منبع حقیقت وضعیت، مبلغ و هویت میز
- شبکه خارجی Push داخل مسیر ثبت/تأیید سفارش اجرا نمی‌شود

## سفارش
- وضعیت سفارش مهمان از مسیر canonical `operator/api_status.php` تعیین تکلیف می‌شود.
- Pending فقط به‌صورت مستقل `accounted` یا `cancelled` می‌شود.
- Retry همان نتیجه Persisted را برمی‌گرداند و نباید عملیات را دوباره اعمال کند.

## Quick Order
`staff/api_quick_order.php` سفارش جدید کارکنان را ثبت می‌کند و در حضور Pending مهمان، ثبت را تا تعیین تکلیف Pending متوقف می‌کند. وضعیت Pending را خودش تغییر نمی‌دهد.

## میز
APIهای عملیات باید `table_number` را در کنار Label انسانی میز برگردانند؛ UI نباید کد داخلی `T-*` را به کاربر نشان دهد.

## اقامتگاه
قرارداد Integration مستقل، با Snapshot مالی ثابت و Retry پایدار حفظ می‌شود. `sort_order` به API اقامتگاه ارسال نمی‌شود.

## تسویه
- Client هنگام شروع تسویه، `expected_session_id`، `expected_total` و `expected_signature` همان حساب مرورشده را ارسال می‌کند؛ Backend پس از Lock مجدد Session/Orders/Items باید هر سه را تطبیق دهد و در اختلاف با `409 / settlement_changed` متوقف شود.
- تسویه مستقیم و مشترک `request_id` پایدار دارند؛ Retry همان درخواست نباید Settlement یا Subscriber Ledger دوم ایجاد کند.
- برای نتیجه نامعلوم شبکه، `operator/api_settlements.php?request_id=...` فقط Settlement دقیق همان Request را برمی‌گرداند و Client علاوه بر شناسه، `table_id` و `destination` را تطبیق می‌دهد.
- آزادشدن میز به‌تنهایی هرگز مدرک موفقیت یک Settlement نامعلوم نیست.
