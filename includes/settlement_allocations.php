<?php
declare(strict_types=1);

/**
 * Finance-owned allocation model for itemized table settlement.
 *
 * The order itself remains immutable for payment purposes. Paid/remaining state
 * is derived only from immutable settlement records and settlement_record_lines.
 */

function settlement_selection_normalize(mixed $value): array
{
    if (!is_array($value) || !$value) throw new SettlementStateConflict('حداقل یک قلم برای پرداخت انتخاب کن.', 409);
    if (count($value) > 120) throw new SettlementStateConflict('تعداد ردیف‌های انتخاب‌شده بیش از حد مجاز است.', 409);
    $normalized = [];
    foreach ($value as $row) {
        if (!is_array($row)) throw new SettlementStateConflict('انتخاب اقلام معتبر نیست.', 409);
        $itemId = (int)($row['order_item_id'] ?? $row['id'] ?? 0);
        $quantity = (int)($row['quantity'] ?? 0);
        if ($itemId < 1 || $quantity < 1 || $quantity > 999) throw new SettlementStateConflict('تعداد یکی از اقلام معتبر نیست.', 409);
        if (isset($normalized[$itemId])) throw new SettlementStateConflict('یک ردیف فاکتور بیش از یک‌بار در انتخاب آمده است.', 409);
        $normalized[$itemId] = $quantity;
    }
    ksort($normalized, SORT_NUMERIC);
    return $normalized;
}

function settlement_request_fingerprint(int $sessionId, string $destination, string $mode, array $selection = []): string
{
    $selection = $selection ? settlement_selection_normalize(array_map(
        static fn(int $quantity, int $itemId): array => ['order_item_id'=>$itemId,'quantity'=>$quantity],
        $selection,
        array_keys($selection)
    )) : [];
    $payload = [
        'session_id'=>$sessionId,
        'destination'=>$destination,
        'mode'=>$mode,
        'selection'=>$selection,
    ];
    return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
}

function settlement_proportional_discount_target(int $subtotal, int $discount, int $cumulativeGross, bool $final): int
{
    if ($subtotal <= 0 || $discount <= 0 || $cumulativeGross <= 0) return 0;
    if ($final || $cumulativeGross >= $subtotal) return min($discount, $subtotal);
    $target = intdiv(($discount * $cumulativeGross) + intdiv($subtotal, 2), $subtotal);
    return max(0, min($discount, $target));
}

function settlement_allocate_line_discounts(array $lines, int $discount): array
{
    $grossTotal = array_sum(array_map(static fn(array $line): int => (int)$line['gross_amount'], $lines));
    if ($grossTotal <= 0) return array_map(static function(array $line): array { $line['discount_amount']=0;$line['net_amount']=(int)$line['gross_amount'];return $line; }, $lines);
    $discount = max(0, min($discount, $grossTotal));
    $runningGross = 0;
    $allocated = 0;
    $count = count($lines);
    foreach ($lines as $index => &$line) {
        $runningGross += (int)$line['gross_amount'];
        $lineDiscount = $index === $count - 1
            ? $discount - $allocated
            : max(0, settlement_proportional_discount_target($grossTotal, $discount, $runningGross, false) - $allocated);
        $lineDiscount = min((int)$line['gross_amount'], $lineDiscount);
        $line['discount_amount'] = $lineDiscount;
        $line['net_amount'] = (int)$line['gross_amount'] - $lineDiscount;
        $allocated += $lineDiscount;
    }
    unset($line);
    return $lines;
}

function settlement_session_has_active_itemized_locked(PDO $pdo, int $sessionId): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM settlement_records sr WHERE sr.session_id=? AND sr.status='completed' AND sr.allocation_version=1 AND sr.settlement_kind='itemized' AND NOT EXISTS(SELECT 1 FROM settlement_records rv WHERE rv.reverses_settlement_id=sr.id AND rv.status='reversal') LIMIT 1");
    $stmt->execute([$sessionId]);
    return (bool)$stmt->fetchColumn();
}

function settlement_assert_session_editable_locked(PDO $pdo, int $sessionId, string $operationLabel = 'این تغییر'): void
{
    if (settlement_session_has_active_itemized_locked($pdo, $sessionId)) {
        throw new SettlementStateConflict($operationLabel . ' پس از شروع پرداخت جداگانه قفل است؛ ابتدا مانده حساب را تسویه یا همه رسیدهای جداگانه را برگشت بده.', 409);
    }
}

