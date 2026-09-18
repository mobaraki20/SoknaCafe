# Sokna Module Ownership Map — 1.36.0

این سند مرز رسمی ماژول‌های Sokna Café را برای معماری **Modular Monolith** مشخص می‌کند.
هدف آن ساخت Plugin System، Microservice یا Database جدا نیست؛ هدف این است که در یک برنامه و یک دیتابیس، مالکیت Business Rule و Data روشن باشد و تغییر یک بخش بی‌دلیل به چند Domain سرایت نکند.

مرجع machine-readable این سند: `includes/modules.php`.
Gate معماری: `tests/v1360-module-ownership-contract.py`.

---

## قواعد ثابت

1. هر Table دقیقاً **یک Owner** دارد.
2. خواندن داده‌ی Domain دیگر ممکن است در `reads_from` ثبت شود؛ این مجوز Mutation نیست.
3. Write روی Table متعلق به Domain دیگر باید از **Public Contract Owner** عبور کند.
4. `includes/maintenance.php`، Installer، Migration و Updater استثناهای Platform هستند و برای Backup/Restore/Upgrade باید بتوانند همه Domainها را ببینند.
5. Reporting یک Read-side composition است و حق تغییر داده‌های Domainهای دیگر را ندارد.
6. `includes/functions.php` یک Shared Legacy Hotspot است؛ Business Logic جدید نباید بدون دلیل به آن اضافه شود.
7. `bootstrap.php` در فاز فعلی Ownerها را به‌صورت سراسری load می‌کند. این موضوع فعلاً به Plugin Loader یا DI Container تبدیل نمی‌شود.
8. Module Toggle واقعی فقط وقتی مجاز است که Route، Navigation، Background Work، Permission و Historical Read آن ماژول همگی Gate شده باشند.
9. خاموش‌کردن قابلیت اختیاری در آینده نباید داده‌ی مالی، Audit یا History را پاک کند.
10. Print، Finance، Inventory Movement و Order State invariants با Modularization تغییر نمی‌کنند.

---

# نقشه کلان

```text
Platform
├── Menu
├── Orders
│   └── Finance
│       ├── Subscribers
│       └── Accommodation
├── Inventory
│   └── Supply
├── Marketing
├── Reporting     (read-side composition)
├── Personnel     (Sokna Center integration)
├── Printing      (critical platform service)
└── Notifications (platform service)
```

این Diagram جهت «مالکیت» را ساده نشان می‌دهد؛ جزئیات `depends_on` و `reads_from` در Registry کد مرجع نهایی هستند.

---

# 1. Platform — هسته سامانه

**نوع:** Core / Required

**Owner اصلی:**
- `bootstrap.php`
- `includes/auth.php`
- `includes/maintenance.php`
- بخش Shared/Legacy در `includes/functions.php`

**Tables:**
- `audit_log`
- `schema_migrations`
- `settings`
- `users`
- `user_capabilities`

**Entry points اصلی:**
- Install/Login/Logout/Help
- `admin/index.php`
- `admin/users.php`
- `admin/settings.php`
- `admin/modules.php`
- `admin/messages.php`
- `admin/maintenance.php`
- `admin/update/`

**Public contracts مهم:** DB bootstrap، Auth، Audit، Product Language (`customer_message*`) و Maintenance/Recovery.

**نکته معماری:** `admin/index.php` Dashboard composition است و می‌تواند Read-side از چند Domain داشته باشد، ولی Owner داده‌های آن Domainها نیست.

---

# 2. Menu — منو و مهمان

**نوع:** Core / Required

**Tables:**
- `categories`
- `items`
- `tags`
- `item_tags`

**Entry points:** Guest menu، Items، Categories، Tags، Menu Transfer.

**وابستگی:** Platform.

**مرز:** Recipe/Inventory metadata روی Menu Item ممکن است توسط Inventory خوانده شود؛ Inventory اجازه ندارد `items` را به‌عنوان Table خودش تلقی کند.

---

# 3. Orders — سفارش، میز و آماده‌سازی

**نوع:** Core / Required

