# استاندارد زنده QA و Release Gate سکنا

نسخه مرجع: 1.32.14

## اصل اصلی
هر باگی که بعد از Test سبز پیدا شود دو خروجی دارد: **Fix باگ + Fix فرآیند تستی که اجازه داده آن کلاس خطا عبور کند**. Test Suite حافظه فنی خطاهای قبلی پروژه است.

## لایه‌های آزمون
1. Syntax/Static: تمام PHP/JS، مسیرها، ownerهای تکراری، icon registry، raw errors، blind toggle.
2. Unit/Contract: Domain rules، parserها، idempotency، financial/inventory invariants.
3. Real-render: Shared PHP renderer واقعی اجرا می‌شود؛ HTML دست‌ساز جای Runtime renderer را نمی‌گیرد.
4. Browser/Geometry: 320/360/390/412 و desktop؛ gap، overflow، touch target، drawer/footer، date picker، money input.
5. Upgrade/Legacy fixtures: وضعیت‌هایی که نسخه قبلی مجاز کرده ولی نسخه جدید محدود کرده است؛ مثل multiple draft count.
6. Release artifact: Source=Full و Baseline+Update=Source byte-for-byte، manifest/hash/path/case/symlink checks.
7. Physical UAT: Android/IME واقعی، MySQL/MariaDB واقعی، Excel/Google Sheets، Center/Host واقعی. `Test not run = Not tested`.

## Defect-class Gate
Registry رسمی: `tests/defect_class_registry.json`.
کلاس‌های فعلی شامل:
- surface_spacing
- money_input / numeric_ssr
- real_render
- icon_registry
- raw_error_leakage
- blind_toggle
- jalali_geometry_swipe
- list_detail_continuity
- reversal_ledger_integrity
- js_initial_state_overwrite
- hidden_limit/completeness

هر کلاس یک Owner، نمونه Regression و Severity دارد. Release Gate باید blockerهای آن را صفر کند.

## No-skip Release Rule
در Source فعال، `tests/run-smoke.sh` به Gate جاری 1.36 هدایت می‌شود. Release Gate رسمی (`tests/run-release-gate.sh`) ماتریس Blocker جاری را اجرا می‌کند؛ تستی که فقط قرارداد منسوخ Pre-Launch را محافظت می‌کرد باید حذف یا به Contract جاری تبدیل شود. هیچ Blocker حق Silent Skip ندارد.

## فرآیند Fix
1. بازتولید باگ و ثبت Root Cause.
2. تعیین Test Gap.
3. افزودن/اصلاح Regression که قبل از Fix fail کند.
4. Fix در Shared Owner یا Domain Owner؛ نه patch موازی.
5. اجرای Regression + suite مرتبط.
6. Defect-class scan برای همان الگو در کل پنل.
7. Update docs/standards اگر قرارداد تغییر کرده است.

## Gate انتشار
Release فقط وقتی ساخته می‌شود که:
- `bash tests/run-release-gate.sh` PASS باشد یا Gate محیطی صریحاً `UAT_REQUIRED/NOT_TESTED` ثبت شده باشد.
- هیچ blocker کدی skip نشده باشد.
- Migration/Updater requirements مشخص باشند.
- Full/Update equivalence PASS باشد.
- Test Report و Go-Live Checklist وضعیت UAT واقعی را صادقانه ثبت کنند.

## یادگیری‌های 1.32.10 از UAT واقعی 1.32.9
- HTTP 500 پرونده مشترک نشان داد Static SQL contract کافی نیست. کلاس `route_db_runtime` اضافه شد: MariaDB/MySQL واقعی + HTTP احراز هویت‌شده برای Routeهای حساس در Promotion.
- ناپدیدشدن Flagهای Quick Edit نشان داد Fixture ساده‌شده می‌تواند DOM موجود ولی نامرئی را نگیرد. کلاس `real_markup_geometry` ارتفاع/Bounding Box و Markup واقعی را Gate می‌کند.
- نبود Swipe-down Quick Edit به `sheet_dismiss_contract` تبدیل شد؛ Overlayها ابتدا sheet/modal/protected-modal طبقه‌بندی می‌شوند.
- 60 گزینه دقیقه به `time_semantic_step` تبدیل شد؛ هر Consumer باید step معنایی خودش را اعلام کند.
- بیرون‌زدگی آیکن تقویم و Alignment Tileها به Geometry gate در 320/360/390/412 تبدیل شد.
- زمان‌بندی شبانه و Date-only range به `schedule_boundary_overnight` تبدیل شد.
- Push routing، URL، event-key/tag و One-tap claim به `notification_routing_integrity` تبدیل شدند.

## Environment Promotion Gates
`tests/run-release-gate.sh` تمام blockerهای قابل اجرای Build را بدون Silent Skip اجرا می‌کند. دو Gate محیطی نیز وجود دارند:
- `SOKNA_REQUIRE_DB_ROUTE_GATE=1 php tests/v13210-critical-route-db.php`
- `SOKNA_REQUIRE_HTTP_ROUTE_GATE=1 python3 tests/v13210-critical-route-http.py`

