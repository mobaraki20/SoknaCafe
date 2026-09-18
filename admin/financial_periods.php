<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';
require_login(['admin']);
require dirname(__DIR__) . '/includes/panel_layout.php';
$pdo=db();$userId=(int)current_user()['id'];
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf($_POST['csrf_token']??null);
 $action=(string)($_POST['action']??'');
 try{
  if($action==='resolve_deferred_review'){
   $reviewId=(int)($_POST['review_id']??0);
   $decision=(string)($_POST['decision']??'');
   $reason=(string)($_POST['resolution_reason']??'');
   if($reviewId<1)throw new RuntimeException('مورد بررسی معتبر نیست.');
   $result=sokna_deferred_resolve_review($reviewId,$decision,$reason,$userId);
   flash('success',$result['state']==='committed'?'رخداد پس از بررسی صریح ثبت شد.':'رخداد Deferred رد شد و اثری روی اطلاعات عملیاتی نگذاشت.');
   redirect('financial_periods.php');
  }

  if($action!=='close')throw new RuntimeException('عملیات دوره مالی معتبر نیست.');
  $id=(int)($_POST['id']??0);if($id<1)throw new RuntimeException('دوره مالی معتبر نیست.');
  if(empty($_POST['confirm_backup']))throw new RuntimeException('ابتدا تهیه نسخه پشتیبان را تأیید کن.');

  $previewStmt=$pdo->prepare('SELECT * FROM financial_periods WHERE id=? LIMIT 1');
  $previewStmt->execute([$id]);$preview=$previewStmt->fetch();
  if(!$preview)throw new RuntimeException('دوره مالی پیدا نشد.');
  $deferredStatus=sokna_deferred_period_close_status($pdo,$preview);
  $overrideRequested=!empty($_POST['override_deferred']);
  $overrideReason=text_substr(trim((string)($_POST['override_reason']??'')),0,500);
  $deferredBlocked=empty($deferredStatus['known'])||(int)($deferredStatus['blocking']??0)>0;
  if($deferredBlocked&&!$overrideRequested){
   if(empty($deferredStatus['known']))throw new RuntimeException('وضعیت کارهای راه‌دور Public قابل تأیید نیست؛ بستن عادی دوره تا برقراری اتصال متوقف است.');
   throw new RuntimeException('برای این دوره کار Deferred در انتظار همگام‌سازی یا بررسی وجود دارد؛ ابتدا آن‌ها را تعیین‌تکلیف کن.');
  }
  if($overrideRequested){
   if(!$deferredBlocked)throw new RuntimeException('در حال حاضر مانع Deferred برای این دوره وجود ندارد و نیازی به ثبت استثنا نیست.');
   if($overrideReason==='')throw new RuntimeException('برای بستن دوره با استثنا، دلیل مدیریتی را ثبت کن.');
  }

  $pdo->beginTransaction();
  $st=$pdo->prepare('SELECT * FROM financial_periods WHERE id=? FOR UPDATE');$st->execute([$id]);$period=$st->fetch();if(!$period)throw new RuntimeException('دوره مالی پیدا نشد.');
  if((string)$period['status']==='closed'){ $pdo->commit();flash('success','این دوره قبلاً بسته شده است.');redirect('financial_periods.php'); }
  if(business_current_date()<=(string)$period['end_date'])throw new RuntimeException('این سال مالی هنوز به پایان نرسیده است.');

  if(!$overrideRequested){
   $localReview=$pdo->prepare("SELECT COUNT(*) FROM deferred_review_items WHERE financial_period_id=? AND state='pending'");
   $localReview->execute([$id]);
   if((int)$localReview->fetchColumn()>0)throw new RuntimeException('در زمان بستن دوره یک بررسی Deferred تازه ایجاد شد؛ ابتدا آن را تعیین‌تکلیف کن.');
  }else{
   sokna_deferred_record_close_override_locked($pdo,$id,$userId,$overrideReason,$deferredStatus);
  }

  $open=(int)$pdo->query("SELECT COUNT(*) FROM table_sessions WHERE status IN('active','pending')")->fetchColumn();if($open>0)throw new RuntimeException('تا زمانی که حساب میز باز وجود دارد، سال مالی بسته نمی‌شود.');
  $pending=(int)$pdo->query("SELECT COUNT(*) FROM accommodation_transfers WHERE resolved_at IS NULL AND (status IN('pending','void_pending','void_failed') OR suspicious_response=1)")->fetchColumn();if($pending>0)throw new RuntimeException('انتقال اقامتگاه تعیین‌تکلیف‌نشده وجود دارد.');
  $sum=$pdo->prepare("SELECT SUM(status='completed') invoice_count,SUM(status='reversal') reversal_count,COALESCE(SUM(CASE WHEN status='completed' THEN total WHEN status='reversal' THEN -total ELSE 0 END),0) total_amount,COALESCE(SUM(CASE WHEN status='completed' THEN discount WHEN status='reversal' THEN -discount ELSE 0 END),0) discount_amount,COALESCE(SUM(status='completed' AND destination='direct'),0) direct_count,COALESCE(SUM(status='completed' AND destination='accommodation'),0) accommodation_count,COALESCE(SUM(status='completed' AND destination='subscriber'),0) subscriber_count FROM settlement_records WHERE financial_period_id=?");$sum->execute([$id]);$summary=$sum->fetch()?:[];
  $summary['deferred_close_status']=$deferredStatus;
  $summary['deferred_override']=$overrideRequested;
  $pdo->prepare("UPDATE financial_periods SET status='closed',closed_at=NOW(),closed_by_user_id=?,close_summary_json=? WHERE id=?")->execute([$userId,json_encode($summary,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$id]);
  $nextDate=(new DateTimeImmutable((string)$period['end_date']))->modify('+1 day')->format('Y-m-d');financial_period_for_date_locked($pdo,$nextDate,$userId);
  audit_log_write_strict($pdo,'financial_period.closed','financial_period',$id,$summary,$userId);
  $pdo->commit();flash('success',$overrideRequested?'سال مالی با ثبت استثنای Deferred بسته و دوره بعدی باز شد.':'سال مالی بسته و دوره بعدی باز شد.');
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('financial period action: '.$e->getMessage());flash('error',safe_business_error_message($e,'عملیات دوره مالی انجام نشد.'));}
 redirect('financial_periods.php');
}
$todayPeriod=null;
try{$pdo->beginTransaction();$todayPeriod=financial_period_for_date_locked($pdo,business_current_date(),$userId);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('financial period ensure current: '.$e->getMessage());flash('error',safe_business_error_message($e,'دوره مالی جاری آماده نشد.'));}
$periods=$pdo->query("SELECT fp.*,COALESCE(s.invoice_count,0) invoice_count,COALESCE(s.reversal_count,0) reversal_count,COALESCE(s.total_amount,0) total_amount,COALESCE(s.discount_amount,0) discount_amount FROM financial_periods fp LEFT JOIN (SELECT financial_period_id,SUM(status='completed') invoice_count,SUM(status='reversal') reversal_count,SUM(CASE WHEN status='completed' THEN total WHEN status='reversal' THEN -total ELSE 0 END) total_amount,SUM(CASE WHEN status='completed' THEN discount WHEN status='reversal' THEN -discount ELSE 0 END) discount_amount FROM settlement_records GROUP BY financial_period_id)s ON s.financial_period_id=fp.id ORDER BY fp.start_date DESC")->fetchAll();
$currentRow=null;foreach($periods as $periodRow){if((int)$periodRow['id']===(int)($todayPeriod['id']??0)){$currentRow=$periodRow;break;}}
$currentPeriodId=(int)($todayPeriod['id']??0);
$historyPeriods=array_values(array_filter($periods,static fn(array $periodRow):bool=>(int)$periodRow['id']!==$currentPeriodId));
$deferredReviews=sokna_deferred_pending_reviews($pdo,100);
panel_header('دوره‌های مالی','financial_periods');
?>
<div class="financial-workspace financial-period-workspace panel-page-flow" data-visual-quality-page="financial_periods">
  <section class="card financial-period-current financial-record-hero">
    <div class="financial-period-current-head">
      <div class="panel-copy-stack">
        <small class="muted">دوره جاری</small>
        <div class="financial-period-title-row"><h2><?= e((string)($todayPeriod['title']??'نامشخص')) ?></h2><span class="panel-status-badge is-success">باز</span></div>
        <p class="muted"><?= e(jalali_date_input($todayPeriod['start_date']??null)) ?> تا <?= e(jalali_date_input($todayPeriod['end_date']??null)) ?></p>
      </div>
      <a class="btn btn-light btn-sm" href="invoices.php?period=<?= (int)($todayPeriod['id']??0) ?>">فاکتورهای این دوره</a>
    </div>
    <div class="financial-metric-strip" aria-label="خلاصه دوره جاری">
      <div><span>فروش خالص</span><strong><?= e(toman((int)($currentRow['total_amount']??0))) ?></strong></div>
      <div><span>فاکتور</span><strong><?= fa_digits((int)($currentRow['invoice_count']??0)) ?></strong></div>
      <div><span>تخفیف</span><strong><?= e(toman((int)($currentRow['discount_amount']??0))) ?></strong></div>
      <div><span>اسناد برگشت</span><strong><?= fa_digits((int)($currentRow['reversal_count']??0)) ?></strong></div>
    </div>
  </section>

  <details class="financial-context-strip financial-info-disclosure"><summary><span>نحوه اتصال اسناد به دوره مالی</span><?= ui_icon('chevron-down') ?></summary><div class="financial-info-copy">هر فاکتور هنگام تسویه به دوره مالی همان تاریخ متصل می‌شود؛ صندوق دوره را دستی انتخاب نمی‌کند.</div></details>

  <?php if($deferredReviews): ?>
  <section class="card financial-record-section">
    <div class="card-head"><div class="panel-copy-stack"><h2>بررسی کارهای راه‌دور</h2><small>این موارد هیچ اثر نهایی روی دوره بسته یا داده متعارض ندارند تا مدیر صریحاً تصمیم بگیرد.</small></div><span class="panel-status-badge is-warning"><?= fa_digits(count($deferredReviews)) ?> مورد</span></div>
    <div class="financial-list">
      <?php foreach($deferredReviews as $review): ?>
      <article class="financial-row">
        <div class="financial-row-main">
          <strong><?= e((string)$review['kind']) ?></strong>
          <span><?= e((string)($review['actor_name']?:'کاربر راه‌دور')) ?> · <?= e(format_jalali_compact((string)$review['occurred_at'])) ?></span>
          <small><?= e((string)$review['message']) ?><?php if(!empty($review['period_title'])): ?> · <?= e((string)$review['period_title']) ?><?php endif; ?></small>
        </div>
        <form method="post" class="panel-page-flow" style="min-width:min(100%,340px)"><?= csrf_field() ?><input type="hidden" name="action" value="resolve_deferred_review"><input type="hidden" name="review_id" value="<?= (int)$review['review_id'] ?>"><textarea class="form-control" name="resolution_reason" required maxlength="500" placeholder="دلیل تصمیم مدیریتی"></textarea><div class="row-actions"><button class="btn btn-primary btn-sm" name="decision" value="approve">تأیید و ثبت</button><button class="btn btn-danger btn-sm" name="decision" value="reject">رد کردن</button></div></form>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="card financial-period-history financial-index-surface financial-record-section">
    <div class="card-head"><div class="panel-copy-stack"><h2>دوره‌های قبلی</h2><small><?= $historyPeriods ? fa_digits(count($historyPeriods)).' دوره' : 'هنوز دوره قبلی وجود ندارد' ?> · دوره بسته حذف یا بازنویسی نمی‌شود.</small></div></div>
    <div class="financial-period-list financial-list">
      <?php if(!$historyPeriods): ?><div class="empty-state">پس از پایان دوره جاری، دوره‌های قبلی اینجا نمایش داده می‌شوند.</div><?php endif; ?>
      <?php foreach($historyPeriods as $period): $isOpen=(string)$period['status']==='open'; $canClose=$isOpen&&business_current_date()>(string)$period['end_date']; ?>
      <article class="financial-period-row financial-row is-navigable" data-row-href="invoices.php?period=<?= (int)$period['id'] ?>" role="link" tabindex="0" aria-label="مشاهده فاکتورهای <?= e((string)$period['title']) ?>">
        <div class="financial-row-main">
          <strong><?= e((string)$period['title']) ?></strong>
          <span><?= e(jalali_date_input($period['start_date'])) ?> تا <?= e(jalali_date_input($period['end_date'])) ?></span>
          <small><?= fa_digits((int)$period['invoice_count']) ?> فاکتور<?php if((int)$period['reversal_count']>0): ?> · <?= fa_digits((int)$period['reversal_count']) ?> برگشت<?php endif; ?><?php if($isOpen): ?> · <span class="text-warning">نیازمند بستن</span><?php endif; ?></small>
        </div>
        <div class="financial-row-amount"><strong><?= e(toman((int)$period['total_amount'])) ?></strong><small>فروش خالص</small></div>
        <div class="financial-period-row-actions">
          <a class="document-open-link" href="invoices.php?period=<?= (int)$period['id'] ?>">مشاهده فاکتورها</a>
          <?php if($canClose): ?><form method="post" data-confirm="سال مالی پس از بسته‌شدن مستقیم ویرایش نمی‌شود. پیش از ادامه از فایل‌ها و دیتابیس نسخه پشتیبان کامل بگیرید." data-confirm-title="بستن دوره مالی" data-confirm-ok="بستن دوره" data-confirm-danger="1"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$period['id'] ?>"><input type="hidden" name="confirm_backup" value="1"><button class="btn btn-danger btn-sm" name="action" value="close">بستن دوره</button></form><details class="financial-info-disclosure"><summary>استثنای مدیریتی</summary><form method="post" class="panel-page-flow" data-confirm="این مسیر فقط وقتی Public در دسترس نیست یا کار Deferred تعیین‌تکلیف‌نشده وجود دارد استفاده شود. رخداد دیررس بعدی خودکار وارد دوره بسته نمی‌شود." data-confirm-title="بستن با استثنا؟" data-confirm-ok="ثبت استثنا و بستن" data-confirm-danger="1"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$period['id'] ?>"><input type="hidden" name="confirm_backup" value="1"><input type="hidden" name="override_deferred" value="1"><textarea class="form-control" name="override_reason" maxlength="500" required placeholder="دلیل استثنا"></textarea><button class="btn btn-danger btn-sm" name="action" value="close">بستن با ثبت استثنا</button></form></details><?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
</div>
<?php panel_footer(); ?>
