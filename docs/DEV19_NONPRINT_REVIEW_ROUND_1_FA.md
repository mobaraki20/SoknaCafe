# بازبینی دور اول dev.19 — تحلیل مستقل مسئله‌های غیرچاپی

Baseline: `1.36.4-dev.18` — وضعیت محصول: Pre-Operational.

## معماری/مهندسی

- Catalog واحد و جداول membership منو در dev.18 Owner مناسب دارند؛ ساخت Catalog جدا برای کافه/صبحانه/ناهار duplication و migration debt ایجاد می‌کند.
- Search/Filter/Sort موجود است؛ بازنویسی آن بدون defect جدید خلاف Root Cause policy است.
- اختلاف Stepper از چند CSS owner روی یک component می‌آید؛ fix باید selector scope/owner را اصلاح کند، نه override انتهای فایل.
- Backup داخلی شامل DB، Upload و هویت برنامه است و برای Disaster Recovery لازم است؛ همان فایل در خروج از سرور نباید plaintext دانلود شود.

## UX/UI

- کنترل تعداد در یک dialog باید در Final و Prepared از یک هندسه و touch target استفاده کند؛ تفاوت ظاهری بدون معنای عملیاتی، خطای ادراکی ایجاد می‌کند.
- Stepper کارت محصول Quick Order می‌تواند compact باشد، ولی همان rule نباید سبد را کوچک کند؛ سبد محل تصمیم نهایی و نیازمند کنترل لمسی 44px است.
- Backup باید به کاربر صریح بگوید رمز ذخیره نمی‌شود و از دست‌دادن رمز یعنی عدم امکان Restore آن فایل.

## عملیات کافه/رستوران

- سه منوی daypart باید از اقلام مشترک استفاده کنند؛ ساخت اقلام کپی‌شده احتمال اختلاف قیمت/نام/وضعیت را بالا می‌برد.
- تغییر امنیت Backup نباید Backup خودکار و Restore داخلی را پیچیده یا متوقف کند.
- Quick Order و اصلاح تعداد در Peak باید کم‌کلیک و قابل لمس بمانند.

## QA/Security

- فایل خارج از سرور باید confidentiality + integrity داشته باشد؛ صرف ZIP/TAR یا password UI کافی نیست.
- رمز نباید log/audit/config شود. wrong password، tamper و truncation باید fail-closed باشند و plaintext موقت باقی نماند.
- افزودن dependency رمزنگاری باید در Installer/Updater fail-fast شود، نه اینکه بعد از نصب هنگام بحران کشف شود.
