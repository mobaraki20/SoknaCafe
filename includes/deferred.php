<?php
declare(strict_types=1);

require_once __DIR__.'/relay_protocol.php';
require_once __DIR__.'/relay_client.php';
require_once __DIR__.'/expenses.php';

final class SoknaDeferredNeedsReview extends RuntimeException
{
    public function __construct(
        public readonly string $reviewType,
        public readonly string $reasonCode,
        string $message,
        public readonly ?int $financialPeriodId=null
    ){ parent::__construct($message); }
}

function sokna_deferred_financial_kinds(): array
{
    return ['supply.receipt','inventory.waste','subscriber.payment','expense.create'];
}

function sokna_deferred_actor_id(string $projectionId): int
{
    return preg_match('/^user:(\d+)$/',$projectionId,$m) ? (int)$m[1] : 0;
}

function sokna_deferred_actor_locked(PDO $pdo,string $projectionId): array
{
    $id=sokna_deferred_actor_id($projectionId);
    if($id<1)throw new RuntimeException('هویت کاربر راه‌دور معتبر نیست.');
    $stmt=$pdo->prepare('SELECT id,username,display_name,role,active FROM users WHERE id=? FOR UPDATE');
    $stmt->execute([$id]);$user=$stmt->fetch();
    if(!$user||(int)$user['active']!==1)throw new RuntimeException('حساب کاربری دیگر فعال نیست.');
    return $user;
}

function sokna_deferred_assert_permission(array $user,string $kind,array $payload): void
{
    $allowed=match($kind){
        'supply.need.create'=>user_can_report_supply_needs($user),
        'supply.status.prepare','supply.status.return','supply.receipt'=>user_can_manage_purchases($user),
        'inventory.waste','inventory.count_draft'=>user_can_inventory_operate($user),
        'subscriber.payment'=>(string)($user['role']??'')==='admin'||user_has_capability('cashier_accounts',$user),
        'expense.create'=>(string)($user['role']??'')==='admin',
        default=>false,
    };
    if(!$allowed)throw new RuntimeException('دسترسی این عملیات دیگر برای حساب کاربری فعال نیست.');
    if($kind==='supply.need.create'){
        $department=inventory_normalize_department((string)($payload['department']??'shared'))??'shared';
        if(!in_array($department,supply_need_allowed_departments($user),true))throw new RuntimeException('این بخش برای ثبت درخواست خرید در اختیار کاربر نیست.');
    }
}

function sokna_deferred_receipt_locked(PDO $pdo,string $installationId,string $requestId): ?array
{
    $stmt=$pdo->prepare('SELECT * FROM deferred_work_receipts WHERE installation_id=? AND request_id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$installationId,$requestId]);$row=$stmt->fetch();
    return is_array($row)?$row:null;
}

function sokna_deferred_receipt_result(array $row): array
{
    $result=json_decode((string)($row['result_json']??''),true);
    return [
        'state'=>(string)$row['state'],
        'result'=>is_array($result)?$result:[],
        'error_code'=>(string)($row['error_code']??''),
        'receipt_id'=>(int)$row['id'],
        'idempotent'=>true,
    ];
}

function sokna_deferred_insert_receipt_locked(
    PDO $pdo,array $envelope,string $installationId,array $user,string $state,array $result,string $errorCode,?int $periodId,bool $publicReconcilePending=false
): int {
    $json=sokna_relay_canonical_json($envelope);
    $hash=sokna_relay_request_hash($envelope);
    $stmt=$pdo->prepare('INSERT INTO deferred_work_receipts(installation_id,request_id,request_hash,kind,actor_projection_id,actor_user_id,occurred_at,envelope_json,state,result_json,error_code,financial_period_id,public_reconcile_pending,committed_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $installationId,(string)$envelope['request_id'],$hash,(string)$envelope['kind'],(string)$envelope['actor_projection_id'],(int)$user['id'],
        date('Y-m-d H:i:s',strtotime((string)$envelope['occurred_at'])),$json,$state,
        json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),
        $errorCode!==''?$errorCode:null,$periodId,$publicReconcilePending?1:0,$state==='committed'?date('Y-m-d H:i:s'):null,
    ]);
    return (int)$pdo->lastInsertId();
}

