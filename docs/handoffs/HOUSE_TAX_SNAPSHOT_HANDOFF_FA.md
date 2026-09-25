# هندوور تغییر قرارداد اقامتگاه برای مالیات کافه

وضعیت: **قرارداد پیشنهادی بر اساس سورس واقعی dev.39؛ هنوز در دو سمت پیاده/پذیرفته نشده است.**
دستور مالک: هر دو سامانه پیش از بهره‌برداری‌اند؛ نیاز صحیح مالی کافه حفظ و API اقامتگاه هماهنگ شود.

## مبنای واقعی کد
- Transport موجود `includes/accommodation_transport.php` و actionهای capabilities/charge/status/void با Bearer و HTTPS حفظ شود؛ نام action دقیق status/void باید با routing موجود دو سمت تطبیق داده شود.
- مالک business و persistence در `includes/accommodation.php` است؛ ماشین وضعیت retry/ambiguous/posted/void بازنویسی نشود.
- کافه در `settlement_invoice_snapshot_locked` برای سند مالیاتی **snapshot version 3** می‌سازد؛ v2 جدید اختراع نشود، چون شماره داخلی قبلاً مصرف شده است.
- normalizer اقامتگاه فقط فیلدهای v1 را نگه می‌دارد؛ validator فقط v1 و subtotal-discount=total را قبول می‌کند؛ create transfer نیز tax>0 را مسدود می‌کند. هر سه نقطه همراه هم اصلاح شوند.

## نسخه و capability پیشنهادی
API موجود 2.0 می‌تواند با یک capability افزایشی توسعه یابد؛ نسخه envelope و نسخه invoice دو مفهوم جدا هستند. این انتخاب باید در هر دو سمت یکسان پیاده شود.
`capabilities.invoice_snapshot_versions: [1,3]` و `capabilities.invoice_tax_snapshot: true` به پاسخ فعلی اضافه شوند. فیلدهای قدیمی unified_search/invoice_snapshot/idempotent_charge/charge_void باقی بمانند.
کافه پیش از اولین انتقال v3 باید پشتیبانی صریح مقصد را احراز کند. نبود capability یا خطای ارتباط اجازه تبدیل v3 به v1 یا حذف مالیات نیست. اسناد v1 قدیمی همچنان قابل retry باشند. مبلغ انتقال همواره برابر invoice.total و currency برابر TOMAN است.

## ساختار snapshot v3
نام‌ها دقیقاً از snapshot موجود کافه گرفته شده‌اند:
- سطح فاکتور: version، number، issued_at، table_name، subtotal، discount، net، taxable، tax، total، items.
- هر ردیف: name، quantity، unit_price، line_total، note، line_discount، line_net، taxable_amount، tax_rate_bps، tax_amount، line_final.
- مبلغ‌ها عدد صحیح تومان؛ quantity عدد صحیح مثبت؛ نرخ عدد صحیح basis point در بازه 0..10000. نمونه 1000 یعنی ۱۰٪.
- line_total = quantity × unit_price؛ line_net = line_total − line_discount؛ line_final = line_net + tax_amount.
- جمع line_total=subtotal، line_discount=discount، line_net=net، taxable_amount=taxable، tax_amount=tax، line_final=total.
- net=subtotal-discount و total=net+tax؛ مقادیر منفی، تخفیف بیش از ناخالص و taxable_amount بیش از line_net رد شود.
- کافه مالیات هر ردیف را پس از تخصیص تخفیف با گردکردن صحیح half-up محاسبه می‌کند: floor((taxable_amount × tax_rate_bps + 5000)/10000). در کد باید از سرریز ضرب جلوگیری شود، مانند tax_round_amount موجود.
- اقامتگاه از نرخ روز خودش برای بازنویسی سند استفاده نکند؛ snapshot زمان صدور ثبت شود. نرخ نمونه در زیر صرفاً داده آزمون است.

