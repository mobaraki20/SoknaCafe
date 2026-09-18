<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login(['admin']);
require dirname(__DIR__) . '/includes/panel_layout.php';

function accommodation_reservation_human_label(string $code): string
{
    $normalized=en_digits(trim($code));
    if(preg_match('/(\d+)$/',$normalized,$match))return 'رزرو '.fa_digits((int)$match[1]);
    return 'رزرو';
}

$liveEnabled=accommodation_live_operations_enabled();
$page=max(1,(int)($_GET['page']??1));
$perPage=25;
$q=text_substr(trim((string)($_GET['q']??'')),0,120);
$statusFilter=(string)($_GET['status']??'');
if(!in_array($statusFilter,['needs_action','posted','voided'],true))$statusFilter='';
$rows=[];$attention=[];$totalRows=0;$totalPages=1;$pageError='';

try{
    $attention=accommodation_attention_rows(10);
    $conditions=['1=1'];$params=[];
    if($q!==''){
        $ascii=en_digits($q);$normalized=normalize_persian_search($q);
        $conditions[]="(at.reservation_code LIKE ? OR sr.invoice_number LIKE ? OR REPLACE(REPLACE(at.guest_name_snapshot,'ي','ی'),'ك','ک') LIKE ? OR REPLACE(REPLACE(at.room_name_snapshot,'ي','ی'),'ك','ک') LIKE ? OR COALESCE(NULLIF(sr.table_name_snapshot,''),t.name) LIKE ?)";
        array_push($params,'%'.$ascii.'%','%'.$ascii.'%','%'.$normalized.'%','%'.$normalized.'%','%'.$q.'%');
    }
    if($statusFilter==='needs_action')$conditions[]=accommodation_attention_where_sql('at');
    elseif($statusFilter!==''){$conditions[]='at.status=?';$params[]=$statusFilter;}
    $where=implode(' AND ',$conditions);
    $joins=" FROM accommodation_transfers at JOIN table_sessions ts ON ts.id=at.session_id JOIN cafe_tables t ON t.id=ts.table_id LEFT JOIN users ou ON ou.id=at.operator_user_id LEFT JOIN users vu ON vu.id=at.voided_by_user_id LEFT JOIN users ru ON ru.id=at.resolved_by_user_id LEFT JOIN settlement_records sr ON sr.accommodation_transfer_id=at.id AND sr.status='completed' LEFT JOIN settlement_records rev ON rev.reverses_settlement_id=sr.id AND rev.status='reversal'";
    $count=db()->prepare('SELECT COUNT(DISTINCT at.id)'.$joins.' WHERE '.$where);$count->execute($params);$totalRows=(int)$count->fetchColumn();
    $totalPages=max(1,(int)ceil($totalRows/$perPage));if($page>$totalPages)$page=$totalPages;$offset=($page-1)*$perPage;
    $eventTimeSql=accommodation_transfer_effective_time_sql('at');
    $stmt=db()->prepare("SELECT at.*,ts.status session_status,ts.table_id,t.name table_name,ou.display_name operator_name,vu.display_name voided_by_name,ru.display_name resolved_by_name,sr.id settlement_record_id,sr.invoice_number,sr.table_name_snapshot,rev.id reversal_settlement_record_id".$joins.' WHERE '.$where.' ORDER BY '.$eventTimeSql.' DESC,at.id DESC LIMIT '.$perPage.' OFFSET '.$offset);
    $stmt->execute($params);$rows=$stmt->fetchAll();
    foreach($rows as &$row)$row=accommodation_enrich_transfer($row);unset($row);
}catch(Throwable $e){
    error_log('accommodation admin page: '.$e->getMessage());
    $rows=[];$attention=[];$pageError='بخش حساب اقامتگاه آماده استفاده نیست. وضعیت به‌روزرسانی سامانه را بررسی کنید.';
}

