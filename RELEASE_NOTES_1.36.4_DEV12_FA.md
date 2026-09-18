# Release Notes — Sokna Cafe 1.36.4-dev.12

## Scope
این checkpoint فقط فاز Owner/Route منوی مهمان را از dev.11 اجرا می‌کند. Redesign منو، تغییر Settlement engine، تغییر House و Feature غیرمرتبط وارد Scope نشده است.

## Route و Owner
- `/menu` منوی عمومی است.
- `/menu?table=TOKEN` همان UI را در Context معتبر میز با قابلیت سفارش نمایش می‌دهد.
- `menu.php` تنها PHP renderer منوی مهمان/میز است.
- `assets/js/menu.js` Runtime مشترک Public/Table است؛ `assets/js/menu-preview.js` حذف شد.
- `/` دیگر Owner منوی مهمان نیست و به Gateway موجود Staff/Login متصل است؛ Redirect سازگاری برای Route قدیمی ساخته نشده است.

## QR / SEO
- `table_menu_url()` مستقیماً `/menu?table=TOKEN` تولید می‌کند؛ QR Admin همان Helper را مصرف می‌کند.
- canonical عمومی و Sitemap به `/menu` منتقل شدند.
- Table-token context و invalid-token 404 دارای `noindex` هستند.
- Block عمومی `robots.txt` برای query میز حذف شد تا page-level `noindex` قابل مشاهده باشد.

## Public waiter call
- قابلیت فراخوان گارسون در منوی عمومی حفظ شده و نام Domain از Preview به Public منتقل شد.
- مهمان در `/menu` میز فعال را انتخاب می‌کند؛ API مجدداً `active=1` را سمت سرور اعتبارسنجی می‌کند.
- Rate limit، idempotency، client/device token و ownership لغو حفظ شده‌اند.
- Shared active call متعلق به دستگاه دیگر به‌عنوان owned نمایش داده نمی‌شود.
- خاموش‌کردن قابلیت، Create جدید را متوقف می‌کند ولی Status/Cancel فراخوان قبلی را کور نمی‌کند.
- Setting از `preview_waiter_call_enabled` به `public_waiter_call_enabled` migrate می‌شود؛ message key متناظر نیز migrate می‌شود.

## Cleanup
- Preview runtime fork حذف شد.
- naming `is-menu-preview/guest-mode-preview/preview_table_id` از Runtime جاری حذف و به Public semantics منتقل شد.
- CSS selectorهای Preview بدون Consumer در Owner پنل حذف شدند.
- Testهایی که جدایی Runtime قدیمی را الزام می‌کردند با Contract Product جدید همگام شدند؛ assertionهای functional/layout ضعیف نشده‌اند.

## Updater Acceptance
بسته Update با `sokna-release-v2` و Engine `1.5.3` ساخته و Manifest/Hash/Delete/Migration آن بررسی شد. Acceptance اجرایی روی کپی dev.11 در این محیط قبل از تغییر فایل‌های زنده Fail-closed شد، چون `pdo_mysql`/MySQL برای snapshot و اجرای Migration موجود نیست. بنابراین Updater Acceptance این checkpoint در این محیط `UAT_REQUIRED` است و PASS اعلام نمی‌شود.

## Test status
Automated blockerها در اجرای قطعه‌بندی‌شده PASS شدند: PHP/JS syntax، 102/102 unit checks، Owner/Module/Finance/Inventory/Settlement/Quick Order/Guest/Printing/Accommodation contracts، Browser blocker matrix، Public waiter picker در 320/360/390/412، Public Menu در 360/390/412/1366/1440/1920، Quick Order production در 390/1366/1920، Mobile Cashier/Table Overview، Startup no-flash، Itemized/Late Accounting و automated visual evidence.

اجرای یک‌تکه Release Gate به سقف زمانی محیط خورد؛ بنابراین Full one-shot PASS ادعا نمی‌شود. تمام قسمت‌های باقی‌مانده Gate به‌صورت قطعه‌بندی‌شده اجرا و PASS شدند. Human visual baselines در خود Gate `UAT_REQUIRED` گزارش شدند.

Critical DB/HTTP probes اجرا شدند و به‌علت نبود `pdo_mysql` و authenticated staging session در محیط بررسی، `UAT_REQUIRED` گزارش شدند. Android/PWA device lifecycle، Push واقعی، Windows Print Agent/Printer، House واقعی، Subscriber واقعی، timeout/قطع شبکه مالی و Pilot shift نیز `UAT_REQUIRED` هستند.
