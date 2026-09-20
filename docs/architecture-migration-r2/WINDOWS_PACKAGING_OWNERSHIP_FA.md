# طرح مالکیت و بسته‌بندی Windows — Phase 8B

تاریخ بررسی: 2026-09-20. وضعیت: **طرح اجرایی؛ هنوز پیاده‌سازی یا تأیید نصب‌کننده نیست**.
کد بررسی‌شده: `2fdb500d3240a1c2adde30b291fe4377d78fca44`، شاخه `phase/8b-windows-setup`، PR #18.
قرارداد پذیرش: `WINDOWS_INSTALLER_ACCEPTANCE_FA.md`.

## نتیجه بررسی کد

`includes/updater_engine/1.5.3/runtime.php` فایل‌های برنامه را در root فعال copy/replace می‌کند. `tools/build-release.php` سازنده canonical بسته update است. MSI نباید همین فایل‌های فعال را Component خود بداند: تصمیم Windows Installer درباره فایل‌های بدون نسخه، مانند PHP، متکی به metadata فایل است و تضمین حفظ نسخه فعال برنامه نیست. تغییر REINSTALLMODE راه‌حل مرز مالکیت نیست.

در کد فعلی، `setup-sokna.ps1` وجود AppRoot و PHP را فرض می‌کند. سرویس `SoknaRuntime` فقط supervisor کارهای پس‌زمینه است؛ وب‌سرور نیست. فایل Apache موجود صرفاً template است. بنابراین «سرویس RUNNING» یا ساخت service-host.exe به معنی وب‌اپ قابل استفاده روی Windows خالی نیست.

## مرز مالکیت برای پیاده‌سازی

| بخش | مالک واحد | قاعده Repair / Uninstall |
|---|---|---|
| ابزار نصب، launcher، فایل‌های مدیریت Windows و نسخه آماده service host | بسته platform در Program Files | MSI فقط این فایل‌ها و shortcut/registration متعلق به خودش را تعمیر یا حذف می‌کند |
| فایل‌های فعال PHP/JS/CSS و نسخه برنامه | updater موجود، پس از نصب اولیه | هیچ MSI Component یا RemoveFile بازگشتی روی AppRoot فعال تعریف نشود |
| بسته اولیه برنامه در cache جدا از AppRoot | بسته platform، به صورت archive تغییرناپذیر | Repair می‌تواند archive را تعمیر کند؛ حق استخراج خودکار آن روی AppRoot موجود را ندارد |
| config.php، install.lock، app.key، identity، رسانه و backup | setup/maintenance/identity ownerهای موجود | حفظ در Repair و uninstall عادی؛ ورود به File table یا archive اولیه ممنوع |
| سرویس Runtime و تنظیمات آن | `setup-sokna.ps1` + میزبان C# موجود | orchestration از همین owner؛ ServiceInstall موازی که همان سرویس را مدیریت کند ساخته نشود |
| TLS، CA و hosts mapping | `provision-local-https.ps1` | identity سالم حفظ شود؛ uninstall فقط با شاهد مالکیت، نه حذف سراسری CA/hosts |
| Print Agent | installer رسمی Pagent | فقط delegate؛ سرویس یا state machine دوم ساخته نشود؛ وابستگی مشترک بدون تشخیص مالکیت حذف نشود |

مسیر نهایی active app باید خارج از Program Files، خارج از data/secrets و خارج از document root داده خصوصی باشد؛ مسیر دقیق و ACL وب‌سرور هنگام انتخاب web stack تثبیت می‌شود. DataRoot نصب موجود با این سند جابه‌جا نمی‌شود. مسیرهای ثبت‌شده باید توسط کاربر عادی غیرقابل تغییر باشند تا Repair elevated از مسیر دلخواه کد اجرا نکند.

وجود نسخه‌هایی از source ابزار Windows داخل یک update، به آن فایل‌ها مالکیت platform نمی‌دهد. نسخه اجرایی ابزار platform باید همان نسخه ثبت‌شده بسته platform باشد. این تفکیک باید در builder و invocation واقعی enforce شود، نه صرفاً در مستندات.

## رفتار نصب و تعمیر

1. preflight و اعتبارسنجی payload قبل از تغییر دستگاه؛ ثبت session و مسیر diagnostics از همان ابتدا.
2. نصب اولیه: مقصد برنامه واقعاً خالی، بررسی manifest/hash و استخراج به staging خصوصی؛ سپس فراخوانی setup owner موجود. Recover فقط با نسخه دقیق recovery set و identity تازه از مسیر موجود انجام شود.
3. پس از application commit، failure مراحل Windows با همان `business_setup_committed` گزارش شود؛ Retry نباید New/Recover را کورکورانه دوباره اجرا کند.
4. Repair platform فایل‌های platform و shortcut را بازیابی کرده و همان Repair موجود را فراخوانی می‌کند؛ Restore دیتابیس و استخراج seed ممنوع است.
5. اگر فایل‌های active app آسیب دیده‌اند، بازیابی فقط از payload کامل **همان نسخه فعال** و با owner updater انجام شود. builder کنونی بسته delta تولید می‌کند؛ وجود cache کامل هم‌نسخه هنوز پیاده‌سازی نشده است. تا پیاده‌سازی، خطای روشن و حفظ فایل‌ها لازم است؛ ادعای Repair کامل برنامه مجاز نیست.
6. نسخه platform و application جدا ثبت شوند. شماره MSI به جای `VERSION.txt` برنامه یا شاهد سازگاری recovery استفاده نشود. نبود manifest/cache نسخه فعال نباید به downgrade یا انتخاب «آخرین نسخه اینترنت» منجر شود.
7. uninstall عادی سرویس/shortcut/ثبت محصول تحت مالکیت نصب را جمع کند و داده/backup/key را نگه دارد. حفظ داده نباید به باقی‌ماندن سرویس فعال با executable حذف‌شده منجر شود.

