<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login(['admin']);
require_once dirname(__DIR__) . '/includes/push.php';
require dirname(__DIR__) . '/includes/panel_layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf($_POST['csrf_token'] ?? null);
    $action=(string)($_POST['action']??'');
    if($action==='admin_live_operations'){db()->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute(['push.admin_live_operations',isset($_POST['enabled'])?'1':'0']);audit_log_write('push.admin_live_operations_changed','settings','push.admin_live_operations',['enabled'=>isset($_POST['enabled'])?1:0],(int)(current_user()['id']??0));flash('success','تنظیم اعلان عملیات زنده مدیر ذخیره شد.');redirect('push_devices.php');}
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdo=db();$pdo->beginTransaction();
        try{$stmt=$pdo->prepare('SELECT id,user_id,device_label,active FROM push_subscriptions WHERE id=? FOR UPDATE');$stmt->execute([$id]);$device=$stmt->fetch();if(!$device)throw new RuntimeException('دستگاه پیدا نشد.');if((int)$device['active']===1){$pdo->prepare('UPDATE push_subscriptions SET active=0 WHERE id=?')->execute([$id]);$userName='';if((int)($device['user_id']??0)>0){$nameStmt=$pdo->prepare('SELECT display_name FROM users WHERE id=?');$nameStmt->execute([(int)$device['user_id']]);$userName=trim((string)$nameStmt->fetchColumn());}audit_log_write('push.device_disabled','push_subscription',$id,['device_label'=>$device['device_label'],'user_name'=>$userName],(int)(current_user()['id']??0));}$pdo->commit();flash('success', 'دستگاه از دریافت اعلان خارج شد.');}
        catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('push device disable: '.$e->getMessage());flash('error',safe_business_error_message($e,'غیرفعال‌کردن دستگاه انجام نشد.'));}
    }
    redirect('push_devices.php');
}

