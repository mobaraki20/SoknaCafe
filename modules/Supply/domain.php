<?php
declare(strict_types=1);

/**
 * Supply domain owner.
 * Operational need -> buyer commitment (preparing) -> physical receipt -> Inventory ledger.
 */


/** Do not hide an in-flight buyer commitment. Open/uncommitted needs may stay dormant safely,
 * but a quantity already marked "preparing" must first be received or returned to the open list. */
function supply_module_runtime_ready_locked(PDO $pdo): bool
{
    if (!$pdo->inTransaction()) throw new RuntimeException('بررسی وضعیت خرید باید داخل تراکنش انجام شود.');
    if (!sokna_module_setting_state_locked($pdo, 'supply')) return false;
    return inventory_module_runtime_ready_locked($pdo);
}

function supply_module_require_runtime_ready_locked(PDO $pdo): void
{
    if (!supply_module_runtime_ready_locked($pdo)) {
        throw new RuntimeException('خرید و تأمین در حال حاضر برای عملیات آماده نیست.');
    }
}


/** Manager-side preflight only. Backend lifecycle checks remain authoritative. */
function supply_module_disable_preflight(): array
{
    try {
        $exists = (int)db()->query("SELECT EXISTS(SELECT 1 FROM inventory_supply_needs WHERE status='open' AND preparing_quantity_base>0 LIMIT 1)")->fetchColumn();
    } catch (Throwable) {
        return [];
    }
    if ($exists !== 1) return [];
    return [[
        'message' => 'برای خاموش‌کردن، ابتدا اقلام «در حال خرید» را تحویل دهید یا به فهرست خرید برگردانید.',
        'href' => 'purchases.php#preparingPurchases',
        'label' => 'مشاهده اقلام در حال خرید',
    ]];
}

function supply_module_before_disable_locked(PDO $pdo, int $actorUserId): void
{
    $stmt = $pdo->query("SELECT COUNT(*) FROM inventory_supply_needs WHERE status='open' AND preparing_quantity_base>0");
    if ((int)$stmt->fetchColumn() > 0) {
        throw new RuntimeException('پیش از غیرفعال‌کردن خرید، اقلام «در حال خرید» را تحویل دهید یا به فهرست خرید برگردانید.');
    }
}

function supply_need_status_labels(): array
{
    return ['open'=>'باز','closed'=>'تأمین‌شده','cancelled'=>'لغوشده'];
}

function supply_need_guard(string $department, int $itemId, string $name, string $baseUnit): string
{
    $department=inventory_normalize_department($department)??'shared';
    if($itemId>0) return $department.':i:'.$itemId;
    $name=text_substr(trim($name),0,160);
    return $department.':n:'.$name.':'.inventory_normalize_base_unit($baseUnit);
}

function supply_need_open_for_item(PDO $pdo, int $itemId, string $department, bool $forUpdate = false): ?array
{
    $department = inventory_normalize_department($department) ?? 'shared';
    $sql = "SELECT n.*,i.name inventory_item_name FROM inventory_supply_needs n LEFT JOIN inventory_items i ON i.id=n.inventory_item_id WHERE n.inventory_item_id=? AND n.department=? AND n.status='open' ORDER BY n.id DESC LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$itemId,$department]);
    return $stmt->fetch() ?: null;
}

/** Total unmet quantity, including anything already committed to the buyer. */
function supply_need_remaining(array $need): int
{
    return max(0, (int)($need['requested_quantity_base'] ?? 0) - (int)($need['fulfilled_quantity_base'] ?? 0));
}

/** Quantity still waiting for the buyer to acknowledge. */
function supply_need_uncommitted(array $need): int
{
    return max(0, supply_need_remaining($need) - (int)($need['preparing_quantity_base'] ?? 0));
}

function supply_need_display_quantity(array $need): string
{
    return inventory_format_quantity(supply_need_remaining($need), (string)($need['base_unit'] ?? 'count'));
}

function supply_free_group_name(string $name): string
{
    return text_lower(trim($name));
}

function supply_free_group_encode(string $name): string
{
    return rtrim(strtr(base64_encode(supply_free_group_name($name)),'+/','-_'),'=');
}

