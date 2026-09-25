# استاندارد Legacy UI/UX سکنا — فقط مرجع سازگاری

> **SUPERSEDED / NON-AUTHORITATIVE FOR NEW WORK**
> Design Authority فعلی و یگانه برای کار جدید و migration، `docs/ui-design-system/CANONICAL_DESIGN_SYSTEM_CONTRACT_FA.md` با شناسه `SCDS-CANONICAL-2026-R1` است.
> این فایل فقط برای حفظ contractهای legacy و provenance نگه داشته شده است؛ هیچ قاعده متعارض با SCDS canonical حق تقدم ندارد.

نسخه تاریخی مرجع: 1.36.3

این سند در نسل قبلی **مالک رسمی استاندارد رابط پنل سکنا** بود. هر تغییر در یک استاندارد UI باید در همان Release دو چیز داشته باشد: ۱) به‌روزرسانی همین سند یا Register استاندارد، ۲) Contract/Regression Test متناظر. تغییر استاندارد بدون تست پذیرفته نیست.

## 1) اصول پایه
- RTL واقعی و فارسی؛ Touch target عملیاتی حداقل `44px`.
- Primary/Confirm/Final Action در RTL سمت راست و Secondary/Cancel سمت چپ.
- در Stepper، `+` سمت راست و `−` سمت چپ و با تمایز بصری روشن.
- رنگ به‌تنهایی حامل وضعیت نیست؛ متن/نشانه لازم است.
- UI باید Mobile-first برای 320/360/390/412 باشد و سپس Tablet/Desktop.
- Guest/Quick Order/Settlement فقط با Bug یا Requirement اثبات‌شده تغییر می‌کنند.

## 2) Surface Ownership و فاصله‌ها
- Surface مستقل مالک Border/Radius خودش است؛ **Parent مالک Gap بین Surfaceهاست**.
- Parent استاندارد برای چند Surface مستقل: `.panel-surface-stack`.
- Card مستقل نباید با `margin` صفحه‌ای یا specificity patch از Card بعدی جدا شود.
- Group داخلی یک Border/Radius دارد و Childها با Divider جدا می‌شوند؛ Card-per-row برای فهرست‌های طولانی ممنوع مگر دلیل عملیاتی داشته باشد.
- هر صفحه بازبینی‌شده باید در 320/360/390/412 در حالت‌های empty/filled/error/form از نظر Gap تست شود.

## 3) Action hierarchy و Utility Slot
- Detail/Record Header: هویت سمت راست، Utility Actionها در Slot ثابت سمت چپ RTL.
- Utilityها: ویرایش، چاپ، دانلود، کپی؛ حداکثر 1–2 مورد، Touch 44×44، icon + aria-label/tooltip.
- List row: Action menu یا Drill-down؛ Utility icon برای هر ردیف تکرار نشود.
- Operational Task: Primary/Secondary button، نه utility icon.
- Destructive Action در Exception/Action menu با Confirmation؛ نه کنار action عادی.

## 4) Financial List → Detail continuity
- List و Detail یک سند باید Human Reference، وضعیت، طرف حساب، مقصد و قالب زمان یکسان داشته باشند.
- UI اصلی از Human Reference مثل `فاکتور ۴۹` استفاده می‌کند؛ Canonical ID مثل `I-1405-000049` در Metadata/Search/Audit حفظ می‌شود.
- Archive برای Scan سریع: Subject/Party + Amount مهم‌تر از ID فنی است.
- Detail: هویت سند → Context → اقلام → Total/Effect → Metadata.
- تاریخ، مبلغ نهایی، میز یا مقصد در یک Detail بی‌دلیل دوبار نمایش داده نمی‌شوند.
- وضعیت عادی در لیست‌های پرتکرار تا حد ممکن پنهان است؛ Exception مثل برگشت‌خورده باید دیده شود.

