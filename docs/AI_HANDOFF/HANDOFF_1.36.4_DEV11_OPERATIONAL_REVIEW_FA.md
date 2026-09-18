# Handoff — Sokna Cafe 1.36.4-dev.11 Operational Review

## هدف
Checkpoint بدون Feature جدید برای Polish محدود Itemized mobile و مرور جامع آمادگی عملیاتی پس از Stabilization dev.10.

## Owner Map
Ownerهای dev.10 بدون مسیر موازی حفظ شده‌اند. Polish فقط در `assets/css/operator-live.css`, `assets/js/operator.js`, `includes/operator_page.php` و Contract/Browserهای همان Flow انجام شده است.

## Itemized mobile polish
- Scroll owner همان `.itemized-settlement-box .settlement-content` است؛ Scrollbar صرفاً visually hidden است.
- Stepper همچنان 44px hit area دارد؛ SVG visual control کوچک‌تر است.
- `setItemizedFlowNotice()` تنها Owner notice است؛ success بعد از 2600ms Collapse، warning پایدار.
- Action missed item با متن «افزودن قلم جاافتاده» صریح است.

## Operational Review
- Dev Gate blockerها: PASS در اجرای قطعه‌بندی‌شده (اجرای یک‌جای Browser در محیط ابزار ممکن است به سقف زمان بخورد؛ Assertion failure ثبت نشد).
- Release Core/Domain contracts: PASS.
- Release Browser blocker matrix: PASS در اجرای قطعه‌بندی‌شده.
- Visual promotion automated evidence: PASS؛ human baselines = UAT_REQUIRED.
- Supplemental: Small Cafe, Operator Accounts, Staff Quick Order, Guest, Printing, Push, Backup, Operator Actions, Uncertain Retry, Staff Queue, Center, Launch Readiness و چند Browser مدیریتی PASS.
- Critical DB/HTTP probes: UAT_REQUIRED چون `pdo_mysql`/staging session در محیط بررسی موجود نبود.

## Known historical debt
`tests/media-picker-browser.py` روی Autofocus کتابخانه تصویر Fail است؛ Audit 1.36.3 ثابت می‌کند همین Failure قبل از تغییرات جاری روی Baseline وجود داشته است. رفتار جاریِ عدم Autofocus روی touch با سیاست عدم بازکردن ناخواسته کیبورد هم‌راستاست. این تست Gate جاری نیست و نباید با Patch محصول صرفاً سبز شود.

## ممنوعیت
- برای سبزکردن تست تاریخی، autofocus موبایل را بدون تصمیم UX برنگردان.
- UAT_REQUIRED را PASS گزارش نکن.
- Itemized success feedback را دوباره با Toast عمومی یا Backend prose تکرار نکن.
