# ثبت استانداردهای زنده سکنا

| استاندارد | Owner اصلی | تست/گیت مرجع | وضعیت 1.32.14 |
|---|---|---|---|
| Surface parent-owned gap | `.panel-surface-stack` / panel CSS | `v1329-defect-class-gate.py`, browser geometry | فعال |
| Utility Action Slot | `.panel-utility-actions`, `.panel-icon-action` | icon registry + browser | فعال |
| Financial List→Detail | financial presenters / invoices/subscribers | `v1329-financial-contracts.py` | فعال |
| Money Input | `CafeUI.money`, `data-money-input` | `v1329-defect-class-gate.py`, browser | فعال |
| Numeric SSR Persian | `numeric_input_display_value()` | defect-class gate | فعال |
| Jalali fixed 42 + swipe | `panel-jalali.js` | `v1329-panel-browser.py` | فعال |
| Progressive optional metadata | inventory form flow | contract/browser | فعال |
| Drawer header/body/footer | shared quick edit patterns | browser geometry | فعال |
| Safe UI error | `CafeUI.requestErrorMessage`, server safe errors | defect-class gate | فعال |
| Desired-state / idempotency | Domain/API owners | structural/domain tests | فعال |
| Standard-change rule | docs + tests same release | release gate | فعال |

| Composite Jalali control | `.jalali-date-control` | `v13210-panel-browser.py` | فعال |
| Selection Tile | tag/day tile owner | `v13210-panel-browser.py` | فعال |
| Dismissible Sheet Swipe | `CafeUI.bindSwipeDismiss` | `v13210-defect-class-gate.py`, browser | فعال |
| Semantic Time Step | `panel-time-picker` + consumers | `v13210-schedule-contracts.py`, browser | فعال |
| Date-only/Overnight schedule | item schedule domain | `v13210-schedule-contracts.py` | فعال |
| Notification responsibility/routing | push outbox/router/SW | `v13210-notification-contracts.py` | فعال |
| Critical route DB runtime | SQL route + staging promotion | `v13210-critical-route-db.php`, HTTP smoke | محیطی/اجباری در Promotion |
| Real-markup geometry | browser blocker fixtures | `v13210-panel-browser.py` | فعال |

قاعده: تغییر هر ردیف باید در همان Release هم سند و هم Test متناظر را تغییر دهد.

## اصلاح استاندارد 1.32.11

| استاندارد | Owner اصلی | Gate | وضعیت |
|---|---|---|---|
| Primary route + secondary enrichment | Subscriber ledger route | `v13211-escaped-defects.py` + DB/HTTP | فعال |
| Shared UI API Signature | `CafeUI` primitives | `v13211-escaped-defects.py` | فعال |
| Sheet gesture single owner | `CafeUI.bindSwipeDismiss` | source contract + browser | فعال |
| Promotion environment integrity | `run-release-gate.sh` | `SOKNA_RELEASE_PROMOTION=1` | Blocker |
| UAT claim verification | `GO_LIVE_CHECKLIST_FA.md` و `tests/uat_matrix.json` | Release review | فعال |

قاعده تکمیلی: یک Route اصلی نباید به Metadata ثانویه‌ای وابسته شود که نبود/ناسازگاری آن می‌تواند کل صفحه را 500 کند. Enrichment ثانویه باید fail-soft، loggable و قابل تست باشد. همچنین هیچ Release با برچسب Final مجاز نیست Gate محیطی Blocker را به `UAT_REQUIRED` تبدیل و همچنان PASS اعلام کند.


## افزوده 1.32.12
| استاندارد | Owner | Gate | وضعیت |
|---|---|---|---|
| Notification test parity | `waiter/api_push.php`, `includes/push.php`, `device-notifications.js` | `v13212-notification-pipeline.py` | فعال |