## 5) Receipt item و Metadata
- اقلام مالی در یک Surface پیوسته با Divider نمایش داده می‌شوند، نه Card مستقل.
- سطر استاندارد: نام + `تعداد × قیمت واحد` + مبلغ ردیف؛ math با Bidi isolation.
- Metadata کم‌تکرار در Disclosure فشرده است.
- Metadata grid در عرض >=380 دو ستون و در عرض‌های باریک یک ستون است.

## 6) Numeric و Money Input
- هر Numeric Input کاربرخوان از **Server** با ارقام فارسی Render می‌شود؛ JS فقط Enhancement است.
- Technical/LTR identifier/URL/code مستثناست.
- Money Input مالک مشترک `[data-money-input]` دارد: ارقام فارسی + هزارگان در UI، ASCII integer در Submit/API/DB.
- یک مبلغ نباید هم داخل Input و هم Preview جدا تکرار شود مگر Preview معنی متفاوتی داشته باشد.
- Money parser باید strict باشد؛ ورودی مخدوش به‌زور cast نمی‌شود.
- نوع کیبورد از معنای داده می‌آید، نه ظاهر فیلد: عدد صحیح/پول `inputmode=numeric`، وزن/حجم اعشاری `decimal`، تلفن `type=tel/inputmode=tel`، جست‌وجو `type=search/inputmode=search`.
- برای پول و اعداد محلی‌شده، `type=number` Owner عمومی نیست؛ `type=text + inputmode` اجازه می‌دهد نمایش فارسی/هزارگان از Validation/Canonical value جدا بماند.
- Search واقعی `enterkeyhint=search` دارد. Enter و دکمه جست‌وجو باید یک Action را اجرا کنند؛ روی touch پس از Query معتبر کیبورد بسته می‌شود تا نتایج دیده شوند، ولی Query نامعتبر Focus را حفظ می‌کند.
- در Flowهای مرحله‌ای، `enterkeyhint=next` به فیلد ورودی منطقی بعدی می‌رود. `enterkeyhint=done` روی touch فقط ورود را تمام و کیبورد را می‌بندد؛ Mutation/Financial submit بدون Contract صریح از Enter اجرا نمی‌شود.
- هیچ Keyboard handler نباید `Enter` را در `KeyboardEvent.isComposing=true` مصرف کند؛ IME باید ابتدا composition را کامل کند.
- Date/Jalali pickerهای سفارشی `inputmode=none` باقی می‌مانند و Keyboard بومی را بی‌دلیل باز نمی‌کنند.
- Browser/headless فقط Contract را می‌سنجد؛ Android/PWA واقعی و IME واقعی برای Release مهم `UAT_REQUIRED` است.

## 7) Jalali Picker
- Grid هر ماه همیشه 42 خانه / 6 هفته دارد؛ تغییر ماه نباید ارتفاع Modal/Sheet را عوض کند.
- Arrow قبلی/بعدی همیشه باقی می‌ماند؛ Swipe افقی فقط Enhancement است.
- Swipe فقط با حرکت افقی واضح فعال می‌شود و Scroll عمودی را مختل نمی‌کند.
- Header تقویم سه ناحیه پایدار دارد تا طول نام ماه Arrowها را جابه‌جا نکند.

## 8) Overlay و Drawer
- Compact choice → centered modal؛ Browse choice → mobile bottom sheet؛ Date/Time → modal؛ contextual menu → desktop popover/mobile action sheet.
- Nested overlay به‌صورت پیش‌فرض ممنوع.
- Drawer فرم: Header ثابت، Body تنها بخش Scrollable، Footer ثابت؛ Footer نباید روی محتوا Overlay شود.
- Multi-field Quick Edit روی touch به‌صورت پیش‌فرض کیبورد را خودکار باز نمی‌کند.
- بستن فرم دارای تغییر ذخیره‌نشده نیازمند Dirty-state confirmation است.

