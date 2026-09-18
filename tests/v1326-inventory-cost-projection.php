<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/inventory.php';

function approx(?float $actual, float $expected, float $epsilon=0.000001): void {
    if ($actual === null || abs($actual-$expected) > $epsilon) {
        throw new RuntimeException('Projection mismatch: got '.var_export($actual,true).' expected '.$expected);
    }
}

$base = [
    ['id'=>1,'movement_type'=>'purchase_receive','quantity_base'=>100,'total_cost_delta'=>1000,'correction_of_id'=>null,'reversal_of_id'=>null,'unit_cost_snapshot'=>10,'cost_status'=>'known','occurred_at'=>'2026-08-01 10:00:00'],
    ['id'=>2,'movement_type'=>'recipe_consumption','quantity_base'=>-90,'total_cost_delta'=>-900,'correction_of_id'=>null,'reversal_of_id'=>null,'unit_cost_snapshot'=>10,'cost_status'=>'known','occurred_at'=>'2026-08-02 10:00:00'],
    ['id'=>3,'movement_type'=>'cost_adjustment','quantity_base'=>0,'total_cost_delta'=>100,'correction_of_id'=>1,'reversal_of_id'=>null,'unit_cost_snapshot'=>null,'cost_status'=>'known','occurred_at'=>'2026-08-03 10:00:00'],
];
$projection = inventory_projection_replay_rows($base);
if ((int)$projection['quantity_base'] !== 10) throw new RuntimeException('Cost correction quantity changed unexpectedly.');
approx($projection['average_unit_cost'],11.0);

// A quantity correction belongs to the original receipt, not to today's remainder.
$qtyCorrection = $base;
$qtyCorrection[2] = ['id'=>3,'movement_type'=>'quantity_correction','quantity_base'=>10,'total_cost_delta'=>100,'correction_of_id'=>1,'reversal_of_id'=>null,'unit_cost_snapshot'=>10,'cost_status'=>'known','occurred_at'=>'2026-08-03 10:00:00'];
$projection = inventory_projection_replay_rows($qtyCorrection);
if ((int)$projection['quantity_base'] !== 20) throw new RuntimeException('Quantity correction was not folded into source receipt.');
approx($projection['average_unit_cost'],1000/110);

// Reversal restores at the replayed historical consumption cost, so corrected average survives.
$withReversal = $base;
$withReversal[] = ['id'=>4,'movement_type'=>'reversal','quantity_base'=>10,'total_cost_delta'=>100,'correction_of_id'=>null,'reversal_of_id'=>2,'unit_cost_snapshot'=>10,'cost_status'=>'known','occurred_at'=>'2026-08-04 10:00:00'];
$projection = inventory_projection_replay_rows($withReversal);
if ((int)$projection['quantity_base'] !== 20) throw new RuntimeException('Reversal quantity mismatch.');
approx($projection['average_unit_cost'],11.0);

// Historical basis uses the corrected truth of the source receipt, even if the correction was entered later.
$projection = inventory_projection_replay_rows($base,'2026-08-02 12:00:00');
if ((int)$projection['quantity_base'] !== 10) throw new RuntimeException('Historical projection quantity mismatch.');
approx($projection['average_unit_cost'],11.0);

echo "v1.32.14 inventory cost projection replay passed: late cost/quantity corrections are folded into source history and no longer distort remaining-stock average.\n";
