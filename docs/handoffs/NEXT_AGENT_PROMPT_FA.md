# Copy/Paste Prompt for the Next Agent

تو مسئول ادامه پروژه SOKNA Cafe هستی. پروژه تا **Phase 7 / 1.36.4-dev.37** کامل شده است. GitHub و handoffها source of truth هستند.

Repository: `https://github.com/mobaraki20/SoknaCafe`

ابتدا بخوان:
1. `NEXT_AGENT_START_HERE.md`
2. `docs/handoffs/MASTER_HANDOFF_FA.md`
3. `docs/handoffs/CURRENT_STATUS_FA.md`
4. `docs/handoffs/PHASE7_HANDOFF_FA.md`
5. `DEVELOPER_READ_FIRST_FA.md`
6. R2 Implementation/API/Schema/Risk contracts.

وضعیت معتبر:
- Phase 7A PR #12 merged; main CI `35439861511` SUCCESS.
- Phase 7B PR #13 merged; main CI `35440573461` SUCCESS.
- Phase 7C PR #14 merged; merge `b29cf18aea52228fc44e08aac5e2a7c521295f98`; main CI `35441174227` SUCCESS.
- release checkpoint: `1.36.4-dev.37`.

اولین کار:
- از current main یک branch جدید برای Phase 8 بساز.
- updater staging/validation/rollback، backup encryption/integrity/portable restore، Runtime Windows install/service، Public binding/identity/takeover و printer/offsite/push setup را audit کن.
- Windows new/recover install flow، enriched Recovery Set/PITR و machine replacement/Public takeover را additive پیاده کن.
- checkpoint فاز 8 باید restore + takeover drill داشته باشد.

قواعد:
- engine بالغ updater/backup را بازنویسی نکن؛ preserve + extend.
- Local تنها Business Authority است.
- secret/identity material را plaintext/log نکن.
- restore/takeover باید explicit، auditable و rollback/recovery-aware باشد.
- هر subphase باید tests/CI/PR/post-merge validation و GitHub handoff داشته باشد.