## 9) Progressive Disclosure
- اطلاعات کم‌تکرار مثل تاریخ واقعی، زمان دقیق، تأمین‌کننده و یادداشت نباید دائماً فرم اصلی را بلند کنند.
- کاربر باید بتواند Optional Field را «اضافه» و پیش از ثبت حذف کند.
- Healthy state بدون Action فضای اصلی صفحه را اشغال نمی‌کند؛ Exception-first.
- Instruction با Alert یکی نیست: Helper Note کوچک برای آموزش، Alert برای مشکل/ریسک/اقدام لازم.

## 10) Error و State
- Raw technical errors مثل `Failed to fetch`, PDO, stack/error.message در UI عادی ممنوع‌اند.
- Shared safe business error owner باید استفاده شود.
- GET نباید side-effect مدیریتی جدید بسازد.
- عملیات حساس retry-safe/idempotent و state-changeها Desired State هستند، نه blind toggle.

## 11) Accessibility و Focus
- Focus indicator مشترک واضح ولی بدون ringهای چندلایه سنگین.
- Focus بعد از overlay به trigger بازگردد.
- Utility iconها aria-label دارند و فقط با رنگ قابل تشخیص نیستند.

## 12) الزام تغییر استاندارد
هر تغییر استاندارد در همان Release باید:
1. در این سند یا `STANDARDS_REGISTER_FA.md` ثبت شود.
2. Test/Contract متناظر داشته باشد.
3. در `tests/uat_matrix.json` اگر UAT بصری/فیزیکی لازم دارد وارد شود.

## 13) Composite Date Control
- تاریخ جلالی یک Composite Control واقعی است: Parent مالک Border/Radius/Focus و Button تقویم داخل همان Bounding Box است.
- آیکن تقویم Absolute overlay روی Input نیست؛ در 320/360/390/412 هیچ بخشی از Button نباید از کادر خارج شود.
- Grid تقویم 42 خانه/6 هفته ثابت دارد؛ Swipe افقی ماه‌ها Enhancement است و Arrowها همیشه باقی می‌مانند.

## 14) Selection Tile
- انتخاب‌هایی مثل ویژگی محصول و روزهای نمایش، Tile کامل clickable با حداقل ارتفاع 44px هستند.
- متن و Checkbox عموداً Center هستند؛ در RTL متن سمت راست و کنترل سمت چپ می‌ماند.
- برای اصلاح Alignment، Margin صفحه‌ای یا Position absolute روی Checkbox مجاز نیست.

## 15) Overlay Classification و Swipe
- `dismissible-sheet`: Bottom Sheet/Drawer موقت مثل Quick Edit؛ Close button + Swipe-down دارد. Swipe باید از همان safe-close عبور کند و Dirty state را دور نزند.
- `modal`: Date/Time centered modal؛ Swipe-down ندارد و با Close/Cancel/Escape بسته می‌شود.
- `protected-modal`: عملیات مالی/مخرب؛ Gesture dismissal خودکار ندارد.
- Swipe implementation جدید باید از Owner مشترک `CafeUI.bindSwipeDismiss` استفاده کند؛ پیاده‌سازی موازی جدید ممنوع است.

## 16) Semantic Time & 12h Presentation
- هیچ `type=time` در پنل بدون `data-minute-step` صریح مجاز نیست.
- Scheduleهای منو/شیفت/کمپین/برچسب: 15 دقیقه؛ Event و «زمان دقیق» کم‌تکرار: 5 دقیقه، مگر نیاز کسب‌وکاری مستند.
- Time Picker Modal است؛ Minute list از Semantic Step مصرف‌کننده ساخته می‌شود و fallback پنهان یک‌دقیقه‌ای ممنوع است.
- Presentation انسانی ۱۲ساعته است؛ **«قبل از ظهر» سمت راست** و **«بعد از ظهر» سمت چپ** Segment دوره هستند.
- Grid ساعت و دقیقه `direction:ltr` دارد تا ساعت `۱` و دقیقه `۰۰` از سمت چپ شروع شوند.
- Trigger نیز زمان را با همان Vocabulary انسانی نشان می‌دهد؛ نمایش 18:00 بعد از انتخاب «۶ بعد از ظهر» ممنوع است.
- Canonical value تغییر نمی‌کند: Input/API/Database همچنان `HH:MM` 24h است. تبدیل ۱۲ قبل از ظهر=00 و ۱۲ بعد از ظهر=12 باید فقط در Owner مشترک انجام شود.
- Hour/Minute grid Nested Scroll ندارد؛ تمام ۱۲ ساعت و Minute stepهای معمول در خود Modal قابل مشاهده‌اند.

