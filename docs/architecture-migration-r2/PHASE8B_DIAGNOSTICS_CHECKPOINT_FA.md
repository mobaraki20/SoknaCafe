# Phase 8B — Setup Diagnostics / Support Bundle Checkpoint

وضعیت: **Source-level complete; Windows runtime/installer acceptance remains required**

## هدف

این checkpoint فقط observability نصب/Repair را یکپارچه می‌کند و هیچ business state یا ownership جدیدی ایجاد نمی‌کند.

## قرارداد قطعی

- `support.zip` با **allowlist صریح** ساخته می‌شود؛ archive بازگشتی از session/data/app ممنوع است.
- فایل‌های پایه bundle:
  - `summary.json`
  - `events.jsonl`
  - `components.json`
- در صورت معرفی امن توسط Burn/MSI، فقط نسخه sanitize‌شده‌ی `burn.log` و `msi.log` می‌تواند افزوده شود.
- `components.json` فقط وضعیت/نسخه‌ی سرویس‌های `SoknaRuntime` و `SoknaPrintWorker` و snapshot محدود health چاپ را ثبت می‌کند.
- دیتابیس، `config.php`، secret file، durable print queue، backup/recovery archive، TLS private key و فایل اصلی `health.json` هرگز داخل bundle کپی نمی‌شوند.
- متن لاگ قبل از اضافه‌شدن به bundle از redaction canonical عبور می‌کند و برای فایل خارجی سقف اندازه وجود دارد.
- Repair باید byte-for-byte فایل‌های live application تحت مالکیت Updater را حفظ کند؛ تست Windows این invariant را در success و rollback failure بررسی می‌کند.

## Evidence / Gates

- `tests/phase8b-windows-setup-contract.py`
- `tests/phase8b-windows-setup-runtime.ps1`
- Windows CI: runtime test واقعی روی SCM/TLS/rollback/support bundle.

## موارد باز

این checkpoint به‌تنهایی Production acceptance نیست. Build/Run واقعی Burn/MSI، signing/timestamp، install/repair/uninstall matrix و UAT ویندوز/پرینتر همچنان در Phase 8B/8C باز هستند.
