# قرارداد توزیع Sokna Print Agent

## Source of Truth
- Server/Cafe: سورس و Release سامانه کافه.
- Windows Print Agent: مخزن GitHub `mobaraki20/Pagent`.
- Binary رسمی Agent: فقط **Stable GitHub Release Asset** همان مخزن.
- Protocol: Print API v4 سمت Server و Contract مشترک.

## قواعد dev.22
1. هیچ سورس Agent یا Setup/ZIP باینری Agent داخل Release سامانه کافه Bundle نمی‌شود.
2. صفحه چاپ نسخه پیشنهادی را از `GET /repos/mobaraki20/Pagent/releases/latest` می‌خواند؛ Draft و Prerelease نباید Recommended شوند.
3. فقط Releaseای معتبر است که Asset دقیق `Sokna-Print-Agent-<version>-Setup.exe` را داشته باشد. URL دانلود از repository ثابت + tag معتبر + نام asset معتبر ساخته می‌شود و URL دلخواه پاسخ API trusted نمی‌شود.
4. نتیجه GitHub حداکثر هر ۶ ساعت refresh و در `storage/cache/print-agent-release.json` به‌عنوان Last-Known-Good نگهداری می‌شود. GitHub در هر page view فراخوانی نمی‌شود.
5. اگر GitHub unavailable / rate-limited / malformed باشد، آخرین cache معتبر حتی اگر stale باشد استفاده می‌شود؛ اگر cache هنوز وجود ندارد، fallback رسمی `6.2.2` فقط برای bootstrap استفاده می‌شود. Order/Payment/Inventory/Print runtime به GitHub availability وابسته نیستند.
6. لینک Binary هرگز `releases/latest/download/...` شناور نیست؛ ابتدا metadata stable resolve می‌شود و سپس لینک **versioned Release Asset** ساخته می‌شود.
7. `print_agent_minimum_version()` مستقل از Latest Stable است؛ Minimum Protocol Compatibility با نسخه پیشنهادی نصب یکی نیست.
8. SHA-256 اگر GitHub روی asset فیلد digest معتبر بدهد در metadata حفظ می‌شود؛ Cafe binary را mirror نمی‌کند و checksum جعلی تولید نمی‌کند.
9. GitHub Actions Artifact مرجع نصب Production نیست؛ Published Stable Release Asset مرجع Distribution است.
10. تغییر Agent نباید برای تغییر متن نسخه/لینک در Cafe نیازمند Web Update باشد؛ بعد از انتشار Stable بعدی، cache refresh باید آن را خودکار نمایش دهد.

## Bootstrap پایدار
- Fallback Recommended Agent: `6.2.2`
- Tag: `v6.2.2`
- Setup: `Sokna-Print-Agent-6.2.2-Setup.exe`
- این مقدار فقط Fail-soft bootstrap است و در حالت عادی GitHub Stable metadata مالک نمایش است.

## Target فعلی
- Agent `6.2.3` تا زمانی که فقط RC/Actions Artifact است، توسط resolver به‌عنوان Recommended نمایش داده نمی‌شود.
- بعد از Publish شدن Stable Release رسمی `v6.2.3` با Setup asset معتبر، Web بدون بسته به‌روزرسانی جدید باید در refresh بعدی cache آن را به‌عنوان Recommended نشان دهد.
- A53/B53/UAT-F01 و Physical Printing همچنان Gateهای مستقل انتشار Agent هستند و auto-discovery جای آن‌ها را نمی‌گیرد.
