# قبل از هر تغییر در Sokna این فایل را بخوانید

این سند **قید مهندسی فعال و قفل‌شده توسط Owner** است و تا زمانی که Owner صریحاً آن را تغییر ندهد، باید مبنای تصمیم‌های معماری، UI/UX، Migration، سازگاری عقب‌رو و Scope قرار بگیرد.

## 1) وضعیت چرخه عمر: PRE-OPERATIONAL / قبل از بهره‌برداری واقعی

Sokna هنوز عملیاتی/Production-live اعلام نشده است.

تا زمانی که Owner صریحاً اعلام نکند «سامانه عملیاتی شده است»:

- حفظ Legacy صرفاً برای Backward Compatibility هدف نیست.
- کد، API، Route، CSS/JS owner، Schema، Migration chain، compatibility shim، feature flag یا workflow قدیمی که دیگر در طراحی نهایی لازم نیست **می‌تواند حذف، ادغام یا بازطراحی شود**.
- اگر راه‌حل تمیزتر نیازمند Breaking change قبل از Go-Live باشد، Breaking change مجاز است؛ به شرط اینکه Source of Truth جدید مشخص، Migration/installer جاری معتبر، تست‌ها به‌روز و Release artifact قابل نصب باشد.
- مسیر موازی قدیمی فقط برای اینکه «شاید یک نصب قدیمی وجود داشته باشد» نگه داشته نشود، مگر Owner صریحاً چنین نصب یا الزام سازگاری را معرفی کند.
- Cleanup باید Root Cause محور باشد؛ Patch stacking و نگه‌داشتن دو Owner برای یک رفتار ممنوع است.
- حذف داده‌های تاریخی/مالی واقعی یا چیزی که برای Audit همین Baseline لازم است همچنان بدون بررسی مجاز نیست. «Pre-operational» به معنی حذف کور داده یا کنترل‌های ایمنی نیست.

### نتیجه عملی برای توسعه‌دهنده
اگر بین این دو انتخاب هستید:
1. نگه‌داشتن مسیر قدیمی + افزودن مسیر جدید، یا
2. حذف مسیر قدیمی و داشتن یک Owner تمیز و تست‌شده،

در وضعیت فعلی پروژه، **گزینه 2 پیش‌فرض است** مگر دلیل مستند فنی/داده‌ای برای حفظ Legacy وجود داشته باشد.

### قاعده اجباری اجرا: اصلاح ریشه‌ای، نه «کد روی کد»

در هر تغییر باید ابتدا Root Cause و Owner اصلی پیدا شود. افزودن handler/route/query/CSS override/compatibility path جدید برای دورزدن منطق قبلی، بدون دلیل مستند، ممنوع است. اگر Scope لمس‌شده دارای مسیر منسوخ یا Owner موازی است، بعد از انتقال مصرف‌کنندگان به Source of Truth جدید، مسیر قدیمی باید حذف شود.

این سیاست به معنی Rewrite بی‌هدف بخش‌های سالم نیست؛ Refactor باید محدود به مسئله واقعی باشد و Regressionهای موجود را حفظ کند. Source of Truth کامل این قاعده: [`docs/ROOT_CAUSE_REFACTOR_POLICY_FA.md`](docs/ROOT_CAUSE_REFACTOR_POLICY_FA.md).

**قفل حفظ ظاهر و رفتار:** هر Cleanup/Refactor تا زمانی که Owner صریحاً Redesign یا Behavior Change را تأیید نکرده، باید UI-preserving و Behavior-preserving باشد. مرجع اجباری: [`docs/UI_BEHAVIOR_PRESERVATION_CONTRACT_FA.md`](docs/UI_BEHAVIOR_PRESERVATION_CONTRACT_FA.md).

---

## 2) مقیاس ثابت فعلی Sokna

تا اعلام صریح Owner، سامانه برای این مقیاس طراحی و بهینه می‌شود:

- یک مجموعه Sokna در مقیاس کافه/مهمان‌پذیری فعلی، نه معماری Enterprise یا زنجیره چندشعبه‌ای.
- حداکثر حدود **50 میز**.
- حداکثر حدود **100 تا 110 مهمان هم‌زمان** در Peak واقعی.
- Peak سنگین تقریباً **3 ماه در سال** است؛ در بقیه سال بار عملیاتی به‌مراتب کمتر است.
- کل تیم عملیاتی معمولاً حدود **8 تا 9 نفر** است و هر نفر می‌تواند چند مسئولیت داشته باشد.

این اعداد «Capacity target» هستند، نه دعوت به ساخت معماری در مرز ظرفیت. سیستم باید در این مقیاس با حاشیه امن مناسب پایدار باشد.

### پیامدهای معماری و محصول

