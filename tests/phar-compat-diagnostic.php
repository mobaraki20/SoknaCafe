<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$tmp = sys_get_temp_dir() . '/sokna-phar-diag-' . bin2hex(random_bytes(4));
mkdir($tmp, 0750, true);
mkdir($tmp . '/storage/backups', 0750, true);
mkdir($tmp . '/storage/tmp', 0750, true);
mkdir($tmp . '/uploads', 0755, true);
define('SOKNA_MAINTENANCE_ROOT', $tmp);
require $root . '/includes/maintenance.php';

function diag_json(string $label, string $raw): void {
    $ok = true;
    $error = null;
    try { json_decode($raw, true, 64, JSON_THROW_ON_ERROR); }
    catch (Throwable $e) { $ok = false; $error = $e->getMessage(); }
    echo $label . ': len=' . strlen($raw)
        . ' sha256=' . hash('sha256', $raw)
        . ' json=' . ($ok ? 'ok' : 'bad:' . $error)
        . ' head=' . bin2hex(substr($raw, 0, 32))
        . ' tail=' . bin2hex(substr($raw, -32))
        . PHP_EOL;
}

try {
    $tar = $tmp . '/storage/tmp/test.tar';
    $db = "-- CAFE-SQL-FRAMED-V2 --\nCAFE-LEN 25\nSET FOREIGN_KEY_CHECKS=0;\n";
    $appKey = str_repeat('c', 64);
    $archive = new PharData($tar);
    $entries = [];
    maintenance_add_string($archive, $db, 'database/database.sql', $entries);
    maintenance_add_string($archive, $appKey, 'system/app.key', $entries);
    $index = json_encode($entries, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $archive->addFromString('files.json', $index);
    $manifest = [
        'format'=>'sokna-backup-v3',
        'type'=>'backup',
        'version'=>maintenance_version(),
        'created_at'=>date(DATE_ATOM),
        'timezone'=>'Asia/Tehran',
        'actor_user_id'=>null,
        'database_sha256'=>hash('sha256',$db),
        'files_index_sha256'=>hash('sha256',$index),
        'entry_count'=>count($entries),
        'uploads_present'=>false,
        'schema_fingerprint'=>'',
        'portable_app_identity'=>true,
        'app_identity_fingerprint'=>substr(hash('sha256',$appKey),0,16),
    ];
    $manifestJson = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $archive->addFromString('manifest.json', $manifestJson);

    diag_json('source-json', $manifestJson);
    diag_json('tar-direct-before-compress', (string)$archive['manifest.json']->getContent());

    $tarSha = hash_file('sha256', $tar);
    $gzObject = $archive->compress(Phar::GZ);
    unset($gzObject, $archive);
    $gzPath = $tar . '.gz';

    echo 'gzip-exists=' . (is_file($gzPath) ? 'yes' : 'no')
        . ' gzip-roundtrip=' . (maintenance_verify_gzip($gzPath, (string)$tarSha) ? 'ok' : 'bad')
        . PHP_EOL;

    $direct = new PharData($gzPath);
    diag_json('tar-gz-direct', (string)$direct['manifest.json']->getContent());
    unset($direct);

    $roundtripTar = $tmp . '/storage/tmp/roundtrip.tar';
    $in = gzopen($gzPath, 'rb');
    $out = fopen($roundtripTar, 'wb');
    if (!$in || !$out) throw new RuntimeException('diagnostic stream open failed');
    while (!gzeof($in)) {
        $chunk = gzread($in, 1024 * 1024);
        if ($chunk === false) throw new RuntimeException('diagnostic gzip read failed');
        fwrite($out, $chunk);
    }
    gzclose($in);
    fclose($out);

    $round = new PharData($roundtripTar);
    diag_json('roundtrip-tar-direct', (string)$round['manifest.json']->getContent());
    echo 'php=' . PHP_VERSION . ' os=' . PHP_OS_FAMILY . PHP_EOL;
} finally {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($tmp);
}
