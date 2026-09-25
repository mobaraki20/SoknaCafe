<?php
declare(strict_types=1);

function expense_categories(PDO $pdo,bool $activeOnly=true): array
{
    $sql='SELECT category_key,name,active,system_category,sort_order FROM expense_categories'.($activeOnly?' WHERE active=1':'').' ORDER BY sort_order,name,category_key';
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function expense_require_transaction(PDO $pdo): void
{
    if(!$pdo->inTransaction()) throw new RuntimeException('عملیات هزینه باید داخل تراکنش انجام شود.');
}

function expense_period_locked(PDO $pdo,int $periodId): array
{
    $stmt=$pdo->prepare('SELECT * FROM financial_periods WHERE id=? FOR UPDATE');$stmt->execute([$periodId]);
    $period=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$period) throw new RuntimeException('دوره مالی هزینه پیدا نشد.');
    return $period;
}

function expense_assert_period_accepts_locked(array $period,string $occurredAt,bool $allowClosedPeriod=false): void
{
    $day=substr($occurredAt,0,10);
    if($day<(string)$period['start_date'] || $day>(string)$period['end_date']) throw new RuntimeException('تاریخ هزینه با دوره مالی انتخاب‌شده هم‌خوان نیست.');
    if((string)$period['status']==='closed' && !$allowClosedPeriod) throw new RuntimeException('دوره مالی این هزینه بسته شده است؛ اصلاح عادی روی دوره بسته مجاز نیست.');
}

/** Caller owns the transaction. General cafe expenses only; inventory purchasing remains in Inventory/Supply. */
function expense_create_locked(
    PDO $pdo,
    string $categoryKey,
    int $amount,
    string $occurredAt,
    string $description,
    int $actorUserId,
    string $sourceRequestId,
    ?int $financialPeriodId=null,
    bool $allowClosedPeriod=false
): array {
    expense_require_transaction($pdo);
    $categoryKey=trim($categoryKey);
    $description=text_substr(trim($description),0,500);
    $sourceRequestId=text_substr(trim($sourceRequestId),0,96);
    if($amount<1)throw new RuntimeException('مبلغ هزینه باید بیشتر از صفر باشد.');
    if($sourceRequestId==='')throw new RuntimeException('شناسه یکتای هزینه مشخص نیست.');
    $ts=strtotime($occurredAt);
    if($ts===false||$ts>time()+300)throw new RuntimeException('زمان وقوع هزینه معتبر نیست.');
    $occurredAt=date('Y-m-d H:i:s',$ts);

    $dup=$pdo->prepare('SELECT * FROM expenses WHERE source_request_id=? LIMIT 1 FOR UPDATE');
    $dup->execute([$sourceRequestId]);$existing=$dup->fetch();
    if($existing){
        if((string)$existing['category_key']!==$categoryKey||(int)$existing['amount']!==$amount||(string)$existing['occurred_at']!==$occurredAt){
            throw new RuntimeException('شناسه این هزینه قبلاً برای اطلاعات دیگری استفاده شده است.');
        }
        return ['id'=>(int)$existing['id'],'financial_period_id'=>(int)$existing['financial_period_id'],'idempotent'=>true];
    }

    $cat=$pdo->prepare('SELECT category_key FROM expense_categories WHERE category_key=? AND active=1 FOR UPDATE');
    $cat->execute([$categoryKey]);
    if(!$cat->fetchColumn())throw new RuntimeException('دسته هزینه معتبر نیست.');
    if(($financialPeriodId??0)<1){
        $period=financial_period_for_date_locked($pdo,substr($occurredAt,0,10),$actorUserId);
        $financialPeriodId=(int)$period['id'];
    }else $period=expense_period_locked($pdo,(int)$financialPeriodId);
    expense_assert_period_accepts_locked($period,$occurredAt,$allowClosedPeriod);

    $stmt=$pdo->prepare("INSERT INTO expenses(financial_period_id,category_key,amount,description,occurred_at,actor_user_id,source_request_id,status) VALUES(?,?,?,?,?,?,?,'committed')");
    $stmt->execute([$financialPeriodId,$categoryKey,$amount,$description!==''?$description:null,$occurredAt,$actorUserId?:null,$sourceRequestId]);
    $id=(int)$pdo->lastInsertId();
    audit_log_write_strict($pdo,'expense.created','expense',$id,[
        'category_key'=>$categoryKey,'amount'=>$amount,'occurred_at'=>$occurredAt,'source_request_id'=>$sourceRequestId,
    ],$actorUserId);
    return ['id'=>$id,'financial_period_id'=>$financialPeriodId,'idempotent'=>false];
}

