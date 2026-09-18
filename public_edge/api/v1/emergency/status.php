<?php
declare(strict_types=1); require dirname(__DIR__,3).'/bootstrap.php'; public_emergency_auth();
$installationId=trim((string)($_GET['installation_id']??'')); if($installationId==='') public_json(['ok'=>false,'error'=>'installation_id_required'],400);
$stmt=public_db()->prepare('SELECT i.installation_id,i.display_name,i.active,i.remote_enabled,i.order_intake_enabled,h.local_version,h.runtime_status,h.last_seen_at FROM installations i LEFT JOIN installation_heartbeats h ON h.installation_id=i.installation_id WHERE i.installation_id=? LIMIT 1');$stmt->execute([$installationId]);$row=$stmt->fetch();if(!$row)public_json(['ok'=>false,'error'=>'not_found'],404);public_json(['ok'=>true,'installation'=>$row]);
