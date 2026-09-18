<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$publicGuest=(string)file_get_contents($root.'/public_edge/guest/index.php');
$publicCompat=(string)file_get_contents($root.'/public_edge/guest/compat.php');
$localGuest=(string)file_get_contents($root.'/menu/index.php');
$sharedView=(string)file_get_contents($root.'/includes/guest_menu_view.php');
$menuJs=(string)file_get_contents($root.'/assets/js/menu.js');
$enqueue=(string)file_get_contents($root.'/public_edge/api/v1/guest/enqueue.php');
$result=(string)file_get_contents($root.'/public_edge/api/v1/guest/result.php');
$createCompat=(string)file_get_contents($root.'/public_edge/api/v1/guest/compat/create_order.php');
$ordersCompat=(string)file_get_contents($root.'/public_edge/api/v1/guest/compat/guest_orders.php');
$waiterCompat=(string)file_get_contents($root.'/public_edge/api/v1/guest/compat/waiter_call.php');
$functions=(string)file_get_contents($root.'/includes/functions.php');
$claim=(string)file_get_contents($root.'/public_edge/api/v1/local/claim.php');

function p3need(bool $ok,string $message):void{
    if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}
    echo "PASS: $message\n";
}

p3need(str_contains($localGuest,"includes/guest_menu_view.php"),'Local menu renders canonical shared guest view');
p3need(str_contains($publicGuest,"includes/guest_menu_view.php"),'Public menu renders canonical shared guest view');
p3need(str_contains($sharedView,"assets/css/guest-menu.css"),'shared guest view owns canonical guest stylesheet');
p3need(substr_count($sharedView,"assets/js/menu.js")===1,'shared guest view owns exactly one canonical menu runtime');
p3need(!str_contains($publicGuest,'guest/app.js'),'Public menu no longer loads forked runtime');

p3need(str_contains($menuJs,'pendingToken'),'canonical browser runtime keeps order idempotency token');
p3need(str_contains($menuJs,'pendingSignature'),'canonical browser runtime reuses token for identical draft');
p3need(str_contains($menuJs,'saveState()')&&str_contains($menuJs,'restoreState()'),'canonical browser runtime persists pending order identity');
p3need(str_contains($createCompat,"public_guest_request_id('guest_order.submit'"),'Public create adapter derives stable Relay request id');
p3need(str_contains($createCompat,"'client_token'=>$client"),'Public create adapter binds stable request id to client token');
p3need(str_contains($publicCompat,'sokna_relay_is_terminal($state)'),'Public compatibility waits for terminal Relay result');
p3need(str_contains($publicCompat,"if($state==='committed')"),'Public compatibility reports success only after committed ACK');
p3need(str_contains($ordersCompat,"'order.edit'")&&str_contains($ordersCompat,"'order.cancel'"),'Public edit/cancel route through Relay');
p3need(str_contains($waiterCompat,"'waiter_call.create'")&&str_contains($waiterCompat,"'waiter_call.status'")&&str_contains($waiterCompat,"'waiter_call.cancel'"),'Public waiter runtime routes through canonical Local owner');

p3need(str_contains($enqueue,"['guest_order.submit','waiter_call.create']"),'anonymous generic Public mutation allowlist remains guest-create only');
foreach(['settlement.commit','preparation.mutate','order.edit','order.cancel','table_draft.finalize'] as $forbidden){
    p3need(!str_contains($enqueue,"'".$forbidden."'"),'anonymous generic guest endpoint cannot enqueue '.$forbidden);
}
p3need(str_contains($enqueue,'$now+45'),'generic guest realtime TTL remains server-owned and short');
p3need(str_contains($enqueue,'public_guest_action_state'),'generic guest create requires fresh Public/Local action state');
p3need(str_contains($result,'hash_equals((string)$payload[\'client_token\'],$clientToken)'),'guest result lookup is bound to client token');
p3need(str_contains($result,"state='queued'"),'result lookup expires only never-claimed queued work');
p3need(!str_contains($result,"state IN ('queued','claimed')"),'result lookup cannot erase claimed ambiguity');

p3need(str_contains($publicGuest,'public_guest_action_state'),'Public renderer computes degraded action state');
p3need(str_contains($publicGuest,"$availability['order_acceptance']=['cafe'=>false"),'degraded Public renderer disables mutation while retaining snapshot view');
p3need(str_contains($publicGuest,'require dirname(__DIR__,2).\'/includes/guest_menu_view.php\''),'degraded mode still reaches shared view');
p3need(str_contains($functions,'function public_guest_base_url()'),'QR owner has Public guest route');
p3need(str_contains($functions,'if($public!==\'\') return $public'),'QR uses Public when pairing is configured');
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
