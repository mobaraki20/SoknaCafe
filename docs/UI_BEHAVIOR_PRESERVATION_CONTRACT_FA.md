# قرارداد حفظ رابط و رفتار در Refactorهای Sokna

این سند یک **قید مهندسی فعال و قفل‌شده توسط Owner** است. تا زمانی که Owner صریحاً یک Task را به‌عنوان Redesign/Behavior Change تعریف نکرده باشد، تمام Cleanup، Legacy removal، Owner consolidation، Schema hardening و Root-Cause Refactorها باید **Behavior-preserving و UI-preserving** باشند.

مرجع‌های همراه:
- `DEVELOPER_READ_FIRST_FA.md`
- `ROOT_CAUSE_REFACTOR_POLICY_FA.md`
- `UI_DESIGN_SYSTEM_FA.md`
- `TESTING_FA.md`

## 1) اصل پایه: Refactor با Redesign یکی نیست

هدف Refactor، تغییر Implementation برای ساده‌تر، امن‌تر و قابل‌تست‌تر شدن سیستم است؛ نه تغییر تجربه کاربر.

در Scopeهای Refactor، بدون Requirement صریح Owner تغییر موارد زیر ممنوع است:

- جایگاه و ترتیب Actionهای اصلی؛
- ساختار Navigation، Tabها، Drawer/Modal/Sheet و نقطه ورود Canonical؛
- ترتیب مراحل Workflow و تعداد Stepهای کاربر؛
- معنی عملیات، State transitionها و نتیجه نهایی Action؛
- متن‌های عملیاتی تثبیت‌شده، مگر خود Task مربوط به Copy/Language باشد؛
- اندازه، فاصله، تراکم، Touch target، responsive composition و hierarchy بصری تثبیت‌شده؛
- رفتار Mobile/Touch/Keyboard/Focus/Swipe؛
- Permission و visibility حاصل از Permission؛
- محاسبات مالی، Settlement، Invoice، Refund، Inventory valuation و Audit؛
- Print routing/result contract؛
- Order/Table/Kitchen-Bar state machine؛
- Guest-facing behavior و URL/QR canonical فعال.

اصل اجرایی:

> **Implementation می‌تواند عوض شود؛ Contract مشاهده‌پذیر کاربر و عملیات نباید ناخواسته عوض شود.**

## 2) Scopeهای محافظت‌شده با حساسیت بالا

این Surfaceها در Refactorهای Pre-Go-Live به‌صورت پیش‌فرض Freeze هستند، مگر Task صریحاً همان UI/Behavior را هدف گرفته باشد:

1. Operator و Staff actions
2. Quick Order
3. Table/Floor management
4. Kitchen/Bar preparation flow
5. Settlement / Invoice / Refund
6. Guest Menu / QR ordering
7. Inventory / Supply operational flows
8. Printing operations
9. Manager dashboard و گزارش‌های عملیاتی
10. Backup / Recovery / Security-sensitive flows

## 3) One-Concern Rule

هر Batch باید یک Concern اصلی داشته باشد.

نمونه مجاز:
- حذف Route تکراری و انتقال همه Call Siteها به Route canonical، بدون تغییر UI.

نمونه غیرمجاز:
- حذف Route تکراری + جابه‌جایی CTA + تغییر رنگ + بازطراحی Modal در همان Task.

اگر هنگام Refactor یک بهبود UX کشف شد، به‌عنوان Task مستقل ثبت می‌شود؛ مگر اینکه بدون آن Fix اصلی ممکن نباشد و دلیل در Decision/Audit ثبت شود.

## 4) Baseline قبل از تغییر

قبل از تغییر Runtime در هر Batch Refactor باید حداقل این Baseline ثبت/اجرا شود:

- PHP lint؛
- JavaScript syntax؛
- Unit/Contractهای مرتبط؛
- Browser Gateهای مرتبط با Surface لمس‌شده؛
- Visual/Layout Gateهای موجود برای Surface مربوط؛
- در Scopeهای مالی/انبار/چاپ، Regression تخصصی همان Domain؛
- وضعیت تست‌های `UAT_REQUIRED` بدون تبدیل مصنوعی به PASS.

