# Sokna Cafe 1.36.4-dev.10 — Stabilization Test RC

این Build Feature جدید نیست؛ هدف آن تثبیت و Consolidation تغییرات dev.7 تا dev.9 و حذف Patch Stacking اخیر است.

## اصلاحات ریشه‌ای
- Startup Context: وقتی Route با `open_table` باز می‌شود، همان حساب میز از First Paint Owner صفحه است؛ Overview قبل از آن Render نمی‌شود.
- Tables/Invoice: DOM جدول و CSS نهایی روی selector واحد `bill-table-current` همگام شدند؛ Override قدیمی‌ای که mismatch را پنهان می‌کرد حذف شد.
- Runtime Owner Cleanup: نام‌های موقت Preview مانند v10/v13/v16/v19/v20 از Ownerهای جدید Runtime حذف و به نام‌های معنایی تبدیل شدند.
- Itemized Settlement: Review مالی سرور حفظ است، اما Feedback موفقیت از متن طولانی Backend مستقل و غیرتکراری شده است.
- پایان حساب: پیام موفقیت کوتاه و عملیاتی است؛ متن طولانی مبلغ/مانده/صف چاپ روی Overview نمایش داده نمی‌شود.
- ردیف‌های Itemized: «مانده» اطلاعات اصلی است و تعداد پرداخت‌شده به Metadata ثانویه منتقل شده است.
- Touch/Keyboard: Focus ring بعد از Touch روی کنترل‌های عمومی باقی نمی‌ماند؛ Keyboard `focus-visible` حفظ می‌شود.
- Mobile overview/account/Quick Order: Presentation تأییدشده موبایل قفل است و Desktop presentation از آن جدا می‌ماند.
- Accommodation: همان Contract واحد Settlement/Response classification dev.8/dev.9 حفظ شده؛ House تغییری ندارد.

## Database
Migration دیتابیس ندارد.

## Update
مسیر تستی رسمی: `1.36.4-dev.9 → 1.36.4-dev.10`.

## UAT_REQUIRED
- گوشی واقعی Chrome/PWA: ورود از Quick Order به همان میز بدون Flash.
- مسیر کامل میز → سفارش → پرداخت جزئی → قلم جاافتاده → پایان حساب.
- Charge/void واقعی House، Network interruption و reconciliation.
- چاپگر واقعی و Windows Print Agent.
- MySQL/MariaDB واقعی و authenticated staging routes.
