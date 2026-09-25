<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/functions.php';
require dirname(__DIR__) . '/includes/tax.php';
require dirname(__DIR__) . '/includes/settlement.php';

$checks = 0;
$failures = [];
function tax_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    if (!$condition) $failures[] = $message;
}

tax_check(tax_round_amount(1000, 1000) === 100, '10% tax must equal 100 on 1000.');
tax_check(tax_round_amount(5, 1000) === 1, 'Tax rounding must use integer half-up semantics.');
tax_check(tax_round_amount(4, 1000) === 0, 'Tax rounding below half must round down.');

$invoice = tax_calculate_invoice_lines([
    ['order_item_id'=>1,'quantity'=>1,'unit_price'=>1000,'tax_policy_snapshot'=>'inherit_default','tax_rate_bps_snapshot'=>1000],
    ['order_item_id'=>2,'quantity'=>1,'unit_price'=>1000,'tax_policy_snapshot'=>'exempt','tax_rate_bps_snapshot'=>0],
], 200);
tax_check($invoice['subtotal'] === 2000, 'Invoice subtotal mismatch.');
tax_check($invoice['discount'] === 200, 'Invoice discount mismatch.');
tax_check($invoice['net'] === 1800, 'Invoice net mismatch.');
tax_check($invoice['taxable'] === 900, 'Only taxable line after discount must enter taxable amount.');
tax_check($invoice['tax'] === 90, 'Tax must be calculated after allocated discount.');
tax_check($invoice['total'] === 1890, 'Final total must be net plus tax.');

$account = [
    'allocation_version'=>2,
    'subtotal'=>3000,
    'discount'=>300,
    'taxable'=>2700,
    'tax'=>270,
    'total'=>2970,
    'paid_subtotal'=>0,
    'paid_discount'=>0,
    'paid_taxable'=>0,
    'paid_tax'=>0,
    'paid_total'=>0,
    'remaining_subtotal'=>3000,
    'remaining_discount'=>300,
    'remaining_taxable'=>2700,
    'remaining_tax'=>270,
    'remaining_total'=>2970,
    'items'=>[
        [
            'id'=>11,'order_id'=>1,'item_id'=>101,'item_name'=>'نمونه','unit_price'=>1000,'quantity'=>3,
            'remaining_quantity'=>3,'paid_quantity'=>0,'paid_discount_amount'=>0,'paid_tax_amount'=>0,
            'invoice_discount_amount'=>300,'tax_policy_snapshot'=>'inherit_default','tax_rate_bps_snapshot'=>1000,'item_note'=>'',
        ],
    ],
];

$first = settlement_review_selection_tax_v2($account, [11=>1]);
tax_check($first['subtotal'] === 1000 && $first['discount'] === 100 && $first['tax'] === 90 && $first['total'] === 990, 'First partial tax allocation mismatch.');

$afterFirst = $account;
$afterFirst['paid_subtotal'] = 1000;
$afterFirst['paid_discount'] = 100;
$afterFirst['paid_taxable'] = 900;
$afterFirst['paid_tax'] = 90;
$afterFirst['paid_total'] = 990;
$afterFirst['remaining_subtotal'] = 2000;
$afterFirst['remaining_discount'] = 200;
$afterFirst['remaining_taxable'] = 1800;
$afterFirst['remaining_tax'] = 180;
$afterFirst['remaining_total'] = 1980;
$afterFirst['items'][0]['remaining_quantity'] = 2;
$afterFirst['items'][0]['paid_quantity'] = 1;
$afterFirst['items'][0]['paid_discount_amount'] = 100;
$afterFirst['items'][0]['paid_tax_amount'] = 90;
$last = settlement_review_selection_tax_v2($afterFirst, [11=>2]);
tax_check($last['subtotal'] === 2000 && $last['discount'] === 200 && $last['tax'] === 180 && $last['total'] === 1980, 'Final partial tax allocation mismatch.');
tax_check($first['tax'] + $last['tax'] === $account['tax'], 'Partial payments must reconcile to full invoice tax.');
tax_check($first['total'] + $last['total'] === $account['total'], 'Partial payments must reconcile to full invoice total.');

$allAtOnce = settlement_review_selection_tax_v2($account, [11=>3]);
tax_check($allAtOnce['tax'] === $first['tax'] + $last['tax'], 'Tax must be path independent across payment splits.');
tax_check($allAtOnce['total'] === $first['total'] + $last['total'], 'Final amount must be path independent across payment splits.');

$mixed = [
    'allocation_version'=>2,
    'subtotal'=>2000,'discount'=>200,'taxable'=>900,'tax'=>90,'total'=>1890,
    'paid_subtotal'=>0,'paid_discount'=>0,'paid_taxable'=>0,'paid_tax'=>0,'paid_total'=>0,
    'remaining_subtotal'=>2000,'remaining_discount'=>200,'remaining_taxable'=>900,'remaining_tax'=>90,'remaining_total'=>1890,
    'items'=>[
        ['id'=>21,'order_id'=>2,'item_id'=>201,'item_name'=>'مشمول','unit_price'=>1000,'quantity'=>1,'remaining_quantity'=>1,'paid_quantity'=>0,'paid_discount_amount'=>0,'paid_tax_amount'=>0,'invoice_discount_amount'=>100,'tax_policy_snapshot'=>'inherit_default','tax_rate_bps_snapshot'=>1000,'item_note'=>''],
        ['id'=>22,'order_id'=>2,'item_id'=>202,'item_name'=>'معاف','unit_price'=>1000,'quantity'=>1,'remaining_quantity'=>1,'paid_quantity'=>0,'paid_discount_amount'=>0,'paid_tax_amount'=>0,'invoice_discount_amount'=>100,'tax_policy_snapshot'=>'exempt','tax_rate_bps_snapshot'=>0,'item_note'=>''],
    ],
];
$exemptOnly = settlement_review_selection_tax_v2($mixed, [22=>1]);
tax_check($exemptOnly['tax'] === 0 && $exemptOnly['total'] === 900, 'Exempt partial receipt must never collect tax.');
$taxableOnly = settlement_review_selection_tax_v2($mixed, [21=>1]);
tax_check($taxableOnly['tax'] === 90 && $taxableOnly['total'] === 990, 'Taxable partial receipt must collect only its own tax.');
tax_check($exemptOnly['total'] + $taxableOnly['total'] === 1890, 'Mixed exempt/taxable receipts must reconcile to invoice total.');

if ($failures) {
    fwrite(STDERR, "FAIL r2-phase6f-tax-contract ($checks checks)\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "PASS r2-phase6f-tax-contract ($checks checks)\n";
