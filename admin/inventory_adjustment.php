<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
sokna_module_require('inventory');
sokna_module_require_runtime_ready('inventory');
require_login();
if (!user_can_inventory_finalize()) deny_access_and_return();
require dirname(__DIR__) . '/includes/panel_layout.php';

function inventory_adjustment_state(PDO $pdo, int $movementId, bool $lock = false): array
{
    $sql = 'SELECT m.*,i.name item_name,i.base_unit,b.quantity_base,b.average_unit_cost,b.cost_status balance_cost_status FROM inventory_movements m JOIN inventory_items i ON i.id=m.inventory_item_id LEFT JOIN inventory_balances b ON b.inventory_item_id=i.id WHERE m.id=?' . ($lock ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$movementId]);
    $movement = $stmt->fetch();
    if (!$movement) throw new RuntimeException('گردش پیدا نشد.');

    $relatedSql = "SELECT id,movement_type,quantity_base,total_cost_delta FROM inventory_movements WHERE correction_of_id=? ORDER BY id" . ($lock ? ' FOR UPDATE' : '');
    $relatedStmt = $pdo->prepare($relatedSql);
    $relatedStmt->execute([$movementId]);
    $quantityCorrection = 0;
    $returned = 0;
    $costAdjustment = 0;
    $costAdjustmentCount = 0;
    foreach ($relatedStmt->fetchAll() as $related) {
        $type = (string)$related['movement_type'];
        if ($type === 'quantity_correction') $quantityCorrection += (int)$related['quantity_base'];
        if ($type === 'purchase_return') $returned += max(0, -(int)$related['quantity_base']);
        if ($type === 'cost_adjustment') {
            $costAdjustment += (int)($related['total_cost_delta'] ?? 0);
            $costAdjustmentCount++;
        }
    }

    $effectiveQuantity = (int)$movement['quantity_base'] + $quantityCorrection;
    $currentCost = null;
    if ($movement['total_cost_delta'] !== null) $currentCost = (int)$movement['total_cost_delta'] + $costAdjustment;
    elseif ($costAdjustmentCount > 0) $currentCost = $costAdjustment;

    return [
        'movement'=>$movement,
        'effective_quantity'=>$effectiveQuantity,
        'returned_quantity'=>$returned,
        'remaining_returnable'=>max(0, $effectiveQuantity - $returned),
        'current_cost'=>$currentCost,
    ];
}

$pdo = db();
$userId = (int)(current_user()['id'] ?? 0);
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id < 1) render_recovery_error_page(404, 'گردش انبار پیدا نشد', 'شناسه گردش معتبر نیست یا پیوند قدیمی است.', 'inventory.php?tab=movements', 'بازگشت به گردش انبار');
try { $state = inventory_adjustment_state($pdo,$id); }
catch (RuntimeException $e) { render_recovery_error_page(404, 'گردش انبار پیدا نشد', 'این گردش دیگر در دسترس نیست.', 'inventory.php?tab=movements', 'بازگشت به گردش انبار'); }
$movement = $state['movement'];
$allowedModes=inventory_adjustment_allowed_modes((string)$movement['movement_type']);
if(!$allowedModes) render_recovery_error_page(404, 'اصلاح از این مسیر ممکن نیست', 'این ثبت انبار با اصلاح دستی تغییر نمی‌کند. از سابقه ورود و خروج انبار ادامه دهید.', 'inventory.php?tab=movements', 'بازگشت به ورود و خروج انبار');
$currentEffectiveQuantity = (int)$state['effective_quantity'];
$returnedQuantity = (int)$state['returned_quantity'];
$remainingReturnable = (int)$state['remaining_returnable'];
$currentCost = $state['current_cost'];

$mode = (string)($_GET['mode'] ?? $_POST['mode'] ?? 'quantity');
if(!in_array($mode,$allowedModes,true))$mode=$allowedModes[0];
$returnContext=(string)($_GET['from'] ?? $_POST['from'] ?? '');
$returnTo=$returnContext==='purchases'?'purchases.php':'inventory.php?tab=movements';
$returnSuffix=$returnContext==='purchases'?'&from=purchases':'';

$requestToken = trim((string)($_POST['request_token'] ?? ''));
if (!preg_match('/^[a-f0-9]{32}$/', $requestToken)) $requestToken = bin2hex(random_bytes(16));

