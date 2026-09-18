# یکپارچه‌سازی زبان تب‌های پنل — 1.36.4-dev.6

## تصمیم
الگوی «نیازمند اقدام / میزها / جمع اقلام» مرجع بصری تب اصلی سکنا است.

سه سطح از هم جدا می‌مانند:
1. Primary Tabs: جابه‌جایی بین Viewهای اصلی؛ Active سبز کامل.
2. Secondary Navigation: جابه‌جایی بین زیرصفحه‌ها؛ Active سبز بسیار نرم و کم‌تأکید.
3. Filter / Segmented: فقط فیلتر داده؛ کوچک‌تر و مستقل از Tab اصلی.

## اعمال‌شده
- Operator به Component مشترک Primary Tabs متصل شد.
- بخش‌های اصلی Settings و Printing از همان زبان بصری استفاده می‌کنند.
- Print Template Switch به Secondary Navigation منتقل شد.
- گروه‌های Messages به‌درستی Filter شناخته می‌شوند و دیگر role=tablist ندارند.
- panel_subnav از نظر Radius، Typography و Touch Target به خانواده مشترک نزدیک شد، بدون Active سبز کامل.

## عدم تغییر
Route، Permission، داده، Business Logic، Settlement، Inventory و رفتار Filterها تغییر نکرده‌اند.
