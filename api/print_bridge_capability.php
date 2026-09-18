<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_capability_any(['orders_floor','cashier_accounts']);
if($_SERVER['REQUEST_METHOD']!=='GET')json_response(['success'=>false,'message'=>'Method Not Allowed'],405);
$raw=trim((string)($_GET['destinations']??''));$requested=[];
foreach(array_slice(array_filter(array_map('trim',explode(',',$raw))),0,20) as $key){if(preg_match('/^[a-z0-9_:-]{1,40}$/i',$key))$requested[$key]=true;}
if(!$requested)json_response(['success'=>true,'bridges'=>[]]);
$base=app_base_url();$parts=parse_url($base);$expectedOrigin='';
if(is_array($parts)&&isset($parts['scheme'],$parts['host'])){$expectedOrigin=strtolower($parts['scheme']).'://'.strtolower($parts['host']).(isset($parts['port'])?':'.(int)$parts['port']:'');}
$bridges=[];
foreach(array_keys($requested) as $key){
    $route=print_destination_operational_route($pdo,$key);if(!$route)continue;
    $st=$pdo->prepare('SELECT id,name,agent_version,last_seen_at,bridge_protocol_version,bridge_port,bridge_pairing_id,bridge_origin,bridge_runtime_seen_at FROM print_agents WHERE id=? AND active=1 AND retired_at IS NULL LIMIT 1');
    $st->execute([(int)$route['agent_id']]);$agent=$st->fetch();if(!$agent)continue;
    $port=(int)($agent['bridge_port']??0);$pairing=trim((string)($agent['bridge_pairing_id']??''));$protocol=(int)($agent['bridge_protocol_version']??0);$origin=strtolower(trim((string)($agent['bridge_origin']??'')));
    $seen=strtotime((string)($agent['bridge_runtime_seen_at']??''));$fresh=$seen!==false && time()-$seen<=45;
    if(!$fresh||$protocol!==1||$port<1024||$port>65535||!preg_match('/^[A-Za-z0-9_-]{20,128}$/',$pairing))continue;
    if($expectedOrigin===''||$origin!==$expectedOrigin)continue;
    $destination=print_destination($pdo,$key,false);if(!$destination)continue;
    $bridges[]=[
        'destination_key'=>$key,'destination_label'=>(string)$destination['label'],'destination_type'=>(string)$destination['destination_type'],
        'paper_width_mm'=>(float)$destination['paper_width_mm'],'printable_width_mm'=>(float)$destination['printable_width_mm'],
        'agent_id'=>(int)$agent['id'],'agent_name'=>(string)$agent['name'],'agent_version'=>(string)($agent['agent_version']??''),
        'port'=>$port,'pairing_id'=>$pairing,'protocol_version'=>1,
        // The current Agent remediation contract does not expose a queue-bound RenderProfile to Web yet.
        // Never label a caller-supplied DPI as exact profile truth.
        'render_profile'=>null,'exact_preview_ready'=>false,'readiness_reason'=>'render_profile_unavailable',
    ];
}
json_response(['success'=>true,'bridges'=>$bridges,'capability_semantics'=>'runtime_listener_fresh_but_local_device_identity_unverified']);
