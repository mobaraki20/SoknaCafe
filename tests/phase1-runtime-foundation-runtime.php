<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$tmp=sys_get_temp_dir().DIRECTORY_SEPARATOR.'sokna-phase1-'.bin2hex(random_bytes(4));
putenv('SOKNA_DATA_DIR='.$tmp);
require $root.'/includes/observability.php';
require $root.'/includes/runtime.php';
function phase1_need(bool $ok,string $m): void { if(!$ok){fwrite(STDERR,"FAIL: $m\n");exit(1);} }
$id=sokna_correlation_id('phase1-test-123456');
phase1_need($id==='phase1-test-123456','correlation id propagation');
$red=sokna_redact_context(['password'=>'x','nested'=>['access_token'=>'y','event_key'=>'safe']]);
phase1_need($red['password']==='[REDACTED]' && $red['nested']['access_token']==='[REDACTED]' && $red['nested']['event_key']==='safe','redaction');
$check=sokna_runtime_self_check();
phase1_need(($check['ok']??false)===true,'self check');
$proc=sokna_runtime_run_process([PHP_BINARY,'-r','fwrite(STDOUT,"runtime-ok");'],5);
phase1_need(($proc['exit_code']??-1)===0 && ($proc['stdout']??'')==='runtime-ok','process supervisor');
$state=sokna_runtime_base_state();
$state['status']='test';sokna_runtime_write_state($state);$back=sokna_runtime_read_state();
phase1_need(($back['format']??'')===SOKNA_RUNTIME_STATE_FORMAT && ($back['status']??'')==='test','atomic runtime state');
sokna_log_event('info','phase1.runtime_test',['password'=>'must-redact','event_key'=>'visible']);
$logs=glob($tmp.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'*.jsonl')?:[];
phase1_need(count($logs)===1,'jsonl log created');
$log=(string)file_get_contents($logs[0]);
phase1_need(!str_contains($log,'must-redact') && str_contains($log,'[REDACTED]') && str_contains($log,'visible'),'log redaction persisted');
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
foreach($it as $p){$p->isDir()?@rmdir($p->getPathname()):@unlink($p->getPathname());}@rmdir($tmp);
echo "Phase 1 runtime foundation runtime PASS.\n";
