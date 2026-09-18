<?php
declare(strict_types=1);
function normalize_fulfillment_mode(string $value): string { return in_array($value,['dine_in','takeaway'],true)?$value:'dine_in'; }
require dirname(__DIR__).'/includes/settlement.php';

$feedSession=['id'=>28,'session_id'=>904,'discount_type'=>'fixed','discount_value'=>10000];
$lockedSession=['id'=>904,'discount_type'=>'fixed','discount_value'=>10000];
$orders=[[
    'id'=>71,'status'=>'accounted','total_amount'=>1190000,
    'items'=>[
        ['id'=>701,'item_name'=>'برگر','quantity'=>1,'unit_price'=>590000,'line_total'=>590000,'item_note'=>'','fulfillment_mode'=>'dine_in'],
        ['id'=>702,'item_name'=>'سالاد','quantity'=>1,'unit_price'=>600000,'line_total'=>600000,'item_note'=>'سس جدا','fulfillment_mode'=>'takeaway'],
    ],
]];
$feed=settlement_review_signature($feedSession,$orders);
$locked=settlement_review_signature($lockedSession,$orders);
if(!hash_equals($feed,$locked)) throw new RuntimeException('Feed and locked session signatures diverged; table id must never replace session id.');

$changed=$orders;$changed[0]['items'][1]['quantity']=2;$changed[0]['items'][1]['line_total']=1200000;
if(hash_equals($feed,settlement_review_signature($feedSession,$changed))) throw new RuntimeException('Quantity/line changes must invalidate reviewed invoice signature.');
$noteChanged=$orders;$noteChanged[0]['items'][1]['item_note']='بدون سس';
if(hash_equals($feed,settlement_review_signature($feedSession,$noteChanged))) throw new RuntimeException('Reviewed line-note changes must invalidate signature.');
$discountChanged=$feedSession;$discountChanged['discount_value']=20000;
if(hash_equals($feed,settlement_review_signature($discountChanged,$orders))) throw new RuntimeException('Discount changes must invalidate signature.');

$zeroPaid=[
    'paid_quantities'=>[],
    'paid_subtotal'=>0,
    'paid_discount'=>0,
    'paid_total'=>0,
];
$feedWithPaidState=settlement_review_signature($feedSession,$orders,$zeroPaid);
$lockedWithPaidState=settlement_review_signature($lockedSession,$orders,$zeroPaid);
if(!hash_equals($feedWithPaidState,$lockedWithPaidState)) throw new RuntimeException('Paid-state-aware signatures diverged between feed and locked session shapes.');
if(hash_equals($feed,$feedWithPaidState)) throw new RuntimeException('Zero paid-state must remain part of the reviewed account contract when the feed publishes it.');
$partialPaid=$zeroPaid;
$partialPaid['paid_quantities']=[701=>1];
$partialPaid['paid_subtotal']=590000;
$partialPaid['paid_discount']=5000;
$partialPaid['paid_total']=585000;
if(hash_equals($feedWithPaidState,settlement_review_signature($feedSession,$orders,$partialPaid))) throw new RuntimeException('Paid allocation changes must invalidate the reviewed account signature.');

echo "Settlement review signature runtime PASS: feed/locked parity, explicit paid-state parity, and content/payment invalidation are stable.\n";
