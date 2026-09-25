# وضعیت پذیرش نصب ویندوز — ۲۵ سپتامبر ۲۰۲۶

این checkpoint جایگزین وضعیت روز قبل است؛ معیارهای اصلی پذیرش تغییر نکرده‌اند. سورس در PR #19 منتشر شده، اما Setup.exe نهایی برای استفاده عملیاتی منتشر نشده است.

PR: https://github.com/mobaraki20/SoknaCafe/pull/19

| معیار | وضعیت مبتنی بر اجرا | کار باقی‌مانده |
|---|---|---|
| WIN-01 فایل Setup.exe، نسخه و هش | Preview ساخته شده؛ محصول نهایی باز | یکپارچه‌سازی Inno با Host/UI و خروجی نهایی قابل نصب |
| WIN-02 میانبرها | پذیرش بسته باز | Desktop و Start در نصب/تعمیر واقعی |
| WIN-03 Installed apps و حذف | پذیرش بسته باز | ARP و Uninstall نصب‌کننده واحد |
| WIN-04 Repair | آزمون Runtime/Print Worker موفق | تعمیر فایل برنامه هم‌نسخه و تجربه UI |
| WIN-05 Preflight | آزمون setup runtime موفق | پیش‌نیاز و DB در نصب کامل ویندوز |
| WIN-06 پیش‌نیاز آفلاین | Freeze و ساخت/verify بسته واقعی موفق | تجربه نصب هدایت‌شده کاربر |
| WIN-07 rollback | Runtime/Print Worker موفق؛ Apache در اصلاح | آزمون خطا بعد از تغییر Apache، شکست بسته و reboot |
| WIN-08 logging | آزمون گزارش مالک setup موفق | اثبات snapshot لاگ در lifecycle بسته |
| WIN-09 support.zip | آزمون allowlist و redaction موفق | میانبر نهایی و آزمون بسته |
| WIN-10 حفظ داده هنگام حذف | حذف دو سرویس با کنترل مالکیت و حفظ کلید/config موفق | حذف از Installed apps با بسته نهایی |
| WIN-11 Update سپس Repair | باز | Updater واقعی و از دست رفتن cache |
| WIN-12 Recover | full-stack فعال؛ هنوز شکست دارد | موفقیت New، backup، Recover و هویت جدید |

## شواهد
- Run `36131569378` روی `c5cacfd5cf03abd93ff614bc55993e45fcda99fa`: Linux regression و Public Relay/MariaDB موفق. Windows build، آزمون setup runtime، self-check و TLS موفق؛ Apache fixture به‌علت خواندن UTF-8 بدون تعیین encoding شکست خورد. Full-stack از build Host/UI و آماده‌سازی shell عبور کرد ولی با timeout متوقف شد. علت قطعی timeout هنوز تعیین نشده است.
- Freeze run `36130815720` و artifact `10861238902`: چهار hash/size با candidate تطبیق داشت و Authenticode مربوط به Microsoft معتبر بود. lock و شاهد بازبینی در مخزن ثبت شده‌اند.
- Run `36165915622` روی `da1e4d7fda00e9040cef40afc932b12e3552e589`: Linux regression و Public Relay/MariaDB موفق شدند. Windows Phase 1 پس از عبور از encoding/CRLF، در مقایسه متنی AppRoot شکست خورد. Full-stack به‌طور قطعی در مرحله `mariadb-install` پس از ۶۰۰ ثانیه timeout شد؛ artifact `10877474564` فقط `failure.json` پاک‌سازی‌شده داشت و لاگ MSI در دسترس artifact قرار نگرفت.
- اصلاح همراه این checkpoint: fixture مسیرهای Apache را پس از استخراج `DocumentRoot` و `Directory` به‌صورت معنایی مقایسه می‌کند؛ harness از مسیر کوتاه اختصاصی روی درایو سیستم، password امن بدون نویسه shell، log MSI با flush، runner مستقل MSI و cleanup صریح `/x` استفاده می‌کند. این موارد تا اجرای موفق CI، شاهد PASS محسوب نمی‌شوند.
- Run `36195932654` روی `2f540954baed5e107f2903d562d29c69ea41a1bd`: Linux regression، Public Relay/MariaDB و Apache integration runtime موفق شدند. preview Inno فقط به‌دلیل ورود ناخواسته به preflight واقعی Apache شکست خورد و full-stack دوباره در `mariadb-install` timeout شد. نصب ۹۰۰ ثانیه و cleanup ۳۰۰ ثانیه دقیقاً تا سقف ماندند و artifact `10891060012` باز هم log MSI نداشت؛ بنابراین MSI اصلاً transaction/log را آغاز نکرده بود.
- علت command line: runner همه tokenها، شامل `/i` و `/qn`، را quote می‌کرد. اصلاح بعدی switchهای MSI را بدون quote و فقط path/valueهای کنترل‌شده را quote می‌کند. preview platform نیز صریحاً `SkipHttps` می‌گیرد؛ HTTPS واقعی همچنان در Apache runtime و RC full-stack اجباری است. این اصلاح‌ها تا CI موفق، PASS نیستند.
- Run `36198103588` روی `81efa8ffabf08b013945634cb5151c39f27373c2`: اصلاح MSI تأیید شد؛ MariaDB 11.4.12 در ۴۱ ثانیه نصب شد، service آماده شد و full-stack وارد `new` شد. شکست بعدی `Apache main configuration file was not found` بود، چون Apache Lounge پس از relocation هنوز compiled root یعنی `C:\Apache24` را در `-V` گزارش می‌کرد. اصلاح جاری root مجاور executable منتخب `bin\httpd.exe` را فقط وقتی مسیر reported وجود ندارد می‌پذیرد و در حالت وجود هر دو مسیر متفاوت fail-closed است. preview نیز پس از رفع HTTPS به fixture قدیمیِ بدون pairing سرویس چاپ رسید؛ pairing موجود و بدون secret واقعی برای همان fixture افزوده شده است. هر دو مورد نیازمند CI جدیدند.

## ترتیب ادامه
۱. اجرای CI روی اصلاح جدید و بررسی `failure.json` / `mariadb-install-tail.log` در artifact `sokna-rc-evidence-<sha>`؛ گزارش خام دارای رمز نباید منتشر شود.
۲. بستن Apache fixture و خطای واقعی full-stack، سپس Inno lifecycle؛ گذر fixture معادل نصب کامل نیست.
۳. تکمیل یک مسیر Setup.exe با UI فارسی، پیش‌نیازهای هدایت‌شده، ثبت مسیر نصب و Repair/Uninstall امن.
۴. Updater واقعی، Phase8C، UAT صندوق/چاپگر/قطع شبکه و reboot؛ سپس انتشار نهایی با هش و شواهد همان head.

PowerShell پشت صحنه با درخواست مالک سازگار است. اجرای دستی ps1 توسط کاربر پذیرفته نیست. WiX/EULA مسیر فعال ساخت نشده و هیچ پذیرش هزینه‌ای انجام نشده است. نتیجه skipped یا صرف وجود سورس، PASS محسوب نمی‌شود.
