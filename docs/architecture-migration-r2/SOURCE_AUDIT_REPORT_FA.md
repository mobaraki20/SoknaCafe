# گزارش ممیزی سورس SOKNA — Phase 0

تاریخ: 2026-09-18  
سورس کاری تأییدشده توسط مالک پروژه: `Sokna-CLEAN-INSTALL-1.36.4-dev.26(1).zip`  
نسخه داخلی: `1.36.4-dev.26`  
SHA-256 آرشیو: `1a1d0723edb5cf2ebdb6e7f37925b08ca729d82ceb283089183fbca61439e4dc`

## 1) نتیجه اجرایی

این سورس برای ادامه مهاجرت قابل استفاده است، اما قبل از هر تغییر معماری باید یک Checkpoint پاک‌سازی Baseline انجام شود. دلیل: سورس فعلی چند ناسازگاری نسخه/Registry و یک خطای UI contract دارد که از قبل موجود بوده‌اند و نباید با Regressionهای مهاجرت مخلوط شوند.

هیچ Domain جدیدی در dev.26 پیدا نشد که خارج از تصمیم‌های Frozen هنداور باشد؛ بنابراین در پایان Phase 0 **تصمیم محصول جدیدی لازم نیست**. مسائل کشف‌شده در این گزارش یا از قبل در هنداور تصمیم‌گیری شده‌اند یا Technical Choice هستند و طبق هنداور بر عهده اجرای مهندسی‌اند.

## 2) نقشه سورس فعلی

ساختار اصلی: PHP/MariaDB Modular Monolith با entrypointهای `admin/`, `api/`, `operator/`, `waiter/`, `staff/`, `menu/` و ownerهای Domain در `includes/` و `modules/`.

Registry فعلی 13 Domain/Capability دارد:

| Domain | Owner فعلی |
|---|---|
| platform | `bootstrap.php`, `includes/auth.php`, `includes/maintenance.php`, `includes/functions.php`, `includes/function_domains/*` |
| menu | `includes/menu_catalog.php`, `admin/items.php`, `assets/js/menu.js` |
| orders | `api/create_order.php`, `staff/api_quick_order.php`, `operator/`, `waiter/` |
| finance | `includes/settlement.php`, `admin/invoices.php`, `admin/financial_periods.php` |
| inventory | `includes/inventory.php`, `admin/inventory*.php` |
| supply | `modules/Supply/` |
| subscribers | `includes/subscribers.php`, `admin/subscribers.php` |
| marketing | `admin/marketing.php`, `admin/events.php` |
| reporting | `includes/reporting.php`, `admin/*_report.php`, `admin/analytics.php` |
| accommodation | `includes/accommodation.php`, `admin/accommodation*.php` |
| personnel | `includes/sokna_center.php`, `admin/personnel.php` |
| printing | `includes/printing.php`, `print-agent/v4/` |
| notifications | `includes/push.php`, `service-worker.js` |

Schema پایه 61 جدول دارد. Public Edge، Relay، Deferred-safe، Expenses، Tax و Table Draft هنوز owner/table مستقل در Registry ندارند.

## 3) Baseline Tests

اجرای `tests/run-1360-dev-gate.sh` روی همین آرشیو:

- PHP lint: **PASS — 175 فایل**
- JavaScript syntax: **PASS — 35 فایل**
- Unit: **PASS — 103 check**
- Supply modular contract: **PASS**
- UI conformance contract: **PASS**
- UI language contract: **FAIL**؛ واژه داخلی `Attempt` در `admin/printing.php` هنوز در UI مدیر دیده می‌شود.

محیط ممیزی: PHP 8.4.23، Node 22.16.0، Python 3.13.5، Chromium موجود. Extension `PDO` موجود است اما `pdo_mysql/mysqli` در محیط ممیزی نصب نیست؛ بنابراین تست‌هایی که به MariaDB واقعی نیاز دارند **BLOCKED_ENVIRONMENT** هستند و PASS محسوب نمی‌شوند.

### Baseline defectهای مستقل از مهاجرت

1. `service-worker.js` هنوز `1.36.4-dev.23` و cache dev.23 را اعلام می‌کند.
2. `README_FA.md` و `docs/UPDATER_FA.md` هنوز current checkpoint را dev.23 معرفی می‌کنند.
3. `tests/print-dev23-claim-reconciliation-contract.py` نسخه جاری را دقیقاً dev.23 فرض می‌کند و به‌عنوان تست تاریخی stale شده است.
4. Registry ماژول Printing مالکیت `print_claim_reconciliations` را ثبت نکرده است.
5. تست module ownership علاوه بر mismatch بالا، count تاریخی schema را hard-code کرده و باید با source فعلی هم‌راستا شود.
6. `v1360-final-invariants.py` به‌علت mismatch نسخه Service Worker شکست می‌خورد.

این موارد باید در **Checkpoint 0A — Baseline Stabilization**، قبل از Phase 1، اصلاح و Gate سبز شود.

## 4) یافته‌های Domain به Domain

### Orders / Guest
`api/create_order.php` یک handler Local بالغ دارد: transaction/lock، idempotency با `client_token`، validation کاتالوگ/قیمت/دسترسی، business order number و history. این handler باید **مرجع Business Local بماند** و Public Relay فقط request را به آن برساند؛ validation نباید در Public دوباره نوشته شود.

