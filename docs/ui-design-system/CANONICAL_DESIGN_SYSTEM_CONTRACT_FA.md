# SOKNA Cafe Design System — Canonical Contract (New Generation)

**وضعیت:** CANONICAL FOR NEW WORK / MIGRATION IN PROGRESS
**شناسه داخلی:** `SCDS-CANONICAL-2026-R1`
**مبنای بصری:** DNA مفید `Sokna Cafe 1.36.4-dev.26` پس از نقد و اصلاح؛ نه کپی کورکورانه.
**زبان محصول:** فارسی؛ `RTL-first` و `Persian-first` اجباری.

## 1. تصمیم مالک و مرجع
تمام نسخه‌ها، Freezeها و Rهای قبلی با عنوان **SOKNA Design System** رد شده‌اند. آن بسته‌ها Design Authority نیستند و نباید از آن‌ها component/token/pattern به دلیل «قبلاً تأیید شده» وارد شود.

`dev.26` نیز Design System نهایی نیست. فقط:
- Business/UI provenance؛
- هویت و DNA بصری قابل‌استفاده؛
- evidence برای workflowهای موجود.

فرمول مهاجرت UI:
**Audit -> Correct -> Standardize -> Migrate -> Enforce**.

## 2. اصول غیرقابل مذاکره
1. هر Component عمومی دقیقاً یک Owner دارد.
2. Style صفحه‌ای نمی‌تواند Component عمومی دوم بسازد.
3. Token معنایی مالک رنگ/فاصله/اندازه/تایپوگرافی/state است؛ مقدار مستقیم در component/domain فقط با exception ثبت‌شده.
4. `!important` راه‌حل عادی نیست؛ debt موجود فقط باید کاهش یابد.
5. Responsive patch برای یک صفحه بدون root-cause/contract ممنوع است.
6. Persian/RTL/A11y بخشی از Definition of Done است، نه polish.
7. رفتار Component (focus/keyboard/loading/error/disabled/confirm/mobile) بخشی از همان Component است.
8. stateهای معماری (`pending`, `deferred`, `needs_review`, `conflict`, `stale`, `offline`, `reconnecting`, `saving`, `publishing`) presentation semantics واحد دارند.
9. UI هیچ stack/PDO/HTTP/raw technical error را به کاربر عادی نشان نمی‌دهد.
10. Pattern عمومی جدید ابتدا Candidate، سپس QA/registry، سپس reuse می‌شود.

## 3. Persian-first / RTL-first contract
- تمام صفحات انسانی: `<html lang="fa" dir="rtl">` یا معادل server-owned قطعی.
- Font UI: Vazirmatn pinned/canonical مگر Requirement صریح دیگر؛ fallback فقط fallback است.
- Layout direction با logical properties (`inline/block-start/end`)؛ physical left/right فقط exception مستند.
- Bidi برای URL/hash/code/ID با isolate و LTR محلی؛ کل Component برای حل یک ID به LTR تبدیل نشود.
- ارقام UI انسانی فارسی؛ protocol/value داخلی canonical ASCII باقی می‌ماند.
- Money: نمایش فارسی + هزارگان؛ مقدار فرم/محاسبه canonical و مستقل از presentation.
- تاریخ انسانی: جلالی با Owner واحد؛ تاریخ backend ISO/Gregorian canonical.
- Action hierarchy RTL: Primary در سمت شروع جریان RTL؛ cancel/secondary مطابق pattern واحد.
- Quantity Stepper: رفتار و ترتیب `+ / -` مطابق قرارداد RTL پروژه و touch target حداقل 44px.
- واژگان user-facing فارسی canonical؛ اصطلاح فنی انگلیسی فقط در diagnostic/detail فنی با whitelist.
- نیم‌فاصله، علائم فارسی، ترکیب عدد/واحد و punctuation به‌صورت یکدست.
- Search باید input فارسی/IME، clear، Enter و mobile keyboard را پوشش دهد.

## 4. Foundation layers
### 4.1 Tokens
خانواده‌های اجباری:
- color semantic: canvas/surface/text/border/primary/accent/success/warning/danger/info/disabled/focus؛
- typography: font family/size/weight/line-height/number style؛
- spacing؛ radius؛ elevation؛ motion؛ z-index؛
- control/touch/density؛ content widths؛ responsive/container contracts.

### 4.2 Core components
حداقل registry:
Button, IconButton, Input, Textarea, Select/Choice, Search, MoneyInput, JalaliDate, QuantityStepper, Checkbox/Switch, Badge/Status, Alert, Card/Surface, ListRow, DataTable/ResponsiveTable, Tabs, Toolbar/FilterBar, Disclosure, Dialog, Sheet/Drawer, Toast/InlineMessage, EmptyState, Loading/Skeleton, ErrorState, Pagination, PageHeader, AppShell, BottomNav.

### 4.3 Domain patterns
Operational/Table/Order/Preparation، Inventory/Supply/Purchase، Finance/Settlement/Expenses/Tax، Printing/Infrastructure، Guest/Public. Domain pattern حق بازتعریف primitive عمومی را ندارد.

## 5. Accessibility contract
- keyboard complete؛ visible `:focus-visible`؛ focus trap/return برای modal/sheet؛ Escape مطابق critical-action policy؛
- semantic HTML و label/name معتبر؛ icon-only action دارای accessible name؛
- color تنها حامل معنی نیست؛
- motion با `prefers-reduced-motion`؛
- touch target عملیاتی حداقل 44×44؛
- contrast و disabled/read-only differentiation بررسی شود.

## 6. Responsive contract
QA حداقل در 320, 360, 390, 412, 768, 1024 و 1366/1440px.
هیچ overflow افقی در 320px مگر data surface مستند با pattern مخصوص.
Breakpoint جدید بدون ثبت در responsive registry ممنوع. ترجیح با layout fluid/container-aware است، نه breakpoint-per-bug.

## 7. Migration policy
- legacy CSS فعلاً برای حفظ رفتار وجود دارد ولی `LEGACY_MIGRATING` است.
- هر Surface که تغییر می‌کند ابتدا inventory و owner آن مشخص می‌شود.
- Component عمومی از legacy استخراج/اصلاح و به canonical layer منتقل می‌شود.
- پس از migration consumerها، owner/override قدیمی حذف می‌شود؛ CSS موازی باقی نمی‌ماند.
- debt budgets در `UI_DEBT_BASELINE.json` سقف موقت‌اند و فقط اجازه کاهش دارند.

## 8. Definition of Done برای UI
Feature/UI فقط وقتی Complete است که:
- business behavior regression نداشته باشد؛
- canonical component/token ownership رعایت شده باشد؛
- Persian/RTL + 320px + keyboard/focus + state/error/loading tests PASS؛
- duplicate selector owner یا style island جدید ایجاد نشده باشد؛
- visual/browser evidence متناسب با risk موجود باشد.
