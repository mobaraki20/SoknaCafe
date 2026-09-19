# Copy/Paste Prompt for the Next Agent

تو مسئول ادامه پروژه SOKNA Cafe هستی. پروژه تا **Phase 6C / 1.36.4-dev.34** کامل شده است. GitHub و handoffها source of truth هستند و تصمیم‌های frozen قبلی را دوباره از کاربر نپرس.

Repository:
`https://github.com/mobaraki20/SoknaCafe`

ابتدا بخوان:
1. `NEXT_AGENT_START_HERE.md`
2. `docs/handoffs/MASTER_HANDOFF_FA.md`
3. `docs/handoffs/CURRENT_STATUS_FA.md`
4. `docs/handoffs/PHASE6C_HANDOFF_FA.md`
5. `DEVELOPER_READ_FIRST_FA.md`
6. R2 Implementation/API/Schema/Risk contracts.

وضعیت معتبر:
- Phase 6C PR #10 merged.
- product merge commit: `ccf0656655702a0b175a7cb9d7521fcb808745b1`.
- post-merge product CI `35438494232`: SUCCESS on Windows / Public+Local MariaDB / Linux+Browser.
- old `PHASE6C_IN_PROGRESS_HANDOFF_FA.md` is superseded.

اولین کار:
- از current `main` یک branch جدید برای Phase 7 بساز.
- قبل از کدنویسی، printing queue/state/claim/reconciliation، Runtime supervisor، notification outbox/worker، Accommodation transport و Center boundary را audit کن.
- Phase 7 باید Print Worker را زیر Runtime internalize کند **بدون** تعویض state machine بالغ چاپ.
- Notification processing را زیر Runtime منتقل کن و durable outbox semantics را حفظ کن.
- Accommodation business contract را حفظ و فقط transport را adapt کن.
- Center را به سمت outbound Local-authoritative integration ببَر.

قواعد:
- Local تنها Business Authority است.
- Public full DB/admin clone نیست.
- Realtime و Deferred جدا می‌مانند.
- Business owner موازی نساز.
- printing reliability و idempotency را برای refactor deployment قربانی نکن.
- هر subphase باید tests/CI/PR/post-merge validation و handoff GitHub داشته باشد.
