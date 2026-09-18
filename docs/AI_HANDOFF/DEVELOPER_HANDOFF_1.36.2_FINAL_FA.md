# هنداور توسعه Sokna 1.36.2

## وضعیت انتشار

- نسخه مقصد: `1.36.2`
- Baseline رسمی Update: Full Project منتشرشده `1.36.1`
- Updater Engine: `1.5.3`
- Migration دیتابیس: ندارد
- نوع تحویل: Full Project + Update استاندارد + SHA256SUMS
- Artifactها: `Sokna-1.36.2-Final-Full-Project.zip` و `Sokna-1.36.1-to-1.36.2-Update.zip`
- وضعیت چرخه عمر: PRE-OPERATIONAL / Test

## تغییرات این Release

### Guest Order Review
- Ownerهای موجود `assets/css/guest-menu.css` و markup جاری حفظ شده‌اند.
- پس‌زمینه بیرون‌بر subtle شده و Order Note به disclosure فشرده 44px تبدیل شده است.
- CSS owner تکراری یادداشت حذف شده؛ override یا stylesheet جدید ساخته نشده است.

### Staff Quick Order
- Ownerهای `staff/quick-order.php`, `assets/js/staff-quick-order.js`, `assets/css/quick-order.css` حفظ شده‌اند.
- هنگام Takeaway editing، quantity کل Read-only است و Takeaway quantity تنها کنترل قابل تغییر است.
- Action اصلی «تأیید» و ترتیب RTL اصلاح شده و geometry تا Tablet gate شده است.

### Purchasing
- `admin/purchases.php` و `assets/css/inventory.css` همان Ownerهای اصلی‌اند.
- `min-height:154px` و nested purchase card owner حذف شده و list بر مبنای content متراکم شده است.
- در <=360px layout برای خوانایی Stack و در عرض‌های بزرگ‌تر compact باقی می‌ماند.

### Print v4 Observability
- `print-agent/v4/api.php` فقط فیلدهای diagnostic اختیاری را در `print_agents.health_json` موجود نگه می‌دارد.
- `admin/printing.php` Healthy/Degraded/Attention را نمایش می‌دهد.
- هیچ Migration، API موازی، retry owner، broker یا تغییر State Machine ایجاد نشده است.
- Agent source/binary همچنان داخل Cafe bundle نیست.

### Supply test contract
- تست `v1340-operations-purchase-permissions.py` دیگر Copy قدیمی UI را به‌عنوان اثبات Multi-item قبول نمی‌کند و Form/JS fields/loop/upsert واقعی را بررسی می‌کند.

## قراردادهای حفظ‌شده

- Modular Monolith و Ownerهای رسمی سامانه حفظ شده‌اند.
- Print API همچنان Protocol v4 است.
- Business logic مالی، Ledger انبار، Settlement و Order state machine خارج از Scope تغییر نکرده‌اند.
- Update استاندارد فقط `1.36.1 → 1.36.2` است.

## UAT

نتیجه Source/Browser/Updater در `docs/TEST_REPORT_V1.36.2_FINAL_FA.md` ثبت می‌شود. UAT واقعی Host/DB/گوشی/Service Worker و چاپگر پیش از Go-Live الزامی باقی می‌ماند.
