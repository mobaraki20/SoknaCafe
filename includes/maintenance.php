<?php
declare(strict_types=1);

/**
 * Backup, restore and maintenance helpers.
 * Archives use tar.gz so the feature works without the optional PHP zip extension.
 */

const MAINTENANCE_AUTO_BACKUP_HOURS = 6;
const MAINTENANCE_BACKUP_KEEP = 28;
const MAINTENANCE_RECOVERY_POINT_KEEP = 5;
const MAINTENANCE_WORKER_HEARTBEAT_HOURS = 12;
const MAINTENANCE_OFFSERVER_REMINDER_DAYS = 7;
const MAINTENANCE_SECURE_BACKUP_FORMAT = 'sokna-secure-backup-v1';
const MAINTENANCE_SECURE_BACKUP_MAGIC = "SOKNA-SKB1\n";
const MAINTENANCE_SECURE_BACKUP_CHUNK_BYTES = 1048576;
const MAINTENANCE_SECURE_BACKUP_MAX_PLAINTEXT_BYTES = 2147483648;
const MAINTENANCE_SECURE_BACKUP_KDF_OPSLIMIT = 3;
const MAINTENANCE_SECURE_BACKUP_KDF_MEMLIMIT = 67108864;
const MAINTENANCE_SECURE_BACKUP_MIN_PASSPHRASE_CHARS = 12;
const MAINTENANCE_SECURE_BACKUP_MAX_PASSPHRASE_CHARS = 256;
const MAINTENANCE_SECURE_BACKUP_MAX_PASSPHRASE_BYTES = 2048;


function maintenance_root(): string
{
    return defined('SOKNA_MAINTENANCE_ROOT') ? rtrim((string)constant('SOKNA_MAINTENANCE_ROOT'), DIRECTORY_SEPARATOR . '/') : dirname(__DIR__);
}

function maintenance_storage_dir(): string
{
    return maintenance_root() . '/storage';
}

function maintenance_backup_dir(): string
{
    return maintenance_storage_dir() . '/backups';
}

function maintenance_tmp_dir(): string
{
    return maintenance_storage_dir() . '/tmp';
}

function maintenance_ensure_storage(): void
{
    $uploadDir = maintenance_root() . '/uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('پوشه تصاویر سامانه ساخته نشد: ' . $uploadDir);
    }
    $uploadGuard = "Options -Indexes\n<FilesMatch \"\\.(?:php|phtml|phar|cgi|pl|py|sh|exe|bat|cmd)$\">\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n</FilesMatch>\n";
    if (!is_file($uploadDir . '/.htaccess')) @file_put_contents($uploadDir . '/.htaccess', $uploadGuard, LOCK_EX);
    if (!is_file($uploadDir . '/index.html')) @file_put_contents($uploadDir . '/index.html', '', LOCK_EX);
    foreach ([maintenance_storage_dir(), maintenance_backup_dir(), maintenance_tmp_dir()] as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('پوشه امن نگهداری سامانه ساخته نشد: ' . $dir);
        }
    }
    $deny = "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
    foreach ([maintenance_storage_dir(), maintenance_backup_dir(), maintenance_tmp_dir()] as $dir) {
        $file = $dir . '/.htaccess';
        if (!is_file($file)) @file_put_contents($file, $deny, LOCK_EX);
        $index = $dir . '/index.html';
        if (!is_file($index)) @file_put_contents($index, '', LOCK_EX);
    }
}

function maintenance_version(): string
{
    $version = trim((string)@file_get_contents(maintenance_root() . '/VERSION.txt'));
    return $version !== '' ? $version : 'unknown';
}

function maintenance_safe_backup_name(string $name): bool
{
    return (bool)preg_match('/^sokna-backup-\d{8}-\d{6}-[a-f0-9]{8}\.tar\.gz$/', $name);
}

function maintenance_safe_recovery_point_name(string $name): bool
{
    return (bool)preg_match('/^sokna-recovery-point-\d{8}-\d{6}-[a-f0-9]{8}\.tar\.gz$/', $name);
}

/**
 * Portable off-server backup envelope.
 *
 * Internal server backups stay as validated tar.gz files inside protected storage so
 * scheduled backup/restore remains one-owner and recovery does not depend on a key
 * stored beside the data. A downloaded copy is wrapped in an authenticated,
 * passphrase-derived secretstream container instead.
 */
function maintenance_secure_backup_supported(): bool
{
    return extension_loaded('sodium')
        && function_exists('sodium_crypto_pwhash')
        && function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_push')
        && function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_pull');
}

function maintenance_secure_backup_require_supported(): void
{
    if (!maintenance_secure_backup_supported()) {
        throw new RuntimeException('افزونه Sodium برای خروجی و بازیابی امن پشتیبان لازم است.');
    }
}

function maintenance_secure_backup_validate_passphrase(string $passphrase): void
{
    if ($passphrase === '' || str_contains($passphrase, "\0") || !preg_match('//u', $passphrase)) {
        throw new RuntimeException('رمز بازیابی شامل نویسه نامعتبر است.');
    }
    $characters = preg_match_all('/./us', $passphrase, $matches);
    $bytes = strlen($passphrase);
    if (!is_int($characters) || $characters < MAINTENANCE_SECURE_BACKUP_MIN_PASSPHRASE_CHARS) {
        throw new RuntimeException('رمز بازیابی باید حداقل ۱۲ نویسه داشته باشد.');
    }
    if ($characters > MAINTENANCE_SECURE_BACKUP_MAX_PASSPHRASE_CHARS || $bytes > MAINTENANCE_SECURE_BACKUP_MAX_PASSPHRASE_BYTES) {
        throw new RuntimeException('رمز بازیابی بیش از حد طولانی است.');
    }
}

function maintenance_secure_backup_write_all($stream, string $data): void
{
    $length = strlen($data);
    $offset = 0;
    while ($offset < $length) {
        $written = fwrite($stream, substr($data, $offset));
        if ($written === false || $written < 1) throw new RuntimeException('نوشتن فایل پشتیبان امن کامل نشد.');
        $offset += $written;
    }
}

function maintenance_secure_backup_read_exact($stream, int $length): string
{
    if ($length < 0) throw new InvalidArgumentException('طول خواندن معتبر نیست.');
    $result = '';
    while (strlen($result) < $length) {
        $chunk = fread($stream, $length - strlen($result));
        if ($chunk === false) throw new RuntimeException('خواندن فایل پشتیبان امن انجام نشد.');
        if ($chunk === '') throw new RuntimeException('فایل پشتیبان امن ناقص است.');
        $result .= $chunk;
    }
    return $result;
}

function maintenance_secure_backup_derive_key(string $passphrase, string $salt, int $opslimit, int $memlimit): string
{
    maintenance_secure_backup_require_supported();
    return sodium_crypto_pwhash(
        SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES,
        $passphrase,
        $salt,
        $opslimit,
        $memlimit,
        SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
    );
}

function maintenance_secure_backup_export_name(string $backupName): string
{
    if (!maintenance_safe_backup_name($backupName)) throw new RuntimeException('نام فایل پشتیبان معتبر نیست.');
    $name = preg_replace('/^sokna-backup-/', 'sokna-secure-backup-', $backupName, 1);
    return preg_replace('/\\.tar\\.gz$/', '.skb', (string)$name, 1) ?: 'sokna-secure-backup.skb';
}

function maintenance_secure_backup_is_file(string $path): bool
{
    if (!is_file($path) || !is_readable($path)) return false;
    $handle = @fopen($path, 'rb');
    if (!$handle) return false;
    try {
        $magic = fread($handle, strlen(MAINTENANCE_SECURE_BACKUP_MAGIC));
        return is_string($magic) && hash_equals(MAINTENANCE_SECURE_BACKUP_MAGIC, $magic);
    } finally {
        fclose($handle);
    }
}

