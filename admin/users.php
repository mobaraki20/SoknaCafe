<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login(['admin']);
require dirname(__DIR__) . '/includes/panel_layout.php';

$capabilityLabels = capability_definitions();
$inventoryConfigured = sokna_module_configured_enabled('inventory');
$responsibilityDefinitions = team_responsibility_definitions();
$currentUser = current_user();
$currentUserId = (int)($currentUser['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $id = (int)($_POST['id'] ?? 0);
    $requestedId = $id;
    $action = (string)($_POST['action'] ?? 'save');
    $hadError = false;

    try {
        $existing = null;
        if ($id > 0) {
            $existingStmt = db()->prepare('SELECT id,username,display_name,role,active FROM users WHERE id=? LIMIT 1');
            $existingStmt->execute([$id]);
            $existing = $existingStmt->fetch() ?: null;
            if (!$existing) throw new RuntimeException('حساب پیدا نشد.');
        }

        if ($action === 'save') {
            $existingCapabilities = $existing ? user_capabilities($id) : [];
            $preservedHiddenInventoryCapabilities = !$inventoryConfigured
                ? array_values(array_intersect(inventory_capability_keys(), $existingCapabilities))
                : [];
            $username = text_substr(trim((string)($_POST['username'] ?? '')), 0, 80);
            $displayName = text_substr(trim((string)($_POST['display_name'] ?? '')), 0, 120);
            $active = isset($_POST['active']) ? 1 : 0;
            $password = (string)($_POST['password'] ?? '');
            $selectedResponsibilities = array_values(array_intersect(
                array_keys($responsibilityDefinitions),
                array_map('strval', (array)($_POST['responsibilities'] ?? []))
            ));
            $inventoryCost = isset($_POST['inventory_cost_view'])
                && in_array('inventory_purchase', $selectedResponsibilities, true);
            $selected = team_capabilities_from_responsibilities($selectedResponsibilities, $inventoryCost);
            if ($preservedHiddenInventoryCapabilities) {
                $selected = array_values(array_unique(array_merge($selected, $preservedHiddenInventoryCapabilities)));
            }
            $selectedPreparationAreas = array_values(array_intersect(
                array_keys(preparation_operational_areas()),
                array_map('strval', (array)($_POST['preparation_areas'] ?? []))
            ));
            $role = (string)($existing['role'] ?? 'operator');
            $isAdminAccount = $role === 'admin';
            if ($isAdminAccount) {
                $selectedResponsibilities = array_keys($responsibilityDefinitions);
                $selected = array_keys($capabilityLabels);
                $selectedPreparationAreas = array_keys(preparation_operational_areas());
            }

            if ($username === '' || $displayName === '') {
                throw new RuntimeException('نام و اطلاعات ورود را کامل کن.');
            }
            if ($active === 1 && !$isAdminAccount && !$selectedResponsibilities && !$preservedHiddenInventoryCapabilities) {
                throw new RuntimeException('برای حساب فعال، حداقل یک مسئولیت انتخاب کن.');
            }
            if (in_array('preparation', $selectedResponsibilities, true) && !$selectedPreparationAreas) {
                throw new RuntimeException('برای مسئولیت آماده‌سازی، آشپزخانه، بار یا هر دو را انتخاب کن.');
            }
            if ($id === $currentUserId && $active !== 1) {
                throw new RuntimeException('حسابی که با آن وارد شده‌ای نباید غیرفعال شود.');
            }
            if ($id > 0 && $password !== '' && text_length($password) < 8) {
                throw new RuntimeException('رمز تازه حداقل ۸ کاراکتر باشد.');
            }
            if ($id === 0 && text_length($password) < 8) {
                throw new RuntimeException('برای حساب تازه یک رمز حداقل ۸ کاراکتری وارد کن.');
            }

            $beforeCapabilities = $existingCapabilities;
            $beforeResponsibilities = team_responsibilities_from_capabilities($beforeCapabilities);
            $beforePreparationAreas = $existing ? user_preparation_areas($id) : [];
            $pdo = db();
            $pdo->beginTransaction();
            if ($id > 0) {
                if ($password !== '') {
                    $stmt = $pdo->prepare('UPDATE users SET username=?,display_name=?,active=?,password_hash=? WHERE id=?');
                    $stmt->execute([$username,$displayName,$active,password_hash($password,PASSWORD_DEFAULT),$id]);
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET username=?,display_name=?,active=? WHERE id=?');
                    $stmt->execute([$username,$displayName,$active,$id]);
                }
            } else {
                $stmt = $pdo->prepare('INSERT INTO users(username,password_hash,display_name,role,active) VALUES(?,?,?,?,?)');
                $stmt->execute([$username,password_hash($password,PASSWORD_DEFAULT),$displayName,'operator',$active]);
                $id = (int)$pdo->lastInsertId();
            }

            $pdo->prepare('UPDATE user_capabilities SET enabled=0 WHERE user_id=?')->execute([$id]);
            $capabilityStmt = $pdo->prepare('INSERT INTO user_capabilities(user_id,capability,enabled) VALUES(?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled)');
            foreach (array_keys($capabilityLabels) as $capability) {
                $capabilityStmt->execute([$id,$capability,in_array($capability,$selected,true) ? 1 : 0]);
            }
            $pdo->prepare('DELETE FROM user_preparation_areas WHERE user_id=?')->execute([$id]);
            if (in_array('preparation', $selected, true)) {
                $areaStmt = $pdo->prepare('INSERT INTO user_preparation_areas(user_id,area_key) VALUES(?,?)');
                foreach ($selectedPreparationAreas as $area) $areaStmt->execute([$id,$area]);
            }
            audit_log_write_strict($pdo, 'user.access_updated', 'user', $id, [
                'created' => $existing === null,
                'active_before' => $existing === null ? null : (int)$existing['active'],
                'active_after' => $active,
                'responsibilities_before' => $beforeResponsibilities,
                'responsibilities_after' => $selectedResponsibilities,
                'capabilities_before' => $beforeCapabilities,
                'capabilities_after' => $selected,
                'preparation_areas_before' => $beforePreparationAreas,
                'preparation_areas_after' => $selectedPreparationAreas,
                'password_changed' => $password !== '',
            ], $currentUserId);
            $pdo->commit();

            if ($id === $currentUserId) {
                $refresh = $pdo->prepare('SELECT * FROM users WHERE id=?');
                $refresh->execute([$id]);
                if ($updatedUser = $refresh->fetch()) login_user($updatedUser);
            }
            flash('success', 'اطلاعات حساب و مسئولیت‌ها ذخیره شد.');
        } elseif ($action === 'set_active') {
            if ($id === $currentUserId) throw new RuntimeException('حسابی که با آن وارد شده‌ای را نمی‌شود خاموش کرد.');
            $desiredRaw=(string)($_POST['desired_active']??'');
            if(!in_array($desiredRaw,['0','1'],true))throw new RuntimeException('وضعیت درخواستی معتبر نیست.');
            $desired=(int)$desiredRaw;
            $pdo = db();
            $pdo->beginTransaction();
            $lockedStmt=$pdo->prepare('SELECT id,role,active FROM users WHERE id=? FOR UPDATE');
            $lockedStmt->execute([$id]);
            $locked=$lockedStmt->fetch();
            if(!$locked)throw new RuntimeException('حساب پیدا نشد.');
            if ($desired === 1 && $locked['role'] !== 'admin' && !user_capabilities($id)) {
                throw new RuntimeException('پیش از فعال‌کردن حساب، حداقل یک مسئولیت برای آن ثبت کن.');
            }
            if((int)$locked['active']!==$desired){
                $pdo->prepare('UPDATE users SET active=? WHERE id=?')->execute([$desired,$id]);
                audit_log_write_strict($pdo, 'user.active_changed', 'user', $id, [
                    'active_before' => (int)$locked['active'],
                    'active_after' => $desired,
                ], $currentUserId);
            }
            $pdo->commit();
            flash('success', $desired ? 'حساب فعال شد.' : 'حساب غیرفعال شد و نشست باز آن در درخواست بعدی بسته می‌شود.');
        } else {
            throw new RuntimeException('عملیات معتبر نیست.');
        }
    } catch (PDOException $e) {
        $hadError = true;
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        error_log('user save: '.$e->getMessage());
        if($action==='save'){$preserved=$_POST;unset($preserved['password'],$preserved['csrf_token']);$preserved['_submitted']='1';$preserved['_active_checked']=isset($_POST['active'])?'1':'0';form_state_store('user_form_'.$requestedId,$preserved);}
        flash('error', 'نام کاربری تکراری است یا اطلاعات ذخیره نشد.');
    } catch (Throwable $e) {
        $hadError = true;
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        if(!($e instanceof RuntimeException))error_log('user action: '.$e->getMessage());
        if($action==='save'){$preserved=$_POST;unset($preserved['password'],$preserved['csrf_token']);$preserved['_submitted']='1';$preserved['_active_checked']=isset($_POST['active'])?'1':'0';form_state_store('user_form_'.$requestedId,$preserved);}
        flash('error', $e instanceof RuntimeException ? $e->getMessage() : 'اطلاعات حساب ذخیره نشد.');
    }
    $returnPath='users.php';
    if($hadError && $action==='save')$returnPath.=$requestedId>0?'?edit='.$requestedId:'?new=1';
    redirect($returnPath);
}

$edit = null;
$selectedCapabilities = [];
$selectedResponsibilities = [];
$inventoryCostSelected = false;
$selectedPreparationAreas = [];
$editId = (int)($_GET['edit'] ?? 0);
if ($editId > 0) {
    $stmt = db()->prepare('SELECT id,username,display_name,role,active FROM users WHERE id=?');
    $stmt->execute([$editId]);
    $edit = $stmt->fetch() ?: null;
    if ($edit) {
        $selectedCapabilities = user_capabilities($editId);
        $selectedResponsibilities = team_responsibilities_from_capabilities($selectedCapabilities);
        $inventoryCostSelected = in_array('inventory_cost_view', $selectedCapabilities, true);
        $selectedPreparationAreas = user_preparation_areas($editId);
    }
}
if (!$edit) {
    $editId=0;
    $selectedResponsibilities = ['orders_floor'];
    $selectedCapabilities = team_capabilities_from_responsibilities($selectedResponsibilities, false);
    $inventoryCostSelected = false;
    $selectedPreparationAreas = ['kitchen','bar'];
}
$userFormState=form_state_pull('user_form_'.$editId);
$userFormSubmitted=(string)form_old($userFormState,'_submitted','')==='1';
if($userFormSubmitted){
    if($edit)$edit=array_merge($edit,[
        'display_name'=>(string)form_old($userFormState,'display_name',$edit['display_name']??''),
        'username'=>(string)form_old($userFormState,'username',$edit['username']??''),
        'active'=>(string)form_old($userFormState,'_active_checked','0')==='1'?1:0,
    ]);
    else $edit=['id'=>0,'display_name'=>(string)form_old($userFormState,'display_name',''),'username'=>(string)form_old($userFormState,'username',''),'active'=>(string)form_old($userFormState,'_active_checked','0')==='1'?1:0,'role'=>'operator'];
    $selectedResponsibilities=array_values(array_intersect(array_keys($responsibilityDefinitions),array_map('strval',(array)form_old($userFormState,'responsibilities',[]))));
    $inventoryCostSelected=(string)form_old($userFormState,'inventory_cost_view','')==='1' && in_array('inventory_purchase',$selectedResponsibilities,true);
    $selectedCapabilities=team_capabilities_from_responsibilities($selectedResponsibilities,$inventoryCostSelected);
    $selectedPreparationAreas=array_values(array_intersect(array_keys(preparation_operational_areas()),array_map('strval',(array)form_old($userFormState,'preparation_areas',[]))));
}
$editorMode = isset($_GET['new']) || $editId > 0 || $userFormSubmitted;

$users = db()->query("SELECT id,username,display_name,role,active,created_at FROM users ORDER BY FIELD(role,'admin','operator'),display_name,id")->fetchAll();
panel_header('اعضای تیم', 'users');
?>
<div class="page-grid team-page-grid <?= $editorMode?'is-editor-mode':'is-list-only' ?>">
<section class="card team-accounts-card">
  <div class="card-head"><div><h2>حساب‌های شخصی تیم</h2><small>هر اقدام با نام صاحب همین حساب ثبت می‌شود.</small></div><div class="team-card-head-actions"><span class="badge"><?= fa_digits(count($users)) ?> حساب</span><?php if(!$editorMode): ?><a class="btn btn-sm btn-primary" href="?new=1"><?= ui_icon('plus') ?> عضو جدید</a><?php endif; ?></div></div>
  <div class="team-account-list">
  <?php foreach ($users as $member):
    $memberCapabilities = user_capabilities((int)$member['id']);
    $memberResponsibilities = team_responsibilities_from_capabilities($memberCapabilities);
    $memberPreparationAreas = user_preparation_areas((int)$member['id']);
    $memberHasCost = in_array('inventory_cost_view',$memberCapabilities,true);
  ?>
    <article class="team-account-row">
      <header><div><strong><?= e($member['display_name']) ?></strong><span class="team-account-username" dir="ltr"><?= e($member['username']) ?></span></div><span class="badge <?= $member['active'] ? 'badge-posted' : 'badge-voided' ?>"><?= $member['active'] ? 'فعال' : 'غیرفعال' ?></span></header>
      <div class="team-account-summary">
        <?php if($member['role'] === 'admin'): ?><span>مدیر سامانه · همه بخش‌ها</span><?php else: ?>
          <?php foreach($memberResponsibilities as $responsibility): ?>
            <span><?= e($responsibilityDefinitions[$responsibility]['label'] ?? $responsibility) ?><?php if($responsibility==='preparation' && $memberPreparationAreas): ?>: <?= e(implode(' و ',array_map(fn($a)=>preparation_operational_areas()[$a]??$a,$memberPreparationAreas))) ?><?php endif; ?></span>
          <?php endforeach; ?>
          <?php if($memberHasCost): ?><span>اطلاعات مالی انبار</span><?php endif; ?>
        <?php endif; ?>
      </div>
      <div class="team-account-actions"><a class="btn btn-sm btn-light" href="?edit=<?= (int)$member['id'] ?>">ویرایش</a><?php if ((int)$member['id'] !== $currentUserId): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$member['id'] ?>"><input type="hidden" name="desired_active" value="<?= $member['active'] ? '0' : '1' ?>"><button class="btn btn-sm btn-outline" name="action" value="set_active"><?= $member['active'] ? 'غیرفعال‌کردن' : 'فعال‌کردن' ?></button></form><?php endif; ?></div>
    </article>
  <?php endforeach; ?>
  </div>
</section>

<?php if($editorMode): ?>
<section class="card team-editor-card">
  <div class="card-head"><div><a class="team-editor-back" href="users.php"><?= ui_icon('chevron-right') ?> بازگشت به اعضای تیم</a><h3><?= $editId>0 ? 'ویرایش حساب' : 'عضو تازه' ?></h3></div></div>
  <div class="card-body"><form method="post" class="form-grid" id="userForm"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
    <div class="form-group full"><label>نام نمایشی</label><input class="form-control" name="display_name" value="<?= e($edit['display_name'] ?? '') ?>" maxlength="120" required></div>
    <div class="form-group full"><label>نام کاربری</label><input class="form-control ltr-input" dir="ltr" name="username" value="<?= e($edit['username'] ?? '') ?>" maxlength="80" autocomplete="off" required></div>
    <div class="form-group full"><label>مسئولیت‌های کاری</label><small class="muted">فقط بخش‌هایی را انتخاب کن که این عضو واقعاً در شیفت انجام می‌دهد.</small><?php if (!$inventoryConfigured): ?><p class="muted">انبار غیرفعال است؛ دسترسی‌های قبلی انبار پنهان می‌مانند و با ویرایش این حساب حذف نمی‌شوند.</p><?php endif; ?><?php if (($edit['role'] ?? '') === 'admin'): ?><p class="muted">مدیر سامانه به همه بخش‌ها دسترسی دارد.</p><?php endif; ?><div class="responsibility-grid">
      <?php foreach ($responsibilityDefinitions as $key => $definition): ?><label class="responsibility-card"><input type="checkbox" name="responsibilities[]" value="<?= e($key) ?>" <?= in_array($key,$selectedResponsibilities,true) ? 'checked' : '' ?> <?= ($edit['role'] ?? '') === 'admin' ? 'disabled' : '' ?>> <span><strong><?= e((string)$definition['label']) ?></strong><small><?= e((string)$definition['description']) ?></small></span></label><?php endforeach; ?>
    </div></div>
    <div class="form-group full inventory-special-access" id="inventorySpecialAccess"><label>اختیار ویژه</label><label class="special-access-row"><input type="checkbox" name="inventory_cost_view" value="1" <?= $inventoryCostSelected?'checked':'' ?> <?= ($edit['role']??'')==='admin'?'disabled':'' ?>><span><strong>مشاهده اطلاعات مالی انبار</strong><small>بهای خرید، میانگین هزینه، ارزش موجودی و هزینه ضایعات</small></span></label></div>
    <div class="form-group full preparation-area-field" id="preparationAreaField"><label>حوزه آماده‌سازی</label><small class="muted">این انتخاب ثابت است و فقط مشخص می‌کند عضو کدام سفارش‌ها را ببیند.</small><div class="capability-grid compact-area-grid"><?php foreach(preparation_operational_areas() as $areaKey=>$areaLabel): ?><label><input type="checkbox" name="preparation_areas[]" value="<?= e($areaKey) ?>" <?= in_array($areaKey,$selectedPreparationAreas,true)?'checked':'' ?> <?= ($edit['role']??'')==='admin'?'disabled':'' ?>><span><strong><?= e($areaLabel) ?></strong></span></label><?php endforeach; ?></div></div>
    <div class="form-group full"><label><?= $edit ? 'رمز تازه؛ برای حفظ رمز فعلی خالی بگذار' : 'رمز عبور' ?></label><input class="form-control ltr-input" dir="ltr" type="password" name="password" minlength="8" autocomplete="new-password" <?= $edit ? '' : 'required' ?>></div>
    <div class="form-group full"><?php if ((int)($edit['id'] ?? 0) === $currentUserId): ?><input type="hidden" name="active" value="1"><label><input type="checkbox" checked disabled> این حساب فعال باشد</label><?php else: ?><label><input type="checkbox" name="active" <?= !isset($edit['active']) || $edit['active'] ? 'checked' : '' ?>> این حساب فعال باشد</label><?php endif; ?></div>
    <div class="form-group full actions"><button class="btn btn-primary" name="action" value="save">ذخیره حساب</button><a class="btn btn-light" href="users.php">انصراف</a></div>
  </form></div>
</section>
<?php endif; ?>
</div>
<?php
$userPageScript = <<<'HTML'
<script>
document.addEventListener('DOMContentLoaded',()=>{
  const prep=document.querySelector('input[name="responsibilities[]"][value="preparation"]');
  const inventory=document.querySelector('input[name="responsibilities[]"][value="inventory_purchase"]');
  const field=document.getElementById('preparationAreaField');
  const special=document.getElementById('inventorySpecialAccess');
  const cost=special?.querySelector('input[name="inventory_cost_view"]');
  const sync=()=>{
    if(field) field.hidden=Boolean(prep && !prep.checked);
    if(special) special.hidden=Boolean(inventory && !inventory.checked);
    if(inventory && !inventory.checked && cost) cost.checked=false;
  };
  prep?.addEventListener('change',sync);
  inventory?.addEventListener('change',sync);
  sync();
});
</script>
HTML;
panel_footer($userPageScript);
?>
