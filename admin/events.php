<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login(['admin']);
sokna_module_require('marketing');
require dirname(__DIR__) . '/includes/panel_layout.php';

$allowedFilters = ['all','upcoming','live','draft','past','cancelled'];
$filter = (string)($_GET['status'] ?? $_POST['return_filter'] ?? 'upcoming');
if (!in_array($filter, $allowedFilters, true)) $filter = 'upcoming';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $id = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    try {
        $pdo=db();
        if ($action === 'set_active') {
            $desiredRaw=(string)($_POST['desired_active']??'');if(!in_array($desiredRaw,['0','1'],true))throw new RuntimeException('وضعیت درخواستی معتبر نیست.');$desired=(int)$desiredRaw;
            $pdo->beginTransaction();$stmt=$pdo->prepare('SELECT id,title,active,cancelled_at FROM events WHERE id=? FOR UPDATE');$stmt->execute([$id]);$before=$stmt->fetch();if(!$before)throw new RuntimeException('رویداد پیدا نشد.');if($before['cancelled_at'])throw new RuntimeException('رویداد لغوشده قابل انتشار نیست.');
            if((int)$before['active']!==$desired){$pdo->prepare('UPDATE events SET active=? WHERE id=?')->execute([$desired,$id]);audit_log_write('event.publication_changed','event',$id,['title'=>$before['title'],'active_before'=>(int)$before['active'],'active_after'=>$desired]);}
            $pdo->commit();flash('success',$desired?'رویداد در منوی مهمان منتشر شد.':'رویداد به پیش‌نویس رفت.');
        } elseif ($action === 'set_featured') {
            $desiredRaw=(string)($_POST['desired_featured']??'');if(!in_array($desiredRaw,['0','1'],true))throw new RuntimeException('وضعیت درخواستی معتبر نیست.');$desired=(int)$desiredRaw;
            $pdo->beginTransaction();$stmt=$pdo->prepare('SELECT id,title,active,featured,cancelled_at FROM events WHERE id=? FOR UPDATE');$stmt->execute([$id]);$before=$stmt->fetch();if(!$before)throw new RuntimeException('رویداد پیدا نشد.');if($desired===1&&((int)$before['active']!==1||$before['cancelled_at']))throw new RuntimeException('فقط رویداد منتشرشده می‌تواند برجسته باشد.');
            if((int)$before['featured']!==$desired){$pdo->prepare('UPDATE events SET featured=? WHERE id=?')->execute([$desired,$id]);audit_log_write('event.featured_changed','event',$id,['title'=>$before['title'],'featured_before'=>(int)$before['featured'],'featured_after'=>$desired]);}
            $pdo->commit();flash('success',$desired?'رویداد در منو برجسته شد.':'برجستگی رویداد برداشته شد.');
        } elseif ($action === 'cancel') {
            $pdo->beginTransaction();$stmt=$pdo->prepare('SELECT id,title,cancelled_at FROM events WHERE id=? FOR UPDATE');$stmt->execute([$id]);$before=$stmt->fetch();if(!$before)throw new RuntimeException('رویداد پیدا نشد.');if(!$before['cancelled_at']){$pdo->prepare('UPDATE events SET cancelled_at=NOW(),active=0,featured=0 WHERE id=?')->execute([$id]);audit_log_write('event.cancelled','event',$id,['title'=>$before['title']]);}$pdo->commit();
            flash('success', 'رویداد لغو شد و از منوی مهمان کنار رفت.');
        } elseif ($action === 'restore') {
            $pdo->beginTransaction();$stmt=$pdo->prepare('SELECT id,title,cancelled_at FROM events WHERE id=? FOR UPDATE');$stmt->execute([$id]);$before=$stmt->fetch();if(!$before)throw new RuntimeException('رویداد پیدا نشد.');if($before['cancelled_at']){$pdo->prepare('UPDATE events SET cancelled_at=NULL,active=0,featured=0 WHERE id=?')->execute([$id]);audit_log_write('event.restored_to_draft','event',$id,['title'=>$before['title']]);}$pdo->commit();
            flash('success', 'رویداد به پیش‌نویس برگشت.');
        } elseif ($action === 'delete') {
            $pdo->beginTransaction();$stmt=$pdo->prepare('SELECT title,image_path FROM events WHERE id=? FOR UPDATE');$stmt->execute([$id]);$before=$stmt->fetch();if(!$before)throw new RuntimeException('رویداد پیدا نشد.');$pdo->prepare('DELETE FROM events WHERE id=?')->execute([$id]);audit_log_write('event.deleted','event',$id,['title'=>$before['title']]);$pdo->commit();
            delete_upload_path($before['image_path'] ? (string)$before['image_path'] : null);
            flash('success', 'رویداد حذف شد.');
        } else {
            throw new RuntimeException('عملیات نامعتبر است.');
        }
    } catch (Throwable $e) {
        if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
        error_log('event action: '.$e->getMessage());
        flash('error', safe_business_error_message($e, 'این تغییر انجام نشد.'));
    }
    redirect('events.php?status=' . rawurlencode($filter));
}

