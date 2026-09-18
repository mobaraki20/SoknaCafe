# Handoff — Sokna Cafe 1.36.4-dev.12 / Menu Owner Consolidation

## Baseline و وضعیت
- From: `1.36.4-dev.11`
- To: `1.36.4-dev.12`
- Status: Pilot-ready source checkpoint; Production Go-Live هنوز نیازمند UAT واقعی است.
- Updater engine: `1.5.3`

## Product decision اجراشده
ظاهر منوی dev.11 Visual Baseline باقی ماند. Redesign انجام نشد.

Route نهایی:
- `/menu` = Public Menu
- `/menu?table=TOKEN` = همان Menu با valid table context + ordering capability

Legacy compatibility/redirect ساخته نشد؛ QR چاپ‌شده Legacy وجود ندارد.

## Owner نهایی
- PHP renderer: `menu.php`
- Client runtime/state: `assets/js/menu.js`
- Menu presentation: `assets/css/guest-menu.css` + shared application tokens
- QR URL owner: `table_menu_url()`
- Waiter endpoint: `api/waiter_call.php`

`assets/js/menu-preview.js` حذف شده است. Public/Table branch تنها Capability branch است، نه Renderer/Runtime fork.

## فراخوان گارسون عمومی
این قابلیت Product requirement است و در `/menu` باقی مانده است.
- Public guest میز فعال را انتخاب می‌کند.
- API با `public_table_id` همان میز را server-side و `active=1` validate می‌کند.
- create/status/cancel، ownership، idempotency و rate-limit حفظ شده‌اند.
- لغو فقط برای call متعلق به همان `client_token` مجاز است.
- setting جاری: `public_waiter_call_enabled`.

## SEO / QR
- public canonical: `/menu`
- sitemap: `/menu`
- table token context: `noindex,nofollow,noarchive`
- invalid token 404: `noindex`
- QR: `/menu?table=TOKEN`

## Root `/`
Root دیگر Guest Menu نیست. Logged-in user به home نقش خودش و user ناشناس به Login موجود می‌رود. Landing Page جدید ساخته نشده و Redirect compatibility منوی قدیمی نیز وجود ندارد.

## Regression invariants
این فاز نباید Table/Account workspace، Quick Order، startup open_table first-paint، Itemized Settlement، Late Accounting، Settlement signature/idempotency، Accommodation API/classification، Printing و Mobile presentation تأییدشده را تغییر دهد.

## Test discipline
Test not run = Not tested. Test Drift قدیمی برای dual runtime به Contract جدید همگام شد؛ assertionهای واقعی UI/behavior حذف یا ضعیف نشدند.

## Test evidence این checkpoint
- PHP/JS syntax: PASS
- Unit: `102/102`: PASS
- `/menu` owner + public waiter contracts: PASS
- Core/Domain blocker contracts: PASS در اجرای قطعه‌بندی‌شده
- Browser blocker matrix: PASS در اجرای قطعه‌بندی‌شده
- Public waiter: 320/360/390/412 PASS
- Public Menu: 360/390/412/1366/1440/1920 PASS
- Quick Order production: 390/1366/1920 PASS
- Mobile Cashier/Table Overview/Startup context/Itemized/Late Accounting: PASS
- Automated visual evidence: PASS؛ Human-approved baselines = `UAT_REQUIRED`
- Critical DB probe: `UAT_REQUIRED` (`pdo_mysql` در محیط بررسی نبود)
- Critical authenticated HTTP probe: `UAT_REQUIRED` (staging URL/session موجود نبود)
- اجرای یک‌تکه Release Gate به سقف زمانی ابزار خورد؛ نتیجه آن PASS اعلام نشده و Gate در قطعات مستقل تکمیل شده است.

## Updater Acceptance
بسته Update با `sokna-release-v2` و Engine `1.5.3` ساخته و Manifest/Hash/Delete/Migration آن بررسی شد. Acceptance اجرایی روی کپی dev.11 در این محیط قبل از تغییر فایل‌های زنده Fail-closed شد، چون `pdo_mysql`/MySQL برای snapshot و اجرای Migration موجود نیست. بنابراین Updater Acceptance این checkpoint در این محیط `UAT_REQUIRED` است و PASS اعلام نمی‌شود.

## UAT_REQUIRED
- MySQL/MariaDB واقعی و Migration روی backup قابل restore
- authenticated HTTP/session staging
- QR واقعی روی گوشی و PWA lifecycle در 360/390/412
- چند گوشی هم‌زمان برای Public waiter ownership/rate behavior
- ordering از QR میز روی شبکه واقعی
- Push واقعی
- Windows Print Agent + Printer
- House/Subscriber واقعی
- قطع اینترنت/timeout عملیات مالی
- Human visual comparison با dev.11
- Pilot shift واقعی 1–3 روز

## ممنوعیت برای توسعه بعدی
- Public/Table renderer یا JS/CSS fork نساز.
- `/` را دوباره Guest Menu نکن مگر Product decision جدید ثبت شود.
- Legacy redirect را بدون Requirement واقعی برنگردان.
- Public waiter call را به table-token-only تبدیل نکن.
- برای تست تاریخی، behavior تأییدشده را patch نکن.
