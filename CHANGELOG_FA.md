## 1.36.4-dev.34 — Phase 6C / Server-persistent Table Draft
- Table Draft به Business State محلی و server-persistent با یک Draft فعال برای هر میز تبدیل شد.
- optimistic version conflict مانع overwrite شدن Draft جدید توسط context قدیمی می‌شود.
- Quick Order عادی از server draft به‌عنوان authority استفاده می‌کند؛ browser storage authority نیست.
- Draft Save هیچ Order/Business Number/Preparation/Inventory/Finance/Receipt ایجاد نمی‌کند.
- Finalize وضعیت جاری را revalidate و به canonical Staff Order transaction واگذار می‌کند.
- Remote Draft فقط Realtime/Local-required است و Deferred-safe نیست.
- تست دو context کارکنان، HTTP actor/permission و MariaDB lifecycle به gateهای رسمی اضافه شدند.
## 1.36.4-dev.26 — Pre-Operational Print Clean Baseline
- پاک‌سازی یک‌باره تاریخچه تستی Print v4 روی Server با حفظ Agent/Destination/Template و تمام داده‌های کسب‌وکار.
- ترتیب حذف برای Claim reconciliation/claim requests/attempts/jobs با Foreign Keyها هم‌راستا شد.
- `AUTO_INCREMENT`های چاپ عمداً Reset نمی‌شوند تا شناسه Attempt/Job قدیمی دوباره استفاده نشود.
- اجرای Update فقط پس از Stop سرویس Agent و خارج‌کردن `queue.db` محلی از مسیر فعال مجاز است.
- این Reset صرفاً Pre-Operational است و برای Production مجاز نیست.

# 1.36.4-dev.25

- Print API v4: پذیرش `null` برای Evidence اختیاری Report (`spooler_job_id`, `error_code`, `error_message`) و رفع Outbox گیرکرده با `invalid_field_type`.
- Submission Fence و الزام `spooler_job_id` برای وضعیت `submitted` بدون تغییر باقی ماند.

# تغییرات Sokna Cafe

## 1.36.4-dev.24
- Hotfix رسمی Print Recovery برای نصب تستی dev.23؛ بدون Migration دیتابیس.
- Legacy Claim فاقد `response_snapshot_json` از Evidence پایدار Attempt/Job بازسازی می‌شود تا `claim_snapshot_missing` بن‌بست reconciliation امن را ایجاد نکند.
- گزینه «دیگر نیاز به چاپ نیست» برای Jobهای چاپ‌نشده و وضعیت‌های مبهم اضافه شد؛ رزرو فقط قبل از Accept و با Lock تراکنشی قابل لغو است.
- رفتار fail-closed برای هر Evidence پذیرفته/شروع/Spool شده حفظ شده و Auto-Reprint اضافه نشده است.

## 1.36.4-dev.23
- رفع Bug تغییرپذیری replay در Print API: Claim قبلاً فقط Attempt ID را نگه می‌داشت و payload/hash/destination را از Job جاری بازسازی می‌کرد؛ اکنون response snapshot بدون Lease به‌صورت durable ذخیره می‌شود.
- رفع بن‌بست عملیاتی `reconciliation_required` برای collision اثبات‌شدهٔ Attempt ID: رزرو هرگز Accept‌نشده با Audit منقضی و Attempt تازه با شناسه غیرمتعارض ساخته می‌شود؛ Attempt پذیرفته/شروع‌شده همچنان fail-closed و انسانی است.
- افزودن capability هماهنگ `claim_conflict_rekey_v1` برای Agent 6.2.4 و migration افزایشی dev.22 → dev.23.
- reconciliation به Ledger مستقل per-Attempt منتقل شد تا Claim چندآیتمی، replay پاسخ گم‌شده و چند collision را idempotent پوشش دهد.
- قفل تمام Attemptهای Job، رد Attempt جدیدتر/فعال، محدودیت جهش شناسه، اثبات mismatch در برابر Snapshot و کنترل row-count مسیر Accept اضافه شد تا race یا Agent معیوب نتواند چاپ تکراری بسازد.
- هنگام rekey فقط فیلدهای هویتی Attempt در Snapshot تغییر می‌کنند؛ Job/Payload/Destination عین Snapshot اولیه باقی می‌مانند.
- هیچ داده چاپی حذف/overwrite نمی‌شود، Auto-Reprint اضافه نشده و UI/مالی/سفارش تغییر نکرده است.

## 1.36.4-dev.19
- اصلاح Root Cause هندسه Stepper در «اصلاح تعداد»: Final/Prepared یک Owner و یک control column مشترک دارند؛ override موازی حذف شد.
- محدودکردن CSS فشرده Quick Order به product-card تا Stepper سبد دسکتاپ دوباره به هندسه 132×44 استاندارد بازگردد.
- حذف دانلود خام پشتیبان خارج از سرور و افزودن خروجی امن `.skb` با Argon2id + XChaCha20-Poly1305 secretstream.
- رمز بازیابی Backup در تنظیمات/DB/Audit نگهداری نمی‌شود؛ Import امن پس از decrypt از همان validator و restore owner موجود عبور می‌کند.
- Sodium به پیش‌نیاز Install/Update اضافه شد؛ Backup داخلی سرور و Recovery Point تغییر فرمت نداده‌اند.
- بدون تغییر Schema و بدون تغییر Catalog/Menu architecture؛ قابلیت‌های سالم dev.18 فقط Regression-gate شده‌اند.

## 1.36.4-dev.18
- اصلاح ریشه‌ای Base URL/Session برای entrypointهای زیرپوشه‌ای و canonical شدن `/menu/`؛ `/Menu/` فقط redirect سازگاری QR است.
- اصلاح Search مدیریت منو برای تایپ فارسی، نیم‌فاصله/خط‌تیره و جست‌وجوی برچسب‌ها بدون reload حین تایپ.
- نمایش و ویرایش صریح عضویت هر آیتم در کافه/صبحانه/ناهار با محدودیت دسته‌بندی.
- فشرده‌سازی رابط دسکتاپ مدیریت منو و حذف overlay/blocking ویرایش سریع در دسکتاپ.
- نمایش context منوی فعال در سفارش سریع حتی وقتی فقط یک منو قابل سرو است.

