<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
sokna_module_require('inventory');
require_login();
if (!user_can_inventory_manage()) deny_access_and_return();
require dirname(__DIR__) . '/includes/panel_layout.php';

$pdo = db();
inventory_seed_if_empty($pdo);
$userId = (int)(current_user()['id'] ?? 0);
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$copyId = (int)($_GET['copy'] ?? 0);
$returnTo = (string)($_GET['return'] ?? $_POST['return'] ?? '');
$copySource = null;
$item = null;
$units = [];

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM inventory_items WHERE id=?');
    $stmt->execute([$id]);
    $item = $stmt->fetch() ?: null;
    if (!$item) { flash('error','کالا پیدا نشد.'); redirect('inventory_items.php'); }
    $units = inventory_purchase_units($pdo,$id,false);
} elseif ($copyId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM inventory_items WHERE id=?');
    $stmt->execute([$copyId]);
    $copySource = $stmt->fetch() ?: null;
    if (!$copySource) { flash('error','کالای مبنا پیدا نشد.'); redirect('inventory_items.php'); }
    $item = $copySource;
    $item['name'] = 'کپیِ '.(string)$copySource['name'];
    $item['review_status'] = 'ready';
    $item['review_note'] = null;
    $units = inventory_purchase_units($pdo,$copyId,true);
    foreach ($units as &$unit) { $unit['id']=0; $unit['review_status']='ready'; }
    unset($unit);
}

$movementCount = 0;
if ($id > 0) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM inventory_movements WHERE inventory_item_id=?');
    $stmt->execute([$id]);
    $movementCount = (int)$stmt->fetchColumn();
}
$baseUnitLocked = $id > 0 && $movementCount > 0;

