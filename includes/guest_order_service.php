<?php
declare(strict_types=1);

require_once __DIR__ . '/push.php';

final class GuestOrderException extends RuntimeException
{
    public function __construct(
        public string $errorCode,
        string $message,
        public int $httpStatus = 422,
        public array $details = []
    ) { parent::__construct($message); }
}

function guest_order_response(array $order, bool $duplicate): array
{
    $status=(string)$order['status'];
    return [
        'success'=>true,
        'order_code'=>(string)$order['public_code'],
        'order_number'=>order_display_number($order),
        'client_token'=>(string)$order['client_token'],
        'status'=>$status,
        'status_label'=>order_status_label($status),
        'duplicate'=>$duplicate,
        'message'=>$duplicate
            ? customer_message('duplicate_order')
            : ($status==='pending_approval'
                ? 'سفارش ثبت شد؛ منتظر تأیید حضور هستیم.'
                : customer_message('order_received_title',['code'=>(string)$order['public_code']])),
    ];
}

function guest_order_normalize_or_throw(array $data): array
{
    $acceptance=order_acceptance_states();
    if(!$acceptance['cafe']){
        throw new GuestOrderException('ordering_paused',order_acceptance_message('cafe'),423,['scope'=>'cafe']);
    }
    try { return normalize_order_request_payload($data); }
    catch(InvalidArgumentException $e){ throw new GuestOrderException('invalid_order',$e->getMessage(),422); }
}

