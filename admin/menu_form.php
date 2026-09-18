<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login(['admin']);
require dirname(__DIR__) . '/includes/panel_layout.php';

$pdo=db();
$id=(int)($_GET['id']??$_POST['id']??0);
$menu=null;
if($id){$st=$pdo->prepare('SELECT * FROM menus WHERE id=?');$st->execute([$id]);$menu=$st->fetch()?:null;if(!$menu)render_recovery_error_page(404,'منو پیدا نشد','ممکن است منو حذف شده باشد.','items.php','بازگشت به مدیریت منو');}
if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf($_POST['csrf_token']??null);
    try{
        $name=text_substr(trim((string)($_POST['name']??'')),0,120);
        $status=(string)($_POST['status']??'draft');
        $days=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['schedule_days']??[])),static fn(int $d):bool=>$d>=1&&$d<=7)));
        sort($days);$scheduleDays=$days?implode(',',$days):null;
        $dailyStart=trim((string)($_POST['daily_start']??''));$dailyEnd=trim((string)($_POST['daily_end']??''));
        $timeRx='/^(?:[01]\\d|2[0-3]):[0-5]\\d$/';
        if($name==='')throw new RuntimeException('نام منو الزامی است.');
        if(!menu_catalog_valid_menu_status($status))throw new RuntimeException('وضعیت منو معتبر نیست.');
        if(($dailyStart==='')!==($dailyEnd===''))throw new RuntimeException('برای ساعت سرو، شروع و پایان را با هم وارد کنید.');
        if($dailyStart!==''&&(!preg_match($timeRx,$dailyStart)||!preg_match($timeRx,$dailyEnd)))throw new RuntimeException('ساعت سرو معتبر نیست.');
        $dailyStart=$dailyStart!==''?$dailyStart:null;$dailyEnd=$dailyEnd!==''?$dailyEnd:null;
        $pdo->beginTransaction();
        if($id){
            $lock=$pdo->prepare('SELECT id,menu_key,name,status FROM menus WHERE id=? FOR UPDATE');$lock->execute([$id]);$before=$lock->fetch();if(!$before)throw new RuntimeException('منو پیدا نشد.');
            $pdo->prepare('UPDATE menus SET name=?,status=?,schedule_days=?,daily_start=?,daily_end=? WHERE id=?')->execute([$name,$status,$scheduleDays,$dailyStart,$dailyEnd,$id]);
            audit_log_write_strict($pdo,'menu.definition_updated','menu',$id,['menu_key'=>$before['menu_key'],'name'=>$name,'status'=>$status],(int)(current_user()['id']??0));
        }else{
            $key=menu_catalog_new_menu_key($pdo);$sort=(int)$pdo->query('SELECT COALESCE(MAX(sort_order),0)+10 FROM menus')->fetchColumn();
            $pdo->prepare('INSERT INTO menus(menu_key,name,status,sort_order,schedule_days,daily_start,daily_end) VALUES(?,?,?,?,?,?,?)')->execute([$key,$name,$status,$sort,$scheduleDays,$dailyStart,$dailyEnd]);
            $id=(int)$pdo->lastInsertId();audit_log_write_strict($pdo,'menu.definition_created','menu',$id,['menu_key'=>$key,'name'=>$name,'status'=>$status],(int)(current_user()['id']??0));
        }
        $pdo->commit();flash('success','تنظیمات منو ذخیره شد.');redirect('items.php?menu='.urlencode((string)($id?$pdo->query('SELECT menu_key FROM menus WHERE id='.(int)$id)->fetchColumn():'')));
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();flash('error',safe_business_error_message($e,'ذخیره منو انجام نشد.'));$menu=array_merge($menu??[],$_POST);}
}
$dayLabels=[7=>'شنبه',1=>'یکشنبه',2=>'دوشنبه',3=>'سه‌شنبه',4=>'چهارشنبه',5=>'پنجشنبه',6=>'جمعه'];
$selectedDays=array_values(array_filter(array_map('intval',explode(',',(string)($menu['schedule_days']??'')))));
panel_header($id?'ویرایش منو':'افزودن منو','items');
?>
<div class="toolbar"><a class="btn btn-light" href="items.php">بازگشت به مدیریت منو</a></div>
<section class="card"><div class="card-head"><div><h2><?= $id?'ویرایش '.e((string)$menu['name']):'منوی جدید' ?></h2><small>نام نمایشی قابل تغییر است؛ شناسه داخلی با تغییر نام عوض نمی‌شود.</small></div></div><div class="card-body"><form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
<label class="form-group full"><span>نام منو</span><input class="form-control" name="name" maxlength="120" value="<?= e((string)($menu['name']??'')) ?>" required></label>
<label class="form-group"><span>وضعیت</span><select class="form-control" name="status" data-choice-mode="compact"><?php foreach(['active'=>'فعال','draft'=>'پیش‌نویس','inactive'=>'غیرفعال'] as $v=>$label): ?><option value="<?= e($v) ?>" <?= ($menu['status']??'draft')===$v?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
<div class="form-group full"><span>روزهای سرو</span><div class="check-grid"><?php foreach($dayLabels as $d=>$label): ?><label><input type="checkbox" name="schedule_days[]" value="<?= $d ?>" <?= in_array($d,$selectedDays,true)?'checked':'' ?>> <?= e($label) ?></label><?php endforeach; ?></div><small class="muted">اگر هیچ روزی انتخاب نشود، همه روزها مجازند.</small></div>
<label class="form-group"><span>شروع سرو</span><input class="form-control" type="time" name="daily_start" data-minute-step="15" value="<?= e(substr((string)($menu['daily_start']??''),0,5)) ?>"></label>
<label class="form-group"><span>پایان سرو</span><input class="form-control" type="time" name="daily_end" data-minute-step="15" value="<?= e(substr((string)($menu['daily_end']??''),0,5)) ?>"></label>
<div class="form-group full actions"><button class="btn btn-primary">ذخیره</button><a class="btn btn-light" href="items.php">انصراف</a></div>
</form></div></section>
<?php panel_footer(); ?>
