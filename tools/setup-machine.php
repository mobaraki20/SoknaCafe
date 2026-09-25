#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(64);
}

$root = dirname(__DIR__);
require_once $root . '/includes/setup_install.php';

$validateOnly = in_array('--validate-only', $argv, true);
$args = ['mode'=>'','config_file'=>'','recovery_file'=>'','passphrase_file'=>''];
foreach (array_slice($argv, 1) as $arg) {
    foreach (['mode','config-file','recovery-file','passphrase-file'] as $name) {
        $prefix = '--' . $name . '=';
        if (str_starts_with((string)$arg, $prefix)) {
            $args[str_replace('-', '_', $name)] = substr((string)$arg, strlen($prefix));
        }
    }
}

$mode = strtolower(trim((string)$args['mode']));
if (!in_array($mode, ['new','recover'], true)) {
    fwrite(STDERR, "--mode=new|recover is required\n");
    exit(64);
}

$configFile = (string)$args['config_file'];
if ($configFile === '' || !is_file($configFile) || !is_readable($configFile)) {
    fwrite(STDERR, "Secure setup config file is required.\n");
    exit(64);
}
if ((int)(filesize($configFile) ?: 0) > 1024 * 1024) {
    fwrite(STDERR, "Setup config is too large.\n");
    exit(64);
}

try {
    $input = json_decode((string)file_get_contents($configFile), true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($input)) throw new RuntimeException('Setup config JSON is invalid.');

    if ($validateOnly) {
        sokna_setup_preflight($input, $mode, $root);
        echo json_encode(['ok'=>true,'mode'=>$mode,'validation_only'=>true]) . PHP_EOL;
        exit(0);
    }

    if ($mode === 'new') {
        $result = sokna_setup_fresh_install($input, $root);
        $GLOBALS['config'] = $result['config'];
        require_once $root . '/includes/observability.php';
        require_once $root . '/includes/installation_identity.php';
        $identity = sokna_installation_identity_ensure();
        echo json_encode(
            ['ok'=>true,'mode'=>'new','installation_identity'=>$identity],
            JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
        ) . PHP_EOL;
        exit(0);
    }

    $recoveryFile = (string)$args['recovery_file'];
    if ($recoveryFile === '' || !is_file($recoveryFile) || !is_readable($recoveryFile)) {
        throw new RuntimeException('Recovery Set file is required.');
    }

    $passphrase = '';
    if ((string)$args['passphrase_file'] !== '') {
        $passphraseFile = (string)$args['passphrase_file'];
        if (!is_file($passphraseFile) || !is_readable($passphraseFile) || (int)(filesize($passphraseFile) ?: 0) > 8192) {
            throw new RuntimeException('Recovery passphrase file is invalid.');
        }
        $passphrase = rtrim((string)file_get_contents($passphraseFile), "\r\n");
    }

    $prepared = sokna_setup_prepare_recovery_target($input, $root);
    $GLOBALS['config'] = $prepared['config'];

    require $root . '/bootstrap.php';
    $freshIdentity = sokna_installation_identity_ensure();

    $restore = maintenance_restore_external_backup_to_empty_target($recoveryFile, $passphrase);

    // Recovery restores the authoritative print tables first; only then rotate/provision
    // the internal worker credential so the recovered queue ownership remains coherent.
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $printWorkerProvision = sokna_setup_write_internal_print_worker_provision($pdo, $input);
        $pdo->commit();
    } catch (Throwable $provisionError) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $provisionError;
    }
    sokna_setup_write_lock($root);

    echo json_encode(
        ['ok'=>true,'mode'=>'recover','installation_identity'=>$freshIdentity,'restore'=>$restore],
        JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
    ) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(2);
}