در CI/Staging دارای DB/Session این دو باید Required شوند. نبود این محیط‌ها در Build محلی **PASS نیست** و در Release Record/UAT Matrix با `UAT_REQUIRED` ثبت می‌شود.

## اصلاح فرآیند 1.32.11 — Promotion واقعاً Blocker است

در 1.32.10 یک تناقض وجود داشت: `route_db_runtime` در استاندارد Blocker تعریف شده بود ولی Release با `UAT_REQUIRED` منتشر شد. این رفتار لغو شد.

دو حالت رسمی داریم:
- **Source/Development Gate:** تست‌های قابل اجرای Source را اجرا می‌کند و نبود محیط خارجی را صریحاً UAT_REQUIRED گزارش می‌کند؛ این حالت مجوز Final Release نیست.
- **Promotion Gate:** با `SOKNA_RELEASE_PROMOTION=1 bash tests/run-release-gate.sh` اجرا می‌شود. در این حالت MariaDB/MySQL واقعی و HTTP احراز هویت‌شده Routeهای حساس اجباری‌اند و نبود هرکدام Exit non-zero است.

برای Routeهای مالی، Secondary metadata باید مستقل از Primary route تست شود. اگر Enrichment شکست خورد، صفحه اصلی باید سالم بماند؛ روی DB نسخه پشتیبانی‌شده خود Enrichment نیز باید در Promotion PASS شود.

هر ادعای UAT این دور در `GO_LIVE_CHECKLIST_FA.md` و `tests/uat_matrix.json` به فایل Owner و Test متناظر وصل می‌شود تا «ثبت در Changelog» جای Validation واقعی را نگیرد.


## اصلاح فرآیند 1.32.12 — Notification test parity
- تستی که از مسیر Direct Send عبور می‌کند، مدرک سلامت اعلان عملیاتی Outbox/Worker نیست.
- تست کاربری Web Push باید همان Queue، Worker، Recipient resolution و Delivery log عملیات واقعی را طی کند.
- Worker heartbeat stale/missing در تست Pipeline شکست محسوب می‌شود، نه هشدار فرعی.
- Transport health و Routing eligibility جدا گزارش می‌شوند؛ Admin ممکن است Push transport سالم داشته باشد ولی طبق Policy اعلان عملیات زنده نگیرد.
- هر Regression این کلاس با `tests/v13212-notification-pipeline.py` Block می‌شود.

## Gate حفظ UI/Behavior در Refactorهای Pre-Operational

هر Root-Cause Refactor که UI/Workflow را هدف نگرفته است باید طبق `UI_BEHAVIOR_PRESERVATION_CONTRACT_FA.md` Behavior-preserving باشد. تغییر مشاهده‌پذیر ناخواسته Regression است. Policy presence با `python3 tests/refactor-preservation-policy-contract.py` قفل می‌شود؛ Runtime preservation همچنان با Contract/Browser/Visual/Domain Gateهای Surface لمس‌شده سنجیده می‌شود.

## ارتقای QA بصری در UAT جاری — Visual/Layout Quality Gate
مشکل «متن‌های Worker/Queue به هم چسبیده‌اند» نشان داد Browser testهای قبلی بیشتر خرابی شدید را می‌دیدند، نه کیفیت Composition. کلاس جدید `visual_rhythm` این شکاف را می‌بندد.

### چهار لایه QA بصری
1. **Structural:** DOM، overflow، touch target، visibility و focus.
2. **Semantic Geometry/Rhythm:** فاصله واقعی Page/Card/Copy، overlap، clipping، containment و responsive composition با Bounding Box در 320/360/390/412/768/1366.
3. **Human-approved Visual Baseline:** Stateهای Critical فقط بعد از UAT انسانی Baseline می‌شوند. Auto-update برای سبزکردن Test ممنوع است.
4. **Physical UAT:** Android keyboard/swipe/OS Push/printing و مواردی که Headless browser کیفیت واقعی‌شان را تضمین نمی‌کند.

### ابزارهای زنده
- `tests/visual-layout-contracts.py`: Token/Owner/Composition scan روی کل admin؛ multi-surface page جدید بدون owner مشترک را Fail می‌کند.
- `tests/visual-quality-browser.py`: Geometry/Rhythm و Content Stress برای Componentهای مشترک؛ اولین state رسمی آن `push_devices_worker_stopped` است.
- `tests/visual_quality_baseline.json`: بدهی قدیمی Composition به‌صورت allowlist صریح؛ debt جدید Silent پذیرفته نمی‌شود.
- `tests/visual_quality_pages.json`: Inventory stateهایی که باید Baseline انسانی داشته باشند.
- `tests/visual-promotion-baselines.py`: در Source حالت `UAT_REQUIRED` و در Final Promotion تا زمان `approved` شدن Baselineها Fail.

