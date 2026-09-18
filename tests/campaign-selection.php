<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/functions.php';

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$rows = [
    ['id'=>10,'title'=>'A'],
    ['id'=>20,'title'=>'B'],
    ['id'=>30,'title'=>'C'],
];
check((int)(select_campaign_from_eligible($rows, 'priority', 'guest-a')['id'] ?? 0) === 10, 'Priority must select the first eligible campaign.');
check((int)(select_campaign_from_eligible([$rows[1],$rows[2]], 'priority', 'guest-a')['id'] ?? 0) === 20, 'Priority must advance when the prior campaign is no longer eligible.');
$first = select_campaign_from_eligible($rows, 'rotation', 'guest-sticky');
for ($i=0;$i<20;$i++) check((int)(select_campaign_from_eligible($rows, 'rotation', 'guest-sticky')['id'] ?? 0) === (int)$first['id'], 'Rotation must remain sticky for the same visitor and eligible set.');
$seen=[];
for ($i=0;$i<80;$i++) $seen[(int)(select_campaign_from_eligible($rows, 'rotation', 'visitor-'.$i)['id'] ?? 0)] = true;
check(count($seen) >= 2, 'Rotation should distribute different visitors across more than one eligible campaign.');
check(select_campaign_from_eligible([], 'priority', 'x') === null, 'Empty eligibility must render no campaign.');
check((int)(select_campaign_from_eligible([$rows[2]], 'rotation', 'x')['id'] ?? 0) === 30, 'Single eligible campaign must render directly.');
echo "Campaign selection contract passed: one campaign, priority advance, sticky rotation, no random refresh.\n";
