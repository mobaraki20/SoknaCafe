# 1.36.0 Development Changelog

- Modular Monolith Registry اضافه شد.
- Supply/Purchasing به‌عنوان Pilot به `modules/Supply/` منتقل شد.
- State عملیاتی «در حال تهیه» بدون تغییر موجودی اضافه شد.
- نیازهای یک کالای شناخته‌شده در Purchase UI بین بخش‌ها تجمیع شدند.
- نیاز جدید هنگام وجود Preparing مستقل باقی می‌ماند.
- Receipt تجمیع‌شده یک Movement می‌سازد و Allocation آن روی Needها audit می‌شود.
- Quantity فرم دریافت دیگر از Need prefill نمی‌شود و با تغییر Unit پاک می‌شود.
- Unknown item در زمان خرید با `needs_review` ساخته می‌شود.
- Low Stock دیگر مقدار خرید را حدس نمی‌زند و Negative Stock را پیشنهاد خرید عادی نشان نمی‌دهد.
- Date input خرید به Jalali owner مشترک متصل شد.
- فایل versioned `purchases-v1350.js` به owner پایدار `supply-purchases.js` تغییر نام داد.
- Snapshot guard برای Actionهای خرید اضافه شد تا تغییر concurrent نیازها بی‌صدا وارد «در حال تهیه» یا Receipt نشود.

## UI / Reliability Hardening

- فرم «ثبت تحویل» از Grid مخصوص Confirmation جدا و به Form Dialog/Sheet مشترک تبدیل شد؛ Header، Body قابل Scroll و Actionهای نهایی Owner مشخص دارند.
- Autofocus مقدار تحویل حذف شد تا کیبورد موبایل بدون درخواست کاربر باز نشود.
- Refresh پس‌زمینه Push دیگر خطای عملیات جاری را با Toast قرمز جعل نمی‌کند و Subscription معتبر قبلی را روی خطای موقت پاک نمی‌کند.
- «شروع تهیه همه» فقط برای بیش از یک قلم نمایش داده می‌شود؛ Timestampهای خرید انسانی/شمسی شدند و Copyهای فنی/تکراری کوتاه شدند.
- Submit «اعلام نیاز» تا اضافه‌شدن اولین قلم پنهان می‌ماند.
- نیازهای کالای آزاد با نام و واحد یکسان برای Buyer تجمیع می‌شوند؛ Needهای Department همچنان مستقل می‌مانند.
- Guest Takeaway overlay از Cart Drawer جدا شد تا Backdrop واقعاً کل Viewport را بگیرد؛ Swipe روی Header، Tap بیرون و Scroll isolation قرارداد مستقل دارند.
- مرور سفارش Guest در برابر Recommendation قدیمی مقاوم شد؛ پیشنهاد فقط تا وقتی Source Item در Cart است باقی می‌ماند.
- یادداشت سفارش قبل از Upsell قرار گرفت، پیشنهاد فروش در Cartهای بزرگ Collapse و کم‌تأکید شد، تکرار Count بیرون‌بر کاهش یافت و Scroll cue واقعی اضافه شد.
- اگر Cart از آخرین Review تغییر کرده باشد، بازشدن دوباره از ابتدای Review شروع می‌شود؛ در غیر این صورت Scroll position حفظ می‌شود.