قاعده جدید: تعداد Test به‌تنهایی معیار بلوغ QA نیست. هر escaped visual defect باید به یک **قانون عمومی قابل تعمیم** تبدیل شود؛ Test موردی فقط در کنار Owner/Contract عمومی پذیرفته است.

## یادگیری UAT جاری — تست نباید قرارداد منسوخ را محافظت کند
مشاهده «پرسنل و حقوق» برای کاربر فاقد دسترسی نشان داد مشکل فقط نبود Test نبود؛ دو Test قدیمی صریحاً رفتار اشتباه «unknown باید visible بماند» را الزام می‌کردند. از این پس هر escaped authorization/navigation defect علاوه بر Regression جدید، **خود assertionهای Legacy مرتبط را نیز از نظر تطابق با سیاست کسب‌وکار جاری Audit می‌کند**.

کلاس `navigation_entitlement_parity` الزام می‌کند:
- Nav و مقصد محافظت‌شده یک Effective Access Policy داشته باشند.
- برای Staff فقط explicit allow باعث Reveal شود.
- deny/unknown/stale/refresh failure لینک را مخفی نگه دارند.
- Direct URL همان Staff را در حالت unknown/deny عبور ندهد.
- Admin exception، اگر وجود دارد، صریح و مستقل از fallback سازگاری باشد.

قاعده جدید: **Test سبز می‌تواند خودش باگ باشد** اگر یک قرارداد Legacy منسوخ را enforce کند؛ بنابراین در تغییر سیاست دسترسی، Testهای تاریخیِ همان حوزه باید semantic review شوند، نه فقط اجرا.
## UAT جاری — Financial UI Family
- Gate ساختاری: `python3 tests/financial-ui-family-contract.py`. هر یک از فاکتورها، مشترکین، حساب اقامتگاه و دوره‌های مالی باید `financial-workspace` و Primitiveهای archive/record/history مشترک را مصرف کند.
- Gate Browser: `python3 tests/v1326-financial-browser.py` در 320/360/390/412/768/1366. Root overflow، surface gap، row containment، فیلتر موبایل، metadata odd-grid و period composition بررسی می‌شوند.
- Desktop-table + mobile-card duplication برای history مالی جدید Regression محسوب می‌شود؛ یک responsive record list ترجیح دارد.
- Visual PASS نهایی همچنان به Baseline انسانی stateهای `invoices_archive_populated`, `subscriber_directory_populated`, `subscriber_profile_populated`, `accommodation_history`, `financial_periods_populated`, `invoice_detail_populated` وابسته است.
- KPI یا Card جدید فقط با تصمیم مالی واقعی مجاز است؛ تست Contract وجود `financial-period-kpis`/`financial-period-hero` و الگوهای Legacy متناظر را رد می‌کند.
- Gate `financial-visual-hierarchy-contract.py` بررسی می‌کند visual polish از Tokenهای مشترک بیاید، Related Document واقعاً Layout owner داشته باشد، دوره جاری در History تکرار نشود و Accommodation Human reference را مقدم کند.
- Browser Gate علاوه بر overflow/rhythm، باید تفاوت واقعی Value و Muted text، gradient/tint Token-backed و containment Related Document را در عرض‌های مرجع بسنجد.

### پالایش دوم Financial UI Gate
- `financial-ui-family-contract.py` علاوه بر حضور Primitiveها، retirement شدن Owner قدیمی Invoice archive، collapse پیش‌فرض Filter موبایل، quiet healthy-state مشترکین و Owner واقعی Subscriber profile/Ledger را کنترل می‌کند.
- `v1326-financial-browser.py` اکنون Subscriber profile populated را نیز کنار Archive/Directory/Accommodation/Periods/Invoice detail در 320/360/390/412/768/1366 Render می‌کند.
- در تغییر UI مالی، بالا بردن threshold قدیمی برای سبزکردن Fixture مجاز نیست؛ Fixture ابتدا باید با Markup/Owner production هم‌راستا شود و سپس geometry outcome سنجیده شود.

## یادگیری 1.32.13 — Time presentation با canonical storage
- UI زمان برای کاربر ۱۲ساعته است، اما Source of Truth و API/DB همچنان ۲۴ساعته می‌ماند.
- `time_12h_canonical_storage` یک Blocker است: ۱۲ قبل از ظهر باید 00:00 و ۱۲ بعد از ظهر باید 12:00 Serialize شود.
- ترتیب Segment دوره ثابت است: «قبل از ظهر» فیزیکی سمت راست و «بعد از ظهر» سمت چپ.
- Grid اعداد مستقل از RTL متن، LTR است؛ ۱ و ۰۰ از سمت چپ شروع می‌شوند.
- تست Browser باید هم Presentation، هم Bounding Box/Touch، هم Conversion و هم عدم Nested Scroll را بسنجد.