/** Append-only reversal. The original row is never edited or deleted. */
function expense_reverse_locked(PDO $pdo,int $expenseId,string $reason,int $actorUserId,string $sourceRequestId,bool $allowClosedPeriod=false): array
{
    expense_require_transaction($pdo);
    $reason=text_substr(trim($reason),0,500);$sourceRequestId=text_substr(trim($sourceRequestId),0,96);
    if($expenseId<1) throw new RuntimeException('هزینه معتبر نیست.');
    if($reason==='') throw new RuntimeException('دلیل برگشت هزینه را وارد کن.');
    if($sourceRequestId==='') throw new RuntimeException('شناسه یکتای برگشت هزینه مشخص نیست.');
    $stmt=$pdo->prepare('SELECT e.*,c.name category_name FROM expenses e JOIN expense_categories c ON c.category_key=e.category_key WHERE e.id=? FOR UPDATE');
    $stmt->execute([$expenseId]);$original=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$original) throw new RuntimeException('هزینه پیدا نشد.');
    if((string)$original['status']!=='committed') throw new RuntimeException('فقط سند هزینه اصلی قابل برگشت است.');
    $existing=$pdo->prepare('SELECT * FROM expenses WHERE reverses_expense_id=? LIMIT 1 FOR UPDATE');$existing->execute([$expenseId]);
    if($row=$existing->fetch(PDO::FETCH_ASSOC)) return ['id'=>(int)$row['id'],'original_id'=>$expenseId,'financial_period_id'=>(int)$row['financial_period_id'],'idempotent'=>true];
    $period=expense_period_locked($pdo,(int)$original['financial_period_id']);
    expense_assert_period_accepts_locked($period,(string)$original['occurred_at'],$allowClosedPeriod);
    $description='برگشت هزینه'; if($reason!=='')$description.=' — '.$reason;
    $insert=$pdo->prepare("INSERT INTO expenses(financial_period_id,category_key,amount,description,occurred_at,actor_user_id,source_request_id,status,reverses_expense_id) VALUES(?,?,?,?,?,?,?,'reversal',?)");
    $insert->execute([(int)$original['financial_period_id'],(string)$original['category_key'],(int)$original['amount'],text_substr($description,0,500),(string)$original['occurred_at'],$actorUserId?:null,$sourceRequestId,$expenseId]);
    $id=(int)$pdo->lastInsertId();
    audit_log_write_strict($pdo,'expense.reversed','expense',$id,['original_expense_id'=>$expenseId,'amount'=>(int)$original['amount'],'reason'=>$reason],$actorUserId);
    return ['id'=>$id,'original_id'=>$expenseId,'financial_period_id'=>(int)$original['financial_period_id'],'idempotent'=>false];
}

/** Correction = append-only reversal of the old row + a new committed row in the same caller transaction. */
function expense_correct_locked(PDO $pdo,int $expenseId,string $categoryKey,int $amount,string $occurredAt,string $description,string $reason,int $actorUserId,string $sourceRequestId): array
{
    expense_require_transaction($pdo);
    $sourceRequestId=text_substr(trim($sourceRequestId),0,80);
    if($sourceRequestId==='') throw new RuntimeException('شناسه یکتای اصلاح هزینه مشخص نیست.');
    $reversal=expense_reverse_locked($pdo,$expenseId,$reason,$actorUserId,$sourceRequestId.':reverse');
    $replacement=expense_create_locked($pdo,$categoryKey,$amount,$occurredAt,$description,$actorUserId,$sourceRequestId.':replace');
    audit_log_write_strict($pdo,'expense.corrected','expense',(int)$replacement['id'],[
        'original_expense_id'=>$expenseId,'reversal_expense_id'=>(int)$reversal['id'],'replacement_expense_id'=>(int)$replacement['id'],'reason'=>text_substr(trim($reason),0,500),
    ],$actorUserId);
    return ['original_id'=>$expenseId,'reversal_id'=>(int)$reversal['id'],'replacement_id'=>(int)$replacement['id'],'financial_period_id'=>(int)$replacement['financial_period_id']];
}

function expense_recent_rows(PDO $pdo,int $limit=100): array
{
    $limit=max(1,min(250,$limit));
    return $pdo->query("SELECT e.*,c.name category_name,u.display_name actor_name,fp.title period_title,fp.status period_status,r.id reversal_id,r.created_at reversal_created_at,o.id original_id,o.description original_description
        FROM expenses e JOIN expense_categories c ON c.category_key=e.category_key JOIN financial_periods fp ON fp.id=e.financial_period_id
        LEFT JOIN users u ON u.id=e.actor_user_id LEFT JOIN expenses r ON r.reverses_expense_id=e.id LEFT JOIN expenses o ON o.id=e.reverses_expense_id
        ORDER BY e.occurred_at DESC,e.id DESC LIMIT ".$limit)->fetchAll(PDO::FETCH_ASSOC);
}

function expense_period_summary(PDO $pdo,int $periodId): array
{
    $stmt=$pdo->prepare("SELECT COALESCE(SUM(status='committed'),0) committed_count,COALESCE(SUM(status='reversal'),0) reversal_count,COALESCE(SUM(CASE WHEN status='committed' THEN amount WHEN status='reversal' THEN -amount ELSE 0 END),0) net_amount FROM expenses WHERE financial_period_id=?");
    $stmt->execute([$periodId]);$row=$stmt->fetch(PDO::FETCH_ASSOC)?:[];
    return ['committed_count'=>(int)($row['committed_count']??0),'reversal_count'=>(int)($row['reversal_count']??0),'net_amount'=>(int)($row['net_amount']??0)];
}
