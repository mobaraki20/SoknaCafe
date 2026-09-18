<?php
declare(strict_types=1);

$path=ltrim(str_replace('\\','/',(string)($_GET['path']??'')),'/');
if(!preg_match('#^assets/(?:css|js|icons|fonts)/[A-Za-z0-9._/-]+$#',$path)||str_contains($path,'..')){
    http_response_code(404);exit;
}
$root=realpath(dirname(__DIR__));
$file=$root!==false?realpath($root.DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$path)):false;
$prefix=$root!==false?rtrim($root,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR:'';
if($file===false||!is_file($file)||$prefix===''||!str_starts_with($file,$prefix)){
    http_response_code(404);exit;
}
$ext=strtolower(pathinfo($file,PATHINFO_EXTENSION));
$types=[
    'css'=>'text/css; charset=utf-8','js'=>'application/javascript; charset=utf-8',
    'svg'=>'image/svg+xml','woff2'=>'font/woff2','png'=>'image/png','jpg'=>'image/jpeg',
    'jpeg'=>'image/jpeg','webp'=>'image/webp','gif'=>'image/gif',
];
if(!isset($types[$ext])){http_response_code(404);exit;}
$hash=hash_file('sha256',$file);
if(!is_string($hash)){http_response_code(500);exit;}
$etag='"'.$hash.'"';
if(trim((string)($_SERVER['HTTP_IF_NONE_MATCH']??''))===$etag){
    http_response_code(304);header('ETag: '.$etag);exit;
}
header('Content-Type: '.$types[$ext]);
header('Content-Length: '.(string)filesize($file));
header('Cache-Control: public, max-age=31536000, immutable');
header('ETag: '.$etag);
readfile($file);
