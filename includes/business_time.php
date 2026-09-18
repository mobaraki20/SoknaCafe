<?php
declare(strict_types=1);

/**
 * Operational day / shift owner.
 *
 * Canonical timestamps stay unchanged. This layer only assigns a business date
 * and shift for reporting. Settlements snapshot that assignment at write time.
 */

function business_clock_minutes(string $clock): int
{
    $clock = trim(en_digits($clock));
    if (!preg_match('/^(\d{1,2}):(\d{2})$/', $clock, $m)) {
        throw new RuntimeException('ساعت معتبر نیست.');
    }
    $hour = (int)$m[1];
    $minute = (int)$m[2];
    if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
        throw new RuntimeException('ساعت معتبر نیست.');
    }
    return $hour * 60 + $minute;
}

function business_clock_normalize(string $clock): string
{
    $minutes = business_clock_minutes($clock);
    return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}

function business_new_shift_key(array $reserved = []): string
{
    $taken = array_fill_keys(array_map('strval', $reserved), true);
    do {
        $key = 'shift_' . bin2hex(random_bytes(6));
    } while (isset($taken[$key]));
    return $key;
}

function business_active_shift_count(): int
{
    return count(business_shifts());
}

/** Shift keys that already exist in immutable operational snapshots. */
function business_used_shift_keys(): array
{
    $sql = "SELECT business_shift_key FROM orders WHERE business_shift_key<>'' AND business_shift_key<>'outside'
        UNION SELECT business_shift_key FROM waiter_calls WHERE business_shift_key<>'' AND business_shift_key<>'outside'
        UNION SELECT business_shift_key FROM table_sessions WHERE business_shift_key<>'' AND business_shift_key<>'outside'
        UNION SELECT business_shift_key FROM settlement_records WHERE business_shift_key<>'' AND business_shift_key<>'outside'";
    $keys = [];
    foreach (db()->query($sql) as $row) {
        $key = trim((string)($row['business_shift_key'] ?? ''));
        if ($key !== '') $keys[$key] = true;
    }
    return array_keys($keys);
}

function business_default_shifts(): array
{
    return [
        ['key'=>'shift_1','label'=>'صبح','start'=>'08:00','end'=>'16:00','active'=>true],
        ['key'=>'shift_2','label'=>'عصر','start'=>'16:00','end'=>'01:00','active'=>true],
    ];
}

function business_day_cutoff(): string
{
    $raw = setting('business_day_cutoff', '04:00');
    try { return business_clock_normalize($raw); }
    catch (Throwable) { return '04:00'; }
}

function business_shift_interval(string $start, string $end, string $cutoff): array
{
    $cutoffMinutes = business_clock_minutes($cutoff);
    $startMinutes = business_clock_minutes($start);
    $endMinutes = business_clock_minutes($end);
    if ($startMinutes === $endMinutes) {
        throw new RuntimeException('ساعت شروع و پایان یک شیفت نمی‌تواند یکسان باشد.');
    }
    $startOffset = $startMinutes - $cutoffMinutes;
    if ($startOffset < 0) $startOffset += 1440;
    $endOffset = $endMinutes - $cutoffMinutes;
    if ($endOffset < 0) $endOffset += 1440;
    if ($endOffset <= $startOffset) $endOffset += 1440;
    if ($startOffset < 0 || $startOffset >= 1440 || $endOffset <= $startOffset || $endOffset > 1440) {
        throw new RuntimeException('ساعت یک شیفت از مرز روز عملیاتی عبور می‌کند.');
    }
    return ['start_offset'=>$startOffset,'end_offset'=>$endOffset];
}

