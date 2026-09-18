# Release Notes — Sokna Cafe 1.36.4-dev.11

## نوع نسخه
Test / Operational-readiness checkpoint؛ Release عمومی نیست.

## تغییرات نسبت به dev.10
- Polish نهایی Mobile Itemized Settlement: Scrollbar بصری حذف، Stepper بصری سبک‌تر با Touch target ثابت، Action قلم جاافتاده صریح‌تر.
- Success notice موفقیت پس از 2.6s جمع می‌شود؛ Warningها باقی می‌مانند.
- تست Browser و Contract برای موارد بالا اضافه/تقویت شد.
- Test governance و گزارش Operational Readiness به‌روز شد.

## بدون تغییر
- Schema و Migration دیتابیس تغییر نکرده‌اند.
- Settlement financial engine / allocation / idempotency تغییر نکرده است.
- Quick Order عادی موبایل و Desktop layoutهای تأییدشده تغییر نکرده‌اند.
- House تغییر نمی‌کند.

## UAT_REQUIRED
MariaDB/MySQL واقعی، authenticated staging، چاپگر/Agent واقعی، PWA/Android/Push واقعی و human visual baselines.
