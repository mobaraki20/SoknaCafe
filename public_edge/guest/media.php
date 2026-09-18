<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
$installationId=trim((string)($_GET['installation_id']??''));
$sha=strtolower(trim((string)($_GET['sha']??'')));
$ext=strtolower(trim((string)($_GET['ext']??'')));
if($installationId===''||!preg_match('/^[a-f0-9]{64}$/',$sha)||!preg_match('/^(jpg|png|webp|gif|svg)$/',$ext)){http_response_code(404);exit;}
$bundle=public_guest_bundle($installationId);if(!$bundle){http_response_code(404);exit;}
$allowed=false;$mime='';
foreach(($bundle['media_manifest']??[]) as $meta){
    if(is_array($meta)&&hash_equals((string)($meta['sha256']??''),$sha)&&(string)($meta['extension']??'')===$ext){$allowed=true;$mime=(string)($meta['mime']??'application/octet-stream');break;}
}
if(!$allowed){http_response_code(404);exit;}
$storage=(string)($publicConfig['app']['storage_dir']??(dirname(__DIR__).'/storage'));
$path=rtrim($storage,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'guest-media'.DIRECTORY_SEPARATOR.preg_replace('/[^A-Za-z0-9._-]/','_',$installationId).DIRECTORY_SEPARATOR.$sha.'.'.$ext;
if(!is_file($path)||!hash_equals($sha,(string)hash_file('sha256',$path))){http_response_code(404);exit;}
header('Content-Type: '.$mime);
header('Content-Length: '.(string)filesize($path));
header('Cache-Control: public, max-age=31536000, immutable');
header('ETag: "'.$sha.'"');
readfile($path);
