<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/maintenance.php';
maintenance_guard_json();
require_any_capability(['orders_floor','preparation']);
require_once dirname(__DIR__) . '/includes/push.php';

$user = current_user();
$userId = (int)$user['id'];
$credentials = push_vapid_credentials(true);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = db()->prepare('SELECT id,device_label,active,last_success_at,last_error_at,last_error_message,created_at,updated_at FROM push_subscriptions WHERE user_id=? ORDER BY updated_at DESC');
    $stmt->execute([$userId]);
    $workerLastSeen = setting('push.worker_last_seen_at', '');
    $workerFresh = false;
    if ($workerLastSeen !== '') {
        try {
            $workerFresh = (time() - (new DateTimeImmutable($workerLastSeen, new DateTimeZone(app_timezone())))->getTimestamp()) <= 180;
        } catch (Throwable) { $workerFresh = false; }
    }
    $caps = user_capabilities($userId);
    $adminLive = (string)($user['role'] ?? '') !== 'admin' || setting_bool('push.admin_live_operations', false);
    json_response([
        'success'=>true,
        'public_key'=>$credentials['public_key'],
        'subscriptions'=>$stmt->fetchAll(),
        'secure_context'=>(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443),
        'worker_last_seen_at'=>$workerLastSeen,
        'worker_fresh'=>$workerFresh,
        'live_floor_enabled'=>$adminLive && in_array('orders_floor', $caps, true),
        'live_preparation_enabled'=>$adminLive && in_array('preparation', $caps, true),
        'admin_live_operations'=>(string)($user['role'] ?? '') === 'admin' ? setting_bool('push.admin_live_operations', false) : null,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success'=>false],405);
$data=request_json();
if(!csrf_valid($data['csrf_token']??null))json_response(['success'=>false,'message'=>'صفحه رو تازه کن.'],419);
$action=(string)($data['action']??'subscribe');
if($action==='subscribe'){
    $subscription=$data['subscription']??null;
    if(!is_array($subscription))json_response(['success'=>false,'message'=>'اطلاعات اعلان کامل نیست.'],422);
    $endpoint=text_substr(trim((string)($subscription['endpoint']??'')),0,2000);
    $p256dh=text_substr(trim((string)($subscription['keys']['p256dh']??'')),0,255);
    $auth=text_substr(trim((string)($subscription['keys']['auth']??'')),0,255);
    $label=text_substr(trim((string)($data['device_label']??'')),0,120)?:'دستگاه بدون نام';
    if(!filter_var($endpoint,FILTER_VALIDATE_URL)||!str_starts_with($endpoint,'https://'))json_response(['success'=>false,'message'=>'نشانی اعلان معتبر نیست.'],422);
    try{if(strlen(push_b64url_decode($p256dh))!==65||strlen(push_b64url_decode($auth))<16)throw new RuntimeException();}catch(Throwable){json_response(['success'=>false,'message'=>'کلید اعلان معتبر نیست.'],422);}
    $hash=hash('sha256',$endpoint);
    db()->prepare('INSERT INTO push_subscriptions(user_id,endpoint_hash,endpoint,p256dh,auth_key,device_label,user_agent,active,last_error_at,last_error_message) VALUES(?,?,?,?,?,?,?,1,NULL,NULL) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),p256dh=VALUES(p256dh),auth_key=VALUES(auth_key),device_label=VALUES(device_label),user_agent=VALUES(user_agent),active=1,last_error_at=NULL,last_error_message=NULL')
        ->execute([$userId,$hash,$endpoint,$p256dh,$auth,$label,text_substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500)]);
    json_response(['success'=>true,'message'=>'اعلان این دستگاه فعال شد.']);
}
if($action==='unsubscribe'){
    $endpoint=trim((string)($data['endpoint']??''));
    if($endpoint!=='')db()->prepare('UPDATE push_subscriptions SET active=0 WHERE user_id=? AND endpoint_hash=?')->execute([$userId,hash('sha256',$endpoint)]);
    json_response(['success'=>true,'message'=>'اعلان این دستگاه خاموش شد.']);
}
if($action==='test'){
    $stmt=db()->prepare('SELECT COUNT(*) FROM push_subscriptions WHERE user_id=? AND active=1');
    $stmt->execute([$userId]);
    if((int)$stmt->fetchColumn()<1)json_response(['success'=>false,'message'=>'ابتدا اعلان دستگاه را فعال کن.'],422);
    if(!push_queue_available())json_response(['success'=>false,'message'=>'صف اعلان عملیاتی روی این نصب آماده نیست. مدیر سامانه باید نصب/ارتقا را بررسی کند.'],503);
    $diagnosticKey=bin2hex(random_bytes(12));
    $queued=push_enqueue_event('diagnostic',[
        'title'=>'آزمایش کامل اعلان سکنا',
        'body'=>'صف، ارسال خودکار و Push این دستگاه درست کار می‌کنند.',
        'url'=>asset(user_home_path($user)),
        'tag'=>'push-pipeline-test-'.$userId,
        'target_user_id'=>$userId,
        'diagnostic_key'=>$diagnosticKey,
        'event_key'=>'pipeline-test-'.$userId.'-'.$diagnosticKey,
    ]);
    if((int)($queued['queued']??0)!==1||empty($queued['queue_id']))json_response(['success'=>false,'message'=>'ثبت تست در صف اعلان انجام نشد.'],503);
    json_response([
        'success'=>true,
        'message'=>'تست کامل در صف اعلان ثبت شد.',
        'queue_id'=>(int)$queued['queue_id'],
        'diagnostic_key'=>$diagnosticKey,
    ]);
}
if($action==='test_status'){
    $queueId=(int)($data['queue_id']??0);$diagnosticKey=trim((string)($data['diagnostic_key']??''));
    if($queueId<1||strlen($diagnosticKey)!==24)json_response(['success'=>false,'message'=>'شناسه تست اعلان معتبر نیست.'],422);
    $stmt=db()->prepare("SELECT id,status,attempt_count,payload_json,created_at,updated_at FROM push_event_queue WHERE id=? AND event_type='diagnostic' LIMIT 1");
    $stmt->execute([$queueId]);$row=$stmt->fetch();
    if(!$row)json_response(['success'=>false,'message'=>'تست اعلان پیدا نشد.'],404);
    try{$payload=json_decode((string)$row['payload_json'],true,16,JSON_THROW_ON_ERROR);}catch(Throwable){$payload=[];}
    if((int)($payload['target_user_id']??0)!==$userId||!hash_equals((string)($payload['diagnostic_key']??''),$diagnosticKey))json_response(['success'=>false,'message'=>'دسترسی به این تست اعلان مجاز نیست.'],403);
    $counts=['total'=>0,'sent'=>0,'failed'=>0,'pending'=>0,'expired'=>0];
    $d=db()->prepare("SELECT COUNT(*) total,SUM(status='sent') sent,SUM(status='failed') failed,SUM(status='pending') pending,SUM(status='expired') expired FROM push_event_deliveries WHERE queue_id=?");
    $d->execute([$queueId]);$counts=array_merge($counts,$d->fetch()?:[]);
    $workerLastSeen=setting('push.worker_last_seen_at','');$workerFresh=false;
    if($workerLastSeen!==''){try{$workerFresh=(time()-(new DateTimeImmutable($workerLastSeen,new DateTimeZone(app_timezone())))->getTimestamp())<=180;}catch(Throwable){$workerFresh=false;}}
    json_response([
        'success'=>true,
        'status'=>(string)$row['status'],
        'attempt_count'=>(int)$row['attempt_count'],
        'deliveries'=>array_map('intval',$counts),
        'worker_last_seen_at'=>$workerLastSeen,
        'worker_fresh'=>$workerFresh,
    ]);
}
json_response(['success'=>false,'message'=>'عملیات اعلان معتبر نیست.'],422);
