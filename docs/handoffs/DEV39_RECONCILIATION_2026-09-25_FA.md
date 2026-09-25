# وضعیت مرجع فعلی

جدول پذیرش به‌روز: `docs/architecture-migration-r2/PHASE8B_WINDOWS_ACCEPTANCE_STATUS_2026-09-25_FA.md`.
Run `36165915622` موفقیت Linux regression و Public Relay/MariaDB را تأیید کرد، اما Windows Phase 1 در مقایسه AppRoot و full-stack در مرحله نصب MariaDB شکست خوردند. artifact `10877474564` مرحله `mariadb-install` و timeout ۶۰۰ ثانیه‌ای را ثبت کرد. اصلاح مقایسه معنایی مسیر Apache و runner/log/cleanup نصب MSI همراه checkpoint جاری است و باید در CI تأیید شود. نصب نهایی منتشر نشده است.

---

# به‌روزرسانی تثبیت پیش‌نیاز و اجرای full-stack

Freeze واقعی Windows در run https://github.com/mobaraki20/SoknaCafe/actions/runs/36130815720 موفق شد. `release-lock.json` پس از تطبیق آرشیو شاهد، چهار hash/size و امضای Microsoft در commit `12f0cb16a8786ef0ea7f0dda56d6395a3125fa4c` ثبت شد. جزئیات بازبینی: `PREREQUISITE_FREEZE_REVIEW_2026-09-25_FA.md` و JSON شاهد کنار آن. تأیید artifact پیش‌نیاز، تأیید محصول نهایی نیست.

full-stack اکنون در PR هم اجرا می‌شود و checkout همان head را دارد. اولین run: https://github.com/mobaraki20/SoknaCafe/actions/runs/36131102833 . نتیجه در زمان این ثبت هنوز در انتظار است؛ job اختیاری WiX همچنان اجرا نشده و پذیرش EULA انجام نشده است.

آخرین اصلاح تست Inno در commit `5f94b61d89968dedf8e236d0a54a0727ebeb3a87`: مقایسه نام ورودی‌های خود ZIP به جای برش مسیرهای کوتاه/بلند Windows؛ چهار فایل دقیق مجاز حفظ شدند و نام فایل‌های غیرمنتظره در خطا دیده می‌شود. گذر lifecycle هنوز اثبات نشده است. تنظیمات Apache در PowerShell 5.1 اصلاح شده و تست‌های مستقل آن پیش از lifecycle اجرا می‌شوند.

---

# وضعیت ادامه — ۲۵ سپتامبر ۲۰۲۶، بررسی مجدد CI

این بخش بر گزارش‌های تاریخی پایین اولویت دارد. PR فعال: https://github.com/mobaraki20/SoknaCafe/pull/19 . انتشار سورس مجاز و انجام شده؛ نصب نهایی منتشر نشده است.

شاهد قطعی روی commit `637494574425ea177bea0251e9ecb2d19603b36f` در run https://github.com/mobaraki20/SoknaCafe/actions/runs/36115585860 :
- Linux regression gate: موفق کامل.
- Public Relay + MariaDB: موفق.
- Windows: build Runtime و Print Worker و آزمون واقعی setup runtime موفق؛ شامل repair/rollback، listener، حذف امن دو سرویس و حفظ داده و TLS.
- Inno lifecycle: شکست؛ فایل prerequisites.json در payload نبود. آزمون bundle نیز هنوز تعداد قدیمی سه فایل را انتظار داشت.
- full-stack / MSI: skipped، شاهد پذیرش نیستند.

commit `8b92d3233505aeebad882cb23eb01b761095fde2` payload پیش‌نیاز، Apache owner/template و Print Worker را اضافه و allowlist چهار فایل bundle را تصحیح کرد. run https://github.com/mobaraki20/SoknaCafe/actions/runs/36130372726 برای آن شروع شده؛ نتیجه باید خوانده شود. این اصلاح مسیر preview است، نه ادعای نصب کامل روی سیستم خام.

freeze پیش‌نیاز در run https://github.com/mobaraki20/SoknaCafe/actions/runs/36115585976 با redirect ناامن متوقف شد. HTTPS نباید غیرفعال شود و hashها نباید برای عبور تست تغییر کنند. پیام خطا اکنون شناسه provider را ثبت می‌کند؛ run جدید https://github.com/mobaraki20/SoknaCafe/actions/runs/36130372748 باید بررسی شود. release-lock هنوز تأیید نشده است.

