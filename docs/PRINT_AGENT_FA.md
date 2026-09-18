# وضعیت Print Agent در Sokna 1.36.0

Print Agent **جزو پذیرش Production این Release نیست**.

Web Core همچنان Queue، Mapping و تنظیمات چاپ را نگه می‌دارد، اما Installer/Service Windows موجود در Tree به‌تنهایی به معنی Production-ready بودن چاپ نیست. پذیرش چاپ باید در یک جریان مستقل انجام شود و شامل حداقل موارد زیر باشد:

- نصب و Upgrade واقعی Windows
- Auto-start/SCM recovery
- اتصال و Authentication پایدار به Sokna
- Printer discovery و Mapping واقعی
- چاپ ESC/POS/Driver در مقصدهای واقعی
- Retry/Unknown-result بدون Duplicate
- Restart سیستم/Spooler/شبکه
- Uninstall/Upgrade و حفظ تنظیمات لازم

تا پایان این پذیرش، در اسناد Go-Live وب نباید چاپ «تأییدشده» اعلام شود. معماری آینده Print Core باید Adapter-based باشد و Core به Winspool قفل نشود؛ نسخه اول مجاز است فقط Winspool Adapter فعال داشته باشد.
