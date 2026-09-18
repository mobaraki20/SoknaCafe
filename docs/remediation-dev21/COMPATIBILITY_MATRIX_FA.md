# ماتریس سازگاری چاپ — Sokna 1.36.4-dev.21

تاریخ: 2026-09-12

| Web | Agent | وضعیت | توضیح |
|---|---|---|---|
| 1.36.4-dev.20 | 6.2.2 | Baseline incident | Heartbeat optional-null و Claim replay conflict طبق شواهد حادثه مشکل دارند. |
| 1.36.4-dev.21 | 6.2.2 | Server-compatible mitigation | Web، null اختیاری Heartbeat را می‌پذیرد و `last_heartbeat_at` را مستقل ثبت می‌کند؛ اما defect سمت Agent (omit-null/quarantine/health separation) هنوز در 6.2.2 باقی است. |
| 1.36.4-dev.21 | 6.2.3 | Candidate / UNVERIFIED | نسخه هدف سند است، اما تا build/installer واقعی، G01-G12 و A53/B53 تأیید نشوند Recommended رسمی نیست. |

## قرارداد نسخه

- Recommended فعلی Web عمداً به‌صورت بدون ادعای 6.2.3 باقی مانده است؛ ارتقای Recommended فقط پس از وجود artifact واقعی Agent، checksum، install/upgrade gate و pair test مجاز است.
- Migration Web فقط additive است: `print_agents.last_heartbeat_at` و index مربوطه؛ هیچ backfill از `last_seen_at` انجام نمی‌شود.
- Job/Attemptهای موجود route snapshot خود را حفظ می‌کنند؛ Promote فقط route کارهای جدید را تغییر می‌دهد.
