# زمینه جاری پروژه Sokna — Baseline 1.36.4-dev.15

- Checkpoint قابل نصب محیط تست: `1.36.4-dev.15`. این نسخه ادامه مستقیم `1.36.4-dev.14` و Pilot-ready است؛ Release عمومی/Production Go-Live نیست.
- مسیر Upgrade رسمی این checkpoint: `1.36.4-dev.14 → 1.36.4-dev.15` با Updater Engine `1.5.3` و Migration یک‌باره پاکسازی Print Test Queue/History پس از Hotfix سازگاری Accept.
- قبل از هر تغییر: `DEVELOPER_READ_FIRST_FA.md` و Handoff جاری را بخوان.
- چرخه عمر: Pre-Operational/Pilot-ready تا اعلام صریح Owner. Legacy بدون Consumer واقعی بعد از مهاجرت Call Siteها حذف می‌شود.
- Root Cause/Owner اصلی را اصلاح کن؛ patch stacking، duplicated handler/query/route/renderer و CSS/JS fork ممنوع است.
- مقیاس ثابت فعلی: حداکثر حدود 50 میز، 100–110 مهمان هم‌زمان در Peak، تیم 8–9 نفره با مسئولیت‌های چندگانه.
- Web/PWA فارسی و RTL برای کافه/مهمان‌پذیری واقعی Sokna.
- Business day پیش‌فرض 04:00 است؛ اسناد مالی و Audit ماندگارند.
- Updater فقط `/admin/update/` با Engine نسخه‌دار است؛ Print Agent پذیرش Windows جدا دارد.
- Test not run = Not tested.

## نقاط قفل‌شده نسخه‌های اخیر
- `dev.7`: Order Context/Settlement یکپارچه و paid-state-aware account signature.
- `dev.8`: Mobile account presentation تأییدشده؛ Settlement/Late Accounting compact؛ Accommodation error classification + tracking_id.
- `dev.9`: Mobile table overview به کنترل مستقیم؛ feedback تکراری مالی حذف؛ Stepper/CTA معنایی.
- `dev.10`: `open_table` از First Paint بدون Overview flash؛ owner cleanup؛ touch/keyboard focus modality.
- `dev.11`: Operational Review و Pilot-ready checkpoint؛ Itemized mobile polish و Test Governance.
- `dev.12`: Owner منوی Guest/Table به `/menu` منتقل شد؛ Public/Table روی یک renderer/runtime/CSS family Consolidate شدند؛ QR/SEO به `/menu` منتقل شد؛ Public waiter call با انتخاب میز حفظ شد؛ `/` staff gateway شد.
- `dev.13`: مرور سفارش مهمان Compact شد بدون کوچک‌کردن Touch Target؛ خلاصه بیرون‌بر صریح‌تر شد؛ Focus کیبورد بعد از Quantity rerender حفظ می‌شود؛ Fulfillment selectorها pure read شدند؛ CSS footer شیت بیرون‌بر Consolidate شد.
- `dev.14`: Print Reliability؛ حذف امن مقصد سفارشی بدون سابقه، نمایش FIFO blocker، Failover واقعی Primary/Fallback، پاکسازی یک‌باره Print Test Jobs/Attempts/Claims و Consolidation Stepper اصلاح تعداد.
- `dev.15`: Print API v4 Accept Compatibility؛ Contract `local_receipt_id` با Agent 6.0/6.1 (`r-<32 hex>`) همگام شد، خطاهای Receipt/Hash تفکیک و Test Queue برای Retest تمیز یک‌باره پاک می‌شود.

## قرارداد جاری منو
- `/menu` = منوی عمومی، read-only از نظر سفارش ولی دارای قابلیت فراخوان گارسون در صورت فعال بودن Setting.
- `/menu?table=TOKEN` = همان UI با Context معتبر میز و Order capability.
- Public/Table یک Owner (`menu.php`)، یک JS state/runtime (`assets/js/menu.js`) و یک CSS owner family دارند.
- هیچ Redirect/Compatibility برای Route قدیمی ساخته نمی‌شود چون QR چاپ‌شده Legacy وجود ندارد.
- QRهای جدید مستقیماً `/menu?table=TOKEN` تولید می‌شوند.
- ظاهر منوی dev.11 Visual Baseline است؛ تغییر ظاهری فقط برای Bug/Accessibility/Layout break مجاز است.
- Public waiter call میز انتخاب‌شده را سمت سرور با `active=1` اعتبارسنجی می‌کند؛ client/device ownership و idempotency/rate limit حفظ می‌شود.
- `/menu` canonical و crawlable است؛ `/menu?table=TOKEN` و invalid token `noindex` هستند.