## 1.36.4-dev.17
- Catalog واحد و داده‌محور برای Guest/Table/Quick Order با کافه، صبحانه و ناهار.
- 128 آیتم واقعی، دسته‌های canonical با stable key/audience و جداسازی presentation از preparation station.
- Menu Manager یکپارچه با Search/Filter/Sort/Bulk/Arrange و حذف صفحات ترتیب منسوخ.
- مسیر نهایی `/Menu/` و حذف route قدیمی `/menu`.
- Backup/Restore یکپارچه و قابل‌حمل با Upload مستقیم و recovery point داخلی.
- Import/Export پایدار بر مبنای category/menu keys و Schema Health جدید.
- همگام‌سازی Web با Print Agent 6.2.0 و رفع Root Causeهای UI قالب چاپ.

## 1.36.4-dev.16
- Print Reliability RC: retry cycle بدون reset تاریخچه، Attempt reconciliation و UTC wire time.
- Local Wake محدود به loopback برای کاهش latency صندوق با Poll fallback.
- readiness مشترک، retire Agent سابقه‌دار، validation تراکنشی مقصد و شمارش صحیح مشکلات باز.
- Template origin/revision/hash، حذف/جایگزینی اتمیک و preview دقیق از Renderer محلی.
- test print نوع‌دار customer/preparation و migration رسمی از dev.15.
- آزمون سخت‌افزار واقعی و soak همچنان UAT_REQUIRED است.

## 1.36.4-dev.15
- Print API v4: سازگاری Accept با `local_receipt_id` واقعی Agent 6.0/6.1 (`r-<32 hex>`).
- Error Codeهای Accept برای Receipt syntax، SHA syntax و Hash mismatch تفکیک شدند.
- Regression gate واقعی Agent receipt اضافه شد.
- Migration یک‌باره Print Test Jobs/Attempts/Claims برای Retest تمیز پس از defect قبلی.

## 1.36.4-dev.14
- Print Reliability: حذف امن مقصد آماده‌سازی سفارشی بدون history، نمایش FIFO blocker، Failover واقعی Primary/Fallback در Print API v4.
- Migration یک‌باره پاکسازی Print Test `print_jobs/print_attempts/print_claim_requests` با حفظ Agents/Destinations/Templates.
- Operator Bill Edit: Stepper final/prepared روی Owner استاندارد واحد Consolidate شد.

## 1.36.4-dev.13
- Compact Order Review به‌صورت Behavior-preserving: کاهش whitespace و Typography ثانویه با حفظ Stepper 44px.
- خلاصه بیرون‌بر به `N عدد بیرون‌بر` و Live Status دسترس‌پذیر تبدیل شد.
- Focus کیبورد کنترل تعداد پس از rerender حفظ می‌شود؛ Touch focus contract دست‌نخورده است.
- Fulfillment read selectors pure شدند و mutation در `clamp()` متمرکز ماند.
- CSS Footer شیت بیرون‌بر Consolidate و override unconditional حذف شد.
- بدون Migration دیتابیس و بدون تغییر Business Logic مالی/سفارش.

## 1.36.4-dev.12
- انتقال Guest/Public/Table Menu به Owner نهایی `/menu` بدون Redesign.
- Consolidation روی `menu.php` + `assets/js/menu.js` و حذف `menu-preview.js`.
- حفظ فراخوان گارسون عمومی با انتخاب میز و server-side validation/ownership.
- انتقال QR/canonical/sitemap به `/menu` و noindex برای table-token/invalid-token.
- `/` به Staff/Login gateway موجود تبدیل شد؛ بدون Legacy redirect.
- Migration تنظیمات/message keys از Preview semantics به Public semantics.

## 1.36.4-dev.11 — Operational readiness / itemized mobile polish

- Itemized mobile: Scrollbar بصری حذف شد بدون حذف Scroll لمسی؛ Stepper ظاهر جمع‌وجورتر با Hit Area 44px؛ Action «افزودن قلم جاافتاده» صریح شد.
- Success Notice پرداخت جزئی و قلم جاافتاده پس از حدود 2.6 ثانیه Collapse می‌شود؛ Warningها Auto-hide نمی‌شوند.
- Browser Contract این رفتارها را به‌صورت واقعی قفل می‌کند.
- Operational readiness review: Dev/Release blocker suites + supplemental operational flows مرور شدند؛ UATهای DB/HTTP/Print/Device جدا نگه داشته شدند.
- Test governance: تست Autofocus قدیمی Media Picker به‌عنوان Known Historical Failure مستند شد، نه Regression جاری.

## 1.36.4-dev.10 — Stabilization / owner consolidation
- حذف recent patch stacking و همگام‌سازی Ownerهای Table/Invoice/Quick Order/Settlement.
- رفع First-Paint flash در `open_table` با Startup Intent ریشه‌ای.
- Feedback تسویه ساختاری و غیرتکراری؛ touch focus modality اصلاح شد.
- بدون Migration دیتابیس.

## 1.36.4-dev.9 — Mobile regression closure + itemized feedback cleanup
- نمای کلی میزها در موبایل دوباره از Select دسکتاپ جدا شد؛ فیلتر و مرتب‌سازی مستقیم یک‌لمسی در 360/390/412 بازگشت و Desktop select حفظ شد.
- تسویه آیتمی پس از پرداخت جزئی دیگر Toast مالی تکراری نشان نمی‌دهد؛ Success feedback فقط داخل همان Sheet می‌ماند و مانده فقط در Summary نمایش داده می‌شود.
- CTA انتخاب آیتم از «قلم» به «عدد» اصلاح شد تا تعداد واحد با تعداد نوع کالا اشتباه نشود.
- Stepper در مقدار حداکثر Geometry ثابت دارد؛ + غیرفعال کم‌رنگ می‌ماند و ناپدید نمی‌شود.
- Resume قلم جاافتاده فقط پیام کوتاه «قلم جاافتاده ثبت شد» نشان می‌دهد و Toast عمومی/پیام تکراری فاکتور در این Flow حذف شد.
- Browser contract مستقل برای Overview موبایل اضافه شد تا Select دسکتاپ دوباره به موبایل نشت نکند.

