<?php
declare(strict_types=1);

function expense_categories(PDO $pdo,bool $activeOnly=true): array
{
    $sql='SELECT category_key,name,active,system_category,sort_order FROM expense_categories'.($activeOnly?' WHERE active=1':'').' ORDER BY sort_order,name,category_key';
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
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
    ?int $financialPeriodId=null
): array {
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
    }
    $stmt=$pdo->prepare("INSERT INTO expenses(financial_period_id,category_key,amount,description,occurred_at,actor_user_id,source_request_id,status) VALUES(?,?,?,?,?,?,?,'committed')");
    $stmt->execute([$financialPeriodId,$categoryKey,$amount,$description!==''?$description:null,$occurredAt,$actorUserId?:null,$sourceRequestId]);
    $id=(int)$pdo->lastInsertId();
    audit_log_write_strict($pdo,'expense.created','expense',$id,[
        'category_key'=>$categoryKey,'amount'=>$amount,'occurred_at'=>$occurredAt,'source_request_id'=>$sourceRequestId,
    ],$actorUserId);
    return ['id'=>$id,'financial_period_id'=>$financialPeriodId,'idempotent'=>false];
}
