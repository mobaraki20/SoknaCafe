<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$protocol=(string)file_get_contents($root.'/includes/relay_protocol.php');
$publicSchema=(string)file_get_contents($root.'/public_edge/database/schema.sql');
$localSchema=(string)file_get_contents($root.'/database/schema.sql');
$deferred=(string)file_get_contents($root.'/includes/deferred.php');
$worker=(string)file_get_contents($root.'/tools/deferred-worker.php');
$periods=(string)file_get_contents($root.'/admin/financial_periods.php');
$staff=(string)file_get_contents($root.'/public_edge/staff/app.js');
function p5(bool $ok,string $m):void{if(!$ok){fwrite(STDERR,"FAIL: $m\n");exit(1);}echo "PASS: $m\n";}

p5(str_contains($protocol,'SOKNA_DEFERRED_STATES'),'Deferred states are explicit');
p5(str_contains($protocol,"['pending_sync','committed','needs_review','rejected']"),'Deferred state machine is frozen');
p5(str_contains($protocol,'SOKNA_DEFERRED_KINDS'),'Deferred kinds are isolated');
foreach(['settlement.commit','preparation.mutate','table_draft.finalize','inventory.count_finalize','subscriber.payment_reversal','expense.reversal'] as $forbidden){
    $block=substr($protocol,strpos($protocol,'const SOKNA_DEFERRED_KINDS'),strpos($protocol,'];',strpos($protocol,'const SOKNA_DEFERRED_KINDS'))-strpos($protocol,'const SOKNA_DEFERRED_KINDS')+2);
    p5(!str_contains($block,$forbidden),'Realtime/local-only kind excluded from Deferred: '.$forbidden);
}
p5(str_contains($publicSchema,'CREATE TABLE IF NOT EXISTS deferred_work'),'Public has separate deferred store');
p5(str_contains($publicSchema,'CREATE TABLE IF NOT EXISTS realtime_requests'),'Realtime store remains separate');
$deferredTable='';
if(preg_match('/CREATE TABLE IF NOT EXISTS deferred_work\s*\([\s\S]*?\) ENGINE=/i',$publicSchema,$m))$deferredTable=$m[0];
p5($deferredTable!==''&&!preg_match('/\bexpires_at\b/i',$deferredTable),'Deferred work has no realtime expiry contract');
foreach(['deferred_work_receipts','deferred_review_items','financial_period_close_overrides','expense_categories','expenses'] as $table)p5(str_contains($localSchema,'CREATE TABLE IF NOT EXISTS '.$table),'Local Phase 5 table exists: '.$table);
p5(str_contains($deferred,"state='needs_review'")||str_contains($deferred,"'needs_review'"),'Local has needs-review persistence');
p5(str_contains($deferred,'closed_financial_period'),'Closed period routes late work to review');
p5(str_contains($deferred,'public_reconcile_pending'),'Resolved review is durably reconciled to Public');
p5(str_contains($deferred,'sokna_deferred_period_close_status'),'Financial close has Public/deferred preflight');
p5(str_contains($periods,'override_deferred')&&str_contains($periods,'override_reason'),'Close override requires explicit reason');
p5(str_contains($periods,'resolve_deferred_review'),'Manager can explicitly resolve deferred review');
p5(str_contains($worker,"/api/v1/local/deferred/claim.php")&&str_contains($worker,"/api/v1/local/deferred/ack.php"),'Deferred worker uses separate claim/ACK path');
p5(!str_contains($worker,"/api/v1/local/claim.php"),'Deferred worker never consumes realtime queue');
p5(str_contains($staff,'pending_sync')&&str_contains($staff,'needs_review'),'Remote UI distinguishes queued/review states from commit');
p5(str_contains($staff,"res.state==='pending_sync'"),'Remote UI does not report enqueue as final business success');
echo "Phase 5 deferred boundary contract PASS\n";
