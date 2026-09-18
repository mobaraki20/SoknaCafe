# Supply / Purchasing Workflow — 1.36 Development

## Actorها

- Staff: نیاز مصرف واقعی را اعلام/اصلاح می‌کند.
- Buyer: نیازها را تجمیع می‌بیند و «برای تهیه» برمی‌دارد.
- Receiver: هنگام رسیدن فیزیکی کالا مقدار واقعی را ثبت می‌کند (ممکن است همان Buyer باشد).

## State Contract

UI ساده است ولی Backend سه Quantity مستقل را نگه می‌دارد:

- Requested: کل تقاضای ثبت‌شده
- Preparing: بخشی که Buyer دیده و برای تهیه برداشته
- Fulfilled: بخشی که واقعاً تحویل و وارد Inventory شده

`Uncommitted = Requested - Fulfilled - Preparing`

Staff فقط Uncommitted را ویرایش می‌کند. Preparing به‌صورت خودکار با ویرایش Staff تغییر نمی‌کند.

## Flow

1. Staff نیاز را ثبت می‌کند.
2. Purchase UI نیاز یک کالای شناخته‌شده را بین Departmentها Aggregate می‌کند.
3. Buyer روی «برای تهیه» می‌زند؛ Uncommitted به Preparing منتقل می‌شود. Stock تغییری نمی‌کند.
4. نیاز جدیدی که بعداً ثبت شود دوباره Uncommitted می‌ماند و مقدار در حال تهیه را تغییر نمی‌دهد.
5. هنگام تحویل، مقدار واقعی دریافت وارد می‌شود.
6. فقط در این مرحله یک `purchase_receive` Inventory Movement ساخته می‌شود.
7. مقدار تحویل oldest-first روی Needهای Departmentها Allocate می‌شود.
8. Partial receive مقدار باقی‌مانده را در Preparing نگه می‌دارد.
9. Over-receive موجودی را کامل زیاد می‌کند، ولی Demand منفی نمی‌سازد.
10. «تهیه نشد» Preparing را بدون Stock mutation به لیست خرید برمی‌گرداند.

## UI Rules

- Purchase UI دو بخش عملیاتی دارد: «در حال تهیه» و «نیازهای خرید».
- Items شناخته‌شده برای Buyer بر اساس کالا Aggregate می‌شوند؛ جزئیات Department برای سابقه و پیگیری حفظ می‌شود.
- کالای آزاد با نام نرمال‌شده و واحد پایه یکسان نیز در نمای Buyer تجمیع می‌شود؛ Needهای اصلی بخش‌ها ادغام یا حذف نمی‌شوند.
- Staff UI فقط «در انتظار خرید» و «در حال تهیه» را برجسته می‌کند.
- Quantity واقعی Receipt هرگز از Need prefill نمی‌شود.
- با تغییر Purchase Unit، Quantity input پاک می‌شود تا واحد قبلی با معنای جدید reuse نشود.
- Quick action «همان مقدار در حال تهیه» فقط در Direct/base-unit mode فعال است.
- Low Stock مقدار خرید را حدس نمی‌زند و Negative Stock داخل Low Stock suggestion نمی‌آید.
- Unknown Item فقط هنگام Receipt ساخته می‌شود و `needs_review` می‌گیرد.
- DatePicker از Owner مشترک `data-jalali-date` استفاده می‌کند.
- Form تحویل روی موبایل Form Sheet است؛ بازشدن آن هیچ Input عددی را Autofocus نمی‌کند.
- «شروع تهیه همه» فقط وقتی بیش از یک قلم باز وجود دارد نمایش داده می‌شود.

## Audit / Failure

- Preparing start/return/unavailable audit می‌شود.
- Receipt idempotent با `request_token` است.
- `inventory_supply_receipt_allocations` رابطه Receipt فیزیکی با Needهای Departmentها را نگه می‌دارد.
- Tap/Refresh/Retry نباید Movement دوباره بسازد.
- Fresh/Update روی MySQL/MariaDB واقعی و end-to-end authenticated UAT تا اجرا در محیط واقعی `UAT_REQUIRED` است.

## Concurrency Guard

Actionهای «برای تهیه»، «شروع تهیه همه»، بازگرداندن/لغو و Receipt مقدار مورد انتظار همان صفحه را به Server می‌فرستند. Server بعد از `FOR UPDATE` مقدار جاری را مقایسه می‌کند؛ اگر Staff بین مشاهده صفحه و Action مقدار را تغییر داده باشد، عملیات Fail می‌شود و Buyer باید صفحه را تازه کند. بنابراین لیست قدیمی نمی‌تواند مقدار جدید و ندیده را بی‌صدا به مسئولیت Buyer منتقل کند.
