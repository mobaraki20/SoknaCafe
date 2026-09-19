<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/maintenance.php';
require_once dirname(__DIR__) . '/includes/push.php';
require_any_capability(['orders_floor','cashier_accounts']);
maintenance_guard_json();

$user = current_user();
$userId = (int)($user['id'] ?? 0);
$requestData = $_SERVER['REQUEST_METHOD'] === 'POST' ? request_json() : [];
$mode = (string)($_SERVER['REQUEST_METHOD'] === 'POST' ? ($requestData['mode'] ?? 'normal') : ($_GET['mode'] ?? 'normal'));
if (!in_array($mode, ['normal','late_accounting'], true)) $mode = 'normal';
if ($mode === 'late_accounting') {
    if (!user_has_capability('cashier_accounts', $user)) json_response(['success'=>false,'message'=>'ثبت قلم جاافتاده فقط برای صندوق‌دار مجاز است.'],403);
} elseif (!staff_quick_order_allowed($user)) {
    json_response(['success'=>false,'message'=>'دسترسی ثبت سفارش برای این حساب فعال نیست.'],403);
}

final class StaffQuickOrderException extends RuntimeException {}

function quick_order_table_scope(): array
{
    return array_map('intval', array_column(db()->query('SELECT id FROM cafe_tables WHERE active=1 ORDER BY sort_order,id')->fetchAll(), 'id'));
}

