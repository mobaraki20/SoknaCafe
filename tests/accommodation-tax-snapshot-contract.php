<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/functions.php';
require dirname(__DIR__).'/includes/accommodation.php';
$checks=0;
function tax_wire_check(bool $ok,string $message): void {global $checks; $checks++; if(!$ok)throw new RuntimeException($message);}
$s=['version'=>3,'number'=>'TEST-123','issued_at'=>'2026-09-25T12:00:00+03:30','table_name'=>'میز','subtotal'=>100000,'discount'=>10000,'net'=>90000,'taxable'=>90000,'tax'=>9000,'total'=>99000,'items'=>[['name'=>'قهوه','quantity'=>1,'unit_price'=>100000,'line_total'=>100000,'note'=>null,'line_discount'=>10000,'line_net'=>90000,'taxable_amount'=>90000,'tax_rate_bps'=>1000,'tax_amount'=>9000,'line_final'=>99000]]];
$n=accommodation_normalize_invoice_snapshot($s);
accommodation_validate_invoice_snapshot($n);
tax_wire_check($n['tax']===9000 && $n['items'][0]['tax_amount']===9000,'Tax lost in normalization');
tax_wire_check(accommodation_canonical_json($n)===accommodation_canonical_json(accommodation_normalize_invoice_snapshot($n)),'Retry changed canonical snapshot');
foreach(['tax','total','discount','taxable'] as $field){$bad=$s;$bad[$field]++;try{accommodation_normalize_invoice_snapshot($bad);throw new LogicException('Invalid totals accepted');}catch(RuntimeException $e){$checks++;}}
foreach(['tax_amount'=>8999,'tax_rate_bps'=>10001,'quantity'=>0,'line_discount'=>100001,'taxable_amount'=>90001,'line_final'=>99001,'unit_price'=>'100000'] as $field=>$value){$bad=$s;$bad['items'][0][$field]=$value;try{accommodation_normalize_invoice_snapshot($bad);throw new LogicException('Invalid line accepted');}catch(RuntimeException $e){$checks++;}}
$bad=$s;unset($bad['items'][0]['tax_amount']);try{accommodation_normalize_invoice_snapshot($bad);throw new LogicException('Missing tax accepted');}catch(RuntimeException $e){$checks++;}
$zero=$s;$zero['tax']=0;$zero['taxable']=0;$zero['total']=90000;$zero['items'][0]['tax_amount']=0;$zero['items'][0]['taxable_amount']=0;$zero['items'][0]['tax_rate_bps']=0;$zero['items'][0]['line_final']=90000;
tax_wire_check(accommodation_normalize_invoice_snapshot($zero)['version']===3,'Zero tax document lost version');
$GLOBALS['SOKNA_ACCOMMODATION_CONFIG_OVERRIDE']=['enabled'=>true,'base_url'=>'https://stay.example.test/api.php','api_key'=>'test-only'];
$support=false;$failProbe=false;$calls=[];
$GLOBALS['SOKNA_ACCOMMODATION_TRANSPORT']=function($method,$url,$headers,$body,$timeout)use(&$support,&$failProbe,&$calls){parse_str((string)parse_url($url,PHP_URL_QUERY),$q);$calls[]=$q['action'];if($q['action']==='capabilities'){if($failProbe)return ['transport_ok'=>false,'http_status'=>0,'payload'=>[]];return ['transport_ok'=>true,'http_status'=>200,'payload'=>['ok'=>true,'api_version'=>'2.0','capabilities'=>['invoice_tax_snapshot'=>$support,'invoice_snapshot_versions'=>$support?[1,3]:[1]]]];} $p=json_decode($body,true);tax_wire_check($p['amount']===99000 && $p['invoice']['tax']===9000,'Wire amounts changed');return ['transport_ok'=>false,'http_status'=>0,'payload'=>[]];};
$t=['external_order_id'=>'CAFE-S-123','reservation_code'=>'TEST','amount'=>99000,'invoice_snapshot_json'=>accommodation_canonical_json($n)];
$r=accommodation_charge_remote($t);tax_wire_check($r['code']==='unsupported_invoice_version' && $calls===['capabilities'],'Unsupported destination was charged');
$calls=[];$failProbe=true;$r=accommodation_charge_remote($t);tax_wire_check(!$r['ambiguous'] && $r['retryable'] && $calls===['capabilities'],'Capability failure became ambiguous charge');
$calls=[];$failProbe=false;$support=true;$r=accommodation_charge_remote($t);tax_wire_check($calls===['capabilities','charge'] && $r['ambiguous'],'Charge timeout lost ambiguity');
echo "PASS accommodation-tax-snapshot-contract ($checks checks)\n";
