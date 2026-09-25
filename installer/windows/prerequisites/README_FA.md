# پیش‌نیازهای Windows — Provider Freeze / Acquisition / Offline

این پوشه **فایل باینری پیش‌نیاز نگه نمی‌دارد**. سه سطح authority عمداً از هم جدا هستند:

1. `provider-candidate.json`: انتخاب بازبینی‌شده provider/artifact برای checkpoint جاری؛ **frozen release lock نیست** و Runtime حق مصرف مستقیم آن را ندارد.
2. `release-lock.json`: فقط پس از اجرای `freeze-prerequisite-lock.ps1` روی باینری واقعی، بررسی evidence و commit روی exact release head ایجاد می‌شود.
3. `bundle-manifest.json`: در Release build از frozen lock ساخته می‌شود و artifactهای cache را exact-set/hash/size/signature verify می‌کند.

## اصل مالکیت
PHP، Apache، OpenSSL، MariaDB و VC Runtime dependency مشترک/خارجی باقی می‌مانند. SOKNA آن‌ها را در Uninstall حذف نمی‌کند و Runtime lifecycle آن‌ها را supervise نمی‌کند. Offline bundle فقط یک cache تأییدشده است؛ `automatic_install=false` باقی می‌ماند.

OpenSSL برای checkpoint فعلی artifact مستقل ندارد: Setup باید یک `openssl.exe` معتبر از Apache تأییدشده یا نصب موجود دریافت کند. این تصمیم ownership را تغییر نمی‌دهد و قبل از UAT باید مسیر واقعی executable ثبت شود.

## فرآیند Freeze و Release
1. `provider-candidate.json` برای `VERSION.txt` جاری، provider/version/filename/HTTPS source و SHA-256 مورد انتظار را ثبت می‌کند.
2. Job دستی `windows-prerequisite-freeze` یا اجرای محلی release engineering روی Windows، `freeze-prerequisite-lock.ps1` را اجرا می‌کند.
3. Freeze script باینری واقعی را دریافت/مصرف می‌کند، SHA-256 را با candidate تطبیق می‌دهد، **size دقیق را از خود artifact** می‌گیرد، نسخه binary لازم را استخراج می‌کند و در صورت policy اجباری Authenticode/publisher را کنترل می‌کند.
4. خروجی `release-lock.json` و `release-lock.json.evidence.json` ابتدا review می‌شوند؛ سپس lock فقط روی همان exact release commit ثبت می‌شود. Evidence CI باید کنار release record نگهداری شود.
5. `prepare-prerequisite-bundle.ps1` فقط lock با `release_frozen=true` را قبول می‌کند و artifact می‌تواند offline موجود باشد یا فقط در release-build opt-in با `-AllowDownload` دریافت شود.
6. `verify-prerequisite-bundle.ps1` exact-set، lock↔manifest metadata، hash/size/signature policy و exact SOKNA `app_version` را دوباره بررسی می‌کند.
7. فقط bundle verify‌شده زیر `Prerequisites/` وارد installer shell می‌شود. End-user Setup دانلود silent انجام نمی‌دهد.

## Provider candidate dev.39
- PHP `8.2.34` x64 Thread Safe از PHP for Windows؛
- Apache HTTP Server `2.4.68-260920` Win64 VS18 از Apache Lounge؛
- MariaDB Community Server `11.4.12` Win64 MSI؛
- Microsoft Visual C++ v14 x64 `14.51.36247.0` pinned artifact؛ Authenticode در Freeze روی Windows دوباره اثبات می‌شود.

این انتخاب‌ها تا وقتی Freeze evidence روی Windows PASS نشده و `release-lock.json` commit نشده **Release authority نیستند**.

## شرط WIN-06
وجود source/script به‌تنهایی WIN-06 را Complete نمی‌کند. PASS نیازمند artifact واقعی، exact size/hash، signer evidence لازم، frozen lock همان release، bundle verification و Windows run reference است.
