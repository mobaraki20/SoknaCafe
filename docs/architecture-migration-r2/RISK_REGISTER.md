# Risk Register — SOKNA Migration

| ID | Risk | Severity | Likelihood | Mitigation / Gate | Phase |
|---|---|---:|---:|---|---|
| R01 | Public تبدیل به Business DB دوم شود | Blocker | Medium | schema review + no business SQL owner in Public + contract tests | 2-9 |
| R02 | Realtime request بعد از timeout/ساعات بعد surprise-commit شود | Blocker | Medium | expiry revalidation + terminal state + idempotent result lookup | 2 |
| R03 | Deferred و Realtime queue یکی شوند | Blocker | Medium | stores/contracts/tests جدا | 2/5 |
| R04 | late sync دوره مالی بسته را تغییر دهد | Blocker | Medium | period gate + late correction review + immutable audit | 5 |
| R05 | shift supervisor mutation preparation بگیرد | Blocker | High (bug current) | visible/actionable split + server permission tests | 6 |
| R06 | current red baseline Regressionها را پنهان کند | High | High | Checkpoint 0A و green static gate قبل Phase 1 | 0A |
| R07 | duplicate business validation هنگام extract از handlerها | High | Medium | canonical service extraction; wrappers only | 2/3 |
| R08 | Print refactor reliability فعلی را خراب کند | High | Medium | preserve queue/state machine; internalize deployment incrementally | 7 |
| R09 | Tax total با receipt/checkout mismatch | Blocker | Medium | one calculator + snapshot + golden vectors | 6 |
| R10 | batch receipt partial-commit شود | High | Medium | validate-all then one transaction; idempotency key | 6 |
| R11 | Public credential projection بیش از حد داده نگه دارد | High | Medium | minimal projection + secret review + rotation/revoke | 2 |
| R12 | replay/duplicate remote mutation | High | Medium | HMAC timestamp/nonce/request-id + durable idempotency | 2/5 |
| R13 | Public shared hosting محدودیت runtime داشته باشد | High | Medium | no daemon/redis/ws dependency; cron+DB+HTTP only | 2 |
| R14 | Local service Windows با Apache/PHP lifecycle conflict کند | High | Medium | runtime owns workers/health only; staged pilot/rollback | 1 |
| R15 | PWA cache نسخه قدیمی UI/contract بدهد | High | Current | single release version source + cache contract tests | 0A/3 |
| R16 | UI redesign ناخواسته workflow جاری را تغییر دهد | Medium | Medium | source UI owner freeze + visual/workflow regression | all |
| R17 | machine recovery old private key را clone کند | High | Low | fresh installation identity + atomic takeover + revoke old | 8 |
| R18 | Center inbound path Local را اینترنتی/وابسته کند | Medium | Medium | outbound Local→Center adaptation | 7 |
| R19 | MySQL-dependent gates در CI فعلی اجرا نشوند | High | Current | mark BLOCKED_ENVIRONMENT؛ add MariaDB CI/pilot gate before release | 0-9 |
| R20 | historical finance rows با tax/service migration rewrite شوند | High | Low | additive snapshots; no historical semantic rewrite | 6 |
| R21 | module disable history را حذف/غیرقابل خواندن کند | Blocker | Low | module lifecycle contract tests; no destructive disable | 6/9 |
| R22 | old routes/workers بعد از migration owner دوم باقی بمانند | High | Medium | route/mutation matrix + Phase 9 dead-path removal | 9 |


## Phase 5 Mitigation Evidence — 1.36.4-dev.31
- Queue merge / semantic bleed: mitigated by separate `deferred_work` table, separate endpoints and separate Runtime worker; static contract rejects Realtime/local-only kinds.
- Duplicate commit / lost ACK: mitigated by Local `deferred_work_receipts` unique installation+request identity and persisted terminal result before Public ACK.
- Closed-period silent mutation: mitigated by period lookup on `occurred_at`; closed period creates `late_correction` review before any domain mutation.
- Duplicate late correction: unique review per receipt + unique receipt request identity.
- Financial close with unknown remote state: normal close blocks when paired Public status is unknown/unreachable; explicit override stores actor/reason/status snapshot.
- Permission drift: Public capability is first gate only; Local active user/current capability is revalidated at dispatch/review.
- Stale stock/count/supply state: expected version/state conflicts route to `needs_review`; no blind overwrite.
- Inventory count authority drift: Local UI and Deferred-safe both use `inventory_count_update_line_locked()`; finalize remains separate Local-only owner.
- Expense double counting: general Expenses owner is separate; Supply/Inventory purchase receipt is not auto-created as Expense.
- Public becoming second DB: no canonical business tables added; only Deferred envelope/result state and bounded read models.
