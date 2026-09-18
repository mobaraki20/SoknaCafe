<?php
declare(strict_types=1);
require_once __DIR__ . '/push.php';

final class WaiterCallException extends RuntimeException
{
    public function __construct(
        public string $errorCode,
        string $message,
        public int $httpStatus = 422,
        public array $details = []
    ) { parent::__construct($message); }
}

function waiter_call_create_tx(PDO $pdo, array $data): array
{
    if(!$pdo->inTransaction()) throw new LogicException('waiter_call_create_tx requires an open transaction.');
    $tableToken=trim((string)($data['table_token']??''));
    $publicContext=!empty($data['public_context']);
    $sessionToken=trim((string)($data['session_token']??''));
    $deviceToken=trim((string)($data['device_token']??''));
    $clientToken=trim((string)($data['client_token']??''));

    if($tableToken==='') throw new WaiterCallException('invalid_qr',customer_message('invalid_qr'),404);
    if(!waiter_call_allowed($publicContext)){
        throw new WaiterCallException('waiter_disabled',$publicContext?'فراخوان گارسون از منوی عمومی فعال نیست.':customer_message('waiter_disabled'),$publicContext?403:503);
    }
    if(strlen($clientToken)<16||strlen($clientToken)>80||strlen($deviceToken)<16||strlen($deviceToken)>80){
        throw new WaiterCallException('invalid_request','درخواست کامل نیست.',422);
    }

    $tableStmt=$pdo->prepare('SELECT id,name FROM cafe_tables WHERE access_token=? AND active=1 LIMIT 1 FOR UPDATE');
    $tableStmt->execute([$tableToken]);
    $table=$tableStmt->fetch();
    if(!$table) throw new WaiterCallException('invalid_qr',customer_message('invalid_qr'),404);

    $existing=$pdo->prepare('SELECT public_code,status FROM waiter_calls WHERE client_token=? LIMIT 1 FOR UPDATE');
    $existing->execute([$clientToken]);
    if($found=$existing->fetch()){
        return ['success'=>true,'call_code'=>(string)$found['public_code'],'status'=>(string)$found['status'],'duplicate'=>true,'owned'=>true];
    }

    if($publicContext){
        $deviceRecent=$pdo->prepare('SELECT COUNT(*) FROM waiter_calls WHERE device_token=? AND created_at>=DATE_SUB(NOW(),INTERVAL 1 MINUTE)');
        $deviceRecent->execute([$deviceToken]);
        if((int)$deviceRecent->fetchColumn()>=3) throw new WaiterCallException('rate_limited','تعداد درخواست‌ها زیاد شده؛ یک دقیقه دیگر دوباره امتحان کن.',429);
    }

    $session=null;
    if(!$publicContext&&table_sessions_enabled()&&$sessionToken!==''){
        $sessionStmt=$pdo->prepare("SELECT id,public_token FROM table_sessions WHERE public_token=? AND table_id=? AND status='active' LIMIT 1");
        $sessionStmt->execute([$sessionToken,(int)$table['id']]);
        $session=$sessionStmt->fetch()?:null;
    }

    $active=$pdo->prepare("SELECT id,public_code,status FROM waiter_calls WHERE table_id=? AND status IN('new','accepted') ORDER BY id DESC LIMIT 1");
    $active->execute([(int)$table['id']]);
    if($row=$active->fetch()){
        return ['success'=>true,'call_code'=>(string)$row['public_code'],'status'=>(string)$row['status'],'shared'=>true,'owned'=>false];
    }

    $recent=$pdo->prepare('SELECT COUNT(*) FROM waiter_calls WHERE table_id=? AND created_at>=DATE_SUB(NOW(),INTERVAL 1 MINUTE)');
    $recent->execute([(int)$table['id']]);
    if((int)$recent->fetchColumn()>=3) throw new WaiterCallException('rate_limited','چند لحظه صبر کن و دوباره امتحان کن.',429);

    $publicCode='W'.date('ymd').strtoupper(bin2hex(random_bytes(4)));
    $business=business_assignment();
    $insert=$pdo->prepare('INSERT INTO waiter_calls(public_code,client_token,device_token,table_id,session_id,status,active_table_guard,business_date,business_shift_key,business_shift_label,business_cutoff_snapshot) VALUES(?,?,?,?,?,"new",?,?,?,?,?)');
    try{
        $insert->execute([$publicCode,$clientToken,$deviceToken?:null,(int)$table['id'],$session['id']??null,(int)$table['id'],(string)$business['business_date'],(string)$business['shift_key'],(string)$business['shift_label'],(string)$business['cutoff']]);
    }catch(PDOException $e){
        if((string)$e->getCode()==='23000'){
            $lookup=$pdo->prepare("SELECT public_code,status FROM waiter_calls WHERE table_id=? AND status IN('new','accepted') ORDER BY id DESC LIMIT 1");
            $lookup->execute([(int)$table['id']]);
            if($row=$lookup->fetch()) return ['success'=>true,'call_code'=>(string)$row['public_code'],'status'=>(string)$row['status'],'shared'=>true,'owned'=>false];
        }
        throw $e;
    }

    if($session&&$deviceToken!=='') register_session_client((int)$session['id'],$deviceToken);
    push_enqueue_event_tx($pdo,'waiter_call',[
        'title'=>'فراخوان تازه از '.(string)$table['name'],
        'body'=>'مهمان درخواست حضور گارسون ثبت کرده است.',
        'url'=>asset('operator/index.php').'?attention_filter=calls&call='.rawurlencode($publicCode),
        'tag'=>'call-'.$publicCode,
        'call_id'=>(int)$pdo->lastInsertId(),
    ]);
    return ['success'=>true,'call_code'=>$publicCode,'status'=>'new','owned'=>true];
}

