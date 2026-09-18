<?php
declare(strict_types=1);

/**
 * Canonical Sokna release builder.
 *
 * The updater uses a stable loader plus versioned engine directories. A new
 * engine is delivered in the same application package and activated only
 * after Health Check, without any external file-manager step.
 */

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }

function arg_value(array $argv, string $name, ?string $default = null): ?string
{
    $prefix = '--' . $name . '=';
    foreach ($argv as $arg) if (str_starts_with($arg, $prefix)) return substr($arg, strlen($prefix));
    return $default;
}
function release_normalize(string $path): string
{
    $path = trim(str_replace('\\', '/', $path), '/');
    if ($path === '' || str_contains($path, "\0") || preg_match('#(^|/)\.\.(/|$)#', $path)) throw new RuntimeException('Invalid path: ' . $path);
    return $path;
}

function release_target_engine(string $root): string
{
    $versions = [];
    foreach (glob(rtrim($root, '/\\') . '/includes/updater_engine/*', GLOB_ONLYDIR) ?: [] as $engineDir) {
        $candidate = basename($engineDir);
        if (preg_match('/^\d+\.\d+\.\d+$/', $candidate) && is_file($engineDir . '/runtime.php') && is_file($engineDir . '/console.php')) $versions[] = $candidate;
    }
    usort($versions, static fn(string $a,string $b): int => version_compare($b,$a));
    return $versions[0] ?? throw new RuntimeException('No complete updater engine exists in the source tree.');
}
function release_collect(string $root, array $exclude): array
{
    $files = [];
    $root = rtrim(str_replace('\\', '/', realpath($root) ?: ''), '/');
    if ($root === '') throw new RuntimeException('Invalid tree root.');
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
    foreach ($it as $file) {
        if (!$file->isFile() || $file->isLink()) continue;
        $real = str_replace('\\', '/', $file->getPathname());
        $relative = ltrim(substr($real, strlen($root)), '/');
        $skip = false;
        foreach ($exclude as $prefix) if ($relative === $prefix || str_starts_with($relative, $prefix . '/')) { $skip = true; break; }
        if ($skip) continue;
        $files[$relative] = ['real'=>$file->getPathname(),'size'=>$file->getSize(),'sha256'=>hash_file('sha256',$file->getPathname())];
    }
    ksort($files, SORT_STRING);
    return $files;
}

$root = realpath(dirname(__DIR__));
$version = trim((string)arg_value($argv, 'version', ''));
$fromVersions = array_values(array_filter(array_map('trim', explode(',', (string)arg_value($argv, 'from', '')))));
$baselineDirs = array_values(array_filter(array_map('trim', explode(',', (string)arg_value($argv, 'baseline-dirs', '')))));
$output = (string)arg_value($argv, 'output', '');
$notesFile = arg_value($argv, 'notes-file');
$migrationFile = arg_value($argv, 'migration');
$explicitDeleteFile = arg_value($argv, 'delete-file');
if (!$root || !preg_match('/^\d+\.\d+\.\d+(?:[-+][A-Za-z0-9.-]+)?$/', $version) || !$fromVersions || !$baselineDirs || $output === '') {
    fwrite(STDERR, "Usage: php tools/build-release.php --version=1.29.0 --from=1.28.0 --baseline-dirs=/tmp/sokna-1.28.0 --output=/tmp/update.zip [--notes-file=...] [--migration=...] [--delete-file=...]\n");
    exit(1);
}
if (trim((string)file_get_contents($root . '/VERSION.txt')) !== $version) throw new RuntimeException('VERSION.txt does not match target version.');

$protected = ['config.php','install.lock','.git','storage','uploads'];
$sourceExclude = $protected;
$source = release_collect($root, $sourceExclude);
$baselines = [];
foreach ($baselineDirs as $dir) $baselines[] = release_collect($dir, $protected);
$targetEngine = release_target_engine($root);
$protectedUpdaterPaths = [
    'admin/update/index.php',
    'includes/updater_engine/' . $targetEngine . '/runtime.php',
    'includes/updater_engine/' . $targetEngine . '/console.php',
];

