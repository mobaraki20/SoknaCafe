# Sokna 1.36.4-dev.16 — Print Reliability RC

این نسخه RC برای تست مالک است و Production Final نیست.

- retry cycle بدون reset تاریخچه و endpoint reconciliation برای Attempt.
- UTC wire timestamps و اعتبارسنجی دقیق‌تر Print API.
- شمارش صحیح مشکلات باز، readiness مشترک و تفکیک مقصدهای ضروری.
- retire Agent سابقه‌دار، validation تراکنشی مقصد و reroute سازگار با نوع سند.
- test print واقعی customer/preparation.
- Template origin/revision/hash، حذف/جایگزینی اتمیک و حذف گزینه preparation بی‌اثر.
- Local Wake محدود به loopback برای کاهش latency صندوق؛ Poll fallback حفظ شده است.
- exact preview از Renderer محلی با fallback تقریبی.
- schema clean-install و migration رسمی از 1.36.4-dev.15.

آزمون‌های سخت‌افزاری پرینتر واقعی، Paper Out/Spooler و soak همچنان UAT_REQUIRED هستند.