- سادگی، Reliability، سرعت Staff، قابلیت نگهداری و کنترل مدیریتی بر معماری پیچیده مقدم است.
- Microservice، distributed queue، multi-region، tenancy پیچیده، workflow approval چندمرحله‌ای یا ACL بسیار ریز فقط با Requirement اثبات‌شده مجاز است.
- UI نباید برای صدها کاربر سازمانی یا نقش‌های بسیار تخصصی طراحی شود.
- Permissionها باید حول **مسئولیت‌های واقعی چندگانه کارکنان** گروه‌بندی شوند، نه ده‌ها capability قابل مشاهده برای مدیر.
- اتوماسیون نباید کاری ایجاد کند که تیم کوچک مجبور به ثبت دستی مکرر و کم‌ارزش شود.
- برای Peak کافه، رفتار Offline/unstable internet، idempotency، جلوگیری از duplicate، audit و recovery مهم‌تر از scale-out پیچیده است.
- Print path حیاتی است، اما حجم سفارش Sokna نیازمند معماری Enterprise message-bus نیست؛ Reliability invariantها باید متناسب با همین مقیاس حفظ شوند.

---


## 3) قاعده معماری: Modular Monolith، نه Plugin/Service Platform

از شاخه توسعه 1.36 به بعد، مرزبندی داخلی Sokna بر مبنای **Modular Monolith** است: یک PHP application، یک MySQL/MariaDB، یک Release و یک Auth/Session؛ با Owner و Dependency صریح برای Domainها.

- Registry مرکزی ماژول‌ها: `includes/modules.php`
- نقشه فعال ماژول‌ها و Dependencyها: `docs/ARCHITECTURE_MODULE_MAP_1.36_FA.md`
- Gate مالکیت ماژول‌ها: `tests/v1360-module-ownership-contract.py`؛ در 1.36 فعلی ۱۳ ماژول، ۵۵ Table جاری و ۹۶ Route/API را پوشش می‌دهد.
- کد جدید حق Mutation مستقیم داده Owner دیگر را ندارد؛ باید از Contract عمومی آن Domain استفاده کند. Cross-writeهای تاریخیِ فعلی در Module Map ثبت شده‌اند و هنگام لمس همان Workflow باید تدریجی جمع شوند.
- Dependency cycle مجاز نیست.
- Module toggle فقط وقتی ساخته می‌شود که Route، Navigation، Background work، History و Permission آن end-to-end gate شده باشند. Toggle نمایشی/نیمه‌کاره ممنوع است.
- خاموش‌شدن قابلیت در آینده مجوز حذف History، Audit یا داده مالی نیست.
- Microservice، Plugin runtime، Database جدا یا Message Broker خارجی بدون Requirement اثبات‌شده ممنوع است.

Supply/Purchasing اولین Pilot مرزبندی Domain است و Owner آن `modules/Supply/` است. Marketing اولین Pilot Runtime Toggle واقعی است؛ `module.marketing.enabled` فقط پس از Gate کامل Navigation/Route/Guest output/Metric فعال شده و خاموش‌کردنش داده‌های قبلی را حذف نمی‌کند.

---

## 4) Change Control این دو قاعده

این دو فرض فقط با **اعلام صریح Owner** تغییر می‌کنند:

1. `Operational Status`: وقتی Owner اعلام کند سامانه عملیاتی/Production-live شده است، از آن نقطه به بعد سیاست Backward Compatibility، Migration safety و حذف Legacy باید دوباره تعریف و سخت‌گیرانه‌تر شود.
2. `Sokna Scale`: اگر Owner ظرفیت، تعداد میز/مهمان، تعداد کارکنان، تعداد شعب یا مدل استقرار را تغییر دهد، معماری و تست ظرفیت باید بازبینی شود.

حدس توسعه‌دهنده، افزایش احتمالی آینده، یا «ممکن است روزی چند شعبه شود» مجوز تغییر این Baseline نیست.

---

## 5) قبل از Merge/Release بپرسید

- آیا این قابلیت برای تیم 8–9 نفره واقعاً لازم است؟
- آیا داریم Enterprise complexity را برای مسئله‌ای کوچک وارد می‌کنیم؟
- آیا Legacy را بدون دلیل واقعی نگه داشته‌ایم؟
- آیا می‌توان Owner دوم/مسیر موازی را حذف کرد؟
- آیا Workflow در Peak با 100–110 مهمان سریع و قابل فهم است؟
- آیا تغییر، داده/مالی/Audit/Print reliability را بدون مسیر Recovery به خطر می‌اندازد؟

اگر پاسخ‌ها روشن نیست، قبل از توسعه Scope را دوباره بررسی کنید.


### Phase 2 Relay ownership
از 1.36.4-dev.28، Relay یک ماژول Core صریح است. جدول relay_processed_requests فقط متعلق به Relay محلی است؛ Public Edge فقط state محدود انتقال، Auth Projection، Heartbeat و Emergency Audit را نگه می‌دارد و مالک Business State canonical نیست.


### Phase 4 Remote Read ownership
از `1.36.4-dev.30`، دسترسی راه‌دور کارکنان برای Read Modelها از همان Local account/capability/area projection استفاده می‌کند. Public فقط آخرین snapshotهای محدود `operations/preparation/inventory/inventory_cost/reports` را نگه می‌دارد؛ Order/Settlement/Inventory canonical state همچنان فقط Local است. Surface راه‌دور Phase 4 read-only است و در stale/offline mode باید آخرین sync را صریح نشان دهد. هیچ capability، role یا permission system موازی برای Remote ساخته نشود.