$unitAuditRows = static function(array $rows): array {
    return array_map(static fn($row) => [
        'id'=>(int)($row['id'] ?? 0),
        'name'=>(string)($row['name'] ?? ''),
        'conversion_mode'=>(string)($row['conversion_mode'] ?? ''),
        'base_quantity'=>$row['base_quantity'] === null ? null : (int)$row['base_quantity'],
        'review_status'=>(string)($row['review_status'] ?? ''),
        'active'=>(int)($row['active'] ?? 0),
    ],array_slice(array_values($rows),0,30));
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        flash('error','نشست صفحه منقضی شده است.');
        redirect($id ? 'inventory_item_form.php?id='.$id : 'inventory_item_form.php');
    }
    try {
        if ((string)($_POST['purchase_units_state'] ?? '') !== '1') throw new RuntimeException('اطلاعات واحدهای خرید کامل دریافت نشد؛ صفحه را تازه کن و دوباره ذخیره کن.');
        $name = text_substr(trim((string)($_POST['name'] ?? '')),0,160);
        if ($name === '') throw new RuntimeException('نام کالا را وارد کن.');
        $category = inventory_normalize_category((string)($_POST['category'] ?? ''));
        $department = inventory_normalize_department((string)($_POST['default_department'] ?? '')) ?? 'shared';
        $baseUnit = $baseUnitLocked ? (string)$item['base_unit'] : inventory_normalize_base_unit((string)($_POST['base_unit'] ?? 'count'));
        $threshold = inventory_major_to_base($_POST['warning_threshold_major'] ?? 0,$baseUnit);
        $reviewStatus = $id > 0 ? (string)($item['review_status'] ?? 'ready') : 'ready';
        if (!isset(inventory_review_labels()[$reviewStatus])) $reviewStatus = 'needs_review';
        $reviewNote = $id > 0 ? text_substr(trim((string)($item['review_note'] ?? '')),0,500) : '';
        $active = $id > 0 ? (int)($item['active'] ?? 1) : 1;

        if ($id > 0 && !$baseUnitLocked && (string)($item['base_unit'] ?? '') !== $baseUnit) {
            $hasDependentValues = (int)($item['warning_threshold'] ?? 0) > 0 || (bool)array_filter($units,static fn($u)=>(string)($u['conversion_mode']??'')==='fixed' && $u['base_quantity']!==null);
            if ($hasDependentValues && (string)($_POST['base_unit_change_confirmed'] ?? '') !== '1') {
                throw new RuntimeException('تغییر واحد اصلی، تبدیل بسته‌ها و حد هشدار را بازنشانی می‌کند؛ تغییر را از همین فرم تأیید کن.');
            }
        }

        $rowIds = array_values((array)($_POST['unit_id'] ?? []));
        $rowNames = array_values((array)($_POST['unit_name'] ?? []));
        $rowModes = array_values((array)($_POST['unit_mode'] ?? []));
        $rowQty = array_values((array)($_POST['unit_major_quantity'] ?? []));
        $rowActive = array_values((array)($_POST['unit_active'] ?? []));
        if (count($rowNames) > 50) throw new RuntimeException('تعداد واحدهای خرید این کالا غیرعادی است.');
        $postedUnits = [];
        $activeNameKeys = [];
        foreach ($rowNames as $index => $unitNameRaw) {
            $unitId = (int)($rowIds[$index] ?? 0);
            $unitName = text_substr(trim((string)$unitNameRaw),0,160);
            $mode = (string)($rowModes[$index] ?? 'fixed');
            $unitActive = ((string)($rowActive[$index] ?? '0')) === '1' ? 1 : 0;
            $qtyRaw = trim((string)($rowQty[$index] ?? ''));
            if ($unitId === 0 && $unitName === '' && $qtyRaw === '') continue;
            if ($unitName === '') throw new RuntimeException('نام واحد خرید را وارد کن.');
            if (!in_array($mode,['fixed','actual_quantity'],true)) throw new RuntimeException('نوع واحد خرید معتبر نیست.');
            $baseQty = null;
            if ($mode === 'fixed' && $qtyRaw !== '') {
                $baseQty = inventory_major_to_base($qtyRaw,$baseUnit);
                if ($baseQty < 1) throw new RuntimeException('مقدار داخل واحد خرید باید بیشتر از صفر باشد.');
            }
            if ($unitActive) {
                $nameKey = normalize_persian_search($unitName);
                if (isset($activeNameKeys[$nameKey])) throw new RuntimeException('دو واحد خرید فعال نمی‌توانند نام یکسان داشته باشند.');
                $activeNameKeys[$nameKey] = true;
            }
            $postedUnits[] = [
                'id'=>$unitId,
                'name'=>$unitName,
                'conversion_mode'=>$mode,
                'base_quantity'=>$baseQty,
                'active'=>$unitActive,
            ];
        }

        $pdo->beginTransaction();
        $before = null;
        if ($id > 0) {
            $lock = $pdo->prepare('SELECT * FROM inventory_items WHERE id=? FOR UPDATE');
            $lock->execute([$id]);
            $before = $lock->fetch();
            if (!$before) throw new RuntimeException('کالا پیدا نشد.');
            $used = $pdo->prepare('SELECT COUNT(*) FROM inventory_movements WHERE inventory_item_id=?');
            $used->execute([$id]);
            if ((int)$used->fetchColumn() > 0) $baseUnit = (string)$before['base_unit'];
            $pdo->prepare('UPDATE inventory_items SET name=?,category=?,base_unit=?,default_department=?,warning_threshold=?,review_status=?,review_note=?,active=? WHERE id=?')
                ->execute([$name,$category,$baseUnit,$department,$threshold,$reviewStatus,$reviewNote ?: null,$active,$id]);
            $itemId = $id;
        } else {
            $code = 'INV-'.strtoupper(substr(bin2hex(random_bytes(5)),0,10));
            $pdo->prepare('INSERT INTO inventory_items(item_code,name,category,base_unit,default_department,warning_threshold,review_status,review_note,active,created_by_user_id) VALUES(?,?,?,?,?,?,?,?,?,?)')
                ->execute([$code,$name,$category,$baseUnit,$department,$threshold,$reviewStatus,$reviewNote ?: null,$active,$userId]);
            $itemId = (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO inventory_balances(inventory_item_id,quantity_base,average_unit_cost,cost_status) VALUES(?,0,NULL,'unknown')")->execute([$itemId]);
        }

        $existingStmt = $pdo->prepare('SELECT * FROM inventory_purchase_units WHERE inventory_item_id=? ORDER BY id FOR UPDATE');
        $existingStmt->execute([$itemId]);
        $existingRows = $existingStmt->fetchAll();
        $existing = [];
        foreach ($existingRows as $unit) $existing[(int)$unit['id']] = $unit;
        $postedExistingIds = [];
        $hasUnitReview = false;

        foreach ($postedUnits as &$postedUnit) {
            $unitId = (int)$postedUnit['id'];
            $old = null;
            if ($unitId > 0) {
                if (!isset($existing[$unitId])) throw new RuntimeException('یکی از واحدهای خرید متعلق به این کالا نیست.');
                $old = $existing[$unitId];
                $postedExistingIds[$unitId] = true;
            }
            $unitReview = $old ? (string)$old['review_status'] : 'ready';
            if ((string)$postedUnit['conversion_mode'] === 'fixed' && $postedUnit['base_quantity'] === null) $unitReview = 'needs_review';
            if (!isset(inventory_review_labels()[$unitReview])) $unitReview = 'needs_review';
            $postedUnit['review_status'] = $unitReview;
            if ((int)$postedUnit['active'] === 1 && $unitReview === 'needs_review') $hasUnitReview = true;

            if ($old) {
                $referencedStmt = $pdo->prepare('SELECT COUNT(*) FROM inventory_movements WHERE purchase_unit_id=?');
                $referencedStmt->execute([$unitId]);
                $referenced = (int)$referencedStmt->fetchColumn() > 0;
                $changed = (string)$old['name'] !== (string)$postedUnit['name']
                    || (string)$old['conversion_mode'] !== (string)$postedUnit['conversion_mode']
                    || (($old['base_quantity'] === null ? null : (int)$old['base_quantity']) !== $postedUnit['base_quantity']);
                if ($referenced && $changed) {
                    $pdo->prepare('UPDATE inventory_purchase_units SET active=0 WHERE id=?')->execute([$unitId]);
                    $pdo->prepare('INSERT INTO inventory_purchase_units(inventory_item_id,name,conversion_mode,base_quantity,review_status,note,active) VALUES(?,?,?,?,?,NULL,?)')
                        ->execute([$itemId,$postedUnit['name'],$postedUnit['conversion_mode'],$postedUnit['base_quantity'],$unitReview,$postedUnit['active']]);
                    $postedUnit['id'] = (int)$pdo->lastInsertId();
                } else {
                    $pdo->prepare('UPDATE inventory_purchase_units SET name=?,conversion_mode=?,base_quantity=?,review_status=?,active=? WHERE id=?')
                        ->execute([$postedUnit['name'],$postedUnit['conversion_mode'],$postedUnit['base_quantity'],$unitReview,$postedUnit['active'],$unitId]);
                }
            } else {
                $pdo->prepare('INSERT INTO inventory_purchase_units(inventory_item_id,name,conversion_mode,base_quantity,review_status,note,active) VALUES(?,?,?,?,?,NULL,?)')
                    ->execute([$itemId,$postedUnit['name'],$postedUnit['conversion_mode'],$postedUnit['base_quantity'],$unitReview,$postedUnit['active']]);
                $postedUnit['id'] = (int)$pdo->lastInsertId();
            }
        }
        unset($postedUnit);

        // Existing rows are server-rendered. If one disappears from POST, fail closed
        // instead of silently deactivating master data because JavaScript/DOM failed.
        foreach ($existing as $unitId => $old) {
            if (!isset($postedExistingIds[$unitId])) throw new RuntimeException('اطلاعات یکی از واحدهای خرید کامل ارسال نشد؛ صفحه را تازه کن و دوباره تلاش کن.');
        }
        if ($hasUnitReview && $reviewStatus !== 'needs_review') {
            $reviewStatus = 'needs_review';
            $pdo->prepare("UPDATE inventory_items SET review_status='needs_review' WHERE id=?")->execute([$itemId]);
        }

        $afterUnitStmt = $pdo->prepare('SELECT * FROM inventory_purchase_units WHERE inventory_item_id=? ORDER BY id');
        $afterUnitStmt->execute([$itemId]);
        $afterUnits = $afterUnitStmt->fetchAll();
        audit_log_write($id > 0 ? 'inventory.item_updated' : 'inventory.item_created','inventory_item',$itemId,[
            'copied_from'=>$copyId ?: null,
            'before'=>$before ? [
                'name'=>$before['name'],'category'=>$before['category'],'base_unit'=>$before['base_unit'],
                'default_department'=>$before['default_department'],'warning_threshold'=>(int)$before['warning_threshold'],
                'review_status'=>$before['review_status'],'active'=>(int)$before['active'],
                'purchase_units'=>$unitAuditRows($existingRows),
            ] : null,
            'after'=>[
                'name'=>$name,'category'=>$category,'base_unit'=>$baseUnit,'default_department'=>$department,
                'warning_threshold'=>$threshold,'review_status'=>$reviewStatus,'active'=>$active,
                'purchase_units'=>$unitAuditRows($afterUnits),
            ],
        ],$userId);
        $pdo->commit();
        flash('success',$id > 0 ? 'تغییرات کالا ذخیره شد.' : 'کالای جدید ساخته شد؛ موجودی و تاریخچه مستقل دارد.');
        redirect($returnTo === 'review' ? 'inventory_review.php?id='.$itemId : 'inventory_item_form.php?id='.$itemId);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('inventory item save: '.$e->getMessage());
        flash('error',$e->getCode()==='23000' ? 'اطلاعات تکراری یا نامعتبر است؛ نام کالا و واحدهای خرید را بررسی کن.' : 'اطلاعات کالا ذخیره نشد. دوباره تلاش کن.');
    } catch (RuntimeException|InvalidArgumentException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error',$e->getMessage());
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('inventory item save: '.$e->getMessage());
        flash('error','ذخیره کالا انجام نشد. دوباره تلاش کن.');
    }
    $item = array_merge($item ?? [],[
        'name'=>$name ?? '', 'category'=>$category ?? 'ingredient','base_unit'=>$baseUnit ?? 'count',
        'default_department'=>$department ?? 'shared','warning_threshold'=>$threshold ?? 0,
        'review_status'=>$reviewStatus ?? 'ready','review_note'=>$reviewNote ?? null,'active'=>$active ?? 1,
    ]);
    if (isset($postedUnits)) $units = $postedUnits;
}

