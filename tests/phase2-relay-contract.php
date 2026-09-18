<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/relay_protocol.php';
require dirname(__DIR__) . '/includes/relay_dispatch.php';
function check(bool $ok,string $message):void{if(!$ok){fwrite(STDERR,"FAIL: $message\n");exit(1);}echo "PASS: $message\n";}
$secret='test-secret-0123456789';$body='{"a":1}';$ts=(string)time();$nonce='nonce-1';$sig=sokna_relay_sign($secret,'POST','/api/v1/local/claim.php',$ts,$nonce,$body);
check(sokna_relay_verify_signature($secret,'POST','/api/v1/local/claim.php',$ts,$nonce,$body,$sig),'HMAC vector verifies');
check(!sokna_relay_verify_signature($secret,'POST','/api/v1/local/claim.php',$ts,$nonce,$body.'x',$sig),'tampered body is rejected');
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) { fwrite(STDERR,"pdo_sqlite is required for the Phase 2 synthetic idempotency contract.\n"); exit(2); }
$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE relay_processed_requests(request_id TEXT PRIMARY KEY,request_hash TEXT NOT NULL,kind TEXT NOT NULL,status TEXT NOT NULL,result_json TEXT NULL,error_code TEXT NULL,created_at TEXT,updated_at TEXT)');
$pdo->exec('CREATE TABLE synthetic_commits(id INTEGER PRIMARY KEY AUTOINCREMENT,request_id TEXT UNIQUE NOT NULL)');
$registry=['system.synthetic_commit'=>static function(PDO $pdo,array $env):array{$s=$pdo->prepare('INSERT INTO synthetic_commits(request_id) VALUES(?)');$s->execute([(string)$env['request_id']]);return ['commit_id'=>(int)$pdo->lastInsertId()];}];
$now=time();$env=['request_id'=>'req-001','kind'=>'system.synthetic_commit','created_at'=>gmdate('c',$now-1),'expires_at'=>gmdate('c',$now+60),'actor_projection_id'=>'actor-1','payload'=>['x'=>1]];
$r1=sokna_relay_process_claim($pdo,['envelope'=>$env],$registry,$now);$r2=sokna_relay_process_claim($pdo,['envelope'=>$env],$registry,$now);
check($r1['state']==='committed' && empty($r1['deduplicated']),'first request commits');
check($r2['state']==='committed' && !empty($r2['deduplicated']),'duplicate request reuses canonical result');
check((int)$pdo->query('SELECT COUNT(*) FROM synthetic_commits')->fetchColumn()===1,'duplicate does not create second business commit');
$rAfterExpiry=sokna_relay_process_claim($pdo,['envelope'=>$env],$registry,$now+120);
check($rAfterExpiry['state']==='committed' && !empty($rAfterExpiry['deduplicated']),'committed result survives retry after request expiry');
check((int)$pdo->query('SELECT COUNT(*) FROM synthetic_commits')->fetchColumn()===1,'post-expiry reconciliation does not create second business commit');
$expired=$env;$expired['request_id']='req-expired';$expired['created_at']=gmdate('c',$now-120);$expired['expires_at']=gmdate('c',$now-1);
$re=sokna_relay_process_claim($pdo,['envelope'=>$expired],$registry,$now);
check($re['state']==='expired','expired request is rejected before dispatch');
check((int)$pdo->query('SELECT COUNT(*) FROM synthetic_commits')->fetchColumn()===1,'expired request creates no delayed business commit');
$canonicalA=sokna_relay_canonical_json(['b'=>2,'a'=>['z'=>3,'y'=>4]]);$canonicalB=sokna_relay_canonical_json(['a'=>['y'=>4,'z'=>3],'b'=>2]);
check($canonicalA===$canonicalB,'canonical JSON is stable across associative key order');
echo "Phase 2 relay contract PASS\n";
