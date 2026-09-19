<?php
declare(strict_types=1);
require dirname(__DIR__,4).'/bootstrap.php';
$installationId=public_verify_local_signature();$body=public_json_body();
$from=trim((string)($body['from_date']??''));$to=trim((string)($body['to_date']??''));
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)||$to<$from)public_json(['ok'=>false,'error'=>'invalid_period'],400);
$stmt=public_db()->prepare("SELECT state,COUNT(*) c FROM deferred_work WHERE installation_id=? AND occurred_at>=CONCAT(?,' 00:00:00') AND occurred_at<=CONCAT(?,' 23:59:59') GROUP BY state");
$stmt->execute([$installationId,$from,$to]);
$counts=['pending_sync'=>0,'committed'=>0,'needs_review'=>0,'rejected'=>0];
foreach($stmt->fetchAll() as $row)if(array_key_exists((string)$row['state'],$counts))$counts[(string)$row['state']]=(int)$row['c'];
public_json(['ok'=>true,'from_date'=>$from,'to_date'=>$to,'counts'=>$counts,'blocking'=>$counts['pending_sync']+$counts['needs_review']]);