$activeCategoryLabels = inventory_category_labels(true,$pdo);
$allCategoryLabels = inventory_category_labels(false,$pdo);
$currentCategory = (string)($item['category'] ?? 'ingredient');
if (isset($allCategoryLabels[$currentCategory])) $activeCategoryLabels[$currentCategory] = $allCategoryLabels[$currentCategory];
$currentBaseUnit = (string)($item['base_unit'] ?? 'count');
$majorLabel = inventory_major_unit_label($currentBaseUnit);
$unitUiState = static function(array $unit): array {
    if ((int)($unit['active'] ?? 1) !== 1) return ['label'=>'غیرفعال','class'=>''];
    if ((string)($unit['conversion_mode'] ?? 'fixed') === 'fixed' && (int)($unit['base_quantity'] ?? 0) < 1) return ['label'=>'نیازمند تکمیل','class'=>'needs-review'];
    if ((string)($unit['review_status'] ?? 'ready') === 'needs_review') return ['label'=>'نیازمند تأیید','class'=>'needs-review'];
    return ['label'=>'آماده','class'=>'ready'];
};
$title = $id ? 'ویرایش کالای انبار' : ($copySource ? 'کپی به‌عنوان کالای جدید' : 'کالای جدید');
panel_header($title,'inventory');
?>
<?php if($copySource): ?><div class="alert alert-info duplicate-context"><?= ui_icon('copy') ?><div><strong>کپی از «<?= e((string)$copySource['name']) ?>»</strong><p>فقط مشخصات پایه و واحدهای خرید کپی می‌شوند؛ موجودی، میانگین هزینه، گردش و تاریخچه منتقل نمی‌شوند.</p></div></div><?php endif; ?>
<section class="card"><div class="card-body"><form method="post" class="form-grid" id="inventoryItemForm" data-inventory-flow><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="return" value="<?= e($returnTo) ?>"><input type="hidden" name="purchase_units_state" value="1"><input type="hidden" name="base_unit_change_confirmed" id="baseUnitChangeConfirmed" value="0">
<?php if((string)($item['review_status']??'ready')==='needs_review'): ?><div class="inventory-attention full"><strong>نیاز به تأیید</strong><p>اطلاعات کالا را بررسی و کامل کن. پس از ذخیره می‌توانی آن را تأیید کنی.</p></div><?php endif; ?>
<div class="form-group"><label>نام کالا</label><input class="form-control" name="name" value="<?= e((string)($item['name']??'')) ?>" maxlength="160" required autocomplete="off" enterkeyhint="next" data-inventory-step <?= $id<1?'data-inventory-autofocus':'' ?>></div>
<div class="form-group"><label>دسته</label><select class="form-control" name="category" data-choice-mode="adaptive" data-inventory-step data-inventory-next-target="[name=default_department]"><?php foreach($activeCategoryLabels as $key=>$label): ?><option value="<?= e($key) ?>" <?= (string)($item['category']??'ingredient')===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label>بخش پیش‌فرض</label><select class="form-control" name="default_department" data-choice-mode="compact" data-inventory-step data-inventory-next-target="<?= $baseUnitLocked?'[name=warning_threshold_major]':'[name=base_unit]' ?>"><?php foreach(inventory_department_labels() as $key=>$label): ?><option value="<?= e($key) ?>" <?= (string)($item['default_department']??'shared')===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select><small class="muted">فقط انتخاب اولیه را مشخص می‌کند؛ موجودی بین همه بخش‌ها مشترک است.</small></div>
<div class="form-group"><label>واحد ثبت موجودی</label><?php if($baseUnitLocked): ?><input type="hidden" name="base_unit" value="<?= e((string)$item['base_unit']) ?>"><input class="form-control" value="<?= e(inventory_base_unit_labels()[(string)$item['base_unit']]??(string)$item['base_unit']) ?>" disabled><small class="muted">پس از ثبت اولین ورود یا مصرف، این واحد دیگر قابل تغییر نیست.</small><?php else: ?><select class="form-control" name="base_unit" data-choice-mode="compact" data-inventory-step data-inventory-next-target="[name=warning_threshold_major]"><?php foreach(inventory_base_unit_labels() as $key=>$label): ?><option value="<?= e($key) ?>" <?= $currentBaseUnit===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select><small class="muted">ورود، مصرف و موجودی با این واحد ثبت می‌شوند.</small><?php endif; ?></div>
<div class="form-group"><label id="warningThresholdLabel">هشدار کمبود (<?= e($majorLabel) ?>)</label><input class="form-control" type="text" inputmode="decimal" enterkeyhint="done" autocomplete="off" name="warning_threshold_major" id="warningThresholdInput" data-inventory-step value="<?= e(numeric_input_display_value(inventory_base_to_major_value((int)($item['warning_threshold']??0),$currentBaseUnit))) ?>"><small class="muted">وقتی موجودی به این مقدار یا کمتر برسد هشدار نمایش داده می‌شود؛ صفر یعنی بدون هشدار.</small></div>