## 1.36.4-dev.8 — Mobile cashier + accommodation contract hardening
- Presentation موبایل حساب میز به Baseline تأییدشده برگشت و تغییرات Desktop از Mobile scope جدا شد؛ CTA مبلغ را در نقطه تصمیم نمایش می‌دهد و اطلاعات مالی تکراری حذف شد.
- تسویه آیتمی موبایل به Sheet فشرده با Summary غیرتکراری، Stepper لمسی و CTA شامل تعداد/مبلغ Server-reviewed تبدیل شد؛ پس از شروع Itemized، دکمه تغییر روش تسویه حذف می‌شود.
- حالت «قلم جاافتاده» فقط همان Flow استثنایی را فشرده می‌کند؛ Search واضح است و Empty Cart chrome نمایش داده نمی‌شود؛ Quick Order عادی موبایل تغییر نکرد.
- Accommodation API پاسخ‌های مالی را بر اساس Contract House طبقه‌بندی می‌کند: `schema_not_ready` و `temporary_failure` دیگر صرف HTTP 503 به Pending مبهم تبدیل نمی‌شوند؛ Timeout/invalid response همچنان fail-safe و ambiguous باقی می‌مانند.
- `tracking_id` House از Payload/Header تا Result، Audit، خطای قابل نمایش و API اپراتور منتقل می‌شود؛ Token یا Secret وارد Log نمی‌شود.
- CSS موبایل Preview/overrideهای چندلایه Consolidate شد و Ownerهای نهایی Responsive جایگزین patch stacking شدند.
- Quick Order Desktop: Proxy منوی «پاک‌کردن سبد» با Clear/Undo Owner واحد همگام شد؛ پس از حذف، متن/آیکن «بازگردانی حذف سبد» واقعاً در همان Overflow دیده می‌شود و Regression Gate به UI جاری منتقل شد.
- این Build همچنان Pre-Operational test checkpoint است و Device/Print/DB/House Charge UAT روی محیط واقعی لازم است.

## 1.36.4-dev.7 — Cashier workflow test checkpoint
- Desktop Tables/Invoice و Quick Order بر اساس بررسی چندنقشی به Layout عملیاتی فشرده و خوانا منتقل شدند؛ موبایل Quick Order حفظ شد.
- پس از Quick Order، همان میز دوباره باز و سفارش تازه مشخص می‌شود؛ قلم جاافتاده نیز به همان Settlement برمی‌گردد.
- تسویه آیتمی از دید کاربر به انتخاب → ثبت پرداخت کاهش یافت؛ Review و Signature سمت سرور حفظ شده و پرداخت‌های بعدی بدون خروج از Modal ادامه دارند.
- Accommodation و Subscriber به Guard واحد `settlement_account_state_locked`/`settlement_assert_expected_account` منتقل شدند؛ Guard قدیمی invoice-only حذف شد تا False Conflict ناشی از paid_state رخ ندهد.
- Test drift مربوط به UI جدید و Contractهای Order Context/Settlement/Signature به‌روزرسانی شد.
- این Build فقط checkpoint تست Pre-Operational است؛ Charge واقعی API اقامتگاه، Print Agent و Device/DB UAT همچنان باید روی محیط تست اجرا شوند.

## 1.36.4-dev.6 — Panel tab language
- الگوی Operator به‌عنوان Primary Tabs استاندارد پنل ثبت شد.
- Settings و Printing به Primary Tabs مشترک منتقل شدند.
- Secondary Navigation یکدست اما کم‌تأکید باقی ماند.
- Filterها و Segmented Controlها عمداً از تب اصلی متمایز ماندند.
- Semantic debt گروه‌های Messages اصلاح شد: فیلتر هستند، نه Tab.
- هیچ منطق عملیاتی یا مالی تغییر نکرد.

## 1.36.4-dev.5 — Desktop invoice review density

- پنل حساب میز در دسکتاپ هنگام باز بودن فاکتور سهم بیشتری از عرض می‌گیرد تا نام اقلام کمتر Wrap شود.
- Header، فاصله‌های داخلی، ردیف‌های فاکتور و Disclosureهای بسته در دسکتاپ فشرده‌تر شده‌اند؛ موبایل و Touch Targetها تغییر نکرده‌اند.
- Footer دسکتاپ از دو/سه ردیف به یک نوار فشرده تبدیل شده تا ارتفاع بیشتری برای مرور فاکتور آزاد شود.
- هدف: فاکتورهای معمول و متوسط بدون اسکرول داخلی قابل کنترل باشند و اسکرول فقط برای حساب‌های واقعاً بلند باقی بماند.
- هیچ منطق مالی، Settlement، Inventory، Print یا رفتار موبایل تغییر نکرده است.


## 1.36.4-dev.4 — Cashier itemized-settlement UI polish
- فشرده‌سازی کارت‌های انتخاب قلم و حفظ Touch Target حداقل 44px.
- نمایش خلاصه تعداد و مبلغ انتخاب‌شده و برجسته‌سازی ظریف اقلام انتخاب‌شده.
- جلوگیری از هم‌پوشانی Footer با انتهای فهرست در Modal پرداخت جداگانه.
- تبدیل پیام‌های توضیحی قفل به وضعیت کوتاه «تسویه جداگانه فعال».
- تغییر CTA پس از پرداخت جزئی به «پرداخت مانده … تومان».
- تمام‌عرض شدن «افزودن قلم جاافتاده» به‌عنوان Secondary Action.
- بدون تغییر در Settlement/Allocation/Inventory/COGS یا قواعد قفل مالی.

# تغییرات Sokna Cafe

## 1.36.4-dev.15
- Print API v4: سازگاری Accept با `local_receipt_id` واقعی Agent 6.0/6.1 (`r-<32 hex>`).
- Error Codeهای Accept برای Receipt syntax، SHA syntax و Hash mismatch تفکیک شدند.
- Regression gate واقعی Agent receipt اضافه شد.
- Migration یک‌باره Print Test Jobs/Attempts/Claims برای Retest تمیز پس از defect قبلی.

## 1.36.4-dev.4 — 2026-08-28 — قلم جاافتاده در تسویه آیتمی + Clean Install

- پس از شروع تسویه آیتمی، Quick Order معمولی همچنان قفل است؛ صندوق‌دار فقط مسیر کنترل‌شده «افزودن قلم جاافتاده» را برای قلمی که قبلاً سرو شده ولی ثبت نشده باز می‌کند.
- میز و نشست در این مسیر ثابت‌اند، مقصد سرو داخل کافه است و ثبت جدید به بار/آشپزخانه چاپ یا Push نمی‌شود.
- قلم جاافتاده به‌عنوان سفارش حساب‌شده ثبت می‌شود تا فروش، COGS و موجودی واقعی اصلاح شوند؛ پرداخت‌های قبلی و اقلام قبلی بازنویسی نمی‌شوند.
- تخفیف‌های قبلی دست‌نخورده می‌مانند و سهم باقی‌مانده تخفیف بر مبنای جمع جدید در پرداخت‌های بعدی جذب می‌شود.
- Audit اختصاصی `order.late_accounting_created` و تفکیک Draft/Retry این مسیر از Quick Order معمولی اضافه شد.
- بسته Delta dev.2→dev.3 و Clean Install کامل dev.3 تولید می‌شوند.

