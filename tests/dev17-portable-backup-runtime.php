<?php
declare(strict_types=1);
$sourceRoot=dirname(__DIR__);
$tmp=sys_get_temp_dir().'/sokna-portable-backup-'.bin2hex(random_bytes(5));
mkdir($tmp,0750,true);mkdir($tmp.'/storage/backups',0750,true);mkdir($tmp.'/storage/tmp',0750,true);mkdir($tmp.'/uploads',0755,true);
define('SOKNA_MAINTENANCE_ROOT',$tmp);
require $sourceRoot.'/includes/maintenance.php';

function t(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
try{
    $originalKey=str_repeat('a',64);$newKey=str_repeat('b',64);
    $cfg=['db'=>['host'=>'db.internal','port'=>'3307','name'=>'sokna','user'=>'u','pass'=>'p','charset'=>'utf8mb4'],'app'=>['url'=>'https://new.example.test','key'=>$originalKey,'timezone'=>'Asia/Tehran','debug'=>false,'trust_proxy_headers'=>false]];
    file_put_contents($tmp.'/config.php',"<?php\nreturn ".var_export($cfg,true).";\n");
    $GLOBALS['config']=$cfg;
    maintenance_config_set_app_key($newKey);
    $loaded=require $tmp.'/config.php';
    t($loaded['app']['key']===$newKey,'app identity did not change');
    t($loaded['app']['url']==='https://new.example.test','target app URL was overwritten');
    t($loaded['db']['host']==='db.internal'&&$loaded['db']['port']==='3307','target DB settings were overwritten');
    t(($GLOBALS['config']['app']['key']??'')===$newKey,'in-memory config was not updated');

    $tar=$tmp.'/storage/tmp/test.tar';$db="-- CAFE-SQL-FRAMED-V2 --\nCAFE-LEN 25\nSET FOREIGN_KEY_CHECKS=0;\n";$appKey=str_repeat('c',64);
    $archive=new PharData($tar);$entries=[];
    maintenance_add_string($archive,$db,'database/database.sql',$entries);
    maintenance_add_string($archive,$appKey,'system/app.key',$entries);
    $index=json_encode($entries,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$archive->addFromString('files.json',$index);
    $manifest=['format'=>'sokna-backup-v3','type'=>'backup','version'=>maintenance_version(),'created_at'=>date(DATE_ATOM),'timezone'=>'Asia/Tehran','actor_user_id'=>null,'database_sha256'=>hash('sha256',$db),'files_index_sha256'=>hash('sha256',$index),'entry_count'=>count($entries),'uploads_present'=>false,'schema_fingerprint'=>'','portable_app_identity'=>true,'app_identity_fingerprint'=>substr(hash('sha256',$appKey),0,16)];
    $archive->addFromString('manifest.json',json_encode($manifest,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));$gz=$archive->compress(Phar::GZ);unset($gz,$archive);$path=$tar.'.gz';
    $validated=maintenance_validate_archive($path);
    t(($validated['app_key']??'')===$appKey,'portable app identity was not validated');
    t(($validated['manifest']['portable_app_identity']??false)===true,'portable identity flag missing');
    $uploadedTmp=$tmp.'/storage/tmp/php-upload-body';copy($path,$uploadedTmp);
    $staged=maintenance_stage_backup_file($uploadedTmp);
    t(str_ends_with($staged,'.tar.gz'),'uploaded backup was not staged with a PharData-compatible extension');
    $uploadedValidated=maintenance_validate_archive($staged);
    t(($uploadedValidated['app_key']??'')===$appKey,'staged upload did not preserve portable app identity');
    @unlink($staged);

    $secure=$tmp.'/storage/tmp/portable.skb';$secureRestored=$tmp.'/storage/tmp/portable-restored.tar.gz';$pass='Portable backup test phrase 1405!';
    maintenance_secure_backup_encrypt_file($path,$secure,$pass);
    t(maintenance_secure_backup_is_file($secure),'secure portable envelope was not detected');
    t(!str_contains((string)file_get_contents($secure),$appKey),'portable app identity leaked in encrypted envelope');
    maintenance_secure_backup_decrypt_file($secure,$secureRestored,$pass);
    $secureValidated=maintenance_validate_archive($secureRestored);
    t(($secureValidated['app_key']??'')===$appKey,'secure portable envelope did not restore the validated app identity');
    $secureUpload=$tmp.'/storage/tmp/php-upload-secure';copy($secure,$secureUpload);
    $imported=maintenance_import_backup_file($secureUpload,null,$pass);
    t(($imported['import_transport']??'')===MAINTENANCE_SECURE_BACKUP_FORMAT,'secure upload did not use the encrypted import transport');
    t(($imported['valid']??false)===true,'secure upload was not validated after import');
    $importedPath=maintenance_backup_path((string)$imported['name']);
    $importedValidated=maintenance_validate_archive($importedPath);
    t(($importedValidated['app_key']??'')===$appKey,'secure import did not preserve portable app identity');
    echo "Portable backup runtime PASS\n";
} finally {
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($it as $f){$f->isDir()?@rmdir($f->getPathname()):@unlink($f->getPathname());}@rmdir($tmp);
}
