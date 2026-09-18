<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
maintenance_guard_json();
require_capability('cashier_accounts');

$userId = (int)current_user()['id'];
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $query = trim((string)($_GET['q'] ?? ''));
    if (text_length($query) < 2) json_response(['success'=>true,'items'=>[]]);
    $items = subscriber_find_active($pdo, $query);
    json_response(['success'=>true,'items'=>array_map(static fn(array $row): array => [
        'id'=>(int)$row['id'],
        'name'=>(string)$row['name'],
        'mobile'=>(string)$row['mobile'],
        'balance'=>(int)$row['balance'],
    ], $items)]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['success'=>false],405);
$data=request_json();
$requestId=text_substr(preg_replace('/[^A-Za-z0-9._:-]/','',trim((string)($data['request_id']??'')))?:bin2hex(random_bytes(8)),0,96);
if (!csrf_valid($data['csrf_token']??null)) json_response(['success'=>false,'message'=>'صفحه منقضی شده؛ دوباره بارگذاری کن.','request_id'=>$requestId],419);
if ((string)($data['action']??'') !== 'charge') json_response(['success'=>false,'message'=>'عملیات مشترک شناخته نشد.','request_id'=>$requestId],422);
$tableId=(int)($data['table_id']??0);
$subscriberId=(int)($data['subscriber_id']??0);
$printFinal=bool_from_mixed($data['print_final']??false);
$expectedSessionId=(int)($data['expected_session_id']??0);
$expectedTotal=(int)($data['expected_total']??-1);
$expectedSignature=strtolower(trim((string)($data['expected_signature']??'')));
if ($tableId<1 || $subscriberId<1) json_response(['success'=>false,'message'=>'میز یا مشترک معتبر نیست.','request_id'=>$requestId],422);