## 1.36.4-dev.2 — 2026-08-28 — تسویه آیتمی میز

- مدل Finance-owned `settlement_record_lines` برای تخصیص دقیق اقلام به هر رسید اضافه شد.
- همه تسویه‌های جدید از Allocation Model واحد عبور می‌کنند؛ مسیر مالی دوم ساخته نشده است.
- پرداخت جزئی، مانده حساب، سهم نسبتی تخفیف، Idempotency/Fingerprint و قفل Concurrent اضافه شد.
- چاپ و چاپ مجدد بر اساس `settlement_id` دقیق اصلاح شد.
- برگشت رسید آیتمی فقط همان خطوط را آزاد می‌کند و در صورت نیاز همان نشست اصلی را باز می‌کند.
- Analytics و COGS برای اسناد جدید از خطوط دقیق تسویه خوانده می‌شوند.
- UI صندوق «پرداخت جداگانه» با انتخاب تعداد، Review و نمایش جمع/پرداخت‌شده/مانده اضافه شد.
- بعد از اولین پرداخت جزئی، تغییرات حساب و مقصدهای غیرمستقیم تا پایان مانده قفل می‌شوند.
- تست‌های Allocation از 95 به 100 Unit Check افزایش یافتند.


## 1.36.4-dev.1 — Pre-Feature Test Checkpoint
- این Build توسعه‌ای فقط برای ارتقای نصب تستی `1.36.3` به Baseline پاک‌سازی‌شده پیش از توسعه قابلیت بعدی ساخته شده است.
- شامل Root-Cause Cleanupهای Batch A/B، رفع Baseline Browser Contractها و جداسازی Ownerهای کم‌ریسک از `includes/functions.php` است.
- UI/Behavior Preservation Contract فعال است؛ Redesign یا تغییر Flow عمدی در این Build انجام نشده است.
- Update رسمی این Build فقط `1.36.3 → 1.36.4-dev.1` است و Migration یک‌باره برای Canonical Schema اجرا می‌شود.
- داده‌های Legacy فاقد Business Snapshot در Migration با Business Date مبتنی بر Cutoff جاری و Shift=`outside` علامت‌گذاری می‌شوند؛ این مسیر فقط برای داده‌های آزمایشی Pre-Operational است.

## Unreleased — Pre-Operational root-cause cleanup
- **Batch B اجرا شد:** هویت میز، Snapshot زمان عملیاتی و شمارش باز انبار از Compatibility Model قدیمی به Contract canonical منتقل شدند.
- `cafe_tables.table_number` و چهار Snapshot عملیاتی در چهار جدول Owner در Clean Schema `NOT NULL` شدند؛ fallbackهای Legacy به `started_at/settled_at` حذف شدند.
- Inventory به یک Open Count Owner ساده شد؛ شاخه UI/API چند Draft قدیمی حذف و Mutex/Transaction موجود حفظ شد.
- Schema Health اکنون Nullability این Invariantهای Batch B را روی DB واقعی read-only بررسی می‌کند و Schema قدیمی را ساکت قبول نمی‌کند.
- هیچ CSS در Batch B تغییر نکرد؛ Browserهای Tables/Inventory/Guest/Jalali/Navigation/UI Conformance و Contractهای مرتبط PASS ماندند.
- مرجع اجرای Batch B: `docs/BATCH_B_ROOT_CAUSE_CLEANUP_1.36.3_FA.md`.
- قرارداد رسمی `docs/UI_BEHAVIOR_PRESERVATION_CONTRACT_FA.md` فعال است؛ Refactorهای Pre-Operational به‌صورت پیش‌فرض UI/Behavior-preserving هستند و Redesign/Behavior Change نیازمند Approval صریح است.
- Audit رسمی `docs/PREOP_ROOT_CAUSE_AUDIT_1.36.3_FA.md` مبنای Cleanup قبل از Go-Live است.
- **Batch A اجرا شد:** `admin/operator.php` حذف و تمام Call Siteهای روزانه به `operator/index.php` canonical منتقل شدند؛ ساختار و چیدمان صفحه Operator تغییر نکرد.
- Route صرفاً سازگاری `staff/accommodation_charge.php` و Helper مرده `sokna_center_sign_payload()` حذف شدند.
- Contract خرید از عبارت‌های قدیمی UI جدا و به Form/Domain owner واقعی متصل شد.
- API اصلاح تعداد سفارش فقط `prepared_removed_quantity` canonical را می‌پذیرد؛ shim بولی `prepared` و ستون بلااستفاده `prepared_before_adjustment` از Clean Schema حذف شدند.
- Gate جدید `tests/v1363-batch-a-root-cleanup.py` اضافه و همراه `tests/refactor-preservation-policy-contract.py` وارد Dev/Release Gate شد.
- PHP lint، JS syntax، Unit و Contractهای مرتبط PASS شدند؛ Browserهای مستقیم Operator و Quick Order نیز PASS شدند.
- دو Baseline Browser defect مستقل بعد از Batch A ریشه‌یابی شدند: `guest-1276-browser.py` به‌جای Owner واقعی کارت روی متن زیر دکمه کلیک می‌کرد و به Owner canonical یعنی `[data-open-item-detail]` منتقل شد؛ Runtime مهمان برای این مورد تغییر نکرد.
- Jalali accessibility contract اصلاح شد: Input و دکمه تقویم هر دو `aria-haspopup`/`aria-expanded` متناظر با Picker مشترک را اعلام می‌کنند؛ Geometry، Layout، input validation و Flow انتخاب تاریخ تغییر نکرد.
- پس از این اصلاح‌ها تمام Browser Gateهای جاری Dev Gate به‌صورت مستقل PASS شدند؛ PHP lint 155/155، JS syntax 36/36 و Unit 95/95 نیز سبز ماندند.
- وضعیت سامانه همچنان `PRE-OPERATIONAL` است؛ Legacy بدون Requirement واقعی الزام به حفظ ندارد و Patch Stacking ممنوع است.

