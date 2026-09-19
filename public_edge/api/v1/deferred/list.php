<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
$session=public_session();$pdo=public_db();
if(in_array('*',$session['capabilities']??[],true)){
    $stmt=$pdo->prepare('SELECT request_id,kind,actor_projection_id,state,occurred_at,error_code,created_at,updated_at FROM deferred_work WHERE installation_id=? ORDER BY created_at DESC LIMIT 100');
    $stmt->execute([(string)$session['installation_id']]);
}else{
    $stmt=$pdo->prepare('SELECT request_id,kind,actor_projection_id,state,occurred_at,error_code,created_at,updated_at FROM deferred_work WHERE installation_id=? AND actor_projection_id=? ORDER BY created_at DESC LIMIT 100');
    $stmt->execute([(string)$session['installation_id'],(string)$session['projection_id']]);
}
public_json(['ok'=>true,'items'=>$stmt->fetchAll()]);
