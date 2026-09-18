# سیاست اصلاح ریشه‌ای و حذف Legacy در Sokna

این سند یک **قید مهندسی فعال** برای توسعه Sokna است و تا زمانی که Owner صریحاً آن را تغییر ندهد، در تمام تغییرات فنی، UI/UX، Schema، API، JavaScript/CSS و Workflow لازم‌الاجرا است.

## 1) وضعیت فعلی محصول

Sokna هنوز وارد بهره‌برداری واقعی / Production-live نشده است. بنابراین تا قبل از اعلام صریح Owner مبنی بر عملیاتی‌شدن سامانه:

- حفظ کد منسوخ فقط برای Backward Compatibility هدف محصول نیست.
- کد، Route، API، Query، Migration، CSS/JS، Feature Flag، Compatibility Shim یا Workflow قدیمی که دیگر در معماری نهایی Owner معتبر ندارد، **قابل حذف، ادغام یا بازطراحی است**.
- وجود Legacy به‌تنهایی دلیل حفظ آن نیست.
- Breaking change کنترل‌شده قبل از Go-Live مجاز است، به شرط اینکه Source of Truth جدید مشخص، نصب/Upgrade پشتیبانی‌شده معتبر، تست‌ها به‌روز و مسیر Recovery روشن باشد.
- این مجوز شامل حذف کور اسناد مالی، Audit، داده‌های لازم برای Baseline یا کنترل‌های ایمنی نمی‌شود.

## 2) ممنوعیت «کد روی کد» و Patch Stacking

در Sokna راه‌حل پیش‌فرض برای Bug یا Requirement جدید، افزودن یک لایه دیگر روی منطق قبلی نیست.

موارد زیر بدون دلیل مستند ممنوع‌اند:

- ساخت تابع/کلاس/Route جدید برای دورزدن Owner موجود به‌جای اصلاح Owner اصلی.
- اضافه‌کردن CSS override انتهای فایل برای خنثی‌کردن Rule قبلی به‌جای اصلاح Rule و Cascade اصلی.
- افزودن Event Listener یا JavaScript handler دوم برای جبران رفتار handler قبلی.
- ساخت Query یا Mutation موازی برای همان Domain به‌جای استفاده یا اصلاح Contract اصلی.
- نگه‌داشتن مسیر قدیمی و جدید هم‌زمان صرفاً از ترس حذف Legacy.
- کپی‌کردن منطق مشترک در چند صفحه/ماژول به‌جای انتقال آن به Owner مشترک.
- افزودن شرط‌های متوالی و Compatibility Shim بدون برنامه حذف مشخص.

اصل اجرایی:

> **هر تغییر باید تا حد ممکن در پایین‌ترین Owner مشترک و در علت ریشه‌ای مسئله اصلاح شود؛ سپس مسیرهای منسوخ، overrideها و کدهای موازی مرتبط با همان Scope حذف شوند.**

## 3) روش اجباری اجرای تغییر

برای هر Bug، Refactor یا Feature که کد موجود را لمس می‌کند:

1. **Root Cause را پیدا کنید**؛ فقط Symptom قابل مشاهده را Patch نکنید.
2. **Owner واقعی را مشخص کنید**؛ Domain، Component، Service، CSS owner، State owner یا Contract مسئول باید معلوم باشد.
3. **راه‌حل را در همان Owner اصلاح کنید**؛ Owner دوم نسازید مگر Requirement معماری اثبات‌شده وجود داشته باشد.
4. **Call Siteها را یکپارچه کنید**؛ مصرف‌کنندگان باید به Source of Truth واحد منتقل شوند.
5. **Legacy مرتبط را حذف کنید**؛ کد مرده، Route قدیمی، override، duplicated handler و compatibility path بدون مصرف باقی نماند.
6. **Test را با رفتار جدید هم‌راستا کنید**؛ تست نباید فقط Legacy را زنده نگه دارد.
7. **Regression را اجرا کنید**؛ Root refactor مجوز شکستن رفتارهای سالم مجاور نیست.
8. **Documentation را به‌روز کنید**؛ اگر Owner، Contract یا Workflow تغییر کرده، نقشه معماری/تصمیم/هنداور نیز باید تغییر کند.
9. **UI/Behavior Preservation را Gate کنید**؛ مگر Task صریحاً Redesign/Behavior Change باشد، ظاهر، جایگاه Actionها، Workflow و نتیجه مشاهده‌پذیر نباید ناخواسته تغییر کند. مرجع: `UI_BEHAVIOR_PRESERVATION_CONTRACT_FA.md`.

## 4) Root Refactor به معنی Rewrite بی‌هدف نیست

این سیاست مجوز بازنویسی گسترده بخش‌های سالم نیست.

- Scope باید به مسئله واقعی محدود بماند.
- بخش سالم و Regression-Locked بدون Bug یا Requirement اثبات‌شده تغییر نمی‌کند.
- هدف، **کم‌کردن مسیرها و Ownerهای موازی** است، نه بازنویسی پروژه برای سلیقه فنی.
- اگر اصلاح ریشه‌ای کوچک ممکن است، Rewrite بزرگ انتخاب ضعیف‌تری است.
- هر Refactor باید در پایان سیستم را ساده‌تر، قابل‌فهم‌تر و قابل‌تست‌تر کند؛ اگر تعداد مسیرها، Flagها یا Exceptionها بیشتر شد، طراحی باید دوباره بررسی شود.

## 5) Definition of Done برای تغییرات ریشه‌ای

تغییر فقط زمانی کامل است که:

- Root Cause اصلاح شده باشد.
- Source of Truth / Owner واحد مشخص باشد.
- مسیر موازی یا Legacy مربوط به Scope، در صورت نبود Requirement واقعی، حذف شده باشد.
- Dead code و overrideهای بی‌مصرف باقی نمانده باشند.
- Contractها و تست‌های مرتبط Pass باشند یا صریحاً `UAT_REQUIRED` ثبت شده باشند.
- نصب/Upgrade پشتیبانی‌شده جاری معتبر بماند.
- Documentation مربوط به Owner/Workflow/Decision به‌روز شده باشد.
- Preservation Gate رابط/رفتار برای Surface لمس‌شده PASS باشد یا تغییر عمدی با `REDESIGN_APPROVED` / `BEHAVIOR_CHANGE_APPROVED` ثبت شده باشد.

## 6) سؤال اجباری قبل از Merge

قبل از Merge یا Release پاسخ این سؤال باید روشن باشد:

> «آیا این تغییر علت اصلی را اصلاح کرده، یا فقط یک لایه کد دیگر روی مشکل قبلی گذاشته است؟»

اگر پاسخ دومی است، تغییر به‌صورت پیش‌فرض آماده Merge نیست.