## 1.36.3 — Final test release
- چاپ و پرینترها: صفحه مدیریتی از Wizard دائمی به Operational Console با سه سطح «نمای کلی / تنظیمات چاپ / عیب‌یابی» تبدیل شد؛ Incident و وضعیت عملیاتی بر Configuration مقدم‌اند.
- چاپ: Agent پیشنهادی سامانه به `6.1.0` pin شد و دانلود فقط به GitHub Release نسخه‌دار `v6.1.0` وصل است؛ Minimum سازگار همچنان `6.0.0` است و Print API روی Protocol v4 باقی مانده است.
- چاپ: مقصدها در حالت عادی Summary خواندنی دارند و Edit-on-demand هستند؛ تنظیمات پیشرفته داخل «تنظیمات چاپ» قرار گرفت و Diagnostics فنی از عملیات روزمره جدا شد.
- Quick Order: `flex-grow` ناخواسته Stepper بیرون‌بر در Mobile/Tablet از Root حذف شد تا کادر فقط به اندازه کنترل `− / مقدار / +` باشد.
- خرید: Action «اشتراک فهرست در حال خرید» به آیکن مرکزی Share + متن کوتاه «اشتراک» تبدیل و به‌صورت Secondary compact نگه داشته شد.
- مسیر Update رسمی این Release فقط `1.36.2 → 1.36.3` است و Migration دیتابیس ندارد.

## 1.36.2 — Final release
- مرور سفارش مهمان: بیرون‌بر با تفکیک بصری ملایم و Order Note فشرده شد و Owner تکراری CSS حذف شد.
- Quick Order: در ویرایش بیرون‌بر تعداد کل Read-only شد، فقط تعداد بیرون‌بر قابل تغییر است و RTL/Tablet geometry اصلاح شد.
- خرید: Card-in-Card و ارتفاع اجباری فهرست «در انتظار خرید» از Root حذف و ردیف‌ها content-driven شدند.
- Print API v4: Health diagnostics اختیاری Agent در همان `health_json` و پنل چاپ اضافه شد، بدون Migration یا State transition جدید.
- Contract چندقلمی Supply از Copy قدیمی UI جدا و به ساختار واقعی Form/JS/processing متصل شد.
- مسیر Update رسمی این Release فقط `1.36.1 → 1.36.2` است و Migration دیتابیس ندارد.

## 1.36.1 — Final release
- اقدام سریع فراخوان در Notification از «پذیرفتم» به «رسیدگی شد» تبدیل شد؛ یک لمس اکنون فراخوان را اتمیک به `done` می‌برد، `active_table_guard` را آزاد می‌کند و Audit تکمیل ثبت می‌شود، بنابراین فراخوان در فهرست باز باقی نمی‌ماند. شناسه فنی Action برای سازگاری Service Workerهای نصب‌شده حفظ شده است.
- شیت موبایل ابزارهای سه‌نقطه به Owner مشترک `CafeUI.bindSwipeDismiss` متصل شد و اکنون با کشیدن دستگیره به پایین بسته می‌شود؛ ژست فقط در حالت باز و Mobile فعال است.
- به‌روزرسانی وضعیت «اعلان این دستگاه» فقط Label داخلی را تغییر می‌دهد و دیگر با جایگزینی کل محتوای دکمه، آیکن گزینه را حذف نمی‌کند.
- فهرست «در انتظار خرید» در موبایل از کارت‌های تو‌در‌تو و پرPadding به یک List متراکم با Divider تبدیل شد؛ Header و «شروع خرید همه» در یک ردیف قرار گرفتند و Action هر قلم دیگر تمام عرض را اشغال نمی‌کند.
- ردیف سبد Quick Order که هم‌زمان استپر تعداد و استپر بیرون‌بر دارد، روی موبایل اطلاعات قلم را تمام‌عرض و کنترل‌ها را در ردیف بعد نمایش می‌دهد؛ کنترل‌ها در عرض‌های بسیار کم Wrap می‌شوند و دیگر نام و قیمت را به ستون چندپیکسلی تبدیل نمی‌کنند.
- Workflow خرید، Confirm حذف، داده‌ها، API و ساختار Desktop تغییر نکرده‌اند. این اصلاحات همراه محتوای `1.36.1-rc.1` در نسخه نهایی `1.36.1` منتشر شدند.

## 1.36.1-rc.1 — اصلاح ریشه‌ای رابط گزارش‌شده پس از 1.36.0
- منوی سه‌نقطه سراسری و Action Menuهای ردیفی به Portal سطح `body` منتقل شدند تا `position: fixed` داخل Topbar/والدهای دارای Blur یا Transform گم نشود؛ بازگردانی DOM، Backdrop، `aria-expanded` و Focus همچنان یک Owner دارد.
- سه هندسه متداخل استپر Quick Order حذف و یک تعریف واحد ۱۳۲ پیکسلی با سه خانه ۴۴ پیکسلی و SVG با اندازه نوری ثابت جایگزین شد.
- مسیر هاردکد و بدون نسخه Sprite در منوی مهمان/پیش‌نمایش حذف شد؛ `asset()` اکنون علاوه بر نسخه انتشار، SHA-256 کوتاه محتوای CSS/JS/SVG را در URL می‌گذارد تا اصلاح هم‌نسخه از کش قدیمی سرو نشود.
- نمایش انتخاب آیکن دسته‌ها با Tile یکپارچه، وزن خط ثابت و حالت انتخاب واضح بازطراحی شد؛ Glyph دستی سه‌نقطه و Refresh باقی‌مانده نیز به Sprite مشترک منتقل شد.
- اصطلاح عملیاتی «اعلام نیاز» به «درخواست خرید» تغییر کرد؛ سطل زباله Owner حذف ردیف است، نوار ثبت موبایل دیگر روی فیلدها Overlay نمی‌شود و فهرست انتظار خرید روی دسکتاپ دو ستونه و روی موبایل تک‌ستونه است.
- Workspace چاپ و طراحی قالب روی عرض ۱۱۲۰ پیکسل محدود شد تا کارت‌ها و فرم‌ها روی مانیتور عریض کشیده نشوند.
- Contract ضدبازگشت `tests/v1361-ui-root-fixes-contract.py` اضافه شد. بسته کامل و Update رسمی این نسخه از Full Project منتشرشده `1.36.0` ساخته می‌شوند؛ Migration دیتابیس ندارد.