$baseQuery=[];if($q!=='')$baseQuery['q']=$q;if($statusFilter!=='')$baseQuery['status']=$statusFilter;
$urlForPage=static function(int $targetPage)use($baseQuery):string{$p=$baseQuery;if($targetPage>1)$p['page']=$targetPage;return 'accommodation.php'.($p?'?'.http_build_query($p):'');};
$attentionCounts=accommodation_attention_counts();
$attentionTotal=array_sum($attentionCounts);
$attentionParts=[];
if($attentionCounts['pending'])$attentionParts[]=fa_digits($attentionCounts['pending']).' در انتظار انتقال';
if($attentionCounts['failed'])$attentionParts[]=fa_digits($attentionCounts['failed']).' ناموفق';
if($attentionCounts['void_pending'])$attentionParts[]=fa_digits($attentionCounts['void_pending']).' در انتظار برگشت';
if($attentionCounts['void_failed'])$attentionParts[]=fa_digits($attentionCounts['void_failed']).' برگشت ناموفق';
if($attentionCounts['local_finalize'])$attentionParts[]=fa_digits($attentionCounts['local_finalize']).' تکمیل تسویه کافه';
if($attentionCounts['local_reversal'])$attentionParts[]=fa_digits($attentionCounts['local_reversal']).' تکمیل سند برگشتی';
$freeTables=[];
try{$freeTables=settlement_free_tables(db(),0);}catch(Throwable $e){error_log('accommodation free tables: '.$e->getMessage());}

panel_header('حساب اقامتگاه','accommodation');
?>
<?php if($pageError!==''): ?><div class="alert alert-error"><?= e($pageError) ?></div><?php endif; ?>
<?php if(!$liveEnabled&&$pageError===''): ?><div class="alert alert-warning accommodation-module-offline"><strong>ارتباط زنده اقامتگاه خاموش است.</strong><span>ثبت جدید، جست‌وجوی رزرو و تماس تازه با اقامتگاه متوقف است؛ سوابق مالی، هشدارها و بازیابی محلی همچنان در دسترس‌اند.</span><div class="panel-utility-actions"><a class="panel-icon-action" href="accommodation_settings.php" aria-label="تنظیم اتصال اقامتگاه" title="تنظیم اتصال"><?= ui_icon('settings') ?></a></div></div><?php endif; ?>