## 17) Schedule Contract
- بازه نمایش آیتم «تاریخ» است، نه تاریخ+ساعت تکراری: شروع=00:00:00 و پایان=23:59:59 در Backend.
- ساعت فقط در Daily Window تعریف می‌شود.
- Daily Window عبوری از نیمه‌شب پس از 00:00 به روز قبلی Schedule نسبت داده می‌شود.
- UI تاریخ بازه، ساعت روزانه و روزهای نمایش را سه مفهوم مستقل نشان می‌دهد.

## 18) Operational Notification Contract
- Permission با Notification Responsibility یک چیز نیست. دریافت Push براساس مسئولیت عملیاتی Routing می‌شود.
- Floor: فراخوان مهمان و سفارش Pending. Preparation: فقط Area مجاز. Admin به‌طور پیش‌فرض Push زنده عملیات نمی‌گیرد مگر Opt-in صریح.
- `event_key` برای Idempotency عملیات است و `tag` برای Group/Replace اعلان روی دستگاه؛ این دو نباید دوباره یکی شوند.
- فراخوان مهمان می‌تواند One-tap «پذیرفتم» داشته باشد؛ Action باید Token امضاشده کوتاه‌عمر، Permission re-check و Claim اتمیک داشته باشد. رد/تصمیم Context-heavy مستقیم از Notification انجام نمی‌شود.
- Push failure هرگز Transaction سفارش/پول/انبار را خراب نمی‌کند.
- Diagnostics باید Queue backlog و Worker heartbeat را نشان دهد.

## 19) Route Runtime Standard
- تغییر SQL در Route حساس مالی/عملیاتی با Static Source Check کامل تلقی نمی‌شود.
- قبل از Promotion باید Query production-shaped روی MariaDB/MySQL و Route احراز هویت‌شده روی Staging اجرا شود.
- اگر محیط Build این Gate را ندارد، نتیجه صریحاً `UAT_REQUIRED` است و در Test Report پنهان نمی‌شود.

## 20) Real-Markup Browser Tests
- برای Component حساس، Fixture باید Markup واقعی Component را مدل کند؛ تست «element exists» جای Geometry/visibility را نمی‌گیرد.
- Flags، sticky/footer، calendar button containment، selection tile alignment و odd metadata با Bounding Box در عرض‌های مرجع تست می‌شوند.

## 21) Visual Rhythm و Composition Owner
- فاصله فقط یک مقدار تزئینی نیست؛ بخشی از Contract رابط است. Tokenهای معنایی رسمی: `--panel-gap-copy`، `--panel-gap-control`، `--panel-gap-section` و `--panel-gap-page`.
- چند Surface مستقل در یک صفحه باید یک Parent owner داشته باشند: `.panel-page-flow` برای Flow عمومی صفحه یا `.panel-surface-stack` برای Stackهای موجود. Child card حق ندارد برای جداشدن از sibling خودش `margin-top/bottom` بسازد.
- محتوای داخل Card که چند Block مستقل دارد از `.panel-card-flow` استفاده می‌کند؛ Title/Description از `.panel-copy-stack`.
- Diagnosticها مثل Worker/Queue/Health نباید به شکل یک جمله طولانی از چند `<span>` پشت‌سرهم Render شوند. Owner مشترک `.panel-diagnostic-grid` باید Label/Value را قابل Scan و Responsive نگه دارد.
- Mobile reference widths: 320/360/390/412. حداقل فاصله Page flow در موبایل `--panel-gap-section` و در عرض‌های بزرگ `--panel-gap-page` است.
- عدد خام spacing در صفحه جدید فقط وقتی مجاز است که Component owner مستقل و دلیل مستند داشته باشد؛ page-level patch برای جبران Composition ممنوع است.