## 1.36.0 — Final release
- هویت Web/PWA و Cache از `1.36.0-rc.4` به `1.36.0` ارتقا یافت.
- بسته کامل نهایی و بسته Update استاندارد `1.36.0-rc.4 → 1.36.0` با Updater Engine `1.5.3` منتشر شدند.
- این Promotion هیچ Migration دیتابیس یا تغییر تازه‌ای در منطق سفارش، مالی، انبار، خرید، چاپ و فراخوان گارسون ندارد؛ Source رفتاری همان RC4 تأییدشده است.
- Release Notes، گزارش آزمون و هنداور مستقل نسخه نهایی به پروژه اضافه شدند.

## 1.36.0-rc.4 — الحاقیه نهایی رابط و آیکن‌ها
- هر ۱۰۵ آیکن رابط با حفظ شناسه‌های پایدار به خانواده واحد Tabler Icons Outline 3.46.0، شبکه ۲۴×۲۴ و ضخامت خط ۲ منتقل شد.
- ۵۶ آیکن دسته‌بندی غذا و نوشیدنی با نگاشت معنایی جدید بازطراحی شدند و Quick Order اکنون همه کلیدهای Registry را بدون حذف آیکن نمایش می‌دهد.
- علامت‌های متنی بستن، تازه‌سازی و استپرهای `+ / −` در مسیرهای اصلی مهمان، اپراتور و پنل با SVG مشترک جایگزین شدند؛ حذف ردیف درخواست خرید و حذف شیفت از آیکن سطل زباله استفاده می‌کند.
- Renderer مشترک برای آیکن‌های ساخته‌شده در JavaScript اضافه شد و تولید Sprite با `tools/build-ui-sprite.mjs` قابل تکرار است.
- مجوز MIT منبع ثبت و Contract جلوگیری از بازگشت آیکن خارج از خانواده یا Glyph متنی به Button اضافه شد.
- این الحاقیه در بسته کامل RC4 و بسته به‌روزرسانی رسمی `RC3 → RC4` منتشر شد؛ Migration دیتابیس ندارد.

## 1.36.0-rc.4 — Developer UI handoff closure
- خطای P0 ناشی از فراخوانی تابع تعریف‌نشده `csp_nonce()` در سه مسیر حذف شد و Handler تکراری حذف دلیل ردیف فاکتور به یک Owner واحد رسید.
- کارت محصول مهمان اکنون یک دکمه واقعی تمام‌سطح دارد؛ کنترل‌های تعداد مستقل مانده‌اند و هیچ دکمه تو‌در‌تو یا دکمه نمایشی «جزئیات» اضافه نشده است.
- Select سفارشی رویداد `panel:choice-commit` دارد تا انتخاب دوباره همان واحد خرید، بدون ساختن Change جعلی، مقدار را آماده ورود کند.
- Modal خرید، Image Picker، Sidebar، Cart و پس‌زمینه صفحه قرارداد Inert/Focus روشن‌تری دارند؛ Backdrop خریدِ غیرقابل‌بستن دیگر نقش Button ندارد.
- Draft چندقلمی اعلام نیاز تا ۲۰ ردیف، حفظ داده پس از خطا، تشخیص تکرار و میانبرهای اختیاری مقدار را پشتیبانی می‌کند؛ ارسال همچنان یک‌مرحله‌ای و بدون حدس مقدار است.
- شمارش موجودی فیلتر اقلام شمارش‌نشده، رفتن به قلم بعدی، Progress زنده و بازیابی موقعیت دارد. فهرست آیتم‌ها در بسته‌های ۵۰تایی صفحه‌بندی می‌شود.
- صف چاپ فیلتر وضعیت و صفحه‌بندی ۲۰تایی گرفت؛ عرض کاری چاپ و صفحات کشیده محدود شد و Sidebar در Workspaceهای تا ۱۱۸۰ پیکسل Overlay می‌شود.
- خطاهای خام ۴۰۴/۴۰۹ صفحات و ماژول‌ها به سطح بازیابی مشترک HTML/JSON با مسیر بازگشت عملیاتی منتقل شدند.
- Labelهای فرم پویا، فایل‌گیر فارسی، Date Trigger، Contrastهای گزارش‌شده و مالکیت CSS خرید اصلاح شدند.
- فایل رسمی Vazirmatn v33.003 همراه مجوز OFL داخل پروژه قرار گرفت؛ Runtime فقط در صورت حذف یا خرابی فایل، همان نسخه قفل‌شده را بازیابی می‌کند.
- Migration دیتابیس ندارد؛ منطق مالی، تسویه، فراخوان گارسون، Ledger انبار و Print reliability تغییر نکرده‌اند.

## 1.36.0-rc.3 — UI/UX conformance & interaction hardening
- ممیزی سراسری رابط روی ۵۷ ورودی UI در ۱۳ خانواده صفحه انجام شد و Registry رسمی برای جلوگیری از جاافتادن صفحه‌های جدید اضافه شد.
- Action Menuهای موبایل به Task Sheet استاندارد با Backdrop، Scroll lock، Focus recovery و هماهنگی با Bannerها تبدیل شدند؛ Desktop همچنان Popover متصل به Trigger دارد.
- Select/Chevron در RTL، Sticky actionهای موبایل، Scroll containment، Touch targetهای ۴۴px و Optical centering عددهای دو رقمی اصلاح و با Browser geometry روی 320/390/412 قفل شدند.
- Confirmهای حساس از متن عمومی خارج شدند؛ عنوان، فعل و رنگ Action اکنون متناسب با عملیات واقعی است و Action مخرب با حالت Danger نمایش داده می‌شود.
- زبان خرید/انبار/چاپ/اعلان/تنظیمات از اصطلاحات فنی یا مبهم به زبان عملیاتی یکدست شد؛ Help Center نیز با Workflowهای اختیاری Inventory/Supply و وضعیت‌های جدید هم‌راستا شد.
- سبک آیکن A (Warm Rounded) به سیستم 24×24/currentColor منتقل شد؛ ۱۰۵ Symbol یکتا و Taxonomy قابل‌توسعه برای ۵۶ دسته فعلی/آتی منو با Fallback Registry اضافه شد.
- Autofocus خام و Focus chaining ناخواسته روی Touch در فرم‌های مدیریتی، خرید و انبار مهار شد؛ Keyboard فقط با اقدام صریح کاربر باز می‌شود.
- تست‌های قدیمی که Copy/Geometry منسوخ را قفل کرده بودند به Contract جاری منتقل شدند؛ یک Race در Harness تست PWA نیز به انتظار وضعیت واقعی Transition اصلاح شد.
- Migration جدید ندارد؛ Business logic مالی، Inventory ledger، Print v4 و Module boundaries تغییر معماری نکرده‌اند.

