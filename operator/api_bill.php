<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/maintenance.php';
require_once dirname(__DIR__) . '/includes/push.php';
maintenance_guard_json();
require_capability('cashier_accounts');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success'=>false],405);
$data=request_json();
if (!csrf_valid($data['csrf_token']??null)) json_response(['success'=>false,'message'=>'صفحه منقضی شده؛ دوباره بارگذاری کن.'],419);
$action=(string)($data['action']??'');
$userId=(int)current_user()['id'];
$requestId=text_substr(preg_replace('/[^A-Za-z0-9._:-]/','',trim((string)($data['request_id']??'')))?:bin2hex(random_bytes(8)),0,96);
$pdo=db();

/** Lock a bill line with the same table -> session -> order -> item order used by checkout. */
function operator_bill_item_locked(PDO $pdo, int $itemId): array
{
    $probe=$pdo->prepare('SELECT oi.order_id,o.table_id,o.session_id FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.id=? LIMIT 1');
    $probe->execute([$itemId]);
    $identity=$probe->fetch();
    if(!$identity)throw new RuntimeException('ردیف سفارش پیدا نشد.');

    $tableStmt=$pdo->prepare('SELECT id,name FROM cafe_tables WHERE id=? FOR UPDATE');
    $tableStmt->execute([(int)$identity['table_id']]);
    $table=$tableStmt->fetch();
    if(!$table)throw new RuntimeException('میز سفارش پیدا نشد.');

    $session=null;
    $sessionId=(int)($identity['session_id']??0);
    if($sessionId>0){
        $sessionStmt=$pdo->prepare('SELECT id,status FROM table_sessions WHERE id=? FOR UPDATE');
        $sessionStmt->execute([$sessionId]);
        $session=$sessionStmt->fetch()?:null;
    }

    $orderStmt=$pdo->prepare('SELECT id,table_id,session_id,status FROM orders WHERE id=? FOR UPDATE');
    $orderStmt->execute([(int)$identity['order_id']]);
    $order=$orderStmt->fetch();
    if(!$order || (int)$order['table_id']!==(int)$identity['table_id'] || (int)($order['session_id']??0)!==$sessionId){
        throw new RuntimeException('حساب این ردیف هم‌زمان تغییر کرده؛ صفحه را تازه کن.');
    }

    $itemStmt=$pdo->prepare('SELECT * FROM order_items WHERE id=? AND order_id=? FOR UPDATE');
    $itemStmt->execute([$itemId,(int)$order['id']]);
    $item=$itemStmt->fetch();
    if(!$item)throw new RuntimeException('ردیف سفارش پیدا نشد.');

    return $item+[
        'order_id'=>(int)$order['id'],
        'session_id'=>$sessionId?:null,
        'table_id'=>(int)$table['id'],
        'order_status'=>(string)$order['status'],
        'session_status'=>(string)($session['status']??''),
        'table_name'=>(string)$table['name'],
    ];
}

