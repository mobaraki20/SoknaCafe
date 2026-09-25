<?php
declare(strict_types=1);


function financial_period_bounds_for_date(string $date): array
{
    $time = strtotime($date);
    if ($time === false) throw new RuntimeException('تاریخ دوره مالی معتبر نیست.');
    [$jy] = gregorian_to_jalali((int)date('Y', $time), (int)date('n', $time), (int)date('j', $time));
    [$sy,$sm,$sd] = jalali_to_gregorian($jy, 1, 1);
    [$ey,$em,$ed] = jalali_to_gregorian($jy + 1, 1, 1);
    $end = (new DateTimeImmutable(sprintf('%04d-%02d-%02d', $ey, $em, $ed)))->modify('-1 day');
    return [
        'jalali_year' => $jy,
        'title' => 'سال مالی ' . fa_digits($jy),
        'start_date' => sprintf('%04d-%02d-%02d', $sy, $sm, $sd),
        'end_date' => $end->format('Y-m-d'),
    ];
}

/** Caller should already own a transaction when a new period may be created. */
function financial_period_for_date_locked(PDO $pdo, string $date, int $actorUserId = 0): array
{
    $day = date('Y-m-d', strtotime($date) ?: time());
    $stmt = $pdo->prepare('SELECT * FROM financial_periods WHERE ? BETWEEN start_date AND end_date ORDER BY id DESC LIMIT 1 FOR UPDATE');
    $stmt->execute([$day]);
    $period = $stmt->fetch();
    if ($period) return $period;
    $bounds = financial_period_bounds_for_date($day);
    $insert = $pdo->prepare("INSERT INTO financial_periods(title,start_date,end_date,status,opened_by_user_id) VALUES(?,?,?,'open',?)");
    try {
        $insert->execute([$bounds['title'],$bounds['start_date'],$bounds['end_date'],$actorUserId ?: null]);
    } catch (PDOException $e) {
        if ((string)$e->getCode() !== '23000') throw $e;
    }
    $stmt->execute([$day]);
    $period = $stmt->fetch();
    if (!$period) throw new RuntimeException('دوره مالی متناسب با تاریخ ساخته نشد.');
    return $period;
}

/** Caller owns a transaction. The row lock guarantees a unique sequential display number. */
function financial_period_issue_invoice_locked(PDO $pdo, string $issuedAt, int $actorUserId, string $prefix = 'I'): array
{
    // Financial documents created during the after-midnight tail belong to the
    // same operational day as the café shift, including around fiscal-year boundaries.
    $periodDate = preg_match('/\d{1,2}:\d{2}/', $issuedAt)
        ? (string)business_assignment($issuedAt)['business_date']
        : date('Y-m-d', strtotime($issuedAt) ?: time());
    $period = financial_period_for_date_locked($pdo, $periodDate, $actorUserId);
    if ((string)$period['status'] !== 'open') throw new RuntimeException('سال مالی مربوط به این فاکتور بسته شده است.');
    $sequence = max(1, (int)$period['next_invoice_sequence']);
    $pdo->prepare('UPDATE financial_periods SET next_invoice_sequence=? WHERE id=?')->execute([$sequence + 1,(int)$period['id']]);
    $bounds = financial_period_bounds_for_date((string)$period['start_date']);
    $prefix = strtoupper(trim($prefix));
    if (!in_array($prefix, ['I','R'], true)) throw new RuntimeException('پیشوند سند مالی معتبر نیست.');
    return [
        'period' => $period,
        'sequence' => $sequence,
        'invoice_number' => sprintf('%s-%d-%06d', $prefix, (int)$bounds['jalali_year'], $sequence),
    ];
}

function settlement_invoice_snapshot_locked(PDO $pdo, array $invoice, string $invoiceNumber, ?string $issuedAt = null): array
{
    $ids = array_map('intval', array_column((array)($invoice['orders'] ?? []), 'id'));
    if (!$ids) throw new RuntimeException('برای این تسویه، فاکتور تأییدشده‌ای وجود ندارد.');
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT oi.id,oi.item_name,oi.quantity,oi.unit_price,oi.line_total,oi.item_note,oi.tax_policy_snapshot,oi.tax_rate_bps_snapshot FROM order_items oi WHERE oi.order_id IN($ph) AND oi.quantity>0 ORDER BY oi.order_id,oi.id");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();
    $taxLines = tax_calculate_invoice_lines($rows, (int)($invoice['discount'] ?? 0));
    $byId = [];
    foreach ((array)$taxLines['lines'] as $line) $byId[(int)$line['id']] = $line;
    $items = [];
    foreach ($rows as $row) {
        $calc = $byId[(int)$row['id']] ?? [];
        $item = [
            'name' => (string)$row['item_name'],
            'quantity' => (int)$row['quantity'],
            'unit_price' => (int)$row['unit_price'],
            'line_total' => (int)$row['line_total'],
            'note' => trim((string)($row['item_note'] ?? '')) ?: null,
        ];
        if (!empty($invoice['tax_document_active'])) {
            $item += [
                'line_discount'=>(int)($calc['invoice_discount_amount'] ?? 0),
                'line_net'=>(int)($calc['invoice_net_amount'] ?? 0),
                'taxable_amount'=>(int)($calc['invoice_taxable_amount'] ?? 0),
                'tax_rate_bps'=>(int)($row['tax_rate_bps_snapshot'] ?? 0),
                'tax_amount'=>(int)($calc['invoice_tax_amount'] ?? 0),
                'line_final'=>(int)($calc['invoice_final_amount'] ?? 0),
            ];
        }
        $items[] = $item;
    }
    $snapshot = [
        'version' => !empty($invoice['tax_document_active']) ? 3 : 1,
        'number' => $invoiceNumber,
        'issued_at' => $issuedAt ?: date(DATE_ATOM),
        'table_name' => (string)($invoice['session']['table_name'] ?? ''),
        'subtotal' => (int)$invoice['subtotal'],
        'discount' => (int)$invoice['discount'],
        'total' => (int)$invoice['total'],
        'items' => $items,
    ];
    if (!empty($invoice['tax_document_active'])) {
        $snapshot['net'] = (int)($invoice['net'] ?? ((int)$invoice['subtotal'] - (int)$invoice['discount']));
        $snapshot['taxable'] = (int)($invoice['taxable'] ?? 0);
        $snapshot['tax'] = (int)($invoice['tax'] ?? 0);
    }
    return $snapshot;
}