## افزوده UAT جاری — Visual/Layout Quality
| استاندارد | Owner | Gate | وضعیت |
|---|---|---|---|
| Semantic visual rhythm | spacing tokens + `.panel-page-flow/.panel-card-flow/.panel-copy-stack` | `visual-layout-contracts.py`, `visual-quality-browser.py` | فعال |
| Diagnostic composition | `.panel-diagnostic-grid` | `visual-quality-browser.py` | فعال |
| Composition debt baseline | `visual_quality_baseline.json` | `visual-layout-contracts.py` | فعال؛ ۶ بدهی Legacy صریح |
| Human visual baseline promotion | `visual_quality_pages.json` | `visual-promotion-baselines.py` | `pending_uat` تا تأیید انسانی |

قاعده تکمیلی: `element exists`، absence of overflow یا touch-target به‌تنهایی Visual PASS نیست. Rhythm/geometry و برای stateهای Critical baseline انسانی نیز لازم است.

## افزوده UAT جاری — هم‌خوانی دسترسی و ناوبری
| استاندارد | Owner | Gate | وضعیت |
|---|---|---|---|
| Navigation entitlement parity | `panel_layout.php` + مقصد محافظت‌شده | `navigation-entitlement-contract.py`, `v1326-center-entitlement-browser.py` | فعال |

قاعده: نمایش یک ورودی ناوبری برای کاربر غیرادمین فقط با **دسترسی مؤثر و صریح همان مقصد** مجاز است. `unknown`، cache منقضی، Center ناسازگار یا خطای Refresh مجوز نیستند و باید fail-closed بمانند. استثنای مدیریتی Admin باید صریح و جدا تعریف شود؛ Permission مقصد همچنان در خود Route/Center enforce می‌شود.

## افزوده UAT جاری — Financial UI Family
| استاندارد | Owner | Gate | وضعیت |
|---|---|---|---|
| Financial archive/record/history family | `.financial-workspace` + financial primitives | `financial-ui-family-contract.py`, `v1326-financial-browser.py` | فعال |

قاعده: فاکتورها، مشترکین، حساب اقامتگاه و دوره‌های مالی حق ندارند برای موبایل/دسکتاپ دو زبان مستقل، KPIهای تزئینی یا table→card duplication جدید بسازند. تفاوت Workflow حفظ می‌شود اما hierarchy، filter density، record row و exception treatment باید از Family مشترک بیاید.

### Financial visual hierarchy 1.32.14
| استاندارد | Owner | Gate | وضعیت |
|---|---|---|---|
| Financial visual hierarchy | `.financial-workspace` local tokens + related-document/human-reference owners | `financial-visual-hierarchy-contract.py`, `v1326-financial-browser.py` | فعال |

قاعده: Primary/Accent فقط از Theme/Token موجود مشتق می‌شوند. صفحه مالی حق تعریف Palette مستقل ندارد. Human reference مقدم بر canonical id است، relation برگشت Owner مستقل دارد و context آموزشی دائمی باید تا حد ممکن disclosure شود.

### پالایش Contract مالی در UAT جاری
- Mobile archive: Search همیشه دیده می‌شود؛ Filter panel در ورود collapsed است، حتی وقتی فیلتر فعال وجود دارد. Active filter با Chip مستقل قابل Scan می‌ماند.
- Desktop archive: همان data model و vocabulary موبایل با density بیشتر نمایش داده می‌شود؛ کشیدن Cardهای موبایل به عرض دسکتاپ ممنوع است.
- Subscriber record: Header، مانده، پرداخت و Ledger باید Owner صریح داشته باشند؛ تکیه به default block-flow یا marginهای مرورگر Regression است.
- CSS archive owner باید scoped به `.financial-invoice-workspace` باشد؛ Owner unscoped قدیمی برای archive مجاز نیست.