## پیش‌نیازهای هنوز باز

| مورد | شاهد موجود | کار لازم برای بسته نهایی |
|---|---|---|
| PHP | بررسی 8.2+ و pdo_mysql/fileinfo/openssl/sodium/mbstring | نسخه دقیق پشتیبانی‌شده، معماری، توزیع معتبر، runtime موردنیاز، مجوز و hash؛ بررسی extensionهای کل محصول از جمله archive/backup |
| DB | preflight اتصال و empty-target روی MariaDB در CI | انتخاب نسخه پشتیبانی‌شده؛ نصب/تشخیص سرویس و account اختصاصی، backup/rollback و حفاظت password |
| HTTPS/web server | template Apache، تست TLS مستقل | توزیع و نسخه، ماژول‌ها، PHP integration، سرویس واقعی، پورت 443، ACL و HTTP+DB health |
| OpenSSL | آزمون اجرای واقعی و مسیر فارسی | توزیع معتبر همراه مجوز/وابستگی‌ها؛ وجود Git در رایانه صندوق فرض نشود |
| C# host | build در CI و تست SCM | پیش‌نیاز .NET Framework و OS/architecture در manifest؛ compiler فقط build-time |
| Print Agent اختیاری | نام asset نسخه‌دار و SHA256 اجباری | نسخه رسمی و trusted manifest، detect/repair/uninstall/3010، تست با چاپگر واقعی |

manifest نهایی باید برای هر payload نسخه، معماری، URL ثابت HTTPS، اندازه، hash، مبنای اعتماد، license، تشخیص نسخه نصب‌شده و exit codeهای معتبر داشته باشد. hash از فایل کنار دانلود غیرقابل اعتماد، به‌تنهایی اصالت ناشر را ثابت نمی‌کند. دانلود ناقص/قطع اینترنت/نسخه نامتناسب باید پیش از mutation قابل تشخیص باشد؛ offline payload از همان validation عبور کند.

## ابزار ساخت و تصمیم لازم از مالک

پیشنهاد فنی همچنان MSI + Burn است؛ اما «WiX رایگان بدون قید» فرض درستی برای نسخه فعلی نیست. Release notes رسمی، WiX 7.0.0 منتشرشده در 2026-04-06 و الزام explicit EULA acceptance را ثبت کرده است. مستند OSMF و تغییرات v7 به شرط درآمدی 10,000 دلار در سال اشاره می‌کنند؛ مبنای دقیق محاسبه را باید از متن EULA مرتبط بررسی کرد، نه از درآمد نامعلوم مالک حدس زد.

**هنوز EULA پذیرفته نشده، اشتراک/پرداخت ایجاد نشده و AcceptEula وارد CI نشده است.** پیش از تثبیت toolchain، مالک مشخص کند: ادامه با WiX و بررسی/پذیرش شرایط و هزینه احتمالی آن، یا انتخاب ابزار بدون هزینه اجباری. این سؤال درباره مجوز ساخت است، نه اجازه توسعه عادی پروژه.

برای حذف این سؤال از نسخه قدیمی و بدون ارزیابی امنیت/پشتیبانی استفاده نشود. Inno Setup قابلیت shortcut/uninstall/logging دارد ولی Repair موردنیاز باید در آن طراحی شود؛ شرایط تجاری نسخه منتخب آن هم جدا بررسی شود. تا پاسخ مالک، source owners و طرح مستقل از ابزار قابل تکمیل‌اند؛ ساخت نهایی با WiX و پذیرش شرایط به جای مالک انجام نشود.

## شواهد پذیرش بعدی

- Windows تمیز و حساب عادی، مسیر فارسی/فاصله، reboot، launcher بدون elevation.
- حذف shortcut و platform file و Repair واقعی از Installed apps.
- update برنامه از A به B، سپس Repair با installer A: hash/version برنامه B و DB/key/identity بدون تغییر.
- cache هم‌نسخه مفقود و فایل active app خراب: شکست صریح و بدون downgrade.
- خرابی وب‌سرور/DB بعد از RUNNING سرویس: نتیجه نصب failure؛ بسته پشتیبانی یکپارچه با session مشترک.
- uninstall با داده sentinel و وابستگی shared؛ حفظ داده و جمع‌شدن سرویس تحت مالکیت.
- امضای انتشار یا برچسب روشن unsigned آزمایشی؛ هیچ artifact آزمایشی به‌عنوان Setup نهایی معرفی نشود.

## منابع رسمی

- Microsoft، قواعد فایل بدون نسخه: https://learn.microsoft.com/en-us/windows/win32/msi/file-versioning-rules
- WiX، Burn و package chain: https://docs.firegiant.com/wix/tools/burn/
- WiX، Release notes: https://docs.firegiant.com/wix/whatsnew/releasenotes/
- WiX، OSMF و explicit EULA: https://docs.firegiant.com/wix/osmf/
- Inno Setup، قابلیت‌ها و لینک شرایط استفاده: https://jrsoftware.org/isinfo.php
