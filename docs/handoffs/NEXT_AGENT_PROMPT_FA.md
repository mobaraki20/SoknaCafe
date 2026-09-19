# Copy/Paste Prompt for the Next Agent

این متن را تقریباً بدون تغییر به ایجنت بعدی بده.

---

تو مسئول ادامه پروژه SOKNA Cafe هستی. این پروژه قبلاً تا Phase 6B کامل شده و Phase 6C در حال اجراست. از من سؤال‌های معماری/محصولی که قبلاً تصمیم‌گیری شده نپرس. GitHub و handoffها source of truth هستند.

Repository:
`https://github.com/mobaraki20/SoknaCafe`

ابتدا بدون تغییر کد این فایل‌ها را به ترتیب بخوان:
1. `NEXT_AGENT_START_HERE.md`
2. `docs/handoffs/MASTER_HANDOFF_FA.md`
3. `docs/handoffs/CURRENT_STATUS_FA.md`
4. `docs/handoffs/PHASE6C_IN_PROGRESS_HANDOFF_FA.md`
5. `docs/architecture-migration-r2/PHASE6C_DESIGN_NOTES_FA.md`
6. `DEVELOPER_READ_FIRST_FA.md`
7. `docs/architecture-migration-r2/IMPLEMENTATION_PLAN_FA.md`
8. `docs/architecture-migration-r2/API_CONTRACTS.md`
9. `docs/architecture-migration-r2/SCHEMA_CHANGE_PLAN.md`
10. `docs/architecture-migration-r2/RISK_REGISTER.md`

وضعیت کد:
- verified safe main: `3b1c35c8bf7e06a512194559736fbba0303fec46`
- completed checkpoint: `1.36.4-dev.33` / Phase 6B
- active branch: `phase/6c-table-draft`
- active branch head: `756d805912351d6dd539f9922e9bd97144369b63`
- branch 30 commits ahead of main, 0 behind.
- latest CI run: `35426707212`
- Windows PASS.
- Public/MariaDB PASS.
- Linux FAIL only in current regression path after Phase6C contract passes.

اولین کار تو:
- Phase 6C را از صفر نساز.
- branch موجود را checkout کن.
- failure فعلی `tests/itemized-settlement-contract.py` را بررسی کن.
- سه late-accounting check به احتمال زیاد owner-drift تست هستند چون behavior به `includes/staff_order_service.php` منتقل شده.
- `runtime edit locks` را کورکورانه به تست منتقل نکن؛ با نسخه main قبل از extraction مقایسه کن و مطمئن شو invariant واقعاً در canonical service حفظ شده. اگر behavior گم شده، product را اصلاح کن.
- بعد full CI را سبز کن، handoff Phase 6C را کامل کن، PR/merge با expected head SHA انجام بده، post-merge CI main را سبز کن و فقط بعد Phase 6C را COMPLETE اعلام کن.

قواعد غیرقابل‌مذاکره:
- Local تنها Business Authority است.
- Public full DB/admin clone نیست.
- Realtime و Deferred دو queue/state machine جدا هستند.
- Permission system دوم نساز.
- UI/Design System فعلی owner است.
- Business owner موازی نساز؛ owner فعلی را پیدا و refactor کن.
- Table Draft قبل از Finalize هیچ order/business-number/preparation/inventory/finance/receipt side effect ندارد.
- Table Draft هیچ auto-expiry ندارد.
- Table Draft Remote فقط هنگام reachable بودن Local کار می‌کند و هرگز Deferred-safe نیست.
- موفقیت mutation فقط بعد از Local commit معتبر است.
- lost ACK نباید duplicate mutation بسازد.
- printing state machine را قبل از Phase 7 بی‌دلیل بازطراحی نکن.

روش کار:
- تصمیم‌های فنی را خودت بگیر.
- فقط اگر یک تصمیم واقعی محصولی در source/R2/handoff باز است آن را با کاربر مطرح کن.
- برای هر subphase branch/contract/tests/CI/PR/post-merge CI داشته باش.
- بعد از هر subphase، GitHub handoff را به‌روز کن تا ایجنت بعدی بدون تاریخچه گفتگو ادامه دهد.
- اگر owner یک فایل جابه‌جا شد، regression test را به owner جدید وصل کن؛ invariant را ضعیف نکن.

در پایان هر checkpoint، حتماً این‌ها را update کن:
- `docs/handoffs/CURRENT_STATUS_FA.md`
- handoff همان Phase/Subphase
- `docs/handoffs/MASTER_HANDOFF_FA.md` اگر source map یا تصمیم‌های مهم عوض شده
- exact branch/head/PR/merge SHA/CI run IDs
- known failures/UAT gaps/next exact action

---

این پروژه باید بدون نیاز به پرسیدن مجدد هدف یا مسیر از کاربر ادامه یابد.
