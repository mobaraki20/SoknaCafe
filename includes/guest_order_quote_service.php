<?php
declare(strict_types=1);

require_once __DIR__ . '/guest_order_service.php';
require_once __DIR__ . '/guest_order_manage_service.php';

/**
 * Read-only financial preview for the guest's draft.
 *
 * The preview reports the marginal effect of this draft on the current table account.
 * That matters for account-level fixed discounts: applying the full discount to the
 * guest cart alone would be financially incorrect.
 */
function guest_order_quote(PDO $pdo,array $data):array
{
    $pdo->beginTransaction();
    try{
        $result=guest_order_quote_tx($pdo,$data);
        // Quote is deliberately read-only. Roll back locks/reads rather than committing
        // any incidental state if a future helper changes underneath this boundary.
        $pdo->rollBack();
        return $result;
    }catch(Throwable $e){
        if($pdo->inTransaction())$pdo->rollBack();
        throw $e;
    }
}

function guest_order_quote_tx(PDO $pdo,array $data):array
{
    if(!$pdo->inTransaction())throw new LogicException('guest_order_quote_tx requires an open transaction.');
    $mode=(string)($data['quote_mode']??'create');
    if(!in_array($mode,['create','update','append'],true))throw new GuestOrderException('invalid_order','نوع پیش‌نمایش سفارش معتبر نیست.',422);

    $tableToken=trim((string)($data['table_token']??''));
    $deviceToken=trim((string)($data['device_token']??''));
    if($tableToken===''||strlen($tableToken)>80||strlen($deviceToken)>80)throw new GuestOrderException('invalid_qr',customer_message('invalid_qr'),422);
    $tableStmt=$pdo->prepare('SELECT id,name,code FROM cafe_tables WHERE access_token=? AND active=1 LIMIT 1 FOR UPDATE');
    $tableStmt->execute([$tableToken]);$table=$tableStmt->fetch();
    if(!$table)throw new GuestOrderException('invalid_qr',customer_message('invalid_qr'),404);

    $session=null;$draft=[];
    if($mode==='create'){
        $payload=guest_order_normalize_or_throw($data);
        if(table_sessions_enabled()){
            $sessionToken=(string)$payload['session_token'];
            if($sessionToken!==''){
                $sessionStmt=$pdo->prepare("SELECT * FROM table_sessions WHERE table_id=? AND public_token=? AND status='active' LIMIT 1 FOR UPDATE");
                $sessionStmt->execute([(int)$table['id'],$sessionToken]);$session=$sessionStmt->fetch()?:null;
                if(!$session)throw new GuestOrderException('session_inactive','نشست قبلی این میز بسته شده؛ صفحه را تازه کن و دوباره سفارش بده.',409);
            }else{
                $sessionStmt=$pdo->prepare("SELECT * FROM table_sessions WHERE table_id=? AND status IN('active','pending') ORDER BY FIELD(status,'active','pending'),id DESC LIMIT 1 FOR UPDATE");
                $sessionStmt->execute([(int)$table['id']]);$session=$sessionStmt->fetch()?:null;
            }
            if($session){
                $sessionId=(int)$session['id'];
                if(settlement_session_has_active_itemized_locked($pdo,$sessionId))throw new GuestOrderException('itemized_settlement_active','پرداخت جداگانه این حساب شروع شده است؛ برای سفارش تازه لطفاً با صندوق هماهنگ کنید.',409);
                if(accommodation_transfer_blocks_invoice_edit($sessionId))throw new GuestOrderException('settlement_pending','نتیجه ثبت حساب اقامتگاه هنوز مشخص نشده است؛ لطفاً با همکاران ما هماهنگ کنید.',409);
                if($deviceToken!==''){
                    $pending=$pdo->prepare("SELECT public_code,client_token,id FROM orders WHERE session_id=? AND device_token=? AND status IN('pending_approval','new') ORDER BY id DESC LIMIT 1 FOR UPDATE");
                    $pending->execute([$sessionId,$deviceToken]);
                    if($row=$pending->fetch())throw new GuestOrderException('pending_order_exists','یک سفارش از همین گوشی هنوز منتظر تأیید است؛ همان سفارش را باز کن و تغییر بده.',409,[
                        'order_code'=>(string)$row['public_code'],'client_token'=>(string)$row['client_token'],'order_number'=>order_display_number($row),
                    ]);
                }
            }
        }
        $validated=guest_order_validate_new_lines_locked($pdo,$payload);$draft=(array)$validated['lines'];
    }else{
        $orderCode=trim((string)($data['order_code']??''));
        if($orderCode===''||strlen($orderCode)>32)throw new GuestOrderEditException('invalid_order','سفارش معتبر نیست.',422);
        $identityStmt=$pdo->prepare('SELECT id,session_id FROM orders WHERE public_code=? AND table_id=? AND device_token=? LIMIT 1 FOR UPDATE');
        $identityStmt->execute([$orderCode,(int)$table['id'],$deviceToken]);$identity=$identityStmt->fetch();
        if(!$identity)throw new GuestOrderEditException('order_not_found','این سفارش روی همین دستگاه پیدا نشد.',404);
        $sessionStmt=$pdo->prepare('SELECT * FROM table_sessions WHERE id=? AND table_id=? LIMIT 1 FOR UPDATE');
        $sessionStmt->execute([(int)$identity['session_id'],(int)$table['id']]);$session=$sessionStmt->fetch()?:null;
        if(!$session||!in_array((string)$session['status'],['active','pending'],true))throw new GuestOrderEditException('session_inactive','نشست این میز پایان یافته است.',409);
        $sessionToken=trim((string)($data['session_token']??''));
        if($sessionToken!==''&&!hash_equals((string)$session['public_token'],$sessionToken))throw new GuestOrderEditException('session_inactive','نشست این میز تغییر کرده؛ صفحه را تازه کن.',409);
        if(settlement_session_has_active_itemized_locked($pdo,(int)$session['id']))throw new GuestOrderEditException('itemized_settlement_active','پرداخت جداگانه این حساب شروع شده است؛ برای تغییر سفارش با صندوق هماهنگ کنید.',409);
        if(accommodation_transfer_blocks_invoice_edit((int)$session['id']))throw new GuestOrderEditException('settlement_pending','نتیجه ثبت حساب اقامتگاه هنوز مشخص نشده است؛ لطفاً با همکاران ما هماهنگ کنید.',409);

        $orderStmt=$pdo->prepare('SELECT * FROM orders WHERE id=? AND table_id=? AND device_token=? LIMIT 1 FOR UPDATE');
        $orderStmt->execute([(int)$identity['id'],(int)$table['id'],$deviceToken]);$order=$orderStmt->fetch();
        if(!$order||!guest_order_status_is_mutable((string)$order['status']))throw new GuestOrderEditException('order_not_editable','این سفارش تأیید شده و دیگر ویرایش مستقیم ندارد.',409);
        $existingStmt=$pdo->prepare('SELECT item_id,item_name,sellable_kind_snapshot,unit_price,quantity,item_note,fulfillment_mode,preparation_station,line_total,tax_policy_snapshot,tax_rate_bps_snapshot,tax_rate_version_id,tax_item_policy_version_id FROM order_items WHERE order_id=? ORDER BY id FOR UPDATE');
        $existingStmt->execute([(int)$order['id']]);$existingRows=$existingStmt->fetchAll();
        try{
            $payload=normalize_order_request_payload(array_merge($data,[
                'table_token'=>$tableToken,'session_token'=>(string)$session['public_token'],'device_token'=>$deviceToken,'client_token'=>(string)$order['client_token'],
            ]));
        }catch(InvalidArgumentException $e){throw new GuestOrderEditException('invalid_order',$e->getMessage(),422);}
        $expectedSignature=trim((string)($data['expected_signature']??''));$currentSignature=guest_order_edit_signature($order,$existingRows);
        if($expectedSignature===''||!hash_equals($currentSignature,$expectedSignature))throw new GuestOrderEditException('order_changed','این سفارش در جای دیگری تغییر کرده؛ فهرست سفارش را تازه کن و دوباره ویرایش کن.',409,['current_signature'=>$currentSignature]);
        $validated=validate_guest_order_update_lines($pdo,$payload,$existingRows);$draft=(array)$validated['lines'];
    }

    return guest_order_quote_financials_locked($pdo,$session,$draft);
}