function supply_free_group_decode(string $encoded): string
{
    if($encoded==='' || !preg_match('/^[A-Za-z0-9_-]+$/',$encoded)) throw new RuntimeException('قلم خرید معتبر نیست؛ صفحه را تازه کن.');
    $padding=str_repeat('=',(4-strlen($encoded)%4)%4);
    $decoded=base64_decode(strtr($encoded,'-_','+/').$padding,true);
    if($decoded===false || trim($decoded)==='') throw new RuntimeException('قلم خرید معتبر نیست؛ صفحه را تازه کن.');
    return trim($decoded);
}

function supply_group_key_for_need(array $need): string
{
    $itemId=(int)($need['inventory_item_id']??0);
    if($itemId>0) return 'item:'.$itemId;
    $unit=inventory_normalize_base_unit((string)($need['base_unit']??'count'));
    return 'free:'.$unit.':'.supply_free_group_encode((string)($need['item_name_snapshot']??''));
}

function supply_parse_group_key(string $groupKey): array
{
    $groupKey=trim($groupKey);
    if(preg_match('/^item:(\d+)$/',$groupKey,$m) && (int)$m[1]>0) return ['type'=>'item','id'=>(int)$m[1]];
    if(preg_match('/^free:(g|ml|count):([A-Za-z0-9_-]+)$/',$groupKey,$m)) return ['type'=>'free','base_unit'=>$m[1],'name'=>supply_free_group_decode($m[2])];
    throw new RuntimeException('قلم خرید معتبر نیست؛ صفحه را تازه کن.');
}

function supply_group_rows_locked(PDO $pdo, string $groupKey, bool $preparingOnly = false): array
{
    $group=supply_parse_group_key($groupKey);
    if($group['type']==='item'){
        $sql="SELECT * FROM inventory_supply_needs WHERE inventory_item_id=? AND status='open'".($preparingOnly?' AND preparing_quantity_base>0':'')." ORDER BY COALESCE(preparing_at,updated_at),id FOR UPDATE";
        $st=$pdo->prepare($sql);$st->execute([$group['id']]);
    }elseif($group['type']==='free'){
        $sql="SELECT * FROM inventory_supply_needs WHERE inventory_item_id IS NULL AND status='open' AND base_unit=? AND LOWER(TRIM(item_name_snapshot))=?".($preparingOnly?' AND preparing_quantity_base>0':'')." ORDER BY COALESCE(preparing_at,updated_at),id FOR UPDATE";
        $st=$pdo->prepare($sql);$st->execute([$group['base_unit'],$group['name']]);
    }
    return $st->fetchAll();
}