function waiter_call_create(PDO $pdo,array $data):array
{
    $pdo->beginTransaction();
    try{$result=waiter_call_create_tx($pdo,$data);$pdo->commit();return $result;}
    catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}


function waiter_call_table(PDO $pdo,string $tableToken,bool $forUpdate=false):?array
{
    if($tableToken==='')return null;
    $sql='SELECT id,name,access_token FROM cafe_tables WHERE access_token=? AND active=1 LIMIT 1'.($forUpdate?' FOR UPDATE':'');
    $stmt=$pdo->prepare($sql);$stmt->execute([$tableToken]);$row=$stmt->fetch();
    return $row?:null;
}

function waiter_call_status(PDO $pdo,array $data):array
{
    $tableToken=trim((string)($data['table_token']??''));
    $clientToken=trim((string)($data['client_token']??''));
    $code=trim((string)($data['call_code']??''));
    $table=waiter_call_table($pdo,$tableToken);
    if(!$table)throw new WaiterCallException('invalid_qr',customer_message('invalid_qr'),404);
    if($code===''){
        $find=$pdo->prepare("SELECT public_code,status,updated_at FROM waiter_calls WHERE table_id=? AND status IN('new','accepted') ORDER BY id DESC LIMIT 1");
        $find->execute([(int)$table['id']]);
    }else{
        $find=$pdo->prepare('SELECT public_code,status,updated_at FROM waiter_calls WHERE public_code=? AND table_id=? LIMIT 1');
        $find->execute([$code,(int)$table['id']]);
    }
    $row=$find->fetch();
    if(!$row)return ['success'=>true,'status'=>'none'];
    $owned=false;
    if($clientToken!==''&&$code!==''){
        $owner=$pdo->prepare('SELECT COUNT(*) FROM waiter_calls WHERE public_code=? AND table_id=? AND client_token=?');
        $owner->execute([$code,(int)$table['id'],$clientToken]);$owned=(bool)$owner->fetchColumn();
    }
    return [
        'success'=>true,'call_code'=>(string)$row['public_code'],'status'=>(string)$row['status'],
        'updated_at'=>(string)$row['updated_at'],'owned'=>$owned,
    ];
}

function waiter_call_cancel_tx(PDO $pdo,array $data):array
{
    if(!$pdo->inTransaction())throw new LogicException('waiter_call_cancel_tx requires an open transaction.');
    $tableToken=trim((string)($data['table_token']??''));
    $clientToken=trim((string)($data['client_token']??''));
    $code=trim((string)($data['call_code']??''));
    if($clientToken===''||$code==='')throw new WaiterCallException('not_owner','این درخواست از همین گوشی ثبت نشده.',403);
    $table=waiter_call_table($pdo,$tableToken,true);
    if(!$table)throw new WaiterCallException('invalid_qr',customer_message('invalid_qr'),404);
    $lookup=$pdo->prepare('SELECT status,client_token FROM waiter_calls WHERE public_code=? AND table_id=? LIMIT 1 FOR UPDATE');
    $lookup->execute([$code,(int)$table['id']]);$row=$lookup->fetch();
    if(!$row)return ['success'=>true,'status'=>'unchanged'];
    if(!hash_equals((string)$row['client_token'],$clientToken))throw new WaiterCallException('not_owner','این درخواست از همین گوشی ثبت نشده.',403);
    if((string)$row['status']==='cancelled')return ['success'=>true,'status'=>'cancelled','duplicate'=>true];
    if((string)$row['status']!=='new')return ['success'=>true,'status'=>'unchanged'];
    $update=$pdo->prepare("UPDATE waiter_calls SET status='cancelled',active_table_guard=NULL,cancelled_at=NOW(),cancel_reason='customer' WHERE public_code=? AND table_id=? AND client_token=? AND status='new'");
    $update->execute([$code,(int)$table['id'],$clientToken]);
    return ['success'=>true,'status'=>$update->rowCount()?'cancelled':'unchanged'];
}

function waiter_call_cancel(PDO $pdo,array $data):array
{
    $pdo->beginTransaction();
    try{$result=waiter_call_cancel_tx($pdo,$data);$pdo->commit();return $result;}
    catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
