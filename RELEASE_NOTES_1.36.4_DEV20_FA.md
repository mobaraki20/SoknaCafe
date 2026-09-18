# Sokna 1.36.4-dev.20

این checkpoint روی `1.36.4-dev.19` ساخته شده و فقط تکمیل Print Web/Agent contract را در دامنهٔ چاپ تغییر می‌دهد. قابلیت‌های Catalog/Menu/UI و بکاپ رمزگذاری‌شده `.skb`/Sodium بدون بازنویسی حفظ شده‌اند.

## اصلاحات

- Wake فقط برای Job واقعاً commit‌شده و پاسخ business موفق منتشر می‌شود؛ rollback یا `success=false` با HTTP 200 wake نمی‌سازد.
- Print API v4 برای Claim/Accept/Renew/Start/Report fingerprint بدنهٔ request را پایدار می‌کند تا reuse همان request_id با بدنهٔ دیگر 409 شود.
- `attempt_status` در receipt mismatch یا reservation منقضی هیچ `start/report` مجاز چاپ نمی‌دهد.
- Report `submitted` بدون Spooler ID حتی در replay رد می‌شود؛ terminal evidence دقیق و late-evidence جدا شده است.
- خطاهای transient شناخته‌شدهٔ MySQL/MariaDB با 503/Retry-After از conflict معنایی جدا شده‌اند.
- pairing Bridge از health JSON عمومی جدا و در ستون runtime محدود نگه‌داری می‌شود؛ capability فقط listener تازه و origin منطبق را برمی‌گرداند.
- Wake مرورگر deadline و coalescing per-Agent دارد و اعلان تازه هنگام in-flight از بین نمی‌رود.
- Preview revision از لحظهٔ تغییر فرم invalid می‌شود و `session_id`/destination binding اضافه شده است. تا وقتی Agent RenderProfile مقصد با DPI X/Y را واقعاً expose نکند، UI به‌جای ادعای Exact به حالت تقریبی fail-closed می‌رود.
- سیاست Agent retirement/token rotation برابر drain-before-retirement است؛ Agent دارای Attempt/گزارش تعیین‌تکلیف‌نشده غیرفعال، بازنشسته یا rotate نمی‌شود.

## Migration

Upgrade رسمی هدف این بسته: `1.36.4-dev.19 → 1.36.4-dev.20`. Migration فقط ستون‌های Bridge runtime و request fingerprint را به جداول چاپ اضافه می‌کند. اجرای واقعی MySQL/MariaDB باید در acceptance environment انجام شود.

## Gate

Agent remediation candidate بررسی‌شده: branch `feat/agent-remediation-post-6.2`, SHA `6cf8b794676585eaff1a2a27c4e910667f7d8b46`. این SHA release رسمی 6.2 نیست و تا PASS جفت واقعی A49/B49 به معنی Web–Agent integration verified نیست. UAT پرینتر واقعی نیز جداست.
