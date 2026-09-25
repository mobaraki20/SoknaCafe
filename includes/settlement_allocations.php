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
    $stmt = $pdo->prepare("SELECT 1 FROM settlement_records sr WHERE sr.session_id=? AND sr.status='completed' AND sr.allocation_version IN(1,2) AND sr.settlement_kind='itemized' AND NOT EXISTS(SELECT 1 FROM settlement_records rv WHERE rv.reverses_settlement_id=sr.id AND rv.status='reversal') LIMIT 1");
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
    $itemStmt = $pdo->prepare("SELECT oi.id,oi.order_id,oi.item_id,oi.item_name,oi.unit_price,oi.quantity,oi.item_note,oi.fulfillment_mode,oi.preparation_station,oi.line_total,oi.tax_policy_snapshot,oi.tax_rate_bps_snapshot,oi.tax_rate_version_id,oi.tax_item_policy_version_id FROM order_items oi WHERE oi.order_id IN($ph) AND oi.quantity>0 ORDER BY oi.order_id,oi.id FOR UPDATE");
    $itemStmt->execute($orderIds);
    $items = $itemStmt->fetchAll();
    if (!$items) throw new RuntimeException('برای این حساب قلم قابل تسویه‌ای وجود ندارد.');

    $allocationVersion = !empty($invoice['tax_document_active']) ? 2 : 1;
    $activeVersionsStmt = $pdo->prepare("SELECT DISTINCT sr.allocation_version FROM settlement_records sr WHERE sr.session_id=? AND sr.status='completed' AND sr.allocation_version IN(1,2) AND NOT EXISTS(SELECT 1 FROM settlement_records rv WHERE rv.reverses_settlement_id=sr.id AND rv.status='reversal')");
    $activeVersionsStmt->execute([$sessionId]);
    foreach ($activeVersionsStmt->fetchAll(PDO::FETCH_COLUMN) as $version) {
        if ((int)$version !== $allocationVersion) throw new SettlementStateConflict('نسخه محاسبه پرداخت‌های این حساب با وضعیت فعلی سازگار نیست؛ ابتدا اسناد قبلی را بررسی کنید.', 409);
    }

    $fullCalc = tax_calculate_invoice_lines($items, (int)$invoice['discount']);
    $fullById = [];
    foreach ((array)$fullCalc['lines'] as $line) $fullById[(int)($line['order_item_id'] ?? $line['id'] ?? 0)] = $line;

    $paidStmt = $pdo->prepare("SELECT sl.order_item_id,SUM(sl.quantity) paid_quantity,SUM(sl.gross_amount) paid_gross,SUM(sl.discount_amount) paid_discount,SUM(sl.net_amount) paid_net,SUM(sl.taxable_amount) paid_taxable,SUM(sl.tax_amount) paid_tax,SUM(sl.final_amount) paid_final
        FROM settlement_record_lines sl JOIN settlement_records sr ON sr.id=sl.settlement_id
        WHERE sr.session_id=? AND sr.status='completed' AND sr.allocation_version=?
          AND NOT EXISTS(SELECT 1 FROM settlement_records rv WHERE rv.reverses_settlement_id=sr.id AND rv.status='reversal') GROUP BY sl.order_item_id");
    $paidStmt->execute([$sessionId,$allocationVersion]);
    $paidByItem = [];
    foreach ($paidStmt->fetchAll() as $row) $paidByItem[(int)$row['order_item_id']] = $row;

    $totalsStmt = $pdo->prepare("SELECT COALESCE(SUM(sr.subtotal),0) paid_subtotal,COALESCE(SUM(sr.discount),0) paid_discount,COALESCE(SUM(sr.taxable_amount),0) paid_taxable,COALESCE(SUM(sr.tax_amount),0) paid_tax,COALESCE(SUM(sr.total),0) paid_total,COUNT(*) receipt_count
        FROM settlement_records sr WHERE sr.session_id=? AND sr.status='completed' AND sr.allocation_version=?
          AND NOT EXISTS(SELECT 1 FROM settlement_records rv WHERE rv.reverses_settlement_id=sr.id AND rv.status='reversal')");
    $totalsStmt->execute([$sessionId,$allocationVersion]);
    $paidTotals = $totalsStmt->fetch() ?: [];

    $remainingItems = []; $paidStateForSignature = [];
    foreach ($items as $item) {
        $itemId = (int)$item['id']; $ordered = (int)$item['quantity']; $paid = $paidByItem[$itemId] ?? [];
        $paidQty = (int)($paid['paid_quantity'] ?? 0);
        if ($paidQty < 0 || $paidQty > $ordered) throw new SettlementStateConflict('وضعیت پرداخت این حساب ناسازگار است و باید توسط مدیر بررسی شود.', 409);
        $remaining = $ordered - $paidQty;
        $calc = $fullById[$itemId] ?? [];
        $item += [
            'invoice_discount_amount'=>(int)($calc['invoice_discount_amount'] ?? 0),'invoice_net_amount'=>(int)($calc['invoice_net_amount'] ?? 0),
            'invoice_taxable_amount'=>(int)($calc['invoice_taxable_amount'] ?? 0),'invoice_tax_amount'=>(int)($calc['invoice_tax_amount'] ?? 0),'invoice_final_amount'=>(int)($calc['invoice_final_amount'] ?? 0),
        ];
        $item['paid_quantity']=$paidQty; $item['paid_discount_amount']=(int)($paid['paid_discount']??0); $item['paid_taxable_amount']=(int)($paid['paid_taxable']??0); $item['paid_tax_amount']=(int)($paid['paid_tax']??0); $item['paid_final_amount']=(int)($paid['paid_final']??0);
        $item['remaining_quantity']=$remaining; $item['remaining_line_total']=(int)$item['unit_price']*$remaining;
        $remainingItems[]=$item; if($paidQty>0)$paidStateForSignature[$itemId]=$paidQty;
    }

    $subtotal=(int)$invoice['subtotal']; $discount=(int)$invoice['discount']; $taxable=(int)($invoice['taxable']??0); $tax=(int)($invoice['tax']??0); $total=(int)$invoice['total'];
    $paidSubtotal=(int)($paidTotals['paid_subtotal']??0); $paidDiscount=(int)($paidTotals['paid_discount']??0); $paidTaxable=(int)($paidTotals['paid_taxable']??0); $paidTax=(int)($paidTotals['paid_tax']??0); $paidTotal=(int)($paidTotals['paid_total']??0);
    if($paidSubtotal>$subtotal||$paidDiscount>$discount||$paidTaxable>$taxable||$paidTax>$tax||$paidTotal>$total) throw new SettlementStateConflict('مجموع پرداخت‌های این حساب با مبلغ فاکتور سازگار نیست و باید بررسی شود.',409);
    $remainingSubtotal=max(0,$subtotal-$paidSubtotal); $remainingDiscount=max(0,$discount-$paidDiscount); $remainingTaxable=max(0,$taxable-$paidTaxable); $remainingTax=max(0,$tax-$paidTax); $remainingTotal=max(0,$total-$paidTotal);
    $remainingGrossFromItems=array_sum(array_map(static fn(array $item):int=>(int)$item['remaining_line_total'],$remainingItems));
    if($remainingGrossFromItems!==$remainingSubtotal) throw new SettlementStateConflict('مانده اقلام با مانده مالی حساب هماهنگ نیست و باید بررسی شود.',409);

    $ordersForSignature=$orders; $byOrder=[]; foreach($items as $item)$byOrder[(int)$item['order_id']][]=$item; foreach($ordersForSignature as &$order)$order['items']=$byOrder[(int)$order['id']]??[]; unset($order);
    $signature=settlement_review_signature($session,$ordersForSignature,['paid_quantities'=>$paidStateForSignature,'paid_subtotal'=>$paidSubtotal,'paid_discount'=>$paidDiscount,'paid_taxable'=>$paidTaxable,'paid_tax'=>$paidTax,'paid_total'=>$paidTotal]);

    return $invoice + [
        'items'=>$remainingItems,'allocation_version'=>$allocationVersion,
        'paid_subtotal'=>$paidSubtotal,'paid_discount'=>$paidDiscount,'paid_taxable'=>$paidTaxable,'paid_tax'=>$paidTax,'paid_total'=>$paidTotal,
        'remaining_subtotal'=>$remainingSubtotal,'remaining_discount'=>$remainingDiscount,'remaining_taxable'=>$remainingTaxable,'remaining_tax'=>$remainingTax,'remaining_total'=>$remainingTotal,
        'receipt_count'=>(int)($paidTotals['receipt_count']??0),'itemized_active'=>settlement_session_has_active_itemized_locked($pdo,$sessionId),'signature'=>$signature,
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
    if ((int)($account['allocation_version'] ?? 1) === 2) return settlement_review_selection_tax_v2($account, $selection);
    $itemsById = [];
    foreach ((array)$account['items'] as $item) $itemsById[(int)$item['id']] = $item;
    $lines=[];$selectedGross=0;
    foreach($selection as $itemId=>$quantity){
        $item=$itemsById[$itemId]??null;
        if(!$item||(int)$item['remaining_quantity']<$quantity)throw new SettlementStateConflict('یکی از اقلام انتخاب‌شده قبلاً پرداخت شده یا تعداد مانده آن تغییر کرده است؛ حساب را تازه کن.',409);
        $gross=(int)$item['unit_price']*$quantity;$selectedGross+=$gross;
        $lines[]=['order_item_id'=>$itemId,'order_id'=>(int)$item['order_id'],'item_id_snapshot'=>$item['item_id']!==null?(int)$item['item_id']:null,'item_name_snapshot'=>(string)$item['item_name'],'unit_price_snapshot'=>(int)$item['unit_price'],'quantity'=>$quantity,'gross_amount'=>$gross,'note'=>trim((string)($item['item_note']??''))?:null];
    }
    if($selectedGross<=0)throw new SettlementStateConflict('مبلغ انتخاب‌شده معتبر نیست.',409);
    $allRemaining=true;foreach($itemsById as $itemId=>$item){$remaining=(int)$item['remaining_quantity'];if($remaining<=0)continue;if(($selection[$itemId]??0)!==$remaining){$allRemaining=false;break;}}
    $newPaidGross=(int)$account['paid_subtotal']+$selectedGross;
    $targetDiscount=settlement_proportional_discount_target((int)$account['subtotal'],(int)$account['discount'],$newPaidGross,$allRemaining);
    $selectedDiscount=max(0,$targetDiscount-(int)$account['paid_discount']);$selectedDiscount=min($selectedGross,$selectedDiscount);
    $lines=settlement_allocate_line_discounts($lines,$selectedDiscount);$selectedTotal=$selectedGross-$selectedDiscount;
    return ['selection'=>$selection,'lines'=>$lines,'subtotal'=>$selectedGross,'discount'=>$selectedDiscount,'net'=>$selectedTotal,'taxable'=>0,'tax'=>0,'total'=>$selectedTotal,'closes_session'=>$allRemaining,'remaining_subtotal'=>max(0,(int)$account['remaining_subtotal']-$selectedGross),'remaining_discount'=>max(0,(int)$account['remaining_discount']-$selectedDiscount),'remaining_taxable'=>0,'remaining_tax'=>0,'remaining_total'=>max(0,(int)$account['remaining_total']-$selectedTotal)];
}


function settlement_review_selection_tax_v2(array $account, array $selection): array
{
    $itemsById=[];foreach((array)$account['items'] as $item)$itemsById[(int)$item['id']]=$item;
    $lines=[];$subtotal=0;$discount=0;$taxable=0;$tax=0;$total=0;
    foreach($selection as $itemId=>$quantity){
        $item=$itemsById[$itemId]??null;
        if(!$item||(int)$item['remaining_quantity']<$quantity)throw new SettlementStateConflict('یکی از اقلام انتخاب‌شده قبلاً پرداخت شده یا تعداد مانده آن تغییر کرده است؛ حساب را تازه کن.',409);
        $ordered=(int)$item['quantity'];$paidQty=(int)$item['paid_quantity'];$newPaidQty=$paidQty+$quantity;
        $gross=(int)$item['unit_price']*$quantity;
        $fullDiscount=(int)$item['invoice_discount_amount'];
        $targetDiscount=tax_proportional_target($fullDiscount,$ordered,$newPaidQty,$newPaidQty===$ordered);
        $lineDiscount=max(0,$targetDiscount-(int)$item['paid_discount_amount']);
        $lineDiscount=min($gross,$lineDiscount);$net=$gross-$lineDiscount;
        $policy=(string)($item['tax_policy_snapshot']??'disabled');$rate=max(0,min(10000,(int)($item['tax_rate_bps_snapshot']??0)));
        $lineTaxable=($policy!=='disabled'&&$policy!=='exempt')?$net:0;
        $cumulativeGross=(int)$item['unit_price']*$newPaidQty;$cumulativeNet=$cumulativeGross-$targetDiscount;
        $targetTax=($policy!=='disabled'&&$policy!=='exempt')?tax_round_amount($cumulativeNet,$rate):0;
        $lineTax=max(0,$targetTax-(int)$item['paid_tax_amount']);$lineFinal=$net+$lineTax;
        $lines[]=['order_item_id'=>$itemId,'order_id'=>(int)$item['order_id'],'item_id_snapshot'=>$item['item_id']!==null?(int)$item['item_id']:null,'item_name_snapshot'=>(string)$item['item_name'],'unit_price_snapshot'=>(int)$item['unit_price'],'quantity'=>$quantity,'gross_amount'=>$gross,'discount_amount'=>$lineDiscount,'net_amount'=>$net,'taxable_amount'=>$lineTaxable,'tax_rate_bps'=>$rate,'tax_amount'=>$lineTax,'final_amount'=>$lineFinal,'note'=>trim((string)($item['item_note']??''))?:null];
        $subtotal+=$gross;$discount+=$lineDiscount;$taxable+=$lineTaxable;$tax+=$lineTax;$total+=$lineFinal;
    }
    if($subtotal<=0)throw new SettlementStateConflict('مبلغ انتخاب‌شده معتبر نیست.',409);
    $allRemaining=true;foreach($itemsById as $itemId=>$item){$remaining=(int)$item['remaining_quantity'];if($remaining<=0)continue;if(($selection[$itemId]??0)!==$remaining){$allRemaining=false;break;}}
    return ['selection'=>$selection,'lines'=>$lines,'subtotal'=>$subtotal,'discount'=>$discount,'net'=>$subtotal-$discount,'taxable'=>$taxable,'tax'=>$tax,'total'=>$total,'closes_session'=>$allRemaining,'remaining_subtotal'=>max(0,(int)$account['remaining_subtotal']-$subtotal),'remaining_discount'=>max(0,(int)$account['remaining_discount']-$discount),'remaining_taxable'=>max(0,(int)$account['remaining_taxable']-$taxable),'remaining_tax'=>max(0,(int)$account['remaining_tax']-$tax),'remaining_total'=>max(0,(int)$account['remaining_total']-$total)];
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
    $taxAware=(int)($account['allocation_version']??1)===2;$items=[];
    foreach((array)$review['lines'] as $line){
        $item=['order_item_id'=>(int)$line['order_item_id'],'name'=>(string)$line['item_name_snapshot'],'quantity'=>(int)$line['quantity'],'unit_price'=>(int)$line['unit_price_snapshot'],'line_total'=>(int)$line['gross_amount'],'line_discount'=>(int)$line['discount_amount'],'line_net'=>(int)$line['net_amount'],'note'=>$line['note']??null];
        if($taxAware)$item += ['taxable_amount'=>(int)($line['taxable_amount']??0),'tax_rate_bps'=>(int)($line['tax_rate_bps']??0),'tax_amount'=>(int)($line['tax_amount']??0),'line_final'=>(int)($line['final_amount']??$line['net_amount'])];
        $items[]=$item;
    }
    $snapshot=['version'=>$taxAware?3:2,'number'=>$invoiceNumber,'issued_at'=>$issuedAt?:date(DATE_ATOM),'table_name'=>(string)($account['session']['table_name']??''),'account_subtotal'=>(int)$account['subtotal'],'account_discount'=>(int)$account['discount'],'account_total'=>(int)$account['total'],'paid_before'=>(int)$account['paid_total'],'subtotal'=>(int)$review['subtotal'],'discount'=>(int)$review['discount'],'total'=>(int)$review['total'],'remaining_after'=>(int)$review['remaining_total'],'items'=>$items];
    if($taxAware)$snapshot += ['account_net'=>(int)($account['net']??((int)$account['subtotal']-(int)$account['discount'])),'account_taxable'=>(int)($account['taxable']??0),'account_tax'=>(int)($account['tax']??0),'paid_tax_before'=>(int)($account['paid_tax']??0),'net'=>(int)($review['net']??0),'taxable'=>(int)($review['taxable']??0),'tax'=>(int)($review['tax']??0),'remaining_tax_after'=>(int)($review['remaining_tax']??0)];
    return $snapshot;
}

function settlement_record_lines_create_locked(PDO $pdo, int $settlementId, array $lines): void
{
    if ($settlementId < 1 || !$lines) throw new RuntimeException('خطوط سند تسویه کامل نیست.');
    $stmt=$pdo->prepare('INSERT INTO settlement_record_lines(settlement_id,order_item_id,order_id,item_id_snapshot,item_name_snapshot,unit_price_snapshot,quantity,gross_amount,discount_amount,net_amount,taxable_amount,tax_rate_bps,tax_amount,final_amount) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach($lines as $line)$stmt->execute([$settlementId,(int)$line['order_item_id'],(int)$line['order_id'],$line['item_id_snapshot']!==null?(int)$line['item_id_snapshot']:null,(string)$line['item_name_snapshot'],(int)$line['unit_price_snapshot'],(int)$line['quantity'],(int)$line['gross_amount'],(int)$line['discount_amount'],(int)$line['net_amount'],(int)($line['taxable_amount']??0),(int)($line['tax_rate_bps']??0),(int)($line['tax_amount']??0),(int)($line['final_amount']??$line['net_amount'])]);
}