Guest UI فعلی از `menu/index.php` مستقیم DB Local را می‌خواند. Renderer واحد و context-based است و باید حفظ شود، ولی مسیر QR اصلی طبق هنداور به Public snapshot منتقل می‌شود.

### Table Draft
پیاده‌سازی server-persistent Table Draft وجود ندارد. Draft فعلی مرورگر/flow سفارش جای قرارداد Frozen را نمی‌گیرد. باید در Phase 6 اضافه شود: یک Draft فعال به‌ازای هر میز، بدون اثر مالی/انبار/شماره سفارش تا Finalize.

### Sellable / Service Item
`items` فیلد صریح `menu_item/service_item` ندارد. رفتارهای `staff_only` و `preparation_station='none'` بخشی از نیاز را پوشش می‌دهند اما جای type صریح را نمی‌گیرند. `sellable_kind` باید additive اضافه شود و تاریخچه order line دست‌کاری نشود.

### Inventory / Supply
Inventory authority و movement/idempotency فعلی ارزش حفظ دارند. Supply state machine و receive با `request_token`, `occurred_at`, movement idempotency پایه خوبی برای Deferred-safe است. Stock count هم draft/finalize دارد. Gapها: ingress Deferred-safe، conflict/review state، batch receipt اتمیک و اتصال به period gate.

### Finance
Settlement/reversal و snapshot فعلی پایه حفظ‌شدنی است. Financial period close فعلی Deferred pending/unknown-Public/late correction را نمی‌شناسد و باید در Phase 5 بازطراحی شود. Expenses و Tax وجود ندارند.

### Preparation Permissions
Debt دقیقاً همان مورد هنداور وجود دارد:
- `waiter/index.php` supervisor-only را وارد سطح UI می‌کند.
- `waiter/api_feed.php` supervisor-only را با guard فعلی بلوک می‌کند.
- کاربر دارای `shift_supervision + preparation` فقط assigned area را می‌بیند، در حالی‌که باید همه areaها را ببیند ولی فقط assignedها actionable باشند.
- mutation check فعلی برای non-admin/assigned area پایه خوبی دارد.

راه‌حل از قبل Frozen است: `visible_preparation_areas` و `actionable_preparation_areas` باید server-side از هم جدا شوند.

### Printing
Queue/state machine/idempotency/fallback فعلی قوی است اما deployment به Print Agent جداگانه متکی است. طبق هنداور، state machine حفظ می‌شود ولی Worker باید در Local Runtime داخلی SOKNA مدیریت/ship شود. `admin/printing.php` نیز یک language-contract defect دارد.

### Notifications
Outbox/queue/push routing فعلی قابل حفظ است. Processing باید به Runtime Notification Worker منتقل شود. Public Push Gateway فقط اگر UAT واقعی نیاز را ثابت کند.

### Accommodation / Center
Accommodation contract بالغ و Local-authoritative است و باید حفظ و transport تطبیق داده شود. Center فعلی inbound S2S endpoint دارد؛ target هنداور outbound Local→Center را ترجیح می‌دهد و Public نباید personnel DB شود، بنابراین این مسیر در Phase 7 refactor می‌شود.

### Updater / Backup / Recovery
Engineهای موجود ارزش حفظ دارند: staging/validation/recovery point/rollback و backup integrity/encryption. Target جدید باید Windows Local Runtime، Public pairing، recovery set گسترده‌تر و machine takeover را اضافه کند؛ بازنویسی بی‌دلیل engine فعلی توصیه نمی‌شود.

### UI / Design System
Ownerهای فعلی `assets/css/tokens.css`, `app.css`, `responsive.css`, `guest-menu.css`, `panel.css`, component CSSها و UI موجود باید source-of-truth بمانند. تغییر UX فقط در سطح موارد صریحاً مجاز در هنداور انجام می‌شود.

## 5) Mutation Inventory

اسکن heuristic روی SQL mutationها 385 occurrence پیدا کرد. پرتراکم‌ترین ownerها: Orders، Printing، Menu، Platform، Inventory، Supply و Notifications. فایل CSV جزئیات جداگانه تولید شده است. موارد `UNKNOWN` عمدتاً dynamic SQL/false-positive هستند و قبل از refactor باید دستی ownerگذاری شوند.

## 6) ریسک‌های فوری

- دو مرجع Business با کپی validation در Public — **Blocker**
- اجرای دیرهنگام عملیات Realtime پس از برگشت Local — **Blocker**
- مخلوط‌کردن Deferred-safe و Realtime — **Blocker**
- تغییر دوره مالی بسته توسط late sync — **Blocker**
- Supervisor gaining preparation mutations — **Blocker**
- بازنویسی Print/Settlement/Inventory بالغ به‌جای wrapping/refactor — ریسک بالا
- Red baseline قبل از مهاجرت — ریسک تشخیص Regression

## 7) حکم Phase 0

Phase 0 از نظر شناخت سورس **کامل** است، با یک شرط ورود به Phase 1: اجرای Checkpoint 0A و سبزکردن baseline static gates. هیچ تصمیم محصول جدید برای شروع 0A لازم نیست.
