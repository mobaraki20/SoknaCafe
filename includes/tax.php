<?php
declare(strict_types=1);

/**
 * Optional Tax domain.
 *
 * Contract:
 * - Catalog prices are always pre-tax.
 * - Tax configuration is append-only/effective-dated.
 * - Order lines snapshot the applicable policy/rate when the line is created.
 * - Financial documents calculate tax after invoice discount allocation.
 * - Historical documents never recompute using a later rate.
 */

function tax_policy_values(): array
{
    return ['inherit_default','exempt','custom_rate'];
}

function tax_policy_label(string $policy): string
{
    return [
        'inherit_default'=>'نرخ پیش‌فرض',
        'exempt'=>'معاف از مالیات',
        'custom_rate'=>'نرخ اختصاصی',
        'disabled'=>'مالیات غیرفعال',
    ][$policy] ?? 'نرخ پیش‌فرض';
}

function tax_rate_bps_normalize(mixed $value): int
{
    $raw = trim((string)$value);
    if ($raw === '') return 0;
    $normalized = str_replace(['٪','%','٫',','],['','','.','.'], en_digits($raw));
    if (!is_numeric($normalized)) throw new RuntimeException('نرخ مالیات معتبر نیست.');
    $percent = (float)$normalized;
    if ($percent < 0 || $percent > 100) throw new RuntimeException('نرخ مالیات باید بین صفر تا صد درصد باشد.');
    return (int)round($percent * 100, 0, PHP_ROUND_HALF_UP);
}

function tax_rate_bps_label(int $bps): string
{
    $bps = max(0, min(10000, $bps));
    $whole = intdiv($bps, 100);
    $fraction = $bps % 100;
    $text = $fraction === 0 ? (string)$whole : rtrim(rtrim(sprintf('%d.%02d', $whole, $fraction), '0'), '.');
    return fa_digits($text) . '٪';
}

/** Integer half-up rounding without multiplying the full monetary amount by the rate. */
function tax_round_amount(int $taxableAmount, int $rateBps): int
{
    if ($taxableAmount <= 0 || $rateBps <= 0) return 0;
    $rateBps = min(10000, $rateBps);
    $whole = intdiv($taxableAmount, 10000) * $rateBps;
    $remainder = $taxableAmount % 10000;
    return $whole + intdiv(($remainder * $rateBps) + 5000, 10000);
}

/** Deterministic cumulative half-up target used for proportional allocation. */
function tax_proportional_target(int $totalValue, int $basisTotal, int $cumulativeBasis, bool $final = false): int
{
    if ($totalValue <= 0 || $basisTotal <= 0 || $cumulativeBasis <= 0) return 0;
    if ($final || $cumulativeBasis >= $basisTotal) return $totalValue;
    $whole = intdiv($totalValue, $basisTotal) * $cumulativeBasis;
    $rem = $totalValue % $basisTotal;
    $fraction = intdiv(($rem * $cumulativeBasis) + intdiv($basisTotal, 2), $basisTotal);
    return max(0, min($totalValue, $whole + $fraction));
}

/**
 * Allocate one invoice discount to stable lines. Input order is significant and callers
 * must provide canonical order (order_item_id ascending). The last line absorbs rounding.
 */
function tax_allocate_invoice_discount(array $lines, int $discount): array
{
    $grossTotal = array_sum(array_map(static fn(array $line): int => max(0, (int)($line['gross_amount'] ?? 0)), $lines));
    $discount = max(0, min($discount, $grossTotal));
    $runningGross = 0;
    $allocated = 0;
    $count = count($lines);
    foreach ($lines as $index => &$line) {
        $gross = max(0, (int)($line['gross_amount'] ?? 0));
        $runningGross += $gross;
        $lineDiscount = $index === $count - 1
            ? $discount - $allocated
            : max(0, tax_proportional_target($discount, $grossTotal, $runningGross, false) - $allocated);
        $lineDiscount = min($gross, $lineDiscount);
        $line['invoice_discount_amount'] = $lineDiscount;
        $line['invoice_net_amount'] = $gross - $lineDiscount;
        $allocated += $lineDiscount;
    }
    unset($line);
    return $lines;
}