function business_validate_configuration(string $cutoff, array $shifts): array
{
    $cutoff = business_clock_normalize($cutoff);
    $normalized = [];
    $seenKeys = [];
    foreach ($shifts as $index => $shift) {
        if (!is_array($shift) || empty($shift['active'])) continue;
        if (count($normalized) >= 3) throw new RuntimeException('حداکثر سه شیفت فعال مجاز است.');
        $key = preg_replace('/[^a-z0-9_\-]/i', '', (string)($shift['key'] ?? '')) ?: 'shift_legacy_' . ($index + 1);
        if (isset($seenKeys[$key])) throw new RuntimeException('شناسه دو شیفت نمی‌تواند یکسان باشد.');
        $seenKeys[$key] = true;
        $label = text_substr(trim((string)($shift['label'] ?? '')), 0, 60);
        if ($label === '') throw new RuntimeException('برای هر شیفت فعال یک نام وارد کن.');
        $start = business_clock_normalize((string)($shift['start'] ?? ''));
        $end = business_clock_normalize((string)($shift['end'] ?? ''));
        $interval = business_shift_interval($start, $end, $cutoff);
        $normalized[] = [
            'key'=>$key,
            'label'=>$label,
            'start'=>$start,
            'end'=>$end,
            'active'=>true,
            'start_offset'=>$interval['start_offset'],
            'end_offset'=>$interval['end_offset'],
        ];
    }
    if (!$normalized) throw new RuntimeException('حداقل یک شیفت عملیاتی باید فعال باشد.');
    usort($normalized, static fn(array $a, array $b): int => $a['start_offset'] <=> $b['start_offset']);
    for ($i=1, $n=count($normalized); $i<$n; $i++) {
        if ($normalized[$i]['start_offset'] < $normalized[$i-1]['end_offset']) {
            throw new RuntimeException('ساعت شیفت‌ها نباید با هم هم‌پوشانی داشته باشد.');
        }
    }
    return ['cutoff'=>$cutoff,'shifts'=>$normalized];
}

function business_shifts(): array
{
    $raw = trim(setting('business_shifts_json', ''));
    $decoded = $raw !== '' ? json_decode($raw, true) : null;
    $candidate = is_array($decoded) ? $decoded : business_default_shifts();
    try {
        return business_validate_configuration(business_day_cutoff(), $candidate)['shifts'];
    } catch (Throwable) {
        return business_validate_configuration('04:00', business_default_shifts())['shifts'];
    }
}

function business_assignment(DateTimeInterface|string|null $value = null): array
{
    $tz = new DateTimeZone(app_timezone());
    if ($value instanceof DateTimeInterface) {
        $dt = DateTimeImmutable::createFromInterface($value)->setTimezone($tz);
    } elseif (is_string($value) && trim($value) !== '') {
        $dt = new DateTimeImmutable($value, $tz);
    } else {
        $dt = new DateTimeImmutable('now', $tz);
    }
    $cutoffMinutes = business_clock_minutes(business_day_cutoff());
    $clockMinutes = ((int)$dt->format('G')) * 60 + (int)$dt->format('i');
    $businessDate = $clockMinutes < $cutoffMinutes ? $dt->modify('-1 day')->format('Y-m-d') : $dt->format('Y-m-d');
    $offset = $clockMinutes - $cutoffMinutes;
    if ($offset < 0) $offset += 1440;
    foreach (business_shifts() as $shift) {
        if ($offset >= (int)$shift['start_offset'] && $offset < (int)$shift['end_offset']) {
            return [
                'business_date'=>$businessDate,
                'shift_key'=>(string)$shift['key'],
                'shift_label'=>(string)$shift['label'],
                'cutoff'=>business_day_cutoff(),
            ];
        }
    }
    return ['business_date'=>$businessDate,'shift_key'=>'outside','shift_label'=>'خارج از شیفت','cutoff'=>business_day_cutoff()];
}

function business_current_date(): string
{
    return (string)business_assignment()['business_date'];
}

function business_day_bounds(string $businessDate): array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $businessDate)) throw new RuntimeException('تاریخ روز عملیاتی معتبر نیست.');
    $tz = new DateTimeZone(app_timezone());
    $start = new DateTimeImmutable($businessDate . ' ' . business_day_cutoff() . ':00', $tz);
    return [$start, $start->modify('+1 day')];
}

