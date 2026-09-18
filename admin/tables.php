<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login(['admin']);
require dirname(__DIR__) . '/includes/panel_layout.php';

function next_internal_table_code(PDO $pdo): string
{
    $check=$pdo->prepare('SELECT COUNT(*) FROM cafe_tables WHERE code=?');
    for($attempt=0;$attempt<20;$attempt++){
        $code='T-'.strtoupper(bin2hex(random_bytes(5)));$check->execute([$code]);
        if((int)$check->fetchColumn()===0)return $code;
    }
    throw new RuntimeException('ساخت شناسه داخلی میز انجام نشد. دوباره امتحان کن.');
}
function table_number_exists(PDO $pdo,int $number,int $exceptId=0):bool
{
    $sql='SELECT COUNT(*) FROM cafe_tables WHERE table_number=?';$params=[$number];
    if($exceptId>0){$sql.=' AND id<>?';$params[]=$exceptId;}
    $st=$pdo->prepare($sql);$st->execute($params);return (int)$st->fetchColumn()>0;
}
function table_live_session_locked(PDO $pdo,int $tableId):?array
{
    $st=$pdo->prepare("SELECT * FROM table_sessions WHERE table_id=? AND status IN('active','pending') ORDER BY FIELD(status,'active','pending'),id DESC LIMIT 1 FOR UPDATE");
    $st->execute([$tableId]);return $st->fetch()?:null;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    verify_csrf($_POST['csrf_token']??null);$action=(string)($_POST['action']??'save');$id=(int)($_POST['id']??0);$pdo=db();$userId=(int)current_user()['id'];
    try{
        if($action==='save'){
            $name=text_substr(trim((string)($_POST['name']??'')),0,100);
            $numberRaw=trim(en_digits((string)($_POST['table_number']??'')));if(!preg_match('/^[0-9]{1,4}$/',$numberRaw))throw new RuntimeException('شماره میز باید فقط عدد و بین ۱ تا ۹۹۹۹ باشد.');$number=(int)$numberRaw;
            $zone=text_substr(trim((string)($_POST['zone_label']??'')),0,80);$active=isset($_POST['active'])?1:0;
            if($name==='')throw new RuntimeException('نام میز الزامی است.');
            if($number<1||$number>9999)throw new RuntimeException('شماره میز باید یک عدد مثبت معتبر باشد.');
            if(table_number_exists($pdo,$number,$id))throw new RuntimeException('میز شماره '.fa_digits($number).' از قبل وجود دارد.');
            $sort=$number;
            if($id>0){
                $pdo->beginTransaction();$lock=$pdo->prepare('SELECT * FROM cafe_tables WHERE id=? FOR UPDATE');$lock->execute([$id]);$old=$lock->fetch();if(!$old)throw new RuntimeException('میز پیدا نشد.');
                $live=table_live_session_locked($pdo,$id);
                if($live&&$active===0)throw new RuntimeException('این میز حساب باز دارد. ابتدا حساب را تسویه یا به میز دیگری منتقل کنید؛ غیرفعال‌سازی حساب را نمی‌بندد.');
                $identityChanged=$name!==(string)$old['name']||$number!==(int)$old['table_number']||($zone?:null)!==($old['zone_label']?:null);
                if($live&&$identityChanged)throw new RuntimeException('نام، شماره یا بخش میز هنگام حساب باز قابل تغییر نیست. ابتدا حساب را تسویه یا منتقل کنید.');
                $pdo->prepare('UPDATE cafe_tables SET name=?,table_number=?,zone_label=?,sort_order=?,active=? WHERE id=?')->execute([$name,$number,$zone?:null,$sort,$active,$id]);
                audit_log_write('table.updated','cafe_table',$id,['before'=>['name'=>$old['name'],'table_number'=>$old['table_number'],'zone_label'=>$old['zone_label'],'active'=>(int)$old['active']],'after'=>['name'=>$name,'table_number'=>$number,'zone_label'=>$zone?:null,'active'=>$active]],$userId);
                $pdo->commit();
            }else{
                $code=next_internal_table_code($pdo);$pdo->prepare('INSERT INTO cafe_tables(name,table_number,code,access_token,zone_label,active,sort_order) VALUES(?,?,?,?,?,?,?)')->execute([$name,$number,$code,unique_table_access_token($pdo),$zone?:null,$active,$sort]);$newId=(int)$pdo->lastInsertId();
                audit_log_write('table.created','cafe_table',$newId,['name'=>$name,'table_number'=>$number,'zone_label'=>$zone?:null,'active'=>$active],$userId);
            }
            flash('success','اطلاعات میز ذخیره شد.');
        }elseif($action==='bulk_create'){
            $fromRaw=trim(en_digits((string)($_POST['from_number']??'')));$toRaw=trim(en_digits((string)($_POST['to_number']??'')));if(!preg_match('/^[0-9]{1,4}$/',$fromRaw)||!preg_match('/^[0-9]{1,4}$/',$toRaw))throw new RuntimeException('شماره شروع و پایان باید فقط عدد باشند.');$from=(int)$fromRaw;$to=(int)$toRaw;$prefix=text_substr(trim((string)($_POST['name_prefix']??'میز')),0,60)?:'میز';$zone=text_substr(trim((string)($_POST['bulk_zone_label']??'')),0,80);
            if($from<1||$to<$from||($to-$from+1)>100)throw new RuntimeException('بازه میزها باید بین ۱ تا ۱۰۰ میز و به ترتیب درست باشد.');
            $pdo->beginTransaction();$st=$pdo->prepare('INSERT INTO cafe_tables(name,table_number,code,access_token,zone_label,active,sort_order) VALUES(?,?,?,?,?,1,?)');$created=[];$skipped=[];
            for($n=$from;$n<=$to;$n++){
                if(table_number_exists($pdo,$n)){ $skipped[]=$n;continue; }
                try{$st->execute([$prefix.' '.fa_digits((string)$n),$n,next_internal_table_code($pdo),unique_table_access_token($pdo),$zone?:null,$n]);$created[]=$n;}
                catch(PDOException $e){if((string)$e->getCode()==='23000'){$skipped[]=$n;continue;}throw $e;}
            }
            audit_log_write('table.bulk_created','cafe_table',null,['from'=>$from,'to'=>$to,'created'=>$created,'skipped'=>$skipped,'zone_label'=>$zone?:null],$userId);$pdo->commit();
            flash($created?'success':'warning',$created?fa_digits(count($created)).' میز جدید ساخته شد.'.($skipped?' '.fa_digits(count($skipped)).' شماره موجود بدون تغییر ماند.':''):'همه شماره‌های این بازه از قبل وجود دارند.');
        }elseif($action==='set_active'){
            if($id<1)throw new RuntimeException('میز معتبر نیست.');$desiredRaw=(string)($_POST['desired_active']??'');if(!in_array($desiredRaw,['0','1'],true))throw new RuntimeException('وضعیت درخواستی معتبر نیست.');$desired=(int)$desiredRaw;$pdo->beginTransaction();$st=$pdo->prepare('SELECT * FROM cafe_tables WHERE id=? FOR UPDATE');$st->execute([$id]);$row=$st->fetch();if(!$row)throw new RuntimeException('میز پیدا نشد.');
            if($desired===0&&table_live_session_locked($pdo,$id))throw new RuntimeException('این میز حساب باز دارد. ابتدا حساب را تسویه یا منتقل کنید.');
            if((int)$row['active']!==$desired){$pdo->prepare('UPDATE cafe_tables SET active=? WHERE id=?')->execute([$desired,$id]);audit_log_write('table.status_changed','cafe_table',$id,['from'=>(int)$row['active'],'to'=>$desired],$userId);}$pdo->commit();flash('success',$desired?'میز فعال شد.':'میز غیرفعال شد.');
        }elseif($action==='delete'){
            if($id<1)throw new RuntimeException('میز معتبر نیست.');$pdo->beginTransaction();$st=$pdo->prepare('SELECT * FROM cafe_tables WHERE id=? FOR UPDATE');$st->execute([$id]);$row=$st->fetch();if(!$row)throw new RuntimeException('میز پیدا نشد.');if(table_live_session_locked($pdo,$id))throw new RuntimeException('میز دارای حساب باز قابل حذف نیست.');$pdo->prepare('DELETE FROM cafe_tables WHERE id=?')->execute([$id]);audit_log_write('table.deleted','cafe_table',$id,['name'=>$row['name'],'table_number'=>$row['table_number']],$userId);$pdo->commit();flash('success','میز حذف شد.');
        }else throw new RuntimeException('عملیات میز شناخته نشد.');
    }catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();flash('error',(string)$e->getCode()==='23000'?'شماره میز تکراری است یا این میز سابقه عملیاتی دارد.':'ذخیره‌سازی میز انجام نشد.');}
    catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('tables action: '.$e->getMessage());flash('error',safe_business_error_message($e,'عملیات میز انجام نشد.'));}
    redirect('tables.php');
}

