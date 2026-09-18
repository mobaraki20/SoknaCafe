<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
sokna_module_require('inventory');
require_any_capability(['inventory_view','inventory_cost_view','inventory_operations','inventory_finalize','inventory_manage']);
require dirname(__DIR__) . '/includes/panel_layout.php';

$pdo = db();
inventory_seed_if_empty($pdo);
// Keep normal operation self-contained: apply a small bounded backlog whenever the
// inventory workspace is opened. The CLI worker remains an optional accelerator.
inventory_process_pending_order_events(10);
$user = current_user();
$canCost = user_can_inventory_cost($user);
$canOps = user_can_inventory_operate($user);
$canFinalize = user_can_inventory_finalize($user);
$canManage = user_can_inventory_manage($user);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['action'] ?? '') === 'retry_inventory_sync') {
    verify_csrf($_POST['csrf_token'] ?? null);
    if (!$canFinalize && !$canManage) deny_access_and_return($user);
    $retried = inventory_retry_failed_order_events($pdo);
    $result = inventory_process_pending_order_events(20);
    audit_log_write('inventory.order_consumption_retry','inventory_order_event',null,['reset_failed'=>$retried,'processed'=>(int)$result['processed'],'failed'=>(int)$result['failed']],(int)($user['id']??0));
    flash('success',($retried>0 || (int)$result['processed']>0)?'ثبت مصرف فروش دوباره بررسی شد.':'موردی برای تلاش مجدد وجود نداشت.');
    redirect('inventory.php');
}
$tab = (string)($_GET['tab'] ?? 'stock');
if (!in_array($tab,['stock','movements','counts'],true)) $tab='stock';
if (inventory_reconciliation_required() && $tab === 'stock') $tab = 'counts';

$summaryRows = $pdo->query("SELECT i.id,i.review_status,i.warning_threshold,i.active,COALESCE(b.quantity_base,0) quantity_base,b.average_unit_cost,COALESCE(b.cost_status,'unknown') cost_status FROM inventory_items i LEFT JOIN inventory_balances b ON b.inventory_item_id=i.id WHERE i.active=1")->fetchAll();
$approxValue = 0; $missingCost = 0; $lowStock = 0; $negativeStock = 0; $reviewCount = 0;
foreach ($summaryRows as $row) {
    $qty=(int)$row['quantity_base'];
    if ($qty > 0 && $row['average_unit_cost'] !== null) $approxValue += (int)round($qty * (float)$row['average_unit_cost']);
    if ($qty > 0 && ($row['average_unit_cost'] === null || (string)$row['cost_status'] !== 'known')) $missingCost++;
    if ($qty < 0) $negativeStock++;
    elseif ((int)$row['warning_threshold'] > 0 && $qty <= (int)$row['warning_threshold']) $lowStock++;
    if ((string)$row['review_status']==='needs_review') $reviewCount++;
}
$lastCount = $pdo->query("SELECT title,finalized_at FROM inventory_count_sessions WHERE status='finalized' ORDER BY finalized_at DESC,id DESC LIMIT 1")->fetch() ?: null;
$syncBacklog = inventory_order_event_backlog($pdo);
$syncPending = (int)$syncBacklog['pending']; $syncFailed = (int)$syncBacklog['failed']; $syncStale = (int)$syncBacklog['stale_pending']; $syncProblemOrders=(int)($syncBacklog['problem_orders']??0);
$inventoryInitialized = inventory_initialized();
$inventoryReconciliationRequired = inventory_reconciliation_required();
$inventoryReady = sokna_module_runtime_ready('inventory');
$openCount = inventory_open_count_session($pdo);

