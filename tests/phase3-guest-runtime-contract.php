<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$app=(string)file_get_contents($root.'/public_edge/guest/app.js');
$enqueue=(string)file_get_contents($root.'/public_edge/api/v1/guest/enqueue.php');
$result=(string)file_get_contents($root.'/public_edge/api/v1/guest/result.php');
$guest=(string)file_get_contents($root.'/public_edge/guest/index.php');
$functions=(string)file_get_contents($root.'/includes/functions.php');
$claim=(string)file_get_contents($root.'/public_edge/api/v1/local/claim.php');

function p3need(bool $ok,string $message):void{
    if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}
    echo "PASS: $message\n";
}

p3need(str_contains($app,'pendingStorageKey'),'browser persists pending guest mutation identity');
p3need(str_contains($app,'request_id:record.requestId'),'browser retries the same request id');
p3need(str_contains($app,'client_token:record.clientToken'),'browser retries the same client token');
p3need(str_contains($app,"data.state==='committed'"),'browser success is gated on committed terminal result');
p3need(str_contains($app,"void resumePending('guest_order.submit')"),'browser resumes uncertain order after reload');
p3need(str_contains($app,"void resumePending('waiter_call.create')"),'browser resumes uncertain waiter call after reload');
p3need(!str_contains($app,'.innerHTML'),'Public guest runtime does not inject snapshot text through innerHTML');

p3need(str_contains($enqueue,"['guest_order.submit','waiter_call.create']"),'anonymous Public mutation allowlist is guest-only');
foreach(['settlement.commit','preparation.mutate','order.edit','order.cancel','table_draft.finalize'] as $forbidden){
    p3need(!str_contains($enqueue,"'".$forbidden."'"),'anonymous guest endpoint cannot enqueue '.$forbidden);
}
p3need(str_contains($enqueue,'$now+45'),'guest realtime TTL is server-owned and short');
p3need(str_contains($enqueue,'public_guest_action_state'),'guest mutation requires fresh Public/Local action state');
p3need(str_contains($result,"hash_equals((string)$payload['client_token'],$clientToken)"),'guest result lookup is bound to client token');
p3need(str_contains($result,"state='queued'"),'result lookup expires only never-claimed queued work');
p3need(!str_contains($result,"state IN ('queued','claimed')"),'result lookup cannot erase claimed ambiguity');

p3need(str_contains($guest,'public_guest_action_state'),'Public renderer computes degraded action state');
p3need(str_contains($guest,'$degraded='),'Public renderer has degraded read state');
p3need(str_contains($functions,'function public_guest_base_url()'),'QR owner has Public guest route');
p3need(str_contains($functions,"if($public!=='') return $public"),'QR uses Public when pairing is configured');
p3need(str_contains($functions,"return canonical_asset('menu/')"),'QR retains safe Local fallback before Public pairing');

p3need(str_contains($claim,"state='queued' AND expires_at<=UTC_TIMESTAMP()"),'only queued requests expire without reconciliation');
p3need(str_contains($claim,"state='claimed' AND lease_expires_at<UTC_TIMESTAMP()"),'claimed work remains re-claimable for reconciliation');

$guestPhp=[];
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/public_edge',FilesystemIterator::SKIP_DOTS));
foreach($iterator as $file){
    if($file->isFile()&&$file->getExtension()==='php')$guestPhp[]=(string)file_get_contents($file->getPathname());
}
$publicSource=implode("\n",$guestPhp);
foreach(['orders','order_items','table_sessions','settlement_records','inventory_movements'] as $canonical){
    p3need(!preg_match('/(?:INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+'.preg_quote($canonical,'/').'/i',$publicSource),'Public PHP has no canonical write to '.$canonical);
}
echo "Phase 3 guest runtime boundary contract PASS\n";