## نمونه دقیق
```json
{
  "external_order_id": "CAFE-S-123",
  "reservation_code": "TEST-RESERVATION",
  "amount": 99000,
  "currency": "TOMAN",
  "invoice": {
    "version": 3,
    "number": "TEST-123",
    "issued_at": "2026-09-25T12:00:00+03:30",
    "table_name": "میز آزمون",
    "subtotal": 100000,
    "discount": 10000,
    "net": 90000,
    "taxable": 90000,
    "tax": 9000,
    "total": 99000,
    "items": [{
      "name": "قلم آزمون", "quantity": 1, "unit_price": 100000,
      "line_total": 100000, "note": null,
      "line_discount": 10000, "line_net": 90000,
      "taxable_amount": 90000, "tax_rate_bps": 1000,
      "tax_amount": 9000, "line_final": 99000
    }]
  }
}
```

## ثبت، تکرار و برگشت
همان شناسه external_order_id و دامنه هویت/احراز هویت مشتری باید برای ثبت یکتا استفاده شود. تکرار همان snapshot باید همان سند را برگرداند؛ همان شناسه با payload متفاوت external_order_conflict بدهد. hash داخلی کافه ترتیب canonical فعلی را دارد؛ normalizer v3 باید پایدار و idempotent باشد و retry نباید به نرخ روز یا نسخه جاری تبدیل شود.
قطع ارتباط پس از commit مقصد باید مسیر استعلام موجود را طی کند؛ ثبت دوباره با شناسه تازه ممنوع. ذخیره snapshot و ثبت مبلغ اقامتگاه در یک transaction باشند. void سند را حذف نکند؛ مبلغ کامل شامل مالیات با همان رابطه برگشت ثبت و برگشت تکراری idempotent باشد. پاسخ نسخه ناسازگار unsupported_invoice_version و جمع ناسازگار invoice_total_mismatch بدهد. قالب پاسخ posted/status/void و کنترل پاسخ مشکوک فعلی حفظ شود.

## آزمون پذیرش مشترک
۱. v1 قدیمی بدون تغییر payload/hash و بدون مالیات.
۲. v3 مالیاتی نمونه بالا؛ exact JSON fields و مبلغ 99000 در دو سیستم.
۳. سبد شامل معاف و مشمول، تخفیف و مرز گردکردن نیم‌تومان؛ جمع ردیف‌ها با کل دقیقاً برابر باشد.
۴. v3 با tax صفر و سابقه مالیاتی معتبر بدون حذف اطلاعات.
۵. مقصد بدون capability؛ هیچ charge مالیاتی ارسال نشود و خطای روشن بازگردد.
۶. جمع/نرخ/نوع داده/نسخه نامعتبر؛ بدون ثبت ناقص.
۷. retry یکسان و تعارض payload؛ timeout قبل و بعد از commit و بازیابی وضعیت.
۸. void دوباره و برگشت محلی؛ حفظ مالیات snapshot و عدم دوبرابرشدن برگشت.
۹. تغییر نرخ پس از ایجاد انتقال نباید retry/void سند قبلی را عوض کند.

## کارهای لازم سمت کافه
normalizer و validator v3، capability gate خارج از تراکنش قفل‌شده و با سیاست cache روشن، حذف block عمومی tax فقط پس از وجود gate، حفظ snapshot/hash retry و تست‌های PHP/DB/transport. این سند به معنی انجام این تغییرها نیست.

## تغییر اجرایی کافه — 2026-09-25
normalizer/validator نسخه ۳ و capability gate پیش از charge پیاده شد. gate پس از commit تراکنش انتقال و خارج از قفل دیتابیس اجرا می‌شود؛ cache ندارد. v1 قدیمی با قالب قبلی حفظ می‌شود. تست PHP جدید در dev gate ثبت شد اما در محیط محلی PHP موجود نیست و نصب بسته به علت محدودیت محیط ممکن نشد. اجرای CI/DB و پیاده‌سازی اقامتگاه هنوز لازم است؛ توضیحات «هنوز پیاده نشده» در بالا درباره کافه با این بند جایگزین می‌شود.
