# Architecture Gap Analysis — SOKNA dev.26 vs Frozen Handoff

## نتیجه
سورس dev.26 یک Local modular monolith نسبتاً بالغ است، اما معماری دو-Deployable و remote/deferred contracts هنداور هنوز پیاده نشده‌اند. استراتژی صحیح **Preserve mature Local business engines + Add/Wrap target infrastructure + Refactor only where Frozen contract requires** است.

| Area | Current | Target | Gap | Action |
|---|---|---|---|---|
| Local authority | یک DB/اپ Local، handlerهای مستقیم | Local تنها Business authority | هم‌راستا | PRESERVE |
| Module framework | Registry 13 دامنه | Modular Monolith با owner روشن | ownerهای جدید کم است | EXTEND registry، نه plugin system |
| Public deployable | وجود ندارد | Guest edge + remote gateway + relay/snapshot | کامل | ADD |
| Realtime relay | وجود ندارد | durable request/result؛ commit only Local | کامل | ADD |
| Deferred-safe | پراکنده/Local-only | queue جدا با pending/committed/needs_review/rejected | کامل | ADD |
| Remote identity | auth فقط Local | minimal Public projection | کامل | ADD |
| Guest menu | `menu/index.php` مستقیم DB Local | Public published snapshot؛ degraded read | بنیادی | REFACTOR transport، PRESERVE renderer |
| Guest submit | Local direct | Public→Relay→Local commit/result | transport gap | WRAP canonical handler |
| Remote read models | ندارد | permission-aware stale snapshots | کامل | ADD |
| Table Draft | browser/order flow؛ نه server draft | 1 active server draft/table | کامل | ADD |
| Service Item | implicit behavior | explicit `menu_item/service_item` | مدل ناقص | REFACTOR additive |
| Tax | ندارد | optional module + effective history + line snapshots | کامل | ADD |
| Expenses | ندارد | simple immutable/reversible expense domain | کامل | ADD |
| Supply | بالغ، single receive | batch + deferred-safe | جزئی | PRESERVE + EXTEND |
| Inventory count | draft/finalize موجود | remote draft, Local finalize | خوب | ADAPT ingress only |
| Financial close | open-operation checks؛ بدون deferred gate | pending/unknown Public gate + audited override + late review | مهم | REFACTOR |
| Preparation auth | feed/action visibility conflated | visible vs actionable | defect مشخص | REFACTOR per Frozen rule |
| Printing | durable Print Agent v4 جدا | internal Local Runtime worker | deployment gap | PRESERVE queue, INTERNALIZE worker |
| Notifications | outbox + CLI/request worker | Runtime worker; in-app truth | ownership gap | PRESERVE queue, MOVE processing |
| Accommodation | mature direct Local | preserve + adapt transport | کم | PRESERVE |
| Center | inbound S2S endpoint موجود | prefer outbound Local→Center | معماری | REFACTOR |
| Updater | mature web updater | single `.soknaupdate`, side-by-side/runtime aware | جزئی | ADAPT |
| Recovery | backup/restore موجود | richer Recovery Set + takeover | جزئی/زیاد | EXTEND |
| Local runtime | ندارد | Windows managed service | کامل | ADD |
| Local HTTPS/discovery | web stack فعلی | managed local HTTPS + `sokna.local` concept | کامل | ADD |
| Observability | پراکنده | unified health/log/diagnostics/support bundle | متوسط | ADD/CONSOLIDATE |
| Theme/media publish | Local UI/assets | strict theme package + public media revision | کامل | ADD |
| Push device admin | `admin/push_devices.php` موجود | no device-management product surface | تعارض UX | REMOVE/replace with diagnostics in Phase 9 |
| Baseline release metadata | mix dev26/dev23 | single version truth | defect | FIX in 0A |

## Blocker rules هنگام پیاده‌سازی
1. هیچ validator مالی/سفارش/انبار در Public business owner نمی‌شود.
2. Realtime request پس از expiry/ambiguous result نباید بعداً surprise-commit شود.
3. Deferred-safe هرگز برای settlement/order-finalize/preparation/Table Draft استفاده نمی‌شود.
4. Public schema فقط projection/relay/snapshot/pending محدود است.
5. migrationها additive-first، idempotent و rollback/recovery-aware باشند.
6. UI/DS فعلی بدون نیاز Frozen بازطراحی نمی‌شود.
