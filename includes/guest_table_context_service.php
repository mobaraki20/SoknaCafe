<?php
declare(strict_types=1);

final class GuestTableContextException extends RuntimeException
{
    public function __construct(
        public string $errorCode,
        string $message,
        public int $httpStatus=422
    ){parent::__construct($message);}
}

function guest_table_context(PDO $pdo,array $data):array
{
    $tableToken=trim((string)($data['table_token']??''));
    $deviceToken=trim((string)($data['device_token']??''));
    if($tableToken===''||strlen($tableToken)>80||strlen($deviceToken)>80){
        throw new GuestTableContextException('invalid_qr',customer_message('invalid_qr'),422);
    }
    $stmt=$pdo->prepare('SELECT id,name,code FROM cafe_tables WHERE access_token=? AND active=1 LIMIT 1');
    $stmt->execute([$tableToken]);$table=$stmt->fetch();
    if(!$table)throw new GuestTableContextException('invalid_qr',customer_message('invalid_qr'),404);

    $session=null;$pendingSession=null;$lateJoin=false;
    if(table_sessions_enabled()){
        $active=$pdo->prepare("SELECT * FROM table_sessions WHERE table_id=? AND status='active' ORDER BY started_at DESC,id DESC LIMIT 1");
        $active->execute([(int)$table['id']]);$session=$active->fetch()?:null;
        if(!$session){
            $pending=$pdo->prepare("SELECT * FROM table_sessions WHERE table_id=? AND status='pending' ORDER BY started_at DESC,id DESC LIMIT 1");
            $pending->execute([(int)$table['id']]);$pendingSession=$pending->fetch()?:null;
        }
        if($session&&$deviceToken!==''){
            $knownStmt=$pdo->prepare('SELECT id FROM table_session_clients WHERE session_id=? AND device_token=? LIMIT 1');
            $knownStmt->execute([(int)$session['id'],$deviceToken]);$known=(bool)$knownStmt->fetchColumn();
            $alertMinutes=max(5,(int)setting('new_device_alert_minutes','20'));
            $lateJoin=!$known&&(time()-strtotime((string)$session['started_at'])>$alertMinutes*60);
            $upsert=$pdo->prepare('INSERT INTO table_session_clients(session_id,device_token,first_seen_at,last_seen_at) VALUES(?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE last_seen_at=NOW()');
            $upsert->execute([(int)$session['id'],$deviceToken]);
        }
    }

    $call=$pdo->prepare("SELECT public_code,status,created_at FROM waiter_calls WHERE table_id=? AND status IN('new','accepted') ORDER BY created_at DESC LIMIT 1");
    $call->execute([(int)$table['id']]);$activeCall=$call->fetch()?:null;
    $acceptance=order_acceptance_states();

    return [
        'success'=>true,
        'table'=>['id'=>(int)$table['id'],'name'=>(string)$table['name'],'code'=>(string)$table['code']],
        'session'=>$session?['token'=>(string)$session['public_token'],'started_at'=>(string)$session['started_at'],'status'=>(string)$session['status']]:null,
        'pending_session'=>$pendingSession?['token'=>(string)$pendingSession['public_token'],'started_at'=>(string)$pendingSession['started_at'],'status'=>(string)$pendingSession['status']]:null,
        'can_order'=>(bool)$acceptance['cafe'],
        'order_acceptance'=>$acceptance,
        'order_acceptance_messages'=>[
            'cafe'=>order_acceptance_message('cafe'),
            'kitchen'=>order_acceptance_message('kitchen'),
            'bar'=>order_acceptance_message('bar'),
        ],
        'station_states'=>station_busy_states(),'station_state_hash'=>station_state_hash(),
        'waiter_enabled'=>setting_bool('waiter_call_enabled',true),'active_call'=>$activeCall,
        'late_join'=>$lateJoin,'requires_operator_confirmation'=>table_sessions_enabled()&&$session===null,
    ];
}