panel_header('انبار','inventory');
panel_subnav([
    'stock'=>['inventory.php','موجودی'],
    'movements'=>['inventory.php?tab=movements','سابقه'],
    'counts'=>['inventory.php?tab=counts','شمارش‌ها'],
],$tab,'بخش‌های انبار');
?>
<div class="inventory-toolbar">
  <div class="panel-copy-stack"><strong>کنترل موجودی، ورود، ضایعات و شمارش</strong><?php if(!$canCost): ?><small class="muted inventory-cost-hidden">اطلاعات هزینه برای این حساب نمایش داده نمی‌شود.</small><?php endif; ?></div>
  <div class="inventory-toolbar-actions">
    <?php if(sokna_module_enabled('supply') && user_can_manage_purchases($user)): ?><a class="btn btn-light" href="purchases.php"><?= ui_icon('list') ?> خرید</a><?php endif; ?>
    <?php if($inventoryReady && $canOps): ?>
      <a class="btn btn-primary" href="inventory_receive.php"><?= ui_icon('plus') ?> ورود کالا</a>
      <a class="btn btn-light" href="inventory_waste.php"><?= ui_icon('trash') ?> ضایعات</a>
      <?php if(!$openCount): ?><a class="btn btn-light" href="inventory_count_start.php"><?= ui_icon('adjust') ?> شروع شمارش</a><?php endif; ?>
    <?php elseif($inventoryReconciliationRequired && $canOps): ?><a class="btn btn-primary" href="inventory_count_start.php"><?= ui_icon('adjust') ?> بازشماری کامل</a>
    <?php elseif(!$inventoryInitialized && $canManage): ?><a class="btn btn-primary" href="inventory_opening.php"><?= ui_icon('archive') ?> راه‌اندازی موجودی اولیه</a><?php endif; ?>
    <?php if($canManage): ?><a class="btn btn-light inventory-manage-link" href="inventory_items.php"><?= ui_icon('settings') ?> مدیریت کالاها<?php if($reviewCount): ?> · <?= fa_digits($reviewCount) ?> نیاز به تأیید<?php endif; ?></a><?php endif; ?>
  </div>
</div>

<?php if($inventoryReady): ?>
<div class="inventory-summary">
  <div class="summary-cell"><small><?= $canCost?'ارزش ثبت‌شده موجودی':'اقلام دارای موجودی' ?></small><strong><?= $canCost?e(toman($approxValue)):fa_digits(count(array_filter($summaryRows,fn($r)=>(int)$r['quantity_base']>0))) ?></strong><?php if($canCost && $missingCost): ?><span class="summary-note">هزینه <?= fa_digits($missingCost) ?> قلم کامل نیست</span><?php endif; ?></div>
  <div class="summary-cell"><small>نیازمند توجه</small><strong><?= fa_digits($lowStock + $negativeStock) ?> مورد</strong><span class="summary-note"><?= fa_digits($lowStock) ?> کم‌موجودی · <?= fa_digits($negativeStock) ?> موجودی منفی</span></div>
  <div class="summary-cell"><small>آخرین شمارش</small><strong><?= $lastCount?e((string)$lastCount['title']):'هنوز انجام نشده' ?></strong><?php if($lastCount): ?><span class="summary-note"><?= e(time_ago((string)$lastCount['finalized_at'])) ?></span><?php endif; ?></div>
</div>

<?php if($negativeStock>0 || $lowStock>0 || ($canCost&&$missingCost>0) || ($canManage&&$reviewCount>0)): ?>
<nav class="inventory-exception-links" aria-label="موارد نیازمند توجه">
  <?php if($negativeStock): ?><a class="is-danger" href="inventory.php?status=negative"><?= fa_digits($negativeStock) ?> موجودی منفی</a><?php endif; ?>
  <?php if($lowStock): ?><a class="is-warning" href="inventory.php?status=low"><?= fa_digits($lowStock) ?> کم‌موجودی</a><?php endif; ?>
  <?php if($canCost&&$missingCost): ?><a href="inventory.php?status=missing_cost"><?= fa_digits($missingCost) ?> هزینه ناقص</a><?php endif; ?>
  <?php if($canManage&&$reviewCount): ?><a href="inventory_review.php"><?= fa_digits($reviewCount) ?> نیاز به تأیید</a><?php endif; ?>
</nav>
<?php endif; ?>

<?php endif; ?>

