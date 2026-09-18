# PRINT_LATENCY_RESULTS — dev.20

## نتیجه

End-to-end print latency (commit → Agent claim/accept/start → Windows Spooler submission یا paper exit) در این محیط **NOT_RUN** است، چون Windows Agent واقعی، Printer Queue و پرینتر هدف در اختیار نبود. بنابراین P50/P95/max چاپ واقعی گزارش نمی‌شود و heartbeat/browser clock به‌جای آن استفاده نشده است.

## evidence محدود Browser

B34 روی Chromium واقعی deadline capability bootstrap را آزمود؛ timeout bounded بود و lock بعد از timeout آزاد شد. این اندازه‌گیری فقط رفتار Browser/Bridge fallback است، نه latency چاپ. Raw evidence در `artifacts/web-print-dev20/browser/B34.json` قرار دارد.

برای پذیرش نهایی باید نمونه‌های خام یک clock domain قابل اعتماد یا timestampهای مرحله‌ای با حدود clock skew ثبت و سپس P50/P95/max جدا برای commit→wake، Agent poll/claim، accept/start و spooler submission محاسبه شود. Paper-exit به‌عنوان exactly-once قابل استنتاج از Spooler نیست.