function supply_request_upsert_locked(PDO $pdo, array $data, int $actorUserId): int
{
    supply_module_require_runtime_ready_locked($pdo);
    $itemId = max(0,(int)($data['inventory_item_id'] ?? 0));
    $department = inventory_normalize_department((string)($data['department'] ?? 'shared')) ?? 'shared';
    $note = text_substr(trim((string)($data['note'] ?? '')),0,500);
    $source = in_array((string)($data['source'] ?? 'staff'),['staff','manager','low_stock'],true) ? (string)$data['source'] : 'staff';

    if ($itemId > 0) {
        $item = inventory_item($pdo,$itemId,true);
        if (!$item || (int)$item['active'] !== 1) throw new RuntimeException('کالای انتخاب‌شده دیگر فعال نیست.');
        $baseUnit = (string)$item['base_unit'];
        $requestedBase = inventory_major_to_base($data['quantity_major'] ?? '', $baseUnit);
        if ($requestedBase < 1) throw new RuntimeException('مقدار موردنیاز را وارد کن.');
        $existing = supply_need_open_for_item($pdo,$itemId,$department,true);
        if ($existing) {
            $fulfilled=(int)$existing['fulfilled_quantity_base'];
            $preparing=(int)($existing['preparing_quantity_base']??0);
            // Staff edits only the quantity that is still waiting for purchase. A quantity
            // already marked as preparing is frozen and cannot silently change underneath the buyer.
            $requestedTotal=$fulfilled+$preparing+$requestedBase;
            $pdo->prepare("UPDATE inventory_supply_needs SET requested_quantity_base=?,item_name_snapshot=?,base_unit=?,source=?,note=?,updated_by_user_id=?,last_outcome=NULL WHERE id=?")
                ->execute([$requestedTotal,(string)$item['name'],$baseUnit,$source,$note?:null,$actorUserId,(int)$existing['id']]);
            audit_log_write_strict($pdo,'supply.need_updated','inventory_supply_need',(int)$existing['id'],[
                'inventory_item_id'=>$itemId,'department'=>$department,'requested_quantity_base'=>$requestedTotal,
                'fulfilled_quantity_base'=>$fulfilled,'preparing_quantity_base'=>$preparing,'uncommitted_quantity_base'=>$requestedBase,
            ],$actorUserId);
            return (int)$existing['id'];
        }
        $guard=supply_need_guard($department,$itemId,(string)$item['name'],$baseUnit);
        $stmt=$pdo->prepare("INSERT INTO inventory_supply_needs(inventory_item_id,item_name_snapshot,base_unit,requested_quantity_base,fulfilled_quantity_base,preparing_quantity_base,department,source,status,note,created_by_user_id,updated_by_user_id,open_item_guard) VALUES(?,?,?,?,0,0,?,?,'open',?,?,?,?)");
        $stmt->execute([$itemId,(string)$item['name'],$baseUnit,$requestedBase,$department,$source,$note?:null,$actorUserId,$actorUserId,$guard]);
    } else {
        $name=text_substr(trim((string)($data['free_name'] ?? '')),0,160);
        $baseUnit=inventory_normalize_base_unit((string)($data['base_unit'] ?? 'count'));
        if ($name==='') throw new RuntimeException('نام مورد خارج از فهرست را وارد کن.');
        $requestedBase=inventory_major_to_base($data['quantity_major'] ?? '',$baseUnit);
        if($requestedBase<1) throw new RuntimeException('مقدار موردنیاز را وارد کن.');
        $find=$pdo->prepare("SELECT * FROM inventory_supply_needs WHERE inventory_item_id IS NULL AND department=? AND status='open' AND item_name_snapshot=? ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $find->execute([$department,$name]);
        if($existing=$find->fetch()){
            if((string)$existing['base_unit']!==$baseUnit) throw new RuntimeException('این مورد یک نیاز باز با واحد دیگری دارد؛ همان نیاز را اصلاح کن یا ابتدا آن را ببند.');
            $fulfilled=(int)$existing['fulfilled_quantity_base'];
            $preparing=(int)($existing['preparing_quantity_base']??0);
            $requestedTotal=$fulfilled+$preparing+$requestedBase;
            $pdo->prepare("UPDATE inventory_supply_needs SET requested_quantity_base=?,source=?,note=?,updated_by_user_id=?,last_outcome=NULL WHERE id=?")
                ->execute([$requestedTotal,$source,$note?:null,$actorUserId,(int)$existing['id']]);
            audit_log_write_strict($pdo,'supply.need_updated','inventory_supply_need',(int)$existing['id'],[
                'free_name'=>$name,'department'=>$department,'requested_quantity_base'=>$requestedTotal,'preparing_quantity_base'=>$preparing,'uncommitted_quantity_base'=>$requestedBase,
            ],$actorUserId);
            return (int)$existing['id'];
        }
        $guard=supply_need_guard($department,0,$name,$baseUnit);
        $stmt=$pdo->prepare("INSERT INTO inventory_supply_needs(inventory_item_id,item_name_snapshot,base_unit,requested_quantity_base,fulfilled_quantity_base,preparing_quantity_base,department,source,status,note,created_by_user_id,updated_by_user_id,open_item_guard) VALUES(NULL,?,?,?,0,0,?,?,'open',?,?,?,?)");
        $stmt->execute([$name,$baseUnit,$requestedBase,$department,$source,$note?:null,$actorUserId,$actorUserId,$guard]);
    }
    $id=(int)$pdo->lastInsertId();
    audit_log_write_strict($pdo,'supply.need_created','inventory_supply_need',$id,[
        'inventory_item_id'=>$itemId?:null,'item_name'=>(string)($item['name']??$name??''),'department'=>$department,'requested_quantity_base'=>$requestedBase,'source'=>$source,
    ],$actorUserId);
    return $id;
}

function supply_merge_open_need_into_target_locked(PDO $pdo, array $need, int $targetItemId, int $actorUserId): array
{
    $needId=(int)$need['id'];
    $department=(string)$need['department'];
    $existing=supply_need_open_for_item($pdo,$targetItemId,$department,true);
    if(!$existing || (int)$existing['id']===$needId) return $need;

    $remaining=supply_need_remaining($need);
    $sourcePreparing=(int)($need['preparing_quantity_base']??0);
    if($remaining<1){
        $pdo->prepare("UPDATE inventory_supply_needs SET status='closed',open_item_guard=NULL,preparing_quantity_base=0,preparing_by_user_id=NULL,preparing_at=NULL,last_outcome='merged',closed_by_user_id=?,closed_at=NOW(),updated_by_user_id=? WHERE id=?")
            ->execute([$actorUserId,$actorUserId,$needId]);
        return $existing;
    }
    $newRequested=(int)$existing['requested_quantity_base']+$remaining;
    $newPreparing=(int)($existing['preparing_quantity_base']??0)+$sourcePreparing;
    $mergedNote=trim((string)($existing['note']??''));
    if($mergedNote==='') $mergedNote=trim((string)($need['note']??''));
    $preparingBy=(int)($existing['preparing_by_user_id']??0) ?: (int)($need['preparing_by_user_id']??0);
    $preparingAt=(string)($existing['preparing_at']??'') ?: (string)($need['preparing_at']??'');
    $pdo->prepare("UPDATE inventory_supply_needs SET requested_quantity_base=?,preparing_quantity_base=?,preparing_by_user_id=?,preparing_at=?,note=?,updated_by_user_id=?,last_outcome=NULL WHERE id=?")
        ->execute([$newRequested,$newPreparing,$preparingBy?:null,$preparingAt?:null,$mergedNote!==''?$mergedNote:null,$actorUserId,(int)$existing['id']]);
    $pdo->prepare("UPDATE inventory_supply_needs SET status='cancelled',open_item_guard=NULL,preparing_quantity_base=0,preparing_by_user_id=NULL,preparing_at=NULL,last_outcome='merged',closed_by_user_id=?,closed_at=NOW(),updated_by_user_id=? WHERE id=?")
        ->execute([$actorUserId,$actorUserId,$needId]);
    audit_log_write_strict($pdo,'supply.need_merged','inventory_supply_need',$needId,[
        'target_need_id'=>(int)$existing['id'],'target_inventory_item_id'=>$targetItemId,'merged_remaining_quantity_base'=>$remaining,'merged_preparing_quantity_base'=>$sourcePreparing,
    ],$actorUserId);
    $existing['requested_quantity_base']=$newRequested;
    $existing['preparing_quantity_base']=$newPreparing;
    $existing['preparing_by_user_id']=$preparingBy?:null;
    $existing['preparing_at']=$preparingAt?:null;
    $existing['note']=$mergedNote!==''?$mergedNote:null;
    return $existing;
}

/** Move all currently-uncommitted demand for an item/group into buyer responsibility. */
function supply_mark_group_preparing_locked(PDO $pdo, string $groupKey, int $actorUserId, ?int $expectedUncommittedBase = null): array
{
    supply_module_require_runtime_ready_locked($pdo);
    $rows=supply_group_rows_locked($pdo,$groupKey,false);
    if(!$rows) throw new RuntimeException('این نیاز دیگر باز نیست.');
    $currentUncommitted=array_sum(array_map(static fn(array $r): int => supply_need_uncommitted($r),$rows));
    if($expectedUncommittedBase!==null && $currentUncommitted!==$expectedUncommittedBase) throw new RuntimeException('مقدار این نیاز تغییر کرده است؛ صفحه را تازه کن و دوباره بررسی کن.');
    $moved=0;$ids=[];
    foreach($rows as $row){
        $qty=supply_need_uncommitted($row);
        if($qty<1) continue;
        $newPreparing=(int)($row['preparing_quantity_base']??0)+$qty;
        $pdo->prepare("UPDATE inventory_supply_needs SET preparing_quantity_base=?,preparing_by_user_id=?,preparing_at=COALESCE(preparing_at,NOW()),last_outcome=NULL,updated_by_user_id=? WHERE id=? AND status='open'")
            ->execute([$newPreparing,$actorUserId,$actorUserId,(int)$row['id']]);
        $moved+=$qty;$ids[]=(int)$row['id'];
    }
    if($moved<1) throw new RuntimeException('نیاز جدیدی برای شروع تهیه وجود ندارد.');
    audit_log_write_strict($pdo,'supply.preparing_started','inventory_supply_group',$groupKey,['need_ids'=>$ids,'quantity_base'=>$moved],$actorUserId);
    return ['group_key'=>$groupKey,'quantity_base'=>$moved,'need_ids'=>$ids];
}

/** Return buyer-committed quantity to the ordinary purchase queue; inventory is untouched. */
function supply_return_group_from_preparing_locked(PDO $pdo, string $groupKey, int $actorUserId, string $outcome = 'returned', ?int $expectedPreparingBase = null): array
{
    supply_module_require_runtime_ready_locked($pdo);
    $rows=supply_group_rows_locked($pdo,$groupKey,true);
    if(!$rows) throw new RuntimeException('این قلم دیگر در حال خرید نیست.');
    $currentPreparing=array_sum(array_map(static fn(array $r): int => (int)$r['preparing_quantity_base'],$rows));
    if($expectedPreparingBase!==null && $currentPreparing!==$expectedPreparingBase) throw new RuntimeException('مقدار در حال خرید تغییر کرده است؛ صفحه را تازه کن و دوباره بررسی کن.');
    $returned=0;$ids=[];
    foreach($rows as $row){
        $qty=(int)$row['preparing_quantity_base'];
        if($qty<1) continue;
        $pdo->prepare("UPDATE inventory_supply_needs SET preparing_quantity_base=0,preparing_by_user_id=NULL,preparing_at=NULL,last_outcome=?,updated_by_user_id=? WHERE id=? AND status='open'")
            ->execute([$outcome,$actorUserId,(int)$row['id']]);
        $returned+=$qty;$ids[]=(int)$row['id'];
    }
    audit_log_write_strict($pdo,$outcome==='unavailable'?'supply.preparing_unavailable':'supply.preparing_returned','inventory_supply_group',$groupKey,['need_ids'=>$ids,'quantity_base'=>$returned],$actorUserId);
    return ['group_key'=>$groupKey,'quantity_base'=>$returned,'need_ids'=>$ids];
}


/** Cancel only demand that has not yet been committed to the buyer. */
function supply_cancel_group_uncommitted_locked(PDO $pdo, string $groupKey, int $actorUserId, ?int $expectedUncommittedBase = null): array
{
    supply_module_require_runtime_ready_locked($pdo);
    $rows=supply_group_rows_locked($pdo,$groupKey,false);
    if(!$rows) throw new RuntimeException('این نیاز دیگر باز نیست.');
    $currentUncommitted=array_sum(array_map(static fn(array $r): int => supply_need_uncommitted($r),$rows));
    if($expectedUncommittedBase!==null && $currentUncommitted!==$expectedUncommittedBase) throw new RuntimeException('مقدار نیاز تغییر کرده است؛ صفحه را تازه کن و دوباره بررسی کن.');
    $cancelled=0;$ids=[];
    foreach($rows as $row){
        $uncommitted=supply_need_uncommitted($row);
        if($uncommitted<1) continue;
        $fulfilled=(int)$row['fulfilled_quantity_base'];
        $preparing=(int)($row['preparing_quantity_base']??0);
        $newRequested=$fulfilled+$preparing;
        $status=$preparing>0?'open':'closed';
        $pdo->prepare("UPDATE inventory_supply_needs SET requested_quantity_base=?,status=?,open_item_guard=CASE WHEN ?='closed' THEN NULL ELSE open_item_guard END,last_outcome='cancelled',updated_by_user_id=?,closed_by_user_id=CASE WHEN ?='closed' THEN ? ELSE NULL END,closed_at=CASE WHEN ?='closed' THEN NOW() ELSE NULL END WHERE id=? AND status='open'")
            ->execute([$newRequested,$status,$status,$actorUserId,$status,$actorUserId,$status,(int)$row['id']]);
        $cancelled+=$uncommitted;$ids[]=(int)$row['id'];
    }
    if($cancelled<1) throw new RuntimeException('نیاز جدیدی برای لغو وجود ندارد.');
    audit_log_write_strict($pdo,'supply.uncommitted_cancelled','inventory_supply_group',$groupKey,['need_ids'=>$ids,'quantity_base'=>$cancelled],$actorUserId);
    return ['group_key'=>$groupKey,'quantity_base'=>$cancelled,'need_ids'=>$ids];
}

/**
 * Register one physical receipt for an aggregated preparing group.
 * The inventory movement is created once; fulfillment is then allocated to the
 * underlying departmental needs oldest-first. Any over-receipt increases stock
 * but never creates negative/phantom demand.
 */
function supply_receive_preparing_locked(PDO $pdo, string $groupKey, array $data, int $actorUserId): array
{
    supply_module_require_runtime_ready_locked($pdo);
    $requestToken=trim((string)($data['request_token']??''));
    if(!preg_match('/^[a-f0-9]{32}$/',$requestToken)) throw new RuntimeException('فرم خرید منقضی شده؛ صفحه را تازه کن.');

    $dup=$pdo->prepare("SELECT r.*,i.base_unit FROM inventory_supply_receipts r JOIN inventory_items i ON i.id=r.inventory_item_id WHERE r.request_token=? LIMIT 1");
    $dup->execute([$requestToken]);
    if($existing=$dup->fetch()){
        // A browser/network retry must never create a second movement. Report the
        // current demand state instead of replaying a stale zero-uncommitted value,
        // because staff may have added a new need after the original receipt.
        $state=$pdo->prepare("SELECT requested_quantity_base,fulfilled_quantity_base,preparing_quantity_base FROM inventory_supply_needs WHERE inventory_item_id=? AND status='open'");
        $state->execute([(int)$existing['inventory_item_id']]);
        $preparedNow=0;$uncommittedNow=0;$unmetNow=0;
        foreach($state->fetchAll() as $needState){
            $remaining=max(0,(int)$needState['requested_quantity_base']-(int)$needState['fulfilled_quantity_base']);
            $preparing=min($remaining,max(0,(int)$needState['preparing_quantity_base']));
            $preparedNow+=$preparing;$uncommittedNow+=max(0,$remaining-$preparing);$unmetNow+=$remaining;
        }
        return [
            'item_id'=>(int)$existing['inventory_item_id'],'movement_id'=>(int)$existing['movement_id'],
            'received_quantity_base'=>(int)$existing['received_quantity_base'],'preparing_remaining_base'=>$preparedNow,
            'uncommitted_remaining_base'=>$uncommittedNow,'total_unmet_base'=>$unmetNow,
            'base_unit'=>(string)$existing['base_unit'],'duplicate'=>true,
        ];
    }

    $rows=supply_group_rows_locked($pdo,$groupKey,true);
    if(!$rows) throw new RuntimeException('این قلم دیگر در حال خرید نیست یا قبلاً تحویل شده است.');

    $anchor=$rows[0];
    $itemId=(int)($anchor['inventory_item_id']??0);
    $targetId=max(0,(int)($data['target_item_id']??0));
    if($itemId<1){
        // Provisional groups are intentionally one-need-at-a-time until their catalog identity is resolved.
        if(count($rows)!==1) throw new RuntimeException('کالای خارج از فهرست باید ابتدا به یک کالای انبار متصل شود.');
        $need=$anchor;$needId=(int)$need['id'];
        if($targetId>0){
            $target=inventory_item($pdo,$targetId,true);
            if(!$target || (int)$target['active']!==1) throw new RuntimeException('کالای مقصد معتبر نیست.');
            if((string)$target['base_unit']!==(string)$need['base_unit']) throw new RuntimeException('واحد کالای مقصد با نیاز ثبت‌شده هم‌خوان نیست.');
            $merged=supply_merge_open_need_into_target_locked($pdo,$need,$targetId,$actorUserId);
            $itemId=$targetId;
            if((int)$merged['id']!==(int)$need['id']){
                $groupKey='item:'.$targetId;
                $rows=supply_group_rows_locked($pdo,$groupKey,true);
                if(!$rows) throw new RuntimeException('درخواست‌ها ادغام شدند اما مقدار در حال خرید پیدا نشد؛ صفحه را تازه کن.');
                $anchor=$rows[0];
            }else{
                $guard=supply_need_guard((string)$need['department'],$itemId,(string)$target['name'],(string)$target['base_unit']);
                $pdo->prepare('UPDATE inventory_supply_needs SET inventory_item_id=?,item_name_snapshot=?,base_unit=?,open_item_guard=?,updated_by_user_id=? WHERE id=?')
                    ->execute([$itemId,(string)$target['name'],(string)$target['base_unit'],$guard,$actorUserId,$needId]);
                $rows=supply_group_rows_locked($pdo,'item:'.$itemId,true);
                $groupKey='item:'.$itemId;$anchor=$rows[0];
            }
        }else{
            $itemId=inventory_create_unreviewed_item_locked($pdo,(string)$need['item_name_snapshot'],(string)$need['base_unit'],(string)$need['department'],$actorUserId,'خرید و تأمین');
            $guard=supply_need_guard((string)$need['department'],$itemId,(string)$need['item_name_snapshot'],(string)$need['base_unit']);
            $pdo->prepare('UPDATE inventory_supply_needs SET inventory_item_id=?,open_item_guard=?,updated_by_user_id=? WHERE id=?')->execute([$itemId,$guard,$actorUserId,$needId]);
            $rows=supply_group_rows_locked($pdo,'item:'.$itemId,true);
            $groupKey='item:'.$itemId;$anchor=$rows[0];
        }
    }

    $item=inventory_item($pdo,$itemId,true);
    if(!$item || (int)$item['active']!==1) throw new RuntimeException('کالای انبار معتبر نیست.');
    $baseUnit=(string)$item['base_unit'];
    foreach($rows as $row) if((string)$row['base_unit']!==$baseUnit) throw new RuntimeException('واحد نیازهای تجمیع‌شده هم‌خوان نیست.');

    $preparedTotal=array_sum(array_map(static fn(array $r): int => (int)$r['preparing_quantity_base'],$rows));
    if($preparedTotal<1) throw new RuntimeException('مقداری برای تحویل در حال خرید نیست.');
    $expectedPrepared=isset($data['expected_preparing_quantity_base'])?(int)$data['expected_preparing_quantity_base']:null;
    if($expectedPrepared!==null && $expectedPrepared!==$preparedTotal) throw new RuntimeException('مقدار در حال خرید از زمان بازکردن فرم تغییر کرده است؛ صفحه را تازه کن و دوباره بررسی کن.');

    $resolved=inventory_resolve_operation_quantity($pdo,$itemId,(int)($data['purchase_unit_id']??0),$data['unit_count']??'',$data['actual_major_quantity']??'');
    $received=(int)$resolved['quantity_base'];
    $costRaw=trim((string)($data['total_cost']??''));
    $totalCost=$costRaw===''?null:inventory_money_value($costRaw);
    $supplier=text_substr(trim((string)($data['supplier']??'')),0,160);
    $note=text_substr(trim((string)($data['note']??'')),0,500);
    $occurredAt=inventory_optional_occurred_at((string)($data['occurred_date_j']??''),(string)($data['occurred_time']??''),'دریافت خرید');
    $departments=array_values(array_unique(array_map(static fn(array $r): string => (string)$r['department'],$rows)));
    $movementDepartment=count($departments)===1?$departments[0]:'shared';

    $movementId=inventory_record_movement_locked($pdo,array_merge($resolved,[
        'item_id'=>$itemId,'movement_type'=>'purchase_receive','quantity_base'=>$received,
        'department'=>$movementDepartment,'total_cost_delta'=>$totalCost,'cost_status'=>$totalCost===null?'unknown':'known',
        'source_type'=>'inventory_supply_group','source_id'=>$groupKey,
        'idempotency_key'=>'supply:receive:'.$requestToken,
        'metadata'=>['supplier'=>$supplier?:null,'prepared_quantity_base'=>$preparedTotal,'need_ids'=>array_map(static fn(array $r): int => (int)$r['id'],$rows),'occurred_precision'=>inventory_occurrence_precision((string)($data['occurred_date_j']??''),(string)($data['occurred_time']??''))],
        'note'=>$note?:null,'actor_user_id'=>$actorUserId,'occurred_at'=>$occurredAt,
    ]));

    $toAllocate=min($received,$preparedTotal);
    $allocations=[];$uncommittedAfter=0;$preparedAfter=0;$totalUnmetAfter=0;
    foreach($rows as $row){
        $prepared=(int)$row['preparing_quantity_base'];
        $allocated=min($prepared,$toAllocate);
        $toAllocate-=$allocated;
        $newPreparing=$prepared-$allocated;
        $newFulfilled=(int)$row['fulfilled_quantity_base']+$allocated;
        $remaining=max(0,(int)$row['requested_quantity_base']-$newFulfilled);
        $uncommitted=max(0,$remaining-$newPreparing);
        $status=($remaining===0 && $newPreparing===0)?'closed':'open';
        $outcome=$status==='closed'?'received':($allocated>0?'partial':(string)($row['last_outcome']??''));
        $pdo->prepare("UPDATE inventory_supply_needs SET fulfilled_quantity_base=?,preparing_quantity_base=?,preparing_by_user_id=CASE WHEN ?>0 THEN preparing_by_user_id ELSE NULL END,preparing_at=CASE WHEN ?>0 THEN preparing_at ELSE NULL END,status=?,open_item_guard=CASE WHEN ?='closed' THEN NULL ELSE open_item_guard END,last_outcome=?,updated_by_user_id=?,closed_by_user_id=CASE WHEN ?='closed' THEN ? ELSE NULL END,closed_at=CASE WHEN ?='closed' THEN NOW() ELSE NULL END WHERE id=?")
            ->execute([$newFulfilled,$newPreparing,$newPreparing,$newPreparing,$status,$status,$outcome?:null,$actorUserId,$status,$actorUserId,$status,(int)$row['id']]);
        if($allocated>0)$allocations[]=['need_id'=>(int)$row['id'],'quantity_base'=>$allocated];
        $uncommittedAfter+=$uncommitted;$preparedAfter+=$newPreparing;$totalUnmetAfter+=$remaining;
    }

    $anchorNeedId=(int)$anchor['id'];
    $receipt=$pdo->prepare("INSERT INTO inventory_supply_receipts(supply_need_id,inventory_item_id,requested_quantity_snapshot,received_quantity_base,remaining_quantity_after,purchase_unit_id,purchase_unit_name_snapshot,purchase_unit_count,conversion_base_quantity_snapshot,total_cost,supplier,note,request_token,movement_id,actor_user_id,received_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,COALESCE(?,NOW()))");
    $receipt->execute([$anchorNeedId,$itemId,$preparedTotal,$received,$preparedAfter,$resolved['purchase_unit_id'],$resolved['purchase_unit_name_snapshot'],$resolved['purchase_unit_count'],$resolved['conversion_base_quantity_snapshot'],$totalCost,$supplier?:null,$note?:null,$requestToken,$movementId,$actorUserId,$occurredAt]);
    $receiptId=(int)$pdo->lastInsertId();
    if($allocations){
        $allocStmt=$pdo->prepare("INSERT INTO inventory_supply_receipt_allocations(receipt_id,supply_need_id,allocated_quantity_base) VALUES(?,?,?)");
        foreach($allocations as $allocation)$allocStmt->execute([$receiptId,$allocation['need_id'],$allocation['quantity_base']]);
    }
    audit_log_write_strict($pdo,'supply.preparing_received','inventory_supply_receipt',$receiptId,[
        'group_key'=>$groupKey,'inventory_item_id'=>$itemId,'prepared_quantity_base'=>$preparedTotal,'received_quantity_base'=>$received,
        'preparing_remaining_base'=>$preparedAfter,'uncommitted_remaining_base'=>$uncommittedAfter,'movement_id'=>$movementId,'allocations'=>$allocations,
    ],$actorUserId);
    return [
        'item_id'=>$itemId,'movement_id'=>$movementId,'receipt_id'=>$receiptId,'received_quantity_base'=>$received,
        'preparing_remaining_base'=>$preparedAfter,'uncommitted_remaining_base'=>$uncommittedAfter,'total_unmet_base'=>$totalUnmetAfter,
        'base_unit'=>$baseUnit,'duplicate'=>false,
    ];
}

function supply_mark_unavailable_locked(PDO $pdo, int $needId, int $actorUserId): void
{
    supply_module_require_runtime_ready_locked($pdo);
    $stmt=$pdo->prepare("UPDATE inventory_supply_needs SET last_outcome='unavailable',updated_by_user_id=? WHERE id=? AND status='open'");
    $stmt->execute([$actorUserId,$needId]);
    if($stmt->rowCount()<1) throw new RuntimeException('این نیاز دیگر باز نیست.');
    audit_log_write_strict($pdo,'supply.need_unavailable','inventory_supply_need',$needId,[],$actorUserId);
}

function supply_cancel_need_locked(PDO $pdo, int $needId, int $actorUserId): void
{
    supply_module_require_runtime_ready_locked($pdo);
    $locked=$pdo->prepare("SELECT * FROM inventory_supply_needs WHERE id=? AND status='open' FOR UPDATE");
    $locked->execute([$needId]);$need=$locked->fetch();
    if(!$need) throw new RuntimeException('این نیاز دیگر باز نیست.');
    if((int)($need['preparing_quantity_base']??0)>0) throw new RuntimeException('این درخواست در حال خرید است؛ ابتدا آن را به فهرست خرید برگردان.');
    $stmt=$pdo->prepare("UPDATE inventory_supply_needs SET status='cancelled',open_item_guard=NULL,last_outcome='cancelled',closed_by_user_id=?,closed_at=NOW(),updated_by_user_id=? WHERE id=? AND status='open'");
    $stmt->execute([$actorUserId,$actorUserId,$needId]);
    if($stmt->rowCount()<1) throw new RuntimeException('این نیاز دیگر باز نیست.');
    audit_log_write_strict($pdo,'supply.need_cancelled','inventory_supply_need',$needId,[],$actorUserId);
}