<?php if($inventoryReconciliationRequired): ?><div class="inventory-attention"><div><strong>برای بازگشت انبار به عملیات، یک شمارش کامل لازم است</strong><p>در مدتی که انبار خاموش بوده مصرف فروش ثبت نشده است؛ موجودی قبلی مبنای امنی برای ادامه نیست. پس از نهایی‌کردن شمارش کل انبار، مصرف خودکار و خرید دوباره قابل فعال‌سازی می‌شوند.</p></div><?php if($canOps && !$openCount): ?><a class="btn btn-primary btn-sm" href="inventory_count_start.php">شروع بازشماری کامل</a><?php endif; ?></div><?php endif; ?>

<?php if($openCount): ?><div class="inventory-attention inventory-active-count"><div><strong>یک شمارش باز دارید: <?= e((string)$openCount['title']) ?></strong><p>برای جلوگیری از دو شمارش همزمان، همین شمارش را ادامه یا لغو کنید.</p></div><a class="btn btn-primary btn-sm" href="inventory_count.php?id=<?= (int)$openCount['id'] ?>">ادامه شمارش</a></div><?php endif; ?>
<?php if($syncFailed>0 || $syncStale>0): ?><div class="inventory-attention"><strong>مصرف انبار برخی سفارش‌ها هنوز کامل ثبت نشده است</strong><p>مصرف انبار <?= fa_digits(max(1,$syncProblemOrders)) ?> سفارش هنوز کامل ثبت نشده است. خود سفارش‌ها محفوظ هستند.</p><?php if($canFinalize||$canManage): ?><form method="post" class="inventory-inline-action"><?= csrf_field() ?><button class="btn btn-light" name="action" value="retry_inventory_sync">تلاش مجدد</button></form><?php endif; ?></div><?php endif; ?>
<?php if(!$inventoryInitialized): ?><div class="inventory-attention"><strong>موجودی اولیه هنوز نهایی نشده است</strong><p><?php if($canManage): ?>برای شروع دقیق، موجودی واقعی امروز را یک‌بار ثبت کن. بعد از آن ورود، ضایعات و شمارش دوره‌ای فعال می‌شوند. <a href="inventory_opening.php">شروع موجودی اولیه</a><?php else: ?>عملیات روزانه انبار پس از نهایی‌شدن موجودی اولیه توسط مدیر فعال می‌شود.<?php endif; ?></p></div><?php endif; ?>

