# بازبینی دور سوم dev.19 — تصمیم نهایی و Gate اجرا

## تصمیم نهایی

1. **Menu/Catalog:** بدون schema/refactor جدید. dev.18 Source of Truth باقی می‌ماند؛ فقط regression اجرا می‌شود.
2. **Bill Edit Stepper:** `bill-edit-control-row` Owner مشترک layout است. Ruleهای duplicate در `panel-components.css` و breakpoint قدیمی حذف می‌شوند.
3. **Quick Order:** selector compact به `.quick-order-item .quick-order-inline-qty` scope می‌شود؛ cart geometry مستقل و استاندارد باقی می‌ماند.
4. **Backup:** internal archive unchanged؛ off-server transport = `.skb` authenticated encryption. Export plaintext route حذف می‌شود. Import legacy tar.gz فقط برای سازگاری بازیابی pre-Go-Live نگه داشته می‌شود.
5. **Crypto:** Argon2id + XChaCha20-Poly1305 SecretStream، chunked. Passphrase در DB/Settings/Audit ذخیره نمی‌شود.
6. **Dependency:** Sodium در Install و Update manifest اجباری و fail-fast است.
7. **Schema:** هیچ migration تازه‌ای وجود ندارد.

## شواهد مقایسه‌ای مورد استفاده

- Square for Restaurants: Item Library مرکزی؛ Menus برای buyer-facing organization/channel visibility/time-based availability و Category برای reporting/kitchen routing.
  https://squareup.com/help/us/en/article/6424-create-menus-with-square-for-restaurants
- Odoo POS: Product مرکزی با POS-specific categories/tags/variants و category برای navigation/visibility.
  https://www.odoo.com/documentation/19.0/applications/sales/point_of_sale/products.html
- Frappe: Backup encryption و نیاز به key برای restore را به‌عنوان capability رسمی مستند می‌کند. الگوی دقیق crypto سکنا مستقل و متناسب با runtime PHP انتخاب شده است.
  https://docs.frappe.io/framework/user/en/guides/basics/how-to-enable-backup-encryption

این منابع فقط pattern مقایسه‌ای‌اند؛ صحت تصمیم سکنا از معماری و تست خود سکنا تعیین می‌شود.

## Gate قبل از تحویل

- PHP/JS lint + dev gate + source release gate.
- runtime امن Backup: roundtrip + wrong password + tamper + truncation.
- portable archive validator بعد از decrypt.
- browser geometry Stepper در 320/360/390/600/1024/1366 و Quick Order desktop.
- update fixture واقعی source tree از dev.18 به dev.19 و file-level comparison برای فایل‌های package.
- Clean Install ZIP از همان source tree و checksum.

## مواردی که هنوز UAT واقعی می‌خواهند

- MySQL/MariaDB واقعی و Restore کامل روی نصب دوم.
- تأیید Sodium روی هاست مقصد.
- Human visual UAT روی دستگاه/مرورگر واقعی.
- حوزه چاپ/Agent در این Release دست نخورده و gateهای چاپ جدا هستند.