function sokna_deferred_create_review_locked(
    PDO $pdo,array $envelope,string $installationId,array $user,SoknaDeferredNeedsReview $review
): array {
    $receiptId=sokna_deferred_insert_receipt_locked(
        $pdo,$envelope,$installationId,$user,'needs_review',[
            'review_type'=>$review->reviewType,'message'=>$review->getMessage(),'financial_period_id'=>$review->financialPeriodId,
        ],$review->reasonCode,$review->financialPeriodId
    );
    $stmt=$pdo->prepare("INSERT INTO deferred_review_items(receipt_id,review_type,financial_period_id,reason_code,message,state) VALUES(?,?,?,?,?,'pending')");
    $stmt->execute([$receiptId,$review->reviewType,$review->financialPeriodId,$review->reasonCode,text_substr($review->getMessage(),0,500)]);
    audit_log_write_strict($pdo,'deferred.needs_review','deferred_work_receipt',$receiptId,[
        'request_id'=>$envelope['request_id'],'kind'=>$envelope['kind'],'review_type'=>$review->reviewType,
        'reason_code'=>$review->reasonCode,'financial_period_id'=>$review->financialPeriodId,'occurred_at'=>$envelope['occurred_at'],
    ],(int)$user['id']);
    return ['state'=>'needs_review','result'=>['review_id'=>(int)$pdo->lastInsertId(),'message'=>$review->getMessage()],'error_code'=>$review->reasonCode,'receipt_id'=>$receiptId,'idempotent'=>false];
}

function sokna_deferred_event_period_locked(PDO $pdo,array $envelope,int $actorUserId): ?array
{
    if(!in_array((string)$envelope['kind'],sokna_deferred_financial_kinds(),true))return null;
    return financial_period_for_date_locked($pdo,substr(date('Y-m-d H:i:s',strtotime((string)$envelope['occurred_at'])),0,10),$actorUserId);
}