<?php if($tab==='stock'):
$q=trim((string)($_GET['q']??''));$status=(string)($_GET['status']??'all');$department=(string)($_GET['department']??'all');
$where=['1=1'];$params=[];
if($q!==''){$where[]="REPLACE(REPLACE(REPLACE(i.name,'ي','ی'),'ى','ی'),'ك','ک') LIKE ?";$params[]='%'.normalize_persian_search($q).'%';}
if($status==='review')$where[]="i.review_status='needs_review' AND i.active=1";
elseif($status==='inactive')$where[]='i.active=0';
elseif($status==='negative')$where[]='i.active=1 AND COALESCE(b.quantity_base,0)<0';
elseif($status==='low')$where[]='i.active=1 AND COALESCE(b.quantity_base,0)>=0 AND i.warning_threshold>0 AND COALESCE(b.quantity_base,0)<=i.warning_threshold';
elseif($status==='missing_cost')$where[]="i.active=1 AND COALESCE(b.quantity_base,0)>0 AND (b.average_unit_cost IS NULL OR COALESCE(b.cost_status,'unknown')<>'known')";
else $where[]='i.active=1';
if(isset(inventory_department_labels()[$department])){$where[]='i.default_department=?';$params[]=$department;}
$stmt=$pdo->prepare('SELECT i.*,COALESCE(b.quantity_base,0) quantity_base,b.average_unit_cost,COALESCE(b.cost_status,\'unknown\') cost_status FROM inventory_items i LEFT JOIN inventory_balances b ON b.inventory_item_id=i.id WHERE '.implode(' AND ',$where).' ORDER BY FIELD(i.review_status,\'needs_review\',\'ready\'),i.category,i.name,i.id');$stmt->execute($params);$items=$stmt->fetchAll();
?>
<form method="get" class="inventory-filters"><div class="form-group inventory-search"><label>جست‌وجو</label><input class="form-control" type="search" inputmode="search" enterkeyhint="search" autocomplete="off" name="q" value="<?= e($q) ?>" placeholder="نام کالا"></div><div class="form-group"><label>وضعیت</label><select class="form-control" name="status" data-choice-mode="compact"><option value="all" <?= $status==='all'?'selected':'' ?>>همه کالاهای فعال</option><option value="negative" <?= $status==='negative'?'selected':'' ?>>موجودی منفی</option><option value="low" <?= $status==='low'?'selected':'' ?>>کم‌موجودی</option><?php if($canCost): ?><option value="missing_cost" <?= $status==='missing_cost'?'selected':'' ?>>هزینه ناقص</option><?php endif; ?><option value="inactive" <?= $status==='inactive'?'selected':'' ?>>غیرفعال</option></select></div><div class="form-group"><label>بخش پیش‌فرض</label><select class="form-control" name="department" data-choice-mode="compact"><option value="all">همه بخش‌ها</option><?php foreach(inventory_department_labels() as $key=>$label): ?><option value="<?= e($key) ?>" <?= $department===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><button class="btn btn-light">اعمال</button></form>
<div class="inventory-list"><?php if(!$items): ?><div class="inventory-empty">کالایی با این فیلتر پیدا نشد.</div><?php endif; ?><?php foreach($items as $item):$qty=(int)$item['quantity_base'];$low=(int)$item['warning_threshold']>0&&$qty<=(int)$item['warning_threshold'];$href=$canManage?'inventory_item_form.php?id='.(int)$item['id']:'inventory_item.php?id='.(int)$item['id']; ?>
<a class="inventory-row" href="<?= e($href) ?>"><div class="inventory-row-main"><strong><?= e((string)$item['name']) ?></strong><small><?= e(inventory_category_labels()[(string)$item['category']]??(string)$item['category']) ?> · <?= e(inventory_department_labels()[(string)$item['default_department']]??'مشترک') ?></small></div><div class="inventory-row-meta"><small>واحد موجودی</small><strong><?= e(inventory_base_unit_labels()[(string)$item['base_unit']]??(string)$item['base_unit']) ?></strong></div><div class="inventory-row-stock <?= $qty<0?'is-negative':($low?'is-low':'') ?>"><?= e(inventory_format_quantity($qty,(string)$item['base_unit'])) ?></div><div><?php if(!(int)$item['active']): ?><span class="inventory-status inactive">غیرفعال</span><?php elseif((string)$item['review_status']==='needs_review'): ?><span class="inventory-status needs-review">نیاز به تأیید</span><?php elseif($low): ?><span class="inventory-status needs-review">کم‌موجودی</span><?php endif; ?></div></a>
<?php endforeach; ?></div>