<div class="form-group full"><div class="form-section-header"><div class="panel-copy-stack"><label>واحدهای خرید</label><small class="muted">مثلاً شل، کارتن یا دبه. اگر مقدار داخل بسته ثابت است آن را مشخص کن؛ اگر متغیر است هنگام تحویل وارد می‌شود.</small></div><button class="btn btn-sm btn-light" type="button" id="addPurchaseUnit"><?= ui_icon('plus') ?> افزودن واحد خرید</button></div><div class="inventory-purchase-unit-list" id="purchaseUnitRows">
<?php if(!$units): ?><div class="recipe-empty-note" data-unit-empty>واحد خرید جداگانه‌ای تعریف نشده؛ ورود مستقیم با واحد ثبت موجودی ممکن است.</div><?php endif; ?>
<?php foreach($units as $index=>$unit): $ui=$unitUiState($unit); $mode=(string)($unit['conversion_mode']??'fixed'); $baseQtyMajor=$unit['base_quantity']===null?'':inventory_base_to_major_value((int)$unit['base_quantity'],$currentBaseUnit); ?>
<details class="inventory-purchase-unit-editor" data-unit-row <?= (int)($unit['id']??0)===0?'open':'' ?>><summary><span><strong data-unit-summary-name><?= e((string)($unit['name']??'واحد خرید جدید')) ?></strong><small data-unit-summary-copy><?= $mode==='actual_quantity'?'مقدار واقعی هنگام ورود ثبت می‌شود':($baseQtyMajor!==''?'هر واحد = '.e($baseQtyMajor).' '.e($majorLabel):'مقدار داخل واحد را تکمیل کن') ?></small></span><span class="inventory-unit-summary-status <?= e($ui['class']) ?>" data-unit-status><?= e($ui['label']) ?></span><span class="inventory-unit-chevron" aria-hidden="true"><?= ui_icon('chevron-down') ?></span></summary><div class="inventory-unit-editor-body form-grid"><input type="hidden" name="unit_id[]" value="<?= (int)($unit['id']??0) ?>"><input type="hidden" name="unit_active[]" value="<?= (int)($unit['active']??1)===1?'1':'0' ?>" data-unit-active><input type="hidden" value="<?= e((string)($unit['review_status']??'ready')) ?>" data-unit-review>
<div class="form-group"><label>نام واحد خرید</label><input class="form-control unit-name" name="unit_name[]" value="<?= e((string)($unit['name']??'')) ?>" placeholder="مثلاً شل" required autocomplete="off" enterkeyhint="next" data-inventory-step></div>
<div class="form-group"><label>مقدار داخل واحد خرید</label><select class="form-control unit-mode" name="unit_mode[]" data-choice-mode="compact"><option value="fixed" <?= $mode==='fixed'?'selected':'' ?>>همیشه ثابت است</option><option value="actual_quantity" <?= $mode==='actual_quantity'?'selected':'' ?>>در هر خرید متفاوت است</option></select></div>
<div class="form-group unit-qty" <?= $mode==='actual_quantity'?'hidden':'' ?>><label class="unit-qty-label">هر «<span data-unit-inline-name><?= e((string)($unit['name']??'واحد خرید')) ?></span>» چند <span data-unit-major-label><?= e($majorLabel) ?></span> است؟</label><input class="form-control unit-qty-input" type="text" inputmode="<?= $currentBaseUnit==='count'?'numeric':'decimal' ?>" enterkeyhint="done" autocomplete="off" name="unit_major_quantity[]" value="<?= e($baseQtyMajor) ?>" <?= $mode==='fixed'?'required':'' ?> data-inventory-step></div>
<div class="form-group full inventory-unit-actions"><button class="btn btn-light unit-toggle" type="button" data-unit-toggle><?= (int)($unit['id']??0)===0?'حذف این واحد':((int)($unit['active']??1)===1?'غیرفعال‌کردن':'فعال‌کردن') ?></button><small class="muted" data-unit-help><?= $ui['label']==='نیازمند تکمیل'?'تا تکمیل تبدیل، در فرم ورود کالا نمایش داده نمی‌شود.':($ui['label']==='نیازمند تأیید'?'پس از تأیید اطلاعات کالا قابل استفاده می‌شود.':'') ?></small></div>
</div></details>
<?php endforeach; ?>
</div></div>
<template id="purchaseUnitTemplate"><details class="inventory-purchase-unit-editor" data-unit-row open><summary><span><strong data-unit-summary-name>واحد خرید جدید</strong><small data-unit-summary-copy>مقدار داخل واحد را تکمیل کن</small></span><span class="inventory-unit-summary-status needs-review" data-unit-status>نیازمند تکمیل</span><span class="inventory-unit-chevron" aria-hidden="true"><?= ui_icon('chevron-down') ?></span></summary><div class="inventory-unit-editor-body form-grid"><input type="hidden" name="unit_id[]" value="0"><input type="hidden" name="unit_active[]" value="1" data-unit-active><input type="hidden" value="ready" data-unit-review><div class="form-group"><label>نام واحد خرید</label><input class="form-control unit-name" name="unit_name[]" value="" placeholder="مثلاً شل" required autocomplete="off" enterkeyhint="next" data-inventory-step></div><div class="form-group"><label>مقدار داخل واحد خرید</label><select class="form-control unit-mode" name="unit_mode[]" data-choice-mode="compact"><option value="fixed" selected>همیشه ثابت است</option><option value="actual_quantity">در هر خرید متفاوت است</option></select></div><div class="form-group unit-qty"><label class="unit-qty-label">هر «<span data-unit-inline-name>واحد خرید</span>» چند <span data-unit-major-label><?= e($majorLabel) ?></span> است؟</label><input class="form-control unit-qty-input" type="text" inputmode="<?= $currentBaseUnit==='count'?'numeric':'decimal' ?>" enterkeyhint="done" autocomplete="off" name="unit_major_quantity[]" value="" required data-inventory-step></div><div class="form-group full inventory-unit-actions"><button class="btn btn-light unit-toggle" type="button" data-unit-toggle>حذف این واحد</button><small class="muted" data-unit-help>تا تکمیل تبدیل، در فرم ورود کالا نمایش داده نمی‌شود.</small></div></div></details></template>
<div class="form-group full actions inventory-sticky-actions"><button class="btn btn-primary"><?= $returnTo==='review'?'ذخیره و بازگشت به تأیید':'ذخیره تغییرات' ?></button><a class="btn btn-light" href="<?= $returnTo==='review'?'inventory_review.php?id='.$id:'inventory_items.php' ?>">انصراف</a></div>
</form></div></section>
<script>
(()=>{
 const box=document.getElementById('purchaseUnitRows'),add=document.getElementById('addPurchaseUnit'),template=document.getElementById('purchaseUnitTemplate'),baseSelect=document.querySelector('select[name="base_unit"]'),thresholdLabel=document.getElementById('warningThresholdLabel'),thresholdInput=document.getElementById('warningThresholdInput'),baseConfirmed=document.getElementById('baseUnitChangeConfirmed');
 if(!box||!add||!template)return;
 let currentBase=<?= json_script($currentBaseUnit) ?>,restoringBase=false;
 const majorLabel=u=>u==='g'?'کیلوگرم':(u==='ml'?'لیتر':'عدد');
 const activeRows=()=>[...box.querySelectorAll('[data-unit-row]')];
 const numericValue=value=>{const normalized=window.CafeUI?.digits?.normalizeNumberText?.(value)??String(value??'');const clean=String(normalized).replace(/[٬,\s]/g,'').replace('٫','.');const number=Number(clean);return Number.isFinite(number)?number:0;};
 const stateOf=row=>({id:Number(row.querySelector('[name="unit_id[]"]')?.value||0),active:row.querySelector('[data-unit-active]')?.value==='1',mode:row.querySelector('.unit-mode')?.value||'fixed',qty:String(row.querySelector('.unit-qty-input')?.value||'').trim(),review:String(row.querySelector('[data-unit-review]')?.value||'ready')});
 const syncRow=row=>{
   const nameInput=row.querySelector('.unit-name'),mode=row.querySelector('.unit-mode'),qtyWrap=row.querySelector('.unit-qty'),qtyInput=row.querySelector('.unit-qty-input'),activeInput=row.querySelector('[data-unit-active]'),reviewInput=row.querySelector('[data-unit-review]'),status=row.querySelector('[data-unit-status]'),help=row.querySelector('[data-unit-help]'),toggle=row.querySelector('[data-unit-toggle]');
   const name=String(nameInput?.value||'').trim(),isActive=activeInput?.value==='1',isFixed=mode?.value!=='actual_quantity',qty=String(qtyInput?.value||'').trim(),review=String(reviewInput?.value||'ready');
   if(qtyWrap)qtyWrap.hidden=!isFixed;if(qtyInput){qtyInput.required=isFixed;qtyInput.inputMode=currentBase==='count'?'numeric':'decimal';}
   row.querySelectorAll('[data-unit-major-label]').forEach(el=>el.textContent=majorLabel(currentBase));
   row.querySelectorAll('[data-unit-inline-name]').forEach(el=>el.textContent=name||'واحد خرید');
   const summaryName=row.querySelector('[data-unit-summary-name]'),summaryCopy=row.querySelector('[data-unit-summary-copy]');
   if(summaryName)summaryName.textContent=name||'واحد خرید جدید';
   if(summaryCopy)summaryCopy.textContent=!isFixed?'مقدار واقعی هنگام ورود ثبت می‌شود':(qty?`هر واحد = ${qty} ${majorLabel(currentBase)}`:'مقدار داخل واحد را تکمیل کن');
   let label='آماده',kind='ready',helpText='';
   if(!isActive){label='غیرفعال';kind='';}
   else if(isFixed&&!qty){label='نیازمند تکمیل';kind='needs-review';helpText='تا تکمیل تبدیل، در فرم ورود کالا نمایش داده نمی‌شود.';}
   else if(review==='needs_review'){label='نیازمند تأیید';kind='needs-review';helpText=isFixed?'پس از تأیید اطلاعات کالا قابل استفاده می‌شود.':'';}
   if(status){status.textContent=label;status.className=`inventory-unit-summary-status ${kind}`.trim();}
   if(help)help.textContent=helpText;
   if(toggle)toggle.textContent=stateOf(row).id===0?'حذف این واحد':(isActive?'غیرفعال‌کردن':'فعال‌کردن');
 };
 const bind=row=>{
   if(row.dataset.unitBound==='1')return;row.dataset.unitBound='1';
   row.querySelector('.unit-name')?.addEventListener('input',()=>syncRow(row));
   row.querySelector('.unit-qty-input')?.addEventListener('input',()=>syncRow(row));
   row.querySelector('.unit-mode')?.addEventListener('change',()=>{syncRow(row);if(row.querySelector('.unit-mode')?.value==='fixed'&&!window.CafeUI?.keyboard?.isTouchContext?.())requestAnimationFrame(()=>row.querySelector('.unit-qty-input')?.focus());});
   row.querySelector('[data-unit-toggle]')?.addEventListener('click',()=>{const id=Number(row.querySelector('[name="unit_id[]"]')?.value||0);if(id===0){row.remove();if(!activeRows().length){const empty=document.createElement('div');empty.className='recipe-empty-note';empty.dataset.unitEmpty='';empty.textContent='واحد خرید جداگانه‌ای تعریف نشده؛ ورود مستقیم با واحد ثبت موجودی ممکن است.';box.appendChild(empty);}return;}const active=row.querySelector('[data-unit-active]');active.value=active.value==='1'?'0':'1';syncRow(row);});
   syncRow(row);
 };
 activeRows().forEach(bind);
 add.addEventListener('click',()=>{box.querySelector('[data-unit-empty]')?.remove();const row=template.content.firstElementChild.cloneNode(true);box.appendChild(row);bind(row);if(!window.CafeUI?.keyboard?.isTouchContext?.())requestAnimationFrame(()=>row.querySelector('.unit-name')?.focus());});
 baseSelect?.addEventListener('change',async()=>{
   if(restoringBase)return;const next=baseSelect.value||'count';if(next===currentBase)return;
   const hasDependent=activeRows().some(row=>row.querySelector('.unit-mode')?.value==='fixed'&&numericValue(row.querySelector('.unit-qty-input')?.value||'')>0)||numericValue(thresholdInput?.value||'')>0;
   if(hasDependent){const accepted=await CafeUI.confirm('با تغییر واحد ثبت موجودی، مقدار بسته‌های ثابت و حد هشدار باید دوباره وارد شوند.','تغییر واحد ثبت موجودی؟',{okLabel:'تغییر واحد'});if(!accepted){restoringBase=true;baseSelect.value=currentBase;baseSelect.dispatchEvent(new Event('input',{bubbles:true}));baseSelect.dispatchEvent(new Event('change',{bubbles:true}));restoringBase=false;return;}}
   currentBase=next;if(baseConfirmed)baseConfirmed.value='1';
   activeRows().forEach(row=>{if(row.querySelector('.unit-mode')?.value==='fixed')row.querySelector('.unit-qty-input').value='';syncRow(row);});
   if(thresholdInput)thresholdInput.value='۰';if(thresholdLabel)thresholdLabel.textContent=`هشدار کمبود (${majorLabel(currentBase)})`;
 });
})();
</script>
<?php panel_footer(); ?>
