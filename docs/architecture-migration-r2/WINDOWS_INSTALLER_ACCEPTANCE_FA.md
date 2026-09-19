# قرارداد نصب‌کننده ویندوز SOKNA

تاریخ: 2026-09-19 | وضعیت: الزامات ثبت‌شده؛ بسته نهایی هنوز ساخته/تأیید نشده است.
مبنای بررسی کد: main `98607d87d50c7913a1143d621e60f807965bae53` و شاخه 8B در `6a3e183ca0ea544046c09bf3b9c8de7ab038ca5b`.

## درخواست صریح مالک
نصب قابل‌اعتماد، آیکن دسکتاپ و Start، نمایش در Installed apps، حذف از همان‌جا، Repair، شناسایی پیش‌نیازها پیش از نصب و اعلام روشن یا دریافت/نصب خودکار آنها، خطای واضح و لاگ متمرکز برای تشخیص بدون جمع‌آوری دستی از منابع پراکنده. این موارد شرط پذیرش بسته نهایی هستند، نه ادعای قابلیت فعلی.

## تصمیم فنی پیشنهادی برای بسته‌بندی
برای نیاز فعلی SOKNA، مسیر منتخب طراحی **WiX MSI + Burn Setup.exe** است: MSI مالک نصب فایل‌ها، shortcut، ثبت محصول و چرخه repair/uninstall باشد؛ Burn زنجیره پیش‌نیازها و بسته رسمی Print Agent را هماهنگ کند. نسخه پشتیبانی‌شده ابزار و extensionها در زمان پیاده‌سازی pin و شرایط توزیع/مجوز آن ثبت شود. این تصمیم جایگزین ownerهای setup، backup یا updater نیست.

مقایسه مهندسی:
- MSI + Burn: سازوکار استاندارد Windows Installer برای repair/uninstall و ترکیب چند بسته؛ نیازمند طراحی دقیق مالکیت فایل و rollback.
- Inno Setup: نصب و uninstall مناسب دارد؛ repair هم‌سطح نیاز پروژه باید جداگانه طراحی و آزموده شود. صرف نام‌گذاری مجدد نصب به Repair قابل‌قبول نیست.
- MSIX: محدودیت‌های بسته‌بندی، تغییرات سیستم و تعامل با updater فعلی باید بررسی شوند؛ برای معماری موجود انتخاب اول نیست. ادعای «MSIX هیچ سرویسی پشتیبانی نمی‌کند» نادرست است.
- PowerShell فعلی: owner هماهنگی قابل استفاده است، اما به‌تنهایی محصول نصب‌شونده با ARP/shortcut/repair کامل نیست.

این انتخاب یک نتیجه مهندسی برای این پروژه است؛ هیچ ابزار بسته‌بندی به‌تنهایی قابل‌اعتماد بودن را تضمین نمی‌کند.

## مرز مالکیت و جلوگیری از تداخل
- `includes/setup_install.php` و `tools/setup-machine.php` در شاخه 8B مالک نصب اولیه و orchestration برنامه هستند؛ `includes/maintenance.php` مالک restore است.
- `runtime/windows/setup-sokna.ps1` هماهنگ‌کننده Windows و `SoknaRuntimeService.cs` میزبان واقعی SCM هستند. هنداور قدیمی 8B که از WinSW می‌گوید با کد فعلی همخوان نیست؛ فعلاً wrapper دوم اضافه نشود.
- installer رسمی `mobaraki20/Pagent` مالک Print Agent می‌ماند؛ lifecycle چاپ بازنویسی نشود.
- پیش از authoring MSI، تکلیف فایل‌هایی که updater فعلی تغییر می‌دهد مشخص و مستند شود: MSI repair نباید نسخه قدیمی را روی payload جدید updater بازنویسی کند. manifest نسخه فعال، package cache و upgrade/repair باید هم‌نسخه بمانند. برای حل این تعارض owner موازی ساخته نشود.
- binary سرویس در CI ساخته و همراه بسته توزیع شود؛ نیاز به کامپایلر C# روی رایانه صندوق از مسیر نهایی حذف شود.
- فایل برنامه و داده mutable جدا؛ data root فعلی ProgramData حفظ و دسترسی اسرار قبل از نوشتن محدود شود.

