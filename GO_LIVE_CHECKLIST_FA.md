# Go-Live Checklist — Sokna 1.36.4-dev.20

این نسخه هنوز Pre-Operational است. مورد اجرا‌نشده = تست‌نشده.

- [ ] Upgrade واقعی `1.36.4-dev.19 → 1.36.4-dev.20` روی MySQL/MariaDB و Schema Health PASS
- [ ] Clean Install dev.20 روی MySQL/MariaDB واقعی PASS
- [ ] Backup dev.20 ساخته، خروج امن `.skb` با رمز بازیابی، Upload و Restore کامل روی نصب جداگانه PASS
- [ ] پس از Restore، کاربران/سفارش‌ها/تنظیمات/Uploads/Secretهای داخلی قابل استفاده باشند
- [ ] `/menu/` عمومی، Table Context و QR واقعی PASS
- [ ] کافه/صبحانه/ناهار، ساعت سرو، audience دسته و Search/Sort/Bulk در Admin/Quick Order PASS
- [ ] سفارش مهمان و Quick Order از Catalog مشترک و Server-side submit validation PASS
- [ ] Windows Print Agent 6.2.0 + Printer واقعی: customer/preparation PASS
- [ ] FIFO blocker / retry cycle / ambiguity / report recovery / local wake UAT واقعی
- [ ] `submitted` با خروج واقعی کاغذ اشتباه نشود؛ هر دو جدا تأیید شوند
- [ ] Android/PWA/Push و Browser Local Network Access روی دستگاه واقعی PASS
- [ ] Accommodation / Subscriber اتصال واقعی PASS
- [ ] Human visual UAT روی 320/390/412 و Desktop