function guest_order_quote_financials_locked(PDO $pdo,?array $session,array $draft):array
{
    $accountLines=[];
    if($session){
        $stmt=$pdo->prepare("SELECT oi.id order_item_id,oi.unit_price,oi.quantity,oi.tax_policy_snapshot,oi.tax_rate_bps_snapshot FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE o.session_id=? AND o.status='accounted' AND oi.quantity>0 ORDER BY oi.id FOR UPDATE");
        $stmt->execute([(int)$session['id']]);$accountLines=$stmt->fetchAll();
    }
    $discountType=(string)($session['discount_type']??'');$discountValue=(int)($session['discount_value']??0);
    $baseSubtotal=array_sum(array_map(static fn(array $line):int=>(int)$line['unit_price']*(int)$line['quantity'],$accountLines));
    $baseDiscount=invoice_discount_amount($baseSubtotal,$discountType,$discountValue);
    $base=tax_calculate_invoice_lines($accountLines,$baseDiscount);

    $projected=$accountLines;$nextId=1;
    foreach($accountLines as $line)$nextId=max($nextId,(int)($line['order_item_id']??0)+1);
    foreach($draft as $line){
        $projected[]=[
            'order_item_id'=>$nextId++,'unit_price'=>(int)$line['unit_price'],'quantity'=>(int)$line['quantity'],
            'tax_policy_snapshot'=>(string)($line['tax']['policy']??'disabled'),'tax_rate_bps_snapshot'=>(int)($line['tax']['rate_bps']??0),
        ];
    }
    $projectedSubtotal=array_sum(array_map(static fn(array $line):int=>(int)$line['unit_price']*(int)$line['quantity'],$projected));
    $projectedDiscount=invoice_discount_amount($projectedSubtotal,$discountType,$discountValue);
    $after=tax_calculate_invoice_lines($projected,$projectedDiscount);
    $keys=['subtotal','discount','net','taxable','tax','total'];$quote=[];
    foreach($keys as $key){
        $delta=(int)$after[$key]-(int)$base[$key];
        if($delta<0)throw new RuntimeException('پیش‌نمایش مالی سفارش با وضعیت حساب سازگار نیست.');
        $quote[$key]=$delta;
    }
    if($quote['subtotal']!==array_sum(array_map(static fn(array $line):int=>(int)$line['line_total'],$draft)))throw new RuntimeException('جمع پیش‌نمایش سفارش با اقلام سبد هماهنگ نیست.');
    if($quote['total']!==$quote['net']+$quote['tax'])throw new RuntimeException('مبلغ نهایی پیش‌نمایش سفارش معتبر نیست.');
    return [
        'success'=>true,
        'financial_preview'=>$quote+[
            'currency'=>'تومان','discount_active'=>$quote['discount']>0,
            'tax_active'=>$quote['tax']>0||array_sum(array_map(static fn(array $line):int=>(string)($line['tax']['policy']??'disabled')!=='disabled'?1:0,$draft))>0,
        ],
    ];
}
