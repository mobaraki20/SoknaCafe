<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/maintenance.php';

maintenance_guard_json();

final class GuestOrderEditException extends RuntimeException
{
    public function __construct(
        public string $errorCode,
        string $message,
        public int $httpStatus = 409,
        public array $details = []
    ) {
        parent::__construct($message);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success' => false], 405);
$data = request_json();
if (!csrf_valid($data['csrf_token'] ?? null)) {
    json_response(['success' => false, 'code' => 'page_expired', 'message' => customer_message('page_expired')], 419);
}

$action = trim((string)($data['action'] ?? 'list'));
$tableToken = trim((string)($data['table_token'] ?? ''));
$sessionToken = trim((string)($data['session_token'] ?? ''));
$deviceToken = trim((string)($data['device_token'] ?? ''));
if ($tableToken === '' || strlen($tableToken) > 80 || $deviceToken === '' || strlen($deviceToken) > 80 || strlen($sessionToken) > 80) {
    json_response(['success' => false, 'code' => 'invalid_context', 'message' => 'اطلاعات میز یا دستگاه معتبر نیست.'], 422);
}

function guest_order_table(string $token, bool $forUpdate = false): ?array
{
    $sql = 'SELECT id,name,code FROM cafe_tables WHERE access_token=? AND active=1 LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = db()->prepare($sql);
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

function guest_order_session(int $tableId, string $sessionToken = '', bool $forUpdate = false): ?array
{
    $params = [$tableId];
    $sql = "SELECT * FROM table_sessions WHERE table_id=? AND status IN('active','pending')";
    if ($sessionToken !== '') {
        $sql .= ' AND public_token=?';
        $params[] = $sessionToken;
    }
    $sql .= " ORDER BY FIELD(status,'active','pending'),id DESC LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() ?: null;
}

function guest_order_rows(int $sessionId, string $deviceToken): array
{
    $stmt = db()->prepare("SELECT id,public_code,client_token,status,customer_note,total_amount,created_at,updated_at
        FROM orders WHERE session_id=? AND device_token=?
        ORDER BY created_at,id");
    $stmt->execute([$sessionId, $deviceToken]);
    $orders = $stmt->fetchAll();
    if (!$orders) return [];

    $ids = array_map('intval', array_column($orders, 'id'));
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $lineStmt = db()->prepare("SELECT order_id,item_id,item_name,unit_price,quantity,item_note,fulfillment_mode,preparation_station,line_total
        FROM order_items WHERE order_id IN($ph) ORDER BY order_id,id");
    $lineStmt->execute($ids);
    $linesByOrder = [];
    foreach ($lineStmt->fetchAll() as $line) $linesByOrder[(int)$line['order_id']][] = $line;

    return array_map(static function(array $order) use ($linesByOrder): array {
        $id = (int)$order['id'];
        $lines = $linesByOrder[$id] ?? [];
        return [
            'order_code' => (string)$order['public_code'],
            'client_token' => (string)$order['client_token'],
            'order_number' => order_display_number($order),
            'status' => (string)$order['status'],
            'status_label' => order_status_label((string)$order['status']),
            'customer_note' => (string)($order['customer_note'] ?? ''),
            'total_amount' => (int)$order['total_amount'],
            'created_at' => (string)$order['created_at'],
            'updated_at' => (string)$order['updated_at'],
            'edit_signature' => guest_order_edit_signature($order, $lines),
            'can_edit' => guest_order_status_is_mutable((string)$order['status']),
            'can_cancel' => guest_order_status_is_mutable((string)$order['status']),
            'items' => array_map(static fn(array $line): array => [
                'id' => (int)($line['item_id'] ?? 0),
                'name' => (string)$line['item_name'],
                'unit_price' => (int)$line['unit_price'],
                'quantity' => (int)$line['quantity'],
                'note' => (string)($line['item_note'] ?? ''),
                'fulfillment_mode' => normalize_fulfillment_mode((string)($line['fulfillment_mode'] ?? 'dine_in')),
                'line_total' => (int)$line['line_total'],
            ], $lines),
        ];
    }, $orders);
}

/**
 * Validate a full replacement of a mutable order.
 * Existing quantities may always stay the same or decrease; only newly added quantity
 * must pass today's availability, schedule, guest-pause and price rules.
 */
function validate_guest_order_update_lines(PDO $pdo, array $payload, array $existingRows): array
{
    $existing = [];
    foreach ($existingRows as $row) {
        $key=(int)$row['item_id'].'|'.normalize_fulfillment_mode((string)($row['fulfillment_mode'] ?? 'dine_in'));
        $existing[$key] = $row;
    }
    $catalog = order_catalog_items_locked($pdo, array_column($payload['items'], 'id'));

    $unavailable = [];
    $serviceBlocked = [];
    $priceChanges = [];
    $lines = [];
    $total = 0;

    foreach ($payload['items'] as $line) {
        $itemId = (int)$line['id'];
        $mode = normalize_fulfillment_mode((string)($line['fulfillment_mode'] ?? 'dine_in'));
        $newQty = (int)$line['quantity'];
        $old = $existing[$itemId.'|'.$mode] ?? null;
        $oldQty = $old ? (int)$old['quantity'] : 0;
        $increase = $newQty > $oldQty;
        $item = $catalog[$itemId] ?? null;

        if ($mode==='takeaway' && ($increase || !$old) && !order_catalog_item_allows_fulfillment($item,$mode)) {
            $label=$item ? (string)$item['name'] : ($old ? (string)$old['item_name'] : ('آیتم '.fa_digits($itemId)));
            throw new GuestOrderEditException('takeaway_not_allowed','«'.$label.'» فقط داخل کافه قابل سرو است.',409,['item_id'=>$itemId,'item_name'=>$label]);
        }
        if (!$increase && $old) {
            $unit = (int)$old['unit_price'];
            $lineTotal = $unit * $newQty;
            $total += $lineTotal;
            $lines[] = [
                'item_id'=>$itemId,
                'item_name'=>(string)$old['item_name'],
                'unit_price'=>$unit,
                'quantity'=>$newQty,
                'item_note'=>(string)$line['note'],
                'fulfillment_mode'=>$mode,
                'station'=>normalize_preparation_station((string)($old['preparation_station'] ?? 'cold_bar')),
                'line_total'=>$lineTotal,
            ];
            continue;
        }

        if (!order_catalog_item_is_orderable($item, 'guest')) {
            $unavailable[] = $item ? (string)$item['name'] : ($old ? (string)$old['item_name'] : ('آیتم ' . fa_digits($itemId)));
            continue;
        }
        $blockedScope = order_acceptance_blocked_scope_for_station((string)$item['preparation_station']);
        if ($blockedScope !== null) {
            $serviceBlocked[] = ['name'=>(string)$item['name'],'scope'=>$blockedScope];
            continue;
        }

        $currentPrice = (int)$item['price'];
        if ($old && $currentPrice !== (int)$old['unit_price']) {
            $priceChanges[] = (string)$item['name'];
            continue;
        }
        if ($line['expected_price'] === null || (int)$line['expected_price'] !== $currentPrice) {
            $priceChanges[] = (string)$item['name'];
            continue;
        }

        // Keep the original snapshot for an existing line; new lines use today's catalog snapshot.
        $name = $old ? (string)$old['item_name'] : (string)$item['name'];
        $station = $old
            ? normalize_preparation_station((string)($old['preparation_station'] ?? 'cold_bar'))
            : normalize_preparation_station((string)($item['preparation_station'] ?? 'cold_bar'));
        $unit = $old ? (int)$old['unit_price'] : $currentPrice;
        $lineTotal = $unit * $newQty;
        $total += $lineTotal;
        $lines[] = [
            'item_id'=>$itemId,'item_name'=>$name,'unit_price'=>$unit,'quantity'=>$newQty,
            'item_note'=>(string)$line['note'],'fulfillment_mode'=>$mode,'station'=>$station,'line_total'=>$lineTotal,
        ];
    }

    if ($unavailable) {
        $unavailable = array_values(array_unique($unavailable));
        throw new GuestOrderEditException('items_unavailable', customer_message('items_unavailable', ['items'=>implode('، ', $unavailable)]), 409, ['items'=>$unavailable]);
    }
    if ($serviceBlocked) {
        $names = array_values(array_unique(array_column($serviceBlocked, 'name')));
        $scopes = array_values(array_unique(array_column($serviceBlocked, 'scope')));
        $scope = count($scopes) === 1 ? $scopes[0] : 'cafe';
        throw new GuestOrderEditException('service_unavailable', order_acceptance_message($scope), 409, ['items'=>$names,'scope'=>$scope]);
    }
    if ($priceChanges) {
        throw new GuestOrderEditException('prices_changed', 'قیمت یکی از آیتم‌هایی که می‌خواهی بیشتر کنی تغییر کرده؛ سفارش را تازه کن و دوباره انتخاب کن.', 409, ['items'=>array_values(array_unique($priceChanges))]);
    }
    return ['lines'=>$lines,'total'=>$total];
}

/** True when a retried full-replacement request already equals the persisted mutable order. */
function guest_order_payload_matches_current(array $payload, array $order, array $existingRows): bool
{
    if (trim((string)($order['customer_note'] ?? '')) !== trim((string)($payload['customer_note'] ?? ''))) return false;
    $current = [];
    foreach ($existingRows as $row) {
        $id = (int)($row['item_id'] ?? 0);
        if ($id < 1) return false;
        $mode=normalize_fulfillment_mode((string)($row['fulfillment_mode'] ?? 'dine_in'));
        $current[$id.'|'.$mode] = [
            'quantity'=>(int)($row['quantity'] ?? 0),
            'note'=>trim((string)($row['item_note'] ?? '')),
            'unit_price'=>(int)($row['unit_price'] ?? 0),
        ];
    }
    if (count($current) !== count($payload['items'] ?? [])) return false;
    foreach (($payload['items'] ?? []) as $line) {
        $id=(int)($line['id'] ?? 0);
        $mode=normalize_fulfillment_mode((string)($line['fulfillment_mode'] ?? 'dine_in'));
        $row=$current[$id.'|'.$mode] ?? null;
        if (!$row || (int)$line['quantity'] !== $row['quantity'] || trim((string)$line['note']) !== $row['note']) return false;
        if ($line['expected_price'] !== null && (int)$line['expected_price'] !== $row['unit_price']) return false;
    }
    return true;
}

function replace_mutable_order(PDO $pdo, int $orderId, array $payload, array $validated): void
{
    $pdo->prepare('DELETE FROM order_items WHERE order_id=?')->execute([$orderId]);
    $insert = $pdo->prepare('INSERT INTO order_items(order_id,item_id,item_name,unit_price,quantity,ordered_quantity,item_note,fulfillment_mode,preparation_station,line_total) VALUES(?,?,?,?,?,?,?,?,?,?)');
    foreach ($validated['lines'] as $line) {
        $insert->execute([$orderId,$line['item_id'],$line['item_name'],$line['unit_price'],$line['quantity'],$line['quantity'],$line['item_note'],$line['fulfillment_mode'],$line['station'],$line['line_total']]);
    }
    $pdo->prepare('UPDATE orders SET customer_note=?,total_amount=?,updated_at=NOW() WHERE id=?')
        ->execute([$payload['customer_note'], $validated['total'], $orderId]);
}

$pdo = db();
try {
    if ($action === 'list') {
        $table = guest_order_table($tableToken);
        if (!$table) json_response(['success'=>false,'code'=>'invalid_qr','message'=>customer_message('invalid_qr')],404);
        $session = guest_order_session((int)$table['id'], $sessionToken);
        json_response(['success'=>true,'session_status'=>$session['status'] ?? null,'orders'=>$session ? guest_order_rows((int)$session['id'],$deviceToken) : []]);
    }

    if (!in_array($action, ['update','cancel'], true)) {
        json_response(['success'=>false,'message'=>'عملیات سفارش معتبر نیست.'],422);
    }

    $orderCode = trim((string)($data['order_code'] ?? ''));
    if ($orderCode === '' || strlen($orderCode) > 32) json_response(['success'=>false,'message'=>'سفارش معتبر نیست.'],422);

    $pdo->beginTransaction();
    $table = guest_order_table($tableToken, true);
    if (!$table) throw new GuestOrderEditException('invalid_qr', customer_message('invalid_qr'), 404);

    // Resolve identity first, then lock in the canonical table -> session -> order order.
    $identityStmt = $pdo->prepare('SELECT id,session_id FROM orders WHERE public_code=? AND table_id=? AND device_token=? LIMIT 1');
    $identityStmt->execute([$orderCode,(int)$table['id'],$deviceToken]);
    $identity = $identityStmt->fetch();
    if (!$identity) throw new GuestOrderEditException('order_not_found','این سفارش روی همین دستگاه پیدا نشد.',404);

    $sessionStmt = $pdo->prepare('SELECT * FROM table_sessions WHERE id=? AND table_id=? LIMIT 1 FOR UPDATE');
    $sessionStmt->execute([(int)$identity['session_id'],(int)$table['id']]);
    $session = $sessionStmt->fetch() ?: null;
    if (!$session) throw new GuestOrderEditException('session_inactive','نشست این میز پیدا نشد.',409);
    if ($sessionToken !== '' && !hash_equals((string)$session['public_token'], $sessionToken)) {
        throw new GuestOrderEditException('session_inactive','نشست این میز تغییر کرده؛ صفحه را تازه کن.',409);
    }

    $orderStmt = $pdo->prepare('SELECT * FROM orders WHERE id=? AND table_id=? AND device_token=? LIMIT 1 FOR UPDATE');
    $orderStmt->execute([(int)$identity['id'],(int)$table['id'],$deviceToken]);
    $order = $orderStmt->fetch();
    if (!$order) throw new GuestOrderEditException('order_not_found','این سفارش روی همین دستگاه پیدا نشد.',404);

    if ($action === 'cancel') {
        if ((string)$order['status'] === 'cancelled') {
            $pdo->commit();
            json_response(['success'=>true,'status'=>'cancelled','status_label'=>order_status_label('cancelled'),'duplicate'=>true,'message'=>'این سفارش قبلاً لغو شده است.']);
        }
        if (!guest_order_status_is_mutable((string)$order['status'])) {
            throw new GuestOrderEditException('order_not_editable','این سفارش تأیید شده و دیگر لغو مستقیم ندارد.',409);
        }
        $pdo->prepare("UPDATE orders SET status='cancelled',updated_at=NOW() WHERE id=?")->execute([(int)$order['id']]);
        $pdo->prepare("INSERT INTO order_status_history(order_id,from_status,to_status,actor_user_id) VALUES(?,?,'cancelled',NULL)")
            ->execute([(int)$order['id'],(string)$order['status']]);
        $remaining = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE session_id=? AND status IN('pending_approval','new')");
        $remaining->execute([(int)$session['id']]);
        if ((string)$session['status'] === 'pending' && (int)$remaining->fetchColumn() === 0) {
            // Use the canonical session-close owner so waiter calls/guards are closed too.
            close_table_session((int)$session['id'], null, 'guest_cancelled');
        }
        $pdo->commit();
        json_response(['success'=>true,'status'=>'cancelled','status_label'=>order_status_label('cancelled'),'message'=>'سفارش لغو شد.']);
    }

    if (!in_array((string)$session['status'], ['active','pending'], true)) {
        throw new GuestOrderEditException('session_inactive','نشست این میز پایان یافته است.',409);
    }
    if (!guest_order_status_is_mutable((string)$order['status'])) {
        throw new GuestOrderEditException('order_not_editable','این سفارش تأیید شده و دیگر ویرایش مستقیم ندارد.',409);
    }

    $existingStmt = $pdo->prepare('SELECT item_id,item_name,unit_price,quantity,item_note,preparation_station,line_total FROM order_items WHERE order_id=? ORDER BY id FOR UPDATE');
    $existingStmt->execute([(int)$order['id']]);
    $existingRows = $existingStmt->fetchAll();
    $payload = normalize_order_request_payload(array_merge($data, [
        'table_token'=>$tableToken,
        'session_token'=>(string)$session['public_token'],
        'device_token'=>$deviceToken,
        'client_token'=>(string)$order['client_token'],
    ]));
    $expectedSignature = trim((string)($data['expected_signature'] ?? ''));
    $currentSignature = guest_order_edit_signature($order, $existingRows);
    if ($expectedSignature === '' || !hash_equals($currentSignature, $expectedSignature)) {
        // A response can be lost after commit. Full replacement makes that retry safe:
        // if the intended final state is already persisted, report success instead of
        // forcing the guest to guess whether the first request was applied.
        if (guest_order_payload_matches_current($payload,$order,$existingRows)) {
            $pdo->commit();
            json_response([
                'success'=>true,'idempotent'=>true,'updated'=>true,
                'order_code'=>(string)$order['public_code'],
                'order_number'=>order_display_number($order),
                'client_token'=>(string)$order['client_token'],
                'status'=>(string)$order['status'],
                'status_label'=>order_status_label((string)$order['status']),
                'edit_signature'=>$currentSignature,
                'message'=>'این تغییرات قبلاً ذخیره شده‌اند.',
            ]);
        }
        throw new GuestOrderEditException('order_changed','این سفارش در جای دیگری تغییر کرده؛ فهرست سفارش را تازه کن و دوباره ویرایش کن.',409,['current_signature'=>$currentSignature]);
    }

    $validated = validate_guest_order_update_lines($pdo,$payload,$existingRows);
    replace_mutable_order($pdo,(int)$order['id'],$payload,$validated);
    $pdo->commit();

    json_response([
        'success'=>true,
        'order_code'=>(string)$order['public_code'],
        'order_number'=>order_display_number($order),
        'client_token'=>(string)$order['client_token'],
        'status'=>(string)$order['status'],
        'status_label'=>order_status_label((string)$order['status']),
        'updated'=>true,
        'message'=>'تغییرات سفارش ذخیره شد.',
    ]);
} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_response(['success'=>false,'code'=>'invalid_order','message'=>$e->getMessage()],422);
} catch (GuestOrderEditException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_response(array_merge(['success'=>false,'code'=>$e->errorCode,'message'=>$e->getMessage()],$e->details),$e->httpStatus);
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $code = $e->getCode();
    $status = is_int($code) && $code >= 400 && $code <= 599 ? $code : 409;
    json_response(['success'=>false,'message'=>$e->getMessage()],$status);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('guest_orders: ' . $e->getMessage());
    json_response(['success'=>false,'message'=>customer_message('order_failed')],500);
}
