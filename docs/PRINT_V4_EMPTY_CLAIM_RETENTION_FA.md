# Print API v4 — Empty Claim Retention

وضعیت: Working change برای نسخه بعدی؛ هنوز Release نشده است.

- Claim خالی همچنان در `print_claim_requests` ثبت می‌شود تا replay همان `request_id` نتیجه پایدار بگیرد.
- فقط Claimهای کامل‌شده با `attempt_ids_json=[]` پس از ۷ روز واجد cleanup هستند.
- Claim ناقص (`attempt_ids_json IS NULL`) هرگز توسط این cleanup حذف نمی‌شود.
- Claim دارای Attempt هرگز توسط این cleanup حذف نمی‌شود.
- cleanup به‌صورت batch با سقف ۲۰۰۰ ردیف اجرا می‌شود.
- cleanup در Heartbeat و خارج از transaction اصلی آن به‌صورت best-effort اجرا می‌شود؛ Claim fast path اصلاً cleanup اجرا نمی‌کند و خطای maintenance نباید مسیر چاپ را متوقف کند.
- Index موجود `(created_at,id)` برای انتخاب ردیف‌های قدیمی استفاده می‌شود؛ migration جدیدی لازم نیست.
- trade-off: replay یک Empty Claim قدیمی‌تر از ۷ روز دیگر تضمین نمی‌شود. این پنجره بسیار بزرگ‌تر از retry واقعی HTTP است و از رشد نامحدود ledger جلوگیری می‌کند.
