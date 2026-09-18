# هنداور توسعه Sokna 1.36.4-dev.2

## وضعیت

این Build برای محیط تست Pre-Operational است و قابلیت «تسویه آیتمی میز» را روی Baseline `1.36.4-dev.1` اضافه می‌کند.

## قرارداد محصول

- یک Settlement Engine باقی می‌ماند؛ `settlement_record_lines` فقط مدل Allocation همان Engine است.
- پرداخت جزئی سفارش/موجودی/صف آماده‌سازی را تغییر نمی‌دهد.
- پس از اولین پرداخت جزئی، حساب تا صفرشدن مانده یا برگشت همه رسیدهای جزئی قفل است.
- چاپ/برگشت باید با `settlement_id` دقیق باشد.
- UI/Behavior خارج از Scope این قابلیت نباید تغییر کند.

## تست‌های اصلی

- `php tests/unit.php`
- `python tests/itemized-settlement-contract.py`
- `python tests/itemized-settlement-browser.py`
- `bash tests/run-1360-dev-gate.sh`

Race روی DB واقعی، چاپگر واقعی و UAT صندوق همچنان قبل از Go-Live الزامی است.