## 22) Visual Quality Gate و Baseline انسانی
- QA بصری چهار لایه دارد: ۱) Structural/overflow/touch، ۲) Semantic geometry و rhythm، ۳) Visual baseline برای state واقعی، ۴) Physical UAT.
- Baseline تصویری فقط وقتی مرجع است که انسان همان **صفحه + state + عرض** را تأیید کرده باشد. Screenshot تولیدشده توسط Build بدون UAT اجازه ندارد خودکار Baseline جدید شود.
- Registry رسمی stateهای بصری: `tests/visual_quality_pages.json`. وضعیت `pending_uat` در Promotion نهایی Blocker است؛ Source/Development می‌تواند با برچسب صریح `UAT_REQUIRED` ادامه پیدا کند.
- تست Content Stress برای stateهای بصری باید متن بلند فارسی، عدد، LTR/RTL ترکیبی، empty/error/populated و wrap دوخطی را پوشش دهد؛ صرفاً داده خوش‌رفتار کافی نیست.
- Geometry test باید فاصله واقعی بین Bounding Boxها، عدم overlap، containment و clipping را بسنجد؛ `element exists` مدرک کیفیت بصری نیست.
- بدهی Composition قدیمی در `tests/visual_quality_baseline.json` صریح نگه داشته می‌شود. اضافه‌شدن بدهی جدید بدون ثبت/تصمیم آگاهانه Gate را Fail می‌کند و رفع بدهی باید entry آن را حذف کند.

## 23) Financial UI Family
- تمام زیرمنوهای مالی از سه Pattern مشترک استفاده می‌کنند، نه از جدول/KPI/Cardهای جداگانه برای هر صفحه: **Archive/List**، **Financial Record** و **Financial History**.
- Archive/List: جست‌وجوی واضح، فیلترهای کم‌حجم، شمارش نتیجه به‌عنوان metadata و ردیف‌های پیوسته. نتیجه Count یک KPI Card نیست.
- Financial Record: هویت سند/شخص → Context → مبلغ/مانده → تاریخچه یا اقلام → Metadata ثانویه. Utility action جای ثابت دارد.
- Financial History: زمان، موضوع و مبلغ در hierarchy اصلی؛ actor/reference/statusهای عادی ثانویه‌اند. وضعیت عادی Badge نمی‌گیرد؛ Exception قابل مشاهده است.
- `financial-workspace` Owner عرض و rhythm خانواده مالی است. `financial-toolbar`، `financial-list`، `financial-row`، `financial-row-main`، `financial-row-amount` و `financial-row-actions` Primitiveهای مشترک هستند.
- در موبایل و دسکتاپ یک data model/markup ترجیح دارد. ساخت هم‌زمان «table desktop + card mobile» برای یک history جدید ممنوع است مگر دلیل عملیاتی مستند داشته باشد.
- فاکتورها، مشترکین، حساب اقامتگاه و دوره‌های مالی باید در 320/360/390/412/768/1366 بدون root overflow، بدون nested table overflow و با List→Detail continuity تست شوند.
- KPI tileهای تزئینی در آرشیو مالی ممنوع‌اند. Summary فقط وقتی می‌ماند که تصمیم یا context مالی واقعی بدهد؛ مثال: مانده کل بدهکاران یا دوره جاری.
- بنچمارک مرجع این Family «کپی برند» نیست؛ از الگوهای بالغ امروزی فقط اصل‌ها اقتباس می‌شوند: task-first و کاهش تصمیم هم‌زمان، search-first برای archive، record متمرکز، customer/guest context یکپارچه، token-backed composition، card برای یک موضوع و list پیوسته برای مجموعه رکوردها. هر الگو که سرعت Scan، دسترس‌پذیری یا عملیات کافه را بدتر کند رد می‌شود.
- Filterها در موبایل Progressive Disclosure هستند؛ Search همیشه در دسترس است، فیلتر فعال به‌صورت Chip فشرده دیده می‌شود و فرم فیلتر نباید به یک Dashboard مستقل تبدیل شود.
- Desktop مجاز نیست فقط «نسخه کشیده‌شده موبایل» باشد: عرض workspace محدود، hierarchy ثابت و density بالاتر است؛ اما data model و واژگان با موبایل یکی می‌ماند.

