# Baseline بصری سکنا

Baseline بصری فقط بعد از تأیید UAT انسانی همان **صفحه + state + عرض** معتبر است. Screenshot ساخته‌شده توسط خود Build بدون بازبینی انسانی، Baseline مرجع محسوب نمی‌شود.

Registry رسمی stateها: `tests/visual_quality_pages.json`.

قاعده Promotion:
- `pending_uat` = Build می‌تواند توسعه‌ای باشد، اما Final Promotion مجاز نیست.
- `approved` = Screenshot/geometry همان state توسط UAT تأیید و Baseline مرجع ثبت شده است.
- تغییر عمدی ظاهر باید Baseline قبلی را عمداً بازبینی و دلیل تغییر را در Change Register ثبت کند؛ Update خودکار Baseline برای سبزکردن Test ممنوع است.