## افزوده 1.32.13 Final
| استاندارد | Owner | Gate | وضعیت |
|---|---|---|---|
| 12h human time / 24h canonical | `panel-time-picker.js` | `v13213-time-picker-contract.py`, `v13213-time-picker-browser.py` | فعال |
| Visual rhythm / composition | spacing tokens + shared flows | `visual-layout-contracts.py`, `visual-quality-browser.py` | فعال |
| Navigation entitlement parity | Cafe nav + protected Center handoff | `navigation-entitlement-contract.py`, Center browser | فعال |
| Financial UI Family | `.financial-workspace` + primitives | `financial-ui-family-contract.py`, financial browser | فعال |

قاعده Time: Period و Grid فقط Presentation هستند؛ canonical 24h در Domain/DB تغییر نمی‌کند. هیچ صفحه‌ای حق ندارد تبدیل 12/24h جداگانه بسازد.

## افزوده 1.32.15 — Financial Composition v2
| استاندارد | Owner | Gate | وضعیت |
|---|---|---|---|
| Financial Page Shell | `.financial-page-shell/.financial-page-head/.financial-page-toolbar` | `financial-ui-family-contract.py`, `v1326-financial-browser.py` | فعال |
| No duplicate financial information | Financial presenters + ledger | `financial-visual-hierarchy-contract.py`, `financial-composition-contract.py` | فعال |
| Financial surface budget | Financial Page Shell | `financial-composition-contract.py` | فعال |
| Advanced financial filter sheet | `financial-ui.js` + `.financial-filter-layer` | `financial-composition-contract.py`, browser geometry | فعال |
| Cross-page financial consistency | shared financial primitives | `financial-composition-contract.py`, `v1326-financial-browser.py` | فعال |
| Compact subscriber ledger | `.subscriber-ledger-disclosure` | source contract + browser density | فعال |

قاعده: فاکتورها، مشترکین و حساب اقامتگاه باید در Summary/Toolbar/List یک زبان Composition داشته باشند؛ تفاوت Domain فقط در محتوای Row و Action مجاز است. اطلاعات تکراری، Surface اضافی و Metadata فنی در نمای اصلی بدهی/Regression محسوب می‌شوند.

## افزوده UAT جاری — Notification self-draining outbox
| استاندارد | Owner | Gate | وضعیت |
|---|---|---|---|
| Transactional push outbox | `includes/push.php` + Domain mutations | `push-queue.py`, `v13216-notification-hybrid.py` | فعال |
| Signed immediate queue kick | `push_response_metadata()` + `api/push_kick.php` | `v13216-notification-hybrid.py` | فعال |
| Opportunistic authenticated drain | `push-runtime.js` + `api/push_drain.php` | `v13216-notification-hybrid.py` | فعال |
| Push stale/actionability guard | `push_event_actionability()` | `v13216-notification-hybrid.py` | فعال |
| Optional independent worker | `tools/push-worker.php` | push contracts | Accelerator، نه Dependency |
| In-app operational fallback | Operator/Preparation feeds | notification hybrid gate | فعال |

قاعده: Push کانال Attention است، نه منبع حقیقت عملیات. Worker مستقل نباید شرط کارکرد روزمره سکنا باشد. هیچ Retry اجازه ندارد Event منقضی، پاسخ‌داده‌شده یا Claim‌شده را دوباره ارسال کند. Failure شبکه Push نباید Order/Call/Settlement را Rollback کند.

## افزوده 1.33.2 — Cart Owner / Fulfillment Exception Integrity
| استاندارد | Owner | Gate | وضعیت |
|---|---|---|---|
| Cart component owner | `guest-menu.css`, `quick-order.css` | `v1332-cart-owner-contract.py` | فعال |
| Fulfillment default inheritance | `menu.js`, `staff-quick-order.js` | Guest/Quick Order browser regressions | فعال |
| Guest fulfillment exception parity | Guest review presenter | `guest-1276-browser.py` | فعال |
| Quick Order cart density | Staff cart presenter | `staff-quick-order-browser.py` | فعال |

