# REVIEW_ROUND_1 — Web Print dev.20

## مهندسی/معماری

مالکیت چاپ در `includes/printing.php` + `print-agent/v4/api.php` حفظ شد؛ queue/service دوم Browser ساخته نشد. Root causes بررسی‌شده: receipt mismatch action، terminal report evidence، request-body replay conflict، commit-safe wake، FIFO after human reprint، DB transient classification و retirement with outstanding reports. تصمیم‌ها additive و قبل از Go-Live fail-closed هستند.

## UX/UI

سرعت workflow صندوق با wake coalescing حفظ شده ولی wake failure سفارش را fail نمی‌کند. Preview دیگر با DPI ثابت/اولین مقصد false-exact نشان داده نمی‌شود. فرم settings هنگام refresh focus/scroll/unsaved data را حفظ می‌کند. Chromium cases B33/B34/B37/B39/B46 اجرا شدند.

## عملیات کافه/رستوران

برای ambiguous/unknown چاپ دوباره خودکار ممنوع؛ Job ID/timeline و action مناسب محور troubleshooting است. مقصد سالم مستقل نباید به‌دلیل wake خراب متوقف شود. retirement/rotation نیازمند drain است تا report معلق گم نشود.

## QA/امنیت/DB

Strict JSON/type/body validation، spooler evidence، pairing separation و import limits بازبینی شدند. DB concurrency واقعی به‌علت نبود pdo_mysql/MariaDB اجرا نشد و PASS اعلام نشده است. Integration Agent و UAT سخت‌افزار نیز gate باز هستند.