function business_date_range_bounds(string $fromDate, string $toDate): array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
        throw new RuntimeException('بازه روز عملیاتی معتبر نیست.');
    }
    [$start] = business_day_bounds($fromDate);
    [, $end] = business_day_bounds($toDate);
    if ($end <= $start) throw new RuntimeException('پایان بازه باید بعد از شروع باشد.');
    return [$start, $end];
}

function business_shift_by_key(string $key): ?array
{
    foreach (business_shifts() as $shift) if ((string)$shift['key'] === $key) return $shift;
    return null;
}

/** Stable report predicate for rows that snapshot their business day / shift at creation. */
function business_snapshot_filter_sql(string $alias, string $fromDate, string $toDate, string $shiftKey = ''): array
{
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)) throw new RuntimeException('شناسه جدول گزارش معتبر نیست.');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
        throw new RuntimeException('بازه روز عملیاتی معتبر نیست.');
    }
    $sql = "$alias.business_date>=? AND $alias.business_date<=?";
    $params = [$fromDate, $toDate];
    if ($shiftKey !== '') {
        $sql .= " AND $alias.business_shift_key=?";
        $params[] = $shiftKey;
    }
    return [$sql, $params];
}

/** SQL predicate for event timestamps that are not snapshot-assigned to a shift. */
function business_shift_time_sql(string $column, string $shiftKey): array
{
    if ($shiftKey === 'outside') {
        $clauses = [];
        $params = [];
        foreach (business_shifts() as $shift) {
            $start = (string)$shift['start'] . ':00';
            $end = (string)$shift['end'] . ':00';
            $startMin = business_clock_minutes((string)$shift['start']);
            $endMin = business_clock_minutes((string)$shift['end']);
            if ($startMin < $endMin) $clauses[] = "(TIME($column)>=? AND TIME($column)<?)";
            else $clauses[] = "(TIME($column)>=? OR TIME($column)<?)";
            array_push($params, $start, $end);
        }
        return $clauses ? ['NOT (' . implode(' OR ', $clauses) . ')', $params] : ['1=1', []];
    }
    $shift = business_shift_by_key($shiftKey);
    if (!$shift) return ['1=1', []];
    $start = (string)$shift['start'] . ':00';
    $end = (string)$shift['end'] . ':00';
    $startMin = business_clock_minutes((string)$shift['start']);
    $endMin = business_clock_minutes((string)$shift['end']);
    if ($startMin < $endMin) return ["TIME($column)>=? AND TIME($column)<?", [$start,$end]];
    return ["(TIME($column)>=? OR TIME($column)<?)", [$start,$end]];
}

function business_shift_filter_options(bool $includeCompare = false): array
{
    $shifts = business_shifts();
    $options = ['all'=>'کل روز'];
    // A single-shift café does not need a shift filter in day-to-day reports.
    if (count($shifts) < 2) return $options;
    foreach ($shifts as $shift) $options[(string)$shift['key']] = (string)$shift['label'];
    if ($includeCompare) $options['compare'] = 'مقایسه شیفت‌ها';
    return $options;
}

/**
 * Shift choices for a report range. Historical snapshot keys are included so a
 * retired shift never disappears from old reports. A one-shift café remains
 * visually simple when the selected range contains only that one shift.
 */