<div class="financial-workspace accommodation-page-stack panel-page-flow" data-visual-quality-page="accommodation_history">
<?php if($attentionTotal>0): ?>
<section class="card accommodation-issues-card financial-exception-surface">
 <div class="card-head"><div><h2><?= fa_digits($attentionTotal) ?> انتقال نیازمند رسیدگی</h2><small><?= e(implode(' · ',$attentionParts)) ?><?php if($attentionTotal>count($attention)): ?> · <?= fa_digits(count($attention)) ?> مورد اول نمایش داده شده<?php endif; ?></small></div><div class="panel-utility-actions"><a class="panel-icon-action" href="accommodation_settings.php" aria-label="تنظیم اتصال اقامتگاه" title="تنظیم اتصال"><?= ui_icon('settings') ?></a></div></div>
 <div class="card-body" id="accommodationIssues">
 <?php foreach($attention as $row): $display=accommodation_transfer_display($row);$tableName=accommodation_transfer_table_snapshot($row); ?>
  <article class="accommodation-issue-row" data-transfer-row="<?= (int)$row['id'] ?>"><div><strong><?= e((string)$row['guest_name_snapshot']) ?> · <?= e(fa_digits((string)$row['room_name_snapshot'])) ?></strong><span><?= e(accommodation_reservation_human_label((string)$row['reservation_code'])) ?> · <?= e(fa_digits($tableName)) ?></span><small><span class="financial-canonical-meta"><?= canonical_identifier_html((string)$row['reservation_code']) ?></span> · <?= e(toman((int)$row['amount'])) ?> · <?= e($display['label']) ?> · آخرین رویداد <?= e(format_jalali_compact((string)(accommodation_transfer_effective_time($row)?:$row['created_at']))) ?></small><?php if(!empty($row['last_error'])): ?><small class="text-danger"><?= e(accommodation_public_error_message((string)($row['last_error_code']??''),(string)$row['last_error'])) ?></small><?php endif; ?></div>
  <div class="actions">
   <?php if(!empty($row['can_finalize_local'])): ?><button class="btn btn-primary btn-sm accommodation-admin-action" data-action="finalize_local" data-id="<?= (int)$row['id'] ?>">تکمیل تسویه کافه</button>
   <?php elseif(!empty($row['local_reversal_pending'])): ?><button class="btn btn-primary btn-sm accommodation-open-reversal" data-id="<?= (int)$row['id'] ?>" data-label="<?= e(accommodation_reservation_human_label((string)$row['reservation_code']).' · '.fa_digits($tableName)) ?>" <?= !$freeTables?'disabled':'' ?>>تکمیل سند برگشتی</button><?php if(!$freeTables): ?><span class="muted">برای بازکردن حساب، ابتدا یک میز آزاد لازم است.</span><?php endif; ?>
   <?php elseif(!empty($row['retry_allowed'])): ?><?php if($liveEnabled): ?><button class="btn btn-primary btn-sm accommodation-admin-action" data-action="retry" data-id="<?= (int)$row['id'] ?>">تلاش مجدد</button><?php else: ?><span class="muted">برای تماس مجدد، ارتباط زنده را فعال کنید.</span><?php endif; ?>
   <?php elseif(in_array((string)$row['status'],['void_pending','void_failed'],true)): ?><span class="muted"><?= $liveEnabled?'ادامه برگشت از جریان ابطال تسویه انجام می‌شود.':'برای ادامه برگشت، ارتباط زنده را فعال کنید.' ?></span>
   <?php endif; ?>
  </div></article>
 <?php endforeach; ?>
 <?php if($attentionTotal>count($attention)): ?><div class="accommodation-more-issues"><a class="btn btn-light btn-sm" href="accommodation.php?status=needs_action">نمایش همه موارد نیازمند رسیدگی</a></div><?php endif; ?>
 </div>
</section>
<?php endif; ?>