/** @return array{tables:array<int,array<string,mixed>>,categories:array<int,array<string,mixed>>,items:array<int,array<string,mixed>>} */
function quick_order_catalog(array $tableIds, ?string $requestedMenuKey = null): array
{
    if (!$tableIds) return ['tables'=>[], 'categories'=>[], 'items'=>[]];
    $pdo = db();
    $ph = implode(',', array_fill(0, count($tableIds), '?'));
    $tableStmt = $pdo->prepare("SELECT t.id,t.name,t.table_number,t.code,t.zone_label,t.sort_order,
        s.id session_id,s.status session_status,s.discount_type,COALESCE(s.discount_value,0) discount_value,COALESCE(s.discount_amount,0) discount_amount,
        COALESCE((SELECT SUM(sr.total) FROM settlement_records sr WHERE sr.session_id=s.id AND sr.status='completed' AND sr.allocation_version=1 AND NOT EXISTS(SELECT 1 FROM settlement_records rv WHERE rv.reverses_settlement_id=sr.id AND rv.status='reversal')),0) paid_total,
        EXISTS(SELECT 1 FROM settlement_records sx WHERE sx.session_id=s.id AND sx.status='completed' AND sx.allocation_version=1 AND sx.settlement_kind='itemized' AND NOT EXISTS(SELECT 1 FROM settlement_records rx WHERE rx.reverses_settlement_id=sx.id AND rx.status='reversal')) itemized_active
        FROM cafe_tables t
        LEFT JOIN table_sessions s ON s.id=(SELECT MAX(s2.id) FROM table_sessions s2 WHERE s2.table_id=t.id AND s2.status IN('active','pending'))
        WHERE t.active=1 AND t.id IN($ph)
        ORDER BY t.sort_order,t.id");
    $tableStmt->execute($tableIds);
    $tables = $tableStmt->fetchAll();
    $tableBySession = [];
    foreach ($tables as $index => &$table) {
        $table['id'] = (int)$table['id'];
        $table['sort_order'] = (int)$table['sort_order'];
        $table['table_number'] = (int)$table['table_number'];
        $table['session_id'] = $table['session_id'] !== null ? (int)$table['session_id'] : null;
        $table['discount_value'] = (int)$table['discount_value'];
        $table['discount_amount'] = (int)$table['discount_amount'];
        $table['paid_total'] = (int)($table['paid_total'] ?? 0);
        $table['itemized_active'] = (int)($table['itemized_active'] ?? 0) === 1;
        $table['pending_order_count'] = 0;
        $table['pending_orders'] = [];
        $table['current_total'] = 0;
        $table['current_final_total'] = 0;
        $table['current_quantity'] = 0;
        $table['current_order_count'] = 0;
        $table['current_items'] = [];
        $table['is_open'] = $table['session_id'] !== null;
        if ($table['session_id']) $tableBySession[(int)$table['session_id']] = $index;
    }
    unset($table);

    if ($tableBySession) {
        $sessionIds = array_keys($tableBySession);
        $sessionPh = implode(',', array_fill(0, count($sessionIds), '?'));
        $orderStmt = $pdo->prepare("SELECT id,session_id,status,total_amount,customer_note,created_at
            FROM orders
            WHERE session_id IN($sessionPh) AND status IN('pending_approval','new','accounted')
            ORDER BY session_id,created_at,id");
        $orderStmt->execute($sessionIds);
        $orders = $orderStmt->fetchAll();
        $itemsByOrder = [];
        $orderIds = array_map('intval', array_column($orders, 'id'));
        if ($orderIds) {
            $orderPh = implode(',', array_fill(0, count($orderIds), '?'));
            $itemStmt = $pdo->prepare("SELECT order_id,item_name,unit_price,quantity,item_note,line_total
                FROM order_items WHERE order_id IN($orderPh) AND quantity>0 ORDER BY order_id,id");
            $itemStmt->execute($orderIds);
            foreach ($itemStmt->fetchAll() as $line) {
                $itemsByOrder[(int)$line['order_id']][] = [
                    'name'=>(string)$line['item_name'],
                    'unit_price'=>(int)$line['unit_price'],
                    'quantity'=>(int)$line['quantity'],
                    'note'=>(string)($line['item_note'] ?? ''),
                    'line_total'=>(int)$line['line_total'],
                ];
            }
        }

        $currentAggregates = [];
        foreach ($orders as $order) {
            $sessionId = (int)$order['session_id'];
            if (!isset($tableBySession[$sessionId])) continue;
            $tableIndex = $tableBySession[$sessionId];
            $orderId = (int)$order['id'];
            $lines = $itemsByOrder[$orderId] ?? [];
            if ((string)$order['status'] !== 'accounted') {
                $tables[$tableIndex]['pending_orders'][] = [
                    'id'=>$orderId,
                    'number'=>order_display_number($orderId),
                    'total'=>(int)$order['total_amount'],
                    'note'=>(string)($order['customer_note'] ?? ''),
                    'created_at'=>(string)$order['created_at'],
                    'items'=>$lines,
                ];
                $tables[$tableIndex]['pending_order_count']++;
                continue;
            }
            $tables[$tableIndex]['current_total'] += (int)$order['total_amount'];
            $tables[$tableIndex]['current_order_count']++;
            foreach ($lines as $line) {
                $tables[$tableIndex]['current_quantity'] += (int)$line['quantity'];
                $key = $line['name'] . "\x1f" . $line['unit_price'] . "\x1f" . $line['note'];
                if (!isset($currentAggregates[$tableIndex][$key])) {
                    $currentAggregates[$tableIndex][$key] = $line;
                } else {
                    $currentAggregates[$tableIndex][$key]['quantity'] += (int)$line['quantity'];
                    $currentAggregates[$tableIndex][$key]['line_total'] += (int)$line['line_total'];
                }
            }
        }
        foreach ($tables as $index => &$table) {
            $table['current_items'] = array_values($currentAggregates[$index] ?? []);
            $table['discount_amount'] = invoice_discount_amount((int)$table['current_total'], (string)($table['discount_type'] ?? ''), (int)$table['discount_value']);
            $table['current_final_total'] = max(0, (int)$table['current_total'] - (int)$table['discount_amount']);
            $table['remaining_total'] = max(0, (int)$table['current_final_total'] - (int)$table['paid_total']);
        }
        unset($table);
    }

    $catalog = menu_catalog_snapshot($pdo,'staff_order',$requestedMenuKey,true);
    $categories = array_map(static fn(array $row): array => [
        'id'=>(int)$row['id'],
        'category_key'=>(string)$row['category_key'],
        'name'=>(string)$row['name'],
        'audience'=>(string)$row['audience'],
        'icon_key'=>(string)($row['icon_key'] ?? ''),
        'icon'=>category_visual_icon((string)($row['icon_key'] ?? ''),(string)$row['name']),
    ],$catalog['categories']);
    $items = [];
    foreach ($catalog['items'] as $row) {
        $station=normalize_preparation_station((string)$row['preparation_station']);
        $items[] = [
            'id'=>(int)$row['id'],
            'category_id'=>(int)$row['category_id'],
            'name'=>(string)$row['name'],
            'category_name'=>(string)$row['category_name'],
            'price'=>(int)$row['price'],
            'station'=>$station,
            'sellable_kind'=>normalize_sellable_kind($row['sellable_kind']??null),
            'takeaway_allowed'=>(int)($row['takeaway_allowed']??1),
            'order_available'=>1,
            'blocked_scope'=>null,
            'unavailable_message'=>'',
        ];
    }
    return ['tables'=>$tables,'menus'=>$catalog['menus'],'selected_menu'=>$catalog['selected_menu'],'categories'=>$categories,'items'=>$items];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $tableIds = quick_order_table_scope();
    json_response(['success'=>true] + quick_order_catalog($tableIds,trim((string)($_GET['menu']??''))));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success'=>false,'message'=>'روش درخواست معتبر نیست.'],405);
$data = $requestData;
if (!csrf_valid($data['csrf_token'] ?? null)) json_response(['success'=>false,'message'=>'صفحه منقضی شده؛ دوباره تلاش کنید.'],419);
if (!table_sessions_enabled()) json_response(['success'=>false,'message'=>'ثبت سریع در وضعیت فعلی سامانه در دسترس نیست.'],409);

$tableId = (int)($data['table_id'] ?? 0);
$expectedSessionId = max(0, (int)($data['expected_session_id'] ?? 0));
$note = text_substr(trim((string)($data['note'] ?? '')), 0, 500);
try {
    $clientToken = normalize_staff_order_request_token($data['request_token'] ?? '');
    $rows = normalize_staff_quick_order_rows($data['items'] ?? null);
} catch (InvalidArgumentException $e) {
    $status = in_array((int)$e->getCode(), [409,422], true) ? (int)$e->getCode() : 422;
    json_response(['success'=>false,'message'=>$e->getMessage()],$status);
}
$activeTableIds = quick_order_table_scope();
if ($tableId < 1 || !in_array($tableId, $activeTableIds, true)) json_response(['success'=>false,'message'=>'میز فعال پیدا نشد.'],404);
$pdo = db();
$table = null;
$orderId = 0;
try {
    $pdo->beginTransaction();
    $tableStmt = $pdo->prepare('SELECT id,name,table_number,code FROM cafe_tables WHERE id=? AND active=1 FOR UPDATE');
    $tableStmt->execute([$tableId]);
    $table = $tableStmt->fetch();
    if (!$table) throw new StaffQuickOrderException('میز فعال پیدا نشد.');

    $duplicateStmt = $pdo->prepare("SELECT id,table_id,order_source,created_by_user_id FROM orders WHERE client_token=? LIMIT 1 FOR UPDATE");
    $duplicateStmt->execute([$clientToken]);
    if ($duplicate = $duplicateStmt->fetch()) {
        if ((int)$duplicate['table_id'] !== $tableId
            || (string)$duplicate['order_source'] !== 'staff'
            || (int)$duplicate['created_by_user_id'] !== $userId) {
            throw new StaffQuickOrderException('این شناسه ثبت سفارش قابل استفاده نیست؛ پنجره را ببندید و دوباره باز کنید.');
        }
        $pdo->commit();
        json_response(['success'=>true,'duplicate'=>true,'message'=>'این سفارش قبلاً ثبت شده بود.','order_id'=>(int)$duplicate['id'],'order_number'=>order_display_number((int)$duplicate['id'])]);
    }

    $sessionStmt = $pdo->prepare("SELECT * FROM table_sessions WHERE table_id=? AND status IN('active','pending') ORDER BY FIELD(status,'active','pending'),id DESC LIMIT 1 FOR UPDATE");
    $sessionStmt->execute([$tableId]);
    $session = $sessionStmt->fetch() ?: null;
    $currentSessionId = $session ? (int)$session['id'] : 0;
    if ($expectedSessionId > 0) {
        if ($currentSessionId !== $expectedSessionId) {
            throw new StaffQuickOrderException('حساب این میز تغییر کرده است؛ صفحه را تازه کنید.');
        }
    } elseif ($currentSessionId > 0) {
        throw new StaffQuickOrderException('وضعیت میز تغییر کرده است؛ صفحه را تازه کنید.');
    }
    if (!$session) {
        $session = create_table_session($tableId, $userId, null, null, 'active');
    }
    $sessionId = (int)$session['id'];
    $itemizedActive = settlement_session_has_active_itemized_locked($pdo, $sessionId);
    if ($mode === 'late_accounting') {
        if (!$itemizedActive) throw new StaffQuickOrderException('این حساب در وضعیت تسویه جداگانه نیست؛ حساب را تازه کنید.', 409);
        if ($expectedSessionId < 1 || $expectedSessionId !== $sessionId) throw new StaffQuickOrderException('حساب این میز تغییر کرده است؛ به صندوق برگردید و دوباره وارد ثبت قلم جاافتاده شوید.', 409);
    } elseif ($itemizedActive) {
        throw new StaffQuickOrderException('پرداخت جداگانه این حساب شروع شده است؛ سفارش تازه قفل است. برای قلمی که قبلاً سرو شده ولی در حساب جا افتاده، از مسیر «افزودن قلم جاافتاده» در صندوق استفاده کنید.', 409);
    }
    if (accommodation_transfer_blocks_invoice_edit($sessionId)) throw new StaffQuickOrderException('نتیجه ثبت حساب اقامتگاه هنوز مشخص نشده است و سفارش تازه پذیرفته نمی‌شود.');

    $pendingStmt = $pdo->prepare("SELECT id FROM orders WHERE session_id=? AND status IN('pending_approval','new') ORDER BY id FOR UPDATE");
    $pendingStmt->execute([$sessionId]);
    if ($pendingStmt->fetch()) {
        throw new StaffQuickOrderException('سفارش مهمان منتظر بررسی است؛ ابتدا آن را تأیید یا رد کنید.');
    }

    if ((string)$session['status'] === 'pending') {
        $pdo->prepare("UPDATE table_sessions SET status='active',live_table_guard=table_id,opened_by_user_id=COALESCE(opened_by_user_id,?) WHERE id=?")
            ->execute([$userId,$sessionId]);
        $session['status'] = 'active';
    }

    $ids = array_values(array_unique(array_map('intval', array_column($rows,'id'))));
    $itemsById = order_catalog_items_locked($pdo, $ids);
    foreach ($rows as $row) {
        $id=(int)$row['id'];
        $item = $itemsById[$id] ?? null;
        if (!order_catalog_item_is_orderable($item)) {
            throw new StaffQuickOrderException('یکی از آیتم‌های انتخاب‌شده دیگر قابل سفارش نیست؛ فهرست را تازه کنید.');
        }
        if (!order_catalog_item_allows_fulfillment($item,(string)$row['fulfillment_mode'])) {
            throw new StaffQuickOrderException('«'.(string)$item['name'].'» فقط داخل کافه قابل سرو است.');
        }
        if ($mode === 'late_accounting' && normalize_fulfillment_mode((string)$row['fulfillment_mode']) !== 'dine_in') {
            throw new StaffQuickOrderException('قلم جاافتاده این میز فقط به‌صورت داخل کافه ثبت می‌شود.');
        }
        $expectedPrice = $row['expected_price'] ?? null;
        if ($expectedPrice !== null && (int)$expectedPrice !== (int)$item['price']) {
            throw new StaffQuickOrderException('قیمت یکی از آیتم‌ها تغییر کرده است؛ فهرست را تازه و دوباره مرور کنید.');
        }
    }

    $total = 0;
    $lines = [];
    foreach ($rows as $row) {
        $id=(int)$row['id'];$item=$itemsById[$id];
        $quantity=(int)$row['quantity'];$itemNote=(string)$row['note'];
        $lineTotal=(int)$item['price']*$quantity;$total+=$lineTotal;
        $lines[] = [$id,(string)$item['name'],(int)$item['price'],$quantity,$itemNote,normalize_fulfillment_mode((string)$row['fulfillment_mode']),normalize_preparation_station((string)($item['preparation_station'] ?? 'cold_bar')),$lineTotal,normalize_sellable_kind($item['sellable_kind']??null)];
    }

    $publicCode = strtoupper(bin2hex(random_bytes(8)));
    $business = business_assignment();
    $businessOrderNumber = order_allocate_business_number($pdo,(string)$business['business_date']);
    $insert = $pdo->prepare("INSERT INTO orders(public_code,client_token,device_token,table_id,session_id,order_source,status,customer_note,total_amount,accepted_at,accepted_by_user_id,created_by_user_id,business_order_number,business_date,business_shift_key,business_shift_label,business_cutoff_snapshot)
        VALUES(?,?,NULL,?,?, 'staff','accounted',?,?,NOW(),?,?,?,?,?,?,?)");
    $insert->execute([$publicCode,$clientToken,$tableId,$sessionId,$note,$total,$userId,$userId,$businessOrderNumber,(string)$business['business_date'],(string)$business['shift_key'],(string)$business['shift_label'],(string)$business['cutoff']]);
    $orderId = (int)$pdo->lastInsertId();
    $lineStmt = $pdo->prepare('INSERT INTO order_items(order_id,item_id,item_name,sellable_kind_snapshot,unit_price,quantity,ordered_quantity,item_note,fulfillment_mode,preparation_station,line_total) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($lines as [$itemId,$name,$price,$quantity,$itemNote,$fulfillmentMode,$station,$lineTotal,$sellableKind]) {
        $lineStmt->execute([$orderId,$itemId,$name,$sellableKind,$price,$quantity,$quantity,$itemNote !== '' ? $itemNote : null,$fulfillmentMode,$station,$lineTotal]);
    }
    $pdo->prepare("INSERT INTO order_status_history(order_id,from_status,to_status,actor_user_id) VALUES(?,NULL,'accounted',?)")->execute([$orderId,$userId]);
    $hasPreparation = (bool)array_filter($lines, static fn(array $line): bool => preparation_station_requires_work((string)$line[6]));
    if ($mode !== 'late_accounting' && $hasPreparation) {
        print_enqueue_prep_order($pdo, $orderId, $userId);
        order_side_effect_best_effort_tx($pdo, 'push staff quick order', static function () use ($pdo,$table,$orderId,$user,$requestToken): void {
            push_enqueue_event_tx($pdo, 'order', [
            'title'=>'سفارش کارکنان برای ' . $table['name'],
            'body'=>order_display_label($orderId) . ' توسط ' . (string)$user['display_name'] . ' ثبت شد.',
            'url'=>asset('waiter/index.php'),
            'tag'=>'staff-order-' . $orderId,
            'order_id'=>$orderId,
        ], $requestToken);
        });
    }
    inventory_enqueue_order_event_tx($pdo,'accounted',$orderId,$userId,['late_accounting'=>$mode === 'late_accounting'],'inventory:order-accounted:'.$orderId);
    if ($mode === 'late_accounting') {
        audit_log_write_strict($pdo, 'order.late_accounting_created', 'order', $orderId, [
            'table_id'=>$tableId,
            'session_id'=>$sessionId,
            'quantity'=>array_sum(array_map(static fn(array $line): int => (int)$line[3], $lines)),
            'total'=>$total,
            'preparation_suppressed'=>true,
            'inventory_accounted'=>true,
            'request_token'=>$clientToken,
        ], $userId);
    }
    $pdo->commit();
    inventory_register_after_response_order($orderId);

    $message = $mode === 'late_accounting' ? 'قلم جاافتاده به حساب ' . $table['name'] . ' اضافه شد؛ برای آماده‌سازی دوباره ارسال نشد.' : 'سفارش ' . $table['name'] . ' ثبت شد.';
    json_response([
        'success'=>true,
        'message'=>$message,
        'order_id'=>$orderId,
        'order_number'=>order_display_number($orderId),
        'mode'=>$mode,
    ]);
} catch (StaffQuickOrderException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $status = in_array((int)$e->getCode(), [409,422], true) ? (int)$e->getCode() : 422;
    json_response(['success'=>false,'message'=>$e->getMessage()],$status);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('staff quick order: ' . $e->getMessage());
    json_response(['success'=>false,'message'=>'ثبت سفارش انجام نشد؛ دوباره تلاش کنید.'],500);
}
