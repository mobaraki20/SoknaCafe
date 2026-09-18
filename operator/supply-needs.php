<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
sokna_module_require('supply');
require_login();
if (!user_can_report_supply_needs()) deny_access_and_return();
require dirname(__DIR__) . '/includes/panel_layout.php';

$pdo=db();
inventory_seed_if_empty($pdo);
$user=current_user();$userId=(int)($user['id']??0);
$allowedDepartments=supply_need_allowed_departments($user);
if(!$allowedDepartments) deny_access_and_return();
$requestedDepartment=(string)($_GET['department']??$_POST['department']??'');
$department=in_array($requestedDepartment,$allowedDepartments,true)?$requestedDepartment:$allowedDepartments[0];
$postedDraft=[];

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf($_POST['csrf_token']??null);
    $ids=array_values((array)($_POST['inventory_item_id']??[]));
    $names=array_values((array)($_POST['free_name']??[]));
    $units=array_values((array)($_POST['base_unit']??[]));
    $quantities=array_values((array)($_POST['quantity_major']??[]));
    $notes=array_values((array)($_POST['line_note']??[]));
    $postedCount=max(count($ids),count($names),count($units),count($quantities));
    for($i=0;$i<$postedCount;$i++)$postedDraft[]=['item_id'=>(int)($ids[$i]??0),'name'=>trim((string)($names[$i]??'')),'base_unit'=>(string)($units[$i]??'count'),'quantity'=>(string)($quantities[$i]??''),'note'=>(string)($notes[$i]??'')];
    try{
        $count=max(count($ids),count($names),count($units),count($quantities));
        if($count<1) throw new RuntimeException('حداقل یک قلم به درخواست خرید اضافه کن.');
        if($count>20) throw new RuntimeException('در هر ثبت حداکثر ۲۰ مورد را یکجا ثبت کن.');
        if(!in_array($department,$allowedDepartments,true)) throw new RuntimeException('بخش انتخاب‌شده برای این حساب مجاز نیست.');
        $pdo->beginTransaction();$saved=0;
        for($i=0;$i<$count;$i++){
            $itemId=(int)($ids[$i]??0);$freeName=trim((string)($names[$i]??''));$quantity=trim((string)($quantities[$i]??''));
            if($itemId<1 && $freeName==='') continue;
            if($quantity==='') throw new RuntimeException('برای همه موارد، مقدار موردنیاز را وارد کن.');
            supply_request_upsert_locked($pdo,[
                'inventory_item_id'=>$itemId,'free_name'=>$freeName,'base_unit'=>(string)($units[$i]??'count'),
                'quantity_major'=>$quantity,'department'=>$department,'source'=>'staff','note'=>(string)($notes[$i]??''),
            ],$userId);$saved++;
        }
        if($saved<1) throw new RuntimeException('حداقل یک قلم معتبر به درخواست خرید اضافه کن.');
        $pdo->commit();flash('success',fa_digits($saved).' درخواست خرید ثبت شد.');redirect('supply-needs.php?department='.rawurlencode($department));
    }catch(RuntimeException|InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();flash('error',$e->getMessage());}
    catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('supply needs: '.$e->getMessage());flash('error','درخواست خرید ثبت نشد. دوباره تلاش کن.');}
}

