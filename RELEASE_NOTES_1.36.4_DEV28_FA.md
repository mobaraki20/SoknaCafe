# SOKNA 1.36.4-dev.28 — Phase 2 Public / Realtime Relay

## دامنه این checkpoint
- Public Edge مستقل با دیتابیس محدود به installation/auth projection/session/realtime relay/heartbeat/emergency audit.
- Local همچنان تنها مرجع Business State است؛ Public هیچ Order/Settlement/Inventory canonical table ندارد.
- Realtime Relay با queue/claim lease/ACK و stateهای terminal.
- HMAC-SHA256 روی درخواست‌های Local→Public با timestamp، nonce و replay guard durable.
- Local deduplication قبل از Business dispatch با request_id + canonical request hash.
- TTL/expiry قبل از dispatch؛ درخواست منقضی به Local mutation نمی‌رسد.
- Auth Projection از Local به Public شامل verifier، capability، preparation area و version.
- Emergency controls فقط disable/enable remote و order intake با reason/audit.
- Runtime worker برای relay و projection sync.

## تست‌های اضافه‌شده
- Synthetic exactly-once contract با SQLite روی Linux/Windows CI.
- HMAC tamper detection و canonical JSON.
- Expired request no-surprise-commit.
- Projection permission boundary؛ shift_supervision به‌تنهایی preparation mutation نمی‌دهد.
- HTTP + MariaDB end-to-end برای bind/projection/login/enqueue/dedupe/claim/ACK/result/replay/expiry/emergency.

## عمداً خارج از این checkpoint
- Guest snapshot/theme/media publishing: Phase 3.
- Remote read models: Phase 4.
- Deferred-safe ledger/financial reconciliation: Phase 5.
- Table Draft/Tax/Expenses/explicit sellable-kind domain changes: Phase 6.
- Print Worker internalization: Phase 7.
- Installer pairing/Windows Service finalization: Phase 8.

## UAT باقی‌مانده
- Public staging روی دامنه/TLS واقعی.
- Windows Service SCM واقعی.
- Printer/hardware.