<section class="card accommodation-history-card financial-page-shell financial-index-surface" data-financial-index-shell="accommodation">
 <div class="financial-page-head">
   <div class="financial-page-summary"><strong><?= fa_digits($totalRows) ?> <?= ($q!==''||$statusFilter!=='')?'نتیجه':'سابقه' ?></strong><?php if($totalPages>1): ?><small>صفحه <?= fa_digits($page) ?> از <?= fa_digits($totalPages) ?></small><?php endif; ?></div>
   <?php if($attentionTotal===0): ?><div class="panel-utility-actions"><a class="panel-icon-action" href="accommodation_settings.php" aria-label="تنظیم اتصال اقامتگاه" title="تنظیم اتصال"><?= ui_icon('settings') ?></a></div><?php endif; ?>
 </div>
 <div class="financial-page-toolbar"><form method="get" class="accommodation-history-search financial-filter-form"><label class="financial-search-field"><span class="sr-only">جست‌وجوی سوابق انتقال</span><input class="form-control" type="search" inputmode="search" enterkeyhint="search" autocomplete="off" name="q" value="<?= e($q) ?>" placeholder="جست‌وجوی مهمان، اتاق، رزرو، فاکتور یا میز"></label><select class="form-control financial-compact-select" name="status" data-choice-mode="compact" data-auto-submit aria-label="وضعیت انتقال"><option value="">همه سوابق</option><option value="needs_action" <?= $statusFilter==='needs_action'?'selected':'' ?>>نیازمند رسیدگی</option><option value="posted" <?= $statusFilter==='posted'?'selected':'' ?>>منتقل‌شده</option><option value="voided" <?= $statusFilter==='voided'?'selected':'' ?>>برگشت‌خورده</option></select><?php if($q!==''||$statusFilter!==''): ?><a class="panel-clear-filter" href="accommodation.php">پاک‌کردن</a><?php endif; ?></form></div>
 <div class="accommodation-history-list financial-list" aria-label="سوابق انتقال">
 <?php if(!$rows): ?><div class="financial-empty-state"><strong>سابقه‌ای پیدا نشد</strong><span><?= $q!==''||$statusFilter!==''?'فیلترها یا عبارت جست‌وجو را تغییر بده.':'پس از اولین انتقال، سوابق اینجا نمایش داده می‌شوند.' ?></span><?php if($q!==''||$statusFilter!==''): ?><a class="document-open-link" href="accommodation.php">پاک‌کردن فیلترها</a><?php endif; ?></div><?php endif; ?>
 <?php foreach($rows as $row): $display=accommodation_transfer_display($row);$eventTime=accommodation_transfer_effective_time($row);$tableName=accommodation_transfer_table_snapshot($row);$actor=(string)($row['status']==='voided'?($row['voided_by_name']?:$row['operator_name']):($row['resolved_at']?($row['resolved_by_name']?:$row['operator_name']):$row['operator_name']));$documentUrl=!empty($row['settlement_record_id'])&&!empty($row['invoice_number'])?'invoices.php?id='.(int)$row['settlement_record_id']:''; ?>
  <article class="accommodation-history-item financial-row<?= $documentUrl!==''?' is-navigable':'' ?>"<?= $documentUrl!==''?' data-row-href="'.e($documentUrl).'" role="link" tabindex="0" aria-label="مشاهده '.e(financial_document_human_label((string)$row['invoice_number'])).'"':'' ?>>
    <div class="financial-row-main accommodation-history-identity"><strong><?= e(accommodation_reservation_human_label((string)$row['reservation_code'])) ?> · <?= e(fa_digits($tableName)) ?></strong><span><?= e((string)$row['guest_name_snapshot']) ?> · <?= e(fa_digits((string)$row['room_name_snapshot'])) ?></span><small><?php if($documentUrl!==''): ?><?= e(financial_document_human_label((string)$row['invoice_number'])) ?> · <?php endif; ?><?= $eventTime?e(format_jalali_human_datetime($eventTime)):'—' ?><?php if((string)$row['status']!=='posted'&&$actor!==''): ?> · اقدام: <?= e($actor) ?><?php endif; ?></small></div>
    <div class="financial-row-amount"><strong><?= e(toman((int)$row['amount'])) ?></strong><?php if($display['needs_action']||(string)$row['status']!=='posted'): ?><span class="panel-status-badge is-<?= e((string)$display['tone']) ?>"><?= e((string)$display['label']) ?></span><?php endif; ?></div>
    <?php if($documentUrl!==''): ?><span class="financial-row-chevron" aria-hidden="true"><?= ui_icon('chevron-left') ?></span><?php endif; ?>
  </article>
 <?php endforeach; ?>
 </div>
 <?php if($totalPages>1): ?><nav class="panel-pagination financial-pagination" aria-label="صفحه‌بندی سوابق انتقال"><a class="btn btn-light btn-sm" href="<?= e($urlForPage(max(1,$page-1))) ?>" <?= $page<=1?'aria-disabled="true" tabindex="-1"':'' ?>>قبلی</a><span>صفحه <?= fa_digits($page) ?> از <?= fa_digits($totalPages) ?></span><a class="btn btn-light btn-sm" href="<?= e($urlForPage(min($totalPages,$page+1))) ?>" <?= $page>=$totalPages?'aria-disabled="true" tabindex="-1"':'' ?>>بعدی</a></nav><?php endif; ?>
</section>
</div>