### Financial visual hierarchy — 1.32.14
- «زنده‌تر شدن» صفحات مالی با **پالت موجود پنل** انجام می‌شود، نه رنگ مستقل یا Theme جدید. Primary برای value/action و Accent برای context/grouping به‌کار می‌رود؛ هر دو باید از Tokenهای جاری مشتق شوند.
- `financial-workspace` مالک Tokenهای محلی `--financial-primary-soft`, `--financial-accent-soft`, `--financial-line`, `--financial-value` و `--financial-shadow` است. Consumer حق ساخت رنگ مالی Hard-coded صفحه‌ای ندارد.
- Summary/Record hero می‌تواند Tint بسیار ملایم Primary داشته باشد؛ Date/group header می‌تواند Accent ملایم بگیرد؛ Rowهای عادی سفید و پیوسته می‌مانند. هدف hierarchy است، نه تزئین.
- مبلغ/مانده کلیدی مجاز است با `--financial-value` برجسته شود، اما Status عادی همچنان quiet است و Badge فقط برای exception/state معنادار می‌ماند.
- Relation اسناد برگشت باید `invoice-related-document` جدا داشته باشد: عنوان رابطه، توضیح/دلیل و action مرتبط نباید در یک جمله به هم بچسبند.
- Canonical identifier برای Audit/Search حفظ می‌شود، ولی در UI روزمره Human reference اولویت دارد؛ مثال: «رزرو ۸» مقدم بر `SK-1405-00008`.
- متن آموزشی دائمی داخل Financial workflow فقط اگر هر بار برای تصمیم لازم باشد مجاز است؛ توضیح «نحوه اتصال اسناد» Progressive Disclosure است.
- دوره جاری فقط در Hero نمایش داده می‌شود و در History تکرار نمی‌شود. History برای دوره‌های قبلی/نیازمند اقدام است.

