# Baseline reproduction — 1.36.4-dev.20

Baseline package SHA-256:
`6fb4d6b14cad02e07f84e8154fe0e6b909a45b72401efc59b5384f0574e58fcb`

## RC-01 — Heartbeat optional null

روی helper واقعی baseline، ورودی `bridge_origin: null` برای field اختیاری اجرا شد.
نتیجه واقعی:

`HTTP=422 code=invalid_field_type field=bridge_origin`

بنابراین mismatch optional-null بازتولید شد.

## RC-04 — Printer freshness fallback

در baseline، `includes/printing.php` دارای این semantics بود:

`printer_discovery_at ?? last_seen_at`

در نتیجه نبود discovery مستقل می‌توانست با `last_seen_at` پوشانده شود؛ این رفتار در dev.21 حذف شد.

## RC-02 — Agent claim replay

اجرای محلی روی Agent 6.2.2 در این محیط انجام نشد، چون exact source commit و Windows/.NET runtime قابل materialize/build نبود. بسته‌های قدیمی 6.1.1 جایگزین baseline نشدند.