function tax_calculate_invoice_lines(array $lines, int $discount): array
{
    usort($lines, static fn(array $a, array $b): int => ((int)($a['order_item_id'] ?? $a['id'] ?? 0)) <=> ((int)($b['order_item_id'] ?? $b['id'] ?? 0)));
    foreach ($lines as &$line) {
        $quantity = max(0, (int)($line['quantity'] ?? 0));
        $unit = max(0, (int)($line['unit_price'] ?? $line['unit_price_snapshot'] ?? 0));
        $line['gross_amount'] = isset($line['gross_amount']) ? max(0, (int)$line['gross_amount']) : $unit * $quantity;
        $line['tax_policy_snapshot'] = (string)($line['tax_policy_snapshot'] ?? 'disabled');
        $line['tax_rate_bps_snapshot'] = max(0, min(10000, (int)($line['tax_rate_bps_snapshot'] ?? 0)));
    }
    unset($line);
    $lines = tax_allocate_invoice_discount($lines, $discount);
    $subtotal = 0; $allocatedDiscount = 0; $taxable = 0; $tax = 0; $net = 0;
    foreach ($lines as &$line) {
        $gross = (int)$line['gross_amount'];
        $lineDiscount = (int)$line['invoice_discount_amount'];
        $lineNet = $gross - $lineDiscount;
        $rate = (int)$line['tax_rate_bps_snapshot'];
        $policy = (string)$line['tax_policy_snapshot'];
        $lineTaxable = ($policy !== 'disabled' && $policy !== 'exempt') ? $lineNet : 0;
        $lineTax = tax_round_amount($lineTaxable, $rate);
        $line['invoice_taxable_amount'] = $lineTaxable;
        $line['invoice_tax_amount'] = $lineTax;
        $line['invoice_final_amount'] = $lineNet + $lineTax;
        $subtotal += $gross;
        $allocatedDiscount += $lineDiscount;
        $net += $lineNet;
        $taxable += $lineTaxable;
        $tax += $lineTax;
    }
    unset($line);
    return [
        'lines'=>$lines,
        'subtotal'=>$subtotal,
        'discount'=>$allocatedDiscount,
        'net'=>$net,
        'taxable'=>$taxable,
        'tax'=>$tax,
        'total'=>$net + $tax,
    ];
}

function tax_default_rate_row(PDO $pdo, ?string $at = null, bool $forUpdate = false): ?array
{
    $at = trim((string)$at) ?: date('Y-m-d H:i:s');
    $sql = 'SELECT * FROM tax_rate_versions WHERE effective_from<=? ORDER BY effective_from DESC,id DESC LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$at]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function tax_module_runtime_ready(): bool
{
    try { return tax_default_rate_row(db()) !== null; }
    catch (Throwable $e) { return false; }
}

function tax_has_live_account_rows(PDO $pdo): bool
{
    $sql = "SELECT 1 FROM table_sessions s JOIN orders o ON o.session_id=s.id JOIN order_items oi ON oi.order_id=o.id AND oi.quantity>0 WHERE s.status IN('active','pending') AND o.status IN('pending_approval','new','accounted') LIMIT 1";
    return (bool)$pdo->query($sql)->fetchColumn();
}

function tax_module_before_enable_locked(PDO $pdo, int $actorUserId): void
{
    if (tax_has_live_account_rows($pdo)) throw new RuntimeException('برای فعال‌کردن مالیات، ابتدا حساب‌های باز فعلی را تعیین تکلیف کنید تا یک حساب با دو قاعده مالیاتی ساخته نشود.');
}

function tax_module_before_disable_locked(PDO $pdo, int $actorUserId): void
{
    if (tax_has_live_account_rows($pdo)) throw new RuntimeException('برای غیرفعال‌کردن مالیات، ابتدا حساب‌های باز فعلی را تعیین تکلیف کنید.');
}

function tax_module_disable_preflight(): array
{
    try {
        if (!tax_has_live_account_rows(db())) return [];
        return [[
            'message'=>'حساب باز دارای سفارش وجود دارد؛ پیش از خاموش‌کردن مالیات آن را تعیین تکلیف کنید.',
            'href'=>asset('operator/'),
            'label'=>'مشاهده حساب‌ها',
        ]];
    } catch (Throwable $e) { return []; }
}

function tax_current_policy_row(PDO $pdo, int $itemId, ?string $at = null, bool $forUpdate = false): ?array
{
    if ($itemId < 1) return null;
    $at = trim((string)$at) ?: date('Y-m-d H:i:s');
    $sql = 'SELECT * FROM tax_item_policy_versions WHERE item_id=? AND effective_from<=? ORDER BY effective_from DESC,id DESC LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$itemId,$at]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

/** Snapshot tax ownership for a newly created order line. */
function tax_order_line_snapshot(PDO $pdo, int $itemId, ?string $at = null): array
{
    if ($itemId < 1 || !sokna_module_runtime_ready('tax')) {
        return ['policy'=>'disabled','rate_bps'=>0,'rate_version_id'=>null,'policy_version_id'=>null];
    }
    $at = trim((string)$at) ?: date('Y-m-d H:i:s');
    $default = tax_default_rate_row($pdo, $at);
    if (!$default) return ['policy'=>'disabled','rate_bps'=>0,'rate_version_id'=>null,'policy_version_id'=>null];
    $policyRow = tax_current_policy_row($pdo, $itemId, $at);
    $policy = (string)($policyRow['policy'] ?? 'inherit_default');
    if (!in_array($policy, tax_policy_values(), true)) $policy = 'inherit_default';
    $rate = match($policy) {
        'exempt' => 0,
        'custom_rate' => max(0, min(10000, (int)($policyRow['custom_rate_bps'] ?? 0))),
        default => max(0, min(10000, (int)$default['rate_bps'])),
    };
    return [
        'policy'=>$policy,
        'rate_bps'=>$rate,
        'rate_version_id'=>(int)$default['id'],
        'policy_version_id'=>$policyRow ? (int)$policyRow['id'] : null,
    ];
}

/** Efficient public/read-model tax profile map; never authoritative for writes. */
function tax_item_profile_map(PDO $pdo, array $itemIds, ?string $at = null): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $itemIds), static fn(int $id): bool => $id > 0)));
    $out = [];
    foreach ($ids as $id) $out[$id] = tax_order_line_snapshot($pdo, $id, $at);
    return $out;
}

