<?php
declare(strict_types=1);

/** Shared report range/settlement contract for all management reports. */
function report_range_resolve(string $defaultPeriod = '30', int $maxDays = 730): array
{
    $allowed = ['today','7','30','90','365','custom'];
    $period = (string)($_GET['period'] ?? '');
    if (!in_array($period, $allowed, true)) $period = $defaultPeriod;

    $today = business_current_date();
    $error = '';
    if ($period === 'custom') {
        $from = parse_jalali_date((string)($_GET['from_j'] ?? ''));
        $to = parse_jalali_date((string)($_GET['to_j'] ?? ''));
        if (!$from || !$to) {
            $error = 'تاریخ شروع و پایان بازه را انتخاب کن.';
            $from = $to = $today;
        } elseif ($to < $from) {
            $error = 'پایان بازه باید بعد از شروع باشد.';
            $to = $from;
        }
    } else {
        $days = $period === 'today' ? 1 : (int)$period;
        $to = $today;
        $from = (new DateTimeImmutable($today))->modify('-'.($days - 1).' days')->format('Y-m-d');
    }

    $spanDays = (int)((new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days ?? 0) + 1;
    if ($spanDays > $maxDays) {
        $error = 'بازه گزارش بیش از '.fa_digits((string)$maxDays).' روز است؛ بازه کوتاه‌تری انتخاب کن.';
        $from = (new DateTimeImmutable($to))->modify('-'.($maxDays - 1).' days')->format('Y-m-d');
        $spanDays = $maxDays;
    }

    return [
        'period' => $period,
        'from' => $from,
        'to' => $to,
        'days' => $spanDays,
        'from_j' => (string)($_GET['from_j'] ?? jalali_date_input($from)),
        'to_j' => (string)($_GET['to_j'] ?? jalali_date_input($to)),
        'label' => $from === $to ? format_jalali_date($from) : format_jalali_date($from, false).' تا '.format_jalali_date($to, false),
        'error' => $error,
    ];
}

function report_range_options(): array
{
    return [
        'today' => 'امروز',
        '7' => '۷ روز',
        '30' => '۳۰ روز',
        '90' => '۹۰ روز',
        '365' => 'یک سال',
        'custom' => 'بازه دلخواه',
    ];
}

function report_range_query(array $range, array $extra = []): array
{
    $query = ['period' => $range['period']];
    if (($range['period'] ?? '') === 'custom') {
        $query['from_j'] = $range['from_j'];
        $query['to_j'] = $range['to_j'];
    }
    return array_merge($query, $extra);
}

function report_valid_settlement_sql(string $alias = 'sr'): string
{
    $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 'sr';
    return "$a.status='completed' AND NOT EXISTS(SELECT 1 FROM settlement_records rv WHERE rv.reverses_settlement_id=$a.id AND rv.status='reversal')";
}

/**
 * Canonical sold-item source per financial receipt.
 * allocation_version=1 reads immutable settlement lines. Historical version 0
 * keeps the pre-itemized one-full-receipt-per-session contract for existing data.
 */
function report_settlement_line_source_sql(string $alias = 'sale_line'): string
{
    $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias) ?: 'sale_line';
    return "(SELECT sl.settlement_id,sl.order_item_id,sl.order_id,sl.item_id_snapshot item_id,sl.item_name_snapshot item_name,sl.unit_price_snapshot unit_price,sl.quantity,sl.gross_amount line_total,sl.discount_amount,sl.net_amount,sl.taxable_amount,sl.tax_rate_bps,sl.tax_amount,sl.final_amount
        FROM settlement_record_lines sl
        UNION ALL
        SELECT sr0.id settlement_id,oi.id order_item_id,o.id order_id,oi.item_id,oi.item_name,oi.unit_price,oi.quantity,oi.line_total,0 discount_amount,oi.line_total net_amount,0 taxable_amount,0 tax_rate_bps,0 tax_amount,oi.line_total final_amount
        FROM settlement_records sr0 JOIN orders o ON o.session_id=sr0.session_id JOIN order_items oi ON oi.order_id=o.id
        WHERE sr0.allocation_version=0) $a";
}

function report_render_range_fields(array $range, string $idPrefix = 'report'): void
{
    $period = (string)$range['period'];
    $custom = $period === 'custom';
    $selectId = $idPrefix . 'Period';
    $fromId = $idPrefix . 'FromDateJ';
    $toId = $idPrefix . 'ToDateJ';
    ?>
    <div class="form-group report-range-field"><label for="<?= e($selectId) ?>">بازه</label><select class="form-control" name="period" data-choice-mode="compact" data-choice-label="بازه گزارش" id="<?= e($selectId) ?>"><?php foreach(report_range_options() as $key=>$label): $key=(string)$key; ?><option value="<?= e($key) ?>" <?= $period===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div class="form-group <?= $custom?'':'hidden' ?>" data-custom-date-field data-panel-condition-source="<?= e($selectId) ?>" data-panel-condition-value="custom"><label for="<?= e($fromId) ?>">از تاریخ</label><div class="jalali-date-control"><input class="form-control" id="<?= e($fromId) ?>" name="from_j" data-jalali-date inputmode="none" value="<?= e((string)$range['from_j']) ?>" placeholder="۱۴۰۵/۰۵/۰۳"><button class="jalali-date-button" type="button" data-open-jalali="<?= e($fromId) ?>" aria-label="انتخاب تاریخ شروع از تقویم"><?= ui_icon('calendar') ?></button></div></div>
    <div class="form-group <?= $custom?'':'hidden' ?>" data-custom-date-field data-panel-condition-source="<?= e($selectId) ?>" data-panel-condition-value="custom"><label for="<?= e($toId) ?>">تا تاریخ</label><div class="jalali-date-control"><input class="form-control" id="<?= e($toId) ?>" name="to_j" data-jalali-date inputmode="none" value="<?= e((string)$range['to_j']) ?>" placeholder="۱۴۰۵/۰۵/۰۳"><button class="jalali-date-button" type="button" data-open-jalali="<?= e($toId) ?>" aria-label="انتخاب تاریخ پایان از تقویم"><?= ui_icon('calendar') ?></button></div></div>
    <?php
}

/**
 * Keep report exports complete without allowing one request to exhaust memory.
 * UI summaries may intentionally show a top/recent subset; Excel must cover the
 * full applied range or explicitly ask for a shorter range.
 */
function report_export_guard(int $rowCount, string $label, int $maxRows = 5000): void
{
    if ($rowCount <= $maxRows) return;
    throw new RuntimeException(
        $label.' در این بازه '.fa_digits((string)$rowCount).' ردیف دارد؛ برای خروجی کامل بازه کوتاه‌تری انتخاب کنید (حداکثر '.fa_digits((string)$maxRows).' ردیف).'
    );
}
