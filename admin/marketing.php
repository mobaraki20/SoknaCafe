<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login(['admin']);
sokna_module_require('marketing');
require dirname(__DIR__) . '/includes/panel_layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $id=(int)($_POST['id']??0);$action=(string)($_POST['action']??'');
    try {
        if ($action==='selection_mode') { $mode=in_array((string)($_POST['selection_mode']??''),['priority','rotation'],true)?(string)$_POST['selection_mode']:'priority'; $before=campaign_selection_mode(); db()->prepare("INSERT INTO settings(setting_key,setting_value) VALUES('campaign_selection_mode',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute([$mode]); if($before!==$mode)audit_log_write('marketing.selection_mode_changed','settings','campaign_selection_mode',['before'=>$before,'after'=>$mode]); flash('success','روش نمایش کمپین‌ها ذخیره شد.'); }
        elseif ($action==='set_active') { $desiredRaw=(string)($_POST['desired_active']??'');if(!in_array($desiredRaw,['0','1'],true))throw new RuntimeException('وضعیت درخواستی معتبر نیست.');$desired=(int)$desiredRaw;$pdo=db();$pdo->beginTransaction();$q=$pdo->prepare('SELECT id,title,active FROM campaigns WHERE id=? FOR UPDATE');$q->execute([$id]);$before=$q->fetch();if(!$before)throw new RuntimeException('کمپین پیدا نشد.');if((int)$before['active']!==$desired){$pdo->prepare('UPDATE campaigns SET active=? WHERE id=?')->execute([$desired,$id]);audit_log_write('marketing.campaign_active_changed','campaign',$id,['title'=>$before['title'],'active_before'=>(int)$before['active'],'active_after'=>$desired]);}$pdo->commit();flash('success',$desired?'کمپین فعال شد.':'کمپین غیرفعال شد.'); }
        elseif ($action==='delete') { $pdo=db();$pdo->beginTransaction();$q=$pdo->prepare('SELECT title,image_path FROM campaigns WHERE id=? FOR UPDATE');$q->execute([$id]);$old=$q->fetch();if(!$old)throw new RuntimeException('کمپین پیدا نشد.');$pdo->prepare('DELETE FROM campaigns WHERE id=?')->execute([$id]);audit_log_write('marketing.campaign_deleted','campaign',$id,['title'=>$old['title']]);$pdo->commit();delete_upload_path($old['image_path']?:null);flash('success','کمپین حذف شد.'); }
        else throw new RuntimeException('عملیات معتبر نیست.');
    } catch(Throwable $e) { if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack(); error_log('marketing action: '.$e->getMessage()); flash('error',safe_business_error_message($e,'این تغییر انجام نشد.')); }
    redirect('marketing.php');
}
$filter=(string)($_GET['status']??'all');if(!in_array($filter,['all','active','scheduled','ended','disabled'],true))$filter='all';
$selectionMode=campaign_selection_mode();
$rows=db()->query("SELECT * FROM campaigns ORDER BY active DESC,sort_order,id")->fetchAll();
$now=time();$eligibleSeen=0;
foreach($rows as &$row){
    $start=$row['starts_at']?strtotime((string)$row['starts_at']):null;$end=$row['ends_at']?strtotime((string)$row['ends_at']):null;
    $base=!(int)$row['active']?'disabled':($start&&$start>$now?'scheduled':($end&&$end<$now?'ended':'active'));
    $row['_filter_state']=$base;
    if($base==='active'){
        if($selectionMode==='rotation')$row['_state']='rotating';
        else{$row['_state']=$eligibleSeen===0?'displaying':'queued';$eligibleSeen++;}
    }else{$row['_state']=$base;}
}unset($row);
$counts=array_fill_keys(['all','active','scheduled','ended','disabled'],0);$counts['all']=count($rows);foreach($rows as $row)$counts[$row['_filter_state']]++;
if($filter!=='all')$rows=array_values(array_filter($rows,fn($row)=>$row['_filter_state']===$filter));
$labels=['all'=>'همه','active'=>'فعال','scheduled'=>'زمان‌بندی‌شده','ended'=>'پایان‌یافته','disabled'=>'غیرفعال'];
$stateLabels=['displaying'=>'در حال نمایش','queued'=>'فعال · منتظر نمایش','rotating'=>'فعال · چرخشی','scheduled'=>'زمان‌بندی‌شده','ended'=>'پایان‌یافته','disabled'=>'غیرفعال'];
panel_header('پیشنهادها و کمپین‌ها','marketing');
?>
<div class="toolbar"><div class="filter-pills" aria-label="فیلتر کمپین‌ها"><?php foreach($labels as $key=>$label): ?><a class="<?= $filter===$key?'active':'' ?>" href="?status=<?= e($key) ?>"><?= e($label) ?><span><?= e(fa_digits($counts[$key])) ?></span></a><?php endforeach; ?></div><a class="btn btn-primary" href="campaign_form.php">کمپین جدید</a></div>
<section class="card" style="margin-bottom:14px"><div class="card-head"><div><h2>روش انتخاب کمپین</h2><small>در منوی مهمان همیشه فقط یک کمپین دیده می‌شود.</small></div></div><div class="card-body"><form method="post" class="form-grid"><?= csrf_field() ?><div class="form-group full"><div class="check-row" role="radiogroup" aria-label="روش انتخاب کمپین"><label><input type="radio" name="selection_mode" value="priority" <?= $selectionMode==='priority'?'checked':'' ?>> اولویت</label><label><input type="radio" name="selection_mode" value="rotation" <?= $selectionMode==='rotation'?'checked':'' ?>> چرخشی</label></div><small class="muted">اولویت: کمپین با اولویت بالاتر نمایش داده می‌شود. چرخشی: نمایش بین کمپین‌های هم‌زمان تقسیم می‌شود و برای همان مهمان بی‌دلیل تغییر نمی‌کند.</small></div><div class="form-group full panel-action-bar"><button class="btn btn-primary" name="action" value="selection_mode">ذخیره روش نمایش</button></div></form></div></section>
<section class="card"><div class="card-head"><div><h2>کمپین‌ها</h2><small>چند کمپین می‌توانند هم‌زمان فعال باشند؛ منوی مهمان فقط یکی را نمایش می‌دهد.</small></div></div><div class="campaign-list-v1292">
<?php if(!$rows): ?><div class="empty-state">کمپینی در این وضعیت وجود ندارد.</div><?php endif; ?>
<?php foreach($rows as $c): ?><article class="campaign-row-v1292"><?php if($c['image_path']): ?><img src="<?= e(asset($c['image_path'])) ?>" loading="lazy" alt=""><?php else: ?><div class="campaign-row-placeholder"><?= ui_icon('megaphone') ?></div><?php endif; ?><div class="campaign-row-copy"><div><strong><?= e($c['title']) ?></strong><span class="badge campaign-state-<?= e($c['_state']) ?>"><?= e($stateLabels[$c['_state']]??$c['_state']) ?></span></div><p><?= e($c['body']?:'بدون متن کوتاه') ?></p><small><?= $c['starts_at']?e(format_jalali_compact((string)$c['starts_at'])):'شروع آزاد' ?><?= $c['ends_at']?' تا '.e(format_jalali_compact((string)$c['ends_at'])):'' ?></small></div><div class="actions"><a class="btn btn-sm btn-light" href="campaign_form.php?id=<?= (int)$c['id'] ?>">ویرایش</a><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="desired_active" value="<?= (int)$c['active']===1?'0':'1' ?>"><button class="btn btn-sm btn-outline" name="action" value="set_active"><?= (int)$c['active']?'غیرفعال‌کردن':'فعال‌کردن' ?></button></form><form method="post" data-confirm="این کمپین حذف می‌شود و دیگر در منوی مهمان نمایش داده نمی‌شود." data-confirm-title="حذف کمپین؟" data-confirm-ok="حذف کمپین" data-confirm-danger="1"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-danger" name="action" value="delete">حذف</button></form></div></article><?php endforeach; ?>
</div></section>
<?php panel_footer(); ?>