$credentials = [];
try {
    $credentials = push_vapid_credentials(true);
} catch (Throwable $e) {
    error_log('push credentials: '.$e->getMessage());
    flash('error', safe_business_error_message($e, 'آماده‌سازی اعلان‌ها انجام نشد. تنظیمات سامانه را بررسی کنید.'));
}
$devices = db()->query("SELECT ps.id,ps.device_label,ps.active,ps.last_success_at,ps.last_error_at,ps.last_error_message,ps.created_at,ps.updated_at,u.display_name,u.username FROM push_subscriptions ps JOIN users u ON u.id=ps.user_id ORDER BY ps.active DESC,ps.updated_at DESC LIMIT 300")->fetchAll();
$summary = db()->query("SELECT COUNT(*) total,SUM(active=1) active_count,SUM(last_error_at IS NOT NULL AND (last_success_at IS NULL OR last_error_at>last_success_at)) error_count FROM push_subscriptions")->fetch() ?: [];
$recentLogs = db()->query("SELECT l.event_type,l.http_status,l.success,l.error_message,l.created_at,ps.device_label,u.display_name FROM push_delivery_log l LEFT JOIN push_subscriptions ps ON ps.id=l.subscription_id LEFT JOIN users u ON u.id=ps.user_id ORDER BY l.id DESC LIMIT 50")->fetchAll();
$queueHealth=['pending'=>0,'oldest'=>null];try{$queueHealth=db()->query("SELECT COUNT(*) pending,MIN(created_at) oldest FROM push_event_queue WHERE status IN('pending','processing')")->fetch()?:$queueHealth;}catch(Throwable $e){error_log('push queue health: '.$e->getMessage());}
$adminLive=setting_bool('push.admin_live_operations',false);
$workerLastSeen=setting('push.worker_last_seen_at','');
$workerFresh=false;if($workerLastSeen!==''){try{$workerFresh=(time()-(new DateTimeImmutable($workerLastSeen,new DateTimeZone(app_timezone())))->getTimestamp())<=180;}catch(Throwable){$workerFresh=false;}}
$pushCoverage=[];
foreach ([
    'waiter_call'=>['label'=>'فراخوان مهمان','event'=>'waiter_call','data'=>[]],
    'pending_guest_order'=>['label'=>'سفارش مهمان منتظر تأیید','event'=>'pending_order','data'=>[]],
    'preparation_kitchen'=>['label'=>'آماده‌سازی آشپزخانه','event'=>'order','data'=>['areas'=>['kitchen']]],
    'preparation_bar'=>['label'=>'آماده‌سازی بار','event'=>'order','data'=>['areas'=>['bar']]],
] as $key=>$config) {
    try {
        $ids=push_recipient_ids((string)$config['event'],(array)$config['data']);
        $active=0;
        if($ids){$ph=implode(',',array_fill(0,count($ids),'?'));$st=db()->prepare("SELECT COUNT(DISTINCT user_id) FROM push_subscriptions WHERE active=1 AND user_id IN($ph)");$st->execute($ids);$active=(int)$st->fetchColumn();}
        $pushCoverage[$key]=['label'=>$config['label'],'eligible'=>count($ids),'active'=>$active];
    } catch(Throwable $e){error_log('push coverage: '.$e->getMessage());$pushCoverage[$key]=['label'=>$config['label'],'eligible'=>0,'active'=>0,'unknown'=>true];}
}
$pushCoverageWarnings=array_filter($pushCoverage,static fn(array $row):bool=>empty($row['unknown']) && (int)$row['active']===0);
panel_header('دستگاه‌ها و اعلان‌ها','push');
?>
<div class="panel-page-flow push-devices-page" data-visual-quality-page="push_devices">
<div class="push-health-strip" aria-label="خلاصه اعلان‌ها"><article><span>ثبت‌شده</span><strong><?= fa_digits((int)($summary['total']??0)) ?></strong></article><article><span>فعال</span><strong><?= fa_digits((int)($summary['active_count']??0)) ?></strong></article><article class="<?= (int)($summary['error_count']??0)>0?'has-warning':'' ?>"><span>نیازمند بررسی</span><strong><?= fa_digits((int)($summary['error_count']??0)) ?></strong></article></div>
<details class="push-setup-guide"><summary>راهنمای فعال‌سازی اعلان روی دستگاه</summary><div><p>هر کارمند اعلان را روی دستگاه خودش فعال می‌کند. در iPhone، وب‌اپ باید روی صفحه اصلی نصب و از همان آیکون باز شود.</p><details><summary>جزئیات فنی</summary><code dir="ltr"><?= e(text_substr((string)($credentials['public_key']??''),0,24)) ?>…</code></details></div></details>
<section class="card"><div class="card-head"><div class="panel-copy-stack"><h2>اعلان‌های عملیات زنده</h2></div></div><div class="card-body panel-card-flow"><?php if($pushCoverageWarnings): ?><div class="push-coverage-warnings" role="status" aria-live="polite"><?php foreach($pushCoverageWarnings as $coverage): ?><div class="push-coverage-warning"><strong><?= e($coverage['label']) ?></strong><span>در حال حاضر دستگاه فعالی برای دریافت اعلان ندارد؛ وظایف همچنان داخل پنل قابل مشاهده‌اند.</span></div><?php endforeach; ?></div><?php endif; ?><form method="post" class="push-policy-form"><?= csrf_field() ?><input type="hidden" name="action" value="admin_live_operations"><label class="check-line push-policy-toggle"><input type="checkbox" name="enabled" value="1" <?= $adminLive?'checked':'' ?> onchange="this.form.submit()"><span>دریافت اعلان‌های سالن و آماده‌سازی برای مدیر</span></label></form><div class="panel-diagnostic-grid push-worker-health <?= (int)($queueHealth['pending']??0)>20?'has-warning':'' ?>" aria-label="سلامت ارسال اعلان"><div class="panel-diagnostic-item is-primary"><span class="panel-diagnostic-label">ارسال خودکار</span><strong>فعال</strong><small>رویداد جدید پس از ثبت تلاش فوری دارد و صف در صفحات فعال کارکنان دوباره پردازش می‌شود.</small></div><div class="panel-diagnostic-item"><span class="panel-diagnostic-label">صف در انتظار</span><strong><?= fa_digits((int)($queueHealth['pending']??0)) ?> اعلان</strong><small><?= !empty($queueHealth['oldest'])?'قدیمی‌ترین: '.e(format_jalali_compact((string)$queueHealth['oldest'])):'صف خالی است' ?></small></div><div class="panel-diagnostic-item"><span class="panel-diagnostic-label">پردازش مستقل</span><strong><?= $workerFresh?'فعال':'اختیاری' ?></strong><small><?= $workerFresh?'پردازش کمکی در پس‌زمینه فعال است.':'پردازش مستقیم برای مقیاس فعلی سکنا کافی است.' ?></small></div></div></div></section>
<section class="card push-device-card"><div class="card-head"><div class="panel-copy-stack"><h2>دستگاه‌های کارکنان</h2><small>دستگاه منقضی‌شده به‌صورت خودکار از دریافت اعلان خارج می‌شود.</small></div><?php if((int)($summary['total']??0)>count($devices)): ?><span class="muted">نمایش <?= fa_digits(count($devices)) ?> دستگاه اخیر</span><?php endif; ?></div><div class="push-device-list"><?php if(!$devices): ?><div class="empty-state">هنوز دستگاهی ثبت نشده.</div><?php endif; ?><?php foreach($devices as $device): $hasRecentError=$device['last_error_at'] && (!$device['last_success_at'] || $device['last_error_at']>$device['last_success_at']); ?><article class="push-device-row <?= $hasRecentError?'has-error':'' ?>"><header><div><strong><?= e($device['display_name']) ?></strong><span><?= e($device['device_label']?:'بدون نام') ?> · <span dir="ltr"><?= e($device['username']) ?></span></span></div><span class="badge <?= (int)$device['active']===1?'badge-posted':'badge-voided' ?>"><?= (int)$device['active']===1?'فعال':'غیرفعال' ?></span></header><div class="push-device-health"><span>آخرین موفق: <?= $device['last_success_at']?e(format_jalali_compact($device['last_success_at'])):'—' ?></span><?php if($hasRecentError): ?><span class="text-danger">آخرین ارسال ناموفق: <?= e(format_jalali_compact($device['last_error_at'])) ?></span><?php else: ?><span class="muted">به‌روزرسانی: <?= e(format_jalali_compact($device['updated_at'])) ?></span><?php endif; ?></div><?php if($device['last_error_message']): ?><details class="push-technical-detail"><summary>جزئیات آخرین خطا</summary><code dir="ltr"><?= e($device['last_error_message']) ?></code></details><?php endif; ?><?php if((int)$device['active']===1): ?><form method="post" class="push-device-action"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$device['id'] ?>"><button class="btn btn-sm btn-outline" data-click-confirm="این دستگاه دیگر اعلان‌های سکنا را دریافت نمی‌کند." data-confirm-title="غیرفعال‌کردن اعلان این دستگاه؟" data-confirm-ok="غیرفعال‌کردن">غیرفعال‌کردن اعلان</button></form><?php endif; ?></article><?php endforeach; ?></div></section>
<section class="card push-log-card"><div class="card-head"><div class="panel-copy-stack"><h2>۵۰ ارسال اخیر</h2><small>این بخش برای عیب‌یابی است؛ متن خصوصی اعلان ذخیره نمی‌شود.</small></div></div><div class="push-log-list"><?php if(!$recentLogs): ?><div class="empty-state">ارسالی ثبت نشده.</div><?php endif; ?><?php foreach($recentLogs as $log): $ok=(int)$log['success']===1; $who=trim(($log['display_name']??'').' · '.($log['device_label']??''),' ·'); ?><article class="push-log-row"><div><strong><?= $log['event_type']==='waiter_call'?'فراخوان گارسون':($log['event_type']==='test_notification'?'آزمایش اعلان':'سفارش') ?></strong><span><?= e($who?:'اشتراک حذف‌شده') ?> · <?= e(format_jalali_compact($log['created_at'])) ?></span></div><?php if($ok): ?><span class="badge badge-posted">موفق · <?= fa_digits((int)$log['http_status']) ?></span><?php else: ?><details class="push-log-error"><summary class="badge badge-failed">ناموفق</summary><code dir="ltr"><?= e($log['error_message']?:('HTTP '.(int)$log['http_status'])) ?></code></details><?php endif; ?></article><?php endforeach; ?></div></section>
</div>
<?php panel_footer(); ?>
