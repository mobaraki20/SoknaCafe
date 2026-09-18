<?php
declare(strict_types=1);

final class GuestOrderStatusException extends RuntimeException
{
    public function __construct(
        public string $errorCode,
        string $message,
        public int $httpStatus=422
    ){parent::__construct($message);}
}

function guest_order_status_lookup(PDO $pdo,array $data):array
{
    $orderCode=trim((string)($data['order_code']??''));
    $clientToken=trim((string)($data['client_token']??''));
    if($orderCode===''||$clientToken===''||strlen($orderCode)>32||strlen($clientToken)>80){
        throw new GuestOrderStatusException('invalid_tracking','اطلاعات پیگیری معتبر نیست.',422);
    }
    $stmt=$pdo->prepare("SELECT o.status,o.accepted_at,o.updated_at,o.session_id,ts.status AS session_status,ts.ended_at AS session_ended_at
        FROM orders o LEFT JOIN table_sessions ts ON ts.id=o.session_id
        WHERE o.public_code=? AND o.client_token=? LIMIT 1");
    $stmt->execute([$orderCode,$clientToken]);$order=$stmt->fetch();
    if(!$order)throw new GuestOrderStatusException('order_not_found','سفارش پیدا نشد.',404);
    if(($order['session_status']??null)==='closed'){
        return [
            'success'=>true,'expired'=>true,'status'=>'session_closed',
            'status_label'=>'نشست این میز پایان یافته است.',
            'updated_at'=>$order['session_ended_at']?:$order['updated_at'],
        ];
    }
    return [
        'success'=>true,'status'=>(string)$order['status'],
        'status_label'=>order_status_label((string)$order['status']),
        'accepted'=>$order['accepted_at']!==null||in_array((string)$order['status'],['accounted','completed'],true),
        'updated_at'=>(string)$order['updated_at'],
    ];
}