function sokna_deferred_apply_locked(PDO $pdo,array $envelope,array $user,?array $period,array $options=[]): array
{
    $kind=(string)$envelope['kind'];$payload=(array)$envelope['payload'];$actor=(int)$user['id'];
    $allowConflict=!empty($options['allow_conflict_override']);
    $occurredAt=inventory_normalize_occurred_at((string)$envelope['occurred_at'],'رخداد راه‌دور');

    if($kind==='supply.need.create'){
        $id=supply_request_add_locked($pdo,$payload,$actor);
        return ['supply_need_id'=>$id];
    }

    if($kind==='supply.status.prepare'){
        try{
            return supply_mark_group_preparing_locked($pdo,trim((string)($payload['group_key']??'')),$actor,$allowConflict?null:(int)($payload['expected_quantity_base']??-1));
        }catch(RuntimeException $e){
            if(!$allowConflict)throw new SoknaDeferredNeedsReview('conflict','supply_state_changed',$e->getMessage());
            throw $e;
        }
    }

    if($kind==='supply.status.return'){
        $outcome=(string)($payload['outcome']??'returned');
        if(!in_array($outcome,['returned','unavailable'],true))throw new RuntimeException('وضعیت خرید معتبر نیست.');
        try{
            return supply_return_group_from_preparing_locked($pdo,trim((string)($payload['group_key']??'')),$actor,$outcome,$allowConflict?null:(int)($payload['expected_preparing_quantity_base']??-1));
        }catch(RuntimeException $e){
            if(!$allowConflict)throw new SoknaDeferredNeedsReview('conflict','supply_state_changed',$e->getMessage());
            throw $e;
        }
    }

    if($kind==='supply.receipt'){
        $data=$payload;
        $data['request_token']=substr(hash('sha256','deferred:'.(string)$envelope['request_id']),0,32);
        $data['occurred_at']=$occurredAt;
        if($allowConflict)unset($data['expected_preparing_quantity_base']);
        try{
            return supply_receive_preparing_locked($pdo,trim((string)($payload['group_key']??'')),$data,$actor);
        }catch(RuntimeException $e){
            if(!$allowConflict)throw new SoknaDeferredNeedsReview('conflict','supply_receipt_state_changed',$e->getMessage(),$period['id']??null);
            throw $e;
        }
    }

    if($kind==='inventory.waste'){
        $itemId=(int)($payload['inventory_item_id']??0);
        $item=inventory_item($pdo,$itemId,true);
        if(!$item||(int)$item['active']!==1)throw new RuntimeException('کالای انبار معتبر نیست.');
        $balance=inventory_balance_locked($pdo,$itemId);
        $expected=trim((string)($envelope['expected_version']??$payload['expected_balance_version']??''));
        if(!$allowConflict&&$expected!==''&&!hash_equals((string)$balance['updated_at'],$expected)){
            throw new SoknaDeferredNeedsReview('conflict','inventory_balance_changed','موجودی این کالا بعد از نسخه راه‌دور تغییر کرده است.',$period['id']??null);
        }
        $qty=inventory_major_to_base($payload['quantity_major']??'',(string)$item['base_unit']);
        if($qty<1)throw new RuntimeException('مقدار ضایعات باید بیشتر از صفر باشد.');
        $movement=inventory_record_movement_locked($pdo,[
            'item_id'=>$itemId,'movement_type'=>'waste','quantity_base'=>-$qty,
            'department'=>inventory_normalize_department((string)($payload['department']??$item['default_department']))??'shared',
            'source_type'=>'deferred_waste','source_id'=>(string)$envelope['request_id'],
            'idempotency_key'=>'deferred:waste:'.(string)$envelope['request_id'],
            'metadata'=>['origin'=>'public_deferred'],'note'=>text_substr(trim((string)($payload['note']??'')),0,500)?:null,
            'actor_user_id'=>$actor,'occurred_at'=>$occurredAt,
        ]);
        return ['movement_id'=>$movement,'quantity_base'=>$qty];
    }

    if($kind==='inventory.count_draft'){
        $sessionId=(int)($payload['session_id']??0);$lineId=(int)($payload['line_id']??0);
        $check=$pdo->prepare('SELECT session_type,status FROM inventory_count_sessions WHERE id=? FOR UPDATE');
        $check->execute([$sessionId]);$session=$check->fetch();
        if(!$session||(string)$session['status']!=='draft')throw new SoknaDeferredNeedsReview('conflict','count_not_draft','این شمارش دیگر در وضعیت پیش‌نویس نیست.');
        if((string)$session['session_type']==='opening')throw new RuntimeException('موجودی اولیه فقط روی Local قابل ویرایش است.');
        try{
            return inventory_count_update_line_locked($pdo,$sessionId,$lineId,
                array_key_exists('actual_major',$payload)?(string)$payload['actual_major']:null,
                null,(string)($payload['note']??''),$actor,$allowConflict?null:(string)($envelope['expected_version']??'')
            );
        }catch(InventoryCountStateConflict $e){
            throw new SoknaDeferredNeedsReview('conflict','count_line_changed',$e->getMessage());
        }
    }

    if($kind==='subscriber.payment'){
        $subscriberId=(int)($payload['subscriber_id']??0);$amount=(int)($payload['amount']??0);
        if($subscriberId<1||$amount<1)throw new RuntimeException('اطلاعات پرداخت مشترک کامل نیست.');
        $current=subscriber_balance($pdo,$subscriberId,true);
        $hasExpected=array_key_exists('expected_balance',$payload);
        if(!$allowConflict&&$hasExpected&&$current!==(int)$payload['expected_balance']){
            throw new SoknaDeferredNeedsReview('conflict','subscriber_balance_changed','مانده حساب مشترک بعد از نسخه راه‌دور تغییر کرده است.',$period['id']??null);
        }
        try{
            $entry=subscriber_insert_ledger_locked(
                $pdo,$subscriberId,'payment',-$amount,$actor,null,null,
                text_substr(trim((string)($payload['reference']??'')),0,120),
                text_substr(trim((string)($payload['reason']??'پرداخت ثبت‌شده در حالت راه‌دور')),0,300),
                null,(int)($period['id']??0),'deferred:subscriber-payment:'.(string)$envelope['request_id']
            );
            return ['ledger_entry_id'=>(int)$entry['id'],'balance_after'=>(int)$entry['balance_after']];
        }catch(RuntimeException $e){
            if(!$allowConflict)throw new SoknaDeferredNeedsReview('conflict','subscriber_payment_review',$e->getMessage(),$period['id']??null);
            throw $e;
        }
    }

    if($kind==='expense.create'){
        $entry=expense_create_locked(
            $pdo,trim((string)($payload['category_key']??'')),(int)($payload['amount']??0),$occurredAt,
            (string)($payload['description']??''),$actor,'deferred:'.(string)$envelope['request_id'],(int)($period['id']??0)
        );
        return ['expense_id'=>(int)$entry['id'],'financial_period_id'=>(int)$entry['financial_period_id']];
    }

    throw new RuntimeException('نوع کار Deferred پشتیبانی نمی‌شود.');
}