<?php elseif($tab==='movements'):
$movementType=(string)($_GET['type']??'all');$movementItemId=max(0,(int)($_GET['item']??0));$movementPage=max(1,(int)($_GET['movement_page']??1));$movementPerPage=50;$moveWhere=['1=1'];$moveParams=[];if(isset(inventory_movement_labels()[$movementType])){$moveWhere[]='m.movement_type=?';$moveParams[]=$movementType;}if($movementItemId>0){$moveWhere[]='m.inventory_item_id=?';$moveParams[]=$movementItemId;}
$movementCountStmt=$pdo->prepare('SELECT COUNT(*) FROM inventory_movements m WHERE '.implode(' AND ',$moveWhere));$movementCountStmt->execute($moveParams);$movementTotal=(int)$movementCountStmt->fetchColumn();$movementPages=max(1,(int)ceil($movementTotal/$movementPerPage));if($movementPage>$movementPages)$movementPage=$movementPages;$movementOffset=($movementPage-1)*$movementPerPage;
$stmt=$pdo->prepare('SELECT m.*,i.name item_name,u.display_name actor_name FROM inventory_movements m JOIN inventory_items i ON i.id=m.inventory_item_id LEFT JOIN users u ON u.id=m.actor_user_id WHERE '.implode(' AND ',$moveWhere).' ORDER BY m.occurred_at DESC,m.id DESC LIMIT '.$movementPerPage.' OFFSET '.$movementOffset);$stmt->execute($moveParams);$movements=$stmt->fetchAll();$movementItemName='';if($movementItemId>0){$nameStmt=$pdo->prepare('SELECT name FROM inventory_items WHERE id=?');$nameStmt->execute([$movementItemId]);$movementItemName=(string)($nameStmt->fetchColumn()?:'');}
$movementPageUrl=static function(int $target)use($movementType,$movementItemId):string{$params=['tab'=>'movements'];if(isset(inventory_movement_labels()[$movementType]))$params['type']=$movementType;if($movementItemId>0)$params['item']=$movementItemId;if($target>1)$params['movement_page']=$target;return 'inventory.php?'.http_build_query($params);}; ?>
<?php if($movementItemId>0): ?><div class="inventory-attention"><strong>سابقه <?= e($movementItemName?:'کالای انتخاب‌شده') ?></strong><p><?= fa_digits($movementTotal) ?> ثبت در سابقه انبار نمایش داده می‌شود.</p><a class="btn btn-sm btn-light" href="inventory.php?tab=movements">نمایش همه سوابق</a></div><?php endif; ?>
<form method="get" class="inventory-filters"><input type="hidden" name="tab" value="movements"><?php if($movementItemId>0): ?><input type="hidden" name="item" value="<?= $movementItemId ?>"><?php endif; ?><div class="form-group"><label>نوع ثبت</label><select class="form-control" name="type" data-choice-mode="browse"><option value="all">همه سوابق</option><?php foreach(inventory_movement_labels() as $key=>$label): ?><option value="<?= e($key) ?>" <?= $movementType===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><button class="btn btn-light">اعمال</button></form>
<div class="inventory-movement-list"><?php if(!$movements): ?><div class="inventory-empty">هنوز گردشی ثبت نشده است.</div><?php endif; ?><?php foreach($movements as $m): $movementAdjustable=$canFinalize && (bool)inventory_adjustment_allowed_modes((string)$m['movement_type']);$movementHref=$movementAdjustable?'inventory_adjustment.php?id='.(int)$m['id']:''; ?><article class="inventory-movement<?= $movementAdjustable?' is-navigable':'' ?>"<?= $movementAdjustable?' data-row-href="'.e($movementHref).'" role="link" tabindex="0" aria-label="اصلاح سابقه '.e((string)$m['item_name']).'"':'' ?>><div class="movement-main"><strong><?= e((string)$m['item_name']) ?></strong><small><?= e(inventory_movement_labels()[(string)$m['movement_type']]??(string)$m['movement_type']) ?> · <?= e(format_jalali_compact((string)$m['occurred_at'])) ?></small></div><div class="movement-qty"><strong><?= e(inventory_format_quantity((int)$m['quantity_base'],(string)$m['base_unit'])) ?></strong><small><?= e($m['department']?(inventory_department_labels()[(string)$m['department']]??(string)$m['department']):'—') ?></small></div><div class="movement-cost"><?php if($canCost): ?><strong><?= $m['total_cost_delta']!==null?e(toman(abs((int)$m['total_cost_delta']))):'هزینه نامشخص' ?></strong><small><?= e(inventory_cost_status_labels()[(string)$m['cost_status']]??'قیمت نامشخص') ?></small><?php else: ?><span class="inventory-cost-hidden">هزینه مخفی</span><?php endif; ?></div><div class="movement-action"><?php if($movementAdjustable): ?><span class="inventory-movement-chevron" aria-hidden="true"><?= ui_icon('chevron-left') ?></span><?php else: ?><small><?= e((string)($m['actor_name']?:'سامانه')) ?></small><?php endif; ?></div></article><?php endforeach; ?></div>
<?php if($movementPages>1): ?><nav class="panel-pagination" aria-label="صفحه‌بندی سابقه انبار"><a class="btn btn-light btn-sm" href="<?= e($movementPageUrl(max(1,$movementPage-1))) ?>" <?= $movementPage<=1?'aria-disabled="true" tabindex="-1"':'' ?>>قبلی</a><span>صفحه <?= fa_digits($movementPage) ?> از <?= fa_digits($movementPages) ?> · <?= fa_digits($movementTotal) ?> ثبت</span><a class="btn btn-light btn-sm" href="<?= e($movementPageUrl(min($movementPages,$movementPage+1))) ?>" <?= $movementPage>=$movementPages?'aria-disabled="true" tabindex="-1"':'' ?>>بعدی</a></nav><?php endif; ?>

