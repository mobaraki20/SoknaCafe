# تصمیم‌های فعال و قفل‌شده Sokna

1. بخش سالم بدون Bug/Requirement اثبات‌شده بازطراحی نمی‌شود.
2. Root Cause در Owner مشترک اصلاح می‌شود؛ CSS/JS patch stacking و مسیر موازی ممنوع است.
3. RTL: Primary/Confirm/اقدام نهایی راست؛ Secondary/Cancel/Reject/Back چپ؛ ترتیب DOM باید درست باشد.
4. حداقل Touch Target در موبایل 44px است.
5. اعداد کاربرخوان فارسی و مقادیر فنی/Canonical لاتین می‌مانند.
6. Overlay بر اساس معنای Interaction انتخاب می‌شود، نه سلیقه صفحه.
7. Nested Overlay پیش‌فرض ممنوع است؛ Choice داخل Workspace/Modal باید Embedded باشد مگر دلیل مستند وجود داشته باشد.
8. Quick Order از نظر Geometry/Color/Spacing محافظت‌شده است؛ فقط تغییرات صریحاً تأییدشده اعمال می‌شوند.
9. سفارش مهمان Pending فقط دو تصمیم دارد: تأیید یا رد؛ هر سفارش مستقل است.
10. `table_number` Source of Truth شماره میز است؛ Legacy فقط با Backfill بدون‌ابهام اصلاح می‌شود و Conflict پنهان نمی‌شود.
11. صفحه اختصاصی QR: استفاده عادی اول، Security بعد، Destructive آخر.
12. روز عملیاتی پیش‌فرض 04:00 است؛ یک شیفت = فیلتر شیفت مخفی؛ 2–3 شیفت = فیلتر/مقایسه.
13. Snapshot تاریخی روز/شیفت تغییرناپذیر است و شناسه شیفت تاریخی دوباره مصرف نمی‌شود.
14. اسناد مالی و تاریخچه اصلی حذف نمی‌شوند.
15. Push کانال کمکی است؛ شکست شبکه خارجی نباید مسیر ثبت سفارش را Block کند.
16. **Print Worker داخلی SOKNA Local است و محصول جدا نصب نمی‌شود.** state machine بالغ چاپ، durable queue و Winspool حفظ می‌شوند، اما build/install/repair/lifecycle آن مالکیت SOKNA Local است. پذیرش Windows و پرینتر واقعی همچنان Gate اجباری انتشار است و به معنی محصول مستقل نیست.
17. Test not run = Not tested. Device-sensitive finding تا UAT واقعی «CLOSED» نیست.
18. وضعیت پروژه تا اعلام صریح Owner **Pre-Operational** است؛ حفظ Legacy صرفاً برای Backward Compatibility الزام نیست و Cleanup/Breaking cleanup کنترل‌شده مجاز است. Source of Truth: `DEVELOPER_READ_FIRST_FA.md`.
19. مقیاس طراحی تا اعلام صریح Owner: حداکثر حدود 50 میز، 100–110 مهمان هم‌زمان در Peak، حدود 3 ماه Peak در سال و تیم عملیاتی حدود 8–9 نفر با مسئولیت‌های چندگانه. Overengineering برای مقیاس Enterprise ممنوع است مگر Requirement جدید صریح ثبت شود.
20. اجرای تغییرات باید Root-Cause/Owner محور باشد؛ «کد روی کد»، Owner موازی، duplicated handler/query/route و CSS/JS patch stacking بدون دلیل مستند ممنوع است. در Scope لمس‌شده، Legacy بی‌مصرف پس از انتقال مصرف‌کنندگان حذف می‌شود. Source of Truth: `docs/ROOT_CAUSE_REFACTOR_POLICY_FA.md`.
21. Refactor و Cleanup تا زمان Approval صریح Redesign/Behavior Change باید UI/Behavior-preserving باشند؛ جایگاه Actionها، Workflow، Permission و نتیجه مشاهده‌پذیر بدون Requirement تغییر نمی‌کند. Source of Truth: `docs/UI_BEHAVIOR_PRESERVATION_CONTRACT_FA.md`.
