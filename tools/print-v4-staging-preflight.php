<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
if (!is_file($root . '/config.php')) {
    fwrite(STDERR, "Print v4 baseline preflight requires the environment-local config.php.\n");
    exit(2);
}

require $root . '/bootstrap.php';
require_once $root . '/includes/printing.php';

/** @return int */
function sokna_print_preflight_count(PDO $pdo, string $sql): int
{
    $value = $pdo->query($sql)->fetchColumn();
    return (int)($value === false ? 0 : $value);
}

/** @return bool */
function sokna_print_preflight_table(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() === 1;
}

/** @return bool */
function sokna_print_preflight_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() === 1;
}

try {
    $pdo = db();
    $hasAttempts = sokna_print_preflight_table($pdo, 'print_attempts');
    $hasClaimLedger = sokna_print_preflight_table($pdo, 'print_claim_requests');
    $hasSnapshot = $hasAttempts && sokna_print_preflight_column($pdo, 'print_attempts', 'destination_snapshot_json');

    $checks = [
        'print_attempts_table' => $hasAttempts,
        'print_claim_requests_table' => $hasClaimLedger,
        'destination_snapshot_column' => $hasSnapshot,
    ];

    $metrics = [];
    if (sokna_print_preflight_table($pdo, 'print_agents')) {
        $metrics['active_agents'] = sokna_print_preflight_count($pdo,
            "SELECT COUNT(*) FROM print_agents WHERE active=1"
        );
    }

    if (sokna_print_preflight_table($pdo, 'print_jobs')) {
        $metrics['unresolved_ambiguous_jobs'] = sokna_print_preflight_count($pdo,
            "SELECT COUNT(*) FROM print_jobs WHERE status IN('unknown','recovery_hold') AND resolved_at IS NULL"
        );
        if ($hasAttempts) {
            $metrics['inflight_jobs_without_attempt'] = sokna_print_preflight_count($pdo,
                "SELECT COUNT(*) FROM print_jobs j LEFT JOIN print_attempts a ON a.job_id=j.id AND a.state IN('reserved','claimed','started') WHERE j.status IN('reserved','claimed') AND a.id IS NULL"
            );
        }
    }

    if ($hasAttempts) {
        $metrics['active_attempts'] = sokna_print_preflight_count($pdo,
            "SELECT COUNT(*) FROM print_attempts WHERE state IN('reserved','claimed','started')"
        );
        if ($hasSnapshot) {
            $metrics['active_attempts_missing_destination_snapshot'] = sokna_print_preflight_count($pdo,
                "SELECT COUNT(*) FROM print_attempts WHERE state IN('reserved','claimed','started') AND destination_snapshot_json IS NULL"
            );
        }
        $metrics['stale_claimed_or_started_attempts_120s'] = sokna_print_preflight_count($pdo,
            "SELECT COUNT(*) FROM print_attempts a JOIN print_jobs j ON j.id=a.job_id WHERE a.state IN('claimed','started') AND j.resolved_at IS NULL AND j.claimed_at IS NOT NULL AND j.claimed_at<DATE_SUB(NOW(),INTERVAL 120 SECOND)"
        );
    }

    if ($hasClaimLedger) {
        $metrics['claim_request_rows_total'] = sokna_print_preflight_count($pdo, 'SELECT COUNT(*) FROM print_claim_requests');
        $metrics['claim_request_rows_24h'] = sokna_print_preflight_count($pdo, "SELECT COUNT(*) FROM print_claim_requests WHERE created_at>=DATE_SUB(NOW(),INTERVAL 1 DAY)");
        $metrics['claim_request_rows_7d'] = sokna_print_preflight_count($pdo, "SELECT COUNT(*) FROM print_claim_requests WHERE created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)");
        $metrics['empty_claim_rows_total'] = sokna_print_preflight_count($pdo, "SELECT COUNT(*) FROM print_claim_requests WHERE attempt_ids_json IS NOT NULL AND JSON_LENGTH(attempt_ids_json)=0");
        $metrics['empty_claim_rows_expired_7d'] = sokna_print_preflight_count($pdo, "SELECT COUNT(*) FROM print_claim_requests WHERE created_at<DATE_SUB(NOW(),INTERVAL 7 DAY) AND attempt_ids_json IS NOT NULL AND JSON_LENGTH(attempt_ids_json)=0");
    }

    $blockers = [];
    foreach (['print_attempts_table','print_claim_requests_table','destination_snapshot_column'] as $key) {
        if (empty($checks[$key])) $blockers[] = $key . '_missing';
    }
    foreach (['inflight_jobs_without_attempt','active_attempts_missing_destination_snapshot'] as $key) {
        if (($metrics[$key] ?? 0) > 0) $blockers[] = $key;
    }

    echo json_encode([
        'success' => true,
        'mode' => 'read_only',
        'checks' => $checks,
        'metrics' => $metrics,
        'baseline_blockers' => $blockers,
        'ready_for_v4_baseline' => count($blockers) === 0,
        'note' => 'این خروجی فقط Preflight است و جای تست authenticated staging یا Printer واقعی را نمی‌گیرد.',
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
} catch (Throwable $e) {
    // Do not echo DSN, credentials, SQL details or raw exception messages.
    fwrite(STDERR, "Print v4 baseline preflight failed. Check server/database logs locally.\n");
    exit(1);
}