قاعده: «داخل کافه» حالت خاموش و پیش‌فرض است. فقط Exception بیرون‌بر نمایش داده می‌شود. حالت Mixed نباید به آیتم جدید Takeaway پیش‌فرض بدهد. Badge و Editor یک Exception هم‌زمان تکرار نمی‌شوند. CSS و State قدیمیِ همان Surface حق ندارد به‌صورت Override موازی باقی بماند.

## افزوده 1.33.3 — Navigation / Task Overlay / Audit Detail
| استاندارد | Owner | Gate |
|---|---|---|
| Quick Order navigation state | `staff-quick-order.js` + shared confirm | `staff-quick-order-browser.py`, `v1333-ui-contracts.py` |
| Single task overlay | `panel-choice.js` + `panel-jalali.js` | `v1333-financial-filter-browser.py` |
| Audit detail snapshot | audit writers + presenter | `v1333-activity-browser.py`, `v1333-ui-contracts.py` |

قاعده: Task sheet یک Overlay owner دارد؛ child picker حق ساخت modal/overlay دوم ندارد. Audit جزئیات تاریخی را از Snapshot زمان Action می‌گیرد، نه حدس از وضعیت زنده بعدی.

## افزوده 1.33.4 — Guest Order Edit State Integrity
| استاندارد | Owner | Gate |
|---|---|---|
| Guest order edit-state integrity | `menu.js` + `api/guest_orders.php` | `v1334-guest-order-workflow-contract.py`, Guest browser |

قاعده: Edit سفارش موجود، Append اقلام جدید و Create سفارش تازه Stateهای مستقل‌اند. Conflict یا terminal شدن سفارش هنگام Edit هرگز اجازه fall-through به create/append یا ثبت دوباره Snapshot قدیمی را ندارد.

## افزوده 1.34.0 — Small-Team Operations
- Permissionهای فنی نباید مستقیماً Interface روزمره مدیر را تشکیل دهند؛ UI تیم با پنج Responsibility Bundle اداره می‌شود.
- «انبار و خرید» عملیات روزانه است؛ هزینه انبار اختیار ویژه مستقل و عملیات حساس تابع مدیریت/سرپرستی است.
- Low Stock فقط Signal است و به‌تنهایی Purchase یا Stock Movement نمی‌سازد.
- `planned`/`deferred`/`bought` تصمیم‌های خرید هستند؛ فقط Inventory Receive موجودی را افزایش می‌دهد.
- Purchase v1 هیچ Replacement/Supplier Approval/Auto-order ندارد.
- Quick Order پس از Success مقصد ثابت میزهای عملیاتی دارد؛ Cancel/Back همچنان به مبدا برمی‌گردد.
- Embedded Jalali و Modal یک Gesture contract مشترک دارند؛ Swipe افقی ماه را تغییر می‌دهد و Drag عمودی Scroll است.
- Control ثانویه بیرون‌بر فقط در مقدار Partial باز می‌ماند و در 0/All Collapse می‌شود.
- Updater Close نباید Session را Logout کند یا با × معنای خروج از حساب بدهد.

## افزوده Print Agent Stabilization — Agent Health Diagnostics
| استاندارد | Owner | Gate | وضعیت |
|---|---|---|---|
| Agent transport health diagnostics | `print-agent/v4/api.php` + `admin/printing.php` + `.panel-diagnostic-grid` | `print-v4-agent-health-diagnostics.py`, printing browser gate | فعال در Development |

قاعده: Health/Transport diagnostic فقط Evidence عملیاتی است و حق تغییر Print Job ownership/state را ندارد. فیلدهای اختیاری Agent داخل `health_json` موجود ثبت می‌شوند؛ DB owner یا مسیر API موازی برای Monitoring ساخته نمی‌شود. UI عادی Error خام/Stack/Secret نشان نمی‌دهد و Healthy state آرام می‌ماند؛ Exception/Degraded قابل Scan است.

