<?php
declare(strict_types=1);
require dirname(__DIR__,3).'/bootstrap.php';
$token=public_bearer_token();
if($token!=='')public_db()->prepare('DELETE FROM public_sessions WHERE token_hash=?')->execute([hash('sha256',$token)]);
public_json(['ok'=>true]);