/** Encrypt one already-validated internal archive into a portable .skb container. */
function maintenance_secure_backup_encrypt_file(string $sourcePath, string $destinationPath, string $passphrase): array
{
    maintenance_secure_backup_require_supported();
    maintenance_secure_backup_validate_passphrase($passphrase);
    if (!is_file($sourcePath) || !is_readable($sourcePath)) throw new RuntimeException('فایل پشتیبان برای رمزنگاری قابل خواندن نیست.');
    $sourceSize = (int)(filesize($sourcePath) ?: 0);
    if ($sourceSize < 1 || $sourceSize > MAINTENANCE_SECURE_BACKUP_MAX_PLAINTEXT_BYTES) throw new RuntimeException('حجم فایل پشتیبان برای خروج امن معتبر نیست.');
    if (is_file($destinationPath)) throw new RuntimeException('فایل موقت خروج امن از قبل وجود دارد.');

    $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
    $key = maintenance_secure_backup_derive_key($passphrase, $salt, MAINTENANCE_SECURE_BACKUP_KDF_OPSLIMIT, MAINTENANCE_SECURE_BACKUP_KDF_MEMLIMIT);
    [$state, $streamHeader] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
    $header = [
        'format' => MAINTENANCE_SECURE_BACKUP_FORMAT,
        'format_version' => 1,
        'cipher' => 'xchacha20poly1305-secretstream',
        'kdf' => 'argon2id13',
        'kdf_opslimit' => MAINTENANCE_SECURE_BACKUP_KDF_OPSLIMIT,
        'kdf_memlimit' => MAINTENANCE_SECURE_BACKUP_KDF_MEMLIMIT,
        'salt_b64' => base64_encode($salt),
        'stream_header_b64' => base64_encode($streamHeader),
        'chunk_bytes' => MAINTENANCE_SECURE_BACKUP_CHUNK_BYTES,
        'source_size' => $sourceSize,
        'created_at' => gmdate('Y-m-d\\TH:i:s\\Z'),
    ];
    $headerJson = json_encode($header, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (strlen($headerJson) > 65535) throw new RuntimeException('سرآیند پشتیبان امن معتبر نیست.');
    $aad = MAINTENANCE_SECURE_BACKUP_MAGIC . $headerJson;

    $input = fopen($sourcePath, 'rb');
    $output = fopen($destinationPath, 'xb');
    if (!$input || !$output) {
        if (is_resource($input)) fclose($input);
        if (is_resource($output)) fclose($output);
        @unlink($destinationPath);
        sodium_memzero($key);
        sodium_memzero($state);
        throw new RuntimeException('فایل موقت پشتیبان امن ساخته نشد.');
    }

    try {
        maintenance_secure_backup_write_all($output, MAINTENANCE_SECURE_BACKUP_MAGIC);
        maintenance_secure_backup_write_all($output, pack('N', strlen($headerJson)));
        maintenance_secure_backup_write_all($output, $headerJson);
        $remaining = $sourceSize;
        while ($remaining > 0) {
            $plainLength = min(MAINTENANCE_SECURE_BACKUP_CHUNK_BYTES, $remaining);
            $plain = maintenance_secure_backup_read_exact($input, $plainLength);
            $remaining -= $plainLength;
            $tag = $remaining === 0
                ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
                : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
            $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($state, $plain, $aad, $tag);
            maintenance_secure_backup_write_all($output, pack('N', strlen($cipher)));
            maintenance_secure_backup_write_all($output, $cipher);
        }
        if (function_exists('fsync')) @fsync($output);
    } catch (Throwable $e) {
        fclose($input);
        fclose($output);
        @unlink($destinationPath);
        sodium_memzero($key);
        sodium_memzero($state);
        throw $e;
    }
    fclose($input);
    fclose($output);
    @chmod($destinationPath, 0640);
    sodium_memzero($key);
    sodium_memzero($state);
    return [
        'format' => MAINTENANCE_SECURE_BACKUP_FORMAT,
        'path' => $destinationPath,
        'size' => (int)(filesize($destinationPath) ?: 0),
        'sha256' => hash_file('sha256', $destinationPath) ?: '',
    ];
}

/** Decrypt a .skb envelope. Authentication must complete before the archive is trusted. */
function maintenance_secure_backup_decrypt_file(string $sourcePath, string $destinationPath, string $passphrase): array
{
    maintenance_secure_backup_require_supported();
    maintenance_secure_backup_validate_passphrase($passphrase);
    if (!is_file($sourcePath) || !is_readable($sourcePath)) throw new RuntimeException('فایل پشتیبان امن قابل خواندن نیست.');
    $encryptedSize = (int)(filesize($sourcePath) ?: 0);
    $maxEncrypted = MAINTENANCE_SECURE_BACKUP_MAX_PLAINTEXT_BYTES + (4 * 1024 * 1024);
    if ($encryptedSize < strlen(MAINTENANCE_SECURE_BACKUP_MAGIC) + 4 || $encryptedSize > $maxEncrypted) throw new RuntimeException('حجم فایل پشتیبان امن معتبر نیست.');
    if (is_file($destinationPath)) throw new RuntimeException('فایل موقت بازیابی از قبل وجود دارد.');

    $input = fopen($sourcePath, 'rb');
    if (!$input) throw new RuntimeException('فایل پشتیبان امن باز نشد.');
    $output = null;
    $key = null;
    $state = null;
    try {
        $magic = maintenance_secure_backup_read_exact($input, strlen(MAINTENANCE_SECURE_BACKUP_MAGIC));
        if (!hash_equals(MAINTENANCE_SECURE_BACKUP_MAGIC, $magic)) throw new RuntimeException('قالب فایل پشتیبان امن شناخته‌شده نیست.');
        $headerLengthRaw = maintenance_secure_backup_read_exact($input, 4);
        $headerLength = unpack('Nlength', $headerLengthRaw)['length'] ?? 0;
        if (!is_int($headerLength) || $headerLength < 2 || $headerLength > 65535) throw new RuntimeException('سرآیند فایل پشتیبان امن معتبر نیست.');
        $headerJson = maintenance_secure_backup_read_exact($input, $headerLength);
        $header = json_decode($headerJson, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($header)
            || ($header['format'] ?? '') !== MAINTENANCE_SECURE_BACKUP_FORMAT
            || ($header['format_version'] ?? null) !== 1
            || ($header['cipher'] ?? '') !== 'xchacha20poly1305-secretstream'
            || ($header['kdf'] ?? '') !== 'argon2id13'
            || (int)($header['kdf_opslimit'] ?? 0) !== MAINTENANCE_SECURE_BACKUP_KDF_OPSLIMIT
            || (int)($header['kdf_memlimit'] ?? 0) !== MAINTENANCE_SECURE_BACKUP_KDF_MEMLIMIT
            || (int)($header['chunk_bytes'] ?? 0) !== MAINTENANCE_SECURE_BACKUP_CHUNK_BYTES) {
            throw new RuntimeException('پارامترهای فایل پشتیبان امن پشتیبانی نمی‌شوند.');
        }
        $sourceSize = (int)($header['source_size'] ?? 0);
        if ($sourceSize < 1 || $sourceSize > MAINTENANCE_SECURE_BACKUP_MAX_PLAINTEXT_BYTES) throw new RuntimeException('اندازه اصلی پشتیبان امن معتبر نیست.');
        $salt = base64_decode((string)($header['salt_b64'] ?? ''), true);
        $streamHeader = base64_decode((string)($header['stream_header_b64'] ?? ''), true);
        if (!is_string($salt) || strlen($salt) !== SODIUM_CRYPTO_PWHASH_SALTBYTES
            || !is_string($streamHeader) || strlen($streamHeader) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES) {
            throw new RuntimeException('پارامترهای رمزنگاری پشتیبان امن معتبر نیستند.');
        }
        $key = maintenance_secure_backup_derive_key($passphrase, $salt, MAINTENANCE_SECURE_BACKUP_KDF_OPSLIMIT, MAINTENANCE_SECURE_BACKUP_KDF_MEMLIMIT);
        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($streamHeader, $key);
        $aad = MAINTENANCE_SECURE_BACKUP_MAGIC . $headerJson;
        $output = fopen($destinationPath, 'xb');
        if (!$output) throw new RuntimeException('فضای موقت برای بازیابی امن ساخته نشد.');

        $plainBytes = 0;
        $sawFinal = false;
        while (!$sawFinal) {
            $lengthRaw = fread($input, 4);
            if ($lengthRaw === false) throw new RuntimeException('خواندن قاب پشتیبان امن انجام نشد.');
            if ($lengthRaw === '') throw new RuntimeException('پشتیبان امن پیش از قاب نهایی تمام شده است.');
            if (strlen($lengthRaw) < 4) $lengthRaw .= maintenance_secure_backup_read_exact($input, 4 - strlen($lengthRaw));
            $cipherLength = unpack('Nlength', $lengthRaw)['length'] ?? 0;
            $maxCipher = MAINTENANCE_SECURE_BACKUP_CHUNK_BYTES + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;
            if (!is_int($cipherLength) || $cipherLength < SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES || $cipherLength > $maxCipher) {
                throw new RuntimeException('اندازه قاب پشتیبان امن معتبر نیست.');
            }
            $cipher = maintenance_secure_backup_read_exact($input, $cipherLength);
            $pulled = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher, $aad);
            if ($pulled === false) throw new RuntimeException('رمز بازیابی نادرست است یا فایل پشتیبان دست‌کاری شده است.');
            [$plain, $tag] = $pulled;
            if (!in_array($tag, [SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL], true)) {
                throw new RuntimeException('قاب پشتیبان امن نوع پشتیبانی‌نشده دارد.');
            }
            $plainBytes += strlen($plain);
            if ($plainBytes > $sourceSize || $plainBytes > MAINTENANCE_SECURE_BACKUP_MAX_PLAINTEXT_BYTES) throw new RuntimeException('اندازه بازیابی‌شده از حد اعلام‌شده بیشتر است.');
            maintenance_secure_backup_write_all($output, $plain);
            $sawFinal = $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
        }
        if ($plainBytes !== $sourceSize) throw new RuntimeException('اندازه فایل بازیابی‌شده با سرآیند پشتیبان یکسان نیست.');
        $trailing = fread($input, 1);
        if ($trailing === false) throw new RuntimeException('خواندن پایان فایل پشتیبان امن انجام نشد.');
        if ($trailing !== '') throw new RuntimeException('فایل پشتیبان امن پس از قاب نهایی داده اضافه دارد.');
        if (function_exists('fsync')) @fsync($output);
        fclose($output);
        $output = null;
        fclose($input);
        @chmod($destinationPath, 0640);
        if (is_string($key)) sodium_memzero($key);
        if (is_string($state)) sodium_memzero($state);
        return [
            'format' => MAINTENANCE_SECURE_BACKUP_FORMAT,
            'path' => $destinationPath,
            'size' => $plainBytes,
            'sha256' => hash_file('sha256', $destinationPath) ?: '',
        ];
    } catch (Throwable $e) {
        if (is_resource($output)) fclose($output);
        if (is_resource($input)) fclose($input);
        @unlink($destinationPath);
        if (is_string($key)) sodium_memzero($key);
        if (is_string($state)) sodium_memzero($state);
        if ($e instanceof RuntimeException) throw $e;
        throw new RuntimeException('بازکردن پشتیبان امن انجام نشد؛ رمز یا سلامت فایل را بررسی کنید.', 0, $e);
    }
}