if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $reason = text_substr(trim((string)($_POST['reason'] ?? '')),0,500);
    try {
        if ($reason==='') throw new RuntimeException('دلیل اصلاح را بنویس.');
        $pdo->beginTransaction();
        $state = inventory_adjustment_state($pdo,$id,true);
        $movement = $state['movement'];
        $lockedAllowedModes=inventory_adjustment_allowed_modes((string)$movement['movement_type']);
        if(!in_array($mode,$lockedAllowedModes,true)) throw new RuntimeException('این اصلاح برای نوع ثبت انتخاب‌شده مجاز نیست.');
        $currentEffectiveQuantity = (int)$state['effective_quantity'];
        $returnedQuantity = (int)$state['returned_quantity'];
        $remainingReturnable = (int)$state['remaining_returnable'];
        $currentCost = $state['current_cost'];
        $idempotencyKey = 'inventory:'.$mode.'-correction:'.$id.':'.$requestToken;
        $dup = $pdo->prepare('SELECT id FROM inventory_movements WHERE idempotency_key=? LIMIT 1');
        $dup->execute([$idempotencyKey]);
        if ((int)($dup->fetchColumn() ?: 0) > 0) {
            $pdo->commit();
            flash('success','این اصلاح قبلاً ثبت شده بود؛ دوباره اعمال نشد.');
            redirect($returnTo);
        }

        if ($mode==='cost') {
            $correctRaw=trim((string)($_POST['correct_total_cost'] ?? ''));
            if ($correctRaw==='') throw new RuntimeException('قیمت کل صحیح را وارد کن.');
            $correct=inventory_money_value($correctRaw);
            $before=$currentCost ?? 0;
            $delta=$correct-$before;
            if ($delta===0) throw new RuntimeException('قیمت صحیح با مقدار فعلی یکسان است.');
            inventory_record_movement_locked($pdo,[
                'item_id'=>(int)$movement['inventory_item_id'],
                'movement_type'=>'cost_adjustment',
                'quantity_base'=>0,
                'total_cost_delta'=>$delta,
                'cost_status'=>'known',
                'correction_of_id'=>$id,
                'source_type'=>'inventory_cost_correction',
                'source_id'=>$id,
                'idempotency_key'=>$idempotencyKey,
                'metadata'=>['correct_total_cost'=>$correct,'previous_total_cost'=>$currentCost],
                'note'=>$reason,
                'actor_user_id'=>$userId,
            ]);
        } elseif ($mode==='return') {
            $amount=inventory_major_to_base($_POST['return_major_quantity'] ?? '',(string)$movement['base_unit']);
            if ($amount<1) throw new RuntimeException('مقدار برگشتی را وارد کن.');
            if ($remainingReturnable < 1) throw new RuntimeException('تمام مقدار قابل برگشت این ورود قبلاً برگشت داده شده است.');
            if ($amount > $remainingReturnable) throw new RuntimeException('مقدار برگشتی از مقدار باقی‌مانده این ورود بیشتر است.');
            $refundRaw=trim((string)($_POST['refund_total'] ?? ''));
            $refund=$refundRaw===''?null:inventory_money_value($refundRaw);
            inventory_record_movement_locked($pdo,[
                'item_id'=>(int)$movement['inventory_item_id'],
                'movement_type'=>'purchase_return',
                'quantity_base'=>-$amount,
                'department'=>$movement['department'] ?: null,
                'source_type'=>'purchase_return',
                'source_id'=>$id,
                'correction_of_id'=>$id,
                'total_cost_delta'=>$refund === null ? null : -$refund,
                'cost_status'=>$refund === null ? 'estimated' : 'known',
                'idempotency_key'=>$idempotencyKey,
                'metadata'=>['supplier_refund_total'=>$refund,'source_purchase_movement_id'=>$id,'remaining_returnable_before'=>$remainingReturnable],
                'note'=>$reason,
                'actor_user_id'=>$userId,
            ]);
        } else {
            $correct=inventory_major_to_base($_POST['correct_major_quantity'] ?? '',(string)$movement['base_unit']);
            $sign=(int)$movement['quantity_base']<0 ? -1 : 1;
            $target=$sign*$correct;
            if ((string)$movement['movement_type']==='purchase_receive' && $target < $returnedQuantity) {
                throw new RuntimeException('مقدار صحیح خرید نمی‌تواند از مقداری که قبلاً به تأمین‌کننده برگشت داده شده کمتر باشد.');
            }
            $delta=$target-$currentEffectiveQuantity;
            if ($delta===0) throw new RuntimeException('مقدار صحیح با مقدار فعلی عملیات یکسان است.');
            inventory_record_movement_locked($pdo,[
                'item_id'=>(int)$movement['inventory_item_id'],
                'movement_type'=>'quantity_correction',
                'quantity_base'=>$delta,
                'department'=>$movement['department'] ?: null,
                'unit_cost_snapshot'=>$movement['unit_cost_snapshot']!==null?(float)$movement['unit_cost_snapshot']:null,
                'cost_status'=>(string)$movement['cost_status'],
                'correction_of_id'=>$id,
                'source_type'=>'inventory_quantity_correction',
                'source_id'=>$id,
                'idempotency_key'=>$idempotencyKey,
                'revalue_balance'=>true,
                'metadata'=>[
                    'original_quantity_base'=>(int)$movement['quantity_base'],
                    'previous_effective_quantity_base'=>$currentEffectiveQuantity,
                    'correct_quantity_base'=>$target,
                ],
                'note'=>$reason,
                'actor_user_id'=>$userId,
            ]);
        }
        $pdo->commit();
        flash('success','اصلاح انبار ثبت شد؛ رکورد اصلی بدون تغییر باقی ماند.');
        redirect($returnTo);
    } catch (RuntimeException|InvalidArgumentException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error',$e->getMessage());
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('inventory adjustment: '.$e->getMessage());
        flash('error','اصلاح انبار ثبت نشد. دوباره تلاش کن؛ اگر مشکل ادامه داشت مدیر سامانه را مطلع کن.');
    }
}

