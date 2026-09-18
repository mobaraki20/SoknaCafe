# Sokna RC3 Project Map

## Root
- admin: پنل مدیریت
- operator: رابط عملیاتی کارکنان
- staff: بخش کارکنان
- waiter: مسیرهای مهمان/سفارش
- api: API endpoints
- modules: ماژول های مستقل
- database: Schema و Migration
- assets: CSS JS Icons Fonts
- print-agent: قراردادهای چاپ
- print-templates: قالب های چاپ
- includes: Shared backend infrastructure
- tests: تست ها

## Modules حساس

### Supply / Inventory
مالک Business logic خرید و انبار.
قواعد:
- تغییر موجودی فقط از Movement/Ledger
- نیاز خرید با تحویل واقعی جداست

### Print
حساس ترین مسیر عملیاتی.
هر تغییر باید:
- Print Job
- Attempt
- Failure Recovery
را حفظ کند.

### Operator
مسیر پرتکرار روزانه.
اولویت:
سرعت، لمس، خطای کم.

### Admin
تنظیمات و کنترل مالک.

