<?php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/sokna-phase8a-id-'.bin2hex(random_bytes(5));
putenv('SOKNA_DATA_DIR='.$tmp);
require dirname(__DIR__).'/includes/installation_identity.php';
function t8a(bool $ok,string $m):void{if(!$ok)throw new RuntimeException($m);}
try{
  $a=sokna_installation_identity_ensure();
  $b=sokna_installation_identity_ensure();
  t8a($a===$b,'identity must be stable across reload');
  t8a((bool)preg_match('/^inst_[a-f0-9]{32}$/',(string)$a['installation_id']),'installation id invalid');
  t8a(is_file(sokna_installation_identity_private_path()),'private key file missing');
  t8a(is_file(sokna_installation_identity_metadata_path()),'metadata file missing');
  $secret=(string)file_get_contents(sokna_installation_identity_private_path());
  $json=(string)file_get_contents(sokna_installation_identity_metadata_path());
  t8a(!str_contains($json,$secret),'private key leaked into metadata');
  t8a(!array_key_exists('private_key',$a)&&!array_key_exists('secret_key',$a),'public metadata exposes private key');
  $raw=base64_decode($secret,true);
  t8a(is_string($raw)&&strlen($raw)===SODIUM_CRYPTO_SIGN_SECRETKEYBYTES,'stored private key invalid');
  $pub=sodium_crypto_sign_publickey_from_secretkey($raw);
  t8a(hash_equals((string)$a['public_key_b64'],base64_encode($pub)),'public key does not match private key');
  sodium_memzero($raw);
  echo "Phase 8A installation identity runtime PASS.\n";
}finally{
  if(is_dir($tmp)){
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($it as $f){$f->isDir()?@rmdir($f->getPathname()):@unlink($f->getPathname());}
    @rmdir($tmp);
  }
}
