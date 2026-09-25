# Phase 8B — Windows MSI/Burn Acceptance Checkpoint

وضعیت: **Acceptance harness authored; Windows execution NOT_RUN in this workspace**

## هدف

بسته‌ی Windows نباید فقط compile شود. Job اختصاصی Windows پس از ساخت واقعی MSI و Burn باید lifecycle بسته را روی runner disposable اثبات کند.

## Gate اجرایی

`tests/phase8b-windows-installer-package-runtime.ps1` روی artifactهای ساخته‌شده اجرا می‌شود و موارد زیر را بررسی می‌کند:

- نصب MSI واقعی و وجود Installer Shell؛
- ثبت صحیح MSI در Installed Apps/ARP، نسخه و Publisher؛
- shortcut اصلی و shortcut فارسی بسته پشتیبانی؛
- اجرای collector نصب‌شده و ساخت ZIP؛
- Windows Installer Repair (`/fa`) و بازگرداندن فایل/shortcut متعلق به MSI؛
- حفظ byte-for-byte marker مربوط به live application تحت مالکیت Updater؛
- حفظ byte-for-byte marker مربوط به business data؛
- Uninstall MSI بدون حذف/تغییر live app یا business data؛
- نصب و uninstall واقعی Burn chain؛
- ثبت Bundle در ARP و حذف clean آن.

Marker مربوط به live application عمداً در مسیر disposable تست ساخته می‌شود و هیچ AppRoot ثابت مانند `C:\\SOKNA\\Cafe` را به‌عنوان قرارداد محصول Freeze نمی‌کند.

## محدودیت

این checkpoint فقط harness و CI wiring را کامل می‌کند. تا زمانی که job روی Windows واقعی اجرا و evidence artifact ثبت نشود، WIN-01..WIN-04/WIN-09 و package lifecycle عملیاتی PASS محسوب نمی‌شوند.
