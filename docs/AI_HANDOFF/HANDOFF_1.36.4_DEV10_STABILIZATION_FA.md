# Handoff — Sokna Cafe 1.36.4-dev.10 Stabilization

## هدف
این checkpoint برای Feature جدید ساخته نشده است. هدف، تبدیل تغییرات dev.7 تا dev.9 به یک Runtime قابل‌ردیابی با Ownerهای واحد و حذف Patch Stacking اخیر است.

## Owner Map نهایی
- Operator bootstrap / startup intent: `includes/operator_page.php` + bootstrap بخش پایانی `assets/js/operator.js`.
- Tables overview state/filter/sort: `assets/js/operator.js`; Mobile و Desktop فقط Presentation متفاوت دارند.
- Table account/invoice render: `assets/js/operator.js` با CSS owner در `assets/css/operator-live.css`.
- Quick Order state/API/render: `staff/quick-order.php` + `assets/js/staff-quick-order.js` + `assets/css/quick-order.css`.
- Itemized Settlement state/review/finalize: `assets/js/operator.js` + `operator/api_table_session.php` + `includes/settlement.php`.
- Accommodation classification/transfer: `includes/accommodation.php` + `operator/api_accommodation.php`؛ House خارج از این Owner است.
- Shared touch/keyboard modality: `assets/js/interaction-modality.js` + shared focus rules in `assets/css/panel-components.css`.

## Root Causes حذف‌شده
1. `open_table` قبلاً بعد از Render عمومی میزها مصرف می‌شد؛ باعث Flash می‌شد. حالا server first-paint و client bootstrap یک Startup Intent دارند.
2. CSS قدیمی Desktop invoice در عمل mismatch بین selector نهایی و DOM را پنهان می‌کرد. DOM و CSS روی `bill-table-current` یکی شدند و override قدیمی حذف شد.
3. Itemized success از Backend prose به Toast/Notice/Card سرایت می‌کرد. UI حالا از structured response استفاده می‌کند و هر اطلاعات یک Owner نمایشی دارد.
4. recent Preview/version suffixها در Runtime به selector/data-ownerهای معنایی تبدیل شدند؛ تست مستقل از بازگشت آن‌ها جلوگیری می‌کند.
5. Touch focus ring از shared modality owner اصلاح شد، نه با حذف accessibility روی دکمه خاص.

## Gateهای جدید
- `tests/v1364-runtime-owner-cleanup.py`
- `tests/v1364-settlement-feedback-contract.py`
- `tests/v1364-startup-context-browser.py`
- `tests/v1364-order-context.py` اکنون First-Paint startup را نیز قفل می‌کند.
- `tests/v1315-touch-focus-browser.py` icon button touch focus را نیز پوشش می‌دهد.

## ممنوعیت ادامه
- Timeout/fade/display:none برای پوشاندن startup flash ممنوع است.
- ساخت Mobile JS/PHP جداگانه ممنوع است.
- افزودن selector با suffix نسخه برای اصلاح بعدی ممنوع است؛ Owner موجود را اصلاح کن.
- Backend `message` نباید دوباره Source of Truth برای UI مالی شود؛ structured fields اولویت دارند.

## UAT_REQUIRED
Device/Print/Network/DB واقعی همچنان طبق Release checklist لازم است. Test not run = Not tested.