function maintenance_secure_backup_create_export(array $backup, string $passphrase): array
{
    $manifest = $backup['manifest'] ?? null;
    if (!is_array($manifest) || ($backup['valid'] ?? null) !== true || ($manifest['type'] ?? '') !== 'backup') {
        throw new RuntimeException('فقط پشتیبان سالم داده قابل خروج امن است.');
    }
    if (($manifest['format'] ?? '') !== 'sokna-backup-v3' || empty($manifest['portable_app_identity'])) {
        throw new RuntimeException('این پشتیبان برای خروج قابل‌انتقال آماده نیست.');
    }
    if (($manifest['version'] ?? '') !== maintenance_version()) throw new RuntimeException('نسخه پشتیبان با نسخه فعلی سامانه یکسان نیست.');
    $name = (string)($backup['name'] ?? '');
    $source = maintenance_backup_path($name);
    maintenance_disk_check((int)(filesize($source) ?: 0));
    maintenance_ensure_storage();
    $downloadName = maintenance_secure_backup_export_name($name);
    $destination = maintenance_tmp_dir() . '/secure-export-' . bin2hex(random_bytes(12)) . '.skb';
    $crypto = maintenance_secure_backup_encrypt_file($source, $destination, $passphrase);
    return $crypto + ['download_name' => $downloadName, 'source_backup_name' => $name];
}

function maintenance_backup_id(string $kind): string
{
    if (!in_array($kind, ['backup', 'recovery_point'], true)) throw new InvalidArgumentException('نوع داخلی پشتیبان معتبر نیست.');
    $prefix = $kind === 'backup' ? 'sokna-backup' : 'sokna-recovery-point';
    return sprintf('%s-%s-%s.tar.gz', $prefix, date('Ymd-His'), bin2hex(random_bytes(4)));
}

function maintenance_archive_entry_is_safe(string $path): bool
{
    $path = str_replace('\\', '/', $path);
    return $path !== ''
        && $path[0] !== '/'
        && !preg_match('#(^|/)\.\.(/|$)#', $path)
        && !str_contains($path, "\0");
}

function maintenance_sql_statements(string $sql): array
{
    if (str_starts_with($sql, "-- CAFE-SQL-FRAMED-V2 --\n")) {
        $offset = strlen("-- CAFE-SQL-FRAMED-V2 --\n");
        $result = [];
        $length = strlen($sql);
        while ($offset < $length) {
            $lineEnd = strpos($sql, "\n", $offset);
            if ($lineEnd === false) break;
            $line = substr($sql, $offset, $lineEnd - $offset);
            $offset = $lineEnd + 1;
            if ($line === '') continue;
            if (!preg_match('/^CAFE-LEN (\d+)$/', $line, $m)) throw new RuntimeException('قالب SQL پشتیبان معتبر نیست.');
            $size = (int)$m[1];
            if ($size < 1 || $offset + $size > $length) throw new RuntimeException('طول Statement پشتیبان معتبر نیست.');
            $result[] = substr($sql, $offset, $size);
            $offset += $size;
            if (($sql[$offset] ?? '') === "\n") $offset++;
        }
        return $result;
    }
    $parts = preg_split('/\R-- CAFE-STMT --\R/', trim($sql));
    return array_values(array_filter(array_map('trim', $parts ?: []), static fn(string $part): bool => $part !== ''));
}

function maintenance_sql_value(PDO $pdo, mixed $value): string
{
    if ($value === null) return 'NULL';
    if (is_bool($value)) return $value ? '1' : '0';
    return $pdo->quote((string)$value);
}

function maintenance_database_dump_write(PDO $pdo, callable $write): void
{
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
    }
    $emit = static function (string $statement) use ($write): void {
        $statement = trim($statement);
        if ($statement === '') return;
        $write('CAFE-LEN ' . strlen($statement) . "\n" . $statement . "\n");
    };
    try {
        $write("-- CAFE-SQL-FRAMED-V2 --\n");
        $emit('SET FOREIGN_KEY_CHECKS=0;');
        $tableRows = $pdo->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
        foreach ($tableRows as $tableRow) {
            $table = (string)($tableRow[0] ?? '');
            if ($table === '' || !preg_match('/^[A-Za-z0-9_]+$/', $table)) continue;
            $quotedTable = '`' . str_replace('`', '``', $table) . '`';
            $createRow = $pdo->query('SHOW CREATE TABLE ' . $quotedTable)->fetch(PDO::FETCH_NUM);
            $createSql = (string)($createRow[1] ?? '');
            if ($createSql === '') throw new RuntimeException('ساختار جدول ' . $table . ' خوانده نشد.');
            $emit('DROP TABLE IF EXISTS ' . $quotedTable . ';');
            $emit($createSql . ';');
            $columnRows = $pdo->query('SHOW FULL COLUMNS FROM ' . $quotedTable)->fetchAll(PDO::FETCH_ASSOC);
            $columns = [];
            foreach ($columnRows as $columnRow) {
                if (str_contains(strtoupper((string)($columnRow['Extra'] ?? '')), 'GENERATED')) continue;
                $column = (string)($columnRow['Field'] ?? '');
                if ($column !== '') $columns[] = $column;
            }
            if (!$columns) throw new RuntimeException('ستون‌های قابل بازیابی جدول ' . $table . ' پیدا نشد.');
            $columnSql = implode(',', array_map(static fn(string $column): string => '`' . str_replace('`', '``', $column) . '`', $columns));
            $statementOptions = [];
            if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) $statementOptions[(int)constant('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')] = false;
            $stmt = $pdo->prepare('SELECT ' . $columnSql . ' FROM ' . $quotedTable, $statementOptions);
            $stmt->execute(); $batch = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $batch[] = '(' . implode(',', array_map(static fn(mixed $value): string => maintenance_sql_value($pdo, $value), array_values($row))) . ')';
                if (count($batch) >= 100) { $emit('INSERT INTO ' . $quotedTable . ' (' . $columnSql . ') VALUES ' . implode(',', $batch) . ';'); $batch = []; }
            }
            $stmt->closeCursor();
            if ($batch) $emit('INSERT INTO ' . $quotedTable . ' (' . $columnSql . ') VALUES ' . implode(',', $batch) . ';');
        }
        $emit('SET FOREIGN_KEY_CHECKS=1;');
        if ($ownsTransaction) $pdo->commit();
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function maintenance_database_dump(PDO $pdo): string
{
    $stream = fopen('php://temp', 'w+b');
    if ($stream === false) throw new RuntimeException('حافظه موقت برای پشتیبان ساخته نشد.');
    try {
        maintenance_database_dump_write($pdo, static function (string $chunk) use ($stream): void {
            if (fwrite($stream, $chunk) !== strlen($chunk)) throw new RuntimeException('نوشتن داده پشتیبان کامل نشد.');
        });
        rewind($stream);
        $dump = stream_get_contents($stream);
        if ($dump === false) throw new RuntimeException('خواندن داده پشتیبان کامل نشد.');
        return $dump;
    } finally {
        fclose($stream);
    }
}

function maintenance_database_dump_file(PDO $pdo, string $path): array
{
    $stream = fopen($path, 'wb');
    if ($stream === false) throw new RuntimeException('فایل موقت پایگاه داده ساخته نشد.');
    $hash = hash_init('sha256');
    $bytes = 0;
    try {
        maintenance_database_dump_write($pdo, static function (string $chunk) use ($stream, $hash, &$bytes): void {
            $length = strlen($chunk);
            $offset = 0;
            while ($offset < $length) {
                $written = fwrite($stream, substr($chunk, $offset));
                if ($written === false || $written === 0) throw new RuntimeException('نوشتن داده پشتیبان کامل نشد.');
                $offset += $written;
            }
            hash_update($hash, $chunk);
            $bytes += $length;
        });
        if (!fflush($stream)) throw new RuntimeException('ثبت نهایی داده پشتیبان کامل نشد.');
        return ['sha256' => hash_final($hash), 'bytes' => $bytes];
    } catch (Throwable $e) {
        @unlink($path);
        throw $e;
    } finally {
        fclose($stream);
    }
}

