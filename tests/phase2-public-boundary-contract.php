<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$schema=(string)file_get_contents($root.'/public_edge/database/schema.sql');
$bootstrap=(string)file_get_contents($root.'/public_edge/bootstrap.php');
$control=(string)file_get_contents($root.'/public_edge/api/v1/emergency/control.php');
$enqueue=(string)file_get_contents($root.'/public_edge/api/v1/realtime/enqueue.php');
function must(bool $ok,string $m):void{if(!$ok){fwrite(STDERR,"FAIL: $m\n");exit(1);}echo "PASS: $m\n";}

foreach(['installations','auth_projections','public_sessions','realtime_requests','request_nonces','installation_heartbeats','emergency_audit'] as $table){
    must(str_contains($schema,'CREATE TABLE IF NOT EXISTS '.$table),'Public allowed table exists: '.$table);
}
foreach(['orders','order_items','table_sessions','settlements','payments','inventory_items','inventory_movements','expenses','purchases','purchase_items','financial_periods','invoices'] as $forbidden){
    must(!preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?'.preg_quote($forbidden,'/').'/i',$schema),'Public has no canonical '.$forbidden.' table');
}
must(!preg_match('/shared_secret\s+(?:VARCHAR|TEXT|CHAR)/i',$schema),'relay shared secret is not stored as plaintext DB column');
must(str_contains($bootstrap,'request_nonces'),'Public replay guard is durable');
must(str_contains($enqueue,'sokna_relay_request_hash'),'Public idempotency uses canonical request hash');
must(str_contains($enqueue,'request_id_conflict'),'request_id payload conflict is explicit');
foreach(['disable_remote','enable_remote','disable_order_intake','enable_order_intake'] as $action) must(str_contains($control,$action),'emergency action allowlisted: '.$action);
foreach(['settlement','refund','inventory','order_edit','force_commit'] as $forbiddenAction) must(!str_contains($control,"'".$forbiddenAction."'"),'emergency console cannot '.$forbiddenAction);
echo "Phase 2 Public boundary contract PASS\n";
