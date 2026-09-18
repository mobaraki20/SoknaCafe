<?php
declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php updater-release-acceptance.php <project-root> <package-copy>\n");
    exit(2);
}
$root = realpath($argv[1]);
$package = realpath($argv[2]);
if (!$root || !$package) throw new RuntimeException('Fixture root or package is missing.');

$archive = new PharData($package);
if (!isset($archive['release-manifest.json'])) throw new RuntimeException('Package manifest is missing.');
$manifestRaw = $archive['release-manifest.json']->getContent();
unset($archive);
$manifest = json_decode((string)$manifestRaw, true, 32, JSON_THROW_ON_ERROR);
$targetVersion = (string)($manifest['version'] ?? '');
$targetEngine = (string)($manifest['updater_engine'] ?? '');
if ($targetVersion === '' || $targetEngine === '') throw new RuntimeException('Package target metadata is incomplete.');

$beforeVersion = trim((string)file_get_contents($root . '/VERSION.txt'));
define('SOKNA_UPDATER_ROOT', $root);
define('SOKNA_RECOVERY_ROOT', $root);
define('SOKNA_UPDATER_TEST_SKIP_EXTENSIONS', true);
define('SOKNA_RECOVERY_TEST_SKIP_DB', true);

$legacyRuntime = $root . '/includes/updater_runtime.php';
if (is_file($legacyRuntime)) {
    require $legacyRuntime;
    $legacyHashes = [
        'admin/update.php' => is_file($root . '/admin/update.php') ? hash_file('sha256', $root . '/admin/update.php') : null,
        'includes/updater_runtime.php' => hash_file('sha256', $legacyRuntime),
    ];
} else {
    $pointerPath = $root . '/storage/updater-engine.json';
    $pointer = is_file($pointerPath) ? json_decode((string)file_get_contents($pointerPath), true) : [];
    $engine = (string)($pointer['current'] ?? '');
    if ($engine === '') {
        $dirs = glob($root . '/includes/updater_engine/*', GLOB_ONLYDIR) ?: [];
        usort($dirs, static fn(string $a,string $b): int => version_compare(basename($b), basename($a)));
        $engine = $dirs ? basename($dirs[0]) : '';
    }
    if (!preg_match('/^\d+\.\d+\.\d+$/', $engine)) throw new RuntimeException('No usable updater engine found.');
    define('SOKNA_UPDATER_ENGINE_VERSION', $engine);
    define('SOKNA_UPDATER_ENGINE_DIR', $root . '/includes/updater_engine/' . $engine);
    require SOKNA_UPDATER_ENGINE_DIR . '/runtime.php';
    $legacyHashes = [];
}

$pending = updater_prepare_package($package, basename($package), 1);
$job = updater_start((string)$pending['id'], 1);
for ($i = 0; $i < 400 && in_array((string)($job['status'] ?? ''), ['running','recovery_required'], true); $i++) {
    $job = updater_job_read((string)$job['id'], 1);
    $job = updater_step_job((string)$job['id'], 1);
}
if (($job['status'] ?? '') !== 'completed') throw new RuntimeException('Release update failed: ' . json_encode($job, JSON_UNESCAPED_UNICODE));
if (trim((string)file_get_contents($root . '/VERSION.txt')) !== $targetVersion) throw new RuntimeException('Target version was not installed.');
if (is_file($root . '/storage/maintenance.json')) throw new RuntimeException('Maintenance flag remained after successful update.');
if (!is_file($root . '/admin/update/index.php')) throw new RuntimeException('Stable updater loader was not installed.');
foreach (['runtime.php','console.php'] as $file) if (!is_file($root . '/includes/updater_engine/' . $targetEngine . '/' . $file)) throw new RuntimeException('Target updater engine is incomplete.');

// During a transition from 1.26, the executing legacy runtime must remain byte-identical until the request ends.
foreach ($legacyHashes as $relative => $sha) {
    if ($sha !== null && (!is_file($root . '/' . $relative) || !hash_equals($sha, (string)hash_file('sha256', $root . '/' . $relative)))) {
        throw new RuntimeException('Executing legacy updater changed during its own request: ' . $relative);
    }
}

// First request through the new loader activates the versioned engine and retires the 1.26 runtime.
$command = 'REQUEST_METHOD=GET ' . escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($root . '/admin/update/index.php');
exec($command . ' >/dev/null 2>&1', $output, $exitCode);
if ($exitCode !== 0) throw new RuntimeException('New updater loader did not execute.');
$pointer = json_decode((string)file_get_contents($root . '/storage/updater-engine.json'), true, 16, JSON_THROW_ON_ERROR);
if (($pointer['current'] ?? '') !== $targetEngine) throw new RuntimeException('Target updater engine was not activated.');
foreach (['admin/update.php','includes/updater_runtime.php'] as $legacy) if (is_file($root . '/' . $legacy)) throw new RuntimeException('Legacy updater remains after in-app transition: ' . $legacy);

printf("release acceptance passed: %s -> %s, engine %s\n", $beforeVersion, $targetVersion, $targetEngine);