$allEvents = db()->query('SELECT * FROM events ORDER BY starts_at DESC,sort_order,id DESC')->fetchAll();
$counts = array_fill_keys($allowedFilters, 0);
$counts['all'] = count($allEvents);
foreach ($allEvents as &$row) {
    $row['_status'] = event_lifecycle_status($row);
    $counts[$row['_status']]++;
}
unset($row);
$events = $filter === 'all' ? $allEvents : array_values(array_filter($allEvents, static fn(array $event): bool => $event['_status'] === $filter));
$filterLabels = ['upcoming'=>'پیش‌رو','live'=>'در حال برگزاری','draft'=>'پیش‌نویس','past'=>'پایان‌یافته','cancelled'=>'لغوشده','all'=>'همه'];
panel_header('رویدادها', 'events');
?>
<div class="toolbar event-toolbar"><div class="filter-pills" aria-label="فیلتر رویدادها"><?php foreach($filterLabels as $value=>$label): ?><a class="<?= $filter === $value ? 'active' : '' ?>" href="events.php?status=<?= e($value) ?>"><?= e($label) ?><span><?= fa_digits((int)$counts[$value]) ?></span></a><?php endforeach; ?></div><a class="btn btn-primary" href="event_form.php"><?= ui_icon('plus') ?> رویداد تازه</a></div>
<div class="panel-helper-note event-lifecycle-note"><?= ui_icon('info') ?><div><strong>پایان رویداد خودکار است.</strong> بعد از زمان پایان، رویداد از منوی مهمان کنار می‌رود و در «پایان‌یافته» باقی می‌ماند.</div></div>
<section class="card table-card events-management-card"><div class="data-table-wrap"><table class="data-table mobile-card-table"><thead><tr><th>تصویر</th><th>رویداد</th><th>زمان</th><th>ثبت‌نام</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>
<?php foreach($events as $event): $status=(string)$event['_status']; ?>
<tr>
<td data-label="تصویر"><?php if($event['image_path']): ?><img class="thumb event-list-thumb" src="<?= e(asset($event['image_path'])) ?>" alt=""><?php else: ?><div class="thumb event-thumb-placeholder"><?= ui_icon('calendar') ?></div><?php endif; ?></td>
<td data-label="رویداد"><strong><?= e($event['title']) ?></strong><?php if($event['featured'] && !in_array($status,['past','cancelled'],true)): ?><br><span class="badge">برجسته</span><?php endif; ?><?php if($event['venue']): ?><br><small class="muted"><?= e($event['venue']) ?></small><?php endif; ?></td>
<td data-label="زمان"><span><?= e(format_jalali_compact($event['starts_at'])) ?></span><br><small class="muted">تا <?= e(format_jalali_compact(event_effective_end($event))) ?></small></td>
<td data-label="ثبت‌نام"><?= e(event_registration_label((string)$event['registration_type'])) ?><?php if($event['capacity']): ?><br><small class="muted">ظرفیت کل <?= fa_digits((int)$event['capacity']) ?> نفر</small><?php endif; ?><?php if(array_key_exists('fee_amount',$event)&&$event['fee_amount']!==null): ?><br><small class="muted">هزینه <?= (int)$event['fee_amount']===0?'رایگان':e(toman((int)$event['fee_amount'])) ?></small><?php endif; ?></td>
<td data-label="وضعیت"><span class="badge event-status-<?= e($status) ?>"><?= e(event_lifecycle_label($status)) ?></span></td>
<td data-label="عملیات"><div class="row-action-menu" data-action-menu><button type="button" class="btn btn-sm btn-light" data-action-menu-trigger aria-label="عملیات <?= e($event['title']) ?>" aria-haspopup="menu" aria-expanded="false"><?= ui_icon('more') ?> عملیات</button><div class="row-action-popover" data-action-menu-popover role="menu">
<a href="event_form.php?id=<?= (int)$event['id'] ?>"><?= ui_icon('edit') ?> ویرایش</a>
<a href="event_form.php?copy_from=<?= (int)$event['id'] ?>"><?= ui_icon('copy') ?> ساخت نوبت جدید</a>
<?php if(!in_array($status,['past','cancelled'],true)): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$event['id'] ?>"><input type="hidden" name="return_filter" value="<?= e($filter) ?>"><input type="hidden" name="desired_active" value="<?= $event['active'] ? '0' : '1' ?>"><button name="action" value="set_active"><?= ui_icon($event['active'] ? 'archive' : 'eye') ?> <?= $event['active'] ? 'تبدیل به پیش‌نویس' : 'انتشار در منو' ?></button></form><?php endif; ?>
<?php if(!in_array($status,['past','cancelled'],true) && $event['active']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$event['id'] ?>"><input type="hidden" name="return_filter" value="<?= e($filter) ?>"><input type="hidden" name="desired_featured" value="<?= $event['featured'] ? '0' : '1' ?>"><button name="action" value="set_featured"><?= ui_icon('sparkles') ?> <?= $event['featured'] ? 'برداشتن برجستگی' : 'برجسته در منو' ?></button></form><?php endif; ?>
<?php if(!in_array($status,['past','cancelled'],true)): ?><form method="post" data-confirm="رویداد از منوی مهمان کنار گذاشته می‌شود و اطلاعاتش باقی می‌ماند." data-confirm-title="لغو رویداد؟" data-confirm-ok="لغو رویداد"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$event['id'] ?>"><input type="hidden" name="return_filter" value="<?= e($filter) ?>"><button name="action" value="cancel"><?= ui_icon('archive') ?> لغو رویداد</button></form><?php elseif($status==='cancelled'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$event['id'] ?>"><input type="hidden" name="return_filter" value="<?= e($filter) ?>"><button name="action" value="restore"><?= ui_icon('refresh') ?> بازگردانی به پیش‌نویس</button></form><?php endif; ?>
<form method="post" data-confirm="این رویداد برای همیشه حذف می‌شود؛ این کار فقط برای رکوردهای آزمایشی یا اشتباه مناسب است." data-confirm-title="حذف رویداد؟" data-confirm-ok="حذف رویداد" data-confirm-danger="1"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$event['id'] ?>"><input type="hidden" name="return_filter" value="<?= e($filter) ?>"><button class="danger-action" name="action" value="delete"><?= ui_icon('trash') ?> حذف</button></form>
</div></div></td>
</tr>
<?php endforeach; ?>
<?php if(!$events): ?><tr><td colspan="6" class="empty-state">در این وضعیت رویدادی وجود ندارد.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?php panel_footer(); ?>
