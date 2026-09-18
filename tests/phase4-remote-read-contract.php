<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$schema=(string)file_get_contents($root.'/public_edge/database/schema.sql');
$projection=(string)file_get_contents($root.'/includes/relay_projection.php');
$bootstrap=(string)file_get_contents($root.'/public_edge/bootstrap.php');
$read=(string)file_get_contents($root.'/public_edge/api/v1/remote/read.php');
$staff=(string)file_get_contents($root.'/public_edge/staff/app.js');
$runtime=(string)file_get_contents($root.'/includes/runtime.php');

function p4check(bool $ok,string $message):void{
    if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}
    echo "PASS: $message\n";
}

p4check(str_contains($schema,'CREATE TABLE IF NOT EXISTS remote_read_models'),'Public has bounded remote read projection store');
foreach(['orders','order_items','table_sessions','settlement_records','inventory_items','inventory_balances','financial_periods'] as $forbidden){
    p4check(!preg_match('/CREATE TABLE IF NOT EXISTS\s+'.preg_quote($forbidden,'/').'\b/i',$schema),'Public schema does not clone canonical '.$forbidden);
}
p4check(str_contains($projection,"'orders.read','operations.read'"),'orders floor projects read authority');
p4check(str_contains($projection,"'finance.read','orders.read','operations.read'"),'cashier projects read authority');
p4check(str_contains($projection,"'preparation.mutate','preparation.read'"),'preparation projects read and existing mutation authority');
p4check(str_contains($projection,"'operations.read','preparation.monitor'"),'shift supervision projects global monitor read');
p4check(!preg_match("/shift_supervision[^\n]+preparation\.mutate/",$projection),'shift supervision alone never gains preparation mutation');
p4check(str_contains($projection,"'inventory.cost.read'"),'inventory cost visibility has separate read capability');
p4check(str_contains($bootstrap,'public_remote_filter_preparation'),'Public filters preparation read by projected areas');
p4check(str_contains($bootstrap,'preparation.monitor'),'monitor capability participates only in read visibility');
p4check(str_contains($read,'public_session_has_capability'),'remote read endpoint rechecks projected capability');
p4check(str_contains($read,"'stale'=>$stale"),'remote read response carries explicit stale state');
p4check(str_contains($runtime,'tools/remote-read-worker.php'),'Runtime supervises read-model sync');
p4check(!str_contains($staff,'/api/v1/realtime/enqueue.php'),'Phase 4 staff UI stays read-only');
p4check(!str_contains($staff,'settlement.commit'),'Phase 4 staff UI does not expose finance mutations');

$publicPhp=[];
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/public_edge',FilesystemIterator::SKIP_DOTS));
foreach($iterator as $file){
    if($file->isFile()&&$file->getExtension()==='php')$publicPhp[]=(string)file_get_contents($file->getPathname());
}
$publicSource=implode("\n",$publicPhp);
foreach(['orders','order_items','table_sessions','settlement_records','inventory_items','inventory_balances'] as $table){
    p4check(!preg_match('/(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+'.preg_quote($table,'/').'/i',$publicSource),'Public PHP never mutates canonical '.$table);
}
echo "Phase 4 remote read boundary contract PASS\n";
