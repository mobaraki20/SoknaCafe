<?php
declare(strict_types=1);

require_once __DIR__ . '/staff_order_service.php';

final class TableDraftException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus = 409,
        public readonly array $details = []
    ) { parent::__construct($message, $httpStatus); }
}

function table_draft_assert_permission(array $user): void
{
    try {
        staff_order_assert_permission($user, 'normal');
    } catch (StaffQuickOrderException $e) {
        throw new TableDraftException('forbidden', $e->getMessage(), 403);
    }
}

function table_draft_normalize_rows(mixed $items): array
{
    if ($items === null) return [];
    if (!is_array($items) || count($items) > 30) {
        throw new TableDraftException('invalid_items','حداکثر ۳۰ ردیف در پیش‌نویس مجاز است.',422);
    }
    if ($items === []) return [];
    try {
        return normalize_staff_quick_order_rows($items);
    } catch (InvalidArgumentException $e) {
        throw new TableDraftException('invalid_items',$e->getMessage(),422);
    }
}

function table_draft_lock_table(PDO $pdo,int $tableId): array
{
    if($tableId<1)throw new TableDraftException('invalid_table','میز فعال پیدا نشد.',404);
    $stmt=$pdo->prepare('SELECT id,name,table_number,code FROM cafe_tables WHERE id=? AND active=1 FOR UPDATE');
    $stmt->execute([$tableId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new TableDraftException('invalid_table','میز فعال پیدا نشد.',404);
    return $row;
}

function table_draft_current_session_id_locked(PDO $pdo,int $tableId): int
{
    $stmt=$pdo->prepare("SELECT id FROM table_sessions WHERE table_id=? AND status IN('active','pending') ORDER BY FIELD(status,'active','pending'),id DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([$tableId]);
    return (int)($stmt->fetchColumn()?:0);
}

function table_draft_active_locked(PDO $pdo,int $tableId): ?array
{
    $stmt=$pdo->prepare("SELECT * FROM table_drafts WHERE table_id=? AND state='active' ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([$tableId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row)?$row:null;
}

function table_draft_by_id_locked(PDO $pdo,int $draftId): ?array
{
    $stmt=$pdo->prepare('SELECT * FROM table_drafts WHERE id=? LIMIT 1 FOR UPDATE');
    $stmt->execute([$draftId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row)?$row:null;
}

function table_draft_items(PDO $pdo,int $draftId): array
{
    $stmt=$pdo->prepare('SELECT item_id,item_name_snapshot,unit_price_snapshot,sellable_kind_snapshot,quantity,item_note,fulfillment_mode,sort_order FROM table_draft_items WHERE draft_id=? ORDER BY sort_order,id');
    $stmt->execute([$draftId]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    return array_map(static fn(array $row):array=>[
        'id'=>(int)$row['item_id'],
        'name'=>(string)$row['item_name_snapshot'],
        'unit_price'=>(int)$row['unit_price_snapshot'],
        'expected_price'=>(int)$row['unit_price_snapshot'],
        'sellable_kind'=>normalize_sellable_kind($row['sellable_kind_snapshot']??null),
        'quantity'=>(int)$row['quantity'],
        'note'=>(string)($row['item_note']??''),
        'fulfillment_mode'=>normalize_fulfillment_mode((string)$row['fulfillment_mode']),
        'sort_order'=>(int)$row['sort_order'],
    ],$rows);
}

function table_draft_result(PDO $pdo,array $draft): array
{
    return [
        'id'=>(int)$draft['id'],
        'table_id'=>(int)$draft['table_id'],
        'state'=>(string)$draft['state'],
        'version'=>(int)$draft['version'],
        'expected_session_id'=>(int)($draft['expected_session_id']??0),
        'note'=>(string)($draft['note']??''),
        'created_by_user_id'=>$draft['created_by_user_id']!==null?(int)$draft['created_by_user_id']:null,
        'updated_by_user_id'=>$draft['updated_by_user_id']!==null?(int)$draft['updated_by_user_id']:null,
        'final_order_id'=>$draft['final_order_id']!==null?(int)$draft['final_order_id']:null,
        'created_at'=>(string)$draft['created_at'],
        'updated_at'=>(string)$draft['updated_at'],
        'items'=>table_draft_items($pdo,(int)$draft['id']),
    ];
}

function table_draft_get(PDO $pdo,int $tableId,array $user): array
{
    table_draft_assert_permission($user);
    if($tableId<1)throw new TableDraftException('invalid_table','میز فعال پیدا نشد.',404);
    $table=$pdo->prepare('SELECT id,name,table_number,code,active FROM cafe_tables WHERE id=? LIMIT 1');
    $table->execute([$tableId]);$tableRow=$table->fetch(PDO::FETCH_ASSOC);
    if(!$tableRow||(int)$tableRow['active']!==1)throw new TableDraftException('invalid_table','میز فعال پیدا نشد.',404);
    $stmt=$pdo->prepare("SELECT * FROM table_drafts WHERE table_id=? AND state='active' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$tableId]);$draft=$stmt->fetch(PDO::FETCH_ASSOC);
    return ['success'=>true,'draft'=>$draft?table_draft_result($pdo,$draft):null];
}

function table_draft_validate_session_locked(PDO $pdo,int $tableId,int $expectedSessionId): int
{
    $current=table_draft_current_session_id_locked($pdo,$tableId);
    if($current!==max(0,$expectedSessionId)){
        throw new TableDraftException('session_changed','حساب این میز تغییر کرده است؛ پیش‌نویس را تازه کنید.',409,['current_session_id'=>$current]);
    }
    return $current;
}

function table_draft_snapshot_rows_locked(PDO $pdo,array $rows): array
{
    if(!$rows)return [];
    $ids=array_values(array_unique(array_map('intval',array_column($rows,'id'))));
    $items=order_catalog_items_locked($pdo,$ids);
    $out=[];$sort=0;
    foreach($rows as $row){
        $id=(int)$row['id'];$item=$items[$id]??null;
        if(!order_catalog_item_is_orderable($item)){
            throw new TableDraftException('items_unavailable','یکی از آیتم‌های انتخاب‌شده دیگر قابل سفارش نیست؛ فهرست را تازه کنید.',409);
        }
        if(!order_catalog_item_allows_fulfillment($item,(string)$row['fulfillment_mode'])){
            throw new TableDraftException('fulfillment_unavailable','«'.(string)$item['name'].'» فقط داخل کافه قابل سرو است.',409);
        }
        if($row['expected_price']!==null&&(int)$row['expected_price']!==(int)$item['price']){
            throw new TableDraftException('prices_changed','قیمت یکی از آیتم‌ها تغییر کرده است؛ فهرست را تازه کنید.',409);
        }
        $out[]=[
            'item_id'=>$id,'name'=>(string)$item['name'],'price'=>(int)$item['price'],
            'sellable_kind'=>normalize_sellable_kind($item['sellable_kind']??null),
            'quantity'=>(int)$row['quantity'],'note'=>(string)$row['note'],
            'fulfillment_mode'=>normalize_fulfillment_mode((string)$row['fulfillment_mode']),
            'sort_order'=>$sort++,
        ];
    }
    return $out;
}

function table_draft_replace_items_locked(PDO $pdo,int $draftId,array $rows): void
{
    $pdo->prepare('DELETE FROM table_draft_items WHERE draft_id=?')->execute([$draftId]);
    if(!$rows)return;
    $insert=$pdo->prepare('INSERT INTO table_draft_items(draft_id,item_id,item_name_snapshot,unit_price_snapshot,sellable_kind_snapshot,quantity,item_note,fulfillment_mode,sort_order) VALUES(?,?,?,?,?,?,?,?,?)');
    foreach($rows as $row){
        $insert->execute([
            $draftId,$row['item_id'],$row['name'],$row['price'],$row['sellable_kind'],$row['quantity'],
            $row['note']!==''?$row['note']:null,$row['fulfillment_mode'],$row['sort_order'],
        ]);
    }
}

function table_draft_save_tx(PDO $pdo,array $data,array $user): array
{
    if(!$pdo->inTransaction())throw new LogicException('table_draft_save_tx requires an open transaction.');
    table_draft_assert_permission($user);
    $userId=(int)($user['id']??0);
    $tableId=(int)($data['table_id']??0);
    $expectedVersion=max(0,(int)($data['expected_version']??0));
    $expectedSessionId=max(0,(int)($data['expected_session_id']??0));
    $note=text_substr(trim((string)($data['note']??'')),0,500);
    $rows=table_draft_normalize_rows($data['items']??[]);

    table_draft_lock_table($pdo,$tableId);
    table_draft_validate_session_locked($pdo,$tableId,$expectedSessionId);
    $draft=table_draft_active_locked($pdo,$tableId);

    if(!$draft){
        if($expectedVersion!==0)throw new TableDraftException('version_conflict','پیش‌نویس این میز تغییر کرده است؛ دوباره بارگذاری کنید.',409,['current_version'=>0]);
        try{
            $stmt=$pdo->prepare("INSERT INTO table_drafts(table_id,state,active_table_guard,version,expected_session_id,note,created_by_user_id,updated_by_user_id) VALUES(?,'active',?,1,?,?,?,?)");
            $stmt->execute([$tableId,$tableId,$expectedSessionId>0?$expectedSessionId:null,$note!==''?$note:null,$userId,$userId]);
        }catch(PDOException $e){
            if((string)$e->getCode()==='23000')throw new TableDraftException('version_conflict','همکار دیگری هم‌زمان برای این میز پیش‌نویس ساخته است؛ دوباره بارگذاری کنید.',409);
            throw $e;
        }
        $draftId=(int)$pdo->lastInsertId();
        $snapshot=table_draft_snapshot_rows_locked($pdo,$rows);
        table_draft_replace_items_locked($pdo,$draftId,$snapshot);
        $fetch=table_draft_by_id_locked($pdo,$draftId);
        audit_log_write_strict($pdo,'table_draft.created','table_draft',$draftId,['table_id'=>$tableId,'version'=>1,'item_count'=>count($snapshot)],$userId);
        return ['success'=>true,'created'=>true,'draft'=>table_draft_result($pdo,$fetch)];
    }

    $currentVersion=(int)$draft['version'];
    if($expectedVersion!==$currentVersion){
        throw new TableDraftException('version_conflict','پیش‌نویس این میز توسط همکار دیگری تغییر کرده است؛ دوباره بارگذاری کنید.',409,['current_version'=>$currentVersion]);
    }
    if((int)($draft['expected_session_id']??0)!==$expectedSessionId){
        throw new TableDraftException('session_changed','حساب میز با پیش‌نویس ذخیره‌شده هم‌خوان نیست؛ پیش‌نویس را بررسی کنید.',409,['draft_session_id'=>(int)($draft['expected_session_id']??0)]);
    }

    $snapshot=table_draft_snapshot_rows_locked($pdo,$rows);
    $next=$currentVersion+1;
    $stmt=$pdo->prepare('UPDATE table_drafts SET version=?,note=?,updated_by_user_id=? WHERE id=? AND state='active' AND version=?');
    $stmt->execute([$next,$note!==''?$note:null,$userId,(int)$draft['id'],$currentVersion]);
    if($stmt->rowCount()!==1)throw new TableDraftException('version_conflict','پیش‌نویس هم‌زمان تغییر کرده است؛ دوباره بارگذاری کنید.',409);
    table_draft_replace_items_locked($pdo,(int)$draft['id'],$snapshot);
    $fetch=table_draft_by_id_locked($pdo,(int)$draft['id']);
    audit_log_write_strict($pdo,'table_draft.updated','table_draft',(int)$draft['id'],['table_id'=>$tableId,'before_version'=>$currentVersion,'version'=>$next,'item_count'=>count($snapshot)],$userId);
    return ['success'=>true,'created'=>false,'draft'=>table_draft_result($pdo,$fetch)];
}

function table_draft_cancel_tx(PDO $pdo,array $data,array $user): array
{
    if(!$pdo->inTransaction())throw new LogicException('table_draft_cancel_tx requires an open transaction.');
    table_draft_assert_permission($user);
    $userId=(int)($user['id']??0);$tableId=(int)($data['table_id']??0);$expectedVersion=max(0,(int)($data['expected_version']??0));
    table_draft_lock_table($pdo,$tableId);
    $draft=table_draft_active_locked($pdo,$tableId);
    if(!$draft)return ['success'=>true,'cancelled'=>false,'draft'=>null];
    if($expectedVersion>0&&(int)$draft['version']!==$expectedVersion){
        throw new TableDraftException('version_conflict','پیش‌نویس این میز توسط همکار دیگری تغییر کرده است؛ دوباره بارگذاری کنید.',409,['current_version'=>(int)$draft['version']]);
    }
    $next=(int)$draft['version']+1;
    $stmt=$pdo->prepare("UPDATE table_drafts SET state='cancelled',active_table_guard=NULL,version=?,cancelled_by_user_id=?,cancelled_at=NOW(),updated_by_user_id=? WHERE id=? AND state='active'");
    $stmt->execute([$next,$userId,$userId,(int)$draft['id']]);
    audit_log_write_strict($pdo,'table_draft.cancelled','table_draft',(int)$draft['id'],['table_id'=>$tableId,'version'=>$next],$userId);
    return ['success'=>true,'cancelled'=>true,'draft_id'=>(int)$draft['id'],'version'=>$next];
}

function table_draft_order_token(int $draftId): string
{
    return 'draft-'.$draftId.'-'.substr(hash('sha256','sokna-table-draft:'.$draftId),0,40);
}

function table_draft_finalize_tx(PDO $pdo,array $data,array $user): array
{
    if(!$pdo->inTransaction())throw new LogicException('table_draft_finalize_tx requires an open transaction.');
    table_draft_assert_permission($user);
    $userId=(int)($user['id']??0);$draftId=(int)($data['draft_id']??0);$expectedVersion=max(0,(int)($data['expected_version']??0));
    if($draftId<1)throw new TableDraftException('invalid_draft','پیش‌نویس معتبر نیست.',422);

    $probe=$pdo->prepare('SELECT table_id FROM table_drafts WHERE id=? LIMIT 1');
    $probe->execute([$draftId]);$tableId=(int)($probe->fetchColumn()?:0);
    if($tableId<1)throw new TableDraftException('invalid_draft','پیش‌نویس پیدا نشد.',404);
    table_draft_lock_table($pdo,$tableId);
    $draft=table_draft_by_id_locked($pdo,$draftId);
    if(!$draft)throw new TableDraftException('invalid_draft','پیش‌نویس پیدا نشد.',404);

    if((string)$draft['state']==='finalized'&&(int)($draft['final_order_id']??0)>0){
        $orderId=(int)$draft['final_order_id'];
        return ['success'=>true,'duplicate'=>true,'draft_id'=>$draftId,'order_id'=>$orderId,'order_number'=>order_display_number($orderId),'draft_version'=>(int)$draft['version'],'_after_commit_order_id'=>0];
    }
    if((string)$draft['state']!=='active')throw new TableDraftException('draft_closed','این پیش‌نویس دیگر فعال نیست.',409);
    if($expectedVersion>0&&(int)$draft['version']!==$expectedVersion){
        throw new TableDraftException('version_conflict','پیش‌نویس این میز توسط همکار دیگری تغییر کرده است؛ دوباره بارگذاری کنید.',409,['current_version'=>(int)$draft['version']]);
    }

    $expectedSessionId=(int)($draft['expected_session_id']??0);
    table_draft_validate_session_locked($pdo,$tableId,$expectedSessionId);
    $items=table_draft_items($pdo,$draftId);
    if(!$items)throw new TableDraftException('empty_draft','پیش‌نویس خالی قابل ثبت نیست.',422);

    $orderData=[
        'table_id'=>$tableId,
        'expected_session_id'=>$expectedSessionId,
        'request_token'=>table_draft_order_token($draftId),
        'note'=>(string)($draft['note']??''),
        'items'=>array_map(static fn(array $row):array=>[
            'id'=>$row['id'],'quantity'=>$row['quantity'],'note'=>$row['note'],
            'expected_price'=>$row['unit_price'],'fulfillment_mode'=>$row['fulfillment_mode'],
        ],$items),
    ];
    try{
        $order=staff_order_commit_tx($pdo,$orderData,$user,'normal');
    }catch(StaffQuickOrderException $e){
        $code=(int)$e->getCode();
        throw new TableDraftException('finalize_rejected',$e->getMessage(),in_array($code,[403,404,409,422],true)?$code:409);
    }

    $orderId=(int)($order['order_id']??0);
    if($orderId<1)throw new RuntimeException('ثبت سفارش پیش‌نویس نتیجه معتبر برنگرداند.');
    $next=(int)$draft['version']+1;
    $update=$pdo->prepare("UPDATE table_drafts SET state='finalized',active_table_guard=NULL,version=?,finalized_by_user_id=?,finalized_at=NOW(),updated_by_user_id=?,final_order_id=? WHERE id=? AND state='active'");
    $update->execute([$next,$userId,$userId,$orderId,$draftId]);
    if($update->rowCount()!==1)throw new TableDraftException('version_conflict','پیش‌نویس هم‌زمان تغییر کرده است؛ دوباره وضعیت میز را بررسی کنید.',409);
    audit_log_write_strict($pdo,'table_draft.finalized','table_draft',$draftId,['table_id'=>$tableId,'order_id'=>$orderId,'version'=>$next],$userId);

    return [
        'success'=>true,'duplicate'=>(bool)($order['duplicate']??false),'message'=>(string)($order['message']??'سفارش ثبت شد.'),
        'draft_id'=>$draftId,'draft_version'=>$next,'order_id'=>$orderId,'order_number'=>(int)($order['order_number']??order_display_number($orderId)),
        '_after_commit_order_id'=>(int)($order['_after_commit_order_id']??0),
    ];
}
