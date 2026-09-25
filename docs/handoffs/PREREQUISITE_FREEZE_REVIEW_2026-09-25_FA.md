# بازبینی فنی پیش‌نیازهای ثابت ویندوز

فایل‌های واقعی در Windows CI از providerهای ثبت‌شده دریافت و با هش candidate تطبیق داده شدند. شناسه run: 36130815720؛ commit: da91820729ba0c5ab39b5055374a77d8ab6631c4؛ artifact: 10861238902.

https://github.com/mobaraki20/SoknaCafe/actions/runs/36130815720

SHA-256 آرشیو شاهد: `35c6b8d50a27c87122dc209c5ac825978d1be14b3c3119e10bb6610633c4e85a`، هنگام دریافت دوباره تطبیق داده شد. چهار ردیف lock با candidate و evidence از نظر شناسه، هش، اندازه و سیاست امضا مقایسه شدند. هش candidate با فایل فعلی یکی است و نسخه با VERSION.txt برابر است. امضای VC Runtime معتبر و متعلق به Microsoft Corporation است؛ سه فایل دیگر با hash بررسی شدند و ادعای Authenticode برای آن‌ها نداریم.

با مجوز مالک برای تکمیل و انتشار، بازبینی فنی انجام و release-lock.json ثبت شد. این فقط تثبیت artifact پیش‌نیاز است؛ تأیید نصب نهایی، سازگاری کامل، حق بازتوزیع همه بسته‌ها یا UAT نیست. JSON شاهد با محتوای یکسان و قالب‌بندی LF ثبت شده است. download end-user و مالکیت سرویس shared هنوز طبق قرارداد فعلی تغییر نکرده‌اند.

مسیر بعد: CI کامل New/Repair/Recover از همین lock؛ سپس یکپارچه‌سازی تجربه Setup.exe و پذیرش محصول. هر تغییر نسخه/URL/hash نیازمند freeze و شاهد جدید است.
