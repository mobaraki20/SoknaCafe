# ⚠️ SUPERSEDED BY PHASE7R

> بخش‌هایی از این سند که Print Agent را محصول/Installer خارجی و مستقل در نظر می‌گیرند **مردود و superseded** هستند. مرجع فعال: `PHASE7R_PRINTING_RECONCILIATION_FA.md` و تصمیم Frozen R2 مبنی بر «Print Worker داخلی SOKNA؛ محصول جدا نصب نمی‌شود». این فایل فقط برای provenance تاریخی نگه داشته شده است.

# Phase 7 Design Notes — Printing / Notifications / Integrations

Status: COMPLETE
Branch: `phase/7-print-notify-integrations`
Base main: `4dc3f4166ba3e4f2b4876a6d88498af55c10b347`

## Frozen Phase 7 goal
- internalize Print Worker ownership under Local Runtime without replacing the mature print state machine.
- Notification processing is owned by Local Runtime while durable Push outbox semantics remain unchanged.
- Accommodation business contract stays Local-authoritative; only transport is adapted.
- Center moves toward outbound Local-authoritative integration without turning Public into a personnel DB.

## 7A — Runtime service ownership
The current Print Agent is already a hardened .NET Windows Service with durable SQLite, isolated Worker, submission fence, Winspool adapter and SCM recovery. Reimplementing those pieces in PHP would violate R08 and the Agent handoff.

“Internalize under Runtime” therefore means:
1. SOKNA Runtime supervises the installed stable Agent Windows Service.
2. Runtime may detect and start a stopped installed Agent.
3. Runtime does not Claim, Accept, Start, Report, render, spool, mutate Print tables or mirror Agent durable state.
4. Missing Agent is an installation/readiness state, not a failure of unrelated Runtime work.
5. Binary distribution remains the stable `mobaraki20/Pagent` Setup contract; Phase 8 installer may automate install/pairing.
6. Existing Agent/SCM crash recovery remains authoritative after process start.

Notification processing already runs through `tools/push-worker.php --once` in the Runtime registry. Phase 7A makes that ownership explicit; after-response and `api/push_drain.php` remain latency accelerators/fallback only. Transactional `push_event_queue` remains source of truth.

## 7B — Accommodation transport
Preserve business result classification, local transfer/settlement/recovery semantics, ambiguity handling and tracking id. Isolate HTTP transport behind one adapter boundary without creating a second finance state machine.

## 7C — Center outbound adaptation
Replace routine inbound Center→Cafe dependency with outbound Local→Center projection/outbox where possible. Local remains user authority; Public is not personnel DB; old inbound compatibility is not destructively removed before safe migration evidence.

## Exit gates
Each subphase requires dedicated contracts, relevant Windows/MariaDB coverage, Linux full regression, PR merge and post-merge main CI.

## Final Phase 7 evidence
- 7A: PR #12 / main CI 35439861511 SUCCESS.
- 7B: PR #13 / main CI 35440573461 SUCCESS.
- 7C: PR #14 / main CI 35441174227 SUCCESS.
- Phase 7 handoff: docs/handoffs/PHASE7_HANDOFF_FA.md.