**Tables:**
- `cafe_tables`
- `table_sessions`
- `table_session_clients`
- `orders`
- `order_items`
- `order_status_history`
- `waiter_calls`
- `order_preparation_claims`
- `preparation_adjustments`
- `order_item_adjustments`
- `user_preparation_areas`

**Entry points اصلی:**
- Guest create/status/context/waiter call
- Staff Quick Order
- Operator floor/session APIs
- Waiter/Preparation queue
- Tables/QR

**Capabilities:**
- `orders_floor`
- `preparation`
- `shift_supervision`

**Invariants:** Order state machine، Table session، idempotency، preparation correction visibility.

**نکته:** Orders به Print/Push/Inventory side-effect contract متصل است، ولی آن Domainها مالک Order tables نیستند.

---

# 4. Finance — مالی و تسویه

**نوع:** Core / Required

**Tables:**
- `financial_periods`
- `settlement_records`
- `invoice_discount_audit`

**Entry points:** Invoices، Financial Periods، Bill/Settlement APIs.

**Capability:** `cashier_accounts`.

**مرز حساس:** `includes/settlement.php` فعلاً یک Orchestrator پرریسک است و برای Finalize/Void روی Order/Session نیز اثر می‌گذارد. این رفتار فعلی معتبر است، ولی توسعه جدید نباید Mutationهای بیشتری از Domainهای نامرتبط به آن اضافه کند.

---

# 5. Inventory — انبار

**نوع:** Operational / Optional / Toggleable

**Tables:**
- `inventory_balances`
- `inventory_categories`
- `inventory_count_lines`
- `inventory_count_sessions`
- `inventory_items`
- `inventory_movements`
- `inventory_order_events`
- `inventory_purchase_units`
- `inventory_recipe_components`
- `inventory_recipe_versions`

**Public contracts مهم:**
- `inventory_record_movement_locked(...)`
- `inventory_create_unreviewed_item_locked(...)`
- order-event materialization
- quantity/unit normalization

**Background:** `tools/inventory-worker.php`.

**مالکیت حیاتی:**
`inventory_balances` Projection است؛ Feature دیگر حق UPDATE مستقیم آن را ندارد.

**Runtime control:**
- Setting مرجع: `module.inventory.enabled`.
- خاموشی Inventory، Orders/Finance/Printing را متوقف نمی‌کند؛ Hookهای مصرف سفارش و Worker انبار no-op می‌شوند.
- خاموش‌کردن در صورت Event مصرف unresolved یا شمارش باز Fail-closed است.
- اگر Inventory قبلاً راه‌اندازی شده باشد، خاموشی `inventory_reconciliation_required=1` می‌گذارد. پس از روشن‌کردن دوباره، یک شمارش کامل لازم است و تا آن زمان مصرف خودکار/Low-stock/Profit وابسته و Supply آماده نیستند.
- Recipe، Movement history و Permissionهای ذخیره‌شده حذف نمی‌شوند.

---

# 6. Supply — خرید و تأمین

**نوع:** Operational / Optional / Toggleable (وابسته به Inventory)

**Owner:** `modules/Supply/`

**Tables:**
- `inventory_supply_needs`
- `inventory_supply_receipts`
- `inventory_supply_receipt_allocations`

**Entry points:**
- `operator/supply-needs.php`
- `admin/purchases.php`
- `assets/js/supply-needs.js`
- `assets/js/supply-purchases.js`

**Public contracts:**
- Need upsert
- Preparing snapshot
- Return/unavailable
- Receive/allocation
- Buyer aggregation/read model
- Attention count

**Boundary hardening 1.36:**
- Supply دیگر `inventory_items` و `inventory_balances` را مستقیم Insert نمی‌کند.
- ساخت کالای ناشناس از `inventory_create_unreviewed_item_locked(...)` متعلق به Inventory عبور می‌کند.
- ورود کالا فقط از Inventory Movement Contract عبور می‌کند.
- Panel shell دیگر برای Badge خرید مستقیماً `inventory_supply_needs` را Query نمی‌کند و از `supply_purchase_attention_count(...)` استفاده می‌کند.
- Setting مرجع: `module.supply.enabled`; Dependency رسمی: `Inventory`.
- `Inventory ON + Supply OFF` مجاز است. `Supply ON` بدون Inventory runtime-ready Block می‌شود.
- خاموش‌کردن Inventory، Supply را در همان Transaction خاموش می‌کند. اگر قلمی در «در حال تهیه» باشد، Disable تا تحویل یا بازگرداندن آن Block می‌شود.
- خاموشی Supply داده‌های Need/Receipt تاریخی را حذف نمی‌کند.

