# Supply / Purchasing Module

Owner فرایند «اعلام نیاز → در حال تهیه → تحویل → Inventory Movement».

## Public contracts
- `supply_request_upsert_locked(...)`
- `supply_mark_group_preparing_locked(...)`
- `supply_return_group_from_preparing_locked(...)`
- `supply_receive_preparing_locked(...)`
- Query/read-modelهای `modules/Supply/queries.php`

## Dependency
این ماژول فقط از Contract عمومی Inventory برای تغییر موجودی استفاده می‌کند و حق UPDATE مستقیم `inventory_balances` را ندارد.

## UI entrypoints
- `operator/supply-needs.php`
- `admin/purchases.php`
- `assets/js/supply-needs.js`
- `assets/js/supply-purchases.js`

Entry pointها فعلاً در مسیرهای عمومی موجود باقی مانده‌اند تا URL/Navigation بدون ارزش عملیاتی تغییر نکند؛ Domain owner از `includes/supply.php` خارج شده است.
