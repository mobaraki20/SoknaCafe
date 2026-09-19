<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$schema=(string)file_get_contents($root.'/database/schema.sql');
$publicSchema=(string)file_get_contents($root.'/public_edge/database/schema.sql');
$draft=(string)file_get_contents($root.'/includes/table_draft.php');
$staffService=(string)file_get_contents($root.'/includes/staff_order_service.php');
$quick=(string)file_get_contents($root.'/staff/api_quick_order.php');
$relay=(string)file_get_contents($root.'/includes/relay_protocol.php');
$quickPage=(string)file_get_contents($root.'/staff/quick-order.php');
$quickJs=(string)file_get_contents($root.'/assets/js/staff-quick-order.js');
$gate=(string)file_get_contents($root.'/tests/run-1360-dev-gate.sh');

function p6c(bool $ok,string $message):void{
    if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}
    echo "PASS: $message\n";
}

p6c(str_contains($schema,'CREATE TABLE IF NOT EXISTS table_drafts'),'Local owns table_drafts');
p6c(str_contains($schema,'CREATE TABLE IF NOT EXISTS table_draft_items'),'Local owns table_draft_items');
p6c(str_contains($schema,'UNIQUE KEY uq_table_drafts_one_active_table (active_table_guard)'),'schema enforces one active draft per table');
$tableBlock='';
if(preg_match('/CREATE TABLE IF NOT EXISTS table_drafts\s*\([\s\S]*?\) ENGINE=/i',$schema,$m))$tableBlock=$m[0];
p6c($tableBlock!==''&&!preg_match('/expire|ttl/i',$tableBlock),'Table Draft has no auto-expiry contract');
p6c(!str_contains($publicSchema,'CREATE TABLE IF NOT EXISTS table_drafts'),'Public does not store Table Draft business state');

p6c(str_contains($draft,'function table_draft_save_tx'),'canonical draft save owner exists');
p6c(str_contains($draft,'function table_draft_cancel_tx'),'canonical draft cancel owner exists');
p6c(str_contains($draft,'function table_draft_finalize_tx'),'canonical draft finalize owner exists');
p6c(str_contains($draft,'staff_order_commit_tx($pdo,$orderData,$user'),'Draft Finalize delegates to canonical Staff Order owner');
p6c(!preg_match('/INSERT\s+INTO\s+orders/i',$draft),'Table Draft owner does not duplicate canonical order INSERT');
p6c(!preg_match('/order_allocate_business_number\s*\(/',$draft),'Table Draft owner does not allocate business number itself');
p6c(!preg_match('/print_enqueue_prep_order\s*\(/',$draft),'Table Draft save/finalize wrapper does not duplicate preparation print side effect');
p6c(!preg_match('/inventory_enqueue_order_event_tx\s*\(/',$draft),'Table Draft owner does not duplicate inventory side effect');
p6c(str_contains($draft,"'version_conflict'"),'optimistic concurrency conflict is explicit');
p6c(str_contains($draft,"state='cancelled'")&&str_contains($draft,"state='finalized'"),'draft closes only through explicit lifecycle states');

p6c(str_contains($staffService,'function staff_order_commit_tx'),'Staff Order commit has canonical service owner');
p6c(str_contains($quick,'staff_order_commit_tx($pdo, $data, $user, $mode)'),'Quick Order consumes canonical Staff Order owner');
p6c(!preg_match('/INSERT\s+INTO\s+orders/i',$quick),'Quick Order endpoint no longer owns order INSERT');
p6c(str_contains($staffService,'$clientToken'),'push idempotency uses canonical staff client token');
p6c(str_contains($quickPage,'window.STAFF_TABLE_DRAFT_API'),'Quick Order page exposes the canonical Table Draft endpoint');
p6c(str_contains($quickJs,'sharedDraftEnabled')&&str_contains($quickJs,'saveDraftNow')&&str_contains($quickJs,"action:'finalize'"),'Quick Order normal mode consumes server-persistent Table Draft lifecycle');
p6c(str_contains($quickJs,'const draftKey = (tableId')&&str_contains($quickJs,'late_accounting.${Number(tableId || 0)}')&&!str_contains($quickJs,'lateAccounting ? `sokna.quick-order.v2.${userKey}.'),'browser draft key is reserved for late-accounting/recovery, not normal Table Draft authority');
p6c(str_contains($gate,'python tests/phase6c-table-draft-browser.py'),'multi-context Table Draft browser test is part of the Linux gate');
$workflow=(string)file_get_contents($root.'/.github/workflows/sokna-ci.yml');
p6c(str_contains($workflow,'php tests/phase6c-table-draft-http.php'),'Realtime Table Draft HTTP/actor-permission test is part of the Public MariaDB gate');

foreach(['table_draft.create','table_draft.edit','table_draft.finalize','table_draft.cancel'] as $kind){
    p6c(str_contains($relay,"'".$kind."'"),'Realtime protocol reserves '.$kind);
}
$deferred=(string)file_get_contents($root.'/includes/relay_protocol.php');
$deferredBlock=substr($deferred,strpos($deferred,'const SOKNA_DEFERRED_KINDS'),strpos($deferred,'const SOKNA_RELAY_REALTIME_KINDS')-strpos($deferred,'const SOKNA_DEFERRED_KINDS'));
p6c(!str_contains($deferredBlock,'table_draft'),'Table Draft is never Deferred-safe');

echo "Phase 6C Table Draft boundary contract PASS\n";