---

# 7. Subscribers — حساب مشترکین

**نوع:** Optional Business Module

**Tables:**
- `subscribers`
- `subscriber_ledger`

**Entry points:** Admin/Operator subscribers و settlement-to-subscriber API.

**Dependency:** Finance.

**Invariant:** Ledger append/reversal باید transactional و audit-able بماند.

---

# 8. Marketing — کمپین و رویداد

**نوع:** Optional Business Module

**Tables:**
- `campaigns`
- `events`

**Entry points:** Campaigns و Events.

**Dependency:** Menu + Platform.

**Toggle Pilot 1.36:** این اولین ماژول با Runtime Toggle واقعی است. Setting مرجع `module.marketing.enabled` است. در حالت خاموش، Navigation و Routeهای مدیریت کمپین/رویداد Fail-closed می‌شوند، Guest page هیچ Campaign/Event را Query یا Render نمی‌کند و Metricهای اختصاصی Marketing no-op می‌شوند. رکوردهای `campaigns` و `events` و Audit حذف نمی‌شوند.

**مرزبندی Product Language:** `admin/messages.php` و `customer_message*` ماژول Marketing نیستند؛ این‌ها Cross-cutting Product Language تحت Platform هستند تا خاموش‌کردن Marketing متن‌های سفارش، فراخوان، Push و سایر تجربه‌های اصلی را از دسترس خارج نکند.

---

# 9. Reporting — گزارش‌ها

**نوع:** Optional Read-side Module

**Tables اختصاصی:**
- `menu_metrics_daily`
- `menu_search_terms_daily`

**Entry points:** Analytics، Operations Report، Activity Report، Inventory Report، Metric endpoint.

**Reads from:** Menu، Orders، Finance، Inventory، Accommodation.

**قاعده:** Reporting نباید Business State Domainهای دیگر را Mutation کند.

---

# 10. Accommodation — اتصال اقامتگاه

**نوع:** Optional Integration

**Table:**
- `accommodation_transfers`

**Entry points:** Accommodation account/settings، Operator API، Staff charge.

**Dependencies:** Orders + Finance + Platform.

**وضعیت کنترل:** `toggleable` عمومی نیست. این مرز از 1.36.0-rc.3 Hardening شده و در RC4 حفظ شده است: `accommodation_connection_enabled` فقط تماس زنده (Search/Charge/Remote Void/Retry) را کنترل می‌کند، در حالی که History مالی، Attention queue، invoice lock و Local Recovery همیشه فعال می‌مانند. بنابراین مدل نهایی پیشنهادی برای این Integration **Connection Control** است، نه Module Toggle.

---

# 11. Personnel — پرسنل و حقوق

**نوع:** Optional Integration / Toggle-ready

**Tables محلی اختصاصی:** ندارد.

**Owner:** `includes/sokna_center.php`.

**Entry points:** Personnel launcher، Center Settings، User Directory S2S، Personnel entitlement، Payroll reminder count، safe Center return.

**قاعده:** Café منطق HR/Payroll مرکز سکنا را بازسازی نمی‌کند؛ Core منبع حقیقت است.

**Toggle 1.36:** Setting مرجع `module.personnel.enabled` است. خاموشی Module قبل از Connection state اعمال می‌شود؛ در نتیجه Pairing موجود نمی‌تواند Feature خاموش را دور بزند. Launcher، تنظیم اتصال، Entitlement refresh، Payroll reminder و User Directory S2S متوقف می‌شوند، ولی Pairing/Secret پاک نمی‌شود. `center_return.php` به‌عنوان recovery redirect امن عمداً در دسترس می‌ماند تا کاربر پس از خاموش‌شدن Module در یک Tab دیگر گرفتار نشود.

---

# 12. Printing — چاپ

**نوع:** Critical Platform Service / Required