try {
    $pdo->beginTransaction();
    $tableStmt=$pdo->prepare('SELECT id,name FROM cafe_tables WHERE id=? AND active=1 FOR UPDATE');
    $tableStmt->execute([$tableId]);
    $table=$tableStmt->fetch();
    if(!$table) throw new RuntimeException('میز پیدا نشد.');
    $existingSettlement=settlement_find_request($pdo,$requestId,true);
    if($existingSettlement){
        settlement_assert_request_match($existingSettlement,'subscriber',$tableId);
        $entryId=(int)($existingSettlement['subscriber_ledger_entry_id']??0);
        $ledgerStmt=$pdo->prepare('SELECT subscriber_id,balance_after FROM subscriber_ledger WHERE id=? LIMIT 1 FOR UPDATE');
        $ledgerStmt->execute([$entryId]);
        $existingLedger=$ledgerStmt->fetch();
        if(!$existingLedger || (int)$existingLedger['subscriber_id']!==$subscriberId) throw new RuntimeException('شناسه این درخواست قبلاً برای مشترک دیگری استفاده شده است.',409);
        $pdo->commit();
        json_response([
            'success'=>true,'persisted'=>true,'idempotent'=>true,'request_id'=>$requestId,'table_id'=>$tableId,
            'session_id'=>(int)$existingSettlement['session_id'],'subscriber_balance'=>(int)$existingLedger['balance_after'],
            'message'=>'این فاکتور قبلاً با همین درخواست در حساب مشترک ثبت شده است.',
        ]+settlement_result_from_record($existingSettlement));
    }
    $sessionStmt=$pdo->prepare("SELECT id FROM table_sessions WHERE table_id=? AND status='active' ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $sessionStmt->execute([$tableId]);
    $sessionId=(int)($sessionStmt->fetchColumn()?:0);
    if($sessionId<1) throw new RuntimeException('این میز حساب فعال ندارد.');
    settlement_assert_session_editable_locked($pdo, $sessionId, 'تسویه حساب مشترک');
    $pendingAdjustmentIds=preparation_adjustments_pending_ids($pdo,$sessionId,true);
    accommodation_resolve_failed_for_alternate_settlement_locked($pdo,$sessionId,$userId,'subscriber_settlement');

    $invoice=settlement_calculate_session_invoice_locked($pdo,$sessionId);
    $account=settlement_account_state_locked($pdo,$invoice);
    settlement_assert_expected_account($account,$expectedSessionId,$expectedTotal,$expectedSignature);
    $issued=financial_period_issue_invoice_locked($pdo,date('Y-m-d H:i:s'),$userId);
    $periodId=(int)$issued['period']['id'];
    $invoiceNumber=(string)$issued['invoice_number'];
    $snapshot=subscriber_invoice_snapshot_locked($pdo,$invoice,(string)$table['name'],$invoiceNumber);
    $ledger=subscriber_insert_ledger_locked(
        $pdo,
        $subscriberId,
        'invoice',
        (int)$invoice['total'],
        $userId,
        $sessionId,
        null,
        'S-'.$sessionId,
        null,
        $snapshot,
        $periodId,
        'settlement:subscriber:'.$requestId
    );
    $result=settlement_finalize_locked($pdo,$invoice,'subscriber',$userId,$printFinal,['subscriber_ledger_entry_id'=>(int)$ledger['id'],'financial_period_id'=>$periodId,'invoice_number'=>$invoiceNumber,'invoice_snapshot'=>$snapshot,'request_id'=>$requestId]);
    audit_log_write('subscriber.invoice_posted','subscriber',$subscriberId,[
        'session_id'=>$sessionId,
        'amount'=>(int)$invoice['total'],
        'balance'=>(int)$ledger['balance_after'],
        'ledger_entry_id'=>(int)$ledger['id'],
    ],$userId);
    if($pendingAdjustmentIds)preparation_adjustments_audit_settlement_notice($sessionId,$pendingAdjustmentIds,$userId,'subscriber');
    $pdo->commit();
    json_response([
        'success'=>true,
        'persisted'=>true,
        'request_id'=>$requestId,
        'table_id'=>$tableId,
        'session_id'=>$sessionId,
        'pending_preparation_adjustments'=>count($pendingAdjustmentIds),
        'message'=>'فاکتور به حساب '.$ledger['subscriber']['name'].' ثبت و میز آزاد شد.'.(!empty($result['print_warning'])?' '.$result['print_warning']:'').($pendingAdjustmentIds?' اصلاحیه آماده‌سازی باز همچنان باقی مانده است.':''),
        'subscriber_balance'=>(int)$ledger['balance_after'],
    ]+$result);
} catch (SettlementStateConflict $e) {
    if($pdo->inTransaction())$pdo->rollBack();
    json_response(['success'=>false,'code'=>'settlement_changed','message'=>$e->getMessage(),'request_id'=>$requestId],409);
} catch (PDOException $e) {
    if($pdo->inTransaction())$pdo->rollBack();
    if((string)$e->getCode()==='23000') json_response(['success'=>false,'message'=>'این فاکتور قبلاً در حساب مشترک ثبت شده است.','request_id'=>$requestId],409);
    error_log('subscriber charge ['.$requestId.']: '.$e->getMessage());
    json_response(['success'=>false,'message'=>'ثبت فاکتور مشترک انجام نشد. کد پیگیری: '.$requestId,'request_id'=>$requestId],500);
} catch (RuntimeException $e) {
    if($pdo->inTransaction())$pdo->rollBack();
    json_response(['success'=>false,'message'=>$e->getMessage(),'request_id'=>$requestId],$e->getCode()===409?409:422);
} catch (Throwable $e) {
    if($pdo->inTransaction())$pdo->rollBack();
    error_log('subscriber charge ['.$requestId.']: '.$e->getMessage());
    json_response(['success'=>false,'message'=>'ثبت فاکتور مشترک انجام نشد. کد پیگیری: '.$requestId,'request_id'=>$requestId],500);
}
