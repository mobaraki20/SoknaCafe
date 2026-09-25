> **مرجع فعلی تطبیق — 2026-09-25:** شاخه منتشرشده `work/reconcile-dev39` در PR #19، نسخه سورس dev.39، مبتنی بر تاریخچه گیت‌هاب و پیشرفت‌های بسته. ابتدا `docs/handoffs/DEV39_RECONCILIATION_2026-09-25_FA.md` را بخوانید (مسیر نسبت به ریشه مخزن). ادامه‌ها و دستورهای متعارض زیر سوابق تاریخی‌اند؛ Phase8B کامل نشده، WiX مجوز اجرا نگرفته و شواهد CI جدید و کارهای باز در مرجع فوق و جدول پذیرش 2026-09-25 ثبت شده‌اند.

# قرارداد نصب‌کننده ویندوز SOKNA

تاریخ: 2026-09-19 | وضعیت: الزامات ثبت‌شده؛ بسته نهایی هنوز ساخته/تأیید نشده است.
مبنای بررسی کد: main `98607d87d50c7913a1143d621e60f807965bae53` و شاخه 8B در `6a3e183ca0ea544046c09bf3b9c8de7ab038ca5b`.

## درخواست صریح مالک
نصب قابل‌اعتماد، آیکن دسکتاپ و Start، نمایش در Installed apps، حذف از همان‌جا، Repair، شناسایی پیش‌نیازها پیش از نصب و اعلام روشن یا دریافت/نصب خودکار آنها، خطای واضح و لاگ متمرکز برای تشخیص بدون جمع‌آوری دستی از منابع پراکنده. این موارد شرط پذیرش بسته نهایی هستند، نه ادعای قابلیت فعلی.

## تصمیم فنی پیشنهادی برای بسته‌بندی
**تصمیم به‌روز 2026-09-20:** مالک هزینه را رد کرده و استفاده فعلی را آزمایشی اعلام کرده است. مسیر منتخب **Inno Setup / Setup.exe** است؛ پیشنهاد اولیه WiX MSI + Burn با این تصمیم جایگزین شد. مجوز رسمی و FAQ خرید Inno بررسی شد: خرید اجباری نیست. مرجع تصمیم و مرز مالکیت: `WINDOWS_PACKAGING_OWNERSHIP_FA.md`.

Inno مالک فایل‌های platform، shortcut و ثبت/uninstall می‌شود و orchestration موجود را مصرف می‌کند. Repair باید صریحاً پیاده‌سازی و آزموده شود؛ قابلیت خودکار MSI نیست. همه معیارهای WIN-01 تا WIN-12 پابرجا هستند.

برای نیاز فعلی SOKNA، مسیر منتخب طراحی **WiX MSI + Burn Setup.exe** است: MSI مالک لایه پایدار نصب، shortcut، ثبت محصول و چرخه repair/uninstall باشد؛ Burn prerequisiteهای نسخه‌دار و MSI خود SOKNA را هماهنگ کند. **Print Worker داخل خود SOKNA Local bundle می‌شود و Pagent مستقل در Chain ممنوع است.** Live application payload تحت مالکیت Updater است و MSI Repair نباید آن را harvest/overwrite کند؛ مرجع جزئیات `PHASE8B_PACKAGE_OWNERSHIP_FA.md` است.

Pin انتخاب‌شده برای authoring جدید: **WiX Toolset 7.0.0**. Build pipeline نباید پذیرش EULA/OSMF ابزار را به‌صورت پنهانی داخل سورس انجام دهد؛ پذیرش لازم باید در محیط Build/Release به‌صورت صریح انجام شود. این تصمیم جایگزین ownerهای setup، backup یا updater نیست.

