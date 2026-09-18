# AGENT_DEPENDENCIES — Web dev.21

Agent رسمی قابل pin در زمان این تحویل `v6.2.0` با source SHA `2fb431962542ab59973142a9820eb673ab05d763` است. branch جدیدتر `feat/agent-remediation-post-6.2` با SHA مشاهده‌شده `6cf8b794676585eaff1a2a27c4e910667f7d8b46` دارای remediation و `SERVER_DEPENDENCIES.md` است ولی Release رسمی نیست.

## Remediation dev.21 / Agent 6.2.2 → 6.2.3

- مبنای Incident برای این remediation: Agent `6.2.2`, exact source SHA `5a58a10f6326698f80c3408a7a69229754e5c9d4`.
- Target سند: `6.2.3`. در این تحویل به‌دلیل نبود exact source materialized در build container و نبود Windows/.NET gate، artifact 6.2.3 ساخته نشده و وضعیت آن `Candidate / UNVERIFIED` است.
- `v6.2.0` فقط آخرین distribution asset قبلاً pin/verify‌شده وب است؛ این واقعیت به معنی حل defectهای 6.2.2 یا توصیه downgrade نیست.
- Web dev.21 به‌طور دفاعی optional null Heartbeat را می‌پذیرد، اما remediation کامل Claim quarantine/health separation همچنان به target Agent نیاز دارد.

## وابستگی A49/B49

برای Integration واقعی باید Agent production transport روی Windows علیه acceptance server واقعی اجرا شود. ورودی‌ها: `SOKNA_ACCEPTANCE_SERVER_URL`, `SOKNA_ACCEPTANCE_TOKEN_FILE`, `SOKNA_ACCEPTANCE_DESTINATION_KEY`, `SOKNA_ACCEPTANCE_FAULT_PROXY_URL` و opt-in `SOKNA_ACCEPTANCE_ALLOW_MUTATION=I_UNDERSTAND_THIS_MUTATES_ACCEPTANCE_API`. Fault proxy باید response را **بعد از commit upstream** قطع کند. نبود هرکدام یعنی A49/B49 `NOT_RUN`؛ fake/loopback PASS معتبر نیست.

## وابستگی‌های باز Agent برای Web

1. RenderProfile واقعی destination/queue با DPI X/Y و renderer/profile identity باید از runtime واقعی expose و bind شود؛ تا آن زمان exact preview در Web dev.20 fail-closed است.
2. Local Bridge device identity/generation باید بتواند ثابت کند localhost مربوط به همان Agent جفت‌شده است؛ صرف port/pairing heartbeat برای ادعای exact/local-ready کافی نیست.
3. Agent diagnostics pending/auth-blocked/reconciliation backlog در Web ذخیره می‌شود؛ semantics نهایی باید در pair test تثبیت شود.
4. retirement policy وب drain-before-retirement است؛ اگر Agent policy محدود report-after-retire بخواهد، نیازمند قرارداد/authorization جدید و آزمون جداست.
5. `submitted` همچنان Spooler submission است، نه exactly-once physical print.