$pdo=db();
$edit=null;$editId=(int)($_GET['edit']??0);$newMode=isset($_GET['new']);if($editId>0){$st=$pdo->prepare('SELECT * FROM cafe_tables WHERE id=?');$st->execute([$editId]);$edit=$st->fetch()?:null;}
$allTables=$pdo->query("SELECT t.*,EXISTS(SELECT 1 FROM table_sessions s WHERE s.table_id=t.id AND s.status IN('active','pending')) has_active_session FROM cafe_tables t ORDER BY COALESCE(t.zone_label,''),t.table_number,t.id")->fetchAll();
$tables=$allTables;
$editorMode=$newMode||$edit!==null;
panel_header('میزها و QR','tables');
?>
<div class="panel-balanced-actions tables-primary-actions"><a class="btn btn-primary" href="?new=1"><?= ui_icon('add') ?> میز جدید</a><a class="btn btn-light" href="qr.php"><?= ui_icon('qr') ?> مدیریت QR</a></div><div class="panel-section-meta"><?= fa_digits(count($allTables)) ?> میز · <?= fa_digits(count(array_filter($allTables,fn($r)=>(int)$r['active']===1))) ?> فعال</div>
<div class="page-grid tables-page-grid <?= $editorMode?'is-editor-mode':'' ?>">
<section class="card table-card tables-list-card panel-list-card"><div class="table-management-list">
<?php if(!$tables): ?><div class="empty-state">هنوز میزی ساخته نشده است.</div><?php endif; ?>
<?php foreach($tables as $table):
$state=!(int)$table['active']?'state-disabled':($table['has_active_session']?'state-active':'state-free');
$status=!(int)$table['active']?'غیرفعال':($table['has_active_session']?'مهمان دارد':'آزاد');
$displayName=trim((string)$table['name'])!==''?fa_digits((string)$table['name']):'میز '.fa_digits((int)$table['table_number']);
$zone=trim((string)($table['zone_label']??''));
?>
<div class="panel-list-row table-management-row is-navigable" data-row-href="?edit=<?= (int)$table['id'] ?>" role="link" tabindex="0" aria-label="ویرایش <?= e($displayName) ?>">
  <div class="panel-list-primary table-management-primary"><div class="table-management-identity"><?= table_medallion_html((string)$table['name'],$state,'sm',null,(int)$table['table_number'],false) ?><div class="panel-copy-stack"><strong><?= e($displayName) ?></strong><?php if(!preg_match('/^میز\s*[۰-۹0-9]+$/u',trim((string)$table['name']))): ?><small>شماره <?= fa_digits((int)$table['table_number']) ?></small><?php endif; ?><small class="table-management-mobile-meta"><?= $zone!==''?e(fa_digits($zone)).' · ':'' ?><?= e($status) ?></small></div></div></div>
  <div class="panel-list-value table-management-zone"><span>بخش</span><strong><?= e($zone!==''?fa_digits($zone):'—') ?></strong></div>
  <div class="panel-list-value table-management-status"><span>وضعیت</span><strong class="<?= (int)$table['active']===1?'text-success':'muted' ?>"><?= e($status) ?></strong></div>
  <div class="panel-row-actions"><div class="row-action-menu" data-action-menu data-action-menu-label="مدیریت <?= e($displayName) ?>"><button type="button" class="btn btn-sm btn-light table-management-action" data-action-menu-trigger aria-expanded="false" aria-haspopup="menu" aria-label="مدیریت <?= e($displayName) ?>"><?= ui_icon('more') ?><span class="table-action-label">مدیریت</span></button><div class="row-action-popover" data-action-menu-popover role="menu"><a role="menuitem" href="?edit=<?= (int)$table['id'] ?>"><?= ui_icon('edit') ?> ویرایش</a><a role="menuitem" href="qr.php?id=<?= (int)$table['id'] ?>"><?= ui_icon('qr') ?> QR میز</a><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$table['id'] ?>"><input type="hidden" name="desired_active" value="<?= (int)$table['active']===1?'0':'1' ?>"><button role="menuitem" name="action" value="set_active"><?= ui_icon('refresh') ?> <?= (int)$table['active']===1?'غیرفعال‌کردن':'فعال‌کردن' ?></button></form><form method="post" data-confirm="فقط میزی که حساب باز یا سابقه عملیاتی ندارد حذف می‌شود." data-confirm-title="حذف میز؟" data-confirm-ok="حذف میز" data-confirm-danger="1"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$table['id'] ?>"><button role="menuitem" class="danger-action" name="action" value="delete"><?= ui_icon('trash') ?> حذف</button></form></div></div></div>