try {
    $pdo->beginTransaction();
    if ($action==='add_item') {
        $sourceItemId=(int)($data['order_item_id']??0);
        $addQuantity=(int)en_digits((string)($data['quantity']??0));
        $expectedPrice=array_key_exists('expected_price',$data)?(int)en_digits((string)$data['expected_price']):-1;
        $rawRequestId=trim((string)($data['request_id']??''));
        if($sourceItemId<1||$addQuantity<1||$addQuantity>99)throw new RuntimeException('تعداد افزوده‌شده معتبر نیست.');
        if($expectedPrice<0)throw new RuntimeException('قیمت فعلی این آیتم مشخص نیست؛ صفحه را تازه کن.');
        if($rawRequestId===''||strlen($rawRequestId)>96)throw new RuntimeException('شناسه امن این عملیات معتبر نیست؛ صفحه را تازه کن.');

        $item=operator_bill_item_locked($pdo,$sourceItemId);
        $clientToken='bill-add-'.substr(hash('sha256',$requestId),0,64);
        $duplicateStmt=$pdo->prepare("SELECT o.id,o.session_id,o.table_id,oi.item_id,oi.quantity,oi.unit_price FROM orders o JOIN order_items oi ON oi.order_id=o.id WHERE o.client_token=? LIMIT 1 FOR UPDATE");
        $duplicateStmt->execute([$clientToken]);
        if($duplicate=$duplicateStmt->fetch()){
            if((int)$duplicate['session_id']!==(int)$item['session_id']||(int)$duplicate['table_id']!==(int)$item['table_id']||(int)$duplicate['item_id']!==(int)$item['item_id']||(int)$duplicate['quantity']!==$addQuantity){
                throw new RuntimeException('شناسه این عملیات قبلاً برای درخواست دیگری استفاده شده است.');
            }
            $pdo->commit();
            $duplicateOrderId=(int)$duplicate['id'];
            inventory_register_after_response_order($duplicateOrderId);
            json_response(['success'=>true,'duplicate'=>true,'message'=>'این سفارش تازه قبلاً ثبت شده است.','order_id'=>$duplicateOrderId,'unit_price'=>(int)$duplicate['unit_price'],'line_total'=>(int)$duplicate['unit_price']*$addQuantity]);
        }

        if(($item['session_status']??'')!=='active'||(string)$item['order_status']!=='accounted')throw new RuntimeException('فقط به حساب فعال می‌توان سفارش تازه افزود.');
        settlement_assert_session_editable_locked($pdo, (int)($item['session_id']??0), 'افزودن سفارش');
        if(accommodation_transfer_blocks_invoice_edit((int)($item['session_id']??0)))throw new RuntimeException('نتیجه انتقال اقامتگاه هنوز نهایی نشده است؛ ابتدا آن را تعیین تکلیف کن.');
        $catalog=order_catalog_items_locked($pdo,[(int)($item['item_id']??0)]);
        $current=$catalog[(int)($item['item_id']??0)]??null;
        if(!order_catalog_item_is_orderable($current))throw new RuntimeException('این آیتم در حال حاضر برای سفارش تازه فعال نیست.');
        if((int)$current['price']!==$expectedPrice)throw new RuntimeException('قیمت این آیتم تغییر کرده است؛ صفحه را تازه کن و دوباره ثبت کن.');

        $unitPrice=(int)$current['price'];$lineTotal=$unitPrice*$addQuantity;
        $publicCode=strtoupper(bin2hex(random_bytes(8)));$business=business_assignment();$businessOrderNumber=order_allocate_business_number($pdo,(string)$business['business_date']);
        $insert=$pdo->prepare("INSERT INTO orders(public_code,client_token,device_token,table_id,session_id,order_source,status,customer_note,total_amount,accepted_at,accepted_by_user_id,created_by_user_id,business_order_number,business_date,business_shift_key,business_shift_label,business_cutoff_snapshot) VALUES(?,?,NULL,?,?, 'staff','accounted',NULL,?,NOW(),?,?,?,?,?,?,?)");
        $insert->execute([$publicCode,$clientToken,(int)$item['table_id'],(int)$item['session_id'],$lineTotal,$userId,$userId,$businessOrderNumber,(string)$business['business_date'],(string)$business['shift_key'],(string)$business['shift_label'],(string)$business['cutoff']]);$orderId=(int)$pdo->lastInsertId();
        $station=normalize_preparation_station((string)($current['preparation_station']??'cold_bar'));
        $fulfillmentMode=normalize_fulfillment_mode((string)($item['fulfillment_mode']??'dine_in'));
        $pdo->prepare('INSERT INTO order_items(order_id,item_id,item_name,sellable_kind_snapshot,unit_price,quantity,ordered_quantity,item_note,fulfillment_mode,preparation_station,line_total) VALUES(?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$orderId,(int)$current['id'],(string)$current['name'],normalize_sellable_kind($current['sellable_kind']??null),$unitPrice,$addQuantity,$addQuantity,$item['item_note']?:null,$fulfillmentMode,$station,$lineTotal]);
        $pdo->prepare("INSERT INTO order_status_history(order_id,from_status,to_status,actor_user_id) VALUES(?,NULL,'accounted',?)")->execute([$orderId,$userId]);
        if(preparation_station_requires_work($station)){
            print_enqueue_prep_order($pdo,$orderId,$userId);
        }
        audit_log_write('order.item_added_again','order',$orderId,['source_order_item_id'=>$sourceItemId,'item_id'=>(int)$current['id'],'quantity'=>$addQuantity,'unit_price'=>$unitPrice,'session_id'=>(int)$item['session_id'],'request_id'=>$requestId],$userId);
        $area=preparation_station_requires_work($station)?preparation_area_for_station($station):null;
        if($area!==null){
            order_side_effect_best_effort_tx($pdo,'push bill add item',static function() use($pdo,$item,$addQuantity,$current,$orderId,$area,$requestId): void {
                push_enqueue_event_tx($pdo,'order',[
                    'title'=>'سفارش افزوده برای '.(string)$item['table_name'],
                    'body'=>fa_digits($addQuantity).' × '.(string)$current['name'].' به‌عنوان سفارش تازه ثبت شد.',
                    'url'=>asset('waiter/index.php').'?order='.$orderId,
                    'tag'=>'order-added-'.$orderId,
                    'order_id'=>$orderId,
                    'areas'=>[$area],
                ],$requestId);
            });
        }
        inventory_enqueue_order_event_tx($pdo,'accounted',$orderId,$userId,[],'inventory:order-accounted:'.$orderId);
        $pdo->commit();
        inventory_register_after_response_order($orderId);
        json_response(['success'=>true,'message'=>$area!==null?'آیتم به‌عنوان سفارش تازه ثبت و برای آماده‌سازی ارسال شد.':'آیتم خدماتی به حساب اضافه شد.','order_id'=>$orderId,'order_number'=>$businessOrderNumber,'unit_price'=>$unitPrice,'line_total'=>$lineTotal]);
    }

    if ($action==='adjust_item') {
        $itemId=(int)($data['order_item_id']??0);
        $newQuantity=(int)en_digits((string)($data['quantity']??-1));
        $reason=text_substr(trim((string)($data['reason']??'')),0,300);
        if ($itemId<1 || $newQuantity<0) throw new RuntimeException('تعداد اصلاح‌شده معتبر نیست.');
        $allowedReasons=['ثبت اشتباه','لغو مهمان','ناموجود','اصلاح سریع','سایر'];
        if ($reason===''||(!in_array($reason,$allowedReasons,true)&&!str_starts_with($reason,'سایر: '))) throw new RuntimeException('یکی از دلیل‌های مشخص اصلاح را انتخاب کن.');
        $item=operator_bill_item_locked($pdo,$itemId);
        if (($item['session_status']??'')!=='active' || (string)$item['order_status']!=='accounted') throw new RuntimeException('فقط ردیف یک حساب فعال و تأییدشده قابل اصلاح است.');
        settlement_assert_session_editable_locked($pdo, (int)($item['session_id']??0), 'کاهش یا حذف قلم');
        if (accommodation_transfer_blocks_invoice_edit((int)($item['session_id']??0))) throw new RuntimeException('فاکتور منتقل‌شده به اقامتگاه قابل ویرایش نیست؛ ابتدا هزینه را برگشت بده و سفارش اصلاحی تازه ثبت کن.');
        $previous=(int)$item['quantity'];
        if ($newQuantity>=$previous) throw new RuntimeException('برای افزایش تعداد از «افزودن سفارش تازه» استفاده کن.');
        if ($newQuantity===$previous) {
            $pdo->rollBack();
            json_response(['success'=>true,'message'=>'تعداد تغییری نکرد.']);
        }

        $removedQuantity=$previous-$newQuantity;
        $requiresPreparation=preparation_station_requires_work((string)($item['preparation_station']??'cold_bar'));
        $preparedRemovedQuantity=0;
        if($requiresPreparation){
            if(array_key_exists('prepared_removed_quantity',$data)){
                $preparedRemovedQuantity=(int)en_digits((string)$data['prepared_removed_quantity']);
            }else{
                throw new RuntimeException('مشخص کن از مقدار حذف‌شده چند عدد آماده شده بود.');
            }
            if($preparedRemovedQuantity<0||$preparedRemovedQuantity>$removedQuantity)throw new RuntimeException('تعداد آماده‌شده با مقدار حذف‌شده هماهنگ نیست.');
        }
        $unpreparedRemovedQuantity=$requiresPreparation?($removedQuantity-$preparedRemovedQuantity):0;
        $preparationTargetQuantity=$previous-$unpreparedRemovedQuantity;

        $lineTotal=(int)$item['unit_price']*$newQuantity;
        $pdo->prepare('UPDATE order_items SET quantity=?,line_total=?,adjustment_reason=?,adjusted_by_user_id=?,adjusted_at=NOW() WHERE id=?')
            ->execute([$newQuantity,$lineTotal,$reason,$userId,$itemId]);
        $pdo->prepare('INSERT INTO order_item_adjustments(order_item_id,order_id,session_id,previous_quantity,new_quantity,reason,prepared_removed_quantity,actor_user_id) VALUES(?,?,?,?,?,?,?,?)')
            ->execute([$itemId,(int)$item['order_id'],$item['session_id']?(int)$item['session_id']:null,$previous,$newQuantity,$reason,$requiresPreparation?$preparedRemovedQuantity:null,$userId]);
        $adjustmentId=(int)$pdo->lastInsertId();
        $area=$requiresPreparation?preparation_area_for_station((string)($item['preparation_station']??'cold_bar')):null;
        $preparationAdjustmentId=null;

        $pdo->prepare('UPDATE orders SET total_amount=(SELECT COALESCE(SUM(line_total),0) FROM order_items WHERE order_id=?),updated_at=NOW() WHERE id=?')
            ->execute([(int)$item['order_id'],(int)$item['order_id']]);

        if($requiresPreparation && $unpreparedRemovedQuantity>0){
            $pdo->prepare("INSERT INTO preparation_adjustments(order_item_adjustment_id,order_id,session_id,table_id,area_key,item_name,previous_quantity,new_quantity,reason,status) VALUES(?,?,?,?,?,?,?,?,?,'pending_delivery')")
                ->execute([$adjustmentId,(int)$item['order_id'],$item['session_id']?(int)$item['session_id']:null,(int)$item['table_id'],$area,(string)$item['item_name'],$previous,$preparationTargetQuantity,$reason]);
            $preparationAdjustmentId=(int)$pdo->lastInsertId();
            $pdo->prepare('DELETE FROM order_preparation_claims WHERE order_id=? AND area_key=?')->execute([(int)$item['order_id'],$area]);
            print_enqueue_prep_adjustment($pdo,$adjustmentId,$item,$previous,$preparationTargetQuantity,$reason,$userId);
            order_side_effect_best_effort_tx($pdo,'push preparation adjustment',static function() use($pdo,$item,$previous,$preparationTargetQuantity,$itemId,$adjustmentId,$area,$requestId): void {
                push_enqueue_event_tx($pdo,'order',[
                    'title'=>'اصلاح سفارش '.(string)$item['table_name'],
                    'body'=>(string)$item['item_name'].': تعداد آماده‌سازی از '.fa_digits($previous).' به '.fa_digits($preparationTargetQuantity).' تغییر کرد.',
                    'url'=>asset('waiter/index.php').'?order='.(int)$item['order_id'],
                    'tag'=>'order-item-adjusted-'.$itemId.'-'.$adjustmentId,
                    'order_id'=>(int)$item['order_id'],
                    'areas'=>[$area],
                ],$requestId);
            });
            inventory_enqueue_order_event_tx($pdo,'quantity_adjusted',(int)$item['order_id'],$userId,[
                'order_item_id'=>$itemId,
                'previous_quantity'=>$previous,
                'new_quantity'=>$newQuantity,
                'restore_quantity'=>$unpreparedRemovedQuantity,
                'adjustment_id'=>$adjustmentId,
            ],'inventory:order-item-adjustment:'.$adjustmentId);
        }
        audit_log_write_strict($pdo, 'order.item_quantity_adjusted','order_item',$itemId,[
            'order_id'=>(int)$item['order_id'],'order_number'=>order_display_number((int)$item['order_id']),'table_name'=>(string)$item['table_name'],'item_name'=>(string)$item['item_name'],
            'previous_quantity'=>$previous,'new_quantity'=>$newQuantity,'removed_quantity'=>$removedQuantity,
            'reason'=>$reason,'requires_preparation'=>$requiresPreparation,'prepared_removed_quantity'=>$requiresPreparation?$preparedRemovedQuantity:null,
            'unprepared_removed_quantity'=>$unpreparedRemovedQuantity,'inventory_restored_quantity'=>$unpreparedRemovedQuantity,
        ],$userId);
        $pdo->commit();
        if($unpreparedRemovedQuantity>0)inventory_register_after_response_order((int)$item['order_id']);
        if(!$requiresPreparation)$message='تعداد آیتم خدماتی در حساب اصلاح شد.';
        elseif($unpreparedRemovedQuantity===0)$message='تعداد فاکتور اصلاح شد؛ همه مقدار حذف‌شده آماده شده بود و موجودی برنگشت.';
        elseif($preparedRemovedQuantity===0)$message='تعداد اصلاح شد؛ اصلاحیه آماده‌سازی ثبت و موجودی مقدار آماده‌نشده برگشت.';
        else $message='تعداد اصلاح شد؛ فقط موجودی '.fa_digits($unpreparedRemovedQuantity).' عدد آماده‌نشده برگشت.';
        json_response([
            'success'=>true,
            'message'=>$message,
            'prepared_removed_quantity'=>$requiresPreparation?$preparedRemovedQuantity:null,
            'unprepared_quantity'=>$unpreparedRemovedQuantity,
            'inventory_restored_quantity'=>$unpreparedRemovedQuantity,
            'preparation_adjustment_id'=>$preparationAdjustmentId,
            'preparation_area'=>$unpreparedRemovedQuantity>0?$area:null,
        ]);
    }

    if ($action==='reprint_prep') {
        $orderId=(int)($data['order_id']??0);
        if($orderId<1)throw new RuntimeException('سفارش معتبر نیست.');
        $stmt=$pdo->prepare("SELECT o.id,o.status,o.session_id,ts.status session_status FROM orders o LEFT JOIN table_sessions ts ON ts.id=o.session_id WHERE o.id=? FOR UPDATE");
        $stmt->execute([$orderId]);
        $order=$stmt->fetch();
        if(!$order)throw new RuntimeException('سفارش پیدا نشد.');
        if(!in_array((string)$order['status'],['accounted','completed'],true))throw new RuntimeException('فقط سفارش تأییدشده قابل چاپ مجدد است.');
        $job=print_enqueue_prep_reprint($pdo,$orderId,$userId,$requestId);
        $pdo->commit();
        json_response(['success'=>true,'persisted'=>true,'request_id'=>$requestId,'message'=>!empty($job['duplicate'])?'این درخواست چاپ قبلاً در صف ثبت شده است.':'نسخه آماده‌سازی با نشان «چاپ مجدد» وارد صف چاپ شد.','print_job'=>$job]);
    }

    if ($action==='discount') {
        $tableId=(int)($data['table_id']??0);
        $type=(string)($data['discount_type']??'none');
        $value=max(0,(int)en_digits((string)($data['discount_value']??0)));
        if ($tableId<1) throw new RuntimeException('میز معتبر نیست.');
        if (!in_array($type,['none','percent','fixed'],true)) throw new RuntimeException('نوع تخفیف معتبر نیست.');
        $sessionStmt=$pdo->prepare("SELECT * FROM table_sessions WHERE table_id=? AND status='active' ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $sessionStmt->execute([$tableId]);
        $session=$sessionStmt->fetch();
        if (!$session) throw new RuntimeException('این میز حساب فعال ندارد.');
        settlement_assert_session_editable_locked($pdo, (int)$session['id'], 'تغییر تخفیف');
        if (accommodation_transfer_blocks_invoice_edit((int)$session['id'])) throw new RuntimeException('تخفیف فاکتور منتقل‌شده به اقامتگاه قابل تغییر نیست؛ ابتدا هزینه را برگشت بده و سفارش اصلاحی تازه ثبت کن.');
        $subtotalStmt=$pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE session_id=? AND status='accounted'");
        $subtotalStmt->execute([(int)$session['id']]);
        $subtotal=(int)$subtotalStmt->fetchColumn();
        if ($subtotal<1) throw new RuntimeException('هنوز مبلغ تأییدشده‌ای برای تخفیف وجود ندارد.');
        if ($type==='none') {
            $value=0;$amount=0;
        } else {
            if ($type==='percent' && $value>100) throw new RuntimeException('درصد تخفیف باید بین صفر تا صد باشد.');
            if ($type==='fixed' && $value>$subtotal) throw new RuntimeException('تخفیف ثابت نمی‌تواند بیشتر از جمع فاکتور باشد.');
            $amount=invoice_discount_amount($subtotal,$type,$value);
        }
        $newType=$type==='none'?null:$type;
        $pdo->prepare('INSERT INTO invoice_discount_audit(session_id,previous_type,previous_value,previous_amount,new_type,new_value,new_amount,subtotal,actor_user_id) VALUES(?,?,?,?,?,?,?,?,?)')
            ->execute([(int)$session['id'],$session['discount_type']?:null,(int)($session['discount_value']??0),(int)($session['discount_amount']??0),$newType,$value,$amount,$subtotal,$userId]);
        $pdo->prepare('UPDATE table_sessions SET discount_type=?,discount_value=?,discount_amount=?,discount_by_user_id=?,discount_updated_at=NOW() WHERE id=?')
            ->execute([$newType,$value,$amount,$type==='none'?null:$userId,(int)$session['id']]);
        audit_log_write_strict($pdo, 'invoice.discount_changed','table_session',(int)$session['id'],[
            'previous_type'=>$session['discount_type']?:null,'previous_value'=>(int)($session['discount_value']??0),'previous_amount'=>(int)($session['discount_amount']??0),
            'new_type'=>$newType,'new_value'=>$value,'new_amount'=>$amount,'subtotal'=>$subtotal,
        ],$userId);
        $pdo->commit();
        json_response(['success'=>true,'message'=>$type==='none'?'تخفیف حذف شد.':'تخفیف فاکتور ثبت شد.','subtotal'=>$subtotal,'discount_amount'=>$amount,'final_total'=>max(0,$subtotal-$amount)]);
    }

    throw new RuntimeException('عملیات فاکتور شناخته نشد.');
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_response(['success'=>false,'message'=>$e->getMessage()],$e->getCode()===409?409:422);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('operator bill action: '.$e->getMessage());
    json_response(['success'=>false,'message'=>'اصلاح فاکتور انجام نشد؛ دوباره امتحان کن.'],500);
}
