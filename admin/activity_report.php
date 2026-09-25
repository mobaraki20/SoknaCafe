<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login(['admin']);
sokna_module_require('reporting');
require dirname(__DIR__) . '/includes/panel_layout.php';
require_once dirname(__DIR__) . '/includes/audit_presentation.php';
require_once dirname(__DIR__) . '/includes/reporting.php';
require_once dirname(__DIR__) . '/includes/xlsx_export.php';

$pdo=db();
$range=report_range_resolve('7');
$userId=max(0,(int)($_GET['user']??0));
$family=(string)($_GET['family']??'all');
$families=['all','orders-tables','finance','inventory','menu-content','settings-access','connection-system'];
if(!in_array($family,$families,true))$family='all';
$q=trim((string)($_GET['q']??''));
[$start,$end]=business_date_range_bounds((string)$range['from'],(string)$range['to']);
$where=['a.actor_user_id IS NOT NULL','a.created_at>=?','a.created_at<?'];
$params=[$start->format('Y-m-d H:i:s'),$end->format('Y-m-d H:i:s')];
if($userId>0){$where[]='a.actor_user_id=?';$params[]=$userId;}
$familySql=[
 'orders-tables'=>"(a.action LIKE 'order.%' OR a.action LIKE 'table.%' OR a.action LIKE 'waiter_call.%' OR a.action LIKE 'preparation.%')",
 'finance'=>"(a.action LIKE 'settlement.%' OR a.action LIKE 'invoice.%' OR a.action LIKE 'subscriber.%' OR a.action LIKE 'financial_period.%' OR a.action LIKE 'expense.%')",
 'inventory'=>"(a.action LIKE 'inventory.%' OR a.action LIKE 'purchase.%')",
 'menu-content'=>"(a.action LIKE 'menu.%' OR a.action LIKE 'marketing.%' OR a.action LIKE 'event.%' OR a.action LIKE 'guest_message%')",
 'settings-access'=>"(a.action LIKE 'user.%' OR a.action LIKE 'operations.%' OR a.action LIKE 'print_template_%' OR a.action LIKE 'print_agent_%' OR a.action LIKE 'print_destination_%')",
 'connection-system'=>"(a.action LIKE 'center_%' OR a.action LIKE 'push.%' OR a.action LIKE 'backup.%' OR a.action LIKE 'print_job_%')",
];
if($family!=='all')$where[]=$familySql[$family];
if($q!==''){
    $where[]="(COALESCE(a.actor_display_name_snapshot,u.display_name,'') LIKE ? OR CAST(a.entity_id AS CHAR) LIKE ? OR a.details_json LIKE ?)";
    $like='%'.$q.'%';array_push($params,$like,$like,$like);
}
$cursorCreated=trim((string)($_GET['cursor_created']??''));$cursorId=max(0,(int)($_GET['cursor_id']??0));
if($cursorCreated!==''&&$cursorId>0){$where[]='(a.created_at<? OR (a.created_at=? AND a.id<?))';array_push($params,$cursorCreated,$cursorCreated,$cursorId);}
$select="SELECT a.id,a.action,a.entity_type,a.entity_id,a.details_json,a.created_at,a.actor_display_name_snapshot,u.display_name FROM audit_log a LEFT JOIN users u ON u.id=a.actor_user_id WHERE ".implode(' AND ',$where);
$st=$pdo->prepare($select.' ORDER BY a.created_at DESC,a.id DESC LIMIT 101');$st->execute($params);$rows=$st->fetchAll();$hasMore=count($rows)>100;if($hasMore)array_pop($rows);
$users=$pdo->query('SELECT id,display_name FROM users ORDER BY active DESC,display_name')->fetchAll();
if(($_GET['action']??'')==='export'){
    // Export ignores the cursor but preserves all user/family/search filters.
    $exportWhere=$where;$exportParams=$params;
    if($cursorCreated!==''&&$cursorId>0){array_pop($exportWhere);array_splice($exportParams,-3);}
    $exportSql="SELECT a.id,a.action,a.entity_type,a.entity_id,a.details_json,a.created_at,a.actor_display_name_snapshot,u.display_name FROM audit_log a LEFT JOIN users u ON u.id=a.actor_user_id WHERE ".implode(' AND ',$exportWhere).' ORDER BY a.created_at DESC,a.id DESC';
    $exportStmt=$pdo->prepare($exportSql);$exportStmt->execute($exportParams);$exportRows=$exportStmt->fetchAll();try{report_export_guard(count($exportRows),'فعالیت کاربران');}catch(RuntimeException $e){flash('warning',$e->getMessage());$query=$_GET;unset($query['action'],$query['cursor_created'],$query['cursor_id']);redirect('activity_report.php'.($query?'?'.http_build_query($query):''));}
    $sheet=[[xlsx_cell('فعالیت کاربران سکنا','title',5)],[xlsx_cell('بازه: '.$range['label'].' · '.fa_digits(count($exportRows)).' ردیف','meta',5)],[xlsx_cell('زمان','header'),xlsx_cell('کاربر','header'),xlsx_cell('فعالیت','header'),xlsx_cell('زمینه','header'),xlsx_cell('گروه','header')]];
    foreach($exportRows as $row)$sheet[]=[format_jalali_compact((string)$row['created_at']),audit_actor_label($row),audit_human_summary($row),audit_human_context($row),audit_human_details_text($row),audit_family((string)$row['action'])];
    xlsx_download('sokna-user-activity.xlsx',[['name'=>'فعالیت کاربران','rows'=>$sheet,'widths'=>[24,22,58,28,52,20],'freeze_row'=>3,'auto_filter'=>'A3:E'.max(3,count($sheet))]]);
}
$familyLabels=['all'=>'همه فعالیت‌ها','orders-tables'=>'سفارش و میز','finance'=>'مالی','inventory'=>'انبار','menu-content'=>'منو و محتوا','settings-access'=>'تنظیمات و دسترسی','connection-system'=>'اتصال و سیستم'];
$groups=[];foreach($rows as $row){$day=substr((string)$row['created_at'],0,10);$groups[$day][]=$row;}
panel_header('فعالیت کاربران','activity_report');
?>
<div class="panel-surface-stack">
<section class="report-shell"><div class="report-shell-head"><div><strong>چه اتفاقی افتاده؟</strong><small>رویدادهای مدیریتی با زمینه، کاربر و زمان؛ سابقه ثبت‌شده بدون تغییر حفظ می‌شود.</small></div><span class="report-range-summary"><?= e((string)$range['label']) ?></span></div><form method="get" class="report-filter-grid"><?php report_render_range_fields($range,'activityReport'); ?><div class="form-group"><label for="activityUser">کاربر</label><select class="form-control" id="activityUser" name="user" data-choice-mode="adaptive" data-choice-search="true" data-choice-label="کاربر"><option value="0">همه کاربران</option><?php foreach($users as $u): ?><option value="<?= (int)$u['id'] ?>" <?= $userId===(int)$u['id']?'selected':'' ?>><?= e((string)$u['display_name']) ?></option><?php endforeach; ?></select></div><div class="activity-filter-extra"><div class="form-group"><label for="activityFamily">نوع فعالیت</label><select class="form-control" id="activityFamily" name="family" data-choice-mode="compact"><?php foreach($familyLabels as $value=>$label): ?><option value="<?= e($value) ?>" <?= $family===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div class="form-group"><label for="activitySearch">جست‌وجو</label><input class="form-control" id="activitySearch" name="q" type="search" inputmode="search" enterkeyhint="search" value="<?= e($q) ?>" placeholder="سفارش، میز، نام یا شناسه"></div></div><div class="report-filter-actions"><button class="btn btn-primary">نمایش</button><button class="btn btn-light" name="action" value="export">خروجی Excel</button></div></form></section>
<?php if($range['error']): ?><div class="alert alert-warning"><?= e((string)$range['error']) ?></div><?php endif; ?>
<section class="card activity-feed-card"><div class="card-head"><div><h2>فعالیت‌ها</h2><small><?= fa_digits(count($rows)) ?> مورد در این صفحه<?= $hasMore?' · موارد بیشتری وجود دارد':'' ?></small></div></div><div class="audit-feed-v2"><?php foreach($groups as $day=>$dayRows): ?><section class="audit-day-group"><header class="audit-day-head"><?= e(format_jalali_date($day,false)) ?></header><?php foreach($dayRows as $row): $context=audit_human_context($row);$detailRows=audit_human_detail_rows($row); ?><?php if($detailRows): ?><details class="audit-v2-row has-details" data-family="<?= e(audit_family((string)$row['action'])) ?>"><summary class="audit-v2-summary"><strong><?= e(audit_human_summary($row)) ?></strong><?php if($context!==''): ?><div class="audit-v2-context"><span><?= e($context) ?></span></div><?php endif; ?><div class="audit-v2-meta"><?php if($userId===0): ?><span><?= e(audit_actor_label($row)) ?></span><?php endif; ?><time><?= e(fa_digits((new DateTimeImmutable((string)$row['created_at']))->format('H:i'))) ?></time><span class="audit-v2-more">جزئیات</span></div></summary><dl class="audit-v2-details"><?php foreach($detailRows as $detail): ?><div><dt><?= e((string)$detail['label']) ?></dt><dd><?= e((string)$detail['value']) ?></dd></div><?php endforeach; ?></dl></details><?php else: ?><article class="audit-v2-row" data-family="<?= e(audit_family((string)$row['action'])) ?>"><strong><?= e(audit_human_summary($row)) ?></strong><?php if($context!==''): ?><div class="audit-v2-context"><span><?= e($context) ?></span></div><?php endif; ?><div class="audit-v2-meta"><?php if($userId===0): ?><span><?= e(audit_actor_label($row)) ?></span><?php endif; ?><time><?= e(fa_digits((new DateTimeImmutable((string)$row['created_at']))->format('H:i'))) ?></time></div></article><?php endif; ?><?php endforeach; ?></section><?php endforeach; ?><?php if(!$rows): ?><div class="empty-state">در این بازه فعالیتی با این فیلتر ثبت نشده است.</div><?php endif; ?><?php if($hasMore&&$rows): $last=end($rows);$next=$_GET;unset($next['action']);$next['cursor_created']=$last['created_at'];$next['cursor_id']=$last['id']; ?><div class="audit-load-more"><a class="btn btn-light" href="?<?= e(http_build_query($next)) ?>">نمایش موارد بیشتر</a></div><?php endif; ?></div></section>
</div>
<?php panel_footer(); ?>