<?php else:
$counts=$pdo->query("SELECT s.*,cu.display_name created_by,fu.display_name finalized_by,(SELECT COUNT(*) FROM inventory_count_lines l WHERE l.session_id=s.id AND l.actual_quantity IS NOT NULL) counted_lines,(SELECT COUNT(*) FROM inventory_count_lines l WHERE l.session_id=s.id) total_lines,(SELECT COUNT(*) FROM inventory_count_lines l WHERE l.session_id=s.id AND l.difference_base<>0) difference_lines FROM inventory_count_sessions s LEFT JOIN users cu ON cu.id=s.created_by_user_id LEFT JOIN users fu ON fu.id=s.finalized_by_user_id ORDER BY s.created_at DESC,s.id DESC LIMIT 100")->fetchAll(); ?>
<div class="inventory-toolbar"><div class="panel-copy-stack"><strong>شمارش‌های موجودی</strong><small class="muted"><?= count($counts)>=100?'۱۰۰ شمارش اخیر · ':'' ?>شمارش‌های نهایی تغییر نمی‌کنند؛ مغایرت به‌صورت یک اصلاح در سابقه انبار ثبت می‌شود.</small></div><?php if(($inventoryReady || $inventoryReconciliationRequired) && $canOps): ?><?php if($openCount): ?><a class="btn btn-primary" href="inventory_count.php?id=<?= (int)$openCount['id'] ?>"><?= ui_icon('adjust') ?> ادامه شمارش</a><?php else: ?><a class="btn btn-primary" href="inventory_count_start.php"><?= ui_icon('plus') ?> شروع شمارش</a><?php endif; ?><?php elseif(!$inventoryInitialized && $canManage): ?><a class="btn btn-primary" href="inventory_opening.php"><?= ui_icon('archive') ?> موجودی اولیه</a><?php endif; ?></div>
<div class="inventory-list"><?php if(!$counts): ?><div class="inventory-empty">هنوز شمارشی ثبت نشده است.</div><?php endif; ?><?php $countCategoryLabels=inventory_category_labels(false,$pdo); foreach($counts as $c): $countScope=(string)($c['scope_type']??'full')==='category'?'دسته: '.($countCategoryLabels[(string)($c['scope_category_key']??'')]??'دسته انتخاب‌شده'):'کل انبار'; ?><a class="inventory-row" href="inventory_count.php?id=<?= (int)$c['id'] ?>"><div class="inventory-row-main"><strong><?= e((string)$c['title']) ?></strong><small><?= (string)$c['session_type']==='opening'?'موجودی اولیه':('شمارش دوره‌ای · '.e($countScope)) ?> · <?= e(format_jalali_compact((string)$c['created_at'])) ?></small></div><div class="inventory-row-meta"><small>پیشرفت</small><strong><?= fa_digits((int)$c['counted_lines']) ?> از <?= fa_digits((int)$c['total_lines']) ?></strong></div><div class="inventory-row-meta"><small>مغایرت</small><strong><?= fa_digits((int)$c['difference_lines']) ?> قلم</strong></div><div><span class="inventory-status <?= (string)$c['status']==='draft'?'needs-review':((string)$c['status']==='cancelled'?'inactive':'') ?>"><?= (string)$c['status']==='draft'?'در حال شمارش':((string)$c['status']==='cancelled'?'لغوشده':'نهایی‌شده') ?></span></div></a><?php endforeach; ?></div>
<?php endif; ?>
<?php panel_footer(); ?>
