<?php
declare(strict_types=1);

function invoice_presented_items(array $items): array
{
    return financial_receipt_presented_items($items);
}


function render_invoices_page(bool $adminContext): void
{
    // The admin/operator routes intentionally share one presentation owner.
    // Authorization is capability-based; $adminContext is retained for route compatibility.
    $user = current_user();
    if (!user_has_capability('cashier_accounts', $user) && !is_admin()) {
        http_response_code(403);
        exit('دسترسی به فاکتورها برای این حساب فعال نیست.');
    }

    $pdo = db();
    $allowedStatuses = ['completed', 'voided', 'reversal'];
    $q = text_substr(trim((string)($_GET['q'] ?? $_POST['q'] ?? '')), 0, 120);
    $destination = (string)($_GET['destination'] ?? $_POST['destination'] ?? '');
    if (!isset(settlement_destinations()[$destination])) $destination = '';
    $fromInput = trim((string)($_GET['from'] ?? $_POST['from'] ?? ''));
    $toInput = trim((string)($_GET['to'] ?? $_POST['to'] ?? ''));
    $range = resolve_optional_jalali_range($fromInput, $toInput);
    $from = $range['from'];
    $to = $range['to'];
    $filterError = (string)$range['error'];
    $periodId = max(0, (int)($_GET['period'] ?? $_POST['period'] ?? 0));
    $status = (string)($_GET['status'] ?? $_POST['status'] ?? '');
    if (!in_array($status, $allowedStatuses, true)) $status = '';
    $page = max(1, (int)($_GET['page'] ?? $_POST['page'] ?? 1));
    $perPage = 30;

    $contextParams = [];
    if ($q !== '') $contextParams['q'] = $q;
    if ($periodId > 0) $contextParams['period'] = $periodId;
    if ($destination !== '') $contextParams['destination'] = $destination;
    if ($status !== '') $contextParams['status'] = $status;
    if ($fromInput !== '') $contextParams['from'] = $fromInput;
    if ($toInput !== '') $contextParams['to'] = $toInput;
    if ($page > 1) $contextParams['page'] = $page;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf($_POST['csrf_token'] ?? null);
        $action = (string)($_POST['action'] ?? '');
        $id = (int)($_POST['id'] ?? 0);
        try {
            if ($action !== 'reprint' || $id < 1) throw new RuntimeException('عملیات فاکتور معتبر نیست.');
            $requestToken = trim((string)($_POST['request_token'] ?? ''));
            if (!preg_match('/^[a-f0-9]{32}$/', $requestToken)) throw new RuntimeException('فرم چاپ منقضی شده است؛ صفحه را تازه کنید.');
            $stmt = $pdo->prepare("SELECT sr.* FROM settlement_records sr WHERE sr.id=? AND sr.status='completed' AND NOT EXISTS(SELECT 1 FROM settlement_records rev WHERE rev.reverses_settlement_id=sr.id AND rev.status='reversal') LIMIT 1");
            $stmt->execute([$id]);
            $record = $stmt->fetch();
            if (!$record) throw new RuntimeException('فقط فاکتور ثبت‌شده قابل چاپ مجدد است.');
            if (!print_destination_ready($pdo, 'customer_receipt')) throw new RuntimeException('مقصد سند مشتری فعال نیست.');
            $reprintKey = 'final.reprint.invoice.' . $id . '.' . $requestToken;
            $job = print_enqueue_final_invoice($pdo, (int)$record['id'], (string)$record['destination'], (int)$user['id'], true, $reprintKey);
            if (empty($job['duplicate'])) {
                audit_log_write('settlement.invoice_reprinted', 'settlement_record', $id, ['print_job_id'=>(int)($job['id'] ?? 0)], (int)$user['id']);
            }
            flash('success', !empty($job['duplicate']) ? 'این درخواست چاپ قبلاً وارد صف شده است.' : 'چاپ مجدد فاکتور وارد صف شد.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        } catch (Throwable $e) {
            error_log('invoice reprint: ' . $e->getMessage());
            flash('error', 'چاپ مجدد در صف قرار نگرفت. وضعیت چاپ را بررسی و دوباره تلاش کنید.');
        }
        $returnParams = $contextParams;
        if (!empty($_POST['detail'])) $returnParams = ['id'=>$id] + $returnParams;
        redirect('invoices.php' . ($returnParams ? '?' . http_build_query($returnParams) : ''));
    }

    $detailId = (int)($_GET['id'] ?? 0);
    $detail = null;
    $snapshot = null;
    if ($detailId > 0) {
        $stmt = $pdo->prepare("SELECT sr.*,fp.title financial_period_title,COALESCE(u.display_name,'—') actor_name,sub.name subscriber_name,sub.mobile subscriber_mobile,at.guest_name_snapshot accommodation_guest,at.room_name_snapshot accommodation_room,at.reservation_code,CASE WHEN sr.status='completed' AND rev.id IS NOT NULL THEN 'voided' ELSE sr.status END effective_status,rev.id reversal_settlement_id,rev.invoice_number reversal_invoice_number,orig.invoice_number reverses_invoice_number FROM settlement_records sr JOIN financial_periods fp ON fp.id=sr.financial_period_id LEFT JOIN settlement_records rev ON rev.reverses_settlement_id=sr.id AND rev.status='reversal' LEFT JOIN settlement_records orig ON orig.id=sr.reverses_settlement_id LEFT JOIN users u ON u.id=sr.actor_user_id LEFT JOIN subscriber_ledger sl ON sl.id=sr.subscriber_ledger_entry_id LEFT JOIN subscribers sub ON sub.id=sl.subscriber_id LEFT JOIN accommodation_transfers at ON at.id=sr.accommodation_transfer_id WHERE sr.id=? LIMIT 1");
        $stmt->execute([$detailId]);
        $detail = $stmt->fetch() ?: null;
        if ($detail) {
            $detail['status'] = (string)($detail['effective_status'] ?? $detail['status']);
            $snapshot = json_decode((string)$detail['invoice_snapshot_json'], true);
            if (!is_array($snapshot)) $snapshot = [];
        }
    }

    $baseParams = $contextParams;
    unset($baseParams['page']);
    $urlForPage = static function (int $targetPage) use ($baseParams): string {
        $p = $baseParams;
        if ($targetPage > 1) $p['page'] = $targetPage;
        return 'invoices.php' . ($p ? '?' . http_build_query($p) : '');
    };

    panel_header('فاکتورها', 'invoices');

    // Detail is one focused financial document. Archive and detail share the same human-facing identity.
    if ($detailId > 0) {
        if (!$detail) {
            ?><div class="alert alert-error">فاکتور پیدا نشد.</div><div class="actions"><a class="btn btn-light" href="<?= e($urlForPage($page)) ?>">بازگشت به فاکتورها</a></div><?php
            panel_footer();
            return;
        }
        $isReversal = (string)$detail['status'] === 'reversal';
        $isVoided = (string)$detail['status'] === 'voided';
        $sign = $isReversal ? -1 : 1;
        $requestToken = bin2hex(random_bytes(16));
        $businessDateRaw = (string)$detail['business_date'];
        $businessDate = format_jalali_date($businessDateRaw, false);
        $settledAt = (string)$detail['settled_at'];
        $settledTimestamp = strtotime($settledAt) ?: 0;
        $settledDateRaw = substr($settledAt, 0, 10);
        $settledDate = $settledDateRaw !== '' ? format_jalali_date($settledDateRaw, false) : '';
        $settledTime = $settledTimestamp ? fa_digits(date('H:i', $settledTimestamp)) : '—';
        $timeLabel = ($businessDateRaw !== '' && $businessDateRaw === $settledDateRaw)
            ? $businessDate . ' · ' . $settledTime
            : 'روز کاری ' . $businessDate . ($settledDate !== '' ? ' · ثبت ' . $settledDate . '، ' . $settledTime : '');
        if (!empty($detail['business_shift_label'])) $timeLabel .= ' · ' . (string)$detail['business_shift_label'];
        $presentedItems = invoice_presented_items((array)($snapshot['items'] ?? []));
        $documentParts = financial_document_reference_parts((string)$detail['invoice_number']);
        $humanReference = financial_document_human_label((string)$detail['invoice_number']);
        ?>
        <div class="financial-workspace financial-document-workspace panel-page-flow" data-visual-quality-page="invoice_detail">
        <div class="panel-detail-nav invoice-detail-back"><a class="panel-back-link" href="<?= e($urlForPage($page)) ?>"><?= ui_icon('chevron-right') ?> بازگشت به فاکتورها</a></div>
        <section class="card invoice-detail-card financial-record" id="invoiceDetail">
          <div class="card-body invoice-receipt">
            <header class="invoice-receipt-summary">
              <div class="invoice-receipt-title">
                <div><h2><?= e($humanReference) ?></h2></div>
                <div class="invoice-receipt-title-actions"><span class="badge badge-<?= e((string)$detail['status']) ?>"><?= e(settlement_status_label((string)$detail['status'])) ?></span><?php if((string)$detail['status']==='completed'): ?><form method="post" class="panel-utility-actions"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$detail['id'] ?>"><input type="hidden" name="detail" value="1"><input type="hidden" name="request_token" value="<?= e($requestToken) ?>"><?php foreach($contextParams as $key=>$value): ?><input type="hidden" name="<?= e((string)$key) ?>" value="<?= e((string)$value) ?>"><?php endforeach; ?><button class="panel-icon-action" name="action" value="reprint" type="submit" aria-label="چاپ مجدد فاکتور" title="چاپ مجدد" data-click-confirm="یک نسخه جدید از همین فاکتور برای چاپ فرستاده می‌شود." data-confirm-title="چاپ دوباره فاکتور؟" data-confirm-ok="چاپ دوباره"><?= ui_icon('print') ?></button></form><?php endif; ?></div>
              </div>
              <div class="invoice-receipt-context"><div class="invoice-receipt-context-main"><strong><?= e(fa_digits((string)$detail['table_name_snapshot'])) ?> · <?= e(settlement_destination_label((string)$detail['destination'])) ?></strong></div><?php if($detail['subscriber_name']): ?><div class="invoice-receipt-party"><?= e((string)$detail['subscriber_name']) ?></div><?php elseif($detail['accommodation_guest']): ?><div class="invoice-receipt-party"><?= e((string)$detail['accommodation_guest']) ?><?= !empty($detail['accommodation_room'])?' · '.e(fa_digits((string)$detail['accommodation_room'])):'' ?></div><?php endif; ?></div>
              <div class="invoice-receipt-time"><?= e($timeLabel) ?></div>
            </header>

            <?php if($isReversal): ?>
              <div class="invoice-related-document is-reversal">
                <strong>برگشت <?= e(financial_document_human_label((string)($detail['reverses_invoice_number'] ?? ''))) ?></strong>
                <span>اثر مالی فاکتور اصلی با این سند معکوس شده است.</span>
                <?php if(!empty($detail['reverses_invoice_number'])): ?><a href="invoices.php?q=<?= e(rawurlencode((string)$detail['reverses_invoice_number'])) ?>">مشاهده فاکتور اصلی</a><?php endif; ?>
              </div>
            <?php elseif($isVoided && !empty($detail['reversal_invoice_number'])): ?>
              <div class="invoice-related-document is-voided">
                <strong><?= e(financial_document_human_label((string)$detail['reversal_invoice_number'])) ?></strong>
                <span><?= !empty($detail['void_reason']) ? 'دلیل برگشت: '.e((string)$detail['void_reason']) : 'اثر مالی این فاکتور با سند برگشت معکوس شده است.' ?></span>
                <a href="invoices.php?q=<?= e(rawurlencode((string)$detail['reversal_invoice_number'])) ?>">مشاهده سند برگشت</a>
              </div>
            <?php endif; ?>

            <section class="invoice-receipt-section"><div class="invoice-receipt-section-head"><h3>اقلام فاکتور</h3></div><div class="invoice-receipt-lines">
              <?php foreach($presentedItems as $item): ?><div class="invoice-receipt-line"><div><strong><?= e((string)$item['name']) ?></strong><?php if($item['note']!==''): ?><small><?= e((string)$item['note']) ?></small><?php endif; ?><small class="invoice-line-math"><bdi dir="ltr"><?= e(fa_digits((int)$item['quantity'])) ?> × <?= e(toman_number((int)$item['unit_price'])) ?></bdi></small></div><strong class="invoice-receipt-line-total"><?= $isReversal?'− ':'' ?><?= e(toman_number((int)$item['line_total'])) ?></strong></div><?php endforeach; ?>
            </div></section>

            <section class="invoice-receipt-totals"><?php $taxAmount=(int)($detail['tax_amount']??0); $netBeforeTax=(int)$detail['total']-$taxAmount; ?><?php if($isReversal || (int)$detail['discount']!==0 || $taxAmount!==0): ?><div><span>جمع اقلام</span><strong><?= $isReversal?'− ':'' ?><?= e(toman_number((int)$detail['subtotal'])) ?></strong></div><?php endif; ?><?php if((int)$detail['discount']!==0): ?><div><span><?= $isReversal?'برگشت تخفیف':'تخفیف' ?></span><strong><?= $isReversal?'+ ':'' ?><?= e(toman_number((int)$detail['discount'])) ?></strong></div><?php endif; ?><?php if($taxAmount!==0): ?><div><span>مبلغ پس از تخفیف</span><strong><?= e(toman($sign*$netBeforeTax)) ?></strong></div><div><span><?= $isReversal?'برگشت مالیات':'مالیات' ?></span><strong><?= e(toman($sign*$taxAmount)) ?></strong></div><?php endif; ?><div class="is-final"><span><?= $isReversal?'اثر مالی':'مبلغ نهایی' ?></span><strong><?= e(toman($sign * (int)$detail['total'])) ?></strong></div></section>

            <details class="panel-disclosure invoice-receipt-meta"><summary><span>اطلاعات ثبت</span><?= ui_icon('chevron-down') ?></summary><dl><div><dt>شناسه سند</dt><dd><?= canonical_identifier_html((string)$detail['invoice_number']) ?></dd></div><div><dt>ثبت‌کننده</dt><dd><?= e((string)$detail['actor_name']) ?></dd></div><div><dt>دوره مالی</dt><dd><?= e((string)$detail['financial_period_title']) ?></dd></div><?php if($detail['subscriber_name']): ?><div><dt>موبایل مشتری</dt><dd><bdi dir="ltr"><?= e(fa_digits((string)$detail['subscriber_mobile'])) ?></bdi></dd></div><?php endif; ?><?php if(!empty($detail['reservation_code'])): ?><div><dt>رزرو</dt><dd><?= canonical_identifier_html((string)$detail['reservation_code']) ?></dd></div><?php endif; ?></dl></details>
          </div>
        </section>
        </div>
        <?php
        panel_footer();
        return;
    }

    $conditions = ['1=1'];
    $params = [];
    if ($filterError !== '') $conditions[] = '0=1';
    if ($q !== '') {
        $searchToken = financial_document_search_token($q);
        $asciiQuery = normalize_persian_search($q);
        $faQuery = fa_digits(en_digits($q));
        $normalizedLike = '%' . $asciiQuery . '%';
        $rawLike = '%' . $q . '%';
        $faLike = '%' . $faQuery . '%';
        $conditions[] = "(sr.invoice_number LIKE ? OR at.reservation_code LIKE ? OR sr.table_name_snapshot LIKE ? OR sr.table_name_snapshot LIKE ? OR REPLACE(REPLACE(sub.name,'ي','ی'),'ك','ک') LIKE ? OR sub.mobile LIKE ? OR REPLACE(REPLACE(at.guest_name_snapshot,'ي','ی'),'ك','ک') LIKE ? OR REPLACE(REPLACE(at.room_name_snapshot,'ي','ی'),'ك','ک') LIKE ?)";
        array_push($params, '%' . $searchToken . '%', '%' . $searchToken . '%', $rawLike, $faLike, $normalizedLike, '%' . $searchToken . '%', $normalizedLike, $normalizedLike);
    }
    if ($destination !== '') { $conditions[] = 'sr.destination=?'; $params[] = $destination; }
    if ($from) { $conditions[] = 'sr.business_date>=?'; $params[] = $from; }
    if ($to) { $conditions[] = 'sr.business_date<=?'; $params[] = $to; }
    if ($periodId > 0) { $conditions[] = 'sr.financial_period_id=?'; $params[] = $periodId; }
    $effectiveStatusSql = "CASE WHEN sr.status='completed' AND rev.id IS NOT NULL THEN 'voided' ELSE sr.status END";
    if ($status !== '') { $conditions[] = $effectiveStatusSql . '=?'; $params[] = $status; }
    $whereSql = implode(' AND ', $conditions);
    $commonJoins = " FROM settlement_records sr JOIN financial_periods fp ON fp.id=sr.financial_period_id LEFT JOIN settlement_records rev ON rev.reverses_settlement_id=sr.id AND rev.status='reversal' LEFT JOIN settlement_records orig ON orig.id=sr.reverses_settlement_id LEFT JOIN users u ON u.id=sr.actor_user_id LEFT JOIN subscriber_ledger sl ON sl.id=sr.subscriber_ledger_entry_id LEFT JOIN subscribers sub ON sub.id=sl.subscriber_id LEFT JOIN accommodation_transfers at ON at.id=sr.accommodation_transfer_id";

    $countStmt = $pdo->prepare('SELECT COUNT(DISTINCT sr.id)' . $commonJoins . ' WHERE ' . $whereSql);
    $countStmt->execute($params);
    $totalRows = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    if ($page > $totalPages) $page = $totalPages;
    $offset = ($page - 1) * $perPage;

    $sql = "SELECT sr.*,fp.title financial_period_title,COALESCE(u.display_name,'—') actor_name,sub.name subscriber_name,at.guest_name_snapshot accommodation_guest,at.room_name_snapshot accommodation_room,$effectiveStatusSql effective_status,rev.invoice_number reversal_invoice_number,orig.invoice_number reverses_invoice_number" . $commonJoins . ' WHERE ' . $whereSql . ' ORDER BY sr.business_date DESC,sr.settled_at DESC,sr.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) $row['status'] = (string)($row['effective_status'] ?? $row['status']);
    unset($row);
    $periods = $pdo->query('SELECT id,title,status FROM financial_periods ORDER BY start_date DESC')->fetchAll();
    $activeFilterLabels = [];
    if ($periodId > 0) {
        foreach ($periods as $period) {
            if ((int)$period['id'] === $periodId) { $activeFilterLabels[] = (string)$period['title']; break; }
        }
    }
    if ($destination !== '') $activeFilterLabels[] = settlement_destination_label($destination);
    if ($status !== '') $activeFilterLabels[] = settlement_status_label($status);
    if ($fromInput !== '' || $toInput !== '') {
        $rangeLabel = ($fromInput !== '' ? fa_digits($fromInput) : 'ابتدا') . ' تا ' . ($toInput !== '' ? fa_digits($toInput) : 'امروز');
        $activeFilterLabels[] = $rangeLabel;
    }

    $detailUrl = static function (int $id) use ($contextParams): string {
        $p = ['id'=>$id] + $contextParams;
        return 'invoices.php?' . http_build_query($p);
    };
    $activeAdvancedFilters = ($periodId > 0 ? 1 : 0) + ($destination !== '' ? 1 : 0) + ($status !== '' ? 1 : 0) + ($fromInput !== '' ? 1 : 0) + ($toInput !== '' ? 1 : 0);
    ?>
    <div class="financial-workspace financial-invoice-workspace panel-page-flow" data-visual-quality-page="invoices_archive">
    <?php if($filterError!==''): ?><div class="alert alert-error"><?= e($filterError) ?></div><?php endif; ?>
    <section class="card financial-page-shell financial-index-surface" data-financial-index-shell="invoices">
      <div class="financial-page-head">
        <div class="financial-page-summary"><strong><?= e(fa_digits($totalRows)) ?> <?= $activeAdvancedFilters||$q!==''?'نتیجه':'فاکتور' ?></strong><?php if($activeFilterLabels): ?><small><?= e(fa_digits(count($activeFilterLabels))) ?> فیلتر فعال</small><?php endif; ?></div>
      </div>
      <div class="financial-page-toolbar">
        <form method="get" class="financial-search-form" role="search">
          <?php if($periodId>0): ?><input type="hidden" name="period" value="<?= (int)$periodId ?>"><?php endif; ?>
          <?php if($destination!==''): ?><input type="hidden" name="destination" value="<?= e($destination) ?>"><?php endif; ?>
          <?php if($status!==''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
          <?php if($fromInput!==''): ?><input type="hidden" name="from" value="<?= e($fromInput) ?>"><?php endif; ?>
          <?php if($toInput!==''): ?><input type="hidden" name="to" value="<?= e($toInput) ?>"><?php endif; ?>
          <label class="financial-search-field"><span class="sr-only">جست‌وجوی فاکتورها</span><input class="form-control" type="search" inputmode="search" enterkeyhint="search" autocomplete="off" name="q" value="<?= e($q) ?>" placeholder="جست‌وجوی فاکتور، میز، مشتری، مهمان یا اتاق"></label>
        </form>
        <button class="financial-filter-trigger" type="button" data-financial-filter-open="invoiceFilterLayer" aria-controls="invoiceFilterLayer" aria-haspopup="dialog"><span>فیلترها</span><?php if($activeAdvancedFilters): ?><b><?= e(fa_digits($activeAdvancedFilters)) ?></b><?php endif; ?></button>
        <?php if($activeFilterLabels): ?><div class="financial-filter-state" aria-label="فیلترهای فعال"><div class="financial-filter-chips"><?php foreach($activeFilterLabels as $label): ?><span class="financial-filter-chip"><?= e($label) ?></span><?php endforeach; ?></div><a class="panel-clear-filter" href="invoices.php<?= $q!==''?'?q='.e(rawurlencode($q)):'' ?>">پاک‌کردن</a></div><?php endif; ?>
      </div>

      <div class="invoice-result-list financial-list" aria-label="فهرست فاکتورها">
        <?php $lastBusinessDate=null;$dayRows=[];foreach($rows as $row){$dayKey=(string)$row['business_date'];$dayRows[$dayKey][]=$row;} ?>
        <?php foreach($dayRows as $dayKey=>$dayItems): ?><section class="invoice-day-group"><header><strong><?= e(format_jalali_date($dayKey,false)) ?></strong></header><div class="invoice-day-list">
          <?php foreach($dayItems as $row): $party=$row['subscriber_name']?:($row['accommodation_guest']?trim((string)$row['accommodation_guest'].($row['accommodation_room']?' · '.(string)$row['accommodation_room']:'')):'');$rowTime=strtotime((string)$row['settled_at']);$subject=fa_digits((string)$row['table_name_snapshot']).($party!==''?' · '.$party:'');$documentLabel=financial_document_human_label((string)$row['invoice_number']); ?>
          <a class="invoice-transaction-row financial-row" href="<?= e($detailUrl((int)$row['id'])) ?>" aria-label="بازکردن <?= e($documentLabel) ?>">
            <div class="invoice-transaction-main financial-row-main"><strong><?= e($documentLabel) ?></strong><span><?= e($subject) ?></span><small><?= e(settlement_destination_label((string)$row['destination'])) ?><?= $rowTime?' · '.e(fa_digits(date('H:i',$rowTime))):'' ?></small></div>
            <div class="invoice-transaction-amount financial-row-amount"><strong><?= $row['status']==='reversal'?'− ':'' ?><?= e(toman((int)$row['total'])) ?></strong><?php if((string)$row['status']!=='completed'): ?><span class="badge badge-<?= e((string)$row['status']) ?>"><?= e(settlement_status_label((string)$row['status'])) ?></span><?php endif; ?></div>
            <span class="invoice-transaction-chevron financial-row-chevron" aria-hidden="true"><?= ui_icon('chevron-left') ?></span>
          </a>
          <?php endforeach; ?>
        </div></section><?php endforeach; ?>
        <?php if(!$rows): ?><div class="financial-empty-state"><strong>فاکتوری پیدا نشد</strong><span><?= $contextParams?'فیلترها یا عبارت جست‌وجو را تغییر بده.':'پس از ثبت اولین تسویه، فاکتورها اینجا نمایش داده می‌شوند.' ?></span><?php if($contextParams): ?><a class="document-open-link" href="invoices.php">پاک‌کردن فیلترها</a><?php endif; ?></div><?php endif; ?>
      </div>
    </section>

    <div class="financial-filter-layer hidden" id="invoiceFilterLayer" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="invoiceFilterTitle" data-financial-filter-layer>
      <button class="financial-filter-backdrop" type="button" data-dialog-backdrop aria-label="بستن فیلترها"></button>
      <form method="get" class="financial-filter-sheet" data-financial-filter-sheet data-overlay-context="embedded">
        <?php if($q!==''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
        <div class="financial-filter-sheet-head" data-financial-filter-handle><span class="financial-sheet-grip" aria-hidden="true"></span><div><small>فاکتورها</small><h2 id="invoiceFilterTitle">فیلترها</h2></div><button class="panel-icon-action" type="button" data-dialog-close aria-label="بستن فیلترها"><?= ui_icon('close') ?></button></div>
        <div class="financial-filter-sheet-body">
          <label class="form-group"><span>سال مالی</span><select class="form-control" name="period" data-choice-mode="adaptive"><option value="0">همه</option><?php foreach($periods as $period): ?><option value="<?= (int)$period['id'] ?>" <?= $periodId===(int)$period['id']?'selected':'' ?>><?= e((string)$period['title']) ?><?= $period['status']==='open'?' · باز':'' ?></option><?php endforeach; ?></select></label>
          <label class="form-group"><span>مقصد</span><select class="form-control" name="destination" data-choice-mode="compact"><option value="">همه</option><?php foreach(settlement_destinations() as $key=>$label): ?><option value="<?= e($key) ?>" <?= $destination===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
          <label class="form-group"><span>وضعیت</span><select class="form-control" name="status" data-choice-mode="compact"><option value="">همه</option><option value="completed" <?= $status==='completed'?'selected':'' ?>>ثبت‌شده</option><option value="voided" <?= $status==='voided'?'selected':'' ?>>برگشت‌خورده</option><option value="reversal" <?= $status==='reversal'?'selected':'' ?>>سند برگشت</option></select></label>
          <label class="form-group"><span>از تاریخ</span><div class="jalali-date-control"><input class="form-control" id="invoiceFromDateJ" name="from" data-jalali-date inputmode="none" value="<?= e($fromInput) ?>" placeholder="انتخاب تاریخ"><button class="jalali-date-button" type="button" data-open-jalali="invoiceFromDateJ" aria-label="انتخاب تاریخ شروع از تقویم"><?= ui_icon('calendar') ?></button></div></label>
          <label class="form-group"><span>تا تاریخ</span><div class="jalali-date-control"><input class="form-control" id="invoiceToDateJ" name="to" data-jalali-date inputmode="none" value="<?= e($toInput) ?>" placeholder="انتخاب تاریخ"><button class="jalali-date-button" type="button" data-open-jalali="invoiceToDateJ" aria-label="انتخاب تاریخ پایان از تقویم"><?= ui_icon('calendar') ?></button></div></label>
        </div>
        <div class="financial-filter-sheet-actions"><button class="btn btn-primary" type="submit">اعمال فیلترها</button><?php if($activeAdvancedFilters): ?><a class="btn btn-light" href="invoices.php<?= $q!==''?'?q='.e(rawurlencode($q)):'' ?>">پاک‌کردن</a><?php endif; ?></div>
      </form>
    </div>
    <?php if($totalPages > 1): ?>
      <nav class="panel-pagination financial-pagination" aria-label="صفحه‌بندی فاکتورها">
        <a class="btn btn-light" href="<?= e($urlForPage(max(1,$page-1))) ?>" <?= $page<=1?'aria-disabled="true" tabindex="-1"':'' ?>>قبلی</a>
        <span>صفحه <?= e(fa_digits($page)) ?> از <?= e(fa_digits($totalPages)) ?></span>
        <a class="btn btn-light" href="<?= e($urlForPage(min($totalPages,$page+1))) ?>" <?= $page>=$totalPages?'aria-disabled="true" tabindex="-1"':'' ?>>بعدی</a>
      </nav>
    <?php endif; ?>
    </div>
    <?php
    panel_footer('<script defer src="' . e(asset('assets/js/financial-ui.js')) . '"></script>');
}
