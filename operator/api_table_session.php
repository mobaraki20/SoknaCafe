<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/maintenance.php';
maintenance_guard_json();
require_any_capability(['orders_floor','cashier_accounts']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false], 405);
}
$data = request_json();
if (!csrf_valid($data['csrf_token'] ?? null)) {
    json_response(['success' => false, 'message' => 'صفحه رو تازه کن.'], 419);
}
if (!table_sessions_enabled()) {
    json_response(['success' => false, 'message' => 'نشست میزها خاموشه.'], 409);
}

$action = (string)($data['action'] ?? '');
$requestId = text_substr(preg_replace('/[^A-Za-z0-9._:-]/','',trim((string)($data['request_id'] ?? ''))) ?: bin2hex(random_bytes(8)),0,64);
$tableId = (int)($data['table_id'] ?? 0);
$targetId = (int)($data['target_table_id'] ?? 0);
$expectedSessionId = (int)($data['expected_session_id'] ?? 0);
$expectedTotal = (int)($data['expected_total'] ?? -1);
$expectedSignature = strtolower(trim((string)($data['expected_signature'] ?? '')));
$itemSelection = $data['items'] ?? [];
$requestFingerprint = null;
$user = current_user();
$canHandleOrders = user_has_capability('orders_floor', $user);
$canHandleAccounts = user_has_capability('cashier_accounts', $user);
if (in_array($action, ['move','close'], true) && !$canHandleOrders) {
    json_response(['success'=>false,'message'=>'مسئولیت «سفارش و سالن» برای این کار فعال نیست.'],403);
}
if (in_array($action, ['print_prebill','checkout_direct','checkout_itemized_review','checkout_itemized'], true) && !$canHandleAccounts) {
    json_response(['success'=>false,'message'=>'مسئولیت «صندوق و حساب» برای این کار فعال نیست.'],403);
}
if ($tableId < 1) {
    json_response(['success' => false, 'message' => 'میز درست انتخاب نشده.'], 422);
}

