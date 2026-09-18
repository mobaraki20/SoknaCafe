<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/maintenance.php';
maintenance_guard_json();
require_capability('shift_supervision');

function controls_payload(): array
{
    return [
        'success'=>true,
        'order_acceptance'=>order_acceptance_states(),
        'order_acceptance_revision'=>order_acceptance_revision(),
        'station_states'=>station_busy_states(),
        'waiter_enabled'=>setting_bool('waiter_call_enabled', true),
        'server_time'=>date(DATE_ATOM),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') json_response(controls_payload());
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success'=>false,'message'=>'روش درخواست معتبر نیست.'],405);
$data=request_json();
if(!csrf_valid($data['csrf_token']??null)) json_response(['success'=>false,'message'=>'نشست صفحه منقضی شده است؛ صفحه را تازه کنید.'],419);
$action=(string)($data['action']??'');
$userId=(int)current_user()['id'];
$requestId=substr(preg_replace('/[^A-Za-z0-9-]+/','',(string)($data['request_id']??''))?:bin2hex(random_bytes(8)),0,48);
$pdo=db();
try{
    if($action==='set_order_acceptance'){
        $scope=(string)($data['scope']??'');
        if(!in_array($scope,['cafe','kitchen','bar'],true)) json_response(['success'=>false,'message'=>'دامنه پذیرش آنلاین معتبر نیست.','request_id'=>$requestId],422);
        $enabled=bool_from_mixed($data['enabled']??false);
        $pdo->beginTransaction();
        $lock=$pdo->prepare("SELECT setting_key,setting_value FROM settings WHERE setting_key IN('orders_accepting.cafe','orders_accepting.kitchen','orders_accepting.bar','orders_accepting.revision') FOR UPDATE");
        $lock->execute();
        clear_setting_cache();
        $before=order_acceptance_states();
        $revision=order_acceptance_revision()+1;
        $upsert=$pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
        $upsert->execute(['orders_accepting.'.$scope,$enabled?'1':'0']);
        $upsert->execute(['orders_accepting.revision',(string)$revision]);
        $verify=$pdo->prepare("SELECT setting_key,setting_value FROM settings WHERE setting_key IN(?,?) FOR UPDATE");
        $verify->execute(['orders_accepting.'.$scope,'orders_accepting.revision']);
        $stored=[];
        foreach($verify->fetchAll() as $row)$stored[(string)$row['setting_key']]=(string)$row['setting_value'];
        if(($stored['orders_accepting.'.$scope]??'')!==($enabled?'1':'0')||(int)($stored['orders_accepting.revision']??0)!==$revision)throw new RuntimeException('مقدار ذخیره‌شده با درخواست یکسان نیست.');
        audit_log_write('guest_order_acceptance.changed','settings',$scope,[
            'scope'=>$scope,'enabled_before'=>(bool)($before[$scope]??true),'enabled_after'=>$enabled,
            'revision'=>$revision,'request_id'=>$requestId,
        ],$userId);
        $pdo->commit();
        clear_setting_cache();
        $labels=['cafe'=>'کل کافه','kitchen'=>'آشپزخانه','bar'=>'بار'];
        json_response(controls_payload()+[
            'persisted'=>true,'request_id'=>$requestId,
            'message'=>'پذیرش آنلاین '.$labels[$scope].($enabled?' فعال شد.':' متوقف شد؛ منو همچنان قابل مشاهده است.'),
        ]);
    }
    if($action==='set_station'){
        $area=(string)($data['station']??'');
        if(!array_key_exists($area,preparation_operational_areas())) json_response(['success'=>false,'message'=>'بخش آماده‌سازی معتبر نیست.','request_id'=>$requestId],422);
        $busy=!empty($data['busy']);
        $stmt=$pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
        $stmt->execute(['station_busy.'.$area,$busy?'1':'0']);
        clear_setting_cache();
        if(station_busy_states()[$area]!==$busy) throw new RuntimeException('وضعیت بخش ذخیره نشد.');
        audit_log_write('preparation_pressure.changed','settings',$area,['busy'=>$busy,'request_id'=>$requestId],$userId);
        json_response(controls_payload()+['station'=>$area,'busy'=>$busy,'request_id'=>$requestId,'message'=>preparation_operational_areas()[$area].($busy?' در حالت شلوغ قرار گرفت.':' به حالت عادی برگشت.')]);
    }
    json_response(['success'=>false,'message'=>'عملیات معتبر نیست.','request_id'=>$requestId],422);
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    error_log('operator controls ['.$requestId.']: '.$e->getMessage());
    json_response(['success'=>false,'message'=>'تغییر وضعیت ذخیره نشد. کد پیگیری: '.$requestId,'request_id'=>$requestId],500);
}