function business_report_shift_options(string $fromDate, string $toDate, bool $includeCompare = false): array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
        throw new RuntimeException('بازه روز عملیاتی معتبر نیست.');
    }
    $current = [];
    foreach (business_shifts() as $shift) $current[(string)$shift['key']] = (string)$shift['label'];

    $history = [];
    $sql = "SELECT business_shift_key,business_shift_label,MAX(event_at) last_seen FROM (
        SELECT business_shift_key,business_shift_label,created_at event_at FROM orders WHERE business_date>=? AND business_date<=? AND business_shift_key<>'' AND business_shift_key<>'outside'
        UNION ALL
        SELECT business_shift_key,business_shift_label,created_at event_at FROM waiter_calls WHERE business_date>=? AND business_date<=? AND business_shift_key<>'' AND business_shift_key<>'outside'
        UNION ALL
        SELECT business_shift_key,business_shift_label,started_at event_at FROM table_sessions WHERE business_date>=? AND business_date<=? AND business_shift_key<>'' AND business_shift_key<>'outside'
        UNION ALL
        SELECT business_shift_key,business_shift_label,settled_at event_at FROM settlement_records WHERE business_date>=? AND business_date<=? AND business_shift_key<>'' AND business_shift_key<>'outside'
    ) snapshot_shifts GROUP BY business_shift_key,business_shift_label ORDER BY last_seen DESC";
    $params = [$fromDate,$toDate,$fromDate,$toDate,$fromDate,$toDate,$fromDate,$toDate];
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt as $row) {
        $key = trim((string)($row['business_shift_key'] ?? ''));
        if ($key === '' || isset($history[$key])) continue;
        $label = text_substr(trim((string)($row['business_shift_label'] ?? '')), 0, 60);
        $history[$key] = $label !== '' ? $label : 'شیفت ثبت‌شده';
    }

    // In a currently single-shift café, do not expose a redundant filter unless
    // the selected historical range actually contains multiple shift identities.
    if (count($current) < 2 && count($history) < 2) return ['all'=>'کل روز'];

    $keys = count($current) >= 2 ? $current : [];
    foreach ($history as $key => $label) {
        if (!isset($keys[$key])) $keys[$key] = $label;
    }
    if (count($keys) < 2) return ['all'=>'کل روز'];

    $options = ['all'=>'کل روز'] + $keys;
    if ($includeCompare) $options['compare'] = 'مقایسه شیفت‌ها';
    return $options;
}

/** Returns selectable shift identities (without all/compare pseudo-options). */
function business_report_shift_identities(array $options): array
{
    $out = [];
    foreach ($options as $key => $label) {
        if (in_array((string)$key, ['all','compare','outside'], true)) continue;
        $out[(string)$key] = (string)$label;
    }
    return $out;
}

/**
 * Counts records that were explicitly snapshotted outside configured shifts.
 * These are shown as an exception instead of a permanent filter option.
 */
function business_outside_activity_counts(string $fromDate, string $toDate): array
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
        throw new RuntimeException('بازه روز عملیاتی معتبر نیست.');
    }
    $pdo = db();
    $queries = [
        'orders' => "SELECT COUNT(*) FROM orders WHERE business_date>=? AND business_date<=? AND business_shift_key='outside'",
        'calls' => "SELECT COUNT(*) FROM waiter_calls WHERE business_date>=? AND business_date<=? AND business_shift_key='outside'",
        'sessions' => "SELECT COUNT(*) FROM table_sessions WHERE business_date>=? AND business_date<=? AND business_shift_key='outside'",
        'settlements' => "SELECT COUNT(*) FROM settlement_records WHERE business_date>=? AND business_date<=? AND business_shift_key='outside'",
    ];
    $out = ['orders'=>0,'calls'=>0,'sessions'=>0,'settlements'=>0,'total'=>0];
    foreach ($queries as $key => $sql) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fromDate,$toDate]);
        $out[$key] = (int)$stmt->fetchColumn();
        $out['total'] += $out[$key];
    }
    return $out;
}

/** True when an active table session belongs to an earlier business day. */
function business_session_is_carryover(array $session, ?string $currentBusinessDate = null): bool
{
    $currentBusinessDate ??= business_current_date();
    $sessionDate = trim((string)($session['business_date'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sessionDate)) {
        throw new RuntimeException('Snapshot روز عملیاتی نشست معتبر نیست.');
    }
    return $sessionDate < $currentBusinessDate;
}