**Tables:**
- `print_agents`
- `print_attempts`
- `print_claim_requests`
- `print_destinations`
- `print_jobs`
- `print_templates`

**Entry points/Runtime:** Printing admin، Template designer، Print API v4، Windows Print Agent v6.2.0.

**Reads from:** Orders/Finance/Subscribers/Accommodation برای snapshot/rendering فعلی.

**Invariant:** Print Intent persist، Attempts، unknown/recovery، no silent loss.

**Toggle عادی مدیریتی:** توصیه نمی‌شود.

---

# 13. Notifications — اعلان‌ها

**نوع:** Platform Service / Required

**Tables:**
- `push_action_claims`
- `push_delivery_log`
- `push_event_deliveries`
- `push_event_queue`
- `push_subscriptions`
- `user_notification_preferences`

**Entry points:** Notification preferences، Push admin، action/drain/kick APIs، waiter push، Service Worker.

**Background:** `tools/push-worker.php` + opportunistic drain.

**قاعده UX:** Failure یک Refresh پس‌زمینه نباید به‌عنوان Failure عملیات اصلی کاربر نمایش داده شود.

---

# Cross-domain exceptions مجاز

## Maintenance / Backup / Restore / Installer / Updater
مجاز به inspect/migrate همه Tables هستند، چون مسئول Recovery/Upgrade کل Application هستند. این مجوز نباید به Feature معمولی تعمیم داده شود.

## Reporting
Read-only composition بین Domainها مجاز است؛ Mutation ممنوع.

## Panel Shell
Navigation/Badge composition مجاز است، ولی ترجیح این است که Badge و Count از Public Read Contract ماژول بیاید. Supply در 1.36 به این الگو منتقل شد.

---

# Cross-domain write hotspotهای فعلی

این موارد در Audit 1.36 صریحاً ثبت شدند. وجودشان به معنی مجازبودن توسعه بیشتر روی همان الگو نیست؛ فقط رفتار فعلی را مستند می‌کند تا Refactor بعدی هدفمند باشد.

- **Finance → Orders:** `includes/settlement.php` و `operator/api_bill.php` برای Finalize/Void/Edit bill روی Order/Session/Preparation state اثر می‌گذارند. این‌ها Orchestratorهای حساس‌اند و بدون تست Settlement/Preparation نباید شکسته شوند.
- **Accommodation → Orders:** آزادکردن/انتقال حساب اقامتگاه در بعضی مسیرها `table_sessions` و `waiter_calls` را تغییر می‌دهد.
- **Orders → Finance:** `operator/api_table_session.php` هنگام Session merge شناسه `invoice_discount_audit` را اصلاح می‌کند. این Reverse write یک coupling ثبت‌شده است.
- **Notifications → Orders:** `api/push_action.php` برای Action امن اعلان می‌تواند `waiter_calls` را تغییر دهد.
- **Platform/User Admin → Orders:** `admin/users.php` محدوده آماده‌سازی کاربر را در `user_preparation_areas` مدیریت می‌کند.
- **Shared settings:** Marketing، Printing، Notifications، Inventory، Accommodation، Personnel و Orders برای namespaceهای تنظیمات خود هنوز روی Table عمومی `settings` write مستقیم دارند. این Table عمداً Configuration store مشترک است، ولی در آینده اگر همین نقاط لمس شدند بهتر است به `setting_write/delete` owner contract منتقل شوند؛ Refactor یک‌باره ROI ندارد.

**تصمیم فعلی:** فقط Boundary ماژول Pilot یعنی Supply به‌صورت سخت Fail می‌شود. Cross-writeهای بالا قبل از تغییر همان Workflow باید یکی‌یکی با Contract Owner جایگزین شوند؛ پروژه-wide rewrite انجام نمی‌شود.

---

# Hotspotها و بدهی فنی ثبت‌شده

## P1 — `includes/functions.php`
هنوز مجموعه‌ای از Platform، Orders، Menu و Permission helpers را در خود دارد. Refactor یک‌باره ممنوع؛ هنگام لمس Feature، Logic دارای Owner روشن باید تدریجی Extract شود.