panel_header('اصلاح سابقه انبار','inventory');
panel_subnav(['stock'=>['inventory.php','موجودی'],'movements'=>['inventory.php?tab=movements','گردش'],'counts'=>['inventory.php?tab=counts','شمارش‌ها']],'movements','بخش‌های انبار');
?>
<section class="card inventory-review-card"><div class="card-body">
<div class="panel-copy-stack"><h2><?= e((string)$movement['item_name']) ?></h2><p class="muted"><?= e(inventory_movement_labels()[(string)$movement['movement_type']]??'') ?> · مقدار اصلی: <?= e(inventory_format_quantity((int)$movement['quantity_base'],(string)$movement['base_unit'])) ?><?php if($currentEffectiveQuantity!==(int)$movement['quantity_base']): ?> · مقدار فعلی عملیات: <?= e(inventory_format_quantity($currentEffectiveQuantity,(string)$movement['base_unit'])) ?><?php endif; ?></p></div>
<nav class="panel-subnav" aria-label="نوع اصلاح"><a class="<?= $mode==='quantity'?'is-active':'' ?>" href="inventory_adjustment.php?id=<?= $id ?>&mode=quantity<?= e($returnSuffix) ?>">اصلاح مقدار</a><?php if(in_array('cost',$allowedModes,true)): ?><a class="<?= $mode==='cost'?'is-active':'' ?>" href="inventory_adjustment.php?id=<?= $id ?>&mode=cost<?= e($returnSuffix) ?>">اصلاح قیمت</a><?php endif; ?><?php if(in_array('return',$allowedModes,true)): ?><a class="<?= $mode==='return'?'is-active':'' ?>" href="inventory_adjustment.php?id=<?= $id ?>&mode=return<?= e($returnSuffix) ?>">برگشت به تأمین‌کننده</a><?php endif; ?></nav>
<form method="post" class="form-grid" data-inventory-flow><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="mode" value="<?= e($mode) ?>"><?php if($returnContext==='purchases'): ?><input type="hidden" name="from" value="purchases"><?php endif; ?><input type="hidden" name="request_token" value="<?= e($requestToken) ?>">
<?php if($mode==='cost'): ?><div class="form-group full"><label>قیمت کل صحیح خرید، تومان</label><input class="form-control" inputmode="numeric" enterkeyhint="next" autocomplete="off" data-inventory-step data-inventory-autofocus name="correct_total_cost" value="<?= $currentCost===null?'':e(money_input_display_value((string)$currentCost)) ?>" data-money-input required><small class="muted">مقدار موجودی تغییر نمی‌کند؛ فقط ارزش خرید اصلاح می‌شود. اگر بخشی از هزینه‌های قبلی نامشخص باشد، ارزش موجودی همچنان تقریبی می‌ماند.</small></div>
<?php elseif($mode==='return'): ?><div class="form-group"><label>مقدار برگشتی (<?= e(inventory_major_unit_label((string)$movement['base_unit'])) ?>)</label><input class="form-control" inputmode="decimal" enterkeyhint="next" autocomplete="off" data-inventory-step data-inventory-autofocus name="return_major_quantity" required><small class="muted">قابل برگشت: <?= e(inventory_format_quantity($remainingReturnable,(string)$movement['base_unit'])) ?></small></div><div class="form-group"><label>مبلغ برگشتی تأمین‌کننده، تومان</label><input class="form-control" inputmode="numeric" enterkeyhint="next" autocomplete="off" data-inventory-step name="refund_total" data-money-input placeholder="اختیاری"><small class="muted">برای سابقه ثبت می‌شود؛ ارزش خروج انبار با میانگین هزینه فعلی محاسبه می‌شود.</small></div>
<?php else: ?><div class="form-group full"><label>مقدار صحیح عملیات (<?= e(inventory_major_unit_label((string)$movement['base_unit'])) ?>)</label><input class="form-control" inputmode="decimal" enterkeyhint="next" autocomplete="off" data-inventory-step data-inventory-autofocus name="correct_major_quantity" value="<?= e(numeric_input_display_value(inventory_base_to_major_value(abs($currentEffectiveQuantity),(string)$movement['base_unit']))) ?>" required><small class="muted">اصلاح نسبت به مقدار فعلی عملیات محاسبه می‌شود؛ اصلاح‌های قبلی دوباره اعمال نمی‌شوند.</small></div><?php endif; ?>
<div class="form-group full"><label>دلیل اصلاح</label><textarea class="form-control" name="reason" required data-inventory-step><?= e((string)($_POST['reason'] ?? '')) ?></textarea></div><div class="form-group full actions inventory-sticky-actions"><button class="btn btn-primary">ثبت اصلاح</button><a class="btn btn-light" href="<?= e($returnTo) ?>">انصراف</a></div></form></div></section>
<?php panel_footer(); ?>
