<?php
declare(strict_types=1);

require_once __DIR__ . '/push.php';

final class StaffQuickOrderException extends RuntimeException {}

function staff_order_assert_permission(array $user, string $mode): void
{
    if ($mode === 'late_accounting') {
        if ((string)($user['role'] ?? '') !== 'admin' && !user_has_capability('cashier_accounts', $user)) {
            throw new StaffQuickOrderException('ثبت قلم جاافتاده فقط برای صندوق‌دار مجاز است.', 403);
        }
        return;
    }
    if (!staff_quick_order_allowed($user)) {
        throw new StaffQuickOrderException('دسترسی ثبت سفارش برای این حساب فعال نیست.', 403);
    }
}

/**
 * Canonical staff-order commit owner.
 *
 * Caller must own an open transaction. This function never commits/rolls back.
 * It revalidates all business state immediately before writing the order.
 */
function staff_order_commit_tx(PDO $pdo, array $data, array $user, string $mode = 'normal'): array
{
    if (!$pdo->inTransaction()) throw new LogicException('staff_order_commit_tx requires an open transaction.');
    $mode = $mode === 'late_accounting' ? 'late_accounting' : 'normal';
    staff_order_assert_permission($user, $mode);
    if (!table_sessions_enabled()) throw new StaffQuickOrderException('ثبت سریع در وضعیت فعلی سامانه در دسترس نیست.', 409);

    $userId = (int)($user['id'] ?? 0);
    if ($userId < 1) throw new StaffQuickOrderException('حساب کاربری معتبر نیست.', 403);

    $tableId = (int)($data['table_id'] ?? 0);
    $expectedSessionId = max(0, (int)($data['expected_session_id'] ?? 0));
    $note = text_substr(trim((string)($data['note'] ?? '')), 0, 500);
    try {
        $clientToken = normalize_staff_order_request_token($data['request_token'] ?? '');
        $rows = normalize_staff_quick_order_rows($data['items'] ?? null);
    } catch (InvalidArgumentException $e) {
        $code = in_array((int)$e->getCode(), [409,422], true) ? (int)$e->getCode() : 422;
        throw new StaffQuickOrderException($e->getMessage(), $code);
    }

    if ($tableId < 1) throw new StaffQuickOrderException('میز فعال پیدا نشد.', 404);

    $tableStmt = $pdo->prepare('SELECT id,name,table_number,code FROM cafe_tables WHERE id=? AND active=1 FOR UPDATE');
    $tableStmt->execute([$tableId]);
    $table = $tableStmt->fetch();
    if (!$table) throw new StaffQuickOrderException('میز فعال پیدا نشد.', 404);

    $duplicateStmt = $pdo->prepare("SELECT id,table_id,order_source,created_by_user_id FROM orders WHERE client_token=? LIMIT 1 FOR UPDATE");
    $duplicateStmt->execute([$clientToken]);
    if ($duplicate = $duplicateStmt->fetch()) {
        if ((int)$duplicate['table_id'] !== $tableId
            || (string)$duplicate['order_source'] !== 'staff'
            || (int)$duplicate['created_by_user_id'] !== $userId) {
            throw new StaffQuickOrderException('این شناسه ثبت سفارش قابل استفاده نیست؛ پنجره را ببندید و دوباره باز کنید.', 409);
        }
        return [
            'success'=>true,'duplicate'=>true,'message'=>'این سفارش قبلاً ثبت شده بود.',
            'order_id'=>(int)$duplicate['id'],'order_number'=>order_display_number((int)$duplicate['id']),
            'mode'=>$mode,'_after_commit_order_id'=>0,
        ];
    }

    $sessionStmt = $pdo->prepare("SELECT * FROM table_sessions WHERE table_id=? AND status IN('active','pending') ORDER BY FIELD(status,'active','pending'),id DESC LIMIT 1 FOR UPDATE");
    $sessionStmt->execute([$tableId]);
    $session = $sessionStmt->fetch() ?: null;
    $currentSessionId = $session ? (int)$session['id'] : 0;
    if ($expectedSessionId > 0) {
        if ($currentSessionId !== $expectedSessionId) {
            throw new StaffQuickOrderException('حساب این میز تغییر کرده است؛ صفحه را تازه کنید.', 409);
        }
    } elseif ($currentSessionId > 0) {
        throw new StaffQuickOrderException('وضعیت میز تغییر کرده است؛ صفحه را تازه کنید.', 409);
    }
    if (!$session) $session = create_table_session($tableId, $userId, null, null, 'active');
    $sessionId = (int)$session['id'];

    $itemizedActive = settlement_session_has_active_itemized_locked($pdo, $sessionId);
    if ($mode === 'late_accounting') {
        if (!$itemizedActive) throw new StaffQuickOrderException('این حساب در وضعیت تسویه جداگانه نیست؛ حساب را تازه کنید.', 409);
        if ($expectedSessionId < 1 || $expectedSessionId !== $sessionId) {
            throw new StaffQuickOrderException('حساب این میز تغییر کرده است؛ به صندوق برگردید و دوباره وارد ثبت قلم جاافتاده شوید.', 409);
        }
    } elseif ($itemizedActive) {
        throw new StaffQuickOrderException('پرداخت جداگانه این حساب شروع شده است؛ سفارش تازه قفل است. برای قلمی که قبلاً سرو شده ولی در حساب جا افتاده، از مسیر «افزودن قلم جاافتاده» در صندوق استفاده کنید.', 409);
    }
    if (accommodation_transfer_blocks_invoice_edit($sessionId)) {
        throw new StaffQuickOrderException('نتیجه ثبت حساب اقامتگاه هنوز مشخص نشده است و سفارش تازه پذیرفته نمی‌شود.', 409);
    }

    $pendingStmt = $pdo->prepare("SELECT id FROM orders WHERE session_id=? AND status IN('pending_approval','new') ORDER BY id FOR UPDATE");
    $pendingStmt->execute([$sessionId]);
    if ($pendingStmt->fetch()) {
        throw new StaffQuickOrderException('سفارش مهمان منتظر بررسی است؛ ابتدا آن را تأیید یا رد کنید.', 409);
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
            throw new StaffQuickOrderException('یکی از آیتم‌های انتخاب‌شده دیگر قابل سفارش نیست؛ فهرست را تازه کنید.', 409);
        }
        if (!order_catalog_item_allows_fulfillment($item,(string)$row['fulfillment_mode'])) {
            throw new StaffQuickOrderException('«'.(string)$item['name'].'» فقط داخل کافه قابل سرو است.', 409);
        }
        if ($mode === 'late_accounting' && normalize_fulfillment_mode((string)$row['fulfillment_mode']) !== 'dine_in') {
            throw new StaffQuickOrderException('قلم جاافتاده این میز فقط به‌صورت داخل کافه ثبت می‌شود.', 409);
        }
        $expectedPrice = $row['expected_price'] ?? null;
        if ($expectedPrice !== null && (int)$expectedPrice !== (int)$item['price']) {
            throw new StaffQuickOrderException('قیمت یکی از آیتم‌ها تغییر کرده است؛ فهرست را تازه و دوباره مرور کنید.', 409);
        }
    }

    $total = 0;
    $lines = [];
    foreach ($rows as $row) {
        $id=(int)$row['id'];$item=$itemsById[$id];
        $quantity=(int)$row['quantity'];$itemNote=(string)$row['note'];
        $lineTotal=(int)$item['price']*$quantity;$total+=$lineTotal;
        $tax = tax_order_line_snapshot($pdo,$id);
        $lines[] = [
            $id,(string)$item['name'],(int)$item['price'],$quantity,$itemNote,
            normalize_fulfillment_mode((string)$row['fulfillment_mode']),
            normalize_preparation_station((string)($item['preparation_station'] ?? 'cold_bar')),
            $lineTotal,normalize_sellable_kind($item['sellable_kind']??null),$tax,
        ];
    }

    $publicCode = strtoupper(bin2hex(random_bytes(8)));
    $business = business_assignment();
    $businessOrderNumber = order_allocate_business_number($pdo,(string)$business['business_date']);
    $insert = $pdo->prepare("INSERT INTO orders(public_code,client_token,device_token,table_id,session_id,order_source,status,customer_note,total_amount,accepted_at,accepted_by_user_id,created_by_user_id,business_order_number,business_date,business_shift_key,business_shift_label,business_cutoff_snapshot)
        VALUES(?,?,NULL,?,?, 'staff','accounted',?,?,NOW(),?,?,?,?,?,?,?)");
    $insert->execute([
        $publicCode,$clientToken,$tableId,$sessionId,$note,$total,$userId,$userId,$businessOrderNumber,
        (string)$business['business_date'],(string)$business['shift_key'],(string)$business['shift_label'],(string)$business['cutoff'],
    ]);
    $orderId = (int)$pdo->lastInsertId();

    $lineStmt = $pdo->prepare('INSERT INTO order_items(order_id,item_id,item_name,sellable_kind_snapshot,unit_price,quantity,ordered_quantity,item_note,fulfillment_mode,preparation_station,line_total,tax_policy_snapshot,tax_rate_bps_snapshot,tax_rate_version_id,tax_item_policy_version_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($lines as [$itemId,$name,$price,$quantity,$itemNote,$fulfillmentMode,$station,$lineTotal,$sellableKind,$tax]) {
        $lineStmt->execute([$orderId,$itemId,$name,$sellableKind,$price,$quantity,$quantity,$itemNote !== '' ? $itemNote : null,$fulfillmentMode,$station,$lineTotal,$tax['policy'],$tax['rate_bps'],$tax['rate_version_id'],$tax['policy_version_id']]);
    }
    $pdo->prepare("INSERT INTO order_status_history(order_id,from_status,to_status,actor_user_id) VALUES(?,NULL,'accounted',?)")
        ->execute([$orderId,$userId]);

    $hasPreparation = (bool)array_filter($lines, static fn(array $line): bool => preparation_station_requires_work((string)$line[6]));
    if ($mode !== 'late_accounting' && $hasPreparation) {
        print_enqueue_prep_order($pdo, $orderId, $userId);
        order_side_effect_best_effort_tx($pdo, 'push staff quick order', static function () use ($pdo,$table,$orderId,$user,$clientToken): void {
            push_enqueue_event_tx($pdo, 'order', [
                'title'=>'سفارش کارکنان برای ' . $table['name'],
                'body'=>order_display_label($orderId) . ' توسط ' . (string)$user['display_name'] . ' ثبت شد.',
                'url'=>asset('waiter/index.php'),
                'tag'=>'staff-order-' . $orderId,
                'order_id'=>$orderId,
            ], $clientToken);
        });
    }

    inventory_enqueue_order_event_tx(
        $pdo,'accounted',$orderId,$userId,
        ['late_accounting'=>$mode === 'late_accounting'],
        'inventory:order-accounted:'.$orderId
    );

    if ($mode === 'late_accounting') {
        audit_log_write_strict($pdo, 'order.late_accounting_created', 'order', $orderId, [
            'table_id'=>$tableId,'session_id'=>$sessionId,
            'quantity'=>array_sum(array_map(static fn(array $line): int => (int)$line[3], $lines)),
            'total'=>$total,'preparation_suppressed'=>true,'inventory_accounted'=>true,
            'request_token'=>$clientToken,
        ], $userId);
    }

    $message = $mode === 'late_accounting'
        ? 'قلم جاافتاده به حساب ' . $table['name'] . ' اضافه شد؛ برای آماده‌سازی دوباره ارسال نشد.'
        : 'سفارش ' . $table['name'] . ' ثبت شد.';

    return [
        'success'=>true,'duplicate'=>false,'message'=>$message,
        'order_id'=>$orderId,'order_number'=>$businessOrderNumber,'mode'=>$mode,
        '_after_commit_order_id'=>$orderId,
    ];
}

function staff_order_after_commit(array $result): void
{
    $orderId=(int)($result['_after_commit_order_id'] ?? 0);
    if($orderId>0) inventory_register_after_response_order($orderId);
}