## P1 — `includes/settlement.php`
Finance Orchestrator برای بستن/برگشت Session و Order نیز Mutation انجام می‌دهد. فعلاً Business invariant معتبر است؛ قبل از هر Extraction باید Settlement/Reverse contract تست شود.

## P1 — Printing read coupling
Printing برای render/snapshot فعلاً جداول Orders/Finance را می‌خواند. تا قبل از نیاز واقعی، Snapshot architecture جدید ساخته نمی‌شود؛ ولی این Read Coupling ثبت شده است.

## P1 — Notifications read coupling
Push برای ساخت پیام/Action بعضی Order/User data را می‌خواند. Queue ownership مستقل است، اما read coupling باقی است.

## P2 — `panel_layout.php`
Shell هنوز برای بعضی Badgeهای Inventory/Orders Query مستقیم دارد. Supply Pilot از این وضعیت خارج شده است. Extract سایر Badgeها فقط هنگام لمس همان Feature انجام شود.

## P2 — Pre-launch schema history
آرشیو Migrationهای نسخه‌های آزمایشی از Source فعال حذف شده است. `database/schema.sql` تنها Baseline نصب تمیز است و هر Migration لازم برای Update فقط داخل همان Release Artifact حمل می‌شود. Tableهای بازنشسته پیش از Go-Live در Runtime/Schema جاری Contract ندارند.

---

# Module Toggle Strategy

از 1.36، `sokna_module_enabled()` فقط برای ماژولی Runtime Setting می‌خواند که `toggleable=true` و end-to-end gate کامل داشته باشد. Optional بودن در Registry به‌تنهایی به معنی قابل خاموش‌شدن نیست.

**Toggleهای end-to-end فعال:**
- Inventory با `module.inventory.enabled`: UI/Route/Worker/Order hooks و گزارش‌های وابسته را کنترل می‌کند؛ Orders/Finance/Printing مستقل باقی می‌مانند و Re-enable پس از استفاده نیازمند شمارش کامل است.
- Supply با `module.supply.enabled`: Need/Preparing/Receive را کنترل می‌کند و فقط در صورت Inventory runtime-ready قابل فعال‌شدن است.
- Marketing با `module.marketing.enabled`: Campaign/Event را در UI/Route/Guest output کنترل می‌کند.
- Reporting با `module.reporting.enabled`: چهار گزارش مدیریتی و جمع‌آوری Metric تازه منوی مهمان را کنترل می‌کند؛ Order/Finance/Audit اصلی مستقل باقی می‌مانند.
- Personnel با `module.personnel.enabled`: Handoff، Entitlement، Payroll reminder، User Directory S2S و تنظیم اتصال Center را کنترل می‌کند؛ Pairing ذخیره‌شده و عملیات اصلی کافه مستقل باقی می‌مانند.

Toggleهای منطقی بعدی فقط پس از Hardening مستقل هرکدام بررسی می‌شوند؛ Subscribers فعلاً به‌دلیل Ledger مالی Toggle عمومی نمی‌گیرد.

Core/Finance/Orders/Platform/Menu و Printing نباید Casual Toggle داشته باشند.

---

# Gate معماری

`tests/v1360-module-ownership-contract.py` موارد زیر را Fail می‌کند:

- Table بدون Owner
- Table با چند Owner
- Dependency ناشناخته یا Cycle در `depends_on`
- Route/API بدون Owner یا با چند Owner
- Entry point ثبت‌شده ولی حذف‌شده
- بازگشت آرشیو Migrationهای Pre-Launch به Source فعال
- Direct write از Supply به Tableهای Inventory
- Direct SQL از Panel Shell به Supply-owned table
- حذف Public Contract ساخت کالای `needs_review` در Inventory
- Runtime Toggle بدون `toggleable/setting_key/default_enabled` معتبر
- بازگشت Product Language (`admin/messages.php`) به مالکیت Marketing

در 1.36.0 فعلی:

- **13 Module**
- **55 Current Table mapped**
- **96 PHP Route/API mapped**
- **Missing owner = 0**
- **Duplicate owner = 0**
- **Dependency cycle = 0**
- **Historical migration archive in active source = 0**

این Map مبنای توسعه Moduleهای بعدی است؛ جابه‌جایی Folder به‌تنهایی Modularization محسوب نمی‌شود.