مقایسه مهندسی:
- MSI + Burn: سازوکار استاندارد Windows Installer برای repair/uninstall و ترکیب چند بسته؛ نیازمند طراحی دقیق مالکیت فایل و rollback.
- Inno Setup: نصب و uninstall مناسب دارد؛ repair هم‌سطح نیاز پروژه باید جداگانه طراحی و آزموده شود. صرف نام‌گذاری مجدد نصب به Repair قابل‌قبول نیست.
- MSIX: محدودیت‌های بسته‌بندی، تغییرات سیستم و تعامل با updater فعلی باید بررسی شوند؛ برای معماری موجود انتخاب اول نیست. ادعای «MSIX هیچ سرویسی پشتیبانی نمی‌کند» نادرست است.
- PowerShell فعلی: owner هماهنگی قابل استفاده است، اما به‌تنهایی محصول نصب‌شونده با ARP/shortcut/repair کامل نیست.

این انتخاب یک نتیجه مهندسی برای این پروژه است؛ هیچ ابزار بسته‌بندی به‌تنهایی قابل‌اعتماد بودن را تضمین نمی‌کند.

## مرز مالکیت و جلوگیری از تداخل
- `includes/setup_install.php` و `tools/setup-machine.php` در شاخه 8B مالک نصب اولیه و orchestration برنامه هستند؛ `includes/maintenance.php` مالک restore است.
- `runtime/windows/setup-sokna.ps1` هماهنگ‌کننده Windows و `SoknaRuntimeService.cs` میزبان واقعی SCM هستند. هنداور قدیمی 8B که از WinSW می‌گوید با کد فعلی همخوان نیست؛ فعلاً wrapper دوم اضافه نشود.
- Print Worker همراه SOKNA Local build/install/repair می‌شود؛ Installer مستقل Pagent مجاز نیست. lifecycle/state-machine بالغ 6.2.5 بازنویسی نمی‌شود و فقط ownership/packaging به SOKNA Local منتقل می‌شود.
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

## وضعیت بازبینی‌شده — 2026-09-20
- source `2fdb500d3240a1c2adde30b291fe4377d78fca44` در CI `35477813459` هر سه gate را گذرانده است؛ این شاهد مربوط به hardening شاخه است، نه نصب‌کننده نهایی.
- کامپایل روی دستگاه مقصد حذف شده؛ host در CI ساخته می‌شود. حفظ سرویس و rollback، ACL قبل از نوشتن اسرار، SHA256 اجباری Agent، diagnostics و TLS preservation پیاده‌سازی و آزموده شده‌اند.
- هنوز MSI/Burn، shortcut/Installed apps، prerequisite acquisition جامع، web server/DB deployment و HTTP health نهایی ساخته نشده‌اند.
- طرح جلوگیری از تداخل updater/Repair، مرز مالکیت و شکاف‌های هر پیش‌نیاز در `WINDOWS_PACKAGING_OWNERSHIP_FA.md` ثبت شده‌اند؛ این سند طرح است، نه ادعای اجرای آن.
- تصمیم هزینه حل شد: Inno Setup بدون خرید اجباری؛ WiX از مسیر اجرا کنار گذاشته شد. هیچ پرداختی انجام نشده است.
- CI سبز orchestration به معنی تأیید نصب‌کننده نهایی نیست.

## ترتیب ادامه
1. ادامه همان شاخه 8B و بستن شکاف‌های orchestration/امنیت فایل/diagnostics.
2. تثبیت مرز updater/MSI و manifest پیش‌نیازها؛ سپس authoring و build Windows برای Inno Setup و Repair صریح.
3. آزمون جدول بالا و ثبت artifact/run/SHA، سپس PR و CI روی final head و دوباره پس از merge.
4. گزارش جداگانه موارد نیازمند UAT رایانه صندوق/چاپگر؛ تکمیل takeover در 8C طبق قرارداد اصلی.

## منابع رسمی بررسی‌شده
- Microsoft Windows Installer: repair، uninstall و logging: https://learn.microsoft.com/en-us/windows/win32/msi/command-line-options
- WiX Burn و زنجیره بسته‌ها: https://docs.firegiant.com/wix/tools/burn/
- WiX ExePackage و قرارداد بسته‌های EXE: https://docs.firegiant.com/wix/schema/wxs/exepackage/
- Inno Setup uninstall: https://jrsoftware.org/ishelp/topic_setup_uninstallable.htm
- محدودیت‌ها و الزامات MSIX: https://learn.microsoft.com/en-us/windows/msix/desktop/desktop-to-uwp-prepare
