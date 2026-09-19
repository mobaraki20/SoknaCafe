<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

function lfail(string $m,mixed $ctx=null):never{fwrite(STDERR,"FAIL: $m\n");if($ctx!==null)fwrite(STDERR,json_encode($ctx,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n");exit(1);}
function lpass(string $m):void{fwrite(STDOUT,"PASS: $m\n");}
function env5(string $id,string $kind,array $payload,string $occurredAt,?string $expectedVersion=null):array{
    global $actorId;
    $e=['request_id'=>$id,'kind'=>$kind,'created_at'=>gmdate('c'),'occurred_at'=>$occurredAt,'actor_projection_id'=>'user:'.$actorId,'payload'=>$payload];
    if($expectedVersion!==null)$e['expected_version']=$expectedVersion;
    return $e;
}
$pdo=db();
$GLOBALS['config']['relay']['installation_id']='phase5-local-ci';
$GLOBALS['config']['relay']['enabled']=false;

$setting=$pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
foreach(['module.inventory.enabled'=>'1','module.supply.enabled'=>'1','inventory_initialized'=>'1','inventory_reconciliation_required'=>'0'] as $k=>$v)$setting->execute([$k,$v]);

$username='phase5-admin-'.bin2hex(random_bytes(4));
$pdo->prepare("INSERT INTO users(username,password_hash,display_name,role,active) VALUES(?,?,?,'admin',1)")->execute([$username,password_hash('x',PASSWORD_DEFAULT),'Phase5 Admin']);
$actorId=(int)$pdo->lastInsertId();

$itemCode='P5-'.strtoupper(bin2hex(random_bytes(4)));
$pdo->prepare("INSERT INTO inventory_items(item_code,name,category,base_unit,default_department,warning_threshold,review_status,active,created_by_user_id) VALUES(?,?,'ingredient','count','kitchen',0,'ready',1,?)")
    ->execute([$itemCode,'Phase5 Item',$actorId]);
$itemId=(int)$pdo->lastInsertId();
$pdo->prepare("INSERT INTO inventory_balances(inventory_item_id,quantity_base,average_unit_cost,cost_status) VALUES(?,10,100,'known')")->execute([$itemId]);

$now=gmdate('c');
$need=env5('local-p5-need','supply.need.create',['inventory_item_id'=>$itemId,'quantity_major'=>'2','department'=>'kitchen','note'=>'deferred need'],$now);
$r=sokna_deferred_dispatch($need);
if(($r['state']??'')!=='committed')lfail('supply need commit',$r);
$needId=(int)($r['result']['supply_need_id']??0);
$qty=(int)$pdo->query('SELECT requested_quantity_base FROM inventory_supply_needs WHERE id='.$needId)->fetchColumn();
if($qty!==2)lfail('supply need quantity',$qty);
$dup=sokna_deferred_dispatch($need);
$qty2=(int)$pdo->query('SELECT requested_quantity_base FROM inventory_supply_needs WHERE id='.$needId)->fetchColumn();
if(empty($dup['idempotent'])||$qty2!==2)lfail('duplicate need changed quantity',[$dup,$qty2]);
lpass('duplicate deferred supply need has exactly one business effect');

$groups=supply_purchase_groups($pdo);$group=null;foreach($groups as $g)if((int)$g['item_id']===$itemId){$group=$g;break;}if(!$group)lfail('supply group fixture');
$prepare=env5('local-p5-prepare','supply.status.prepare',['group_key'=>$group['group_key'],'expected_quantity_base'=>(int)$group['uncommitted_quantity_base']],$now);
$pr=sokna_deferred_dispatch($prepare);if(($pr['state']??'')!=='committed')lfail('prepare commit',$pr);
$groups=supply_purchase_groups($pdo);$group=null;foreach($groups as $g)if((int)$g['item_id']===$itemId){$group=$g;break;}if(!$group)lfail('prepared group fixture');
$receipt=env5('local-p5-receipt','supply.receipt',[
    'group_key'=>$group['group_key'],'expected_preparing_quantity_base'=>(int)$group['preparing_quantity_base'],
    'purchase_unit_id'=>0,'unit_count'=>'2','total_cost'=>'200','supplier'=>'CI Supplier','note'=>'physical receipt'
],$now);
$rr=sokna_deferred_dispatch($receipt);if(($rr['state']??'')!=='committed')lfail('receipt commit',$rr);
$receiptCount=(int)$pdo->query("SELECT COUNT(*) FROM inventory_supply_receipts WHERE request_token='".substr(hash('sha256','deferred:local-p5-receipt'),0,32)."'")->fetchColumn();
if($receiptCount!==1)lfail('receipt exactly once',$receiptCount);
lpass('deferred physical receipt commits through canonical Supply/Inventory owner');

$balStmt=$pdo->prepare('SELECT quantity_base,updated_at FROM inventory_balances WHERE inventory_item_id=?');$balStmt->execute([$itemId]);$bal=$balStmt->fetch();
$waste=env5('local-p5-waste','inventory.waste',['inventory_item_id'=>$itemId,'quantity_major'=>'1','department'=>'kitchen','note'=>'CI waste'],$now,(string)$bal['updated_at']);
$wr=sokna_deferred_dispatch($waste);if(($wr['state']??'')!=='committed')lfail('waste commit',$wr);
$wasteMoves=$pdo->prepare("SELECT COUNT(*) FROM inventory_movements WHERE idempotency_key='deferred:waste:local-p5-waste'");$wasteMoves->execute();
if((int)$wasteMoves->fetchColumn()!==1)lfail('waste movement exactly once');
sokna_deferred_dispatch($waste);
$wasteMoves->execute();if((int)$wasteMoves->fetchColumn()!==1)lfail('duplicate waste movement');
lpass('deferred waste is idempotent');

$pdo->prepare("INSERT INTO inventory_count_sessions(title,session_type,scope_type,status,snapshot_at,created_by_user_id) VALUES('Phase5 Count','periodic','full','draft',NOW(),?)")->execute([$actorId]);
$countId=(int)$pdo->lastInsertId();
$pdo->prepare('INSERT INTO inventory_count_lines(session_id,inventory_item_id,system_quantity_snapshot,actual_quantity) VALUES(?,?,0,NULL)')->execute([$countId,$itemId]);
$lineId=(int)$pdo->lastInsertId();
$lineVersion=(string)$pdo->query('SELECT updated_at FROM inventory_count_lines WHERE id='.$lineId)->fetchColumn();
$countEnv=env5('local-p5-count','inventory.count_draft',['session_id'=>$countId,'line_id'=>$lineId,'actual_major'=>'11','note'=>'remote count'],$now,$lineVersion);
$cr=sokna_deferred_dispatch($countEnv);if(($cr['state']??'')!=='committed')lfail('count draft commit',$cr);
$countState=$pdo->query('SELECT status FROM inventory_count_sessions WHERE id='.$countId)->fetchColumn();
$countValue=(int)$pdo->query('SELECT actual_quantity FROM inventory_count_lines WHERE id='.$lineId)->fetchColumn();
$countMovements=(int)$pdo->query("SELECT COUNT(*) FROM inventory_movements WHERE source_type='inventory_count_session' AND source_id=".$pdo->quote((string)$countId))->fetchColumn();
if($countState!=='draft'||$countValue!==11||$countMovements!==0)lfail('count draft must not finalize',[$countState,$countValue,$countMovements]);
lpass('remote stock count edits draft only; finalize remains Local-only');

$mobile='09'.random_int(100000000,999999999);
$pdo->prepare('INSERT INTO subscribers(name,mobile,mobile_normalized,active,created_by_user_id) VALUES(?,?,?,?,?)')->execute(['Phase5 Subscriber',$mobile,$mobile,1,$actorId]);
$subscriberId=(int)$pdo->lastInsertId();
$pdo->beginTransaction();$period=financial_period_for_date_locked($pdo,business_current_date(),$actorId);subscriber_insert_ledger_locked($pdo,$subscriberId,'invoice',1000,$actorId,null,null,'P5 opening','CI fixture',null,(int)$period['id'],'p5-fixture-invoice-'.$subscriberId);$pdo->commit();
$payment=env5('local-p5-payment','subscriber.payment',['subscriber_id'=>$subscriberId,'amount'=>300,'expected_balance'=>1000,'reference'=>'CI payment'],$now);
$pay=sokna_deferred_dispatch($payment);if(($pay['state']??'')!=='committed'||(int)($pay['result']['balance_after']??-1)!==700)lfail('subscriber payment',$pay);
sokna_deferred_dispatch($payment);if(subscriber_balance($pdo,$subscriberId)!==700)lfail('duplicate subscriber payment changed balance');
lpass('pending customer payment becomes official only once in Local ledger');

$expense=env5('local-p5-expense','expense.create',['category_key'=>'services','amount'=>450,'description'=>'CI service'],$now);
$er=sokna_deferred_dispatch($expense);if(($er['state']??'')!=='committed')lfail('expense commit',$er);
$expenseRows=(int)$pdo->query("SELECT COUNT(*) FROM expenses WHERE source_request_id='deferred:local-p5-expense'")->fetchColumn();
if($expenseRows!==1)lfail('expense exactly once',$expenseRows);
lpass('general expense commits to canonical Expenses owner');

$closedStart='2018-01-01';$closedEnd='2018-12-31';$summary=json_encode(['locked_total'=>12345],JSON_UNESCAPED_SLASHES);
$pdo->prepare("INSERT INTO financial_periods(title,start_date,end_date,status,close_summary_json,opened_by_user_id,closed_by_user_id,closed_at) VALUES('Phase5 Closed',?,?,'closed',?,?,?,NOW())")
    ->execute([$closedStart,$closedEnd,$summary,$actorId,$actorId]);
$closedId=(int)$pdo->lastInsertId();
$late=env5('local-p5-late-expense','expense.create',['category_key'=>'other','amount'=>999,'description'=>'late candidate'],'2018-06-15T10:00:00Z');
$lateResult=sokna_deferred_dispatch($late);
if(($lateResult['state']??'')!=='needs_review'||($lateResult['error_code']??'')!=='closed_financial_period')lfail('late event should review',$lateResult);
if((int)$pdo->query("SELECT COUNT(*) FROM expenses WHERE source_request_id='deferred:local-p5-late-expense'")->fetchColumn()!==0)lfail('late event mutated closed period before review');
$lateDup=sokna_deferred_dispatch($late);
$reviewCount=(int)$pdo->query("SELECT COUNT(*) FROM deferred_review_items r JOIN deferred_work_receipts d ON d.id=r.receipt_id WHERE d.request_id='local-p5-late-expense'")->fetchColumn();
if($reviewCount!==1||empty($lateDup['idempotent']))lfail('duplicate late event review',[$reviewCount,$lateDup]);
$reviewId=(int)$pdo->query("SELECT r.id FROM deferred_review_items r JOIN deferred_work_receipts d ON d.id=r.receipt_id WHERE d.request_id='local-p5-late-expense' LIMIT 1")->fetchColumn();
$resolved=sokna_deferred_resolve_review($reviewId,'approve','CI explicit correction approval',$actorId);
if(($resolved['state']??'')!=='committed')lfail('late review approval',$resolved);
if((int)$pdo->query("SELECT COUNT(*) FROM expenses WHERE source_request_id='deferred:local-p5-late-expense'")->fetchColumn()!==1)lfail('approved late correction missing');
$summaryAfter=(string)$pdo->query('SELECT close_summary_json FROM financial_periods WHERE id='.$closedId)->fetchColumn();
if($summaryAfter!==$summary)lfail('closed summary changed during explicit correction',[$summary,$summaryAfter]);
$reconcilePending=(int)$pdo->query("SELECT public_reconcile_pending FROM deferred_work_receipts WHERE request_id='local-p5-late-expense'")->fetchColumn();
if($reconcilePending!==1)lfail('review resolution not marked for Public reconcile');
lpass('late closed-period event creates one review and never silently back-posts');

$overrideStatus=['known'=>false,'paired'=>true,'blocking'=>1,'error'=>'public_unreachable'];
$pdo->beginTransaction();$overrideId=sokna_deferred_record_close_override_locked($pdo,$closedId,$actorId,'CI audited exception',$overrideStatus);$pdo->commit();
if($overrideId<1||(int)$pdo->query('SELECT COUNT(*) FROM financial_period_close_overrides WHERE id='.$overrideId)->fetchColumn()!==1)lfail('close override audit');
lpass('financial close override is durable and reasoned');

echo "Phase 5 Local deferred/domain integration PASS\n";
