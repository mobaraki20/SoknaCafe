<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$args=$argv; array_shift($args);
$opts=[];
for($i=0;$i<count($args);$i++){
    $a=$args[$i];
    if(str_starts_with($a,'--') && str_contains($a,'=')){[$k,$v]=explode('=',substr($a,2),2);$opts[$k]=$v;continue;}
    if(str_starts_with($a,'--')){$k=substr($a,2);$v=true;if(isset($args[$i+1])&&!str_starts_with($args[$i+1],'--')){$v=$args[++$i];}$opts[$k]=$v;}
}
if(!isset($opts['results']))$opts['results']=$root.'/artifacts/web-print-dev20/acceptance-runner';
$out=(string)$opts['results']; if(!is_dir($out)&&!mkdir($out,0775,true)&&!is_dir($out)){fwrite(STDERR,"cannot create results dir\n");exit(70);}
$all=array_map(fn($n)=>'B'.str_pad((string)$n,2,'0',STR_PAD_LEFT),range(1,50));
$browser=['B33','B34','B37','B39','B46'];
$dbRequired=array_values(array_diff($all,array_merge(['B01','B50','B49'],$browser)));
$selected=[];
if(isset($opts['case'])){$id=strtoupper((string)$opts['case']);if(!in_array($id,$all,true)){fwrite(STDERR,"Unknown case: $id\n");exit(64);} $selected=[$id];}
elseif(isset($opts['suite'])){
  $suite=strtolower((string)$opts['suite']);
  if($suite==='web')$selected=array_values(array_diff($all,['B49','B50']));
  elseif($suite==='integration')$selected=['B49'];
  else {fwrite(STDERR,"Unknown suite: $suite\n");exit(64);}
}else{fwrite(STDERR,"Usage: php tests/print_acceptance.php --case B01|--suite web|--suite integration --results <dir>\n");exit(64);}
if(!$selected){fwrite(STDERR,"Zero tests selected\n");exit(64);}
function runCmd(string $cmd,string $cwd):array{
  $des=[1=>['pipe','w'],2=>['pipe','w']];$p=proc_open($cmd,$des,$pipes,$cwd);
  if(!is_resource($p))return [70,'','proc_open failed'];
  $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$rc=proc_close($p);return [$rc,$stdout,$stderr];
}
function result(string $id,string $status,array $commands,array $evidence,?string $blocker,string $root,string $out):array{
  $r=['id'=>$id,'status'=>$status,'commands'=>$commands,'web_source_sha'=>trim((string)shell_exec('cd '.escapeshellarg($root).' && git rev-parse HEAD 2>/dev/null')),'agent_source_sha'=>null,'runtime_versions'=>['php'=>PHP_VERSION,'os'=>PHP_OS_FAMILY],'run_at'=>gmdate('c'),'evidence'=>$evidence,'blocker_fa'=>$blocker];
  file_put_contents($out.'/'.$id.'.json',json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));return $r;
}
$results=[];$worst=0;
foreach($selected as $id){
  if($id==='B01'){
    $cmds=['python3 tests/print-dev20-contract.py','python3 tests/printing-settings-browser.py','python3 tests/print-template-v2-browser.py'];$ev=[];$ok=true;
    foreach($cmds as $cmd){[$rc,$so,$se]=runCmd($cmd,$root);$log=$out.'/'.$id.'-'.substr(hash('sha256',$cmd),0,8).'.log';file_put_contents($log,$so.$se);$ev[]=$log;$ok=$ok&&$rc===0;}
    $results[]=result($id,$ok?'PASS':'FAIL',$cmds,$ev,$ok?null:'یک یا چند regression مبنا شکست خورد.',$root,$out); if(!$ok)$worst=max($worst,1); continue;
  }
  if(in_array($id,$browser,true)){
    $cmd='python3 tests/print-dev20-browser.py --case '.$id.' --results '.escapeshellarg($out.'/browser');[$rc,$so,$se]=runCmd($cmd,$root);$log=$out.'/'.$id.'.log';file_put_contents($log,$so.$se);$status=$rc===0?'PASS':'FAIL';$results[]=result($id,$status,[$cmd],[$log,$out.'/browser/'.$id.'.json'],null,$root,$out);if($rc!==0)$worst=max($worst,1);continue;
  }
  if($id==='B49'){
    $need=['SOKNA_ACCEPTANCE_SERVER_URL','SOKNA_ACCEPTANCE_TOKEN_FILE','SOKNA_ACCEPTANCE_DESTINATION_KEY','SOKNA_ACCEPTANCE_FAULT_PROXY_URL','SOKNA_ACCEPTANCE_ALLOW_MUTATION'];$missing=[];foreach($need as $k)if(getenv($k)===false||getenv($k)==='')$missing[]=$k;
    $block=$missing?'محیط integration واقعی Agent موجود نیست: '.implode(', ',$missing):'Harness .NET Agent باید روی Windows و همان acceptance server اجرا شود؛ این runner وب جای A49 را PASS نمی‌کند.';
    $results[]=result($id,'NOT_RUN',[],[],$block,$root,$out);$worst=max($worst,3);continue;
  }
  if($id==='B50'){$results[]=result($id,'UAT_REQUIRED',[],[],'نیازمند Windows، صندوق، پرینتر واقعی، 50 چاپ و soak حداقل 24 ساعت.',$root,$out);$worst=max($worst,3);continue;}
  $pdoMysql=extension_loaded('pdo_mysql');
  $block=$pdoMysql?'Harness DB/API واقعی این case در این workspace اجرا نشده است؛ PASS ممنوع است.':'pdo_mysql/MariaDB acceptance environment در این runtime موجود نیست؛ SQLite/fake جایگزین معتبر نیست.';
  $results[]=result($id,'NOT_RUN',[],[],$block,$root,$out);$worst=max($worst,3);
}
file_put_contents($out.'/summary.json',json_encode(['selected'=>$selected,'results'=>$results,'exit_code'=>$worst],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
foreach($results as $r)echo $r['id']."\t".$r['status'].($r['blocker_fa']?"\t".$r['blocker_fa']:'')."\n";
exit($worst);