function tax_create_rate_version_locked(PDO $pdo, int $rateBps, string $effectiveFrom, int $actorUserId): int
{
    $rateBps = max(0, min(10000, $rateBps));
    $ts = strtotime($effectiveFrom);
    if ($ts === false) throw new RuntimeException('زمان شروع نرخ مالیات معتبر نیست.');
    $effectiveFrom = date('Y-m-d H:i:s', $ts);
    $existingCount=(int)$pdo->query('SELECT COUNT(*) FROM tax_rate_versions')->fetchColumn();
    if($existingCount===0){
        if($ts>time()+60)throw new RuntimeException('برای راه‌اندازی اولیه مالیات، نخستین نرخ باید از همین حالا مؤثر باشد. زمان‌بندی نرخ‌های بعدی پس از راه‌اندازی امکان‌پذیر است.');
        if(tax_has_live_account_rows($pdo))throw new RuntimeException('برای ثبت نخستین نرخ مالیات، ابتدا حساب‌های باز فعلی را تعیین تکلیف کنید.');
    }
    $stmt = $pdo->prepare('INSERT INTO tax_rate_versions(rate_bps,effective_from,created_by_user_id) VALUES(?,?,?)');
    $stmt->execute([$rateBps,$effectiveFrom,$actorUserId ?: null]);
    $id = (int)$pdo->lastInsertId();
    audit_log_write_strict($pdo,'tax.rate_version_created','tax_rate_version',$id,[
        'rate_bps'=>$rateBps,'effective_from'=>$effectiveFrom,
    ],$actorUserId);
    return $id;
}

function tax_create_item_policy_version_locked(PDO $pdo, int $itemId, string $policy, ?int $customRateBps, string $effectiveFrom, int $actorUserId): int
{
    if ($itemId < 1) throw new RuntimeException('کالای منو معتبر نیست.');
    if (!in_array($policy, tax_policy_values(), true)) throw new RuntimeException('سیاست مالیاتی کالا معتبر نیست.');
    if ($policy === 'custom_rate') {
        if ($customRateBps === null || $customRateBps < 0 || $customRateBps > 10000) throw new RuntimeException('نرخ اختصاصی مالیات معتبر نیست.');
    } else $customRateBps = null;
    $ts = strtotime($effectiveFrom);
    if ($ts === false) throw new RuntimeException('زمان شروع سیاست مالیاتی معتبر نیست.');
    $effectiveFrom = date('Y-m-d H:i:s', $ts);
    $check = $pdo->prepare('SELECT id,name FROM items WHERE id=? LIMIT 1 FOR UPDATE');
    $check->execute([$itemId]);
    $item = $check->fetch();
    if (!$item) throw new RuntimeException('کالای منو پیدا نشد.');
    $stmt = $pdo->prepare('INSERT INTO tax_item_policy_versions(item_id,policy,custom_rate_bps,effective_from,created_by_user_id) VALUES(?,?,?,?,?)');
    $stmt->execute([$itemId,$policy,$customRateBps,$effectiveFrom,$actorUserId ?: null]);
    $id = (int)$pdo->lastInsertId();
    audit_log_write_strict($pdo,'tax.item_policy_version_created','tax_item_policy_version',$id,[
        'item_id'=>$itemId,'item_name'=>(string)$item['name'],'policy'=>$policy,'custom_rate_bps'=>$customRateBps,'effective_from'=>$effectiveFrom,
    ],$actorUserId);
    return $id;
}