$changed = [];
foreach ($source as $path => $meta) {
    $different = false;
    foreach ($baselines as $baseline) {
        if (!isset($baseline[$path]) || !hash_equals((string)$meta['sha256'], (string)$baseline[$path]['sha256'])) { $different = true; break; }
    }
    if ($different) $changed[$path] = $meta;
}
if (!isset($changed['VERSION.txt'])) $changed['VERSION.txt'] = $source['VERSION.txt'] ?? throw new RuntimeException('VERSION.txt missing from source.');
ksort($changed, SORT_STRING);

$delete = [];
foreach ($baselines as $baseline) {
    foreach ($baseline as $path => $_) {
        if (isset($source[$path]) || in_array($path, $protectedUpdaterPaths, true)) continue;
        foreach ($protected as $prefix) if ($path === $prefix || str_starts_with($path, $prefix . '/')) continue 2;
        $delete[$path] = $path;
    }
}
if ($explicitDeleteFile !== null) {
    if (!is_file($explicitDeleteFile)) throw new RuntimeException('Delete-list file not found.');
    foreach (file($explicitDeleteFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim(preg_replace('/\s+#.*$/', '', $line) ?? '');
        if ($line === '') continue;
        $path = release_normalize($line);
        if (in_array($path, $protectedUpdaterPaths, true)) throw new RuntimeException('Active updater path cannot be deleted by the application delete list: ' . $path);
        foreach ($protected as $prefix) if ($path === $prefix || str_starts_with($path, $prefix . '/')) throw new RuntimeException('Protected path cannot be deleted: ' . $path);
        if (isset($changed[$path])) throw new RuntimeException('Path cannot be installed and deleted: ' . $path);
        $delete[$path] = $path;
    }
}
ksort($delete, SORT_STRING);

$output = str_replace('\\', '/', $output);
@unlink($output);
if (!is_dir(dirname($output)) && !mkdir(dirname($output), 0755, true) && !is_dir(dirname($output))) throw new RuntimeException('Output directory cannot be created.');
$archive = new PharData($output, 0, null, Phar::ZIP);
$manifestFiles = [];
foreach ($changed as $path => $meta) {
    $archive->addFile($meta['real'], 'files/' . $path);
    $manifestFiles[] = ['path'=>$path,'sha256'=>$meta['sha256'],'size'=>$meta['size']];
}
$migration = null;
if ($migrationFile !== null) {
    $real = realpath($migrationFile);
    if (!$real || !is_file($real)) throw new RuntimeException('Migration file not found.');
    $entry = 'migrations/' . basename($real);
    $archive->addFile($real, $entry);
    $migration = ['path'=>$entry,'sha256'=>hash_file('sha256',$real)];
}
$notes = $notesFile && is_file($notesFile) ? trim((string)file_get_contents($notesFile)) : '';
$manifest = [
    'format'=>'sokna-release-v2',
    'package_type'=>'update',
    'min_updater'=>'1.5.1',
    'updater_engine'=>$targetEngine,
    'version'=>$version,
    'from_versions'=>$fromVersions,
    'min_php'=>'8.1.0',
    'required_extensions'=>['pdo_mysql','openssl','sodium','Phar'],
    'created_at'=>date(DATE_ATOM),
    'release_notes'=>$notes,
    'files'=>$manifestFiles,
    'delete'=>array_values($delete),
    'migration'=>$migration,
];
$archive->addFromString('release-manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
unset($archive);

echo json_encode([
    'output'=>$output,
    'sha256'=>hash_file('sha256',$output),
    'files'=>count($manifestFiles),
    'delete'=>count($delete),
    'updater_engine'=>$targetEngine,
], JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) . "\n";
