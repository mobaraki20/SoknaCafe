
## Financial cross-page quality gate — 1.32.15
- QA مالی فقط Page-local نیست. Gate باید هم‌زمان فاکتورها، مشترکین و حساب اقامتگاه را Render و Search height، Row amount typography، Surface owner، filter behavior و density را با هم مقایسه کند.
- Fixture اجازه ندارد Advanced Filter را inline ساده کند؛ باید Overlay واقعی و Bottom-sheet geometry را مدل کند.
- Ledger fixture باید Markup واقعی یک Disclosure را مدل کند؛ وجود دو Disclosure «اقلام/اطلاعات ثبت» Regression است.
- Density assertion Outcome-based است: Row بیش از حد بلند باید Fail شود حتی اگر overflow نداشته باشد.
- No-duplicate contract Source-level و Browser-level دارد: تاریخ گروهی نباید دوباره تاریخ کامل هر Row را بسازد؛ canonical id نباید در accommodation normal row ظاهر شود؛ ledger metadata ثانویه نباید تکرار شود.
- Cross-page inconsistency یک Defect Class مستقل است: سه صفحه می‌توانند جداگانه PASS باشند ولی اگر Search/Amount/Toolbar زبان متفاوت داشته باشند Release مالی PASS نیست.

## Notification self-draining outbox — UAT جاری
- تست Push نباید فقط VAPID/Subscription یا ارسال مستقیم Provider را اثبات کند؛ همان `push_event_queue` عملیاتی باید طی شود.
- Mutation سفارش/فراخوان فقط Outbox را داخل Transaction می‌نویسد. هیچ Provider call قبل از Commit یا به‌عنوان شرط success مجاز نیست.
- Immediate delivery سه مسیر دارد: FPM after-response، signed queue kick، و authenticated opportunistic drain. هر سه باید idempotent و bounded باشند.
- `tools/push-worker.php` Accelerator است؛ نبود heartbeat به‌تنهایی Failure عملیات زنده محسوب نمی‌شود.
- Actionability درست قبل از Delivery re-check می‌شود. `state_unknown` باید fail-closed باشد.
- Browser/JS contract باید response metadata `_push.kick` را بدون Block کردن Workflow اصلی مصرف کند.
- Public kick endpoint حق دریافت Payload اعلان، user id یا event type از Client را ندارد؛ فقط Queue ID + HMAC کوتاه‌عمر.
- Authenticated drain باید CSRF داشته و Batch کوچک داشته باشد؛ Requestهای عادی نباید Worker نامحدود شوند.
- Regression تست باید backlog قدیمی، duplicate kick، offline kick، expired subscription و Provider failure را پوشش دهد.

## Defect classes افزوده‌شده در 1.32.18
- `shared_reorder_contract`: exact-set + transactional reorder + keyboard/mobile geometry.
- `message_editor_scalability`: runtime consumer parity، noneditable boundaries، token guardrail، search/filter/reset/preview.
- `print_template_contract`: safe four-file package، versioning، Agent compatibility، preparation no-price boundary.
- `print_preview_parity`: 58/80 browser preview scenarios؛ Physical printer/driver همچنان UAT واقعی است.

## یادگیری 1.33.2 — پاک‌سازی Owner و Mixed Fulfillment
- `cart_component_owner`: رفع UI فقط با افزودن Override جدید مجاز نیست؛ Owner قدیمی/غیرقابل‌استفاده همان Surface باید حذف شود و Gate عدم بازگشت داشته باشد.
- `fulfillment_default_inheritance`: تصمیم پیش‌فرض بیرون‌بر از State جداگانه و stale گرفته نمی‌شود؛ از وضعیت واقعی کل Cart مشتق می‌شود.
- `guest_fulfillment_exception_parity`: Global fulfillmentها Action هستند، نه Segment انتخاب‌شده. حالت سالم «داخل کافه» نویز دائمی ندارد.
- `quick_order_cart_density`: برای Staff، نبود Overflow کافی نیست؛ ارتفاع Row عادی و تعداد رکورد قابل مشاهده بخشی از Regression geometry است.

## افزوده 1.33.3
- Back/Change Table در Quick Order باید با Cart دارای Draft در Browser واقعی تست شود؛ visible confirmation و scrollable table picker جزء Gate است.
- هر Select/DatePicker داخل Task Sheet باید ثابت کند modal/overlay دوم ایجاد نمی‌کند.
- Audit critical actions باید هم compact summary و هم expandable snapshot detail را در Browser/contract پوشش دهند.

## افزوده 1.33.4 — Guest Edit/Conflict
- تست Guest باید Race مدل‌شده `order_changed` و `order_not_editable` را پوشش دهد و ثابت کند CTA و Runtime هرگز Edit قدیمی را به سفارش تازه تبدیل نمی‌کنند.
- `edit_signature` بخشی از Draft ویرایش است و Reload/reconcile باید تست شود.
- Long order card باید progressive disclosure داشته باشد؛ بازبودن همه اقلام در normal state Regression density است.
- Cleanup endpoint/helper منسوخ باید Source-level assertion داشته باشد تا مسیر قدیمی برنگردد.
