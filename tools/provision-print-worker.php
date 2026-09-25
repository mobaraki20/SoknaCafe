#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(64);
}

$root = dirname(__DIR__);
$outputFile = '';
foreach (array_slice($argv, 1) as $arg) {
    $prefix = '--output-file=';
    if (str_starts_with((string)$arg, $prefix)) {
        $outputFile = substr((string)$arg, strlen($prefix));
    }
}
if ($outputFile === '') {
    fwrite(STDERR, "--output-file is required\n");
    exit(64);
}

try {
    require $root . '/bootstrap.php';
    require_once $root . '/includes/setup_install.php';
    if (!is_file($root . '/config.php') || !is_file($root . '/install.lock')) {
        throw new RuntimeException('Repair pairing requires an installed SOKNA Local instance.');
    }
    $cfg = config();
    $hostname = strtolower(trim((string)($cfg['app']['local_hostname'] ?? 'sokna.local'))) ?: 'sokna.local';
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = sokna_setup_write_internal_print_worker_provision($pdo, [
            'print_worker_provision_file' => $outputFile,
            'local_hostname' => $hostname,
        ]);
        if (!$result) throw new RuntimeException('Print Worker provisioning was not produced.');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    echo json_encode([
        'ok' => true,
        'component' => 'sokna-print-worker',
        'agent_id' => (int)$result['agent_id'],
        'provision_file_written' => true,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(2);
}