$itemStmt=$pdo->prepare("SELECT i.id,i.name,i.base_unit,i.default_department,COALESCE(b.quantity_base,0) quantity_base,
    n.id open_need_id,n.requested_quantity_base,n.fulfilled_quantity_base,n.preparing_quantity_base,n.preparing_at,n.note open_need_note
    FROM inventory_items i
    LEFT JOIN inventory_balances b ON b.inventory_item_id=i.id
    LEFT JOIN inventory_supply_needs n ON n.inventory_item_id=i.id AND n.department=? AND n.status='open'
    WHERE i.active=1
    ORDER BY (i.default_department=?) DESC,i.name,i.id");
$itemStmt->execute([$department,$department]);$items=$itemStmt->fetchAll();
$openStmt=$pdo->prepare("SELECT n.*,u.display_name requester_name,pu.display_name preparing_user_name FROM inventory_supply_needs n LEFT JOIN users u ON u.id=n.created_by_user_id LEFT JOIN users pu ON pu.id=n.preparing_by_user_id WHERE n.department=? AND n.status='open' ORDER BY n.updated_at DESC,n.id DESC");
$openStmt->execute([$department]);$openNeeds=$openStmt->fetchAll();

$catalog=[];
foreach($items as $row){
    $need=$row['open_need_id']?$row:[];$uncommitted=$need?supply_need_uncommitted($need):0;$preparing=(int)($row['preparing_quantity_base']??0);
    $catalog[]=[
        'id'=>(int)$row['id'],'name'=>(string)$row['name'],'base_unit'=>(string)$row['base_unit'],'unit_label'=>inventory_major_unit_label((string)$row['base_unit']),
        'current'=>inventory_format_major_quantity((int)$row['quantity_base'],(string)$row['base_unit']),'open_need_id'=>(int)($row['open_need_id']??0),
        'open_quantity'=>$uncommitted>0?inventory_base_to_major_value($uncommitted,(string)$row['base_unit']):'',
        'open_quantity_label'=>$uncommitted>0?inventory_format_quantity($uncommitted,(string)$row['base_unit']):'',
        'preparing_quantity_label'=>$preparing>0?inventory_format_quantity($preparing,(string)$row['base_unit']):'',
        'open_note'=>(string)($row['open_need_note']??''),
    ];
}
$openPayload=[];
foreach($openNeeds as $need){$uncommitted=supply_need_uncommitted($need);$preparing=(int)($need['preparing_quantity_base']??0);$openPayload[]=[
    'id'=>(int)$need['id'],'item_id'=>(int)($need['inventory_item_id']??0),'name'=>(string)$need['item_name_snapshot'],'base_unit'=>(string)$need['base_unit'],'unit_label'=>inventory_major_unit_label((string)$need['base_unit']),
    'uncommitted_major'=>$uncommitted>0?inventory_base_to_major_value($uncommitted,(string)$need['base_unit']):'',
    'uncommitted_label'=>$uncommitted>0?inventory_format_quantity($uncommitted,(string)$need['base_unit']):'',
    'preparing_label'=>$preparing>0?inventory_format_quantity($preparing,(string)$need['base_unit']):'','note'=>(string)($need['note']??''),
];}

$section='supply_needs';
panel_header('درخواست خرید',$section);
?>
<div class="panel-surface-stack supply-needs-page">
<section class="card supply-needs-hero"><div class="card-head"><div><span class="dashboard-kicker">درخواست بخش</span><h2>چه چیزهایی باید خریداری شود؟</h2><small>اقلام لازم را ثبت کن؛ مواردی که خریدشان شروع شده است جدا می‌مانند.</small></div><?php if(user_can_manage_purchases($user)): ?><a class="btn btn-light btn-sm" href="<?= e(asset('admin/purchases.php')) ?>">رفتن به خرید</a><?php endif; ?></div>
<?php if(count($allowedDepartments)>1): ?><div class="card-body supply-department-switch" role="group" aria-label="بخش درخواست"><?php foreach($allowedDepartments as $dept): ?><a class="<?= $dept===$department?'is-active':'' ?>" href="?department=<?= e($dept) ?>"><?= e(inventory_department_labels()[$dept]??$dept) ?></a><?php endforeach; ?></div><?php endif; ?>
</section>

<section class="card"><div class="card-body supply-builder">
<label class="supply-search"><span>جست‌وجوی ماده یا کالا</span><input class="form-control" id="supplyNeedSearch" type="search" inputmode="search" enterkeyhint="search" autocomplete="off" placeholder="مثلاً سینه مرغ"></label>
<div class="supply-search-results" id="supplyNeedResults" aria-label="نتایج کالاها"></div>
<button class="supply-free-add hidden" id="supplyFreeAdd" type="button">+ درخواست خرید برای «<span></span>»</button>
<form method="post" id="supplyNeedForm" class="supply-draft-form"><?= csrf_field() ?><input type="hidden" name="department" value="<?= e($department) ?>">
<div class="supply-draft-head"><div><strong>اقلام این درخواست خرید</strong><small id="supplyDraftCount" aria-live="polite">هنوز موردی اضافه نشده</small></div><button class="btn btn-light btn-sm hidden" id="supplyDraftClear" type="button">پاک‌کردن</button></div>
<div class="supply-draft-list" id="supplyDraftList"><div class="inventory-empty" id="supplyDraftEmpty">یک کالا را جست‌وجو و انتخاب کن.</div></div>
<div class="supply-submit-bar hidden"><button class="btn btn-primary" id="supplySubmit" type="submit" disabled>ثبت درخواست خرید</button></div>
</form>
</div></section>

<?php if($openNeeds): ?><section class="card supply-open-card"><div class="card-head"><div><h2>درخواست‌های فعلی · <?= fa_digits(count($openNeeds)) ?></h2><small>برای اصلاح مقدار در انتظار خرید، ردیف را لمس کن. مقداری که «در حال خرید» است با ویرایش شما تغییر نمی‌کند.</small></div></div><div class="supply-open-list"><?php foreach($openNeeds as $need): $uncommitted=supply_need_uncommitted($need);$preparing=(int)($need['preparing_quantity_base']??0); ?>
<button class="supply-open-row" type="button" data-edit-supply-need="<?= (int)$need['id'] ?>"><div><span class="supply-open-title"><strong><?= e((string)$need['item_name_snapshot']) ?></strong><?php if($preparing>0): ?><span class="status-chip status-chip-success">در حال خرید</span><?php else: ?><span class="status-chip">در انتظار خرید</span><?php endif; ?></span><small><?php if($preparing>0): ?>در حال خرید <?= e(inventory_format_quantity($preparing,(string)$need['base_unit'])) ?><?php endif; ?><?php if($preparing>0&&$uncommitted>0): ?> · <?php endif; ?><?php if($uncommitted>0): ?>درخواست اضافه <?= e(inventory_format_quantity($uncommitted,(string)$need['base_unit'])) ?><?php endif; ?><?php if((string)$need['last_outcome']==='unavailable'): ?> · دفعه قبل تهیه نشد<?php endif; ?></small></div><span><?= $uncommitted>0?'ویرایش':'افزودن درخواست' ?></span></button>
<?php endforeach; ?></div></section><?php endif; ?>
</div>
<script>window.SOKNA_SUPPLY_CATALOG=<?= json_script($catalog) ?>;window.SOKNA_SUPPLY_OPEN=<?= json_script($openPayload) ?>;window.SOKNA_SUPPLY_DRAFT=<?= json_script($postedDraft) ?>;</script>
<?php panel_footer('<script defer src="'.e(asset('assets/js/supply-needs.js')).'"></script>'); ?>
