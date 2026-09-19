<?php
declare(strict_types=1);

require dirname(__DIR__).'/bootstrap.php';
require_once dirname(__DIR__).'/includes/table_draft.php';

function p6cl(bool $ok,string $message,mixed $context=null):void{
    if(!$ok){
        fwrite(STDERR,"FAIL: $message\n");
        if($context!==null)fwrite(STDERR,json_encode($context,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n");
        exit(1);
    }
    echo "PASS: $message\n";
}

$pdo=db();
$suffix=substr(bin2hex(random_bytes(6)),0,10);
$pdo->beginTransaction();
try{
    $userStmt=$pdo->prepare("INSERT INTO users(username,password_hash,display_name,role,active) VALUES(?,?,?,?,1)");
    $userStmt->execute(['p6c-owner-'.$suffix,password_hash('p6c-pass',PASSWORD_DEFAULT),'P6C Owner','admin']);
    $ownerId=(int)$pdo->lastInsertId();
    $owner=['id'=>$ownerId,'username'=>'p6c-owner-'.$suffix,'display_name'=>'P6C Owner','role'=>'admin','active'=>1];

    $userStmt->execute(['p6c-staff-'.$suffix,password_hash('p6c-pass',PASSWORD_DEFAULT),'P6C Staff','operator']);
    $staffId=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO user_capabilities(user_id,capability,enabled) VALUES(?,'orders_floor',1)")->execute([$staffId]);
    $staff=['id'=>$staffId,'username'=>'p6c-staff-'.$suffix,'display_name'=>'P6C Staff','role'=>'operator','active'=>1];

    $menuKey='p6c-'.$suffix;$categoryKey='p6c-cat-'.$suffix;
    $pdo->prepare("INSERT INTO menus(menu_key,name,status,sort_order) VALUES(?,?,'active',950)")->execute([$menuKey,'P6C Menu']);
    $menuId=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO categories(category_key,name,audience,sort_order,active) VALUES(?,?,'guest_staff',950,1)")->execute([$categoryKey,'P6C Category']);
    $categoryId=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO menu_categories(menu_id,category_id,sort_order) VALUES(?,?,1)')->execute([$menuId,$categoryId]);
    $pdo->prepare("INSERT INTO items(item_code,category_id,name,price,available,active,staff_only,sellable_kind,takeaway_allowed,preparation_station,sort_order) VALUES(?,?,?,?,1,1,0,'menu_item',1,'none',1)")
        ->execute(['P6C-ITEM-'.$suffix,$categoryId,'P6C No Prep Item',33000]);
    $itemId=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO menu_items(menu_id,item_id) VALUES(?,?)')->execute([$menuId,$itemId]);

    $tableNumber=32000+random_int(1,500);
    $pdo->prepare("INSERT INTO cafe_tables(name,table_number,code,access_token,active,sort_order) VALUES(?,?,?,?,1,950)")
        ->execute(['P6C Table',$tableNumber,'P6C-'.$suffix,'p6c-table-'.$suffix.'-token']);
    $tableId=(int)$pdo->lastInsertId();

    $business=business_assignment();
    $seqStmt=$pdo->prepare('SELECT last_number FROM order_business_sequences WHERE business_date=?');
    $seqStmt->execute([(string)$business['business_date']]);$sequenceBefore=(int)($seqStmt->fetchColumn()?:0);
    $countsBefore=[
        'orders'=>(int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
        'sessions'=>(int)$pdo->query('SELECT COUNT(*) FROM table_sessions')->fetchColumn(),
        'prints'=>(int)$pdo->query('SELECT COUNT(*) FROM print_jobs')->fetchColumn(),
        'inventory_events'=>(int)$pdo->query('SELECT COUNT(*) FROM inventory_order_events')->fetchColumn(),
        'settlements'=>(int)$pdo->query('SELECT COUNT(*) FROM settlement_records')->fetchColumn(),
    ];

    $first=table_draft_save_tx($pdo,[
        'table_id'=>$tableId,'expected_version'=>0,'expected_session_id'=>0,'note'=>'پیش‌نویس مشترک',
        'items'=>[['id'=>$itemId,'quantity'=>1,'expected_price'=>33000,'note'=>'بدون تغییر','fulfillment_mode'=>'dine_in']],
    ],$owner);
    p6cl(!empty($first['success'])&&!empty($first['created'])&&($first['draft']['version']??0)===1,'first save creates version 1 active server draft',$first);
    $draftId=(int)$first['draft']['id'];

    $seqStmt->execute([(string)$business['business_date']]);$sequenceAfterSave=(int)($seqStmt->fetchColumn()?:0);
    $countsAfterSave=[
        'orders'=>(int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
        'sessions'=>(int)$pdo->query('SELECT COUNT(*) FROM table_sessions')->fetchColumn(),
        'prints'=>(int)$pdo->query('SELECT COUNT(*) FROM print_jobs')->fetchColumn(),
        'inventory_events'=>(int)$pdo->query('SELECT COUNT(*) FROM inventory_order_events')->fetchColumn(),
        'settlements'=>(int)$pdo->query('SELECT COUNT(*) FROM settlement_records')->fetchColumn(),
    ];
    p6cl($countsAfterSave===$countsBefore,'draft save creates no order/session/print/inventory/settlement side effect',[$countsBefore,$countsAfterSave]);
    p6cl($sequenceAfterSave===$sequenceBefore,'draft save does not allocate a business order number',[$sequenceBefore,$sequenceAfterSave]);

    $shared=table_draft_get($pdo,$tableId,$staff);
    p6cl(($shared['draft']['id']??0)===$draftId&&($shared['draft']['items'][0]['quantity']??0)===1,'second authorized staff account sees the same server draft',$shared);

    $second=table_draft_save_tx($pdo,[
        'table_id'=>$tableId,'expected_version'=>1,'expected_session_id'=>0,'note'=>'ویرایش همکار',
        'items'=>[['id'=>$itemId,'quantity'=>2,'expected_price'=>33000,'note'=>'ویرایش شده','fulfillment_mode'=>'dine_in']],
    ],$staff);
    p6cl(($second['draft']['version']??0)===2&&($second['draft']['items'][0]['quantity']??0)===2,'authorized collaborator updates shared draft with optimistic version',$second);

    $conflict=false;
    try{
        table_draft_save_tx($pdo,[
            'table_id'=>$tableId,'expected_version'=>1,'expected_session_id'=>0,'note'=>'stale',
            'items'=>[['id'=>$itemId,'quantity'=>3,'expected_price'=>33000,'note'=>'','fulfillment_mode'=>'dine_in']],
        ],$owner);
    }catch(TableDraftException $e){$conflict=$e->errorCode==='version_conflict';}
    p6cl($conflict,'stale version cannot overwrite a newer shared draft');

    $cancel=table_draft_cancel_tx($pdo,['table_id'=>$tableId,'expected_version'=>2],$owner);
    p6cl(!empty($cancel['cancelled']),'draft cancel is explicit');
    p6cl((int)$pdo->query("SELECT COUNT(*) FROM table_drafts WHERE table_id=$tableId AND state='active'")->fetchColumn()===0,'cancel clears the one-active guard');

    $fresh=table_draft_save_tx($pdo,[
        'table_id'=>$tableId,'expected_version'=>0,'expected_session_id'=>0,'note'=>'برای ثبت نهایی',
        'items'=>[['id'=>$itemId,'quantity'=>1,'expected_price'=>33000,'note'=>'','fulfillment_mode'=>'dine_in']],
    ],$staff);
    $finalDraftId=(int)$fresh['draft']['id'];$finalVersion=(int)$fresh['draft']['version'];

    $beforeFinalizeOrders=(int)$pdo->query("SELECT COUNT(*) FROM orders WHERE table_id=$tableId")->fetchColumn();
    p6cl($beforeFinalizeOrders===0,'no canonical order exists before finalize');

    $final=table_draft_finalize_tx($pdo,['draft_id'=>$finalDraftId,'expected_version'=>$finalVersion],$staff);
    p6cl(!empty($final['success'])&&(int)($final['order_id']??0)>0,'finalize delegates to canonical staff order transaction',$final);
    $orderId=(int)$final['order_id'];

    $orderStmt=$pdo->prepare('SELECT business_order_number,status,order_source,created_by_user_id,session_id FROM orders WHERE id=?');
    $orderStmt->execute([$orderId]);$order=$orderStmt->fetch(PDO::FETCH_ASSOC)?:[];
    p6cl((int)($order['business_order_number']??0)>0&&($order['status']??'')==='accounted'&&($order['order_source']??'')==='staff','finalize creates normal canonical staff order',$order);
    p6cl((int)($order['created_by_user_id']??0)===$staffId,'finalize attributes canonical order to current actor',$order);

    $draftStmt=$pdo->prepare('SELECT state,active_table_guard,final_order_id,version FROM table_drafts WHERE id=?');
    $draftStmt->execute([$finalDraftId]);$closed=$draftStmt->fetch(PDO::FETCH_ASSOC)?:[];
    p6cl(($closed['state']??'')==='finalized'&&$closed['active_table_guard']===null&&(int)($closed['final_order_id']??0)===$orderId,'finalized draft is retained for audit but releases active-table guard',$closed);

    $retry=table_draft_finalize_tx($pdo,['draft_id'=>$finalDraftId,'expected_version'=>$finalVersion],$staff);
    p6cl(!empty($retry['duplicate'])&&(int)($retry['order_id']??0)===$orderId,'retry after finalization returns the same canonical order',$retry);
    p6cl((int)$pdo->query("SELECT COUNT(*) FROM orders WHERE table_id=$tableId")->fetchColumn()===1,'retry cannot create a second order');

    $seqStmt->execute([(string)$business['business_date']]);$sequenceAfterFinalize=(int)($seqStmt->fetchColumn()?:0);
    p6cl($sequenceAfterFinalize===$sequenceBefore+1,'business number is allocated exactly once at finalize',[$sequenceBefore,$sequenceAfterFinalize]);

    $settle=$pdo->prepare('SELECT COUNT(*) FROM settlement_records WHERE session_id=?');$settle->execute([(int)$order['session_id']]);
    p6cl((int)$settle->fetchColumn()===0,'finalize does not settle or post finance automatically');

    $pdo->rollBack();
}catch(Throwable $e){
    if($pdo->inTransaction())$pdo->rollBack();
    throw $e;
}

echo "Phase 6C Table Draft Local/MariaDB integration PASS\n";
