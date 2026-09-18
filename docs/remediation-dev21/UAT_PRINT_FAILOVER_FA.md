# UAT-F01 — Primary outage → Fallback → Promote → Retire

وضعیت این تحویل: `UAT_REQUIRED`

## پیش‌نیاز

- «صندوق سکنا» Primary و «خانه» Fallback.
- هر دو Agent باید نسخه target واقعی و تأییدشده داشته باشند.
- Windows printer queueهای واقعی مشخص باشند.

## مراحل و Evidence

- [ ] هر دو Agent online و Heartbeat/Discovery تازه هستند.
- [ ] Test print از Primary و ثبت شناسه Job/Attempt.
- [ ] Primary از دسترس خارج شود؛ Web باید Primary unavailable و Fallback effective نشان دهد.
- [ ] Job جدید به Fallback route شود؛ خروج کاغذ/Spooler evidence ثبت شود.
- [ ] «تبدیل مسیر جایگزین به اصلی» اجرا شود؛ before/after audit ثبت شود.
- [ ] Fallback قبلی Primary شود و Job جدید به آن برود.
- [ ] Attempt قبل از Promote همان destination snapshot قبلی را نگه دارد.
- [ ] Agent قدیمی تا وقتی live route reference دارد قابل Retire نباشد.
- [ ] پس از reroute و Drain، Agent قدیمی Retire شود و history/attempt/audit باقی بماند.
- [ ] Agent بازنشسته در selector عملیاتی دیده نشود.
- [ ] Agent/Web restart و health check تکرار شود.
- [ ] Support Package نهایی فاقد token/lease/payload/queue.db باشد.

## معیار قبولی

- بدون duplicate print.
- بدون silent lost job.
- بدون auto-reprint در ambiguity.
- stale heartbeat/discovery هرگز Ready تلقی نشود.
- retired agent قابل استفاده عملیاتی نباشد.
- diagnostics علت failure را مشخص کند.

خروج واقعی کاغذ و رفتار Windows Spooler فقط با hardware evidence می‌تواند PASS شود.
