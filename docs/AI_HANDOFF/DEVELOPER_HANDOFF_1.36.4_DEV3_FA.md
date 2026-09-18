# هنداور توسعه Sokna 1.36.4-dev.3

- Baseline محیط تست: `1.36.4-dev.3`.
- تسویه آیتمی میز روی Finance Allocation واحد فعال است.
- پس از اولین پرداخت جزئی، ویرایش حساب و Quick Order معمولی قفل می‌ماند.
- استثنای کنترل‌شده: صندوق‌دار می‌تواند از «افزودن قلم جاافتاده» برای قلمی که قبلاً سرو شده ولی در حساب ثبت نشده استفاده کند.
- late accounting میز/session را ثابت نگه می‌دارد، فقط dine-in است، prep print/push نمی‌سازد، ولی Inventory/COGS و Audit را ثبت می‌کند.
- پرداخت‌ها و Allocationهای قبلی immutable می‌مانند؛ مانده و signature پس از ثبت قلم تازه محاسبه می‌شوند.
- مسیر عادی Quick Order نباید در حالت itemized باز شود.
- تست‌های اصلی: `tests/itemized-settlement-contract.py`, `tests/itemized-settlement-browser.py`, `tests/late-accounting-browser.py`, `tests/staff-quick-order-browser.py`, `tests/unit.php`.
