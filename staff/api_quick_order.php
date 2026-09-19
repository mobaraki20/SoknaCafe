<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/maintenance.php';
require_once dirname(__DIR__) . '/includes/push.php';
require_once dirname(__DIR__) . '/includes/staff_order_service.php';
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

$pdo = db();
try {
    $pdo->beginTransaction();
    $result = staff_order_commit_tx($pdo, $data, $user, $mode);
    $pdo->commit();
    staff_order_after_commit($result);
    unset($result['_after_commit_order_id']);
    json_response($result);
} catch (StaffQuickOrderException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $status = in_array((int)$e->getCode(), [403,404,409,422], true) ? (int)$e->getCode() : 422;
    json_response(['success'=>false,'message'=>$e->getMessage()],$status);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('staff quick order: ' . $e->getMessage());
    json_response(['success'=>false,'message'=>'ثبت سفارش انجام نشد؛ دوباره تلاش کنید.'],500);
}
