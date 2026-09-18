# آزمون‌های جاری Sokna 1.36.4-dev.19

## Gateهای رسمی
- Dev Gate: `bash tests/run-1360-dev-gate.sh`
- Release Gate: `bash tests/run-release-gate.sh`
- Promotion: `SOKNA_RELEASE_PROMOTION=1 bash tests/run-release-gate.sh` فقط در محیط دارای DB/HTTP واقعی.

## لایه‌های تست
1. **Blocker:** تست‌های ثبت‌شده در Dev/Release Gate؛ Failure یا Skip در Promotion مانع انتشار است.
2. **Supplemental operational:** سناریوهای عملیاتی مثل `small-cafe-flow.py`, `operator-accounts.py`, `staff-quick-order.py`, `backup-manager-*`, `operator-actions-browser.py`, `quick-order-uncertain-browser.py`, `staff-action-queue-*`, `printing-system-*`, `launch-readiness.py`. این‌ها در Operational Review دوره‌ای اجرا می‌شوند.
3. **Historical/Reproduction:** تست‌های قدیمی نسخه‌محور یا بازتولید که الزاماً Gate جاری نیستند. نبودن در Gate به معنی حذف‌شدن خودکار نیست.

## Known historical test debt
- `media-picker-browser.py`: assertion مربوط به Autofocus آلبوم تصویر از قبل از P2 / 1.36.3 روی Baseline نیز Fail بوده است (`docs/P2_OWNER_TEST_AUDIT_1.36.3_FA.md`). رفتار موبایل جاری عمداً از Autofocus ناخواسته و بازشدن کیبورد اجتناب می‌کند؛ این تست Blocker جاری نیست تا Contract آن به‌صورت آگاهانه بازتعریف شود.

## قراردادهای dev.19 — Non-print hardening
- `dev19-nonprint-contract.py`: Owner واحد Stepperهای اصلاح تعداد، جداسازی geometry سبد Quick Order و ممنوعیت دانلود خام Backup خارج از سرور.
- `dev19-stepper-alignment-browser.py`: هندسه واقعی Stepper اصلاح تعداد در 320–1366 و Quick Order دسکتاپ.
- `dev19-secure-backup-runtime.php`: round-trip رمزنگاری authenticated و fail-closed برای رمز اشتباه، tamper و truncation.
- `backup-manager-contract.py` + `dev17-portable-backup-*`: فرمت داخلی قابل بازیابی حفظ شده و transport امن `.skb` روی همان validator/restore owner قرار دارد.
- این Release Schema/Migration جدید ندارد؛ fixture رسمی Update باید `dev.18 → dev.19` را روی همان source tree بازتولید کند.

## قراردادهای dev.12
- Itemized mobile scroll بصری بدون Scrollbar زمخت، ولی Scroll واقعی حفظ می‌شود.
- Stepper: Hit Area >=44px و visual glyph کوچک‌تر.
- Success notice موفقیت Itemized به‌صورت transient Collapse می‌شود؛ warning پایدار می‌ماند.
- «افزودن قلم جاافتاده» Action صریح است.
- Startup `open_table` detail-first و بدون Flash Overview.
- Mobile Overview/Account/Quick Order از Desktop presentation مستقل‌اند، Business state مشترک است.
- Accommodation error classification و tracking_id contract-aware است.
- `/menu/` تنها Route منوی عمومی/میز است؛ `/` مسیر منوی مهمان نیست.
- Public/Table یک `menu/index.php` و یک `assets/js/menu.js` دارند؛ `menu-preview.js` حذف شده است.
- فراخوان گارسون عمومی با `public_table_id` و server-side active-table validation/ownership حفظ شده است.
- `/menu/` canonical و sitemap owner است؛ table-token context و invalid token `noindex` هستند.

## UAT_REQUIRED
DB واقعی، authenticated staging HTTP، Windows Print Agent/Printer، Android/PWA/Push واقعی، و human visual baselines. Test not run = Not tested.

## dev.14 Print Reliability / Stepper
- `print-dev14-reliability-contract.py`: حذف امن مقصد سفارشی، نمایش FIFO blocker و Failover Primary/Fallback Print API v4.
- `bill-edit-stepper-standard-contract.py`: یک Owner برای Stepperهای Final/Prepared در اصلاح تعداد.

- `print-v4-agent61-accept-contract.py` + `print-agent-receipt-validator.php`: parity قرارداد `local_receipt_id` واقعی Agent 6.0/6.1 با Print API v4 Accept.
