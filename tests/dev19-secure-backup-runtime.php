<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/maintenance.php';

function dev19_expect_failure(callable $fn, string $label): void {
    try { $fn(); } catch (RuntimeException) { return; }
    throw new RuntimeException($label . ' unexpectedly succeeded.');
}

if (!maintenance_secure_backup_supported()) throw new RuntimeException('Sodium secure-backup support is unavailable in this test runtime.');
maintenance_secure_backup_validate_passphrase('رمز بازیابی فارسی امن ۱۴۰۵');
dev19_expect_failure(fn() => maintenance_secure_backup_validate_passphrase('رمزکوتاه'), 'short Persian passphrase');
$dir = sys_get_temp_dir() . '/sokna-secure-backup-' . bin2hex(random_bytes(6));
if (!mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('Test directory was not created.');
try {
    $source = $dir . '/source.tar.gz';
    $encrypted = $dir . '/source.skb';
    $restored = $dir . '/restored.tar.gz';
    $sentinel = 'TOP-SECRET-APP-KEY-' . bin2hex(random_bytes(16));
    $payload = str_repeat("sokna-archive-data\n", 30000) . $sentinel . random_bytes(1200000);
    file_put_contents($source, $payload, LOCK_EX);
    $sourceHash = hash_file('sha256', $source);
    $passphrase = 'Sokna recovery phrase 1405!';

    $meta = maintenance_secure_backup_encrypt_file($source, $encrypted, $passphrase);
    if (($meta['format'] ?? '') !== MAINTENANCE_SECURE_BACKUP_FORMAT) throw new RuntimeException('Secure format metadata is missing.');
    if (!maintenance_secure_backup_is_file($encrypted) || maintenance_secure_backup_is_file($source)) throw new RuntimeException('Secure envelope detection failed.');
    if (str_contains((string)file_get_contents($encrypted), $sentinel)) throw new RuntimeException('Plaintext sentinel leaked into encrypted export.');

    maintenance_secure_backup_decrypt_file($encrypted, $restored, $passphrase);
    if (!hash_equals((string)$sourceHash, (string)hash_file('sha256', $restored))) throw new RuntimeException('Secure backup roundtrip hash mismatch.');

    $wrongOut = $dir . '/wrong.tar.gz';
    dev19_expect_failure(fn() => maintenance_secure_backup_decrypt_file($encrypted, $wrongOut, 'wrong password phrase 123456'), 'wrong passphrase');
    if (is_file($wrongOut)) throw new RuntimeException('Wrong passphrase left plaintext output.');

    $tampered = $dir . '/tampered.skb';
    copy($encrypted, $tampered);
    $h = fopen($tampered, 'r+b');
    fseek($h, -30, SEEK_END); $byte = fread($h, 1); fseek($h, -1, SEEK_CUR); fwrite($h, chr(ord((string)$byte) ^ 1)); fclose($h);
    $tamperedOut = $dir . '/tampered.tar.gz';
    dev19_expect_failure(fn() => maintenance_secure_backup_decrypt_file($tampered, $tamperedOut, $passphrase), 'tampered ciphertext');
    if (is_file($tamperedOut)) throw new RuntimeException('Tampered ciphertext left plaintext output.');

    $truncated = $dir . '/truncated.skb';
    $raw = (string)file_get_contents($encrypted);
    file_put_contents($truncated, substr($raw, 0, -10), LOCK_EX);
    $truncatedOut = $dir . '/truncated.tar.gz';
    dev19_expect_failure(fn() => maintenance_secure_backup_decrypt_file($truncated, $truncatedOut, $passphrase), 'truncated ciphertext');
    if (is_file($truncatedOut)) throw new RuntimeException('Truncated ciphertext left plaintext output.');

    echo "dev19 secure backup runtime PASS: authenticated roundtrip, no plaintext sentinel, wrong-pass/tamper/truncation fail closed.\n";
} finally {
    foreach (glob($dir . '/*') ?: [] as $file) @unlink($file);
    @rmdir($dir);
}
