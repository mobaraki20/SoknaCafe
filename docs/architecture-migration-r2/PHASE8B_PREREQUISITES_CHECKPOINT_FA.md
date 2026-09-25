# Phase 8B — Windows Prerequisite Contract Checkpoint

تاریخ: 2026-09-24
نسخه engineering: `1.36.4-dev.39`
وضعیت: **PROVIDER CANDIDATE PINNED — WINDOWS FREEZE EVIDENCE + RELEASE LOCK COMMIT PENDING**

## تصمیم
Requirementهای Windows از owner canonical یعنی `runtime/windows/prerequisites.json` خوانده می‌شوند. Runtime/Setup lifecycle مربوط به PHP/Apache/MariaDB/OpenSSL/VC Runtime را تصاحب نمی‌کند؛ فقط preflight/readiness را validate می‌کند.

WIN-06 چهار مرحله جدا دارد:
1. `provider-candidate.json` — انتخاب provider/artifact برای release engineering؛ non-frozen و غیرقابل مصرف توسط Runtime.
2. `freeze-prerequisite-lock.ps1` — اجرای Windows روی باینری واقعی؛ hash/size/version/signature evidence.
3. `release-lock.json` — فقط پس از review evidence و commit روی exact release head authority می‌شود.
4. verified offline bundle — از frozen lock ساخته و قبل از ورود به shell دوباره verify می‌شود.

End-user Setup/Runtime حق silent download ندارد و dependencyهای shared با `manual-external` باقی می‌مانند.

## Provider candidate dev.39
- PHP `8.2.34` x64 Thread Safe؛
- Apache `2.4.68-260920` Win64 VS18؛
- MariaDB Community Server `11.4.12` Win64 MSI؛
- Microsoft Visual C++ v14 x64 pinned artifact؛ exact ProductVersion از خود binary در Freeze استخراج می‌شود.

OpenSSL artifact مستقل در candidate نیست. executable مورد استفاده HTTPS باید از Apache تأییدشده یا installation معتبر موجود resolve شود؛ ownership همچنان external است.

## Lifecycle note
PHP `8.2.34` انتخاب فعلی closure است تا installer qualification با runtime-major migration ترکیب نشود. شاخه PHP 8.2 در `2026-12-31` از security support خارج می‌شود؛ بنابراین PHP 8.3 qualification یک gate جدا برای release بلندمدت است و نباید از روی PASS شدن dev.39 حذف شود.

## زنجیره اعتماد WIN-06
1. candidate باید با `VERSION.txt` جاری یکی باشد و URLها HTTPS + SHA-256 expected داشته باشند.
2. Freeze script فقط باینری واقعی را قبول می‌کند؛ SHA را با candidate تطبیق می‌دهد، size دقیق را از فایل می‌گیرد و policy Authenticode را در صورت required enforce می‌کند.
3. Freeze خروجی `release-lock.json` + evidence JSON می‌سازد؛ candidate خودش هیچ‌وقت `release_frozen=true` نمی‌شود.
4. `prepare-prerequisite-bundle.ps1` فقط lock فریز‌شده و artifact set دقیق را می‌پذیرد.
5. `verify-prerequisite-bundle.ps1` exact-set و metadata lock↔manifest، hash/size/signature policy و `app_version` را fail-closed بررسی می‌کند.
6. `prepare-shell-payload.ps1` bundle مربوط به نسخه دیگری از SOKNA را رد می‌کند.
7. shell payload manifest integrity کل cache و installer-owned files را pin می‌کند.

## Wiring
- workflow دستی `windows-prerequisite-freeze` فقط evidence/lock review candidate تولید می‌کند؛ خروجی خودکار authority Git نمی‌شود.
- workflow `windows-installer-package` برای offline bundle فقط `release-lock.json` committed با `release_frozen=true` را قبول می‌کند.
- workflow `windows-rc-full-stack` نیز فقط lock فریز‌شده commit‌شده را قبول می‌کند و bundle واقعی را برای New/Repair/Recover روی Windows disposable مصرف می‌کند؛ این harness release-engineering است و dependency ownership محصول را تغییر نمی‌دهد.
- Setup UI فقط cache آفلاین verify‌شده را به کاربر نشان می‌دهد و مسیر retry دارد؛ dependency را silent install نمی‌کند.
- C# Setup Host وجود verifier owner را در exact shell contract الزام می‌کند.

## Source gates dev.39
- provider candidate/acquisition contract: PASS در Linux static gate؛
- prerequisites contract: باید در regression کامل dev.39 PASS بماند؛
- Windows Freeze/Authenticode: **PENDING واقعی**؛
- MSI/Burn build و hosted acceptance: **PENDING واقعی**.

## عمداً NOT CLAIMED
- exact sizeهای release تا اجرای Freeze از روی artifact واقعی authority نیستند؛
- Authenticode VC Runtime تا Windows run PASS اعلام نمی‌شود؛
- frozen `release-lock.json` هنوز commit نشده؛
- اجرای واقعی `windows-rc-full-stack` با MariaDB/Apache/HTTPS فریز‌شده، signing/timestamp، reboot/failure evidence و field UAT هنوز شرط خروج از Phase 8B هستند.
