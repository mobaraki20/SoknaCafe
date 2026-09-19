<?php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/sokna-phase8a-meta-'.bin2hex(random_bytes(5));
putenv('SOKNA_DATA_DIR='.$tmp);
$GLOBALS['fake_settings']=[
 'menu_theme'=>'courtyard','primary_color'=>'#111111','accent_color'=>'#222222','background_color'=>'#ffffff','logo_path'=>'uploads/logo.png','favicon_updated_at'=>'2026-09-19T00:00:00Z',
 'sokna_center_connection_enabled'=>'1','sokna_center_base_url'=>'https://center.example.test','sokna_center_handoff_secret_fingerprint'=>'CENTERFP',
 'accommodation_connection_enabled'=>'1','accommodation_api_base_url'=>'https://stay.example.test/api.php','accommodation_api_key_fingerprint'=>'STAYFP',
];
function setting(string $key,string $default=''):string{return (string)($GLOBALS['fake_settings'][$key]??$default);}
$GLOBALS['config']=[
 'app'=>['key'=>str_repeat('a',64)],
 'relay'=>['enabled'=>true,'public_base_url'=>'https://public.example.test','installation_id'=>'legacy-relay-id','shared_secret'=>'DO-NOT-EXPORT-THIS'],
];
require dirname(__DIR__).'/includes/maintenance.php';
function tm(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
try{
 $m=maintenance_recovery_metadata();$j=json_encode($m,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
 tm(($m['format']??'')==='sokna-recovery-metadata-v1','format missing');
 tm(($m['private_identity_cloned']??true)===false,'private identity clone flag wrong');
 tm(($m['public_binding']['installation_id']??'')==='legacy-relay-id','public binding missing');
 tm(($m['public_binding']['public_host']??'')==='public.example.test','public host missing');
 tm(!str_contains($j,'DO-NOT-EXPORT-THIS'),'relay secret leaked');
 tm(($m['integrations']['center']['secret_fingerprint']??'')==='CENTERFP','center secret reference missing');
 tm(($m['integrations']['accommodation']['secret_fingerprint']??'')==='STAYFP','accommodation secret reference missing');
 tm(!str_contains(strtolower($j),'private_key'),'private key field leaked');
 echo "Phase 8A recovery metadata runtime PASS.\n";
}finally{
 if(is_dir($tmp)){
  $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
  foreach($it as $f){$f->isDir()?@rmdir($f->getPathname()):@unlink($f->getPathname());}@rmdir($tmp);
 }
}