رفع سازگاری جاری: Apache owner از IsPathFullyQualified استفاده می‌کرد که در .NET Framework/Windows PowerShell 5.1 موجود نیست. تشخیص مسیر کامل ویندوز جایگزین شده و مسیر drive-relative رد می‌شود؛ آزمون Windows لازم است.

موارد باز: انتخاب و ادغام مسیر واحد Setup.exe با Host/UI، نصب هدایت‌شده پیش‌نیازها و Apache، fixture معتبر lifecycle نصب، Repair هم‌نسخه و updater، پذیرش مالی مشترک اقامتگاه، Phase8C و UAT چاپگر/صندوق. PowerShell پشت صحنه مجاز است و کاربر نباید ps1 اجرا کند. شکست یا skipped به موفق تبدیل گزارش نشود.

---
# گزارش‌های تاریخی (وضعیت جاری نیستند)

# آخرین ادامه و شواهد اجرایی

شاخه منتشرشده: `work/reconcile-dev39`، PR https://github.com/mobaraki20/SoknaCafe/pull/19 (Draft؛ مقصد phase/8b-windows-setup).
سورس اولیه منتشرشده: `99e0f653efcd1e6a6eb8294b20b5c3e0527a0169`؛ run https://github.com/mobaraki20/SoknaCafe/actions/runs/36082275270.
MariaDB job موفق؛ Linux تا PHP lint 288، unit 105، accommodation v1 41 و tax v3 19 موفق، سپس قاعده متن ماژول رد شد. Windows ساخت Print Worker با CS0136 در Program.cs خطا داشت. در اصلاح بعد نام متغیر provisioning، متن ماژول و BOM دوبل PowerShell اصلاح شد؛ این اصلاحات هنوز نیاز به CI جدید دارند.

اتصال مالیات اکنون در کافه کدنویسی شده و ۱۹ آزمون wire/validation موفق است؛ پذیرش مشترک اقامتگاه و DB مالی هنوز باز است. اطلاعات «محلی/منتشرنشده» و «PHP NOT_RUN» در گزارش تاریخی پایین متعلق به نوبت قبل از انتشار است.

مسیر بعد: اجرای CI روی head اصلاحی، رفع خطاهای بعدی، یکپارچه‌سازی Inno+Host/UI+worker، freeze پیش‌نیاز واقعی، New/Repair/Recover/Uninstall و updater، سپس Phase8C و UAT. هیچ مرحله با شکست یا skipped کامل محسوب نشود.

---
## گزارش تاریخی مرحله اولیه

# تطبیق dev.39 با تاریخچه گیت‌هاب — ۲۵ سپتامبر ۲۰۲۶

وضعیت: **تطبیق اولیه محلی، قابل بازبینی؛ نصب‌کننده نهایی نیست.**
شاخه محلی: `work/reconcile-dev39`؛ مبنای تاریخچه: `6f01e76d791b054d162973288fc79122b45f733d`.
منبع پیشرفت‌ها: بسته dev.39 در `614b0a6454bfb6df61b6a8c9857559269ffc3be6`؛ snapshot اولیه آن `bc2e57c`.

## تصمیم جدید مالک
مالک به علت نبود دسترسی ویرایش گیت‌هاب، توسعه را در workspace ایجنت دیگری ادامه داده است. تاریخچه مستقل بسته علت رد پیشرفت‌ها نیست. هر دو سامانه کافه و اقامتگاه هنوز عملیاتی نشده‌اند؛ قرارداد اقامتگاه باید نیاز صحیح مالی کافه را بپذیرد و هندوور مستقل تغییرات داشته باشد. توضیح صریح و جدید مالک: PowerShell پشت صحنه مجاز است؛ کاربر نباید فایل ps1 را دستی اجرا کند. فقط Setup.exe و رابط نصب قابل‌اعتماد مشابه تجربه Pagent لازم است. .NET و PowerShell می‌توانند زیر همین رابط هماهنگ شوند. پذیرش WiX/EULA/هزینه اعلام نشده است. شرط بدون هزینه اجباری و تمام معیارهای WIN-01 تا WIN-12 برقرارند.