## 1.36.0-rc.2 — Security & operational RC hardening
- Quick Order موبایل: خلاصه مالی «جمع سفارش جدید / مانده فعلی / جمع حساب پس از ثبت» از Scroll body خارج و همراه CTA در Footer ثابت شد؛ Scrollbar پررنگ موبایل نیز بدون حذف قابلیت اسکرول مخفی شد.
- ورود اصلی کافه Rate Limit سبک و مستقل گرفت: پنجره ۱۰ دقیقه، سقف IP+حساب و سقف کلی IP، بدون ذخیره نام کاربری/رمز و بدون قفل دائمی.
- Runtime storage از اولین Bootstrap با Guard وب محافظت می‌شود تا Session، وضعیت Updater و Backup به‌صورت مستقیم از HTTP قابل دریافت نباشند.
- Metric API روش HTTP را صریحاً به POST محدود می‌کند و Boundaryهای CSRF/Token در Route Security Contract قفل شدند.
- Module Manager پیش از Disable، Blockerهای عملیاتی وابسته را نمایش می‌دهد؛ برای خرید «در حال تهیه» لینک مستقیم تعیین تکلیف می‌دهد و Backend locked guard همچنان مرجع نهایی است.
- متن‌های فنی Worker/Recipe از UI مدیر حذف و به زبان عملیاتی فارسی تبدیل شدند.
- Feature Freeze حفظ شد؛ RC2 Feature جدید ندارد و فقط Security/Operational bugfix و Release hardening است.

## 1.36.0-rc.1 — Optional operations modules & pre-release freeze
- Blocker ورود «پرسنل و حقوق» رفع شد: Probe/Entitlement مرکز سکنا دیگر پیش‌شرط ساخت Handoff نیست؛ خود Center همچنان مرجع نهایی Authorization است و خرابی Probe فقط روی Hint/Diagnostics اثر می‌گذارد.
- `Inventory` به Module واقعاً اختیاری تبدیل شد؛ خاموشی آن Orders، Finance و Printing را متوقف نمی‌کند و Hookهای مصرف فروش/Worker/APIهای انبار Fail-safe به No-op یا Route gate تبدیل می‌شوند.
- `Supply` نیز اختیاری و وابسته رسمی به Inventory شد؛ `Inventory ON + Supply OFF` مجاز است، `Supply ON` بدون Inventory آماده Block می‌شود و خاموش‌کردن Inventory، Supply را در همان Transaction غیرفعال می‌کند.
- خاموش‌کردن Inventory در صورت Event مصرف unresolved، شمارش باز یا خرید «در حال تهیه» Block می‌شود تا کار نیمه‌تمام پنهان نشود.
- پس از خاموشی یک Inventory راه‌اندازی‌شده، فعال‌سازی مجدد موجودی قبلی را قابل اعتماد فرض نمی‌کند؛ یک شمارش کامل اجباری است و تا پایان آن مصرف خودکار، Low-stock/Profit وابسته و Supply متوقف می‌مانند.
- Permissionهای قبلی Inventory هنگام خاموشی Module حذف نمی‌شوند؛ فقط از UI اعضای تیم پنهان می‌شوند و در Re-enable قابل بازیابی‌اند.
- Module Manager اکنون پنج قابلیت واقعی `Inventory / Supply / Marketing / Reporting / Personnel` را با stateهای configured/runtime-ready/dependency نمایش می‌دهد.
- Race بین خاموش‌کردن Inventory و enqueue/process رویداد مصرف با Lock مشترک Module state بسته شد؛ سفارش نمی‌تواند Eventی را پس از Safety Check غیرفعال‌سازی مخفیانه وارد صف کند.
- Release Gateهای قدیمی Accommodation و responsibility با Boundaryهای جاری هم‌راستا شدند؛ invariantهای مالی/Recovery ضعیف نشده‌اند.
- Feature Freeze آغاز شد: تا پایان UAT فقط Bugfix/Release-hardening مجاز است.

## 1.36.0-dev.9 — Pre-RC schema health & updater observability
- Health Check مستقل Updater اکنون Contract خواندنی Schema جاری را نیز اجرا می‌کند؛ برای dev.9 وجود `settlement_records.request_id` و Unique Index آن به‌صورت Fail-closed بررسی می‌شود.
- نتیجه اعمال Migration و وضعیت Schema حیاتی در کارت پایان به‌روزرسانی شفاف نمایش داده می‌شود؛ «سامانه سالم است» دیگر فقط به وجود چند Table عمومی متکی نیست.
- شماره نسخه‌های Dev روی موبایل در یک خط نگه داشته می‌شوند و متن Restore Point در UI فارسی‌سازی شد.
- تست `v1360-pre-rc-schema-health.py` اضافه شد تا این Health Contract و Observability در Release Gate باقی بماند.

# Sokna Cafe 1.36.0-dev.8

## Settlement race / retry integrity hardening
- تسویه دیگر فقط مبلغ لحظه‌ای Server را قبول نمی‌کند؛ صندوق‌دار `session_id`، مبلغ نهایی و Signature همان ریزفاکتوری را که دیده است همراه درخواست می‌فرستد و Backend داخل Transaction همان Snapshot را دوباره تطبیق می‌دهد.
- اگر سفارش، تخفیف یا ردیف‌های حساب بعد از بازشدن پنجره تسویه تغییر کرده باشند، عملیات با `settlement_changed` متوقف می‌شود و کاربر باید حساب جدید را دوباره ببیند و تأیید کند؛ مبلغ جدید بی‌صدا تسویه نمی‌شود.
- `settlement_records.request_id` با Unique Index اضافه شد تا نتیجه تسویه مستقیم/مشترک پس از Timeout یا Retry با هویت مالی پایدار قابل بازیابی باشد.
- تسویه مشترک علاوه بر Settlement، Ledger را نیز با Idempotency Key همان Request قفل می‌کند تا Retry نتواند بدهی را دوباره ثبت کند.
- Unknown-result در Direct/Subscriber دیگر از «آزاد شدن میز» نتیجه‌گیری نمی‌شود؛ Client فقط با `request_id` دقیق و تطبیق `table_id + destination` موفقیت را می‌پذیرد.
- ثبت اقامتگاه نیز قبل از ساخت/ارسال Transfer، Snapshot مرورشده حساب را دوباره تطبیق می‌دهد؛ State machine و شناسه یکتای Session اقامتگاه برای Retry/ambiguity موجود حفظ شده است.
- Autofocus جست‌وجوی مشترک/اقامتگاه و دلیل ابطال روی Touch حذف شد؛ Desktop همچنان Focus سریع دارد.
- Contract جدید `v1360-settlement-race-hardening.py` این کلاس خطاهای مالی را Gate می‌کند.

