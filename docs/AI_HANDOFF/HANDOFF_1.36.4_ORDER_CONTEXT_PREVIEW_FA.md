# هنداور Preview — حفظ Context سفارش سریع

Baseline: `1.36.4-dev.6` + UI Preview تأییدشده V15

## هدف
بعد از ثبت موفق Quick Order عادی برای یک میز، کاربر دیگر به نمای کلی میزها پرت نمی‌شود. همان میز دوباره باز می‌شود، اطلاعات حساب از API اصلی بازخوانی می‌شود و سفارش تازه داخل Context حساب قابل تشخیص است.

## تغییر رفتار
- موفقیت Quick Order عادی، `open_table=<table_id>` را به مقصد میزها اضافه می‌کند.
- `quick_order_order` و `quick_order_number` از پاسخ canonical API به صفحه میزها منتقل می‌شوند.
- Operator در Load اولیه همان میز را با مسیر existing `openTable()` باز می‌کند؛ مسیر جدید یا Owner موازی ایجاد نشده است.
- بالای فاکتور یک پیام موفقیت موقت نمایش داده می‌شود.
- اگر میز بیش از یک نوبت سفارش داشته باشد، Accordion سفارش‌های میز باز می‌شود و نوبت تازه باز/Highlight می‌شود.
- Context موفقیت پس از حدود 5.2 ثانیه از UI پاک می‌شود و Queryهای موقت از URL حذف می‌شوند.
- Back/Cancel از Quick Order که از پنل میز آمده نیز `open_table` میز مبدأ را حفظ می‌کند.
- `late_accounting` عمداً در این فاز به Context تسویه متصل نشده است؛ آن فاز مستقل باقی می‌ماند.

## Safety / Regression
- API، منطق مالی، Inventory و چاپ تغییر نکرده‌اند.
- Idempotency و uncertain retry همان Owner قبلی را دارند.
- تست‌های Quick Order، uncertain-result، late-accounting و Operator actions اجرا شده و PASS هستند.
- تست مرورگری مستقیم Navigation URL در این محیط به‌علت محدودیت Navigation محلی قابل اجرا نبود؛ به‌عنوان PASS ثبت نشده است.