$userId = (int)$user['id'];
$pdo = db();
try {
    $pdo->beginTransaction();

    $target = null;
    if ($action === 'move') {
        if ($targetId < 1 || $targetId === $tableId) throw new RuntimeException('میز مقصد درست انتخاب نشده.');
        $lockIds = [$tableId, $targetId];
        sort($lockIds, SORT_NUMERIC);
        $tableStmt = $pdo->prepare('SELECT id,name FROM cafe_tables WHERE id IN(?,?) AND active=1 ORDER BY id FOR UPDATE');
        $tableStmt->execute($lockIds);
        $lockedTables = [];
        foreach ($tableStmt->fetchAll() as $lockedTable) $lockedTables[(int)$lockedTable['id']] = $lockedTable;
        $table = $lockedTables[$tableId] ?? null;
        $target = $lockedTables[$targetId] ?? null;
        if (!$table) throw new RuntimeException('میز مبدأ پیدا نشد.');
        if (!$target) throw new RuntimeException('میز مقصد پیدا نشد.');
    } else {
        $tableStmt = $pdo->prepare('SELECT id,name FROM cafe_tables WHERE id=? AND active=1 FOR UPDATE');
        $tableStmt->execute([$tableId]);
        $table = $tableStmt->fetch();
        if (!$table) throw new RuntimeException('میز پیدا نشد.');
    }

    if ($action === 'checkout_direct' || $action === 'checkout_itemized') {
        if ($expectedSessionId > 0) {
            if ($action === 'checkout_itemized') {
                $normalizedForFingerprint = settlement_selection_normalize($itemSelection);
                $requestFingerprint = settlement_request_fingerprint($expectedSessionId, 'direct', 'itemized', $normalizedForFingerprint);
            } else {
                $requestFingerprint = settlement_request_fingerprint($expectedSessionId, 'direct', 'full_remaining');
            }
        }
        $existingSettlement = settlement_find_request($pdo, $requestId, true);
        if ($existingSettlement) {
            settlement_assert_request_match($existingSettlement, 'direct', $tableId, $requestFingerprint);
            $pdo->commit();
            json_response([
                'success'=>true,'persisted'=>true,'idempotent'=>true,'request_id'=>$requestId,
                'table_id'=>$tableId,'session_id'=>(int)$existingSettlement['session_id'],
                'message'=>'این پرداخت قبلاً با همین درخواست ثبت شده است.',
            ] + settlement_result_from_record($existingSettlement));
        }
    }

    $activeStmt = $pdo->prepare("SELECT * FROM table_sessions WHERE table_id=? AND status IN('active','pending') ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $activeStmt->execute([$tableId]);
    $session = $activeStmt->fetch() ?: null;

    if ($action === 'open') throw new RuntimeException('ثبت حضور دستی از سامانه حذف شده است؛ نخستین سفارش نشست میز را خودکار می‌سازد.');

    if (!$session) throw new RuntimeException('این میز نشست فعالی نداره.');
    if ($action === 'print_prebill') {
        if (($session['status'] ?? '') !== 'active') throw new RuntimeException('اول حضور و سفارش مهمان را تأیید کن.');
        if (!print_destination_ready($pdo, 'customer_receipt')) throw new RuntimeException('پرینتر فاکتور هنوز از بخش «چاپ و پرینترها» فعال نشده است.');
        $ordersStmt = $pdo->prepare("SELECT id,status FROM orders WHERE session_id=? AND status IN('pending_approval','new','accounted') ORDER BY id FOR UPDATE");
        $ordersStmt->execute([(int)$session['id']]);
        $sessionOrders = $ordersStmt->fetchAll();
        foreach ($sessionOrders as $order) if (in_array((string)$order['status'], ['pending_approval','new'], true)) throw new RuntimeException('یک سفارش تازه هنوز تأیید نشده؛ اول صف سفارش‌ها را خالی کن.');
        if (!array_filter($sessionOrders, static fn(array $order): bool => (string)$order['status'] === 'accounted')) throw new RuntimeException('برای این میز حساب تأییدشده‌ای وجود ندارد.');
        if (accommodation_transfer_blocks_invoice_edit((int)$session['id'])) throw new RuntimeException('این حساب در فرایند انتقال اقامت است و از این مسیر چاپ نمی‌شود.');
        settlement_assert_session_editable_locked($pdo, (int)$session['id'], 'چاپ صورتحساب کامل');
        $job = print_enqueue_prebill($pdo, (int)$session['id'], $userId, $requestId);
        $pdo->commit();
        json_response(['success'=>true,'persisted'=>true,'message'=>!empty($job['duplicate'])?'این درخواست قبلاً وارد صف چاپ شده است؛ Job موجود نمایش داده می‌شود.':'صورتحساب وارد صف چاپ شد؛ سفارش‌گیری میز همچنان باز است.','print_job'=>$job,'request_id'=>$requestId,'session_id'=>(int)$session['id'],'table_id'=>$tableId]);
    }


    if (in_array($action, ['checkout_itemized_review','checkout_itemized','checkout_direct'], true)) {
        if (($session['status'] ?? '') !== 'active') throw new RuntimeException('اول حضور و سفارش مهمان را تأیید کن.');
        $pendingAdjustmentIds = preparation_adjustments_pending_ids($pdo, (int)$session['id'], true);
        $invoice = settlement_calculate_session_invoice_locked($pdo, (int)$session['id']);
        $account = settlement_account_state_locked($pdo, $invoice);
        settlement_assert_expected_account($account, $expectedSessionId, $expectedTotal, $expectedSignature);

        if ($action === 'checkout_itemized_review') {
            $review = settlement_review_selection($account, $itemSelection);
            $pdo->commit();
            json_response([
                'success'=>true,
                'persisted'=>false,
                'table_id'=>$tableId,
                'session_id'=>(int)$session['id'],
                'review'=>[
                    'subtotal'=>(int)$review['subtotal'],
                    'discount'=>(int)$review['discount'],
                    'taxable'=>(int)($review['taxable']??0),
                    'tax'=>(int)($review['tax']??0),
                    'total'=>(int)$review['total'],
                    'remaining_subtotal'=>(int)$review['remaining_subtotal'],
                    'remaining_discount'=>(int)$review['remaining_discount'],
                    'remaining_total'=>(int)$review['remaining_total'],
                    'closes_session'=>!empty($review['closes_session']),
                    'lines'=>array_map(static fn(array $line): array => [
                        'order_item_id'=>(int)$line['order_item_id'],
                        'name'=>(string)$line['item_name_snapshot'],
                        'quantity'=>(int)$line['quantity'],
                        'unit_price'=>(int)$line['unit_price_snapshot'],
                        'gross_amount'=>(int)$line['gross_amount'],
                        'discount_amount'=>(int)$line['discount_amount'],
                        'net_amount'=>(int)$line['net_amount'],
                        'taxable_amount'=>(int)($line['taxable_amount']??0),
                        'tax_rate_bps'=>(int)($line['tax_rate_bps']??0),
                        'tax_amount'=>(int)($line['tax_amount']??0),
                        'final_amount'=>(int)($line['final_amount']??$line['net_amount']),
                    ], (array)$review['lines']),
                ],
            ]);
        }

        $printFinal = bool_from_mixed($data['print_final'] ?? false);
        accommodation_resolve_failed_for_alternate_settlement_locked($pdo,(int)$session['id'],$userId,'direct_settlement');

        if ($action === 'checkout_itemized') {
            if ($requestFingerprint === null) {
                $normalizedForFingerprint = settlement_selection_normalize($itemSelection);
                $requestFingerprint = settlement_request_fingerprint((int)$session['id'], 'direct', 'itemized', $normalizedForFingerprint);
            }
            $result = settlement_finalize_itemized_locked($pdo, $invoice, $itemSelection, $userId, $printFinal, [
                'request_id'=>$requestId,
                'request_fingerprint'=>$requestFingerprint,
            ]);
        } else {
            $requestFingerprint ??= settlement_request_fingerprint((int)$session['id'], 'direct', 'full_remaining');
            $result = settlement_finalize_locked($pdo, $invoice, 'direct', $userId, $printFinal, [
                'request_id'=>$requestId,
                'request_fingerprint'=>$requestFingerprint,
            ]);
        }

        if ($pendingAdjustmentIds) preparation_adjustments_audit_settlement_notice((int)$session['id'], $pendingAdjustmentIds, $userId, 'direct');
        $pdo->commit();
        $closed = !empty($result['closes_session']);
        $message = $closed
            ? 'پرداخت ' . toman((int)$result['total_amount']) . ' ثبت شد؛ حساب کامل شد و ' . $table['name'] . ' آزاد شد.'
            : 'پرداخت ' . toman((int)$result['total_amount']) . ' ثبت شد؛ مانده حساب ' . toman((int)$result['remaining_total']) . ' است و میز باز می‌ماند.';
        if ($printFinal) $message .= !empty($result['print_warning']) ? ' '.$result['print_warning'] : ' رسید این پرداخت وارد صف چاپ شد.';
        if ($pendingAdjustmentIds) $message .= ' اصلاحیه آماده‌سازی باز همچنان برای بار/آشپزخانه باقی مانده است.';
        json_response([
            'success'=>true,
            'persisted'=>true,
            'request_id'=>$requestId,
            'table_id'=>$tableId,
            'session_id'=>(int)$session['id'],
            'pending_preparation_adjustments'=>count($pendingAdjustmentIds),
            'message'=>$message,
        ] + $result);
    }

    if ($action === 'close') {
        $wasPending = ($session['status'] ?? '') === 'pending';
        if (!$wasPending) {
            $ordersStmt = $pdo->prepare("SELECT id FROM orders WHERE session_id=? AND status IN('pending_approval','new','accounted') ORDER BY id FOR UPDATE");
            $ordersStmt->execute([(int)$session['id']]);
            if ($ordersStmt->fetch()) {
                throw new RuntimeException('این میز حساب باز دارد؛ از دکمه «تسویه حساب» استفاده کن.');
            }
        }
        close_table_session((int)$session['id'], $userId, $wasPending ? 'pending_rejected' : 'empty_table');
        $pdo->commit();
        json_response([
            'success' => true,
            'persisted' => true,
            'request_id' => $requestId,
            'table_id' => $tableId,
            'session_id' => (int)$session['id'],
            'message' => $wasPending
                ? $table['name'] . ' رد و بسته شد؛ سفارش‌های منتظر هم لغو شدند.'
                : $table['name'] . ' بدون سفارش آزاد شد.',
        ]);
    }

    if ($action === 'move') {
        settlement_assert_session_editable_locked($pdo, (int)$session['id'], 'جابه‌جایی میز');
        if (accommodation_transfer_blocks_invoice_edit((int)$session['id'])) throw new RuntimeException('این حساب به اقامتگاه متصل است و تا تعیین تکلیف انتقال قابل جابه‌جایی نیست.');
        $targetActive = $pdo->prepare("SELECT id FROM table_sessions WHERE table_id=? AND status IN('active','pending') LIMIT 1 FOR UPDATE");
        $targetActive->execute([$targetId]);
        if ($targetActive->fetchColumn()) throw new RuntimeException('میز مقصد الان مهمان داره.');

        $sourceStatus = ($session['status'] ?? '') === 'pending' ? 'pending' : 'active';
        $pdo->prepare("UPDATE table_sessions SET status='closed',live_table_guard=NULL,ended_at=NOW(),ended_reason='moved',closed_by_user_id=? WHERE id=? AND status IN('active','pending')")
            ->execute([$userId, $session['id']]);
        $newSession = create_table_session(
            $targetId,
            $userId,
            (int)$session['id'],
            (string)$session['started_at'],
            $sourceStatus,
            [
                'business_date'=>$session['business_date'],
                'business_shift_key'=>$session['business_shift_key'],
                'business_shift_label'=>$session['business_shift_label'],
                'business_cutoff_snapshot'=>$session['business_cutoff_snapshot'],
            ]
        );
        $newSessionId = (int)$newSession['id'];
        $pdo->prepare('UPDATE table_sessions SET discount_type=?,discount_value=?,discount_amount=?,discount_by_user_id=?,discount_updated_at=? WHERE id=?')
            ->execute([
                $session['discount_type'] ?: null,
                (int)($session['discount_value'] ?? 0),
                (int)($session['discount_amount'] ?? 0),
                $session['discount_by_user_id'] ? (int)$session['discount_by_user_id'] : null,
                $session['discount_updated_at'] ?: null,
                $newSessionId,
            ]);
        $pdo->prepare("UPDATE orders SET table_id=?,session_id=? WHERE session_id=? AND status IN('pending_approval','new','accounted')")
            ->execute([$targetId, $newSessionId, $session['id']]);
        $pdo->prepare('UPDATE order_item_adjustments SET session_id=? WHERE session_id=?')
            ->execute([$newSessionId, $session['id']]);
        $pdo->prepare('UPDATE invoice_discount_audit SET session_id=? WHERE session_id=?')
            ->execute([$newSessionId, $session['id']]);
        $pdo->prepare("UPDATE waiter_calls SET table_id=?,active_table_guard=?,session_id=? WHERE session_id=? AND status IN('new','accepted')")
            ->execute([$targetId, $targetId, $newSessionId, $session['id']]);
        $pdo->prepare('INSERT IGNORE INTO table_session_clients(session_id,device_token,first_seen_at,last_seen_at) SELECT ?,device_token,first_seen_at,last_seen_at FROM table_session_clients WHERE session_id=?')
            ->execute([$newSessionId, $session['id']]);

        $pdo->commit();
        json_response([
            'success' => true,
            'persisted' => true,
            'request_id' => $requestId,
            'source_table_id' => $tableId,
            'target_table_id' => $targetId,
            'new_session_id' => $newSessionId,
            'message' => $sourceStatus === 'pending'
                ? 'درخواست حضور از ' . $table['name'] . ' به ' . $target['name'] . ' منتقل شد و همچنان منتظر تأیید است.'
                : 'مهمان از ' . $table['name'] . ' به ' . $target['name'] . ' منتقل شد؛ سفارش‌های باز هم همراهش جابه‌جا شدند.',
            'session' => $newSession,
        ]);
    }

    throw new RuntimeException('عملیات شناخته نشد.');
} catch (SettlementStateConflict $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_response(['success'=>false,'code'=>'settlement_changed','message'=>$e->getMessage(),'request_id'=>$requestId],409);
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_response(['success' => false, 'message' => $e->getMessage(), 'request_id'=>$requestId], $e->getCode()===409?409:422);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('table session action [' . $requestId . ']: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'این کار انجام نشد. کد پیگیری: ' . $requestId, 'request_id'=>$requestId], 500);
}
