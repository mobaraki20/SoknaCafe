<?php
declare(strict_types=1);

/**
 * Enrich a subscriber ledger page with settlement metadata without making the
 * route depend on settlement joins.  The ledger remains readable even when an
 * older/partially-upgraded installation has an unexpected settlement schema.
 */
function subscriber_ledger_enrich_settlements(PDO $pdo, array $ledger): array
{
    if (!$ledger) return [];

    $lookupIds = [];
    foreach ($ledger as &$entry) {
        foreach (['settlement_id','invoice_number','settled_at','destination','settlement_status','reversal_settlement_id','reversal_invoice_number'] as $key) {
            if (!array_key_exists($key, $entry)) $entry[$key] = null;
        }
        $targetId = (string)($entry['entry_type'] ?? '') === 'invoice_reversal'
            ? (int)($entry['related_entry_id'] ?? 0)
            : (int)($entry['id'] ?? 0);
        if ($targetId > 0) $lookupIds[$targetId] = true;
    }
    unset($entry);
    if (!$lookupIds) return $ledger;

    try {
        $ids = array_keys($lookupIds);
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT sr.id settlement_id,sr.subscriber_ledger_entry_id,sr.invoice_number,sr.settled_at,sr.destination,sr.status settlement_status,sr.table_name_snapshot,rev.id reversal_settlement_id,rev.invoice_number reversal_invoice_number
                FROM settlement_records sr
                LEFT JOIN settlement_records rev ON rev.reverses_settlement_id=sr.id AND rev.status='reversal'
                WHERE sr.subscriber_ledger_entry_id IN ($marks)
                  AND sr.status='completed'
                  AND sr.reverses_settlement_id IS NULL
                ORDER BY sr.id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($ids);
        $byLedger = [];
        foreach ($stmt->fetchAll() as $row) {
            $key = (int)($row['subscriber_ledger_entry_id'] ?? 0);
            // Keep the first original settlement deterministically if historical
            // bad data contains more than one completed record for one ledger id.
            if ($key > 0 && !isset($byLedger[$key])) $byLedger[$key] = $row;
        }
        foreach ($ledger as &$entry) {
            $targetId = (string)($entry['entry_type'] ?? '') === 'invoice_reversal'
                ? (int)($entry['related_entry_id'] ?? 0)
                : (int)($entry['id'] ?? 0);
            $meta = $byLedger[$targetId] ?? null;
            if (!$meta) continue;
            foreach (['settlement_id','invoice_number','settled_at','destination','settlement_status','reversal_settlement_id','reversal_invoice_number'] as $key) {
                $entry[$key] = $meta[$key] ?? null;
            }
            if (!empty($meta['table_name_snapshot'])) $entry['table_name'] = $meta['table_name_snapshot'];
        }
        unset($entry);
    } catch (Throwable $e) {
        // Settlement metadata is secondary.  A failure here must never turn the
        // whole subscriber profile into HTTP 500; ledger/snapshot data remains usable.
        error_log('subscriber settlement enrichment: ' . $e->getMessage());
    }
    return $ledger;
}