function sokna_deferred_dispatch(array $envelope): array
{
    $validation=sokna_deferred_validate_envelope($envelope);
    if(!$validation['ok'])return ['state'=>'rejected','result'=>[],'error_code'=>'invalid_envelope','fields'=>$validation['errors']];
    $cfg=sokna_relay_config();$installationId=trim((string)($cfg['installation_id']??''));
    if($installationId==='')throw new RuntimeException('شناسه اتصال Public تنظیم نشده است.');
    $requestId=(string)$envelope['request_id'];$requestHash=sokna_relay_request_hash($envelope);
    $pdo=db();$pdo->beginTransaction();
    try{
        $existing=sokna_deferred_receipt_locked($pdo,$installationId,$requestId);
        if($existing){
            if(!hash_equals((string)$existing['request_hash'],$requestHash))throw new RuntimeException('شناسه Deferred با محتوای دیگری قبلاً ثبت شده است.');
            $out=sokna_deferred_receipt_result($existing);$pdo->commit();return $out;
        }
        $user=sokna_deferred_actor_locked($pdo,(string)$envelope['actor_projection_id']);
        try{sokna_deferred_assert_permission($user,(string)$envelope['kind'],(array)$envelope['payload']);}
        catch(RuntimeException $e){
            $receipt=sokna_deferred_insert_receipt_locked($pdo,$envelope,$installationId,$user,'rejected',[],'permission_denied',null);
            audit_log_write_strict($pdo,'deferred.rejected','deferred_work_receipt',$receipt,['request_id'=>$requestId,'kind'=>$envelope['kind'],'reason'=>'permission_denied'],(int)$user['id']);
            $pdo->commit();return ['state'=>'rejected','result'=>[],'error_code'=>'permission_denied','receipt_id'=>$receipt,'idempotent'=>false];
        }

        $period=sokna_deferred_event_period_locked($pdo,$envelope,(int)$user['id']);
        if($period&&(string)$period['status']==='closed'){
            $review=new SoknaDeferredNeedsReview('late_correction','closed_financial_period','این رخداد متعلق به یک دوره مالی بسته است و فقط پس از بررسی صریح قابل ثبت است.',(int)$period['id']);
            $out=sokna_deferred_create_review_locked($pdo,$envelope,$installationId,$user,$review);$pdo->commit();return $out;
        }

        try{
            $result=sokna_deferred_apply_locked($pdo,$envelope,$user,$period);
        }catch(SoknaDeferredNeedsReview $review){
            $out=sokna_deferred_create_review_locked($pdo,$envelope,$installationId,$user,$review);$pdo->commit();return $out;
        }catch(InvalidArgumentException|RuntimeException $e){
            $receipt=sokna_deferred_insert_receipt_locked($pdo,$envelope,$installationId,$user,'rejected',[],'business_validation',$period['id']??null);
            audit_log_write_strict($pdo,'deferred.rejected','deferred_work_receipt',$receipt,['request_id'=>$requestId,'kind'=>$envelope['kind'],'reason'=>'business_validation','message'=>text_substr($e->getMessage(),0,300)],(int)$user['id']);
            $pdo->commit();return ['state'=>'rejected','result'=>[],'error_code'=>'business_validation','message'=>$e->getMessage(),'receipt_id'=>$receipt,'idempotent'=>false];
        }

        $receipt=sokna_deferred_insert_receipt_locked($pdo,$envelope,$installationId,$user,'committed',$result,'',$period['id']??null);
        audit_log_write_strict($pdo,'deferred.committed','deferred_work_receipt',$receipt,['request_id'=>$requestId,'kind'=>$envelope['kind'],'occurred_at'=>$envelope['occurred_at']],(int)$user['id']);
        $pdo->commit();
        return ['state'=>'committed','result'=>$result,'error_code'=>'','receipt_id'=>$receipt,'idempotent'=>false];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function sokna_deferred_pending_reviews(PDO $pdo,int $limit=100): array
{
    $limit=max(1,min(250,$limit));
    return $pdo->query("SELECT r.id review_id,r.review_type,r.financial_period_id,r.reason_code,r.message,r.created_at,d.id receipt_id,d.request_id,d.kind,d.actor_user_id,d.occurred_at,u.display_name actor_name,fp.title period_title
        FROM deferred_review_items r JOIN deferred_work_receipts d ON d.id=r.receipt_id
        LEFT JOIN users u ON u.id=d.actor_user_id LEFT JOIN financial_periods fp ON fp.id=r.financial_period_id
        WHERE r.state='pending' ORDER BY r.created_at,r.id LIMIT ".$limit)->fetchAll(PDO::FETCH_ASSOC);
}

function sokna_deferred_resolve_review(int $reviewId,string $decision,string $reason,int $reviewerUserId): array
{
    if(!in_array($decision,['approve','reject'],true))throw new RuntimeException('تصمیم بررسی معتبر نیست.');
    $reason=text_substr(trim($reason),0,500);if($reason==='')throw new RuntimeException('دلیل تصمیم را ثبت کن.');
    $pdo=db();$pdo->beginTransaction();
    try{
        $stmt=$pdo->prepare('SELECT r.*,d.installation_id,d.request_id,d.request_hash,d.kind,d.actor_projection_id,d.envelope_json,d.state receipt_state,d.result_json FROM deferred_review_items r JOIN deferred_work_receipts d ON d.id=r.receipt_id WHERE r.id=? FOR UPDATE');
        $stmt->execute([$reviewId]);$review=$stmt->fetch();
        if(!$review)throw new RuntimeException('مورد بررسی پیدا نشد.');
        if((string)$review['state']!=='pending'){ $pdo->commit();return ['state'=>(string)$review['state'],'idempotent'=>true]; }
        $mgr=$pdo->prepare('SELECT id,role,active FROM users WHERE id=? FOR UPDATE');$mgr->execute([$reviewerUserId]);$manager=$mgr->fetch();
        if(!$manager||(int)$manager['active']!==1||(string)$manager['role']!=='admin')throw new RuntimeException('فقط مدیر فعال می‌تواند مورد Deferred را تعیین تکلیف کند.');

        if($decision==='reject'){
            $pdo->prepare("UPDATE deferred_review_items SET state='rejected',resolved_by_user_id=?,resolution_reason=?,resolved_at=NOW() WHERE id=?")->execute([$reviewerUserId,$reason,$reviewId]);
            $pdo->prepare("UPDATE deferred_work_receipts SET state='rejected',error_code='review_rejected',result_json=?,public_reconcile_pending=1 WHERE id=?")
                ->execute([json_encode(['review_id'=>$reviewId,'resolution_reason'=>$reason],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),(int)$review['receipt_id']]);
            audit_log_write_strict($pdo,'deferred.review_rejected','deferred_review',$reviewId,['request_id'=>$review['request_id'],'kind'=>$review['kind'],'reason'=>$reason],$reviewerUserId);
            $pdo->commit();return ['state'=>'rejected','idempotent'=>false];
        }

        $envelope=json_decode((string)$review['envelope_json'],true);
        if(!is_array($envelope))throw new RuntimeException('اطلاعات رخداد Deferred قابل بازیابی نیست.');
        $user=sokna_deferred_actor_locked($pdo,(string)$review['actor_projection_id']);
        sokna_deferred_assert_permission($user,(string)$envelope['kind'],(array)$envelope['payload']);
        $period=sokna_deferred_event_period_locked($pdo,$envelope,(int)$user['id']);
        $result=sokna_deferred_apply_locked($pdo,$envelope,$user,$period,['allow_closed_period'=>true,'allow_conflict_override'=>true]);
        $pdo->prepare("UPDATE deferred_review_items SET state='approved',resolved_by_user_id=?,resolution_reason=?,resolved_at=NOW() WHERE id=?")->execute([$reviewerUserId,$reason,$reviewId]);
        $pdo->prepare("UPDATE deferred_work_receipts SET state='committed',result_json=?,error_code=NULL,public_reconcile_pending=1,committed_at=NOW() WHERE id=?")
            ->execute([json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),(int)$review['receipt_id']]);
        audit_log_write_strict($pdo,'deferred.review_approved','deferred_review',$reviewId,['request_id'=>$review['request_id'],'kind'=>$review['kind'],'reason'=>$reason,'result'=>$result],$reviewerUserId);
        $pdo->commit();return ['state'=>'committed','result'=>$result,'idempotent'=>false];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function sokna_deferred_reconcile_public(int $limit=20): array
{
    $pdo=db();$stmt=$pdo->prepare("SELECT id,request_id,state,result_json,error_code FROM deferred_work_receipts WHERE public_reconcile_pending=1 AND state IN('committed','rejected') ORDER BY updated_at,id LIMIT ?");
    $stmt->bindValue(1,max(1,min(100,$limit)),PDO::PARAM_INT);$stmt->execute();
    $ok=0;$failed=0;
    foreach($stmt->fetchAll() as $row){
        $result=json_decode((string)($row['result_json']??''),true);if(!is_array($result))$result=[];
        try{$res=sokna_relay_http('POST','/api/v1/local/deferred/reconcile.php',['request_id'=>(string)$row['request_id'],'state'=>(string)$row['state'],'result'=>$result,'error_code'=>(string)($row['error_code']??'')]);}
        catch(Throwable){$failed++;continue;}
        if(!empty($res['ok'])){$pdo->prepare('UPDATE deferred_work_receipts SET public_reconcile_pending=0 WHERE id=? AND public_reconcile_pending=1')->execute([(int)$row['id']]);$ok++;}else{$failed++;}
    }
    return ['reconciled'=>$ok,'failed'=>$failed];
}

function sokna_deferred_period_close_status(PDO $pdo,array $period): array
{
    $localStmt=$pdo->prepare("SELECT COUNT(*) FROM deferred_review_items WHERE financial_period_id=? AND state='pending'");
    $localStmt->execute([(int)$period['id']]);$localPending=(int)$localStmt->fetchColumn();
    $cfg=sokna_relay_config();
    if(empty($cfg['enabled'])){
        return ['known'=>true,'paired'=>false,'local_pending_reviews'=>$localPending,'counts'=>['pending_sync'=>0,'needs_review'=>0,'committed'=>0,'rejected'=>0],'blocking'=>$localPending];
    }
    try{$remote=sokna_relay_http('POST','/api/v1/local/deferred/period-status.php',['from_date'=>(string)$period['start_date'],'to_date'=>(string)$period['end_date']]);}
    catch(Throwable $e){return ['known'=>false,'paired'=>true,'local_pending_reviews'=>$localPending,'blocking'=>max(1,$localPending),'error'=>'public_unreachable'];}
    if(empty($remote['ok']))return ['known'=>false,'paired'=>true,'local_pending_reviews'=>$localPending,'blocking'=>max(1,$localPending),'error'=>(string)($remote['error']??'public_unknown'),'http_status'=>(int)($remote['_http_status']??0)];
    $counts=is_array($remote['counts']??null)?$remote['counts']:[];
    $blocking=(int)($remote['blocking']??0)+$localPending;
    return ['known'=>true,'paired'=>true,'local_pending_reviews'=>$localPending,'counts'=>$counts,'blocking'=>$blocking];
}

function sokna_deferred_record_close_override_locked(PDO $pdo,int $periodId,int $actorUserId,string $reason,array $status): int
{
    $reason=text_substr(trim($reason),0,500);if($reason==='')throw new RuntimeException('دلیل عبور از کنترل Deferred را ثبت کن.');
    $json=json_encode($status,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $stmt=$pdo->prepare('INSERT INTO financial_period_close_overrides(financial_period_id,actor_user_id,reason,public_status_json) VALUES(?,?,?,?)');
    $stmt->execute([$periodId,$actorUserId,$reason,$json]);$id=(int)$pdo->lastInsertId();
    audit_log_write_strict($pdo,'financial_period.deferred_override','financial_period',$periodId,['override_id'=>$id,'reason'=>$reason,'status'=>$status],$actorUserId);
    return $id;
}