## کار انجام‌شده
- ZIP مستقیم باز شد؛ CRC همه ورودی‌ها سالم و هر ۱۲۲۸ هش فهرست اصلی مطابق است.
- به جای جایگزینی snapshot، تفاوت snapshot اولیه بسته تا dev.39 با ادغام سه‌طرفه روی تاریخچه واقعی گیت‌هاب اعمال شد.
- ۷ تعارض رفع شد؛ اصلاح Unicode OpenSSL و WorkingDirectory گیت‌هاب باقی ماند.
- پارامتر ValidateRepair و RemovePlatform گیت‌هاب همراه با preflight و Print Worker داخلی dev.39 حفظ شدند.
- snapshot لاگ باز نصب‌کننده و گزارش خطای تشخیص گیت‌هاب با components.json و جمع‌آوری محدود dev.39 تلفیق شدند.
- متغیر محلی $pid به $listenerProcessId تغییر کرد؛ فیلد خروجی pid عوض نشده است. تست با listener واقعی به harness ویندوز اضافه شد، اما این تست در این محیط اجرا نشده است.
- پیشرفت‌های خرید گروهی، هزینه، مالیات، طراحی فارسی و چاپ داخلی منتقل شدند.

## شاهد آزمون
۱۵ فایل تست Python شامل ۱۱ قرارداد Phase8B و قراردادهای چاپ داخلی، مالیات، خرید گروهی و هزینه اجرا و موفق شدند. این‌ها عمدتاً تست ایستا هستند. PHP، PowerShell، .NET و Windows در این محیط موجود نیست؛ اجرای Windows، lint/unit PHP و DB جدید NOT_RUN است. نتایج تاریخی CI به این شاخه نسبت داده نشوند.

## نتیجه قطعی بررسی نصب‌کننده
`installer/windows/setup-host/Program.cs` در RunPlan مستقیماً Windows PowerShell را برای deploy-seed.ps1 یا setup-sokna.ps1 اجرا می‌کند. UI نیز verifier پیش‌نیاز را با PowerShell اجرا می‌کند. این معماری با توضیح جدید مالک سازگار است؛ وجود PowerShell پشت صحنه نقص پذیرش نیست. معیار، عدم نیاز به اجرای دستی اسکریپت و تکمیل قابل‌اعتماد عملیات از رابط نصب است.

Inno مبنای تصمیم بدون هزینه باقی می‌ماند. سورس WiX بسته فعلاً جهت حفظ کار و بازبینی موجود است؛ مجوز اجرا یا پذیرش EULA ثبت نشده است. پیش از build نهایی، تنها یک مسیر محصول انتخاب و UI/Host/seed/internal print با آن کامل یکپارچه شود. بازنویسی orchestration صرفاً برای حذف PowerShell لازم نیست. Setup.exe باید lifecycle و پیش‌نیازها را هدایت کند؛ درخواست اجرای دستی ps1، تغییر ExecutionPolicy توسط کاربر یا سرهم‌کردن بسته‌ها قابل قبول نیست.

## موانع باقی‌مانده
- دو مسیر بسته‌بندی Inno preview و WiX source هنوز موجودند. workflow ترکیبی هنوز برای Windows تأیید نشده؛ Inno preview قدیمی payload کامل Print Worker جدید را حمل نمی‌کند و نباید محصول نهایی یا مسیر CI آماده نامیده شود.
- RemovePlatform فعلاً حذف Runtime قدیمی را حفظ می‌کند؛ حذف امن سرویس داخلی Print Worker و مالکیت آن برای uninstall کامل باید افزوده و آزموده شود.
- Repair واقعی فایل برنامه با cache هم‌نسخه و updater واقعی همچنان باز است؛ Repair سرویس‌ها معادل تعمیر کامل برنامه نیست.
- پیش‌نیازهای نصب روی رایانه خام و reload Apache هنوز راهکار عملی کامل ندارند. release-lock واقعی موجود نیست و ساخته نشده است.
- اتصال اقامتگاه هنوز v1-only و دارای مانع مالیات است؛ هندوور مستقل قرارداد در HOUSE_TAX_SNAPSHOT_HANDOFF_FA.md آماده شده؛ تغییر اجرایی انتقال مالیات هنوز انجام نشده است.
- چاپگر، صندوق، reboot/network UAT و Phase8C هنوز بازند.

## ادامه دقیق
۱. نهایی‌کردن یک مسیر بسته‌بندی با شرط بدون هزینه و تمام معیارهای پذیرش؛ حفظ lifecycleهای موجود تا جایگزین آزموده شود.
۲. پیاده‌سازی snapshot مالیاتی اقامتگاه و negotiation مطابق هندوور، همراه آزمون wire/DB/timeout/retry/void.
۳. ساخت Windows و freeze واقعی پیش‌نیازها؛ تست New/Repair/Recover/Uninstall/Updater و هویت دستگاه.
۴. سپس CI روی head نهایی و پس از merge و UAT.

این شاخه محلی است. انتشار سند در گیت‌هاب در نوبت قبل توسط بازبینی خودکار مجوز رد شد؛ در این مرحله هیچ push یا workaround انتشار انجام نشده است.
