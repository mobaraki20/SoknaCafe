# Sokna Cafe — 1.36.4-dev.27

نسخهٔ dev.27 checkpoint نخست مهاجرت معماری است: Baseline dev.26 را پاک‌سازی می‌کند و Foundation مربوط به Local Runtime، correlation/logging و HTTPS محلی را اضافه می‌کند؛ Business behavior موجود بدون تصمیم Frozen تغییر نکرده است.

- نسخه جاری Web/PWA: `1.36.4-dev.27`
- Updater: `1.5.3`
- Upgrade رسمی این بسته: `1.36.4-dev.26 → 1.36.4-dev.27`
- منوی عمومی و میز فقط از مسیر canonical `/menu/` ارائه می‌شود.
- Catalog واحد برای Guest/Table/Quick Order: منو → دسته → آیتم؛ ایستگاه آماده‌سازی مستقل باقی می‌ماند.
- منوهای اولیه داده‌محور: کافه، صبحانه، ناهار؛ شام hard-code نشده است.
- مدیریت منو شامل Search/Filter/Sort/Bulk و چیدمان دسته/آیتم در همان Owner است.
- Backup/Restore داخلی همان فرمت قابل‌حمل معتبر را حفظ می‌کند؛ نسخهٔ خارج از سرور به‌صورت `.skb` رمزگذاری‌شده با رمز بازیابی کاربر صادر می‌شود.
- Windows Print Agent پیشنهادی برای این checkpoint: RC نسخهٔ `6.2.4`؛ نصب Production فقط پس از UAT واقعی مجاز است.
- Print Reliability نسخه‌های قبلی حفظ شده، پاسخ Claim به snapshot تغییرناپذیر متصل است و تعارض فقط با مدرک کافی و attempt جدید رفع می‌شود.
- Status: Pre-Operational / UAT required؛ Production Go-Live فقط پس از DB/HTTP/Printer/Device UAT واقعی.

مرجع توسعه: `DEVELOPER_READ_FIRST_FA.md` و اسناد معماری جاری در `docs/`.
