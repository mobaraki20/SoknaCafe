# Sokna 1.36.4-dev.24 — Print Recovery & Operator Resolution

این نسخه Hotfix رسمی برای نصب تستی `1.36.4-dev.23` است و از مسیر استاندارد `/admin/update/` نصب می‌شود. Migration دیتابیس ندارد.

## رفع Claim Recovery قدیمی

- Claimهای ساخته‌شده پیش از ذخیره `response_snapshot_json` در صورت داشتن Evidence پایدار، Snapshot خود را از Attempt/Job ذخیره‌شده بازسازی می‌کنند.
- مسیر `claim_reconcile` دیگر برای Legacy Claim امن صرفاً با `claim_snapshot_missing` متوقف نمی‌شود و سپس همان مسیر audited rekey نسخه dev.23 را ادامه می‌دهد.
- بازسازی Snapshot از Destination Snapshot و Payload پایدار انجام می‌شود و به Mapping زنده مقصد وابسته نیست.
- اگر Evidence برای reconciliation امن نباشد، رفتار fail-closed حفظ می‌شود و چاپ خودکار انجام نمی‌شود.

## تعیین تکلیف چاپی که دیگر لازم نیست

- برای Jobهای `pending`، `blocked`، `failed` و رزرو امنِ پیش از Accept، مدیر می‌تواند «دیگر نیاز به چاپ نیست» را انتخاب کند تا Job بدون چاپ خودکار بعدی بسته شود.
- لغو Job رزروشده فقط وقتی مجاز است که Attempt هنوز هیچ Receipt/Accept/Start/Spooler/Report evidence نداشته باشد؛ عملیات با Lock تراکنشی در برابر Race محافظت می‌شود.
- برای `unknown` و `recovery_hold` گزینه «دیگر نیاز به چاپ نیست» اضافه شد تا صف مقصد بدون ساخت Reprint جدید آزاد شود؛ اگر چاپ قبلاً وارد Spooler شده باشد، خروج فیزیکی کاغذ همچنان قابل تضمین نیست.

## سازگاری

- مبدا رسمی Update: `1.36.4-dev.23`.
- Agent پیشنهادی: `6.2.4` و نیازی به نصب مجدد Agent برای این Hotfix نیست.
- Migration دیتابیس: ندارد.
