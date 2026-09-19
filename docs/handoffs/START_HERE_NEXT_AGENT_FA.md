# START HERE — Next Agent

اگر این پروژه را بدون هیچ زمینه قبلی تحویل گرفته‌ای، **هیچ کدی را تغییر نده** تا این ترتیب را کامل طی کنی:

1. `docs/handoffs/MASTER_HANDOFF_FA.md`
2. `docs/handoffs/CURRENT_STATUS_FA.md`
3. handoff آخرین Phase/Subphase معرفی‌شده در Current Status
4. `DEVELOPER_READ_FIRST_FA.md`
5. `docs/architecture-migration-r2/IMPLEMENTATION_PLAN_FA.md`
6. `docs/architecture-migration-r2/API_CONTRACTS.md`
7. `docs/architecture-migration-r2/SCHEMA_CHANGE_PLAN.md`
8. `docs/architecture-migration-r2/RISK_REGISTER.md`
9. checkpoint آخرین Phase در `docs/architecture-migration-r2/`

## Mandatory source recovery
Repository:
`https://github.com/mobaraki20/SoknaCafe`

```bash
git clone https://github.com/mobaraki20/SoknaCafe.git
cd SoknaCafe
git checkout main
git pull
cat VERSION.txt
```

## Never assume
- هرگز از تاریخچه ChatGPT به‌عنوان source of truth استفاده نکن.
- هرگز Business owner موازی نساز.
- هرگز Realtime و Deferred را merge نکن.
- هرگز Public را Business Authority نکن.
- هرگز UI قدیمی را برای سرعت کار resurrect نکن.
- هرگز یک Phase را بدون CI final-head + post-merge PASS کامل اعلام نکن.

## Current continuation
نقطه ادامه دقیق در:
`docs/handoffs/CURRENT_STATUS_FA.md`

در وضعیت فعلی:
- completed through Phase 6B
- Phase 6C is already IN PROGRESS on `phase/6c-table-draft`
- active head: `756d805912351d6dd539f9922e9bd97144369b63`
- read `docs/handoffs/PHASE6C_IN_PROGRESS_HANDOFF_FA.md` before changing code

اگر `CURRENT_STATUS_FA.md` جدیدتر است، همان وضعیت جدیدتر معتبر است.
