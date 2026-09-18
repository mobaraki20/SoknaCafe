<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login(['admin']);
if($_SERVER['REQUEST_METHOD']!=='GET')json_response(['success'=>false,'message'=>'فقط GET مجاز است.'],405);
$pdo=db();if(!print_tables_available($pdo))json_response(['success'=>false,'code'=>'printing_not_installed'],503);
$problemSql=print_open_problem_sql('j');$problemCount=(int)$pdo->query("SELECT COUNT(*) FROM print_jobs j WHERE $problemSql")->fetchColumn();
$agents=$pdo->query('SELECT * FROM print_agents ORDER BY id')->fetchAll();$onlineAgents=0;foreach($agents as $agent)if(print_agent_online($agent))$onlineAgents++;
$destinations=$pdo->query('SELECT * FROM print_destinations ORDER BY destination_key')->fetchAll();$readyDestinations=0;$required=0;$readyRequired=0;foreach($destinations as $d){if((int)$d['active']!==1)continue;if(print_destination_operational_route($pdo,(string)$d['destination_key']))$readyDestinations++;if((int)($d['required_for_operation']??1)===1){$required++;if(print_destination_operational_route($pdo,(string)$d['destination_key']))$readyRequired++;}}
$setupComplete=$required>0&&$readyRequired===$required;$healthy=$setupComplete&&$problemCount===0;$lastSubmitted=(string)($pdo->query("SELECT COALESCE(MAX(submitted_at),'') FROM print_jobs WHERE status='submitted'")->fetchColumn()?:'');
json_response(['success'=>true,'generated_at'=>(new DateTimeImmutable('now',new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z'),'overall'=>['healthy'=>$healthy,'label'=>!$setupComplete?'راه‌اندازی مسیرهای ضروری چاپ کامل نشده':($problemCount>0?'چاپ نیازمند رسیدگی است':'چاپ آماده است')],'problem_count'=>$problemCount,'online_agents'=>$onlineAgents,'agent_count'=>count($agents),'ready_destinations'=>$readyDestinations,'destination_count'=>count($destinations),'last_submitted_at'=>print_database_time_to_utc($lastSubmitted)]);
