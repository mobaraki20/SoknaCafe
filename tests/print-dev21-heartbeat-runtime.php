<?php
declare(strict_types=1);
final class Dev21JsonResponseStop extends RuntimeException { public function __construct(public array $payload, public int $httpStatus){parent::__construct((string)($payload['message']??''));} }
if(!function_exists('json_response')){function json_response(array $payload,int $status=200): never { throw new Dev21JsonResponseStop($payload,$status); }}
if(!function_exists('text_substr')){function text_substr(string $value,int $start,int $length): string { return substr($value,$start,$length); }}
require dirname(__DIR__).'/includes/print_agent_api.php';
$fail=[];
if(print_agent_api_optional_string_field([], 'bridge_origin', 240)!==null)$fail[]='H01_string_omitted';
if(print_agent_api_optional_int_field([], 'last_api_latency_ms',0,600000)!==null)$fail[]='H01_int_omitted';
if(print_agent_api_optional_bool_field([], 'printer_discovery_fresh')!==null)$fail[]='H01_bool_omitted';
if(print_agent_api_optional_string_field(['bridge_origin'=>null], 'bridge_origin',240)!==null)$fail[]='H02_string_null';
if(print_agent_api_optional_int_field(['last_api_latency_ms'=>null], 'last_api_latency_ms',0,600000)!==null)$fail[]='H02_int_null';
if(print_agent_api_optional_bool_field(['printer_discovery_fresh'=>null], 'printer_discovery_fresh')!==null)$fail[]='H02_bool_null';
foreach([
    [['bridge_origin'=>123],fn($d)=>print_agent_api_optional_string_field($d,'bridge_origin',240),'bridge_origin'],
    [['last_api_latency_ms'=>'5'],fn($d)=>print_agent_api_optional_int_field($d,'last_api_latency_ms',0,600000),'last_api_latency_ms'],
    [['printer_discovery_fresh'=>1],fn($d)=>print_agent_api_optional_bool_field($d,'printer_discovery_fresh'),'printer_discovery_fresh'],
] as [$data,$call,$field]){
    try{$call($data);$fail[]='H03_'.$field.'_accepted';}
    catch(Dev21JsonResponseStop $e){if($e->httpStatus!==422||($e->payload['code']??'')!=='invalid_field_type'||($e->payload['field']??'')!==$field)$fail[]='H03_'.$field.'_wrong_error';}
}
if($fail){fwrite(STDERR,'FAIL '.implode(',',$fail).PHP_EOL);exit(1);}echo "PASS H01 H02 H03 heartbeat nullable runtime\n";
