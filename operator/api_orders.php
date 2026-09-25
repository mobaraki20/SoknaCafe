<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_any_capability(['orders_floor','cashier_accounts','shift_supervision']);

$status = trim((string)($_GET['status'] ?? 'active'));
$tableId = (int)($_GET['table_id'] ?? 0);
$searchRaw = trim((string)($_GET['search'] ?? ''));
$search = function_exists('mb_substr') ? text_substr($searchRaw, 0, 100, 'UTF-8') : substr($searchRaw, 0, 100);
$clientSnapshot = trim((string)($_GET['snapshot'] ?? ''));
$accommodationLiveEnabled = accommodation_live_operations_enabled();
$accommodationCanPost = $accommodationLiveEnabled && user_has_capability('cashier_accounts');
$accommodationCanManage = is_admin() || user_has_capability('shift_supervision');
$accommodationVisible = user_has_capability('cashier_accounts') || $accommodationCanManage;
$apiWarnings = [];
$acceptanceStates = order_acceptance_states();
$acceptanceRevision = order_acceptance_revision();
$stationStates = station_busy_states();
$waiterEnabled = setting_bool('waiter_call_enabled', true);
$clientRevision = trim((string)($_GET['revision'] ?? ''));
$lightRevision = '';
try {
    $revisionRow = db()->query("SELECT
        (SELECT COALESCE(MAX(updated_at),'') FROM orders WHERE status IN('pending_approval','new','accounted')) orders_updated,
        (SELECT COUNT(*) FROM orders WHERE status IN('pending_approval','new','accounted')) orders_count,
        (SELECT COALESCE(MAX(updated_at),'') FROM table_sessions WHERE status IN('active','pending')) sessions_updated,
        (SELECT COUNT(*) FROM table_sessions WHERE status IN('active','pending')) sessions_count,
        (SELECT COALESCE(MAX(updated_at),'') FROM waiter_calls WHERE status IN('new','accepted')) calls_updated,
        (SELECT COUNT(*) FROM waiter_calls WHERE status IN('new','accepted')) calls_count,
        (SELECT COALESCE(MAX(updated_at),'') FROM preparation_adjustments WHERE status NOT IN('applied','cancelled')) adjustments_updated,
        (SELECT COUNT(*) FROM preparation_adjustments WHERE status NOT IN('applied','cancelled')) adjustments_count,
        (SELECT COALESCE(MAX(updated_at),'') FROM print_jobs WHERE status IN('pending','reserved','claimed','failed','unknown')) print_updated,
        (SELECT COUNT(*) FROM print_jobs WHERE status IN('pending','reserved','claimed','failed','unknown')) print_count,
        (SELECT COALESCE(MAX(updated_at),'') FROM accommodation_transfers) accommodation_updated,
        (SELECT COUNT(*) FROM accommodation_transfers) accommodation_count")->fetch() ?: [];
    $lightRevision = substr(hash('sha256', json_encode([$revisionRow,$acceptanceRevision,$stationStates,$waiterEnabled,$accommodationLiveEnabled], JSON_UNESCAPED_UNICODE)), 0, 24);
} catch (Throwable $revisionError) {
    error_log('operator revision: ' . $revisionError->getMessage());
}
if ($lightRevision !== '' && $clientRevision !== '' && hash_equals($lightRevision, $clientRevision)) {
    json_response([
        'success'=>true,'unchanged'=>true,'revision'=>$lightRevision,'server_time'=>date(DATE_ATOM),
        'order_acceptance'=>$acceptanceStates,'order_acceptance_revision'=>$acceptanceRevision,
        'waiter_enabled'=>$waiterEnabled,'station_states'=>$stationStates,
    ]);
}
try {
    $printingSummary = print_queue_summary();
} catch (Throwable $printingError) {
    // Printing is supplementary to the live board. A missing/partially migrated
    // print table must not take the whole operator workspace offline.
    error_log('operator live print summary: ' . $printingError->getMessage());
    $printingSummary = ['agent_online'=>false,'pending'=>0,'problem'=>0,'unavailable'=>true];
    $apiWarnings[] = 'وضعیت چاپ در دسترس نیست.';
}
$where = [];
$params = [];

if ($status === 'active') {
    // In the small-cafe flow, this board is only a confirmation queue.
    $where[] = "o.status IN('pending_approval','new')";
} elseif ($status !== 'all' && isset(order_statuses()[$status])) {
    $where[] = 'o.status=?';
    $params[] = $status;
}
if ($tableId > 0) {
    $where[] = 'o.table_id=?';
    $params[] = $tableId;
}
if ($search !== '') {
    $where[] = '(CAST(o.id AS CHAR) LIKE ? OR t.name LIKE ? OR t.code LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}

$sql = 'SELECT o.id,o.table_id,o.session_id,o.order_source,o.status,o.customer_note,o.total_amount,o.accepted_at,o.created_at,o.updated_at,t.name table_name,t.table_number,t.code table_code,t.zone_label,u.display_name accepted_by,cu.display_name created_by FROM orders o JOIN cafe_tables t ON t.id=o.table_id LEFT JOIN users u ON u.id=o.accepted_by_user_id LEFT JOIN users cu ON cu.id=o.created_by_user_id';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= $status === 'active'
    ? ' ORDER BY FIELD(o.status,"pending_approval","new"),o.created_at ASC LIMIT 150'
    : ' ORDER BY o.created_at DESC LIMIT 150';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

if ($orders) {
    $ids = array_map('intval', array_column($orders, 'id'));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $lineStmt = db()->prepare("SELECT order_id,item_name,unit_price,quantity,item_note,fulfillment_mode,preparation_station,line_total FROM order_items WHERE order_id IN($placeholders) ORDER BY id");
    $lineStmt->execute($ids);
    $grouped = [];
    foreach ($lineStmt->fetchAll() as $line) $grouped[(int)$line['order_id']][] = $line;
    foreach ($orders as &$order) {
        $order['items'] = $grouped[(int)$order['id']] ?? [];
        $order['order_number'] = order_display_number($order);
        $order['status_label'] = order_status_label((string)$order['status']);
        $order['time_ago'] = time_ago((string)$order['created_at']);
        $order['created_time'] = fa_digits(date('H:i', strtotime((string)$order['created_at'])));
        $order['waiting_minutes'] = max(0, (int)floor((time() - strtotime((string)$order['created_at'])) / 60));
        $order['allowed_statuses'] = allowed_order_statuses_from((string)$order['status']);
    }
    unset($order);
}

$attentionRows = db()->query("SELECT id FROM orders WHERE status IN('pending_approval','new') ORDER BY created_at DESC LIMIT 200")->fetchAll();
$attentionIds = array_map(static fn(array $row): string => (string)$row['id'], $attentionRows);

$callStmt = db()->query("SELECT w.id,w.table_id,w.session_id,w.status,w.created_at,w.accepted_at,w.updated_at,t.name table_name,t.table_number,t.zone_label,u.display_name accepted_by FROM waiter_calls w JOIN cafe_tables t ON t.id=w.table_id LEFT JOIN users u ON u.id=w.accepted_by_user_id WHERE w.status IN('new','accepted') ORDER BY w.created_at ASC");
$calls = $callStmt->fetchAll();
$callIds = [];
foreach ($calls as &$call) {
    $call['time_ago'] = time_ago((string)$call['created_at']);
    $call['waiting_minutes'] = max(0, (int)floor((time() - strtotime((string)$call['created_at'])) / 60));
    $callIds[] = (string)$call['id'];
}
unset($call);

$tables = [];
$currentBusinessDate = business_current_date();
$alertMinutes = max(5, (int)setting('new_device_alert_minutes', '20'));
$tableSql = "SELECT t.id,t.name,t.table_number,t.code,t.zone_label,t.sort_order,s.id session_id,s.status,s.public_token session_token,s.started_at,s.business_date,s.business_shift_key,s.business_shift_label,s.continued_from_session_id,s.updated_at,s.discount_type,s.discount_value,s.discount_amount,s.discount_updated_at,(SELECT display_name FROM users du WHERE du.id=s.discount_by_user_id) discount_by,
    (SELECT MAX(o.created_at) FROM orders o WHERE o.session_id=s.id AND o.status<>'cancelled') last_order_at,
    (SELECT COUNT(*) FROM table_session_clients sc WHERE sc.session_id=s.id) client_count,
    (SELECT MAX(sc.first_seen_at) FROM table_session_clients sc WHERE sc.session_id=s.id) latest_client_at,
    (SELECT COUNT(*) FROM waiter_calls wc WHERE wc.session_id=s.id AND wc.status IN('new','accepted')) active_call_count
    FROM cafe_tables t LEFT JOIN table_sessions s ON s.id=(SELECT MAX(s2.id) FROM table_sessions s2 WHERE s2.table_id=t.id AND s2.status IN ('active','pending')) WHERE t.active=1 ORDER BY t.sort_order,t.id";
foreach (db()->query($tableSql)->fetchAll() as $row) {
    $row['active'] = $row['session_id'] !== null;
    $row['pending_confirmation'] = (($row['status'] ?? '') === 'pending');
    $row['duration'] = setting_bool('show_visit_duration', true) && $row['started_at'] ? friendly_duration((string)$row['started_at']) : null;
    $row['carryover'] = $row['active'] && business_session_is_carryover($row, $currentBusinessDate);
    $sessionBusinessDate = $row['active'] ? (string)$row['business_date'] : '';
    $row['business_date_display'] = $sessionBusinessDate !== '' ? fa_digits(jalali_date_input($sessionBusinessDate)) : '';
    $row['last_order_ago'] = $row['last_order_at'] ? time_ago((string)$row['last_order_at']) : null;
    $late = false;
    if ($row['active'] && (int)$row['client_count'] > 1 && $row['latest_client_at']) {
        $alertAfter = strtotime((string)$row['started_at']) + $alertMinutes * 60;
        $late = strtotime((string)$row['latest_client_at']) > $alertAfter;
    }
    $row['late_device_alert'] = $late;
    $row['bill_orders'] = [];
    $row['bill_items'] = [];
    $row['bill_total'] = 0;
    $row['bill_order_count'] = 0;
    $row['unconfirmed_order_count'] = 0;
    $row['bill_subtotal'] = 0;
    $row['bill_discount'] = 0;
    $row['bill_net'] = 0;
    $row['bill_taxable'] = 0;
    $row['bill_tax'] = 0;
    $row['bill_final_total'] = 0;
    $row['bill_paid_subtotal'] = 0;
    $row['bill_paid_discount'] = 0;
    $row['bill_paid_taxable'] = 0;
    $row['bill_paid_tax'] = 0;
    $row['bill_paid_total'] = 0;
    $row['bill_remaining_subtotal'] = 0;
    $row['bill_remaining_discount'] = 0;
    $row['bill_remaining_taxable'] = 0;
    $row['bill_remaining_tax'] = 0;
    $row['bill_remaining_total'] = 0;
    $row['bill_paid_receipt_count'] = 0;
    $row['bill_itemized_active'] = false;
    $tables[] = $row;
}

$sessionIds = array_values(array_filter(array_map('intval', array_column($tables, 'session_id'))));
$billOrdersBySession = [];
$billItemsByOrder = [];
$accommodationBySession = [];
$pendingPreparationBySession = [];
$paidByOrderItem = [];
$paidTotalsBySession = [];
$itemizedActiveBySession = [];
if ($sessionIds) {
    $sessionPlaceholders = implode(',', array_fill(0, count($sessionIds), '?'));
    $prepStmt = db()->prepare("SELECT session_id,COUNT(*) pending_count FROM preparation_adjustments WHERE session_id IN($sessionPlaceholders) AND status NOT IN('applied','cancelled') GROUP BY session_id");
    $prepStmt->execute($sessionIds);
    foreach ($prepStmt->fetchAll() as $row) $pendingPreparationBySession[(int)$row['session_id']] = (int)$row['pending_count'];

    $paidLineStmt = db()->prepare("SELECT sr.session_id,sl.order_item_id,SUM(sl.quantity) paid_quantity,SUM(sl.gross_amount) paid_gross,SUM(sl.discount_amount) paid_discount,SUM(sl.net_amount) paid_net,SUM(sl.taxable_amount) paid_taxable,SUM(sl.tax_amount) paid_tax,SUM(sl.final_amount) paid_final
        FROM settlement_records sr JOIN settlement_record_lines sl ON sl.settlement_id=sr.id
        WHERE sr.session_id IN($sessionPlaceholders) AND sr.status='completed' AND sr.allocation_version IN(1,2)
          AND NOT EXISTS(SELECT 1 FROM settlement_records rv WHERE rv.reverses_settlement_id=sr.id AND rv.status='reversal')
        GROUP BY sr.session_id,sl.order_item_id");
    $paidLineStmt->execute($sessionIds);
    foreach ($paidLineStmt->fetchAll() as $paidRow) $paidByOrderItem[(int)$paidRow['order_item_id']] = $paidRow;

    $paidTotalStmt = db()->prepare("SELECT sr.session_id,COALESCE(SUM(sr.subtotal),0) paid_subtotal,COALESCE(SUM(sr.discount),0) paid_discount,COALESCE(SUM(sr.taxable_amount),0) paid_taxable,COALESCE(SUM(sr.tax_amount),0) paid_tax,COALESCE(SUM(sr.total),0) paid_total,COUNT(*) receipt_count,
        MAX(CASE WHEN sr.settlement_kind='itemized' THEN 1 ELSE 0 END) itemized_active
        FROM settlement_records sr
        WHERE sr.session_id IN($sessionPlaceholders) AND sr.status='completed' AND sr.allocation_version IN(1,2)
          AND NOT EXISTS(SELECT 1 FROM settlement_records rv WHERE rv.reverses_settlement_id=sr.id AND rv.status='reversal')
        GROUP BY sr.session_id");
    $paidTotalStmt->execute($sessionIds);
    foreach ($paidTotalStmt->fetchAll() as $paidRow) {
        $paidTotalsBySession[(int)$paidRow['session_id']] = $paidRow;
        $itemizedActiveBySession[(int)$paidRow['session_id']] = (int)$paidRow['itemized_active'] === 1;
    }

    $billStmt = db()->prepare("SELECT o.id,o.session_id,o.status,o.total_amount,o.customer_note,o.device_token,o.order_source,o.created_at,o.updated_at,cu.display_name created_by FROM orders o LEFT JOIN users cu ON cu.id=o.created_by_user_id WHERE o.session_id IN($sessionPlaceholders) AND status IN('pending_approval','new','accounted') ORDER BY o.session_id,o.created_at,o.id");
    $billStmt->execute($sessionIds);
    $billOrders = $billStmt->fetchAll();
    $billOrderIds = array_map('intval', array_column($billOrders, 'id'));
    if ($billOrderIds) {
        $billPlaceholders = implode(',', array_fill(0, count($billOrderIds), '?'));
        $billLineStmt = db()->prepare("SELECT oi.id,oi.order_id,oi.item_id,oi.item_name,oi.unit_price,oi.quantity,oi.ordered_quantity,oi.adjustment_reason,oi.adjusted_at,oi.item_note,oi.fulfillment_mode,oi.preparation_station,oi.line_total,oi.tax_policy_snapshot,oi.tax_rate_bps_snapshot,oi.tax_rate_version_id,oi.tax_item_policy_version_id,u.display_name adjusted_by FROM order_items oi LEFT JOIN users u ON u.id=oi.adjusted_by_user_id WHERE oi.order_id IN($billPlaceholders) AND oi.quantity>0 ORDER BY oi.order_id,oi.id");
        $billLineStmt->execute($billOrderIds);
        $billLines=$billLineStmt->fetchAll();
        $billCatalogPrices=[];
        $billItemIds=array_values(array_unique(array_filter(array_map(static fn(array $line): int => (int)($line['item_id']??0),$billLines),static fn(int $id): bool => $id>0)));
        if($billItemIds){
            $itemPlaceholders=implode(',',array_fill(0,count($billItemIds),'?'));
            $priceStmt=db()->prepare("SELECT id,price FROM items WHERE id IN($itemPlaceholders)");
            $priceStmt->execute($billItemIds);
            foreach($priceStmt->fetchAll() as $priceRow)$billCatalogPrices[(int)$priceRow['id']]=(int)$priceRow['price'];
        }
        foreach ($billLines as $line) {
            $line['current_menu_price']=$billCatalogPrices[(int)($line['item_id']??0)]??null;
            $billItemsByOrder[(int)$line['order_id']][] = $line;
        }
    }
    $guestLabelsBySession = [];
    foreach ($billOrders as $billOrder) {
        $sessionId = (int)$billOrder['session_id'];
        if ((string)($billOrder['order_source'] ?? 'guest') === 'staff') {
            $creator=trim((string)($billOrder['created_by']??''));
            $billOrder['guest_label']=$creator!==''?'ثبت: '.$creator:'ثبت‌کننده نامشخص';
        } else {
            $deviceKey = trim((string)($billOrder['device_token'] ?? '')) ?: 'unknown-' . (int)$billOrder['id'];
            if (!isset($guestLabelsBySession[$sessionId][$deviceKey])) {
                $guestLabelsBySession[$sessionId][$deviceKey] = count($guestLabelsBySession[$sessionId] ?? []) + 1;
            }
            $billOrder['guest_label'] = 'مهمان ' . fa_digits((string)$guestLabelsBySession[$sessionId][$deviceKey]);
        }
        unset($billOrder['device_token']);
        $billOrder['order_number'] = order_display_number($billOrder);
        $billOrder['status_label'] = order_status_label((string)$billOrder['status']);
        $billOrder['allowed_statuses'] = allowed_order_statuses_from((string)$billOrder['status']);
        $billOrder['created_time'] = fa_digits(date('H:i', strtotime((string)$billOrder['created_at'])));
        $billOrder['items'] = $billItemsByOrder[(int)$billOrder['id']] ?? [];
        $billOrdersBySession[$sessionId][] = $billOrder;
    }
}

if ($accommodationVisible && $sessionIds) {
    $sessionPlaceholders = implode(',', array_fill(0, count($sessionIds), '?'));
    $localFields = accommodation_transfer_local_fields_sql('at');
    $transferStmt = db()->prepare("SELECT at.id,at.session_id,at.external_order_id,at.reservation_code,at.guest_name_snapshot,at.room_name_snapshot,at.amount,at.status,at.attempt_count,at.last_error,at.last_error_code,at.suspicious_response,at.resolved_at,at.updated_at,$localFields FROM accommodation_transfers at WHERE at.session_id IN($sessionPlaceholders)");
    $transferStmt->execute($sessionIds);
    foreach ($transferStmt->fetchAll() as $transferRow) {
        $transferRow['session_status'] = 'active';
        $transferRow = accommodation_enrich_transfer($transferRow);
        $accommodationBySession[(int)$transferRow['session_id']] = $transferRow;
    }
}

foreach ($tables as &$table) {
    $sessionId = (int)($table['session_id'] ?? 0);
    if ($sessionId < 1) continue;
    if ($accommodationVisible) $table['accommodation_transfer'] = $accommodationBySession[$sessionId] ?? null;
    $billOrders = $billOrdersBySession[$sessionId] ?? [];
    $aggregated = [];
    $unconfirmed = 0;
    $total = 0;
    foreach ($billOrders as $billOrder) {
        $total += (int)$billOrder['total_amount'];
        if (in_array((string)$billOrder['status'], ['pending_approval','new'], true)) $unconfirmed++;
        if ((string)$billOrder['status'] !== 'accounted') continue;
        foreach ($billOrder['items'] as $line) {
            $key = (string)$line['item_name'] . "\0" . (string)$line['unit_price'] . "\0" . trim((string)$line['item_note']);
            if (!isset($aggregated[$key])) {
                $aggregated[$key] = [
                    'item_name' => (string)$line['item_name'],
                    'unit_price' => (int)$line['unit_price'],
                    'quantity' => 0,
                    'item_note' => trim((string)$line['item_note']),
                    'line_total' => 0,
                    'takeaway_quantity' => 0,
                    'source_lines' => [],
                ];
            }
            $aggregated[$key]['quantity'] += (int)$line['quantity'];
            $aggregated[$key]['line_total'] += (int)$line['line_total'];
            if (normalize_fulfillment_mode((string)($line['fulfillment_mode'] ?? 'dine_in')) === 'takeaway') {
                $aggregated[$key]['takeaway_quantity'] += (int)$line['quantity'];
            }
            $paidQuantity = (int)($paidByOrderItem[(int)$line['id']]['paid_quantity'] ?? 0);
            $remainingQuantity = max(0, (int)$line['quantity'] - $paidQuantity);
            $aggregated[$key]['paid_quantity'] = (int)($aggregated[$key]['paid_quantity'] ?? 0) + $paidQuantity;
            $aggregated[$key]['remaining_quantity'] = (int)($aggregated[$key]['remaining_quantity'] ?? 0) + $remainingQuantity;
            $aggregated[$key]['source_lines'][] = [
                'id'=>(int)$line['id'],
                'order_id'=>(int)$billOrder['id'],
                'order_number'=>(int)$billOrder['order_number'],
                'guest_label'=>(string)$billOrder['guest_label'],
                'ordered_quantity'=>(int)$line['ordered_quantity'],
                'quantity'=>(int)$line['quantity'],
                'paid_quantity'=>$paidQuantity,
                'remaining_quantity'=>$remainingQuantity,
                'unit_price'=>(int)$line['unit_price'],
                'current_menu_price'=>$line['current_menu_price']===null?null:(int)$line['current_menu_price'],
                'adjustment_reason'=>(string)($line['adjustment_reason']??''),
                'adjusted_at'=>(string)($line['adjusted_at']??''),
                'adjusted_by'=>(string)($line['adjusted_by']??''),
                'fulfillment_mode'=>normalize_fulfillment_mode((string)($line['fulfillment_mode'] ?? 'dine_in')),
                'preparation_station'=>normalize_preparation_station((string)($line['preparation_station'] ?? 'none')),
            ];
        }
    }
    $confirmedSubtotal = array_sum(array_map(static fn(array $order): int => (string)$order['status']==='accounted' ? (int)$order['total_amount'] : 0, $billOrders));
    $discountType = (string)($table['discount_type'] ?? '');
    $discountValue = (int)($table['discount_value'] ?? 0);
    $discountAmount = invoice_discount_amount($confirmedSubtotal, $discountType, $discountValue);
    $taxSourceLines=[];
    foreach($billOrders as $taxOrder){
        if((string)$taxOrder['status']!=='accounted')continue;
        foreach((array)$taxOrder['items'] as $taxLine)$taxSourceLines[]=['order_item_id'=>(int)$taxLine['id'],'quantity'=>(int)$taxLine['quantity'],'unit_price'=>(int)$taxLine['unit_price'],'tax_policy_snapshot'=>(string)($taxLine['tax_policy_snapshot']??'disabled'),'tax_rate_bps_snapshot'=>(int)($taxLine['tax_rate_bps_snapshot']??0)];
    }
    $taxCalc=tax_calculate_invoice_lines($taxSourceLines,$discountAmount);
    $paidTotals = $paidTotalsBySession[$sessionId] ?? [];
    $paidSubtotal = (int)($paidTotals['paid_subtotal'] ?? 0);
    $paidDiscount = (int)($paidTotals['paid_discount'] ?? 0);
    $paidTaxable = (int)($paidTotals['paid_taxable'] ?? 0);
    $paidTax = (int)($paidTotals['paid_tax'] ?? 0);
    $paidTotal = (int)($paidTotals['paid_total'] ?? 0);
    $paidQuantitiesForSignature = [];
    foreach ($billOrders as &$signatureOrder) {
        foreach ((array)($signatureOrder['items'] ?? []) as &$signatureLine) {
            $paidQuantity = (int)($paidByOrderItem[(int)$signatureLine['id']]['paid_quantity'] ?? 0);
            $signatureLine['paid_quantity'] = $paidQuantity;
            $signatureLine['remaining_quantity'] = max(0, (int)$signatureLine['quantity'] - $paidQuantity);
            if ($paidQuantity > 0) $paidQuantitiesForSignature[(int)$signatureLine['id']] = $paidQuantity;
        }
        unset($signatureLine);
    }
    unset($signatureOrder);
    $table['bill_orders'] = $billOrders;
    $table['bill_items'] = array_values($aggregated);
    $table['bill_total'] = $total;
    $table['bill_subtotal'] = $confirmedSubtotal;
    $table['bill_discount'] = (int)$taxCalc['discount'];
    $table['bill_net'] = (int)$taxCalc['net'];
    $table['bill_taxable'] = (int)$taxCalc['taxable'];
    $table['bill_tax'] = (int)$taxCalc['tax'];
    $table['bill_final_total'] = (int)$taxCalc['total'];
    $table['bill_paid_subtotal'] = $paidSubtotal;
    $table['bill_paid_discount'] = $paidDiscount;
    $table['bill_paid_taxable'] = $paidTaxable;
    $table['bill_paid_tax'] = $paidTax;
    $table['bill_paid_total'] = $paidTotal;
    $table['bill_remaining_subtotal'] = max(0,$confirmedSubtotal-$paidSubtotal);
    $table['bill_remaining_discount'] = max(0,(int)$taxCalc['discount']-$paidDiscount);
    $table['bill_remaining_taxable'] = max(0,(int)$taxCalc['taxable']-$paidTaxable);
    $table['bill_remaining_tax'] = max(0,(int)$taxCalc['tax']-$paidTax);
    $table['bill_remaining_total'] = max(0,(int)$taxCalc['total']-$paidTotal);
    $table['bill_paid_receipt_count'] = (int)($paidTotals['receipt_count'] ?? 0);
    $table['bill_itemized_active'] = !empty($itemizedActiveBySession[$sessionId]);
    $table['bill_signature'] = settlement_review_signature($table,$billOrders,[
        'paid_quantities'=>$paidQuantitiesForSignature,
        'paid_subtotal'=>$paidSubtotal,
        'paid_discount'=>$paidDiscount,
        'paid_taxable'=>$paidTaxable,
        'paid_tax'=>$paidTax,
        'paid_total'=>$paidTotal,
    ]);
    $table['bill_order_count'] = count($billOrders);
    $table['unconfirmed_order_count'] = $unconfirmed;
    $table['pending_preparation_adjustments'] = (int)($pendingPreparationBySession[$sessionId] ?? 0);
}
unset($table);

$itemTotals = [];
foreach ($billOrdersBySession as $sessionOrders) {
    foreach ($sessionOrders as $order) {
        $bucket = (string)$order['status'] === 'accounted' ? 'confirmed' : (in_array((string)$order['status'], ['pending_approval','new'], true) ? 'pending' : '');
        if ($bucket === '') continue;
        foreach (($order['items'] ?? []) as $line) {
            $station = normalize_preparation_station((string)($line['preparation_station'] ?? 'other'));
            $key = $station . "\x1F" . (string)$line['item_name'];
            if (!isset($itemTotals[$key])) {
                $itemTotals[$key] = [
                    'item_name'=>(string)$line['item_name'],
                    'station'=>$station,
                    'station_label'=>preparation_stations()[$station] ?? 'سایر',
                    'confirmed'=>0,
                    'pending'=>0,
                ];
            }
            $itemTotals[$key][$bucket] += (int)$line['quantity'];
        }
    }
}
$itemTotals = array_values($itemTotals);
usort($itemTotals, static function(array $a,array $b): int {
    $stationOrder=['kitchen'=>0,'hot_bar'=>1,'cold_bar'=>2,'other'=>3];
    return ($stationOrder[$a['station']]??9)<=>($stationOrder[$b['station']]??9)
        ?: ((int)$b['confirmed']+(int)$b['pending'])<=>((int)$a['confirmed']+(int)$a['pending'])
        ?: strnatcasecmp((string)$a['item_name'],(string)$b['item_name']);
});

$accommodationPayload = null;
if ($accommodationVisible) {
    $accommodationPayload = [
        'enabled'=>$accommodationLiveEnabled,
        'can_post'=>$accommodationCanPost,
        'can_manage'=>$accommodationCanManage,
        'unresolved'=>accommodation_attention_rows(30),
    ];
}

$snapshotPayload = [
    'orders' => array_map(static fn(array $row): array => [(int)$row['id'], $row['status'], $row['updated_at']], $orders),
    'calls' => array_map(static fn(array $row): array => [(int)$row['id'], $row['status'], $row['updated_at']], $calls),
    'tables' => array_map(static fn(array $row): array => [
        (int)$row['id'], $row['session_id'], $row['status'] ?? null, $row['updated_at'] ?? null,
        (int)$row['bill_total'], (int)$row['bill_discount'], (int)$row['bill_final_total'], (int)$row['bill_paid_total'], (int)$row['bill_remaining_total'], !empty($row['bill_itemized_active']) ? 1 : 0, (int)$row['bill_order_count'], (int)$row['unconfirmed_order_count'],
        !empty($row['carryover']) ? 1 : 0,
        array_map(static fn(array $order): array => [(int)$order['id'], $order['status'], $order['updated_at']], $row['bill_orders'] ?? []),
    ], $tables),
    'waiter' => $waiterEnabled,
    'order_acceptance' => $acceptanceStates,
    'order_acceptance_revision' => $acceptanceRevision,
    'stations' => $stationStates,
    'item_totals' => $itemTotals,
];
if ($accommodationVisible) $snapshotPayload['accommodation'] = $accommodationPayload;
$snapshot = substr(hash('sha256', json_encode($snapshotPayload, JSON_UNESCAPED_UNICODE)), 0, 24);

if ($clientSnapshot !== '' && hash_equals($snapshot, $clientSnapshot)) {
    $response = [
        'success' => true,
        'unchanged' => true,
        'snapshot' => $snapshot,
        'revision' => $lightRevision,
        'new_count' => count($attentionIds),
        'new_ids' => $attentionIds,
        'call_ids' => $callIds,
        'server_time' => date(DATE_ATOM),
        'order_acceptance' => $acceptanceStates,
    'order_acceptance_revision' => $acceptanceRevision,
        'waiter_enabled' => $waiterEnabled,
        'station_states' => $stationStates,
        'printing' => $printingSummary,
        'item_totals' => $itemTotals,
        'warnings' => $apiWarnings,
    ];
    if ($accommodationVisible) $response['accommodation'] = $accommodationPayload;
    json_response($response);
}

$response = [
    'success' => true,
    'unchanged' => false,
    'snapshot' => $snapshot,
    'revision' => $lightRevision,
    'orders' => $orders,
    'new_count' => count($attentionIds),
    'new_ids' => $attentionIds,
    'calls' => $calls,
    'call_ids' => $callIds,
    'tables' => $tables,
    'waiter_enabled' => $waiterEnabled,
    'sessions_enabled' => table_sessions_enabled(),
    'order_acceptance' => $acceptanceStates,
    'order_acceptance_revision' => $acceptanceRevision,
    'station_states' => $stationStates,
    'item_totals' => $itemTotals,
    'printing' => $printingSummary,
    'warnings' => $apiWarnings,
    'server_time' => date(DATE_ATOM),
];
if ($accommodationVisible) $response['accommodation'] = $accommodationPayload;
json_response($response);
