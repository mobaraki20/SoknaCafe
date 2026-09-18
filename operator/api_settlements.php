<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
maintenance_guard_json();
require_capability('cashier_accounts');

$pdo = db();
$userId = (int)current_user()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $lookupRequestId=trim((string)($_GET['request_id']??''));
    if($lookupRequestId!==''){
        try{
            $record=settlement_find_request($pdo,$lookupRequestId,false);
            if(!$record)json_response(['success'=>true,'found'=>false]);
            json_response([
                'success'=>true,'found'=>true,'table_id'=>(int)$record['settlement_table_id'],
                'destination'=>(string)$record['destination'],'request_id'=>(string)$record['request_id'],
            ]+settlement_result_from_record($record));
        }catch(RuntimeException $e){
            json_response(['success'=>false,'message'=>$e->getMessage()],422);
        }
    }
    json_response([
        'success'=>true,
        'items'=>settlement_today($pdo),
        'can_void'=>is_admin(),
    ]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success'=>false],405);
$data = request_json();
if (!csrf_valid($data['csrf_token']??null)) json_response(['success'=>false,'message'=>'صفحه منقضی شده؛ دوباره بارگذاری کن.'],419);
$action=(string)($data['action']??'');
$settlementId=(int)($data['settlement_id']??0);
if($settlementId<1) json_response(['success'=>false,'message'=>'تسویه معتبر نیست.'],422);

try {
    if ($action === 'reprint') {
        $stmt=$pdo->prepare("SELECT sr.* FROM settlement_records sr WHERE sr.id=? AND sr.status='completed' AND NOT EXISTS(SELECT 1 FROM settlement_records rev WHERE rev.reverses_settlement_id=sr.id AND rev.status='reversal') LIMIT 1");
        $stmt->execute([$settlementId]);
        $record=$stmt->fetch();
        if(!$record) throw new RuntimeException('فقط فاکتور نهایی ثبت‌شده قابل چاپ مجدد است.');
        if(!print_destination_ready($pdo,'customer_receipt')) throw new RuntimeException('مقصد سند مشتری فعال نیست.');
        $requestId=trim((string)($data['request_id']??''));
        if(!preg_match('/^[A-Za-z0-9._:-]{8,96}$/',$requestId)) throw new RuntimeException('درخواست چاپ معتبر نیست؛ دوباره تلاش کن.');
        $reprintKey='final.reprint.settlement.'.$settlementId.'.'.$requestId;
        $job=print_enqueue_final_invoice($pdo,(int)$record['id'],(string)$record['destination'],$userId,true,$reprintKey);
        if(empty($job['duplicate'])) audit_log_write('settlement.invoice_reprinted','settlement_record',$settlementId,['print_job_id'=>(int)($job['id']??0)],$userId);
        json_response(['success'=>true,'message'=>!empty($job['duplicate'])?'این درخواست چاپ قبلاً وارد صف شده است.':'چاپ مجدد فاکتور وارد صف شد.','print_job'=>$job]);
    }

    if ($action !== 'void') throw new RuntimeException('عملیات تسویه شناخته نشد.');
    if (!is_admin()) json_response(['success'=>false,'message'=>'ابطال تسویه فقط برای مدیر سامانه مجاز است.'],403);
    $reason=text_substr(trim((string)($data['reason']??'')),0,300);
    if($reason==='') throw new RuntimeException('دلیل ابطال را وارد کن.');

    $read=$pdo->prepare("SELECT sr.*,ts.table_id current_table_id,rev.id reversal_settlement_id,rev.invoice_number reversal_invoice_number FROM settlement_records sr JOIN table_sessions ts ON ts.id=sr.session_id LEFT JOIN settlement_records rev ON rev.reverses_settlement_id=sr.id AND rev.status='reversal' WHERE sr.id=? LIMIT 1");
    $read->execute([$settlementId]);
    $record=$read->fetch();
    if(!$record) throw new RuntimeException('تسویه پیدا نشد.');
    if((string)$record['status']==='voided' || !empty($record['reversal_settlement_id'])) json_response(['success'=>true,'message'=>'این تسویه قبلاً با سند برگشتی ثبت شده است.','idempotent'=>true,'reversal_invoice_number'=>$record['reversal_invoice_number']??null]);

    $originalTableId=(int)$record['current_table_id'];
    $targetTableId=(int)($data['target_table_id']??0);
    $busy=$pdo->prepare("SELECT id FROM table_sessions WHERE table_id=? AND status IN('active','pending') AND id<>? LIMIT 1");
    $busy->execute([$originalTableId,(int)$record['session_id']]);
    $originalBusy=(bool)$busy->fetchColumn();
    if($targetTableId<1 && !$originalBusy) $targetTableId=$originalTableId;
    if($targetTableId<1){
        json_response([
            'success'=>false,
            'needs_target'=>true,
            'message'=>'میز قبلی اکنون به مهمان دیگری اختصاص دارد؛ یک میز آزاد برای بازگرداندن حساب انتخاب کن.',
            'free_tables'=>settlement_free_tables($pdo,$originalTableId),
        ],409);
    }

    if((string)$record['destination']==='accommodation'){
        $transferId=(int)($record['accommodation_transfer_id']??0);
        if($transferId<1) throw new RuntimeException('شناسه انتقال اقامتگاه برای این تسویه ثبت نشده است.');
        $remote=accommodation_attempt_void($transferId,$userId,$reason);
        if(!($remote['success']??false)){
            json_response(['success'=>false,'message'=>$remote['message']??'برگشت اقامتگاه انجام نشد.','ambiguous'=>$remote['ambiguous']??false],($remote['ambiguous']??false)?409:422);
        }
    }

    $pdo->beginTransaction();
    $lock=$pdo->prepare('SELECT * FROM settlement_records WHERE id=? FOR UPDATE');
    $lock->execute([$settlementId]);
    $locked=$lock->fetch();
    if(!$locked) throw new RuntimeException('تسویه پیدا نشد.');
    if((string)$locked['status']==='voided'){
        $pdo->commit();
        json_response(['success'=>true,'message'=>'این تسویه قبلاً با سند برگشتی ثبت شده است.','idempotent'=>true]);
    }
    if((string)$locked['destination']==='subscriber'){
        $entryId=(int)($locked['subscriber_ledger_entry_id']??0);
        if($entryId<1) throw new RuntimeException('سند مشترک این تسویه پیدا نشد.');
        subscriber_reverse_entry_locked($pdo,$entryId,$reason,$userId);
    }
    $result=settlement_reopen_locked($pdo,$locked,$targetTableId,$reason,$userId);
    $pdo->commit();
    $message=!empty($result['idempotent'])?'این تسویه قبلاً با سند برگشتی ثبت شده است.':'تسویه با سند برگشتی '.$result['reversal_invoice_number'].' ابطال شد و حساب روی '.$result['table_name'].' دوباره باز شد.';
    json_response(['success'=>true,'message'=>$message,'result'=>$result]);
} catch (RuntimeException $e) {
    if($pdo->inTransaction())$pdo->rollBack();
    json_response(['success'=>false,'message'=>$e->getMessage()],422);
} catch (PDOException $e) {
    if($pdo->inTransaction())$pdo->rollBack();
    if((string)$e->getCode()==='23000') json_response(['success'=>false,'message'=>'این عملیات قبلاً ثبت شده یا میز مقصد هم‌زمان اشغال شده است.'],409);
    error_log('settlement api: '.$e->getMessage());
    json_response(['success'=>false,'message'=>'عملیات تسویه کامل نشد.'],500);
} catch (Throwable $e) {
    if($pdo->inTransaction())$pdo->rollBack();
    error_log('settlement api: '.$e->getMessage());
    json_response(['success'=>false,'message'=>'عملیات تسویه کامل نشد.'],500);
}