## 24) Financial Composition v2 — 1.32.15
- فاکتورها، مشترکین و حساب اقامتگاه یک **Financial Page Shell** مشترک دارند: `financial-page-shell` مالک Summary/Utility، Toolbar و List است. ساخت سه Card جدا برای Summary/Search/List در یک Archive ممنوع است مگر Context واقعاً تغییر کند.
- **No duplicate information:** هر داده فقط یک‌بار در نزدیک‌ترین سطحی که برای تصمیم لازم است نمایش داده می‌شود. Count در Header، تاریخ روز در Group Header، مبلغ سند در Row، Metadata ثبت فقط در Detail. Ledger حق تکرار «اطلاعات ثبت» یا «مبلغ نهایی» سندی را که مبلغش در Summary همان Row آمده ندارد.
- **Surface budget:** Archive عادی یک Surface اصلی دارد. Exception مستقل، Editor و Record Detail می‌توانند Surface مستقل داشته باشند. Card برای جداکردن بصری بدون تغییر Context مجاز نیست.
- Search همیشه در Toolbar دیده می‌شود. Filterهای ساده compact هستند؛ Advanced Filter فاکتورها از Overlay مشترک استفاده می‌کند و در موبایل Bottom Sheet با Swipe-down است. فرم Advanced Filter نباید در Flow صفحه ارتفاع اشغال کند.
- Financial Row anatomy مشترک است: `Primary identity/context → amount → compact metadata/action`. Human reference مقدم است؛ canonical id فقط Search/Audit/Detail.
- Healthy status در List نمایش داده نمی‌شود؛ فقط exception/state معنادار Badge می‌گیرد.
- Amount typography سه سطح دارد: Hero amount، Row amount و Secondary balance. Consumer حق ساخت `font-size/font-weight` صفحه‌ای برای مبلغ ندارد.
- Date/time: وقتی Date Group وجود دارد Row فقط ساعت را تکرار می‌کند؛ بدون Group از `format_jalali_human_datetime()` استفاده می‌شود. سال جاری تا وقتی Context روشن است تکرار نمی‌شود.
- Subscriber Ledger یک Financial History است، نه Mini Invoice Page: Collapsed Row فقط سند/زمان/مبلغ/مانده بعد را دارد؛ یک Disclosure برای اقلام/دلیل/لینک سند. Metadata ثبت از Ledger حذف است.
- معیار Density در 390px برای State عادی: Invoice/Accommodation Row حداکثر حدود 96px، Subscriber Row حدود 96px و Ledger Collapsed حدود 90px. Touch target حداقل 44px و خوانایی مقدم بر رسیدن مصنوعی به عدد است.
- Cross-page consistency بخشی از Gate است: ارتفاع Search، Typography مبلغ، Shell/Toolbar/Row owner و Status language بین صفحات مالی مقایسه می‌شوند؛ «هر صفحه به‌تنهایی سالم است» برای PASS کافی نیست.
- Empty/Error State باید کوتاه، actionable و داخل همان Shell باشد؛ Card تزئینی جدا برای Empty state ممنوع است. Raw DB/HTTP error همچنان ممنوع است.

## Shared Reorder / Messages / Print Template — 1.32.18
- ترتیب دستی Collectionها باید از Shared Reorder استفاده کند: drag + دکمه بالا/پایین + keyboard fallback؛ شماره `sort_order` به کاربر نمایش داده نشود.
- Message editor باید بر پایه تعریف مرکزی پیام‌ها باشد؛ متن‌های Security/Financial-risk قابل ویرایش عمومی نیستند. Search، گروه، Changed/Optional filter، Reset تکی و Token guardrail بخشی از قرارداد است.
- قالب چاپ، declarative و versioned است؛ Import آزاد HTML/CSS/JS ممنوع. Preview 58/80mm قبل از فعال‌سازی لازم است. Preview جای Physical Print UAT را نمی‌گیرد.

## Order Review / Current Bill — 1.32.19
- مرور سفارش فقط یک Owner برای Total دارد؛ مبلغ تکراری در Header ممنوع است و جمع نزدیک CTA ثبت قرار می‌گیرد.
- پاک‌کردن کل سبد Action ثانویه است و کنار Close هم‌وزن نمایش داده نمی‌شود؛ Undo موجود حفظ می‌شود.
- Dine-in حالت عادی است و Badge وضعیت دائمی ندارد. Takeaway فقط به‌عنوان Exception دیده می‌شود؛ ورود به تنظیم می‌تواند با utility خنثی انجام شود.
- Stepper تعداد کل Primary است؛ Stepper Takeaway Secondary و فشرده است و نباید با Primary اشتباه شود.
- فاکتور جاری Staff هم Financial و هم Operational است: Takeaway Exception باید در Line باقی بماند، اما Receipt نهایی مشتری لازم نیست آن را تکرار کند.
- یک نوبت سفارش Disclosure جدا ندارد؛ multi-order فقط از دو نوبت به بالا با عنوان «سفارش‌های این میز» ظاهر می‌شود.
- بدون تخفیف، Subtotal تکراری مخفی است؛ با تخفیف Subtotal/Discount/Payable نمایش داده می‌شوند.
- Printer unavailable یک Status است، نه Button غیرفعالِ بزرگ.
