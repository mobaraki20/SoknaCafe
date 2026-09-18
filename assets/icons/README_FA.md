# سامانه آیکن سکنا

مالک واحد آیکن‌های رابط، فایل `ui-sprite.svg` است. شناسه‌های `icon-*` قرارداد پایدار برنامه‌اند و حتی در صورت تغییر منبع تصویری نباید بدون مهاجرت داده عوض شوند.

- خانواده تصویری: Tabler Icons Outline 3.46.0، شبکه ۲۴×۲۴، ضخامت خط ۲
- رنگ، اندازه و ضخامت از کلاس مشترک `.ui-icon` می‌آیند؛ داخل هر `symbol` رنگ یا ضخامت مستقل تعریف نمی‌شود.
- آیکن‌های دسته‌بندی نیز از همین Sprite و تابع `category_visual_icon()` استفاده می‌کنند.
- برای عملیات حذف از `trash` و برای بستن پنجره از `close` استفاده شود؛ کاراکترهای متنی جای آیکن قرار نگیرند.

بازسازی در محیط توسعه:

```bash
icon_tmp="$(mktemp -d)"
npm pack @tabler/icons@3.46.0 --pack-destination "$icon_tmp"
tar -xzf "$icon_tmp"/tabler-icons-3.46.0.tgz -C "$icon_tmp"
node tools/build-ui-sprite.mjs "$icon_tmp/package/icons/outline"
python3 tests/v1360-icon-system-contract.py
```

مجوز MIT منبع در `THIRD_PARTY_LICENSES.txt` ثبت شده است.
