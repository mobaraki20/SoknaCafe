# Pilot Checklist — Sokna 1.36.4-dev.20

- [ ] قبل از Update یک `sokna-backup-*` سالم و قابل دانلود وجود داشته باشد
- [ ] Update واقعی `1.36.4-dev.19 → 1.36.4-dev.20` اجرا و Health Check بررسی شود
- [ ] 128 آیتم و دسته‌های canonical پس از Update بدون تغییر ناخواسته با دادهٔ مورد انتظار تطبیق داشته باشند
- [ ] منوهای کافه/صبحانه/ناهار، وضعیت و ساعت سرو بررسی شوند
- [ ] دسته‌های فقط کارکنان در Guest مخفی و در Staff قابل مشاهده باشند
- [ ] `/menu/`, QR میز، Quick Order و Search/Sort/Bulk Regression PASS
- [ ] Backup `.skb` امن → Upload با رمز → Restore روی نصب دوم آزمایش شود؛ نگهداری جداگانه رمز نیز تأیید شود
- [ ] Agent 6.2.0 و Destination/Templateها پس از Update سالم باشند
- [ ] Test Print مشتری و آماده‌سازی روی کاغذ واقعی انجام شود
- [ ] Preview دقیق/تقریبی، DPI و geometry روی Printer هدف بررسی شود
- [ ] Settlement / Late Accounting / Close و فراخوان گارسون Regression PASS

موارد محیط واقعی که اجرا نشده‌اند `UAT_REQUIRED` باقی می‌مانند.
