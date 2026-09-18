<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

$fail=[];$check=static function(bool $ok,string $message)use(&$fail):void{if(!$ok)$fail[]=$message;};

$dedup=accommodation_public_error_message('reservation_not_chargeable','این رزرو امکان ثبت هزینه ندارد. بازه اقامت پایان یافته است.');
$check($dedup==='این رزرو امکان ثبت هزینه ندارد. بازه اقامت پایان یافته است.','reservation_not_chargeable message must not duplicate its prefix');

$terminal=['status'=>'failed','last_error_code'=>'reservation_not_chargeable','resolved_at'=>null,'suspicious_response'=>0];
$check(accommodation_transfer_retry_allowed($terminal)===false,'reservation_not_chargeable must not be retryable');
$display=accommodation_transfer_display($terminal);
$check(($display['label']??'')==='ناموفق قطعی · تسویه با روش دیگر','terminal business rejection must have an explicit manual-settlement label');
$check(($display['needs_action']??false)===true,'terminal business rejection still needs operator resolution');

$temp=['status'=>'failed','last_error_code'=>'temporary_failure','resolved_at'=>null];
$check(accommodation_transfer_retry_allowed($temp)===true,'temporary_failure must remain retryable');
$amb=['status'=>'pending','last_error_code'=>'transport_error','resolved_at'=>null,'suspicious_response'=>1];
$check(accommodation_transfer_retry_allowed($amb)===true,'ambiguous transport failure must remain retryable with same identity');

$check(accommodation_result_connection_healthy(['success'=>false,'code'=>'reservation_not_chargeable'])===true,'business rejection must not mark connection unhealthy');
$check(accommodation_result_connection_healthy(['success'=>false,'code'=>'external_order_conflict'])===true,'business conflict response proves API connectivity');
$check(accommodation_result_connection_healthy(['success'=>false,'code'=>'transport_error'])===false,'transport error must mark connection unhealthy');
$check(accommodation_result_connection_healthy(['success'=>false,'code'=>'unauthorized'])===false,'auth failure must mark integration unhealthy');

if($fail){fwrite(STDERR,"dev22 phase2 accommodation FAIL\n- ".implode("\n- ",$fail)."\n");exit(1);}echo "dev22 phase2 accommodation contract PASS\n";
