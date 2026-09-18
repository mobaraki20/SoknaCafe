# HANDOFF — Sokna 1.36.4-dev.7 Test Checkpoint

Baseline مبدا: `1.36.4-dev.6`.

## Ownerهای تغییر
- Tables/Invoice UI: `assets/css/operator-live.css`, `assets/js/operator.js`, `includes/operator_page.php`.
- Quick Order: `staff/quick-order.php`, `assets/css/quick-order.css`, `assets/js/staff-quick-order.js`.
- Finance signature: `includes/settlement.php`, `includes/settlement_allocations.php`.
- Accommodation/Subscriber consumers: `includes/accommodation.php`, `operator/api_subscribers.php`.

## Invariants
- Allocation/Idempotency/Discount arithmetic تغییر مسیر موازی ندارد.
- تمام مقصدهای مالی Signature منتشرشده توسط Operator feed را با `settlement_account_state_locked` و `settlement_assert_expected_account` بررسی می‌کنند.
- `settlement_assert_expected_invoice` و `settlement_review_signature_locked` حذف شده‌اند و نباید دوباره ایجاد شوند.
- موبایل Quick Order نباید تحت تأثیر CSS Desktop قرار گیرد.
- Test not run = UAT_REQUIRED.

## UAT باز
- Charge واقعی API اقامتگاه جدید بعد از رفع False Conflict.
- MariaDB/MySQL و Authenticated HTTP promotion gate.
- Windows Print Agent/Printer، Push و Device واقعی.