## Financial & operational UI/workflow hardening
- صفحه «خرید» از Hero/Card توضیحی جدا شد و مثل صفحات عملیاتی انبار یک Header فشرده دارد؛ Actionهای «اعلام نیاز» و «اشتراک در حال تهیه» بدون فضای مرده در دسترس‌اند.
- «آخرین تحویل‌ها» در خرید برای کاربر دارای مجوز اصلاح، Whole-row navigation دارد و مستقیماً به اصلاح همان Inventory Movement می‌رود.
- Context مسیر خرید هنگام اصلاح حفظ می‌شود؛ ذخیره یا انصراف از اصلاح خرید دوباره به صفحه «خرید» برمی‌گردد و کاربر به گردش انبار پرت نمی‌شود.
- گردش‌های قابل اصلاح انبار به‌جای دکمه کوچک «اصلاح»، کل ردیف را به Target قابل کلیک/Keyboard تبدیل می‌کنند؛ ردیف‌های read-only همچنان بدون Action جعلی باقی می‌مانند.
- Autofocus اولیه فرم‌های انبار روی Mobile/Touch متوقف شد تا Keyboard قبل از دیدن Context فرم باز نشود؛ Autofocus دسکتاپ و Focus صریح پس از انتخاب کاربر حفظ شده است.
- متن فنی «Audit» از صفحه فعالیت کاربران حذف و به زبان مدیریتی قابل‌فهم تبدیل شد.
- Contract جدید `v1360-operational-ui-workflow.py` این کلاس خطاها را برای Mobile/Operational UI قفل می‌کند.

## Accommodation boundary hardening
- کنترل `accommodation_connection_enabled` اکنون فقط **عملیات زنده اتصال** را روشن/خاموش می‌کند؛ History مالی و Recovery دیگر با خاموشی اتصال پنهان یا مسدود نمی‌شوند.
- وضعیت‌های `posted` بدون سند تسویه محلی و `voided` بدون سند برگشتی محلی به صف `نیازمند رسیدگی` اضافه شدند.
- بازیابی `تکمیل تسویه کافه` و `تکمیل سند برگشتی` محلی، idempotent و مستقل از اتصال زنده است.
- Retry/Charge/Void تازه همچنان Fail-closed هستند و بدون اتصال زنده هیچ تماس Remote انجام نمی‌شود.
- Polling پنل اپراتور تغییر وضعیت انتقال یا روشن/خاموش‌شدن اتصال اقامتگاه را داخل Revision خود می‌بیند تا Recovery بدون Refresh دستی stale نماند.
- Lock ویرایش فاکتور در خطای Persistence به‌صورت Fail-closed باقی می‌ماند.
- نتیجه معماری: `Accommodation` Module Toggle عمومی نمی‌گیرد؛ مدل درست آن **Integration با Connection Control + Financial Memory/Recovery همیشه‌فعال** است.

## Personnel integration module toggle
- پس از Audit مقایسه‌ای، `Personnel` به‌جای `Accommodation` به‌عنوان Toggle سوم انتخاب شد؛ چون Personnel هیچ Table مالی محلی ندارد و خاموش‌سازی آن Order/Settlement/Inventory را درگیر نمی‌کند.
- `Personnel` اکنون با Setting مرجع `module.personnel.enabled` یک Module واقعاً `toggleable` است.
- خاموش‌کردن «پرسنل و حقوق» Launcher، Entitlement refresh، Payroll reminder و User Directory S2S را Fail-closed می‌کند و درخواست تازه‌ای به مرکز سکنا نمی‌فرستد.
- تنظیم Pairing/Secret موجود حذف یا overwrite نمی‌شود؛ پس از فعال‌سازی مجدد، اتصال قبلی قابل استفاده است.
- `admin/personnel.php` و `admin/center_settings.php` Server-side guard دارند و لینک تنظیم اتصال نیز هنگام خاموشی Module از Settings حذف می‌شود.
- `center_return.php` عمداً فعال می‌ماند تا کاربری که پیش از خاموش‌سازی به Center رفته، بتواند بدون بن‌بست به Home امن کافه برگردد.
- Help Topic پرسنل با Module state هماهنگ شد و Module Manager همچنان Registry-driven است؛ هیچ شرط اختصاصی Personnel داخل صفحه مدیریت اضافه نشد.

## قواعد حفظ‌شده
- Optional بودن به‌تنهایی Toggle ایجاد نمی‌کند؛ فقط Module با end-to-end gating کامل کلید مدیریتی می‌گیرد.
- Module disable عملیات Delete نیست.
- Core/Orders/Finance/Menu/Inventory/Supply/Printing/Notifications کلید خاموش/روشن ندارند.
- هر checkpoint قابل نصب Version یکتا دارد: `dev`, `dev.1`, `dev.2`, `dev.3`, `dev.4`, `dev.5`, `dev.6`, `dev.7`, ...


## 1.36.3 — P2 incremental owner cleanup
- `includes/functions.php` بدون Rewrite کلی به‌صورت incremental سبک‌تر شد؛ Jalali، Media، Favicon، Audit، Messages و Events به Ownerهای مستقل منتقل شدند.
- فایل shared از حدود 139KB/2783 خط پیش از P2 به حدود 86KB/1906 خط رسید؛ Order/Finance/Inventory/Capabilityها عمداً خارج از Scope این Batch ماندند.
- قرارداد include عمومی تغییر نکرد؛ `includes/functions.php` همچنان aggregator اصلی است و Route/Pageها مستقیم Ownerها را include نمی‌کنند.
- Root path وابسته به محل فایل در Media/Favicon پس از استخراج صریح و پایدار شد.
- Contractهای source-sensitive فقط به Owner جدید متصل شدند؛ UI/Behavior محصول تغییر نکرد.
- 114 تست version-stamped Audit شدند؛ Mass rename/delete رد و Semantic naming برای تست جدید ثبت شد.
- `function-owner-boundary-contract.py` برای جلوگیری از برگشت Ownerها به Shared Hotspot توسعه یافت.
- Browserهای Messages/Event/Guest/UI Conformance و Release/Core contractهای مرتبط پس از ادامه extraction PASS شدند.