<?php if($attentionTotal>0): ?>
<div class="success-modal hidden" id="accommodationReversalRecoveryModal" role="dialog" data-dialog-backdrop data-backdrop-close="0" aria-modal="true" aria-labelledby="accommodationReversalRecoveryTitle"><div class="success-box settlement-void-box"><div class="modal-head-v1280"><div><h2 id="accommodationReversalRecoveryTitle">تکمیل سند برگشتی کافه</h2><p id="accommodationReversalRecoveryLabel">برگشت در اقامتگاه قطعی است؛ فقط سند برگشت محلی تکمیل می‌شود.</p></div><button class="icon-btn" type="button" data-dialog-close aria-label="بستن"><?= ui_icon('close') ?></button></div><input type="hidden" id="accommodationReversalTransferId"><label class="form-group"><span>میز برای بازکردن حساب</span><select class="form-control" id="accommodationReversalTarget" data-choice-mode="embedded"><?php foreach($freeTables as $table): ?><option value="<?= (int)$table['id'] ?>"><?= e((string)$table['name']) ?> · <?= e((string)$table['zone_label']) ?></option><?php endforeach; ?></select></label><label class="form-group"><span>دلیل تکمیل برگشت</span><input class="form-control" id="accommodationReversalReason" maxlength="300" placeholder="مثلاً تکمیل بازیابی پس از قطع ارتباط"></label><div class="modal-actions"><button class="btn btn-primary" type="button" id="confirmAccommodationLocalReversal" <?= !$freeTables?'disabled':'' ?>>تکمیل سند و بازکردن حساب</button><button class="btn btn-light" type="button" data-dialog-close>انصراف</button></div></div></div>
<script>
window.ACCOMMODATION_API=<?= json_script(asset('operator/api_accommodation.php')) ?>;
(()=>{
 const csrf=document.querySelector('meta[name="csrf-token"]').content;
 const reversalModal=document.getElementById('accommodationReversalRecoveryModal');
 const post=async(payload)=>{const response=await fetch(window.ACCOMMODATION_API,{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify({...payload,csrf_token:csrf}),cache:'no-store'});const data=await response.json().catch(()=>({}));if(!response.ok||!data.success){const error=new Error(data.message||'عملیات انجام نشد.');error.httpStatus=response.status;error.data=data;throw error;}return data;};
 document.addEventListener('click',async(event)=>{
  const reversalButton=event.target.closest('.accommodation-open-reversal');
  if(reversalButton){document.getElementById('accommodationReversalTransferId').value=reversalButton.dataset.id;document.getElementById('accommodationReversalRecoveryLabel').textContent=`${reversalButton.dataset.label} · برگشت اقامتگاه قطعی است و فقط سند کافه تکمیل می‌شود.`;document.getElementById('accommodationReversalReason').value='';CafeUI.dialog.open(reversalModal,reversalButton);return;}
  const button=event.target.closest('.accommodation-admin-action');if(!button)return;
  const isLocal=button.dataset.action==='finalize_local';
  const accepted=await CafeUI.confirm(isLocal?'انتقال در اقامتگاه قبلاً قطعی شده است. فقط تسویه محلی کافه تکمیل شود؟':'این فاکتور با همان شناسه دوباره به اقامتگاه ارسال شود؟',isLocal?'تکمیل تسویه کافه':'تلاش مجدد',{okLabel:isLocal?'تکمیل تسویه':'تلاش مجدد',trigger:button});
  if(!accepted)return;button.disabled=true;
  try{const data=await post({action:button.dataset.action,transfer_id:Number(button.dataset.id)});CafeUI.toast(data.message||'انجام شد.');location.reload();}catch(error){CafeUI.toast(CafeUI.requestErrorMessage(error),'error');button.disabled=false;}
 });
 document.getElementById('confirmAccommodationLocalReversal')?.addEventListener('click',async(event)=>{const button=event.currentTarget;const transferId=Number(document.getElementById('accommodationReversalTransferId').value);const targetTableId=Number(document.getElementById('accommodationReversalTarget').value);const reason=document.getElementById('accommodationReversalReason').value.trim();if(!reason){CafeUI.toast('دلیل تکمیل برگشت را وارد کنید.','error');return;}button.disabled=true;try{const data=await post({action:'finalize_local_reversal',transfer_id:transferId,target_table_id:targetTableId,reason});CafeUI.toast(data.message||'سند برگشتی تکمیل شد.');CafeUI.dialog.close(reversalModal);location.reload();}catch(error){CafeUI.toast(CafeUI.requestErrorMessage(error),'error');button.disabled=false;}});
})();
</script>
<?php endif; ?>
<?php panel_footer(); ?>