function settlement_account_state_locked(PDO $pdo, array $invoice): array
{
    $session = (array)($invoice['session'] ?? []);
    $sessionId = (int)($session['id'] ?? 0);
    if ($sessionId < 1) throw new RuntimeException('حساب میز معتبر نیست.');
    $orders = (array)($invoice['orders'] ?? []);
    $orderIds = array_values(array_filter(array_map('intval', array_column($orders, 'id'))));
    if (!$orderIds) throw new RuntimeException('برای این حساب سفارش تأییدشده‌ای وجود ندارد.');

    $ph = implode(',', array_fill(0, count($orderIds), '?'));
    $itemStmt = $pdo->prepare("SELECT oi.id,oi.order_id,oi.item_id,oi.item_name,oi.unit_price,oi.quantity,oi.item_note,oi.fulfillment_mode,oi.preparation_station,oi.line_total FROM order_items oi WHERE oi.order_id IN($ph) AND oi.quantity>0 ORDER BY oi.order_id,oi.id FOR UPDATE");
    $itemStmt->execute($orderIds);
    $items = $itemStmt->fetchAll();
    if (!$items) throw new RuntimeException('برای این حساب قلم قابل تسویه‌ای وجود ندارد.');

    $paidStmt = $pdo->prepare("SELECT sl.order_item_id,SUM(sl.quantity) paid_quantity,SUM(sl.gross_amount) paid_gross,SUM(sl.discount_amount) paid_discount,SUM(sl.net_amount) paid_net
        FROM settlement_record_lines sl
        JOIN settlement_records sr ON sr.id=sl.settlement_id
        WHERE sr.session_id=? AND sr.status='completed' AND sr.allocation_version=1
          AND NOT EXISTS(SELECT 1 FROM settlement_records rv WHERE rv.reverses_settlement_id=sr.id AND rv.status='reversal')
        GROUP BY sl.order_item_id");
    $paidStmt->execute([$sessionId]);
    $paidByItem = [];
    foreach ($paidStmt->fetchAll() as $row) $paidByItem[(int)$row['order_item_id']] = $row;

    $totalsStmt = $pdo->prepare("SELECT COALESCE(SUM(sr.subtotal),0) paid_subtotal,COALESCE(SUM(sr.discount),0) paid_discount,COALESCE(SUM(sr.total),0) paid_total,COUNT(*) receipt_count
        FROM settlement_records sr
        WHERE sr.session_id=? AND sr.status='completed' AND sr.allocation_version=1
          AND NOT EXISTS(SELECT 1 FROM settlement_records rv WHERE rv.reverses_settlement_id=sr.id AND rv.status='reversal')");
    $totalsStmt->execute([$sessionId]);
    $paidTotals = $totalsStmt->fetch() ?: [];

    $remainingItems = [];
    $paidStateForSignature = [];
    foreach ($items as $item) {
        $itemId = (int)$item['id'];
        $ordered = (int)$item['quantity'];
        $paidQty = (int)($paidByItem[$itemId]['paid_quantity'] ?? 0);
        if ($paidQty < 0 || $paidQty > $ordered) throw new SettlementStateConflict('وضعیت پرداخت این حساب ناسازگار است و باید توسط مدیر بررسی شود.', 409);
        $remaining = $ordered - $paidQty;
        $item['paid_quantity'] = $paidQty;
        $item['remaining_quantity'] = $remaining;
        $item['remaining_line_total'] = (int)$item['unit_price'] * $remaining;
        $remainingItems[] = $item;
        if ($paidQty > 0) $paidStateForSignature[$itemId] = $paidQty;
    }

    $subtotal = (int)$invoice['subtotal'];
    $discount = (int)$invoice['discount'];
    $total = (int)$invoice['total'];
    $paidSubtotal = (int)($paidTotals['paid_subtotal'] ?? 0);
    $paidDiscount = (int)($paidTotals['paid_discount'] ?? 0);
    $paidTotal = (int)($paidTotals['paid_total'] ?? 0);
    if ($paidSubtotal > $subtotal || $paidDiscount > $discount || $paidTotal > $total) {
        throw new SettlementStateConflict('مجموع پرداخت‌های این حساب با مبلغ فاکتور سازگار نیست و باید بررسی شود.', 409);
    }
    $remainingSubtotal = max(0, $subtotal - $paidSubtotal);
    $remainingDiscount = max(0, $discount - $paidDiscount);
    $remainingTotal = max(0, $total - $paidTotal);
    $remainingGrossFromItems = array_sum(array_map(static fn(array $item): int => (int)$item['remaining_line_total'], $remainingItems));
    if ($remainingGrossFromItems !== $remainingSubtotal) {
        throw new SettlementStateConflict('مانده اقلام با مانده مالی حساب هماهنگ نیست و باید بررسی شود.', 409);
    }

    $ordersForSignature = $orders;
    $byOrder = [];
    foreach ($items as $item) $byOrder[(int)$item['order_id']][] = $item;
    foreach ($ordersForSignature as &$order) $order['items'] = $byOrder[(int)$order['id']] ?? [];
    unset($order);
    $signature = settlement_review_signature($session, $ordersForSignature, [
        'paid_quantities'=>$paidStateForSignature,
        'paid_subtotal'=>$paidSubtotal,
        'paid_discount'=>$paidDiscount,
        'paid_total'=>$paidTotal,
    ]);

    return $invoice + [
        'items'=>$remainingItems,
        'paid_subtotal'=>$paidSubtotal,
        'paid_discount'=>$paidDiscount,
        'paid_total'=>$paidTotal,
        'remaining_subtotal'=>$remainingSubtotal,
        'remaining_discount'=>$remainingDiscount,
        'remaining_total'=>$remainingTotal,
        'receipt_count'=>(int)($paidTotals['receipt_count'] ?? 0),
        'itemized_active'=>settlement_session_has_active_itemized_locked($pdo, $sessionId),
        'signature'=>$signature,
    ];
}

function settlement_assert_expected_account(array $account, int $expectedSessionId, int $expectedRemainingTotal, string $expectedSignature): void
{
    $sessionId = (int)($account['session']['id'] ?? 0);
    $remainingTotal = (int)($account['remaining_total'] ?? -1);
    $expectedSignature = strtolower(trim($expectedSignature));
    if ($expectedSessionId < 1 || $expectedRemainingTotal < 0 || !preg_match('/^[a-f0-9]{64}$/', $expectedSignature)) {
        throw new SettlementStateConflict('اطلاعات تأیید تسویه کامل نیست؛ حساب را دوباره باز کن.', 409);
    }
    if ($sessionId !== $expectedSessionId || $remainingTotal !== $expectedRemainingTotal || !hash_equals((string)$account['signature'], $expectedSignature)) {
        throw new SettlementStateConflict('حساب در این فاصله تغییر کرده است؛ ریز فاکتور و مانده جدید را دوباره بررسی و سپس تسویه کن.', 409);
    }
}

function settlement_review_selection(array $account, array $selection): array
{
    $selection = settlement_selection_normalize($selection);
    $itemsById = [];
    foreach ((array)$account['items'] as $item) $itemsById[(int)$item['id']] = $item;

    $lines = [];
    $selectedGross = 0;
    foreach ($selection as $itemId => $quantity) {
        $item = $itemsById[$itemId] ?? null;
        if (!$item || (int)$item['remaining_quantity'] < $quantity) {
            throw new SettlementStateConflict('یکی از اقلام انتخاب‌شده قبلاً پرداخت شده یا تعداد مانده آن تغییر کرده است؛ حساب را تازه کن.', 409);
        }
        $gross = (int)$item['unit_price'] * $quantity;
        $selectedGross += $gross;
        $lines[] = [
            'order_item_id'=>$itemId,
            'order_id'=>(int)$item['order_id'],
            'item_id_snapshot'=>$item['item_id'] !== null ? (int)$item['item_id'] : null,
            'item_name_snapshot'=>(string)$item['item_name'],
            'unit_price_snapshot'=>(int)$item['unit_price'],
            'quantity'=>$quantity,
            'gross_amount'=>$gross,
            'note'=>trim((string)($item['item_note'] ?? '')) ?: null,
        ];
    }
    if ($selectedGross <= 0) throw new SettlementStateConflict('مبلغ انتخاب‌شده معتبر نیست.', 409);

    $allRemaining = true;
    foreach ($itemsById as $itemId => $item) {
        $remaining = (int)$item['remaining_quantity'];
        if ($remaining <= 0) continue;
        if (($selection[$itemId] ?? 0) !== $remaining) { $allRemaining = false; break; }
    }
    $newPaidGross = (int)$account['paid_subtotal'] + $selectedGross;
    $targetDiscount = settlement_proportional_discount_target(
        (int)$account['subtotal'],
        (int)$account['discount'],
        $newPaidGross,
        $allRemaining
    );
    $selectedDiscount = max(0, $targetDiscount - (int)$account['paid_discount']);
    $selectedDiscount = min($selectedGross, $selectedDiscount);
    $lines = settlement_allocate_line_discounts($lines, $selectedDiscount);
    $selectedTotal = $selectedGross - $selectedDiscount;

    return [
        'selection'=>$selection,
        'lines'=>$lines,
        'subtotal'=>$selectedGross,
        'discount'=>$selectedDiscount,
        'total'=>$selectedTotal,
        'closes_session'=>$allRemaining,
        'remaining_subtotal'=>max(0, (int)$account['remaining_subtotal'] - $selectedGross),
        'remaining_discount'=>max(0, (int)$account['remaining_discount'] - $selectedDiscount),
        'remaining_total'=>max(0, (int)$account['remaining_total'] - $selectedTotal),
    ];
}

function settlement_review_all_remaining(array $account): array
{
    $selection = [];
    foreach ((array)$account['items'] as $item) {
        $remaining = (int)($item['remaining_quantity'] ?? 0);
        if ($remaining > 0) $selection[] = ['order_item_id'=>(int)$item['id'],'quantity'=>$remaining];
    }
    if (!$selection) throw new SettlementStateConflict('برای این حساب مانده‌ای جهت تسویه وجود ندارد.', 409);
    return settlement_review_selection($account, $selection);
}

function settlement_payment_snapshot(array $account, array $review, string $invoiceNumber, ?string $issuedAt = null): array
{
    $items = [];
    foreach ((array)$review['lines'] as $line) {
        $items[] = [
            'order_item_id'=>(int)$line['order_item_id'],
            'name'=>(string)$line['item_name_snapshot'],
            'quantity'=>(int)$line['quantity'],
            'unit_price'=>(int)$line['unit_price_snapshot'],
            'line_total'=>(int)$line['gross_amount'],
            'line_discount'=>(int)$line['discount_amount'],
            'line_net'=>(int)$line['net_amount'],
            'note'=>$line['note'] ?? null,
        ];
    }
    return [
        'version'=>2,
        'number'=>$invoiceNumber,
        'issued_at'=>$issuedAt ?: date(DATE_ATOM),
        'table_name'=>(string)($account['session']['table_name'] ?? ''),
        'account_subtotal'=>(int)$account['subtotal'],
        'account_discount'=>(int)$account['discount'],
        'account_total'=>(int)$account['total'],
        'paid_before'=>(int)$account['paid_total'],
        'subtotal'=>(int)$review['subtotal'],
        'discount'=>(int)$review['discount'],
        'total'=>(int)$review['total'],
        'remaining_after'=>(int)$review['remaining_total'],
        'items'=>$items,
    ];
}

function settlement_record_lines_create_locked(PDO $pdo, int $settlementId, array $lines): void
{
    if ($settlementId < 1 || !$lines) throw new RuntimeException('خطوط سند تسویه کامل نیست.');
    $stmt = $pdo->prepare('INSERT INTO settlement_record_lines(settlement_id,order_item_id,order_id,item_id_snapshot,item_name_snapshot,unit_price_snapshot,quantity,gross_amount,discount_amount,net_amount) VALUES(?,?,?,?,?,?,?,?,?,?)');
    foreach ($lines as $line) {
        $stmt->execute([
            $settlementId,
            (int)$line['order_item_id'],
            (int)$line['order_id'],
            $line['item_id_snapshot'] !== null ? (int)$line['item_id_snapshot'] : null,
            (string)$line['item_name_snapshot'],
            (int)$line['unit_price_snapshot'],
            (int)$line['quantity'],
            (int)$line['gross_amount'],
            (int)$line['discount_amount'],
            (int)$line['net_amount'],
        ]);
    }
}