function maintenance_recursive_files(string $base, array $excludePrefixes = []): Generator
{
    $base = rtrim(str_replace('\\', '/', $base), '/');
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->isLink()) continue;
        $path = str_replace('\\', '/', $file->getPathname());
        $relative = ltrim(substr($path, strlen($base)), '/');
        $skip = false;
        foreach ($excludePrefixes as $prefix) {
            $prefix = trim(str_replace('\\', '/', $prefix), '/');
            if ($relative === $prefix || str_starts_with($relative, $prefix . '/')) {
                $skip = true;
                break;
            }
        }
        if (!$skip) yield [$path, $relative];
    }
}

function maintenance_disk_check(int $estimatedBytes = 0): void
{
    maintenance_ensure_storage();
    $free = @disk_free_space(maintenance_storage_dir());
    $required = max(50 * 1024 * 1024, $estimatedBytes * 2);
    if ($free !== false && $free < $required) {
        throw new RuntimeException('فضای آزاد سرور برای عملیات امن کافی نیست.');
    }
}

function maintenance_lock(callable $callback): mixed
{
    maintenance_ensure_storage();
    $handle = fopen(maintenance_storage_dir() . '/operations.lock', 'c+');
    if (!$handle || !flock($handle, LOCK_EX | LOCK_NB)) {
        if (is_resource($handle)) fclose($handle);
        throw new RuntimeException('یک عملیات پشتیبان‌گیری، بازیابی یا به‌روزرسانی دیگر در حال اجراست.');
    }
    try {
        return $callback();
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function maintenance_verify_gzip(string $gzipPath, string $expectedUncompressedSha256): bool
{
    $handle = @gzopen($gzipPath, 'rb');
    if (!$handle) return false;
    $context = hash_init('sha256');
    try {
        while (!gzeof($handle)) {
            $chunk = gzread($handle, 1024 * 1024);
            if ($chunk === false) return false;
            hash_update($context, $chunk);
        }
    } finally {
        gzclose($handle);
    }
    return hash_equals($expectedUncompressedSha256, hash_final($context));
}

function maintenance_add_file(PharData $archive, string $realPath, string $archivePath, array &$entries): void
{
    if (!maintenance_archive_entry_is_safe($archivePath)) throw new RuntimeException('مسیر فایل پشتیبان امن نیست.');
    $archive->addFile($realPath, $archivePath);
    $entries[] = [
        'path' => $archivePath,
        'size' => filesize($realPath) ?: 0,
        'sha256' => hash_file('sha256', $realPath),
    ];
}

function maintenance_add_string(PharData $archive, string $content, string $archivePath, array &$entries): void
{
    if (!maintenance_archive_entry_is_safe($archivePath)) throw new RuntimeException('مسیر فایل پشتیبان امن نیست.');
    $archive->addFromString($archivePath, $content);
    $entries[] = ['path'=>$archivePath,'size'=>strlen($content),'sha256'=>hash('sha256',$content)];
}

/** The application identity is portable backup state, but DB credentials and server URL are not. */
function maintenance_current_app_key(): string
{
    global $config;
    $key = trim((string)($config['app']['key'] ?? ''));
    if (strlen($key) < 32 || strlen($key) > 256 || preg_match('/[\r\n]/', $key)) {
        throw new RuntimeException('هویت داخلی سامانه برای پشتیبان قابل اتکا نیست.');
    }
    return $key;
}

/** Replace only app.key atomically; environment-specific DB/URL settings remain those of the target server. */
function maintenance_config_set_app_key(string $key): void
{
    $key = trim($key);
    if (strlen($key) < 32 || strlen($key) > 256 || preg_match('/[\r\n]/', $key)) throw new RuntimeException('هویت داخلی پشتیبان معتبر نیست.');
    $path = maintenance_root() . '/config.php';
    if (!is_file($path) || !is_readable($path) || !is_writable(dirname($path))) throw new RuntimeException('فایل تنظیمات سامانه برای بازیابی هویت داخلی در دسترس نیست.');
    $loaded = require $path;
    if (!is_array($loaded) || !is_array($loaded['app'] ?? null) || !is_array($loaded['db'] ?? null)) throw new RuntimeException('ساختار config.php معتبر نیست.');
    $loaded['app']['key'] = $key;
    $tmp = $path . '.tmp-' . bin2hex(random_bytes(5));
    $content = "<?php\nreturn " . var_export($loaded, true) . ";\n";
    if (file_put_contents($tmp, $content, LOCK_EX) === false) throw new RuntimeException('ثبت هویت داخلی بازیابی‌شده انجام نشد.');
    @chmod($tmp, 0600);
    if (!rename($tmp, $path)) { @unlink($tmp); throw new RuntimeException('جایگزینی امن تنظیمات سامانه انجام نشد.'); }
    @chmod($path, 0600);
    global $config;
    $config = $loaded;
    if (function_exists('clear_setting_cache')) clear_setting_cache();
}

function maintenance_schema_fingerprint(?PDO $pdo = null): string
{
    $pdo ??= db();
    try {
        $versions = $pdo->query('SELECT version FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN);
        return hash('sha256', json_encode(array_values(array_map('strval', $versions)), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    } catch (Throwable) {
        return '';
    }
}

function maintenance_backup_sidecar_path(string $archivePath): string { return $archivePath . '.meta.json'; }
function maintenance_write_backup_sidecar(string $archivePath, array $manifest, ?bool $valid = null, ?string $error = null): void
{
    $data = ['format'=>'sokna-backup-meta-v2','name'=>basename($archivePath),'size'=>is_file($archivePath)?(filesize($archivePath)?:0):0,'modified_at'=>is_file($archivePath)?date(DATE_ATOM,filemtime($archivePath)?:time()):date(DATE_ATOM),'manifest'=>$manifest,'valid'=>$valid,'error'=>$error,'validated_at'=>$valid===true?date(DATE_ATOM):null];
    $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
    $path=maintenance_backup_sidecar_path($archivePath);$tmp=$path.'.tmp-'.bin2hex(random_bytes(3));
    if(file_put_contents($tmp,$json,LOCK_EX)===false||!rename($tmp,$path)){@unlink($tmp);throw new RuntimeException('ثبت اطلاعات سبک پشتیبان انجام نشد.');}
}
function maintenance_read_backup_sidecar(string $archivePath): array
{
    $path=maintenance_backup_sidecar_path($archivePath);if(!is_file($path))return [];
    try{$data=json_decode((string)file_get_contents($path),true,32,JSON_THROW_ON_ERROR);return is_array($data)?$data:[];}catch(Throwable){return [];}
}
function maintenance_estimated_data_bytes(): int
{
    $bytes=0;
    try{$bytes+=(int)(db()->query("SELECT COALESCE(SUM(data_length+index_length),0) FROM information_schema.tables WHERE table_schema=DATABASE()")->fetchColumn()?:0);}catch(Throwable){}
    $uploads=maintenance_root().'/uploads';if(is_dir($uploads))foreach(maintenance_recursive_files($uploads) as [$real])$bytes+=(int)(filesize($real)?:0);
    return max(1,$bytes);
}
function maintenance_job_dir(): string { $d=maintenance_storage_dir().'/maintenance-jobs';if(!is_dir($d)&&!mkdir($d,0750,true)&&!is_dir($d))throw new RuntimeException('پوشه وضعیت عملیات ساخته نشد.');return $d; }
function maintenance_job_start(string $type, array $details=[]): array { $j=['format'=>'sokna-maintenance-job-v2','id'=>bin2hex(random_bytes(12)),'type'=>$type,'status'=>'running','stage'=>'starting','progress'=>1,'message'=>'عملیات شروع شد.','details'=>$details,'created_at'=>date(DATE_ATOM),'updated_at'=>date(DATE_ATOM)];maintenance_job_write($j);return $j; }
function maintenance_job_write(array $job): void { $job['updated_at']=date(DATE_ATOM);$path=maintenance_job_dir().'/job-'.preg_replace('/[^a-f0-9]/','',(string)$job['id']).'.json';$tmp=$path.'.tmp';if(file_put_contents($tmp,json_encode($job,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX)===false||!rename($tmp,$path)){@unlink($tmp);throw new RuntimeException('وضعیت عملیات ثبت نشد.');} }
function maintenance_job_finish(array $job, bool $success, string $message): void { $job['status']=$success?'completed':'failed';$job['stage']=$success?'done':'failed';$job['progress']=100;$job['message']=$message;maintenance_job_write($job); }

function maintenance_create_backup(?int $actorUserId = null): array
{
    return maintenance_lock(function () use ($actorUserId): array {
        $metadata = maintenance_create_backup_unlocked('backup',$actorUserId);
        maintenance_offserver_reminder_ensure_started((string)($metadata['manifest']['created_at'] ?? date(DATE_ATOM)));
        maintenance_prune_backups();
        return $metadata;
    });
}

function maintenance_open_archive(string $path): PharData
{
    if (!is_file($path)) throw new RuntimeException('فایل پشتیبان پیدا نشد.');
    try {
        return new PharData($path);
    } catch (Throwable $e) {
        throw new RuntimeException('فایل پشتیبان قابل خواندن نیست.', 0, $e);
    }
}

function maintenance_archive_string(PharData $archive, string $entry): string
{
    if (!isset($archive[$entry])) throw new RuntimeException('فایل ضروری پشتیبان وجود ندارد: ' . $entry);
    return (string)$archive[$entry]->getContent();
}

/** Resolve a Phar entry path without depending on the client/archive filename. */
function maintenance_archive_entry_path(PharData $archive, SplFileInfo $file): string
{
    $archivePath = str_replace('\\', '/', $archive->getPath());
    $prefix = 'phar://' . $archivePath . '/';
    $full = str_replace('\\', '/', $file->getPathname());
    if (!str_starts_with($full, $prefix)) throw new RuntimeException('مسیر داخلی پشتیبان قابل تأیید نیست.');
    return substr($full, strlen($prefix));
}

function maintenance_read_manifest(string $path): array
{
    $archive = maintenance_open_archive($path);
    $manifest = json_decode(maintenance_archive_string($archive, 'manifest.json'), true, 32, JSON_THROW_ON_ERROR);
    if (($manifest['format'] ?? '') !== 'sokna-backup-v3' || !in_array($manifest['type'] ?? '', ['backup','recovery_point'], true)) {
        throw new RuntimeException('قالب پشتیبان شناخته‌شده نیست.');
    }
    return $manifest;
}

function maintenance_validate_archive(string $path): array
{
    $archive = maintenance_open_archive($path);
    foreach (new RecursiveIteratorIterator($archive) as $file) {
        $entryPath = maintenance_archive_entry_path($archive, $file);
        if (!maintenance_archive_entry_is_safe($entryPath)) throw new RuntimeException('پشتیبان شامل مسیر ناامن است.');
    }
    $manifestRaw = maintenance_archive_string($archive, 'manifest.json');
    $manifest = json_decode($manifestRaw, true, 64, JSON_THROW_ON_ERROR);
    if (($manifest['format'] ?? '') !== 'sokna-backup-v3' || !in_array($manifest['type'] ?? '', ['backup','recovery_point'], true)) {
        throw new RuntimeException('قالب پشتیبان شناخته‌شده نیست.');
    }
    $indexRaw = maintenance_archive_string($archive, 'files.json');
    if (!hash_equals((string)($manifest['files_index_sha256'] ?? ''), hash('sha256', $indexRaw))) {
        throw new RuntimeException('فهرست فایل‌های پشتیبان تغییر کرده است.');
    }
    $entries = json_decode($indexRaw, true, 128, JSON_THROW_ON_ERROR);
    if (!is_array($entries)) throw new RuntimeException('فهرست فایل‌های پشتیبان معتبر نیست.');
    $expected = ['manifest.json'=>true,'files.json'=>true]; foreach ($entries as $entry) $expected[(string)($entry['path']??'')] = true;
    $actual = []; foreach (new RecursiveIteratorIterator($archive) as $file) { if ($file->isDir()) continue; $actual[maintenance_archive_entry_path($archive,$file)]=true; }
    $extra=array_diff_key($actual,$expected);$missing=array_diff_key($expected,$actual);if($extra||$missing)throw new RuntimeException('مجموعه فایل‌های آرشیو دقیقاً با فهرست امضاشده یکسان نیست.');
    foreach ($entries as $entry) {
        $entryPath = (string)($entry['path'] ?? '');
        if (!maintenance_archive_entry_is_safe($entryPath) || !isset($archive[$entryPath])) {
            throw new RuntimeException('یکی از فایل‌های پشتیبان ناقص است.');
        }
        $entryStream = 'phar://' . $archive->getPath() . '/' . $entryPath;
        $actualHash = hash_file('sha256', $entryStream);
        if (!$actualHash || !hash_equals((string)($entry['sha256'] ?? ''), $actualHash)) {
            throw new RuntimeException('صحت فایل پشتیبان تأیید نشد: ' . $entryPath);
        }
    }
    $databasePath = 'phar://' . $archive->getPath() . '/database/database.sql';
    $databaseHash = hash_file('sha256', $databasePath);
    if (!$databaseHash || !hash_equals((string)($manifest['database_sha256'] ?? ''), $databaseHash)) {
        throw new RuntimeException('نسخه پایگاه داده داخل پشتیبان آسیب دیده است.');
    }
    $appKey = null;
    if (($manifest['format'] ?? '') === 'sokna-backup-v3') {
        if (empty($manifest['portable_app_identity']) || !isset($archive['system/app.key'])) throw new RuntimeException('هویت داخلی قابل‌انتقال در پشتیبان وجود ندارد.');
        $appKey = trim((string)$archive['system/app.key']->getContent());
        if (strlen($appKey) < 32 || strlen($appKey) > 256 || preg_match('/[\r\n]/', $appKey)) throw new RuntimeException('هویت داخلی پشتیبان معتبر نیست.');
        $fingerprint = substr(hash('sha256',$appKey),0,16);
        if (!hash_equals((string)($manifest['app_identity_fingerprint'] ?? ''), $fingerprint)) throw new RuntimeException('هویت داخلی پشتیبان با Manifest یکسان نیست.');
    }
    return ['manifest' => $manifest, 'entries' => $entries, 'database_path' => $databasePath, 'app_key'=>$appKey];
}

function maintenance_format_bytes(int $bytes): string
{
    $units = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت'];
    $value = max(0, $bytes);
    $unit = 0;
    while ($value >= 1024 && $unit < count($units) - 1) {
        $value /= 1024;
        $unit++;
    }
    return fa_digits(number_format($value, $unit === 0 ? 0 : 1)) . ' ' . $units[$unit];
}

function maintenance_backup_metadata(string $path, bool $validate = false): array
{
    $name=basename($path);$sidecar=maintenance_read_backup_sidecar($path);
    $result=['name'=>$name,'path'=>$path,'size'=>is_file($path)?(filesize($path)?:0):0,'modified_at'=>is_file($path)?date(DATE_ATOM,filemtime($path)?:time()):null,'valid'=>$sidecar['valid']??null,'manifest'=>is_array($sidecar['manifest']??null)?$sidecar['manifest']:null,'error'=>$sidecar['error']??null,'validated_at'=>$sidecar['validated_at']??null];
    if($validate){try{$validated=maintenance_validate_archive($path);$result['valid']=true;$result['manifest']=$validated['manifest'];$result['validated_at']=date(DATE_ATOM);maintenance_write_backup_sidecar($path,$validated['manifest'],true);}catch(Throwable $e){$result['valid']=false;$result['error']=$e->getMessage();$manifest=is_array($result['manifest'])?$result['manifest']:[];maintenance_write_backup_sidecar($path,$manifest,false,$e->getMessage());}}
    return $result;
}

function maintenance_list_backups(bool $validate = true): array
{
    maintenance_ensure_storage();
    $files = glob(maintenance_backup_dir() . '/sokna-backup-*.tar.gz') ?: [];
    usort($files, static fn(string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
    return array_map(static fn(string $path): array => maintenance_backup_metadata($path, $validate), $files);
}

function maintenance_archive_path(string $name, bool $recoveryPoint = false): string
{
    $safe = $recoveryPoint ? maintenance_safe_recovery_point_name($name) : maintenance_safe_backup_name($name);
    if (!$safe) throw new RuntimeException('نام فایل پشتیبان معتبر نیست.');
    $path = maintenance_backup_dir() . '/' . $name;
    $realDir = realpath(maintenance_backup_dir());
    $real = realpath($path);
    if (!$realDir || !$real || !str_starts_with(str_replace('\\', '/', $real), str_replace('\\', '/', $realDir) . '/')) throw new RuntimeException('فایل پشتیبان پیدا نشد.');
    return $real;
}

function maintenance_backup_path(string $name): string { return maintenance_archive_path($name, false); }
function maintenance_recovery_point_path(string $name): string { return maintenance_archive_path($name, true); }

/**
 * Stage an uploaded backup under a server-controlled filename that PharData can open reliably.
 * PHP upload temp names have no trusted extension; the client filename is intentionally ignored.
 */
function maintenance_stage_backup_file(string $sourcePath): string
{
    if (!is_file($sourcePath) || !is_readable($sourcePath)) throw new RuntimeException('فایل پشتیبان بارگذاری‌شده قابل خواندن نیست.');
    $size=(int)(filesize($sourcePath)?:0);
    if($size<1)throw new RuntimeException('فایل پشتیبان خالی است.');
    if($size>2*1024*1024*1024)throw new RuntimeException('حجم فایل پشتیبان بیش از حد مجاز است.');
    maintenance_disk_check($size);
    maintenance_ensure_storage();
    $stage=maintenance_tmp_dir().'/backup-import-'.bin2hex(random_bytes(12)).'.tar.gz';
    if(!copy($sourcePath,$stage))throw new RuntimeException('انتقال فایل پشتیبان به فضای بررسی انجام نشد.');
    @chmod($stage,0640);
    return $stage;
}

/** Import one portable backup through the admin UI; the original client filename is never trusted. */
function maintenance_import_backup_file(string $sourcePath, ?int $actorUserId = null, string $passphrase = ''): array
{
    $secureTransport = maintenance_secure_backup_is_file($sourcePath);
    if ($secureTransport) {
        maintenance_disk_check((int)(filesize($sourcePath) ?: 0));
        maintenance_ensure_storage();
        $stage = maintenance_tmp_dir() . '/backup-import-' . bin2hex(random_bytes(12)) . '.tar.gz';
        maintenance_secure_backup_decrypt_file($sourcePath, $stage, $passphrase);
    } else {
        $stage = maintenance_stage_backup_file($sourcePath);
    }
    try{
        return maintenance_lock(function()use($stage,$actorUserId,$secureTransport):array{
            $validated=maintenance_validate_archive($stage);
            maintenance_restore_compatibility($validated['manifest']);
            $name=maintenance_backup_id('backup');
            $target=maintenance_backup_dir().'/'.$name;
            if(!rename($stage,$target)){
                if(!copy($stage,$target))throw new RuntimeException('ثبت نهایی فایل پشتیبان انجام نشد.');
                @unlink($stage);
            }
            @chmod($target,0640);
            try{
                $stored=maintenance_validate_archive($target);
                maintenance_restore_compatibility($stored['manifest']);
                maintenance_write_backup_sidecar($target,$stored['manifest'],true);
                maintenance_prune_backups();
                $meta=maintenance_backup_metadata($target,false);
                $meta['valid']=true;$meta['manifest']=$stored['manifest'];
                $meta['import_transport']=$secureTransport?MAINTENANCE_SECURE_BACKUP_FORMAT:'legacy-plain-tar-gz';
                if(function_exists('audit_log_write'))audit_log_write('backup.imported','backup',$name,['version'=>(string)$stored['manifest']['version'],'size'=>(int)($meta['size']??0),'transport'=>$meta['import_transport']],$actorUserId);
                return $meta;
            }catch(Throwable $e){
                @unlink($target);@unlink(maintenance_backup_sidecar_path($target));throw $e;
            }
        });
    }finally{
        @unlink($stage);
    }
}

function maintenance_prune_backups(int $keep = MAINTENANCE_BACKUP_KEEP): void
{
    // One backup format, four recovery points per day, approximately seven days on-server.
    $keep = MAINTENANCE_BACKUP_KEEP;
    $files = glob(maintenance_backup_dir() . '/sokna-backup-*.tar.gz') ?: [];
    usort($files, static fn(string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));

    $protected = array_slice($files, 0, $keep);
    $latestKnownGood = null;
    foreach ($files as $path) {
        $sidecar = maintenance_read_backup_sidecar($path);
        if (($sidecar['valid'] ?? null) === true) {
            $latestKnownGood = $path;
            break;
        }
    }
    if ($latestKnownGood !== null && !in_array($latestKnownGood, $protected, true)) $protected[] = $latestKnownGood;

    foreach ($files as $path) {
        if (in_array($path, $protected, true)) continue;
        @unlink($path);
        @unlink(maintenance_backup_sidecar_path($path));
    }

    $recoveryPoints = glob(maintenance_backup_dir() . '/sokna-recovery-point-*.tar.gz') ?: [];
    usort($recoveryPoints, static fn(string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
    foreach (array_slice($recoveryPoints, MAINTENANCE_RECOVERY_POINT_KEEP) as $path) {
        @unlink($path);
        @unlink(maintenance_backup_sidecar_path($path));
    }
}

function maintenance_backup_worker_status_path(): string
{
    return maintenance_storage_dir() . '/backup-worker-last.json';
}

function maintenance_record_backup_worker_run(string $status, string $message = ''): void
{
    maintenance_ensure_storage();
    $payload = [
        'format' => 'sokna-backup-worker-v1',
        'last_run_at' => date(DATE_ATOM),
        'status' => $status,
        'message' => $message,
    ];
    @file_put_contents(maintenance_backup_worker_status_path(), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
}

function maintenance_backup_worker_status(?int $now = null): array
{
    $now ??= time();
    $path = maintenance_backup_worker_status_path();
    $data = [];
    if (is_file($path)) {
        $decoded = json_decode((string)@file_get_contents($path), true);
        if (is_array($decoded)) $data = $decoded;
    }
    $lastRunAt = trim((string)($data['last_run_at'] ?? ''));
    $lastRunTs = $lastRunAt !== '' ? (strtotime($lastRunAt) ?: 0) : 0;
    $recent = $lastRunTs > 0 && $lastRunTs >= $now - (MAINTENANCE_WORKER_HEARTBEAT_HOURS * 3600);
    return [
        'seen' => $lastRunTs > 0,
        'active' => $recent && (($data['status'] ?? '') !== 'failed'),
        'last_run_at' => $lastRunAt !== '' ? $lastRunAt : null,
        'status' => (string)($data['status'] ?? 'unknown'),
        'message' => (string)($data['message'] ?? ''),
    ];
}

function maintenance_setting_write(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    $stmt->execute([$key, $value]);
    clear_setting_cache();
}

function maintenance_offserver_reminder_ensure_started(?string $at = null): void
{
    if (trim(setting('backup_last_offserver_export_at', '')) !== '' || trim(setting('backup_offserver_reminder_started_at', '')) !== '') return;
    $timestamp = $at !== null && strtotime($at) !== false ? date(DATE_ATOM, (int)strtotime($at)) : date(DATE_ATOM);
    maintenance_setting_write('backup_offserver_reminder_started_at', $timestamp);
}

function maintenance_offserver_reminder_bootstrap(): void
{
    if (trim(setting('backup_last_offserver_export_at', '')) !== '' || trim(setting('backup_offserver_reminder_started_at', '')) !== '') return;
    foreach (maintenance_list_backups(false) as $backup) {
        $manifest = $backup['manifest'] ?? null;
        if (!is_array($manifest)) continue;
        if (($manifest['type'] ?? '') !== 'backup' || ($backup['valid'] ?? null) !== true) continue;
        if (($manifest['version'] ?? '') !== maintenance_version()) continue;
        // Existing installations get a clean seven-day grace period from the first load
        // of this policy rather than an immediate historical reminder.
        maintenance_offserver_reminder_ensure_started(date(DATE_ATOM));
        return;
    }
}

function maintenance_offserver_export_status(?int $now = null): array
{
    $now ??= time();
    $lastAt = trim(setting('backup_last_offserver_export_at', ''));
    $startedAt = trim(setting('backup_offserver_reminder_started_at', ''));
    $reference = $lastAt !== '' ? $lastAt : $startedAt;
    $referenceTs = $reference !== '' ? (strtotime($reference) ?: 0) : 0;
    $interval = MAINTENANCE_OFFSERVER_REMINDER_DAYS * 86400;
    $due = $referenceTs > 0 && $now >= $referenceTs + $interval;
    $daysSince = $referenceTs > 0 ? max(0, (int)floor(($now - $referenceTs) / 86400)) : null;
    $daysRemaining = $referenceTs > 0 ? max(0, (int)ceil((($referenceTs + $interval) - $now) / 86400)) : null;
    return [
        'last_export_at' => $lastAt !== '' ? $lastAt : null,
        'last_export_name' => trim(setting('backup_last_offserver_export_name', '')) ?: null,
        'started_at' => $startedAt !== '' ? $startedAt : null,
        'due' => $due,
        'days_since' => $daysSince,
        'days_remaining' => $daysRemaining,
    ];
}

function maintenance_record_offserver_export(array $backup, ?int $actorUserId = null): void
{
    $manifest = $backup['manifest'] ?? null;
    if (!is_array($manifest) || ($manifest['type'] ?? '') !== 'backup' || ($backup['valid'] ?? null) !== true) {
        throw new RuntimeException('فقط یک پشتیبان سالم داده می‌تواند به‌عنوان نسخه خارج از سرور ثبت شود.');
    }
    if (($manifest['version'] ?? '') !== maintenance_version()) {
        throw new RuntimeException('نسخه پشتیبان برای خروج امن با نسخه فعلی سامانه سازگار نیست.');
    }
    $now = date(DATE_ATOM);
    maintenance_setting_write('backup_last_offserver_export_at', $now);
    maintenance_setting_write('backup_last_offserver_export_name', (string)($backup['name'] ?? ''));
    maintenance_setting_write('backup_offserver_reminder_started_at', $now);
    audit_log_write('backup.offserver_exported', 'backup', (string)($backup['name'] ?? ''), [
        'backup_created_at' => (string)($manifest['created_at'] ?? ''),
        'backup_version' => (string)($manifest['version'] ?? ''),
        'size' => (int)($backup['size'] ?? 0),
    ], $actorUserId);
}

function maintenance_set_state(string $mode, string $message, ?int $actorUserId = null, array $details = []): void
{
    maintenance_ensure_storage();
    $state = array_merge(['active' => true, 'mode' => $mode, 'message' => $message, 'started_at' => date(DATE_ATOM), 'actor_user_id' => $actorUserId, 'owner' => 'maintenance-v2'], $details);
    if (file_put_contents(maintenance_storage_dir() . '/maintenance.json', json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
        throw new RuntimeException('حالت نگهداری فعال نشد.');
    }
}

function maintenance_clear_state(): void
{
    @unlink(maintenance_storage_dir() . '/maintenance.json');
}

function maintenance_state(): array
{
    $path = maintenance_storage_dir() . '/maintenance.json';
    if (!is_file($path)) return ['active' => false];
    $data = json_decode((string)@file_get_contents($path), true);
    return is_array($data) ? $data + ['active' => true] : ['active' => true, 'mode' => 'unknown', 'message' => 'سامانه در حال نگهداری است.'];
}

function maintenance_is_active(): bool
{
    return (bool)(maintenance_state()['active'] ?? false);
}


function maintenance_guard_json(string $message = 'سامانه برای عملیات نگهداری موقتاً در دسترس نیست.'): void
{
    if (!maintenance_is_active()) return;
    $state = maintenance_state();
    json_response([
        'success' => false,
        'message' => (string)($state['message'] ?? $message),
        'code' => 'maintenance_mode',
    ], 503);
}

function maintenance_restore_sql(PDO $pdo, string $sql): void
{
    $statements = maintenance_sql_statements($sql);
    if (!$statements) throw new RuntimeException('پایگاه داده پشتیبان خالی است.');
    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }
}

function maintenance_restore_sql_file(PDO $pdo, string $path): void
{
    $stream=fopen($path,'rb');if($stream===false)throw new RuntimeException('فایل SQL پشتیبان خوانده نشد.');$executed=0;
    try{
        $first=fgets($stream);
        if($first==="-- CAFE-SQL-FRAMED-V2 --\n"||rtrim((string)$first,"\r\n")==='-- CAFE-SQL-FRAMED-V2 --'){
            while(($line=fgets($stream))!==false){$line=rtrim($line,"\r\n");if($line==='')continue;if(!preg_match('/^CAFE-LEN (\d+)$/',$line,$m))throw new RuntimeException('قالب Statement پشتیبان معتبر نیست.');$remaining=(int)$m[1];if($remaining<1||$remaining>100*1024*1024)throw new RuntimeException('اندازه Statement پشتیبان مجاز نیست.');$statement='';while($remaining>0){$chunk=fread($stream,min(1024*1024,$remaining));if($chunk===false||$chunk==='')throw new RuntimeException('Statement پشتیبان ناقص است.');$statement.=$chunk;$remaining-=strlen($chunk);} $pdo->exec($statement);$executed++;$next=fgetc($stream);if($next!==false&&$next!=="\n")fseek($stream,-1,SEEK_CUR);}
        }else{
            rewind($stream);$delimiter="\n-- CAFE-STMT --\n";$buffer='';while(!feof($stream)){$chunk=fread($stream,1024*1024);if($chunk===false)throw new RuntimeException('خواندن فایل SQL پشتیبان کامل نشد.');$buffer.=$chunk;while(($position=strpos($buffer,$delimiter))!==false){$statement=trim(substr($buffer,0,$position));$buffer=substr($buffer,$position+strlen($delimiter));if($statement==='')continue;$pdo->exec($statement);$executed++;}}$statement=trim($buffer);if($statement!==''){$pdo->exec($statement);$executed++;}
        }
    }finally{fclose($stream);}if($executed===0)throw new RuntimeException('پایگاه داده پشتیبان خالی است.');
}

function maintenance_clear_directory(string $directory, array $preserve = ['.htaccess', 'index.html']): void
{
    if (!is_dir($directory)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $file) {
        $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen(rtrim($directory, DIRECTORY_SEPARATOR)))), '/');
        if (in_array($relative, $preserve, true)) continue;
        if ($file->isLink() || $file->isFile()) @unlink($file->getPathname());
        elseif ($file->isDir()) @rmdir($file->getPathname());
    }
}

function maintenance_restore_files(PharData $archive, string $prefix, string $destination): void
{
    $prefix = trim($prefix, '/') . '/';
    foreach (new RecursiveIteratorIterator($archive) as $file) {
        if ($file->isDir()) continue;
        $entry = maintenance_archive_entry_path($archive, $file);
        if (!str_starts_with($entry, $prefix)) continue;
        $relative = substr($entry, strlen($prefix));
        if ($relative === '' || !maintenance_archive_entry_is_safe($relative)) continue;
        $target = rtrim($destination, '/') . '/' . $relative;
        $parent = dirname($target);
        if (!is_dir($parent) && !mkdir($parent, 0750, true) && !is_dir($parent)) throw new RuntimeException('پوشه مقصد بازیابی ساخته نشد.');
        $temp = $target . '.restore-' . bin2hex(random_bytes(3));
        if (file_put_contents($temp, $file->getContent(), LOCK_EX) === false || !rename($temp, $target)) {
            @unlink($temp);
            throw new RuntimeException('بازیابی فایل انجام نشد: ' . $relative);
        }
    }
}

function maintenance_health_check(?PDO $pdo = null): array
{
    $pdo ??= db();
    $checks = [];
    $required = [
        'settings', 'schema_migrations', 'users', 'user_capabilities',
        'menus', 'menu_categories', 'menu_items', 'categories', 'items', 'cafe_tables', 'table_sessions',
        'table_session_clients', 'orders', 'order_items', 'order_item_adjustments', 'preparation_adjustments', 'push_subscriptions', 'push_delivery_log',
        'order_preparation_claims', 'order_status_history', 'waiter_calls', 'events', 'tags',
        'item_tags', 'campaigns', 'menu_metrics_daily', 'menu_search_terms_daily', 'financial_periods', 'accommodation_transfers',
        'invoice_discount_audit', 'subscribers', 'subscriber_ledger', 'audit_log',
        'print_agents', 'print_destinations', 'print_templates', 'print_jobs', 'push_event_queue', 'user_preparation_areas',
        'inventory_categories', 'inventory_items', 'inventory_purchase_units', 'inventory_balances', 'inventory_movements',
        'inventory_recipe_versions', 'inventory_recipe_components', 'inventory_count_sessions', 'inventory_count_lines', 'inventory_order_events',
        'inventory_supply_needs', 'inventory_supply_receipts',
    ];
    $tables = array_map(static fn(array $row): string => (string)array_values($row)[0], $pdo->query('SHOW TABLES')->fetchAll());
    foreach ($required as $table) $checks['table_' . $table] = in_array($table, $tables, true);
    $checks['version_file'] = is_file(maintenance_root() . '/VERSION.txt');
    $checks['login_page'] = is_file(maintenance_root() . '/login.php');
    $authSource = @file_get_contents(maintenance_root() . '/includes/auth.php');
    $checks['auth_runtime'] = is_string($authSource)
        && str_contains($authSource, 'function is_logged_in')
        && str_contains($authSource, 'function login(');
    $orderSource = @file_get_contents(maintenance_root() . '/api/create_order.php');
    $checks['order_contract'] = is_string($orderSource)
        && str_contains($orderSource, 'normalize_order_request_payload')
        && str_contains($orderSource, 'order_status_history');
    $uploadDir = maintenance_root() . '/uploads';
    $checks['uploads_writable'] = is_dir($uploadDir) ? is_writable($uploadDir) : is_writable(dirname($uploadDir));
    $checks['storage_writable'] = is_dir(maintenance_storage_dir()) && is_writable(maintenance_storage_dir());
    $checks['settings_readable'] = in_array('settings', $tables, true) && (int)$pdo->query('SELECT COUNT(*) FROM settings')->fetchColumn() >= 0;
    if ($checks['settings_readable']) {
        $centerStored = trim(setting('sokna_center_handoff_secret_encrypted',''));
        $checks['center_secret_decryptable'] = $centerStored === '' || sokna_center_secret() !== '';
        $accommodationStored = trim(setting('accommodation_api_key_encrypted',''));
        $checks['accommodation_secret_decryptable'] = $accommodationStored === '' || accommodation_api_key() !== '';
    }
    return ['ok' => !in_array(false, $checks, true), 'checks' => $checks, 'version' => maintenance_version(), 'checked_at' => date(DATE_ATOM)];
}

function maintenance_restore_compatibility(array $manifest): void
{
    if(($manifest['type']??'')!=='backup')throw new RuntimeException('بازیابی مدیریتی فقط از پشتیبان اصلی سامانه مجاز است.');
    if(($manifest['format']??'')!=='sokna-backup-v3'||empty($manifest['portable_app_identity']))throw new RuntimeException('این فایل از قالب قدیمی پشتیبان است و برای انتقال کامل سرور کافی نیست. از نسخه فعلی یک پشتیبان تازه بسازید.');
    $version=(string)($manifest['version']??'');if($version!==maintenance_version())throw new RuntimeException('این پشتیبان برای نسخه '.$version.' است. ابتدا همان نسخه برنامه را نصب کنید و سپس بازیابی را انجام دهید.');
    $fingerprint=(string)($manifest['schema_fingerprint']??'');if($fingerprint!==''&&!hash_equals($fingerprint,maintenance_schema_fingerprint()))throw new RuntimeException('ساختار دیتابیس با پشتیبان انتخاب‌شده سازگار نیست.');
}
function maintenance_restore_backup(string $name, bool $createEmergency = true): array
{
    return maintenance_lock(function()use($name,$createEmergency):array{
        $path=maintenance_backup_path($name);$validated=maintenance_validate_archive($path);maintenance_restore_compatibility($validated['manifest']);maintenance_disk_check(maintenance_estimated_data_bytes());
        $targetKey=(string)($validated['app_key']??'');if($targetKey==='')throw new RuntimeException('هویت داخلی پشتیبان قابل بازیابی نیست.');
        $actor=(int)(current_user()['id']??0);$job=maintenance_job_start('restore',['backup'=>$name]);$recoveryPoint=null;
        maintenance_set_state('restore','بازیابی داده در حال انجام است. عملیات کافه تا پایان بررسی سلامت متوقف می‌ماند.',$actor,['target_backup'=>$name,'maintenance_job_id'=>(string)$job['id']]);
        try{
            if($createEmergency){
                $job['stage']='emergency_snapshot';$job['progress']=20;$job['message']='در حال ساخت نقطه بازگشت اضطراری…';maintenance_job_write($job);
                $recoveryPoint=maintenance_create_backup_unlocked('recovery_point',$actor);
                maintenance_set_state('restore','نقطه بازگشت اضطراری ساخته شد؛ بازیابی داده در حال انجام است.',$actor,['target_backup'=>$name,'recovery_point'=>(string)($recoveryPoint['name']??''),'maintenance_job_id'=>(string)$job['id']]);
            }
            $job['stage']='apply';$job['progress']=45;$job['message']='در حال اعمال داده پشتیبان…';maintenance_job_write($job);
            $archive=maintenance_open_archive($path);maintenance_restore_sql_file(db(),$validated['database_path']);maintenance_clear_directory(maintenance_root().'/uploads');maintenance_restore_files($archive,'uploads',maintenance_root().'/uploads');
            maintenance_config_set_app_key($targetKey);
            $job['stage']='health';$job['progress']=80;$job['message']='در حال بررسی سلامت…';maintenance_job_write($job);$health=maintenance_health_check();if(!$health['ok'])throw new RuntimeException('بررسی سلامت پس از بازیابی کامل نبود.');
            maintenance_clear_state();maintenance_job_finish($job,true,'بازیابی و بررسی سلامت کامل شد.');return ['success'=>true,'health'=>$health,'recovery_point'=>$recoveryPoint];
        }catch(Throwable $e){
            if($recoveryPoint&&isset($recoveryPoint['name'])){try{$fallbackPath=maintenance_recovery_point_path((string)$recoveryPoint['name']);$fallback=maintenance_validate_archive($fallbackPath);$fallbackKey=(string)($fallback['app_key']??'');if($fallbackKey==='')throw new RuntimeException('هویت داخلی نقطه بازگشت اضطراری ناقص است.');$fallbackArchive=maintenance_open_archive($fallbackPath);maintenance_restore_sql_file(db(),$fallback['database_path']);maintenance_clear_directory(maintenance_root().'/uploads');maintenance_restore_files($fallbackArchive,'uploads',maintenance_root().'/uploads');maintenance_config_set_app_key($fallbackKey);$rollbackHealth=maintenance_health_check();if(!$rollbackHealth['ok'])throw new RuntimeException('Health Check پس از بازگشت اضطراری ناموفق بود.');}catch(Throwable $rollbackError){maintenance_set_state('recovery_required','بازیابی و بازگشت اضطراری هر دو نیازمند رسیدگی‌اند. سامانه برای جلوگیری از ثبت داده جدید قفل مانده است.',$actor,['target_backup'=>$name,'recovery_point'=>(string)($recoveryPoint['name']??''),'maintenance_job_id'=>(string)$job['id']]);maintenance_job_finish($job,false,'نیازمند بازیابی اضطراری: '.$rollbackError->getMessage());throw new RuntimeException('سامانه در وضعیت بازیابی اضطراری قفل ماند: '.$rollbackError->getMessage(),0,$e);}maintenance_clear_state();maintenance_job_finish($job,false,'بازیابی مقصد ناموفق بود و وضعیت قبل با موفقیت برگردانده شد.');throw new RuntimeException('بازیابی انجام نشد؛ سامانه با موفقیت به وضعیت قبل برگشت: '.$e->getMessage(),0,$e);}
            maintenance_set_state('recovery_required','بازیابی کامل نشد و نقطه بازگشت اضطراری در دسترس نیست.',$actor,['target_backup'=>$name,'maintenance_job_id'=>(string)$job['id']]);maintenance_job_finish($job,false,'بازیابی اضطراری لازم است.');throw $e;
        }
    });
}

/** Internal backup creator used while the maintenance lock is already held. */
function maintenance_create_backup_unlocked(string $kind, ?int $actorUserId = null): array
{
    if (!in_array($kind, ['backup','recovery_point'], true)) throw new InvalidArgumentException('نوع داخلی پشتیبان معتبر نیست.');
    maintenance_disk_check(maintenance_estimated_data_bytes());
    $filename = maintenance_backup_id($kind);
    $tarPath = maintenance_tmp_dir() . '/' . substr($filename, 0, -3);
    $finalPath = maintenance_backup_dir() . '/' . $filename;
    @unlink($tarPath); @unlink($tarPath . '.gz'); @unlink($finalPath);
    $entries = [];
    try {
        $dbFile = maintenance_tmp_dir() . '/database-' . bin2hex(random_bytes(4)) . '.sql';
        $dumpMetadata = maintenance_database_dump_file(db(), $dbFile);
        $archive = new PharData($tarPath);
        maintenance_add_file($archive, $dbFile, 'database/database.sql', $entries);
        @unlink($dbFile);
        $uploadDir = maintenance_root() . '/uploads';
        if (is_dir($uploadDir)) foreach (maintenance_recursive_files($uploadDir) as [$real, $relative]) maintenance_add_file($archive, $real, 'uploads/' . $relative, $entries);

        // Carry the application identity required to decrypt integration secrets after a server move.
        // DB credentials, hostnames and app URL intentionally remain target-environment configuration.
        $appKey = maintenance_current_app_key();
        maintenance_add_string($archive, $appKey, 'system/app.key', $entries);

        $indexJson = json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $archive->addFromString('files.json', $indexJson);
        $manifest = [
            'format'=>'sokna-backup-v3','type'=>$kind,'version'=>maintenance_version(),'created_at'=>date(DATE_ATOM),'timezone'=>app_timezone(),
            'actor_user_id'=>$actorUserId,'database_sha256'=>$dumpMetadata['sha256'],'files_index_sha256'=>hash('sha256',$indexJson),
            'entry_count'=>count($entries),'uploads_present'=>is_dir($uploadDir),'schema_fingerprint'=>maintenance_schema_fingerprint(),
            'portable_app_identity'=>true,'app_identity_fingerprint'=>substr(hash('sha256',$appKey),0,16),
        ];
        $archive->addFromString('manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $tarSha256 = hash_file('sha256', $tarPath);
        $compressedArchive = $archive->compress(Phar::GZ); unset($compressedArchive, $archive);
        if (!is_file($tarPath . '.gz') || !$tarSha256 || !maintenance_verify_gzip($tarPath . '.gz', $tarSha256)) throw new RuntimeException('فشرده‌سازی یا بررسی نهایی فایل پشتیبان انجام نشد.');
        if (!rename($tarPath . '.gz', $finalPath)) throw new RuntimeException('ذخیره فایل پشتیبان انجام نشد.');
        @unlink($tarPath);

        // A file is healthy only after the same validator used by Restore accepts the final bytes.
        $validated = maintenance_validate_archive($finalPath);
        maintenance_write_backup_sidecar($finalPath,$validated['manifest'],true);
        $metadata = maintenance_backup_metadata($finalPath, false);
        $metadata['valid'] = true;
        $metadata['manifest'] = $validated['manifest'];
        return $metadata;
    } catch (Throwable $e) {
        if (isset($dbFile) && is_file($dbFile)) @unlink($dbFile);
        @unlink($tarPath); @unlink($tarPath . '.gz'); @unlink($finalPath);
        throw $e;
    }
}
