# Phase 8B — Setup UI فارسی / RTL Checkpoint

تاریخ: 2026-09-24
وضعیت: **SOURCE IMPLEMENTED — WINDOWS BUILD/RUNTIME ACCEPTANCE PENDING**

## هدف
این checkpoint رابط رسمی New / Recover / Repair ویندوز را بدون ساختن owner موازی برای نصب اضافه می‌کند. UI فقط ورودی‌های کاربر را جمع می‌کند، فایل plan/config موقت را در مسیر ACL-private می‌سازد و سپس `SoknaSetupHost.exe` را با elevation اجرا می‌کند. تمام mutationهای schema/recovery/service/runtime همچنان متعلق به ownerهای canonical موجودند.

## قواعد قفل‌شده
- رابط کاربر فارسی و RTL واقعی است؛ مسیرهای فنی داخلی به کاربر به‌عنوان workflow مستقل نمایش داده نمی‌شوند.
- سه حالت کاربری فقط `نصب جدید`، `بازیابی روی رایانه جدید` و `تعمیر نصب موجود` هستند.
- HTTPS محلی و Apache در UI عملیاتی optional نیستند. switch فنی برای خاموش‌کردن آن‌ها در UI وجود ندارد؛ گزینه‌های bypass فقط در لایه script/CI برای تست‌های کنترل‌شده باقی می‌مانند.
- `AppRoot` قابل انتخاب است و مقدار `C:\SOKNA\Cafe` فقط default پیشنهادی است؛ مسیر قراردادی Freeze نشده است.
- `DataRoot` باید کاملاً خارج از `AppRoot/WebRoot` باشد و UI قبل از elevation overlap را رد می‌کند.
- در معماری فعلی `WebRoot = AppRoot` است چون برنامه public-root مستقل ندارد. Business Data زیر WebRoot قرار نمی‌گیرد.
- Apache lifecycle خارجی می‌ماند. Setup فقط include مالک‌شده SOKNA را validate/apply می‌کند و `reload_required` گزارش می‌دهد؛ start/stop/restart انجام نمی‌دهد.
- secretها masked هستند و فقط بعد از ساخت ACL-private temp directory روی دیسک نوشته می‌شوند؛ در CLI قرار نمی‌گیرند.
- Setup UI با `asInvoker` اجرا می‌شود؛ فقط Setup Host مرز elevation را عبور می‌دهد.
- Repair حق seed-overwrite روی live app را ندارد و ownership Updater حفظ می‌شود.
- دکمه‌های عملیاتی حداقل هدف 44px دارند و ترتیب actionها برای RTL حفظ می‌شود.

## Packaging
- `installer/windows/setup-ui/` owner سورس WinForms است.
- `installer/windows/scripts/build-setup-ui.ps1` خروجی self-contained single-file `win-x64` می‌سازد.
- `prepare-shell-payload.ps1` فایل `SoknaSetupUi.exe` را داخل immutable installer shell قرار می‌دهد.
- MSI میانبر فارسی `راه‌اندازی و تعمیر سکنا` می‌سازد.
- Burn success page با `LaunchTarget` همین executable نصب‌شده را اجرا می‌کند.
- Setup Host exact-set manifest وجود Setup UI را الزام می‌کند.

## Evidence محلی
- `tests/phase8b-setup-ui-contract.py`: PASS
- `tests/phase8b-apache-integration-contract.py`: PASS
- Setup Host / Windows setup / prerequisites / seed / package ownership / WiX / support contracts: PASS
- `php tests/unit.php`: 103/103 PASS
- PHP lint: 287 فایل PASS
- `git diff --check`: PASS

## مواردی که هنوز PASS عملیاتی نیستند
- `dotnet publish` واقعی Setup UI روی Windows runner؛
- MSI/Burn build و LaunchTarget واقعی روی artifact همان head؛
- نصب/Repair/Uninstall و میانبر Setup UI روی Windows؛
- Apache runtime integration و reload توسط owner خارجی؛
- MariaDB New/Recover؛
- signing/timestamp و field UAT.

وجود source/test به‌تنهایی Production-ready محسوب نمی‌شود.