</div>
<?php endforeach; ?>
</div></section>
<section class="card tables-editor-card"><div class="card-head"><div class="panel-copy-stack"><h2><?= $edit?'ویرایش میز':($newMode?'میز جدید':'مدیریت میزها') ?></h2><small class="muted"><?= $editorMode?'هویت میز دارای حساب باز قابل تغییر نیست.':'برای ایجاد یا ویرایش یک میز اقدام کن.' ?></small></div><?php if($editorMode): ?><a class="btn btn-sm btn-light tables-editor-back" href="tables.php">بازگشت به فهرست</a><?php endif; ?></div><div class="card-body">
<?php if($editorMode): ?><form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($edit['id']??0) ?>"><div class="form-group"><label>نام میز</label><input class="form-control" name="name" maxlength="100" required value="<?= e((string)($edit['name']??'')) ?>" placeholder="مثلاً میز ۱۲"></div><div class="form-group"><label>شماره میز</label><input class="form-control" inputmode="numeric" enterkeyhint="next" name="table_number" required value="<?= e(numeric_input_display_value((string)($edit['table_number']??''))) ?>" placeholder="۱۲"></div><div class="form-group full"><label>بخش / سالن</label><input class="form-control" name="zone_label" maxlength="80" value="<?= e((string)($edit['zone_label']??'')) ?>" placeholder="مثلاً تراس"></div><div class="form-group full"><label class="check-line"><input type="checkbox" name="active" <?= !isset($edit['active'])||(int)$edit['active']===1?'checked':'' ?>><span>میز فعال باشد</span></label></div><div class="form-group full actions"><button class="btn btn-primary" name="action" value="save">ذخیره میز</button><a class="btn btn-light" href="tables.php">انصراف</a></div></form>
<?php else: ?><div class="settings-inline-note">ترتیب نمایش میزها به‌صورت رسمی بر اساس بخش و شماره میز است. برای ساخت چند میز پشت سر هم از ابزار زیر استفاده کن.</div><form method="post" class="form-grid"><?= csrf_field() ?><div class="form-group"><label>از شماره</label><input class="form-control" inputmode="numeric" enterkeyhint="next" name="from_number" required></div><div class="form-group"><label>تا شماره</label><input class="form-control" inputmode="numeric" enterkeyhint="next" name="to_number" required></div><div class="form-group"><label>پیشوند نام</label><input class="form-control" name="name_prefix" value="میز"></div><div class="form-group"><label>بخش / سالن</label><input class="form-control" name="bulk_zone_label"></div><div class="form-group full"><button class="btn btn-light" name="action" value="bulk_create">ساخت گروهی میزها</button></div></form><?php endif; ?></div></section>
</div>
<?php panel_footer(); ?>