function render_subscribers_page(): void
{
    $user = current_user();
    $isAdmin = is_admin();
    if (!$isAdmin && !user_has_capability('cashier_accounts', $user)) {
        http_response_code(403);
        exit('دسترسی کافی نیست.');
    }
    require_once __DIR__ . '/panel_layout.php';
    $pdo = db();
    $userId = (int)$user['id'];
    $basePath = 'subscribers.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf($_POST['csrf_token'] ?? null);
        $action = (string)($_POST['action'] ?? '');
        $redirectId = (int)($_POST['subscriber_id'] ?? $_POST['id'] ?? 0);
        $ledgerType = (string)($_POST['ledger_type'] ?? '');
        $ledgerPage = max(1, (int)($_POST['ledger_page'] ?? 1));
        $viewSuffix = $redirectId > 0 ? '?view=' . $redirectId . ($ledgerType !== '' ? '&ledger_type=' . rawurlencode($ledgerType) : '') . ($ledgerPage > 1 ? '&ledger_page=' . $ledgerPage : '') : '';
        $redirect = $basePath . $viewSuffix;
        try {
            if ($action === 'save') {
                if (!$isAdmin) throw new RuntimeException('فقط مدیر می‌تواند مشتری بسازد یا ویرایش کند.');
                $id = (int)($_POST['id'] ?? 0);
                $name = text_substr(trim((string)($_POST['name'] ?? '')), 0, 160);
                $mobile = text_substr(trim((string)($_POST['mobile'] ?? '')), 0, 30);
                $normalized = subscriber_mobile_normalize($mobile);
                $active = isset($_POST['active']) ? 1 : 0;
                $errors = [];
                if ($name === '') $errors['name'] = 'نام یا عنوان را وارد کن.';
                if (text_length($normalized) < 10) $errors['mobile'] = 'شماره موبایل معتبر نیست.';
                if ($errors) throw new InvalidArgumentException('اطلاعات مشخص‌شده را اصلاح کن.');
                if ($id > 0) {
                    $pdo->prepare('UPDATE subscribers SET name=?,mobile=?,mobile_normalized=?,active=?,updated_by_user_id=? WHERE id=?')->execute([$name,$mobile,$normalized,$active,$userId,$id]);
                    audit_log_write('subscriber.updated','subscriber',$id,['active'=>$active],$userId);
                    $redirectId=$id;
                } else {
                    $pdo->prepare('INSERT INTO subscribers(name,mobile,mobile_normalized,active,created_by_user_id,updated_by_user_id) VALUES(?,?,?,?,?,?)')->execute([$name,$mobile,$normalized,$active,$userId,$userId]);
                    $redirectId=(int)$pdo->lastInsertId();
                    audit_log_write('subscriber.created','subscriber',$redirectId,[],$userId);
                }
                flash('success','اطلاعات مشتری ذخیره شد.');
                $redirect=$basePath.'?view='.$redirectId;
            } elseif ($action === 'payment') {
                $subscriberId=(int)($_POST['subscriber_id']??0);
                $amount=parse_toman_amount_text((string)($_POST['amount']??''));
                $reference=text_substr(trim((string)($_POST['reference']??'')),0,120);
                $requestToken=trim((string)($_POST['request_token']??''));
                if($subscriberId<1||$amount===null||$amount<1)throw new RuntimeException('مشتری و مبلغ معتبر را انتخاب کن.');
                if(!preg_match('/^[a-f0-9]{32}$/',$requestToken))throw new RuntimeException('فرم پرداخت منقضی شده است؛ صفحه را تازه کنید.');
                $idempotencyKey='subscriber:payment:'.$subscriberId.':'.$requestToken;
                $pdo->beginTransaction();
                $entry=subscriber_insert_ledger_locked($pdo,$subscriberId,'payment',-$amount,$userId,null,null,$reference?:null,null,null,null,$idempotencyKey);
                if(empty($entry['idempotent']))audit_log_write('subscriber.payment','subscriber',$subscriberId,['amount'=>$amount,'balance'=>(int)$entry['balance_after'],'ledger_entry_id'=>(int)$entry['id']],$userId);
                $pdo->commit();
                flash('success','پرداخت ثبت شد؛ مانده جدید '.toman((int)$entry['balance_after']).' است.');
                $redirect=$basePath.'?view='.$subscriberId;
            } elseif ($action === 'reverse_payment') {
                if(!$isAdmin)throw new RuntimeException('فقط مدیر می‌تواند پرداخت را برگشت بزند.');
                $entryId=(int)($_POST['entry_id']??0);
                $reason=text_substr(trim((string)($_POST['reason']??'')),0,300);
                if($entryId<1||$reason==='')throw new RuntimeException('پرداخت و دلیل برگشت را مشخص کن.');
                $pdo->beginTransaction();
                $entry=subscriber_reverse_entry_locked($pdo,$entryId,$reason,$userId);
                audit_log_write('subscriber.payment_reversed','subscriber',(int)$entry['subscriber_id'],['entry_id'=>$entryId,'reason'=>$reason],$userId);
                $pdo->commit();
                flash('success','پرداخت با سند برگشتی خنثی شد.');
                $redirect=$basePath.'?view='.(int)$entry['subscriber_id'];
            } else {
                throw new RuntimeException('عملیات مشتری شناخته نشد.');
            }
        } catch (PDOException $e) {
            if($pdo->inTransaction())$pdo->rollBack();
            flash('error',(string)$e->getCode()==='23000'?'این شماره موبایل یا سند قبلاً ثبت شده است.':'اطلاعات ذخیره نشد.');
            if($action==='save'){
                form_state_store('subscriber_form',$_POST);
                $redirect=$basePath.(((int)($_POST['id']??0))>0?'?edit='.(int)$_POST['id']:'?new=1');
            }
        } catch (InvalidArgumentException $e) {
            if($pdo->inTransaction())$pdo->rollBack();
            $errors=[];
            $name=trim((string)($_POST['name']??''));
            $mobile=subscriber_mobile_normalize((string)($_POST['mobile']??''));
            if($name==='')$errors['name']='نام یا عنوان را وارد کن.';
            if(text_length($mobile)<10)$errors['mobile']='شماره موبایل معتبر نیست.';
            form_state_store('subscriber_form',$_POST,$errors);
            flash('error',$e->getMessage());
            $redirect=$basePath.(((int)($_POST['id']??0))>0?'?edit='.(int)$_POST['id']:'?new=1');
        } catch(Throwable $e) {
            if($pdo->inTransaction())$pdo->rollBack();
            error_log('subscriber action: '.$e->getMessage());
            flash('error',safe_business_error_message($e,'عملیات حساب مشتری انجام نشد. دوباره تلاش کن.'));
            if($action==='payment')form_state_store('subscriber_payment',$_POST);
        }
        redirect($redirect);
    }

    $viewId=(int)($_GET['view']??0);
    if($viewId>0){
        $st=$pdo->prepare('SELECT s.*,COALESCE((SELECT l.balance_after FROM subscriber_ledger l WHERE l.subscriber_id=s.id ORDER BY l.id DESC LIMIT 1),0) balance,(SELECT MAX(l2.created_at) FROM subscriber_ledger l2 WHERE l2.subscriber_id=s.id) last_activity FROM subscribers s WHERE s.id=?');
        $st->execute([$viewId]);
        $view=$st->fetch()?:null;
        if(!$view){flash('error','مشتری پیدا نشد.');redirect($basePath);}

        $allowedLedgerTypes=['invoice','payment','invoice_reversal','payment_reversal'];
        $ledgerType=(string)($_GET['ledger_type']??'');
        if(!in_array($ledgerType,$allowedLedgerTypes,true))$ledgerType='';
        $ledgerPage=max(1,(int)($_GET['ledger_page']??1));
        $ledgerPerPage=30;
        $ledgerWhere='l.subscriber_id=?';
        $ledgerParams=[$viewId];
        if($ledgerType!==''){$ledgerWhere.=' AND l.entry_type=?';$ledgerParams[]=$ledgerType;}
        $count=$pdo->prepare('SELECT COUNT(*) FROM subscriber_ledger l WHERE '.$ledgerWhere);
        $count->execute($ledgerParams);
        $ledgerTotal=(int)$count->fetchColumn();
        $ledgerPages=max(1,(int)ceil($ledgerTotal/$ledgerPerPage));
        if($ledgerPage>$ledgerPages)$ledgerPage=$ledgerPages;
        $ledgerOffset=($ledgerPage-1)*$ledgerPerPage;
        $sql="SELECT l.*,u.display_name actor_name,ts.table_id,t.name table_name,rev_l.id reversal_id FROM subscriber_ledger l LEFT JOIN users u ON u.id=l.actor_user_id LEFT JOIN table_sessions ts ON ts.id=l.table_session_id LEFT JOIN cafe_tables t ON t.id=ts.table_id LEFT JOIN subscriber_ledger rev_l ON rev_l.related_entry_id=l.id WHERE $ledgerWhere ORDER BY l.created_at DESC,l.id DESC LIMIT $ledgerPerPage OFFSET $ledgerOffset";
        try {
            $st=$pdo->prepare($sql);$st->execute($ledgerParams);$ledger=$st->fetchAll();
        } catch (Throwable $ledgerJoinError) {
            // Actor/table metadata is secondary too.  Keep the financial ledger route alive.
            error_log('subscriber ledger metadata: '.$ledgerJoinError->getMessage());
            $fallback="SELECT l.*,NULL actor_name,NULL table_id,NULL table_name,NULL reversal_id FROM subscriber_ledger l WHERE $ledgerWhere ORDER BY l.created_at DESC,l.id DESC LIMIT $ledgerPerPage OFFSET $ledgerOffset";
            $st=$pdo->prepare($fallback);$st->execute($ledgerParams);$ledger=$st->fetchAll();
        }
        $ledger=subscriber_ledger_enrich_settlements($pdo,$ledger);
        $paymentState=form_state_pull('subscriber_payment');$paymentRequestToken=(string)form_old($paymentState,'request_token',bin2hex(random_bytes(16)));if(!preg_match('/^[a-f0-9]{32}$/',$paymentRequestToken))$paymentRequestToken=bin2hex(random_bytes(16));
        $ledgerUrl=static function(int $target,string $type) use($viewId):string{$p=['view'=>$viewId];if($type!=='')$p['ledger_type']=$type;if($target>1)$p['ledger_page']=$target;return 'subscribers.php?'.http_build_query($p);};
        panel_header('پرونده مشتری','subscribers');?>
        <div class="financial-workspace subscriber-profile-workspace panel-page-flow" data-visual-quality-page="subscriber_profile">
        <div class="panel-detail-nav"><a class="panel-back-link" href="subscribers.php"><?= ui_icon('chevron-right') ?> بازگشت به مشتریان</a></div>
        <div class="subscriber-profile-stack panel-page-flow">
        <section class="card subscriber-profile-head financial-record-hero"><div class="subscriber-profile-top"><div class="subscriber-profile-identity"><div class="subscriber-profile-name-row"><h2><?= e($view['name']) ?></h2><?php if((int)$view['active']!==1): ?><span class="panel-status-badge is-muted">غیرفعال</span><?php endif; ?></div><p dir="ltr"><?= e(fa_digits((string)$view['mobile'])) ?></p></div><?php if($isAdmin): ?><div class="panel-utility-actions"><a class="panel-icon-action" href="?edit=<?= (int)$view['id'] ?>" aria-label="ویرایش اطلاعات مشتری" title="ویرایش اطلاعات"><?= ui_icon('edit') ?></a></div><?php endif; ?></div><div class="subscriber-balance-block"><span>مانده حساب</span><strong><?= e(toman((int)$view['balance'])) ?></strong></div><div class="subscriber-profile-foot"><span><?= $view['last_activity']?'آخرین فعالیت: '.e(format_jalali_human_datetime((string)$view['last_activity'])):'هنوز گردش حسابی ثبت نشده است.' ?></span></div></section>
        <?php if((int)$view['balance']>0): ?><details class="card panel-disclosure subscriber-payment-card" id="subscriberPaymentPanel" <?= !empty($paymentState['values'])?'open':'' ?>><summary><span><strong>ثبت پرداخت</strong><small>پرداخت کامل یا جزئی از مانده حساب</small></span><?= ui_icon('chevron-down') ?></summary><div class="card-body"><form method="post" class="subscriber-payment-form"><?= csrf_field() ?><input type="hidden" name="subscriber_id" value="<?= (int)$view['id'] ?>"><input type="hidden" name="request_token" value="<?= e($paymentRequestToken) ?>"><label class="form-group"><span>مبلغ پرداخت</span><input class="form-control" id="subscriberPaymentAmount" type="text" inputmode="numeric" enterkeyhint="next" name="amount" value="<?= e(money_input_display_value((string)form_old($paymentState,'amount',''))) ?>" data-money-input required><small class="muted">مبلغ به تومان</small></label><button class="btn btn-sm btn-light subscriber-pay-full" type="button" data-fill-money-target="subscriberPaymentAmount" data-fill-money-value="<?= (int)$view['balance'] ?>">پرداخت کل مانده · <?= e(toman((int)$view['balance'])) ?></button><label class="form-group"><span>مرجع، اختیاری</span><input class="form-control" name="reference" enterkeyhint="done" maxlength="120" value="<?= e((string)form_old($paymentState,'reference','')) ?>" placeholder="مثلاً شماره رسید"></label><button class="btn btn-primary subscriber-payment-submit" name="action" value="payment">ثبت پرداخت</button></form></div></details><?php endif; ?>
        <section class="card subscriber-ledger-card financial-record-section"><div class="card-head subscriber-ledger-head"><div><h2>گردش حساب</h2><small><?= e(fa_digits($ledgerTotal)) ?> سند</small></div><form method="get" class="subscriber-ledger-filter"><input type="hidden" name="view" value="<?= (int)$viewId ?>"><label><span class="sr-only">نوع سند</span><select class="form-control" name="ledger_type" data-choice-mode="compact" data-auto-submit><option value="">همه اسناد</option><?php foreach($allowedLedgerTypes as $type): ?><option value="<?= e($type) ?>" <?= $ledgerType===$type?'selected':'' ?>><?= e(subscriber_entry_type_label($type)) ?></option><?php endforeach; ?></select></label></form></div><div class="subscriber-timeline">
        <?php if(!$ledger): ?><div class="empty-state">گردش حسابی با این فیلتر ثبت نشده است.</div><?php endif; ?>
        <?php foreach($ledger as $entry):
          $snapshot=$entry['invoice_snapshot_json']?json_decode((string)$entry['invoice_snapshot_json'],true):null;
          $entryType=(string)$entry['entry_type'];
          $isInvoice=$entryType==='invoice';
          $isInvoiceReversal=$entryType==='invoice_reversal';
          $isPayment=$entryType==='payment';
          $isReversedInvoice=$isInvoice && !empty($entry['reversal_settlement_id']);
          $invoiceNumber=(string)($entry['invoice_number']?:($snapshot['number']??''));
          $invoiceLabel=$invoiceNumber!==''?financial_document_human_label($invoiceNumber):'فاکتور';
          $title=$isInvoice?$invoiceLabel:($isInvoiceReversal?'برگشت '.$invoiceLabel:subscriber_entry_type_label($entryType));
          $presentedItems=is_array($snapshot)?financial_receipt_presented_items((array)($snapshot['items']??[])):[];
        ?>
          <article class="subscriber-ledger-entry financial-history-row <?= ($isInvoice||$isInvoiceReversal)?'is-invoice':'is-payment' ?>">
            <?php $hasLedgerDetail=($isInvoice||$isInvoiceReversal)&&($presentedItems||!empty($entry['settlement_id'])||!empty($entry['reason'])); ?>
            <?php if($hasLedgerDetail): ?><details class="subscriber-ledger-disclosure">
              <summary>
                <div class="subscriber-ledger-entry-main"><h3><?= e($title) ?></h3><div class="subscriber-ledger-entry-context"><?php if($entry['table_name']): ?><span><?= e(fa_digits((string)$entry['table_name'])) ?></span><?php endif; ?><span><?= e(format_jalali_human_datetime((string)$entry['created_at'])) ?></span><?php if($isReversedInvoice): ?><span class="panel-status-badge is-muted">برگشت‌خورده</span><?php endif; ?></div></div>
                <div class="subscriber-ledger-entry-amount"><strong class="<?= (int)$entry['amount_delta']<0?'text-success':'' ?>"><?= e(toman((int)$entry['amount_delta'])) ?></strong><small>مانده بعد: <?= e(toman((int)$entry['balance_after'])) ?></small></div>
                <span class="subscriber-ledger-chevron" aria-hidden="true"><?= ui_icon('chevron-down') ?></span>
              </summary>
              <div class="subscriber-ledger-detail">
                <?php if(($isInvoiceReversal||$entryType==='payment_reversal') && $entry['reason']): ?><div class="subscriber-ledger-reason"><span>دلیل برگشت</span><strong><?= e((string)$entry['reason']) ?></strong><?php if($isInvoiceReversal&&!empty($entry['reversal_invoice_number'])): ?><small><?= e(financial_document_human_label((string)$entry['reversal_invoice_number'])) ?></small><?php endif; ?></div><?php endif; ?>
                <?php if($presentedItems): ?><div class="financial-receipt-preview" aria-label="اقلام <?= e($invoiceLabel) ?>"><?php foreach($presentedItems as $item): ?><div class="financial-receipt-row"><div><strong><?= e((string)$item['name']) ?></strong><?php if($item['note']!==''): ?><small><?= e((string)$item['note']) ?></small><?php endif; ?><small class="invoice-line-math"><bdi dir="ltr"><?= e(fa_digits((int)$item['quantity'])) ?> × <?= e(toman_number((int)$item['unit_price'])) ?></bdi></small></div><strong><?= e(toman_number((int)$item['line_total'])) ?></strong></div><?php endforeach; ?></div><?php endif; ?>
                <?php if($entry['settlement_id']): ?><a class="document-open-link subscriber-ledger-open-document" href="invoices.php?id=<?= (int)$entry['settlement_id'] ?>#invoiceDetail">مشاهده <?= e($invoiceLabel) ?><?= ui_icon('chevron-left') ?></a><?php endif; ?>
              </div>
            </details><?php else: ?>
              <div class="subscriber-ledger-static">
                <div class="subscriber-ledger-entry-main"><h3><?= e($title) ?></h3><div class="subscriber-ledger-entry-context"><?php if($entry['table_name']): ?><span><?= e(fa_digits((string)$entry['table_name'])) ?></span><?php endif; ?><span><?= e(format_jalali_human_datetime((string)$entry['created_at'])) ?></span></div></div>
                <div class="subscriber-ledger-entry-amount"><strong class="<?= (int)$entry['amount_delta']<0?'text-success':'' ?>"><?= e(toman((int)$entry['amount_delta'])) ?></strong><small>مانده بعد: <?= e(toman((int)$entry['balance_after'])) ?></small></div>
              </div>
            <?php endif; ?>
            <?php if($isAdmin&&$isPayment&&!$entry['reversal_id']): ?><footer class="subscriber-ledger-entry-actions"><button type="button" class="btn btn-sm btn-danger" data-reverse-payment data-entry-id="<?= (int)$entry['id'] ?>" data-entry-label="<?= e(format_jalali_human_datetime((string)$entry['created_at'])) ?>">برگشت پرداخت</button></footer><?php endif; ?>
          </article>
        <?php endforeach; ?></div></section>
        </div>
        <?php if($ledgerPages>1): ?><nav class="panel-pagination financial-pagination" aria-label="صفحه‌بندی گردش حساب"><a class="btn btn-light" href="<?= e($ledgerUrl(max(1,$ledgerPage-1),$ledgerType)) ?>" <?= $ledgerPage<=1?'aria-disabled="true" tabindex="-1"':'' ?>>قبلی</a><span>صفحه <?= e(fa_digits($ledgerPage)) ?> از <?= e(fa_digits($ledgerPages)) ?></span><a class="btn btn-light" href="<?= e($ledgerUrl(min($ledgerPages,$ledgerPage+1),$ledgerType)) ?>" <?= $ledgerPage>=$ledgerPages?'aria-disabled="true" tabindex="-1"':'' ?>>بعدی</a></nav><?php endif; ?>
        </div>
        <?php if($isAdmin): ?><dialog class="panel-action-dialog" id="paymentReversalDialog"><form method="post" id="paymentReversalForm"><?= csrf_field() ?><input type="hidden" name="subscriber_id" value="<?= (int)$view['id'] ?>"><input type="hidden" name="entry_id" id="paymentReversalEntry"><input type="hidden" name="ledger_type" value="<?= e($ledgerType) ?>"><input type="hidden" name="ledger_page" value="<?= (int)$ledgerPage ?>"><div class="panel-action-dialog-head"><div><small>عملیات مالی</small><h3>برگشت پرداخت</h3><p id="paymentReversalLabel"></p></div></div><label class="form-group"><span>دلیل برگشت</span><textarea class="form-control" name="reason" maxlength="300" rows="3" required></textarea></label><div class="actions"><button type="button" class="btn btn-light" data-close-payment-reversal>انصراف</button><button class="btn btn-danger" name="action" value="reverse_payment">ثبت سند برگشتی</button></div></form></dialog><script>(()=>{const d=document.getElementById('paymentReversalDialog'),f=document.getElementById('paymentReversalForm'),id=document.getElementById('paymentReversalEntry'),label=document.getElementById('paymentReversalLabel');document.querySelectorAll('[data-reverse-payment]').forEach(b=>b.addEventListener('click',()=>{id.value=b.dataset.entryId||'';label.textContent='پرداخت '+(b.dataset.entryLabel||'انتخاب‌شده')+' با سند برگشتی خنثی می‌شود.';f.querySelector('textarea').value='';d.showModal();f.querySelector('textarea').focus();}));d.querySelector('[data-close-payment-reversal]')?.addEventListener('click',()=>d.close());d.addEventListener('cancel',()=>f.querySelector('textarea').value='');})();</script><?php endif; ?>
        <?php panel_footer();return;
    }

    $edit=null;$editId=$isAdmin?(int)($_GET['edit']??0):0;if($editId>0){$st=$pdo->prepare('SELECT * FROM subscribers WHERE id=?');$st->execute([$editId]);$edit=$st->fetch()?:null;}
    $formState=form_state_pull('subscriber_form');if($formState['values'])$edit=array_merge($edit??[],$formState['values']);
    $debtOnly=($_GET['debt']??'')==='1';
    $statusFilter=(string)($_GET['status']??'all');if(!in_array($statusFilter,['all','active','inactive'],true))$statusFilter='all';
    $q=text_substr(trim((string)($_GET['q']??'')),0,100);
    $page=max(1,(int)($_GET['page']??1));$perPage=30;
    $where=[];$params=[];
    if($debtOnly)$where[]="COALESCE((SELECT l3.balance_after FROM subscriber_ledger l3 WHERE l3.subscriber_id=s.id ORDER BY l3.id DESC LIMIT 1),0)>0";
    if($statusFilter==='active')$where[]='s.active=1';elseif($statusFilter==='inactive')$where[]='s.active=0';
    if($q!==''){
        $normalized=subscriber_mobile_normalize($q);
        $where[]="(REPLACE(REPLACE(s.name,'ي','ی'),'ك','ک') LIKE ? OR s.mobile LIKE ? OR s.mobile_normalized LIKE ?)";
        $params[]='%'.normalize_persian_search($q).'%';$params[]='%'.$q.'%';$params[]='%'.($normalized!==''?$normalized:$q).'%';
    }
    $whereSql=$where?' WHERE '.implode(' AND ',$where):'';
    $count=$pdo->prepare('SELECT COUNT(*) FROM subscribers s'.$whereSql);$count->execute($params);$total=(int)$count->fetchColumn();$pages=max(1,(int)ceil($total/$perPage));if($page>$pages)$page=$pages;$offset=($page-1)*$perPage;
    $sql="SELECT s.*,COALESCE((SELECT l.balance_after FROM subscriber_ledger l WHERE l.subscriber_id=s.id ORDER BY l.id DESC LIMIT 1),0) balance,(SELECT MAX(l2.created_at) FROM subscriber_ledger l2 WHERE l2.subscriber_id=s.id) last_activity FROM subscribers s$whereSql ORDER BY balance DESC,s.active DESC,s.name,s.id LIMIT $perPage OFFSET $offset";
    $st=$pdo->prepare($sql);$st->execute($params);$rows=$st->fetchAll();
    $sumSql="SELECT COALESCE(SUM(GREATEST(COALESCE((SELECT l4.balance_after FROM subscriber_ledger l4 WHERE l4.subscriber_id=s.id ORDER BY l4.id DESC LIMIT 1),0),0)),0) FROM subscribers s";$totalBalance=(int)$pdo->query($sumSql)->fetchColumn();
    $listParams=[];if($q!=='')$listParams['q']=$q;if($debtOnly)$listParams['debt']='1';if($statusFilter!=='all')$listParams['status']=$statusFilter;
    $pageUrl=static function(int $target)use($listParams):string{$p=$listParams;if($target>1)$p['page']=$target;return 'subscribers.php'.($p?'?'.http_build_query($p):'');};
    panel_header('مشتریان','subscribers');?>
    <div class="financial-workspace subscriber-directory-workspace panel-page-flow" data-visual-quality-page="subscriber_directory">
    <?php if($isAdmin&&(isset($_GET['new'])||$edit)): ?><section class="card subscriber-editor-card"><div class="card-head"><div><h2><?= $editId?'ویرایش مشتری':'مشتری جدید' ?></h2><small>برای این مشتری، بدهی فاکتورها و پرداخت‌ها در همین حساب ثبت می‌شود.</small></div></div><div class="card-body"><?php if($formState['errors']): ?><div class="alert alert-error">اطلاعات مشخص‌شده را اصلاح کن.</div><?php endif; ?><form method="post" class="form-grid"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($edit['id']??0) ?>"><div class="form-group"><label>نام یا عنوان</label><input class="form-control <?= form_field_error($formState,'name')?'is-invalid':'' ?>" name="name" value="<?= e($edit['name']??'') ?>" maxlength="160" required><?php if(form_field_error($formState,'name')): ?><small class="field-error"><?= e(form_field_error($formState,'name')) ?></small><?php endif; ?></div><div class="form-group"><label>شماره موبایل</label><input class="form-control ltr-input <?= form_field_error($formState,'mobile')?'is-invalid':'' ?>" dir="ltr" type="tel" inputmode="tel" autocomplete="tel" name="mobile" value="<?= e($edit['mobile']??'') ?>" maxlength="30" required><?php if(form_field_error($formState,'mobile')): ?><small class="field-error"><?= e(form_field_error($formState,'mobile')) ?></small><?php endif; ?></div><div class="form-group full"><label class="check-line"><input type="checkbox" name="active" <?= !isset($edit['active'])||(int)$edit['active']===1||isset($edit['active'])&&$edit['active']==='on'?'checked':'' ?>><span>فعال باشد</span></label></div><div class="form-group full actions"><button class="btn btn-primary" name="action" value="save">ذخیره</button><a class="btn btn-light" href="subscribers.php">انصراف</a></div></form></div></section><?php endif; ?>
    <section class="card subscriber-directory-card financial-page-shell financial-index-surface" data-financial-index-shell="subscribers">
      <div class="financial-page-head">
        <div class="financial-page-summary"><span>مانده کل بدهکاران</span><strong><?= e(toman($totalBalance)) ?></strong><small><?= e(fa_digits($total)) ?> <?= ($q!==''||$debtOnly||$statusFilter!=='all')?'نتیجه':'مشتری' ?></small></div>
        <?php if($isAdmin): ?><a class="financial-head-action" href="?new=1"><?= ui_icon('add') ?><span>مشتری جدید</span></a><?php endif; ?>
      </div>
      <div class="financial-page-toolbar">
        <form method="get" class="subscriber-search-form financial-filter-form">
          <label class="financial-search-field"><span class="sr-only">جست‌وجوی مشتریان</span><input class="form-control" type="search" inputmode="search" enterkeyhint="search" autocomplete="off" name="q" value="<?= e($q) ?>" placeholder="جست‌وجوی نام یا موبایل"></label>
          <div class="subscriber-filter-row financial-filter-inline">
            <select class="form-control financial-compact-select" name="status" data-choice-mode="compact" data-auto-submit><option value="all" <?= $statusFilter==='all'?'selected':'' ?>>همه وضعیت‌ها</option><option value="active" <?= $statusFilter==='active'?'selected':'' ?>>فعال</option><option value="inactive" <?= $statusFilter==='inactive'?'selected':'' ?>>غیرفعال</option></select>
            <label class="financial-filter-toggle"><input type="checkbox" name="debt" value="1" <?= $debtOnly?'checked':'' ?> data-auto-submit><span>فقط بدهکار</span></label>
            <?php if($q!==''||$debtOnly||$statusFilter!=='all'): ?><a class="panel-clear-filter" href="subscribers.php">پاک‌کردن</a><?php endif; ?>
          </div>
        </form>
      </div>
      <div class="subscriber-card-list financial-list">
        <?php if(!$rows): ?><div class="financial-empty-state"><strong>مشتری پیدا نشد</strong><span><?= $q!==''||$debtOnly||$statusFilter!=='all'?'فیلترها یا عبارت جست‌وجو را تغییر بده.':'برای شروع، اولین مشتری را ثبت کن.' ?></span><?php if($q!==''||$debtOnly||$statusFilter!=='all'): ?><a class="document-open-link" href="subscribers.php">پاک‌کردن فیلترها</a><?php endif; ?></div><?php endif; ?>
        <?php foreach($rows as $row): ?><a class="subscriber-list-card financial-row" href="?view=<?= (int)$row['id'] ?>"><div class="subscriber-list-identity financial-row-main"><strong><?= e($row['name']) ?></strong><span dir="ltr"><?= e(fa_digits((string)$row['mobile'])) ?></span><small><?= $row['last_activity']?e(format_jalali_human_datetime((string)$row['last_activity'])):'بدون گردش' ?></small></div><div class="subscriber-list-balance financial-row-amount"><?php if((int)$row['balance']===0): ?><strong class="is-zero">بدون مانده</strong><?php else: ?><strong><?= e(toman((int)$row['balance'])) ?></strong><?php endif; ?><?php if((int)$row['active']!==1): ?><span class="panel-status-badge is-muted">غیرفعال</span><?php endif; ?></div><span class="subscriber-list-chevron"><?= ui_icon('chevron-left') ?></span></a><?php endforeach; ?>
      </div>
    </section>
    <?php if($pages>1): ?><nav class="panel-pagination financial-pagination" aria-label="صفحه‌بندی مشتریان"><a class="btn btn-light" href="<?= e($pageUrl(max(1,$page-1))) ?>" <?= $page<=1?'aria-disabled="true" tabindex="-1"':'' ?>>قبلی</a><span>صفحه <?= e(fa_digits($page)) ?> از <?= e(fa_digits($pages)) ?></span><a class="btn btn-light" href="<?= e($pageUrl(min($pages,$page+1))) ?>" <?= $page>=$pages?'aria-disabled="true" tabindex="-1"':'' ?>>بعدی</a></nav><?php endif; ?>
    </div>
    <?php panel_footer();
}