/**
 * Settlement destinations are deliberately limited to the three real Sokna flows.
 * They are destinations, not payment methods.
 */
function settlement_destinations(): array
{
    return [
        'direct' => 'تسویه',
        'accommodation' => 'حساب اقامتگاه',
        'subscriber' => 'حساب مشتری',
    ];
}

function settlement_destination_label(?string $destination): string
{
    return settlement_destinations()[$destination ?? ''] ?? 'تسویه';
}

function settlement_status_label(string $status): string
{
    return ['completed'=>'ثبت‌شده','voided'=>'برگشت‌خورده','reversal'=>'سند برگشتی'][$status] ?? $status;
}

final class SettlementStateConflict extends RuntimeException {}

require_once __DIR__ . '/settlement_allocations.php';

function settlement_request_id(string $value): string
{
    $id = text_substr(preg_replace('/[^A-Za-z0-9._:-]/', '', trim($value)) ?? '', 0, 96);
    if (!preg_match('/^[A-Za-z0-9._:-]{8,96}$/', $id)) throw new RuntimeException('شناسه یکتای تسویه معتبر نیست؛ صفحه را تازه کن و دوباره تلاش کن.', 409);
    return $id;
}

/** Caller owns a transaction when $forUpdate is true. */
function settlement_find_request(PDO $pdo, string $requestId, bool $forUpdate = false): ?array
{
    $requestId = settlement_request_id($requestId);
    $sql = "SELECT sr.*,ts.table_id settlement_table_id FROM settlement_records sr JOIN table_sessions ts ON ts.id=sr.session_id WHERE sr.request_id=? LIMIT 1" . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$requestId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function settlement_assert_request_match(array $record, string $destination, int $tableId, ?string $requestFingerprint = null): void
{
    if ((string)($record['status'] ?? '') !== 'completed'
        || (string)($record['destination'] ?? '') !== $destination
        || (int)($record['settlement_table_id'] ?? 0) !== $tableId) {
        throw new RuntimeException('شناسه این درخواست قبلاً برای عملیات دیگری استفاده شده است؛ صفحه را تازه کن.', 409);
    }
    if ($requestFingerprint !== null && $requestFingerprint !== '') {
        $stored = strtolower(trim((string)($record['request_fingerprint'] ?? '')));
        if ($stored === '' || !hash_equals($stored, strtolower($requestFingerprint))) {
            throw new RuntimeException('شناسه این درخواست قبلاً با انتخاب دیگری استفاده شده است؛ حساب را تازه کن.', 409);
        }
    }
}

function settlement_result_from_record(array $record): array
{
    return [
        'settlement_id' => (int)$record['id'],
        'invoice_number' => (string)$record['invoice_number'],
        'financial_period_id' => (int)$record['financial_period_id'],
        'session_id' => (int)$record['session_id'],
        'destination' => (string)$record['destination'],
        'destination_label' => settlement_destination_label((string)$record['destination']),
        'settlement_kind' => (string)($record['settlement_kind'] ?? 'full'),
        'closes_session' => (int)($record['closes_session'] ?? 1) === 1,
        'subtotal_amount' => (int)$record['subtotal'],
        'discount_amount' => (int)$record['discount'],
        'taxable_amount' => (int)($record['taxable_amount'] ?? 0),
        'tax_amount' => (int)($record['tax_amount'] ?? 0),
        'total_amount' => (int)$record['total'],
        'remaining_subtotal' => (int)($record['remaining_subtotal'] ?? 0),
        'remaining_discount' => (int)($record['remaining_discount'] ?? 0),
        'remaining_tax' => (int)($record['remaining_tax'] ?? 0),
        'remaining_total' => (int)($record['remaining_total'] ?? 0),
        'completed_orders' => 0,
        'idempotent' => true,
    ];
}

function settlement_review_signature(array $session, array $orders, array $paidState = []): string
{
    $normalizedOrders=[];
    foreach($orders as $order){
        if((string)($order['status']??'')!=='accounted')continue;
        $items=[];
        foreach((array)($order['items']??[]) as $line){
            if((int)($line['quantity']??0)<=0)continue;
            $items[]=[
                'id'=>(int)($line['id']??0),
                'item_name'=>(string)($line['item_name']??''),
                'quantity'=>(int)($line['quantity']??0),
                'unit_price'=>(int)($line['unit_price']??0),
                'line_total'=>(int)($line['line_total']??0),
                'item_note'=>trim((string)($line['item_note']??'')),
                'fulfillment_mode'=>normalize_fulfillment_mode((string)($line['fulfillment_mode']??'dine_in')),
                'tax_policy_snapshot'=>(string)($line['tax_policy_snapshot']??'disabled'),
                'tax_rate_bps_snapshot'=>(int)($line['tax_rate_bps_snapshot']??0),
                'tax_rate_version_id'=>(int)($line['tax_rate_version_id']??0),
                'tax_item_policy_version_id'=>(int)($line['tax_item_policy_version_id']??0),
            ];
        }
        usort($items,static fn(array $a,array $b): int => $a['id']<=>$b['id']);
        $normalizedOrders[]=['id'=>(int)$order['id'],'total_amount'=>(int)($order['total_amount']??0),'items'=>$items];
    }
    usort($normalizedOrders,static fn(array $a,array $b): int => $a['id']<=>$b['id']);
    $payload=[
        'session_id'=>(int)($session['session_id']??$session['id']??0),
        'discount_type'=>(string)($session['discount_type']??''),
        'discount_value'=>(int)($session['discount_value']??0),
        'orders'=>$normalizedOrders,
    ];
    if ($paidState) {
        $paidQuantities = [];
        foreach ((array)($paidState['paid_quantities'] ?? []) as $itemId=>$quantity) {
            if ((int)$quantity > 0) $paidQuantities[(int)$itemId] = (int)$quantity;
        }
        ksort($paidQuantities, SORT_NUMERIC);
        $payload['paid_state'] = [
            'paid_quantities'=>$paidQuantities,
            'paid_subtotal'=>(int)($paidState['paid_subtotal'] ?? 0),
            'paid_discount'=>(int)($paidState['paid_discount'] ?? 0),
            'paid_taxable'=>(int)($paidState['paid_taxable'] ?? 0),
            'paid_tax'=>(int)($paidState['paid_tax'] ?? 0),
            'paid_total'=>(int)($paidState['paid_total'] ?? 0),
        ];
    }
    return hash('sha256',json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
}


/** Lock and calculate the one authoritative invoice for an active table session. */
function settlement_calculate_session_invoice_locked(PDO $pdo, int $sessionId, array $allowedStatuses = ['active']): array
{
    $sessionStmt = $pdo->prepare('SELECT s.*,t.name table_name FROM table_sessions s JOIN cafe_tables t ON t.id=s.table_id WHERE s.id=? LIMIT 1 FOR UPDATE');
    $sessionStmt->execute([$sessionId]);
    $session = $sessionStmt->fetch();
    if (!$session) throw new RuntimeException('حساب میز پیدا نشد.');
    $allowedStatuses = array_values(array_unique(array_filter(array_map('strval', $allowedStatuses))));
    if (!$allowedStatuses) $allowedStatuses = ['active'];
    if (!in_array((string)$session['status'], $allowedStatuses, true)) throw new RuntimeException('این حساب دیگر در وضعیت قابل تسویه نیست.');

    $ordersStmt = $pdo->prepare("SELECT id,status,total_amount FROM orders WHERE session_id=? AND status IN('pending_approval','new','accounted') ORDER BY id FOR UPDATE");
    $ordersStmt->execute([$sessionId]);
    $orders = $ordersStmt->fetchAll();
    $unconfirmed = array_values(array_filter($orders, static fn(array $order): bool => in_array((string)$order['status'], ['pending_approval','new'], true)));
    if ($unconfirmed) throw new RuntimeException('یک یا چند سفارش تازه هنوز تأیید نشده؛ ابتدا صف سفارش‌ها را خالی کن.');
    $confirmed = array_values(array_filter($orders, static fn(array $order): bool => (string)$order['status'] === 'accounted'));
    if (!$confirmed) throw new RuntimeException('برای این میز حساب تأییدشده‌ای وجود ندارد.');

    $subtotal = array_sum(array_map(static fn(array $order): int => (int)$order['total_amount'], $confirmed));
    $discount = invoice_discount_amount($subtotal, (string)($session['discount_type'] ?? ''), (int)($session['discount_value'] ?? 0));
    $ids = array_map('intval', array_column($confirmed, 'id'));
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $lineStmt = $pdo->prepare("SELECT oi.id order_item_id,oi.order_id,oi.item_id,oi.item_name,oi.unit_price,oi.quantity,oi.line_total,oi.tax_policy_snapshot,oi.tax_rate_bps_snapshot,oi.tax_rate_version_id,oi.tax_item_policy_version_id FROM order_items oi WHERE oi.order_id IN($ph) AND oi.quantity>0 ORDER BY oi.id FOR UPDATE");
    $lineStmt->execute($ids);
    $lines = $lineStmt->fetchAll();
    if (!$lines) throw new RuntimeException('برای این میز قلم قابل تسویه‌ای وجود ندارد.');
    $calculated = tax_calculate_invoice_lines($lines, $discount);
    if ((int)$calculated['subtotal'] !== $subtotal) throw new SettlementStateConflict('جمع اقلام سفارش با مبلغ حساب هماهنگ نیست و باید بررسی شود.', 409);
    $taxDocumentActive = false;
    foreach ($lines as $line) {
        if ((string)($line['tax_policy_snapshot'] ?? 'disabled') !== 'disabled') { $taxDocumentActive = true; break; }
    }
    return [
        'session' => $session,
        'orders' => $confirmed,
        'subtotal' => $subtotal,
        'discount' => (int)$calculated['discount'],
        'net' => (int)$calculated['net'],
        'taxable' => (int)$calculated['taxable'],
        'tax' => (int)$calculated['tax'],
        'total' => (int)$calculated['total'],
        'tax_document_active'=>$taxDocumentActive,
        'tax_lines'=>(array)$calculated['lines'],
    ];
}

/** Caller owns the transaction. */
function settlement_record_create_locked(PDO $pdo, array $invoice, string $destination, int $actorUserId, array $context = []): int
{
    $periodId = (int)($context['financial_period_id'] ?? 0);
    $invoiceNumber = trim((string)($context['invoice_number'] ?? ''));
    $snapshot = $context['invoice_snapshot'] ?? null;
    if ($periodId < 1 || $invoiceNumber === '' || !is_array($snapshot)) throw new RuntimeException('شناسه دوره و اطلاعات نهایی فاکتور کامل نیست.');
    $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $requestId = trim((string)($context['request_id'] ?? ''));
    if ($requestId !== '') $requestId = settlement_request_id($requestId);
    $fingerprint = strtolower(trim((string)($context['request_fingerprint'] ?? '')));
    if ($fingerprint !== '' && !preg_match('/^[a-f0-9]{64}$/', $fingerprint)) throw new RuntimeException('اثر انگشت درخواست تسویه معتبر نیست.');
    $kind = (string)($context['settlement_kind'] ?? 'full');
    if (!in_array($kind, ['full','itemized'], true)) throw new RuntimeException('نوع سند تسویه معتبر نیست.');
    $closesSession = !empty($context['closes_session']);
    $allocationVersion = (int)($context['allocation_version'] ?? (!empty($invoice['tax_document_active']) ? 2 : 1));
    if (!in_array($allocationVersion,[1,2],true)) throw new RuntimeException('نسخه تخصیص سند تسویه معتبر نیست.');
    $settledAt = date('Y-m-d H:i:s');
    $business = business_assignment($settledAt);
    $stmt = $pdo->prepare("INSERT INTO settlement_records(session_id,financial_period_id,invoice_number,invoice_snapshot_json,destination,table_name_snapshot,subtotal,discount,taxable_amount,tax_amount,total,status,actor_user_id,subscriber_ledger_entry_id,accommodation_transfer_id,request_id,request_fingerprint,settlement_kind,closes_session,remaining_subtotal,remaining_discount,remaining_tax,remaining_total,allocation_version,settled_at,business_date,business_shift_key,business_shift_label,business_cutoff_snapshot) VALUES(?,?,?,?,?,?,?,?,?,?,?,'completed',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        (int)$invoice['session']['id'], $periodId, $invoiceNumber, $snapshotJson, $destination,
        (string)($invoice['session']['table_name'] ?? ''), (int)$invoice['subtotal'], (int)$invoice['discount'], (int)($invoice['taxable'] ?? 0), (int)($invoice['tax'] ?? 0), (int)$invoice['total'],
        $actorUserId,
        isset($context['subscriber_ledger_entry_id']) ? (int)$context['subscriber_ledger_entry_id'] : null,
        isset($context['accommodation_transfer_id']) ? (int)$context['accommodation_transfer_id'] : null,
        $requestId !== '' ? $requestId : null,
        $fingerprint !== '' ? $fingerprint : null,
        $kind,
        $closesSession ? 1 : 0,
        (int)($context['remaining_subtotal'] ?? 0),
        (int)($context['remaining_discount'] ?? 0),
        (int)($context['remaining_tax'] ?? 0),
        (int)($context['remaining_total'] ?? 0),
        $allocationVersion,
        $settledAt,(string)$business['business_date'],(string)$business['shift_key'],(string)$business['shift_label'],(string)$business['cutoff'],
    ]);
    return (int)$pdo->lastInsertId();
}

/** Print is a secondary side effect: failure must not rollback a valid financial settlement. */
function settlement_enqueue_final_print_best_effort_locked(PDO $pdo, int $settlementId, string $destination, int $actorUserId, bool $requested): array
{
    if (!$requested) return ['requested'=>false,'queued'=>false];
    $savepoint = 'settlement_print_side_effect';
    try {
        if (!$pdo->inTransaction()) throw new RuntimeException('settlement_print_requires_transaction');
        $pdo->exec('SAVEPOINT ' . $savepoint);
    } catch (Throwable $e) {
        error_log('settlement print skipped: savepoint_unavailable class=' . get_class($e));
        return ['requested'=>true,'queued'=>false,'reason'=>'savepoint_unavailable'];
    }
    try {
        $job = print_enqueue_final_invoice($pdo, $settlementId, $destination, $actorUserId);
        $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
        return ['requested'=>true] + $job;
    } catch (Throwable $e) {
        try {
            $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
            $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
        } catch (Throwable $rollbackError) {
            throw new RuntimeException('تراکنش تسویه پس از خطای زیرسامانه چاپ نیازمند تلاش دوباره است.', 0, $e);
        }
        error_log('settlement print enqueue failed: class=' . get_class($e));
        return ['requested'=>true,'queued'=>false,'reason'=>'enqueue_failed'];
    }
}

/**
 * Finalize one allocation payment. Caller owns the transaction and the account rows are locked.
 * Every new financial settlement uses the same immutable allocation-line model.
 */
function settlement_finalize_review_locked(PDO $pdo, array $account, array $review, string $destination, int $actorUserId, bool $printFinal = false, array $context = []): array
{
    if (!isset(settlement_destinations()[$destination])) throw new RuntimeException('مسیر تسویه معتبر نیست.');
    $session = $account['session'] ?? null;
    if (!is_array($session) || (int)($session['id'] ?? 0) < 1) throw new RuntimeException('حساب میز معتبر نیست.');
    $sessionId = (int)$session['id'];
    if (!empty($account['itemized_active']) && $destination !== 'direct') throw new SettlementStateConflict('پس از شروع پرداخت جداگانه، فقط تسویه مانده حساب مجاز است.', 409);
    if (!(array)($review['lines'] ?? [])) throw new RuntimeException('اقلام این پرداخت مشخص نشده است.');

    $requestedKind = (string)($context['settlement_kind'] ?? '');
    $kind = $requestedKind === 'itemized' || ($destination === 'direct' && (empty($review['closes_session']) || !empty($account['itemized_active']))) ? 'itemized' : 'full';
    $periodId = (int)($context['financial_period_id'] ?? 0);
    $invoiceNumber = trim((string)($context['invoice_number'] ?? ''));
    $snapshot = $context['invoice_snapshot'] ?? null;
    if ($periodId < 1 || $invoiceNumber === '') {
        $issued = financial_period_issue_invoice_locked($pdo, date('Y-m-d H:i:s'), $actorUserId);
        $periodId = (int)$issued['period']['id'];
        $invoiceNumber = (string)$issued['invoice_number'];
    }
    if (!is_array($snapshot) || $kind === 'itemized') $snapshot = settlement_payment_snapshot($account, $review, $invoiceNumber);

    $paymentInvoice = [
        'session'=>$account['session'],
        'orders'=>$account['orders'],
        'subtotal'=>(int)$review['subtotal'],
        'discount'=>(int)$review['discount'],
        'net'=>(int)($review['net'] ?? ((int)$review['subtotal']-(int)$review['discount'])),
        'taxable'=>(int)($review['taxable'] ?? 0),
        'tax'=>(int)($review['tax'] ?? 0),
        'total'=>(int)$review['total'],
        'tax_document_active'=>((int)($account['allocation_version'] ?? 1) === 2),
    ];
    $context['financial_period_id'] = $periodId;
    $context['invoice_number'] = $invoiceNumber;
    $context['invoice_snapshot'] = $snapshot;
    $context['settlement_kind'] = $kind;
    $context['closes_session'] = !empty($review['closes_session']);
    $context['remaining_subtotal'] = (int)$review['remaining_subtotal'];
    $context['remaining_discount'] = (int)$review['remaining_discount'];
    $context['remaining_tax'] = (int)($review['remaining_tax'] ?? 0);
    $context['remaining_total'] = (int)$review['remaining_total'];
    $context['allocation_version'] = (int)($account['allocation_version'] ?? 1);
    if (empty($context['request_fingerprint'])) {
        $context['request_fingerprint'] = settlement_request_fingerprint($sessionId,$destination,$kind === 'itemized' ? 'itemized' : 'full',(array)$review['selection']);
    }

    $settlementId = settlement_record_create_locked($pdo, $paymentInvoice, $destination, $actorUserId, $context);
    settlement_record_lines_create_locked($pdo, $settlementId, (array)$review['lines']);

    $completedOrders = 0;
    if (!empty($review['closes_session'])) {
        $complete = $pdo->prepare("UPDATE orders SET status='completed' WHERE id=? AND status='accounted'");
        $history = $pdo->prepare("INSERT INTO order_status_history(order_id,from_status,to_status,actor_user_id) VALUES(?,'accounted','completed',?)");
        foreach ($account['orders'] as $order) {
            $complete->execute([(int)$order['id']]);
            if ($complete->rowCount() > 0) { $history->execute([(int)$order['id'], $actorUserId]); $completedOrders++; }
        }
        $pdo->prepare("UPDATE table_sessions SET discount_amount=?,checkout_subtotal=?,checkout_discount=?,checkout_taxable=?,checkout_tax=?,checkout_total=?,settlement_destination=?,checkout_voided_at=NULL,checkout_voided_by_user_id=NULL WHERE id=?")
            ->execute([(int)$account['discount'], (int)$account['subtotal'], (int)$account['discount'], (int)($account['taxable'] ?? 0), (int)($account['tax'] ?? 0), (int)$account['total'], $destination, $sessionId]);
        close_table_session($sessionId, $actorUserId, 'checkout');
    } else {
        $pdo->prepare('UPDATE table_sessions SET updated_at=NOW() WHERE id=?')->execute([$sessionId]);
    }

    $printJob = settlement_enqueue_final_print_best_effort_locked($pdo, $settlementId, $destination, $actorUserId, $printFinal);
    if (!empty($printJob['job_id'])) $pdo->prepare('UPDATE settlement_records SET final_print_job_id=? WHERE id=?')->execute([(int)$printJob['job_id'], $settlementId]);

    audit_log_write_strict($pdo, 'settlement.completed', 'settlement_record', $settlementId, [
        'session_id'=>$sessionId,'table_name'=>(string)($account['session']['table_name'] ?? ''),'invoice_number'=>$invoiceNumber,'financial_period_id'=>$periodId,
        'destination'=>$destination,'settlement_kind'=>$kind,'closes_session'=>!empty($review['closes_session']),
        'subtotal'=>(int)$review['subtotal'],'discount'=>(int)$review['discount'],'taxable'=>(int)($review['taxable'] ?? 0),'tax'=>(int)($review['tax'] ?? 0),'total'=>(int)$review['total'],
        'remaining_tax'=>(int)($review['remaining_tax'] ?? 0),'remaining_total'=>(int)$review['remaining_total'],'line_count'=>count((array)$review['lines']),
        'allocation_version'=>(int)($account['allocation_version'] ?? 1),'print_requested'=>$printFinal,'print_queued'=>!empty($printJob['queued']) || !empty($printJob['duplicate']),'print_job_id'=>(int)($printJob['job_id'] ?? 0),
    ], $actorUserId);

    return [
        'settlement_id'=>$settlementId,'invoice_number'=>$invoiceNumber,'financial_period_id'=>$periodId,'session_id'=>$sessionId,'destination'=>$destination,
        'destination_label'=>settlement_destination_label($destination),'settlement_kind'=>$kind,'closes_session'=>!empty($review['closes_session']),
        'subtotal_amount'=>(int)$review['subtotal'],'discount_amount'=>(int)$review['discount'],'taxable_amount'=>(int)($review['taxable'] ?? 0),'tax_amount'=>(int)($review['tax'] ?? 0),'total_amount'=>(int)$review['total'],
        'remaining_subtotal'=>(int)$review['remaining_subtotal'],'remaining_discount'=>(int)$review['remaining_discount'],'remaining_tax'=>(int)($review['remaining_tax'] ?? 0),'remaining_total'=>(int)$review['remaining_total'],
        'completed_orders'=>$completedOrders,'print_job'=>$printJob,
        'print_warning'=>$printFinal && empty($printJob['queued']) && empty($printJob['duplicate']) ? 'تسویه ثبت شد؛ چاپ فاکتور انجام نشد. سند از سوابق قابل چاپ مجدد است.' : '',
    ];
}

/** Existing full-settlement contract now settles the authoritative remaining balance through allocation lines. */
function settlement_finalize_locked(PDO $pdo, array $invoice, string $destination, int $actorUserId, bool $printFinal = false, array $context = []): array
{
    $account = settlement_account_state_locked($pdo, $invoice);
    if (!empty($account['itemized_active']) && $destination !== 'direct') {
        throw new SettlementStateConflict('این حساب وارد پرداخت جداگانه شده و مقصد تسویه آن دیگر قابل تغییر نیست.', 409);
    }
    $review = settlement_review_all_remaining($account);
    return settlement_finalize_review_locked($pdo, $account, $review, $destination, $actorUserId, $printFinal, $context);
}

function settlement_finalize_itemized_locked(PDO $pdo, array $invoice, array $selection, int $actorUserId, bool $printFinal = false, array $context = []): array
{
    $account = settlement_account_state_locked($pdo, $invoice);
    $review = settlement_review_selection($account, $selection);
    $context['settlement_kind'] = 'itemized';
    return settlement_finalize_review_locked($pdo, $account, $review, 'direct', $actorUserId, $printFinal, $context);
}

function settlement_today(PDO $pdo, int $limit = 100): array
{
    $limit = max(1, min(300, $limit));
    $businessDate = business_current_date();
    $stmt = $pdo->prepare("SELECT sr.*,sr.table_name_snapshot table_name,u.display_name actor_name,vu.display_name voided_by_name,pj.status print_status,sub.name subscriber_name,at.guest_name_snapshot accommodation_guest,at.room_name_snapshot accommodation_room,
        CASE WHEN sr.status='completed' AND rev.id IS NOT NULL THEN 'voided' ELSE sr.status END effective_status,
        rev.id reversal_settlement_id,rev.invoice_number reversal_invoice_number,orig.invoice_number reverses_invoice_number
        FROM settlement_records sr
        JOIN table_sessions ts ON ts.id=sr.session_id
        LEFT JOIN settlement_records rev ON rev.reverses_settlement_id=sr.id AND rev.status='reversal'
        LEFT JOIN settlement_records orig ON orig.id=sr.reverses_settlement_id
        LEFT JOIN users u ON u.id=sr.actor_user_id
        LEFT JOIN users vu ON vu.id=sr.voided_by_user_id
        LEFT JOIN print_jobs pj ON pj.id=sr.final_print_job_id
        LEFT JOIN subscriber_ledger sl ON sl.id=sr.subscriber_ledger_entry_id
        LEFT JOIN subscribers sub ON sub.id=sl.subscriber_id
        LEFT JOIN accommodation_transfers at ON at.id=sr.accommodation_transfer_id
        WHERE sr.business_date=?
        ORDER BY sr.id DESC LIMIT {$limit}");
    $stmt->execute([$businessDate]);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $row['id'] = (int)$row['id'];
        $row['session_id'] = (int)$row['session_id'];
        $row['subtotal'] = (int)$row['subtotal'];
        $row['discount'] = (int)$row['discount'];
        $row['total'] = (int)$row['total'];
        $row['destination_label'] = settlement_destination_label((string)$row['destination']);
        $row['status'] = (string)($row['effective_status'] ?? $row['status']);
        $row['status_label'] = settlement_status_label((string)$row['status']);
        $row['settled_at_label'] = format_jalali_datetime((string)$row['settled_at'], false);
        $printLabels = print_job_status_labels();
        $row['print_status_label'] = !empty($row['print_status']) ? ($printLabels[(string)$row['print_status']] ?? 'وضعیت نامشخص') : 'چاپ نشده';
        $rows[] = $row;
    }
    return $rows;
}

function settlement_free_tables(PDO $pdo, int $excludeTableId = 0): array
{
    $stmt = $pdo->prepare("SELECT t.id,t.name,COALESCE(t.zone_label,'بدون دسته‌بندی') zone_label FROM cafe_tables t LEFT JOIN table_sessions s ON s.table_id=t.id AND s.status IN('active','pending') WHERE t.active=1 AND t.id<>? AND s.id IS NULL ORDER BY COALESCE(t.zone_label,''),t.sort_order,t.table_number,t.id");
    $stmt->execute([$excludeTableId]);
    return array_map(static fn(array $row): array => ['id'=>(int)$row['id'],'name'=>(string)$row['name'],'zone_label'=>(string)$row['zone_label']], $stmt->fetchAll());
}

/** Caller owns a transaction and must have locked the settlement/session/target rows. */
function settlement_reopen_locked(PDO $pdo, array $record, int $targetTableId, string $reason, int $actorUserId): array
{
    $reason = text_substr(trim($reason), 0, 300);
    if ($reason === '') throw new RuntimeException('دلیل ابطال را وارد کن.');

    $existingStmt = $pdo->prepare("SELECT id,invoice_number FROM settlement_records WHERE reverses_settlement_id=? AND status='reversal' LIMIT 1 FOR UPDATE");
    $existingStmt->execute([(int)$record['id']]);
    if ($existing = $existingStmt->fetch()) {
        return [
            'idempotent'=>true,
            'original_settlement_id'=>(int)$record['id'],
            'reversal_settlement_id'=>(int)$existing['id'],
            'reversal_invoice_number'=>(string)$existing['invoice_number'],
        ];
    }
    if ((string)$record['status'] === 'voided') {
        return ['idempotent'=>true,'original_settlement_id'=>(int)$record['id'],'legacy_void'=>true];
    }
    if ((string)$record['status'] !== 'completed') throw new RuntimeException('این تسویه قابل برگشت نیست.');

    $sessionStmt = $pdo->prepare('SELECT * FROM table_sessions WHERE id=? FOR UPDATE');
    $sessionStmt->execute([(int)$record['session_id']]);
    $session = $sessionStmt->fetch();
    if (!$session) throw new RuntimeException('حساب اصلی پیدا نشد.');

    $isItemizedAllocation = in_array((int)($record['allocation_version'] ?? 0), [1,2], true) && (string)($record['settlement_kind'] ?? '') === 'itemized';
    if ($isItemizedAllocation) {
        if (!in_array((string)$session['status'], ['active','closed'], true)) throw new RuntimeException('این حساب در وضعیت قابل برگشت رسید نیست.');
        $target = null;
        if ((string)$session['status'] === 'closed') {
            $targetStmt = $pdo->prepare('SELECT id,name FROM cafe_tables WHERE id=? AND active=1 FOR UPDATE');
            $targetStmt->execute([$targetTableId]);
            $target = $targetStmt->fetch();
            if (!$target) throw new RuntimeException('میز مقصد معتبر نیست.');
            $busyStmt = $pdo->prepare("SELECT id FROM table_sessions WHERE table_id=? AND status IN('active','pending') AND id<>? LIMIT 1 FOR UPDATE");
            $busyStmt->execute([$targetTableId,(int)$session['id']]);
            if ($busyStmt->fetchColumn()) throw new RuntimeException('میز مقصد اکنون اشغال است؛ میز آزاد دیگری انتخاب کن.');

            $pdo->prepare("UPDATE table_sessions SET table_id=?,status='active',live_table_guard=?,ended_at=NULL,ended_reason=NULL,closed_by_user_id=NULL,checkout_subtotal=NULL,checkout_discount=NULL,checkout_taxable=NULL,checkout_tax=NULL,checkout_total=NULL,settlement_destination=NULL,checkout_voided_at=NULL,checkout_voided_by_user_id=NULL,updated_at=NOW() WHERE id=? AND status='closed'")
                ->execute([$targetTableId,$targetTableId,(int)$session['id']]);
            $orders = $pdo->prepare("SELECT id FROM orders WHERE session_id=? AND status='completed' ORDER BY id FOR UPDATE");
            $orders->execute([(int)$session['id']]);
            $orderIds = array_map('intval', $orders->fetchAll(PDO::FETCH_COLUMN));
            if (!$orderIds) throw new RuntimeException('سفارش تکمیل‌شده‌ای برای بازکردن حساب پیدا نشد.');
            $restore = $pdo->prepare("UPDATE orders SET table_id=?,status='accounted' WHERE id=? AND session_id=? AND status='completed'");
            $history = $pdo->prepare("INSERT INTO order_status_history(order_id,from_status,to_status,actor_user_id) VALUES(?,'completed','accounted',?)");
            foreach ($orderIds as $orderId) {
                $restore->execute([$targetTableId,$orderId,(int)$session['id']]);
                if ($restore->rowCount()) $history->execute([$orderId,$actorUserId]);
            }
            $pdo->prepare("UPDATE waiter_calls SET table_id=?,active_table_guard=? WHERE session_id=? AND status IN('new','accepted')")
                ->execute([$targetTableId,$targetTableId,(int)$session['id']]);
        } else {
            $targetTableId = (int)$session['table_id'];
            $targetStmt = $pdo->prepare('SELECT id,name FROM cafe_tables WHERE id=? LIMIT 1');
            $targetStmt->execute([$targetTableId]);
            $target = $targetStmt->fetch();
            if (!$target) throw new RuntimeException('میز حساب پیدا نشد.');
        }

        $issued = financial_period_issue_invoice_locked($pdo, date('Y-m-d H:i:s'), $actorUserId, 'R');
        $snapshot = json_decode((string)$record['invoice_snapshot_json'], true);
        if (!is_array($snapshot)) $snapshot = [];
        $snapshot['number'] = (string)$issued['invoice_number'];
        $snapshot['issued_at'] = date(DATE_ATOM);
        $snapshot['document_type'] = 'reversal';
        $snapshot['reverses_invoice_number'] = (string)$record['invoice_number'];
        $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $reversalAt = date('Y-m-d H:i:s');
        $business = business_assignment($reversalAt);
        $insert = $pdo->prepare("INSERT INTO settlement_records(session_id,financial_period_id,invoice_number,invoice_snapshot_json,destination,table_name_snapshot,subtotal,discount,taxable_amount,tax_amount,total,status,reverses_settlement_id,actor_user_id,request_fingerprint,settlement_kind,closes_session,remaining_subtotal,remaining_discount,remaining_tax,remaining_total,allocation_version,void_reason,voided_by_user_id,voided_at,settled_at,business_date,business_shift_key,business_shift_label,business_cutoff_snapshot) VALUES(?,?,?,?,?,?,?,?,?,?,?,'reversal',?,?,?,'reversal',0,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $insert->execute([
            (int)$record['session_id'],(int)$issued['period']['id'],(string)$issued['invoice_number'],$snapshotJson,
            (string)$record['destination'],(string)$record['table_name_snapshot'],(int)$record['subtotal'],(int)$record['discount'],(int)($record['taxable_amount']??0),(int)($record['tax_amount']??0),(int)$record['total'],
            (int)$record['id'],$actorUserId,null,
            0,0,0,0,
            (int)($record['allocation_version']??1),$reason,$actorUserId,$reversalAt,$reversalAt,
            (string)$business['business_date'],(string)$business['shift_key'],(string)$business['shift_label'],(string)$business['cutoff'],
        ]);
        $reversalId = (int)$pdo->lastInsertId();
        $copyLines = $pdo->prepare("INSERT INTO settlement_record_lines(settlement_id,order_item_id,order_id,item_id_snapshot,item_name_snapshot,unit_price_snapshot,quantity,gross_amount,discount_amount,net_amount,taxable_amount,tax_rate_bps,tax_amount,final_amount) SELECT ?,order_item_id,order_id,item_id_snapshot,item_name_snapshot,unit_price_snapshot,quantity,gross_amount,discount_amount,net_amount,taxable_amount,tax_rate_bps,tax_amount,final_amount FROM settlement_record_lines WHERE settlement_id=? ORDER BY id");
        $copyLines->execute([$reversalId,(int)$record['id']]);
        if ($copyLines->rowCount() < 1) throw new RuntimeException('خطوط مالی رسید برای برگشت پیدا نشد.');
        $pdo->prepare("UPDATE settlement_records SET void_reason=?,voided_by_user_id=?,voided_at=NOW() WHERE id=? AND status='completed'")
            ->execute([$reason,$actorUserId,(int)$record['id']]);
        $pdo->prepare('UPDATE table_sessions SET updated_at=NOW() WHERE id=?')->execute([(int)$session['id']]);

        audit_log_write_strict($pdo, 'settlement.reversed', 'settlement_record', (int)$record['id'], [
            'reversal_settlement_id'=>$reversalId,
            'reversal_invoice_number'=>(string)$issued['invoice_number'],
            'session_id'=>(int)$session['id'],
            'destination'=>(string)$record['destination'],
            'target_table_id'=>$targetTableId,
            'itemized_exact_receipt'=>true,
            'reason'=>$reason,
        ], $actorUserId);
        return [
            'idempotent'=>false,
            'original_settlement_id'=>(int)$record['id'],
            'reversal_settlement_id'=>$reversalId,
            'reversal_invoice_number'=>(string)$issued['invoice_number'],
            'session_id'=>(int)$session['id'],
            'table_id'=>$targetTableId,
            'table_name'=>(string)$target['name'],
            'itemized_exact_receipt'=>true,
        ];
    }

    if ((string)$session['status'] !== 'closed') throw new RuntimeException('این حساب قبلاً باز شده است.');

    $targetStmt = $pdo->prepare('SELECT id,name FROM cafe_tables WHERE id=? AND active=1 FOR UPDATE');
    $targetStmt->execute([$targetTableId]);
    $target = $targetStmt->fetch();
    if (!$target) throw new RuntimeException('میز مقصد معتبر نیست.');
    $busyStmt = $pdo->prepare("SELECT id FROM table_sessions WHERE table_id=? AND status IN('active','pending') LIMIT 1 FOR UPDATE");
    $busyStmt->execute([$targetTableId]);
    if ($busyStmt->fetchColumn()) throw new RuntimeException('میز مقصد اکنون اشغال است؛ میز آزاد دیگری انتخاب کن.');

    // A reopened account receives a fresh technical session. This keeps the original
    // financial document immutable and gives a later accommodation charge a new
    // external_order_id instead of conflicting with the already voided snapshot.
    $newSession = create_table_session($targetTableId, $actorUserId, (int)$session['id'], null, 'active');
    $newSessionId = (int)$newSession['id'];
    $pdo->prepare('UPDATE table_sessions SET guest_count=?,note=?,discount_type=?,discount_value=?,discount_amount=?,discount_by_user_id=?,discount_updated_at=? WHERE id=?')
        ->execute([
            $session['guest_count'] !== null ? (int)$session['guest_count'] : null,
            $session['note'] ?: null,
            $session['discount_type'] ?: null,
            (int)($session['discount_value'] ?? 0),
            (int)($session['discount_amount'] ?? 0),
            $session['discount_by_user_id'] ? (int)$session['discount_by_user_id'] : null,
            $session['discount_updated_at'] ?: null,
            $newSessionId,
        ]);

    $orders = $pdo->prepare("SELECT id FROM orders WHERE session_id=? AND status='completed' ORDER BY id FOR UPDATE");
    $orders->execute([(int)$record['session_id']]);
    $orderIds = array_map('intval', $orders->fetchAll(PDO::FETCH_COLUMN));
    if (!$orderIds) throw new RuntimeException('سفارش تکمیل‌شده‌ای برای بازگرداندن حساب پیدا نشد.');
    $restore = $pdo->prepare("UPDATE orders SET table_id=?,session_id=?,status='accounted' WHERE id=? AND status='completed'");
    $history = $pdo->prepare("INSERT INTO order_status_history(order_id,from_status,to_status,actor_user_id) VALUES(?,'completed','accounted',?)");
    foreach ($orderIds as $orderId) {
        $restore->execute([$targetTableId,$newSessionId,$orderId]);
        if ($restore->rowCount()) $history->execute([$orderId,$actorUserId]);
    }
    $pdo->prepare('INSERT IGNORE INTO table_session_clients(session_id,device_token,first_seen_at,last_seen_at) SELECT ?,device_token,first_seen_at,last_seen_at FROM table_session_clients WHERE session_id=?')
        ->execute([$newSessionId,(int)$session['id']]);
    $pdo->prepare("UPDATE waiter_calls SET table_id=?,active_table_guard=?,session_id=? WHERE session_id=? AND status IN('new','accepted')")
        ->execute([$targetTableId,$targetTableId,$newSessionId,(int)$session['id']]);

    $pdo->prepare('UPDATE table_sessions SET checkout_voided_at=NOW(),checkout_voided_by_user_id=? WHERE id=?')
        ->execute([$actorUserId,(int)$session['id']]);

    $issued = financial_period_issue_invoice_locked($pdo, date('Y-m-d H:i:s'), $actorUserId, 'R');
    $snapshot = json_decode((string)$record['invoice_snapshot_json'], true);
    if (!is_array($snapshot)) $snapshot = [];
    $snapshot['number'] = (string)$issued['invoice_number'];
    $snapshot['issued_at'] = date(DATE_ATOM);
    $snapshot['document_type'] = 'reversal';
    $snapshot['reverses_invoice_number'] = (string)$record['invoice_number'];
    $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    $reversalAt = date('Y-m-d H:i:s');
    $business = business_assignment($reversalAt);
    $insert = $pdo->prepare("INSERT INTO settlement_records(session_id,financial_period_id,invoice_number,invoice_snapshot_json,destination,table_name_snapshot,subtotal,discount,taxable_amount,tax_amount,total,status,reverses_settlement_id,actor_user_id,subscriber_ledger_entry_id,accommodation_transfer_id,settlement_kind,closes_session,remaining_subtotal,remaining_discount,remaining_tax,remaining_total,allocation_version,void_reason,voided_by_user_id,voided_at,settled_at,business_date,business_shift_key,business_shift_label,business_cutoff_snapshot) VALUES(?,?,?,?,?,?,?,?,?,?,?,'reversal',?,?,?,?,'reversal',0,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $insert->execute([
        (int)$record['session_id'],(int)$issued['period']['id'],(string)$issued['invoice_number'],$snapshotJson,
        (string)$record['destination'],(string)$record['table_name_snapshot'],(int)$record['subtotal'],(int)$record['discount'],(int)($record['taxable_amount']??0),(int)($record['tax_amount']??0),(int)$record['total'],
        (int)$record['id'],$actorUserId,$record['subscriber_ledger_entry_id'] ? (int)$record['subscriber_ledger_entry_id'] : null,$record['accommodation_transfer_id'] ? (int)$record['accommodation_transfer_id'] : null,
        0,0,0,0,(int)($record['allocation_version']??0),
        $reason,$actorUserId,$reversalAt,$reversalAt,(string)$business['business_date'],(string)$business['shift_key'],(string)$business['shift_label'],(string)$business['cutoff'],
    ]);
    $reversalId = (int)$pdo->lastInsertId();
    $copyLines = $pdo->prepare("INSERT INTO settlement_record_lines(settlement_id,order_item_id,order_id,item_id_snapshot,item_name_snapshot,unit_price_snapshot,quantity,gross_amount,discount_amount,net_amount,taxable_amount,tax_rate_bps,tax_amount,final_amount) SELECT ?,order_item_id,order_id,item_id_snapshot,item_name_snapshot,unit_price_snapshot,quantity,gross_amount,discount_amount,net_amount,taxable_amount,tax_rate_bps,tax_amount,final_amount FROM settlement_record_lines WHERE settlement_id=? ORDER BY id");
    $copyLines->execute([$reversalId,(int)$record['id']]);

    // Financial values and status of the original stay untouched. Only audit metadata
    // is added; effective "voided" state is derived from the reversal document.
    $pdo->prepare('UPDATE settlement_records SET void_reason=?,voided_by_user_id=?,voided_at=NOW() WHERE id=? AND status=\'completed\'')
        ->execute([$reason,$actorUserId,(int)$record['id']]);

    audit_log_write_strict($pdo, 'settlement.reversed', 'settlement_record', (int)$record['id'], [
        'reversal_settlement_id'=>$reversalId,
        'reversal_invoice_number'=>(string)$issued['invoice_number'],
        'new_session_id'=>$newSessionId,
        'destination'=>(string)$record['destination'],
        'target_table_id'=>$targetTableId,
        'reason'=>$reason,
    ], $actorUserId);
    return [
        'idempotent'=>false,
        'original_settlement_id'=>(int)$record['id'],
        'reversal_settlement_id'=>$reversalId,
        'reversal_invoice_number'=>(string)$issued['invoice_number'],
        'session_id'=>$newSessionId,
        'table_id'=>$targetTableId,
        'table_name'=>(string)$target['name'],
    ];
}
