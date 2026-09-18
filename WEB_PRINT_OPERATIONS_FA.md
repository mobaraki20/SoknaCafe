# راهنمای عملیات و عیب‌یابی چاپ — Web dev.20

برای هر مشکل ابتدا Job ID را در پنل چاپ پیدا کن و timeline Attempt را بخوان. `submitted` یعنی کار به Windows Spooler تحویل شده؛ اگر کاغذ بیرون نیامده، بدون بررسی queue/paper و تصمیم انسانی reprint نزن.

| وضعیت | اقدام مجاز | ریسک چاپ تکراری |
|---|---|---|
| pending و Agent/Printer ready نیست | اتصال/کاغذ/queue را اصلاح کن؛ polling بعداً کار را می‌گیرد | کم، reprint نساز |
| reserved منقضی و Accept نشده | reconcile/صبر برای owner expiry | چاپ خودکار ممنوع |
| claimed/started و receipt mismatch | توقف و reconcile | زیاد؛ Start/reprint دستی نزن |
| failed با evidence امن قبل از submission | retry محدود یا اقدام انسانی | کم تا متوسط، evidence را ببین |
| unknown / recovery_hold | تعیین تکلیف انسانی | زیاد؛ auto-retry ممنوع |
| submitted ولی کاغذ دیده نشد | Spooler/Printer/Paper را بررسی و سپس explicit reprint | بالا؛ ممکن است چاپ در صف بوده باشد |
| report auth/conflict backlog | credential/server scope را اصلاح و reconcile report | reprint نزن؛ outcome محلی ممکن است قبلاً submitted باشد |
| Bridge/Wake unavailable | سفارش را تکرار نکن؛ polling Agent fallback است | هیچ، Wake owner چاپ نیست |
| exact preview unavailable | از preview تقریبی استفاده کن؛ چاپ واقعی profile مقصد را Agent تعیین می‌کند | ندارد |

Agent دارای Attempt/report باز را disable/delete/token-rotate نکن؛ Web dev.20 این عملیات را تا Drain کامل block می‌کند. credential و pairing خام را در screenshot/support package عمومی قرار نده.