## معیارهای پذیرش اجباری
| شناسه | الزام | شاهد پذیرش |
|---|---|---|
| WIN-01 | Setup.exe نسخه‌دار و قابل رهگیری به commit؛ checksum و payload manifest | نصب روی Windows تمیز و artifact واقعی CI؛ امضای انتشار و timestamp یا اعلام صریح unsigned بودن نسخه آزمایشی |
| WIN-02 | آیکن برند در Desktop و Start برای بازکردن URL صحیح Local | تست حساب عادی، مسیر دارای فاصله/فارسی و پس از reboot؛ بدون اجرای مرورگر با دسترسی Admin |
| WIN-03 | ثبت نام/نسخه/ناشر/آیکن در Installed apps و Programs and Features | حذف از رابط Windows؛ ثبت درست وضعیت/خطای حذف |
| WIN-04 | Repair واقعی | حذف عمدی فایل/shortcut و خرابی سرویس، سپس تعمیر و health check؛ داده تجاری، app.key، identity و تنظیمات سالم تغییر نکنند |
| WIN-05 | preflight پیش از mutation | بررسی OS/architecture، Admin، disk، نسخه/extensionهای PHP، DB، web server، OpenSSL، runtimeهای موردنیاز، پورت، نصب قبلی و pending reboot |
| WIN-06 | پیش‌نیازها و اینترنت ناپایدار | تشخیص نسخه نصب‌شده؛ payload همراه یا دانلود HTTPS نسخه‌دار با hash/signature معتبر؛ خطای قابل‌فهم، retry و مسیر offline؛ shared dependency بدون دلیل حذف نشود |
| WIN-07 | نصب ناقص و rollback | fail injection در مراحل؛ rollback فقط منابع تحت مالکیت همان نصب؛ موفقیت تا پایان health check اعلام نشود؛ reboot-required جدا از failure |
| WIN-08 | لاگ یکپارچه | session ID مشترک، خلاصه فارسی، stage/component/error code/exit code/version/timestamp و اقدام بعدی؛ لاگ از شروع preflight و fallback قبل از دسترسی ProgramData |
| WIN-09 | بسته تشخیص یک‌مرحله‌ای | ZIP شامل summary.json، لاگ MSI/Burn/orchestrator و وضعیت سرویس/نسخه/health؛ اسرار، رمز، token، app.key، private key و محتوای backup حذف شوند |
| WIN-10 | حفظ اطلاعات در uninstall | دیتابیس، backup، media و کلیدها پیش‌فرض باقی بمانند؛ shared DB/Agent/CA متعلق به نصب دیگر حذف نشوند؛ پاک‌سازی داده اقدام جدا و صریح است |
| WIN-11 | upgrade و repair بعد از update | upgrade موفق/ناموفق، cache مفقود و repair پس از updater آزموده شود؛ downgrade ناخواسته ممنوع |
| WIN-12 | Recover | اجرای owner موجود روی مقصد خالی، نسخه سازگار، identity جدید و عدم کپی private identity قدیمی؛ takeover فاز 8C جداست |

Repair با Recover فرق دارد: Repair فایل/سرویس نصب را اصلاح می‌کند؛ Recover اطلاعات را از backup بازیابی می‌کند و نباید خودکار هنگام Repair اجرا شود.

## شکاف‌های مشاهده‌شده در شاخه 8B
- حالت‌های New/Recover/Repair/Validate و service host وجود دارند، اما MSI/Burn project و shortcut/Installed apps authoring در diff بررسی‌شده نیست.
- service host در دستگاه مقصد compile می‌شود؛ نسخه نهایی باید artifact آماده داشته باشد.
- preflight جامع قبل از mutation، دانلود اعتبارسنجی‌شده پیش‌نیازها و support bundle یکپارچه هنوز اثبات نشده‌اند.
- تشخیص Print Agent صرفاً از نام فایل، تأیید اصالت نیست؛ hash/signature باید اضافه شود.
- Repair فعلی stop/delete/create سرویس دارد؛ حفظ تنظیمات و rollback خرابی میانی باید آزموده شود. sleep ثابت جای انتظار state با timeout را نمی‌گیرد.
- پوشه موقت اسرار باید ACL محدود را پیش از Copy اعمال کند؛ private نامیدن فایل کافی نیست.
- CI سبز orchestration به معنی تأیید نصب‌کننده نهایی نیست.

## ترتیب ادامه
1. ادامه همان شاخه 8B و بستن شکاف‌های orchestration/امنیت فایل/diagnostics.
2. تثبیت مرز updater/MSI و manifest پیش‌نیازها؛ سپس authoring و build Windows برای MSI + Burn.
3. آزمون جدول بالا و ثبت artifact/run/SHA، سپس PR و CI روی final head و دوباره پس از merge.
4. گزارش جداگانه موارد نیازمند UAT رایانه صندوق/چاپگر؛ تکمیل takeover در 8C طبق قرارداد اصلی.

## منابع رسمی بررسی‌شده
- Microsoft Windows Installer: repair، uninstall و logging: https://learn.microsoft.com/en-us/windows/win32/msi/command-line-options
- WiX Burn و زنجیره بسته‌ها: https://docs.firegiant.com/wix/tools/burn/
- WiX ExePackage و قرارداد بسته‌های EXE: https://docs.firegiant.com/wix/schema/wxs/exepackage/
- Inno Setup uninstall: https://jrsoftware.org/ishelp/topic_setup_uninstallable.htm
- محدودیت‌ها و الزامات MSIX: https://learn.microsoft.com/en-us/windows/msix/desktop/desktop-to-uwp-prepare