اگر Baseline از قبل Failure شناخته‌شده دارد، Failure باید قبل از تغییر طبقه‌بندی شود تا بعداً به Refactor نسبت داده نشود.

## 5) Preservation Gate بعد از تغییر

Refactor فقط زمانی پذیرفته است که:

- مسیر کاربر همان نتیجه را با همان Contract عملیاتی بدهد؛
- Navigation و Actionهای اصلی بدون Requirement جدید جابه‌جا نشده باشند؛
- Browser/Touch/Responsive regression جدید ایجاد نشده باشد؛
- Visual debt جدید وارد `visual_quality_baseline.json` نشده باشد؛
- برای سبزکردن تست، threshold/allowlist بی‌دلیل شل نشده باشد؛
- Permission یا نمایش Action به‌صورت ناخواسته تغییر نکرده باشد؛
- Finance/Order/Inventory/Print/Audit invariantهای مرتبط PASS باشند؛
- Legacy حذف‌شده Consumer زنده نداشته باشد؛
- Route/API/Owner canonical واحد باقی مانده باشد.

## 6) قانون Stop-the-Batch

اگر بعد از Refactor هر تغییر ناخواسته در ظاهر، جایگاه، Workflow، Permission، خروجی مالی، Print، State یا Mobile behavior مشاهده شد:

1. Batch **قبول نمی‌شود**؛
2. Regression باید به همان Change نسبت‌سنجی شود؛
3. Fix باید در Root Cause همان Regression انجام شود؛
4. اضافه‌کردن Patch دوم برای مخفی‌کردن Regression مجاز نیست؛
5. تا سبزشدن Gate مربوط، Batch بعدی شروع نمی‌شود.

## 7) Schema Refactor حساس‌تر از Route Cleanup است

Batchهای Schema مثل `NOT NULL` کردن فیلدهای canonical یا حذف fallbackهای Legacy باید علاوه بر Preservation Gate از این مسیر عبور کنند:

`Data Audit → Migration/Schema Change → Fresh Install → Domain Contracts → Browser Regression → Financial/Operational Regression → UAT لازم`

Pre-Operational بودن مجوز شکستن داده یا Contract سالم نیست؛ فقط اجازه می‌دهد Compatibility بی‌مصرف را پس از اثبات عدم نیاز حذف کنیم.

## 8) استثنا: Redesign یا Behavior Change عمدی

تغییر UI/Behavior فقط وقتی در همان Scope مجاز است که Task صریحاً یکی از این برچسب‌ها را داشته باشد:

- `REDESIGN_APPROVED`
- `BEHAVIOR_CHANGE_APPROVED`

در این حالت باید قبل از Merge مشخص باشد:

- چه Contract قبلی عمداً تغییر می‌کند؛
- دلیل Product/Operations چیست؛
- چه Baseline/Testهایی باید به Contract جدید منتقل شوند؛
- آیا نیاز به UAT انسانی یا Visual Baseline جدید وجود دارد.

بدون این ثبت، تغییر مشاهده‌پذیر کاربر Regression محسوب می‌شود، نه «بهبود جانبی».

## 9) Definition of Done برای Refactor محافظه‌کار

Refactor زمانی Done است که:

- Root Cause اصلاح شده باشد؛
- Owner واحد شده باشد؛
- Legacy/Dead path Scope حذف شده باشد؛
- Runtime behavior ناخواسته تغییر نکرده باشد؛
- UI/UX ناخواسته تغییر نکرده باشد؛
- تست‌ها و Visual gates مرتبط سالم باشند؛
- هیچ Patch Stacking تازه‌ای برای جبران تغییر ایجاد نشده باشد؛
- مستندات Owner/Workflow در صورت نیاز به‌روز شده باشند.

## 10) قانون Batch A Audit 1.36.3

Batch A تعریف‌شده در `PREOP_ROOT_CAUSE_AUDIT_1.36.3_FA.md` یک **Behavior-preserving cleanup** است. حذف Route/Compatibility/Dead Code یا اصلاح تست Copy-based در این Batch حق تغییر UI، ترتیب Workflow، Action placement یا State contract را ندارد.

هر مورد Batch A که برای اجرا نیازمند تغییر مشاهده‌پذیر واقعی باشد، باید از Batch خارج و به Task مستقل Product/UX منتقل شود.