/** Canonical business mutation. Caller MUST own an open transaction. */
function guest_order_commit_tx(PDO $pdo, array $data): array
{
    if(!$pdo->inTransaction()) throw new LogicException('guest_order_commit_tx requires an open transaction.');
    $payload=guest_order_normalize_or_throw($data);

    $tableStmt=$pdo->prepare('SELECT id,name,code FROM cafe_tables WHERE access_token=? AND active=1 LIMIT 1 FOR UPDATE');
    $tableStmt->execute([$payload['table_token']]);
    $table=$tableStmt->fetch();
    if(!$table) throw new GuestOrderException('invalid_qr',customer_message('invalid_qr'),404);
    $tableId=(int)$table['id'];

    $existingStmt=$pdo->prepare('SELECT id,public_code,client_token,table_id,status,created_at FROM orders WHERE client_token=? LIMIT 1 FOR UPDATE');
    $existingStmt->execute([$payload['client_token']]);
    if($existing=$existingStmt->fetch()){
        if((int)$existing['table_id']!==$tableId) throw new GuestOrderException('invalid_order','شناسه این ارسال با میز دیگری ثبت شده است.',409);
        return guest_order_response($existing,true);
    }

    $session=null;$sessionId=null;$orderStatus='new';
    if(table_sessions_enabled()){
        if($payload['session_token']!==''){
            $sessionStmt=$pdo->prepare("SELECT * FROM table_sessions WHERE table_id=? AND public_token=? AND status='active' LIMIT 1 FOR UPDATE");
            $sessionStmt->execute([$tableId,$payload['session_token']]);
            $session=$sessionStmt->fetch()?:null;
            if(!$session) throw new GuestOrderException('session_inactive','نشست قبلی این میز بسته شده؛ صفحه را تازه کن و دوباره سفارش بده.',409);
        }else{
            $sessionStmt=$pdo->prepare("SELECT * FROM table_sessions WHERE table_id=? AND status IN('active','pending') ORDER BY FIELD(status,'active','pending'),id DESC LIMIT 1 FOR UPDATE");
            $sessionStmt->execute([$tableId]);
            $session=$sessionStmt->fetch()?:null;
            if(!$session) $session=create_table_session($tableId,null,null,null,'pending');
        }
        $sessionId=(int)$session['id'];
        if(settlement_session_has_active_itemized_locked($pdo,$sessionId)) throw new GuestOrderException('itemized_settlement_active','پرداخت جداگانه این حساب شروع شده است؛ برای سفارش تازه لطفاً با صندوق هماهنگ کنید.',409);
        if(accommodation_transfer_blocks_invoice_edit($sessionId)) throw new GuestOrderException('settlement_pending','نتیجه ثبت حساب اقامتگاه هنوز مشخص نشده است؛ لطفاً با همکاران ما هماهنگ کنید.',409);
        $orderStatus=($session['status']??'')==='pending'?'pending_approval':'new';
        if($payload['device_token']!=='') register_session_client($sessionId,$payload['device_token']);
        if($payload['device_token']!==''){
            $pending=$pdo->prepare("SELECT public_code,client_token,id FROM orders WHERE session_id=? AND device_token=? AND status IN('pending_approval','new') ORDER BY id DESC LIMIT 1 FOR UPDATE");
            $pending->execute([$sessionId,$payload['device_token']]);
            if($row=$pending->fetch()){
                throw new GuestOrderException('pending_order_exists','یک سفارش از همین گوشی هنوز منتظر تأیید است؛ همان سفارش را باز کن و تغییر بده.',409,[
                    'order_code'=>(string)$row['public_code'],
                    'client_token'=>(string)$row['client_token'],
                    'order_number'=>order_display_number($row),
                ]);
            }
        }
    }

    $itemsById=order_catalog_items_locked($pdo,array_column($payload['items'],'id'));
    $unavailable=[];$serviceBlocked=[];$priceChanges=[];
    foreach($payload['items'] as $line){
        $item=$itemsById[$line['id']]??null;
        if(!order_catalog_item_is_orderable($item,'guest')){$unavailable[]=$item?(string)$item['name']:('آیتم '.fa_digits($line['id']));continue;}
        $blockedScope=order_acceptance_blocked_scope_for_station((string)$item['preparation_station']);
        if($blockedScope!==null){$serviceBlocked[]=['name'=>(string)$item['name'],'scope'=>$blockedScope];continue;}
        if($line['expected_price']===null||(int)$line['expected_price']!==(int)$item['price'])$priceChanges[]=(string)$item['name'];
    }
    if($unavailable) throw new GuestOrderException('items_unavailable',customer_message('items_unavailable',['items'=>implode('، ',$unavailable)]),409,['items'=>$unavailable]);
    if($serviceBlocked){
        $names=array_values(array_unique(array_column($serviceBlocked,'name')));
        $scopes=array_values(array_unique(array_column($serviceBlocked,'scope')));
        $scope=count($scopes)===1?$scopes[0]:'cafe';
        throw new GuestOrderException('service_unavailable',order_acceptance_message($scope),409,['items'=>$names,'scope'=>$scope]);
    }
    if($priceChanges) throw new GuestOrderException('prices_changed',customer_message('prices_changed'),409,['items'=>$priceChanges]);

    $orderItems=[];$total=0;
    foreach($payload['items'] as $line){
        $item=$itemsById[$line['id']];
        $mode=normalize_fulfillment_mode((string)($line['fulfillment_mode']??'dine_in'));
        if(!order_catalog_item_allows_fulfillment($item,$mode)) throw new GuestOrderException('takeaway_not_allowed','«'.(string)$item['name'].'» فقط داخل کافه قابل سرو است.',409,['item_id'=>(int)$item['id'],'item_name'=>(string)$item['name']]);
        $unit=(int)$item['price'];$lineTotal=$unit*(int)$line['quantity'];$total+=$lineTotal;
        $orderItems[]=['item_id'=>(int)$item['id'],'item_name'=>(string)$item['name'],'unit_price'=>$unit,'quantity'=>(int)$line['quantity'],'item_note'=>(string)$line['note'],'fulfillment_mode'=>$mode,'line_total'=>$lineTotal,'station'=>normalize_preparation_station((string)($item['preparation_station']??'cold_bar')),'sellable_kind'=>normalize_sellable_kind($item['sellable_kind']??null)];
    }

    $publicCode=strtoupper(bin2hex(random_bytes(8)));$business=business_assignment();
    $number=order_allocate_business_number($pdo,(string)$business['business_date']);
    $insert=$pdo->prepare('INSERT INTO orders(public_code,client_token,device_token,table_id,session_id,status,customer_note,total_amount,business_order_number,business_date,business_shift_key,business_shift_label,business_cutoff_snapshot) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $insert->execute([$publicCode,$payload['client_token'],$payload['device_token']!==''?$payload['device_token']:null,$tableId,$sessionId,$orderStatus,$payload['customer_note'],$total,$number,(string)$business['business_date'],(string)$business['shift_key'],(string)$business['shift_label'],(string)$business['cutoff']]);
    $orderId=(int)$pdo->lastInsertId();

    $lineStmt=$pdo->prepare('INSERT INTO order_items(order_id,item_id,item_name,sellable_kind_snapshot,unit_price,quantity,ordered_quantity,item_note,fulfillment_mode,preparation_station,line_total) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
    foreach($orderItems as $line)$lineStmt->execute([$orderId,$line['item_id'],$line['item_name'],$line['sellable_kind'],$line['unit_price'],$line['quantity'],$line['quantity'],$line['item_note'],$line['fulfillment_mode'],$line['station'],$line['line_total']]);
    $pdo->prepare('INSERT INTO order_status_history(order_id,from_status,to_status,actor_user_id) VALUES(?,NULL,?,NULL)')->execute([$orderId,$orderStatus]);

    $created=['id'=>$orderId,'public_code'=>$publicCode,'client_token'=>$payload['client_token'],'table_id'=>$tableId,'status'=>$orderStatus,'created_at'=>date('Y-m-d H:i:s')];
    order_side_effect_best_effort_tx($pdo,'push guest pending order',static function()use($pdo,$table,$orderId):void{
        push_enqueue_event_tx($pdo,'pending_order',[
            'title'=>customer_message('staff_pending_order_title',['table'=>(string)$table['name']]),
            'body'=>customer_message('staff_pending_order_body',['order'=>order_display_label($orderId)]),
            'url'=>push_pending_order_review_url($orderId,(int)$table['id']),
            'tag'=>'order-confirm-'.$orderId,'order_id'=>$orderId,'table_id'=>(int)$table['id'],
        ]);
    });
    return guest_order_response($created,false);
}

function guest_order_commit(PDO $pdo,array $data):array
{
    $pdo->beginTransaction();
    try{$result=guest_order_commit_tx($pdo,$data);$pdo->commit();return $result;}
    catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
