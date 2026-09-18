<?php
declare(strict_types=1);

$project = dirname(__DIR__);
$root = sys_get_temp_dir() . '/sokna-updater-canonical-' . bin2hex(random_bytes(5));
$mkdir = static function(string $path): void { if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) throw new RuntimeException('mkdir failed'); };
foreach (['includes/updater_engine/1.5.3','admin/update','api','storage','uploads'] as $dir) $mkdir($root . '/' . $dir);
foreach (['runtime.php','console.php'] as $file) copy($project . '/includes/updater_engine/1.5.3/' . $file, $root . '/includes/updater_engine/1.5.3/' . $file);
copy($project . '/admin/update/index.php', $root . '/admin/update/index.php');
copy($project . '/includes/maintenance.php', $root . '/includes/maintenance.php');
file_put_contents($root . '/VERSION.txt', "1.0.0\n");
foreach (['bootstrap.php','index.php','login.php'] as $file) file_put_contents($root . '/' . $file, "<?php\n");
foreach (['functions.php','auth.php','panel_layout.php'] as $file) file_put_contents($root . '/includes/' . $file, "<?php\n");
file_put_contents($root . '/api/create_order.php', "<?php\n");
file_put_contents($root . '/app.txt', "old\n");
file_put_contents($root . '/legacy.txt', "remove\n");
file_put_contents($root . '/config.php', "<?php return ['db'=>['host'=>'localhost','name'=>'x','user'=>'x','pass'=>'x']];\n");

define('SOKNA_UPDATER_ROOT', $root);
define('SOKNA_UPDATER_ENGINE_VERSION', '1.5.3');
define('SOKNA_UPDATER_ENGINE_DIR', $root . '/includes/updater_engine/1.5.3');
define('SOKNA_MAINTENANCE_ROOT', $root);
define('SOKNA_RECOVERY_ROOT', $root);
define('SOKNA_RECOVERY_TEST_SKIP_DB', true);
define('SOKNA_UPDATER_TEST_SKIP_EXTENSIONS', true);
require $project . '/includes/updater_engine/1.5.3/runtime.php';

$files = ['VERSION.txt' => "1.0.1\n", 'app.txt' => "new\n"];
$manifestFiles = [];
foreach ($files as $path => $content) $manifestFiles[] = ['path'=>$path,'sha256'=>hash('sha256',$content),'size'=>strlen($content)];
$manifest = [
    'format'=>SOKNA_UPDATER_MANIFEST_FORMAT,
    'package_type'=>'update',
    'min_updater'=>'1.5.1',
    'updater_engine'=>'1.5.3',
    'version'=>'1.0.1',
    'from_versions'=>['1.0.0'],
    'min_php'=>'8.1.0',
    'required_extensions'=>[],
    'release_notes'=>'fixture',
    'files'=>$manifestFiles,
    'delete'=>['legacy.txt'],
    'migration'=>null,
];
$tree = $root . '/package-tree'; $mkdir($tree . '/files');
file_put_contents($tree . '/release-manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT));
foreach ($files as $path=>$content) { $target=$tree.'/files/'.$path; $mkdir(dirname($target)); file_put_contents($target,$content); }
$zipBinary = trim((string)shell_exec('command -v zip 2>/dev/null'));
if ($zipBinary === '') throw new RuntimeException('zip CLI is unavailable');
$makePackage = static function(string $target) use ($tree,$zipBinary): void {
    @unlink($target); exec('cd '.escapeshellarg($tree).' && '.escapeshellcmd($zipBinary).' -q -r '.escapeshellarg($target).' .',$out,$code);
    if ($code !== 0 || !is_file($target)) throw new RuntimeException('zip creation failed');
};
$package=$root.'/release.zip'; $makePackage($package);

$pending=updater_prepare_package($package,'fixture.zip',7);
$job=updater_start((string)$pending['id'],7);
for($i=0;$i<80&&in_array((string)$job['status'],['running','recovery_required'],true);$i++) $job=updater_step_job((string)$job['id'],7);
if(($job['status']??'')!=='completed') throw new RuntimeException('update failed: '.json_encode($job,JSON_UNESCAPED_UNICODE));
if(trim((string)file_get_contents($root.'/VERSION.txt'))!=='1.0.1'||file_get_contents($root.'/app.txt')!=="new\n"||is_file($root.'/legacy.txt')) throw new RuntimeException('application update mismatch');
$pointer=updater_engine_pointer(); if(($pointer['current']??'')!=='1.5.3') throw new RuntimeException('engine pointer missing');

$points=updater_restore_points(5); if(!$points) throw new RuntimeException('restore point missing');
$rollback=updater_start_rollback((string)$points[0]['restore_id'],7);
for($i=0;$i<100&&in_array((string)$rollback['status'],['running','recovery_required'],true);$i++) $rollback=updater_step_job((string)$rollback['id'],7);
if(($rollback['status']??'')!=='completed'||trim((string)file_get_contents($root.'/VERSION.txt'))!=='1.0.0'||file_get_contents($root.'/app.txt')!=="old\n"||!is_file($root.'/legacy.txt')) throw new RuntimeException('rollback mismatch');

// Corruption after live changes must end in verified automatic rollback.
$failurePackage=$root.'/release-failure.zip';$makePackage($failurePackage);
$pendingFailure=updater_prepare_package($failurePackage,'fixture-corrupt.zip',7);$failed=updater_start((string)$pendingFailure['id'],7);$id=(string)$failed['id'];
for($i=0;$i<50&&($failed['stage']??'')!=='apply_deferred'&&($failed['status']??'')==='running';$i++)$failed=updater_step_job($id,7);
if(($failed['stage']??'')!=='apply_deferred') throw new RuntimeException('failure fixture did not reach deferred stage');
file_put_contents((string)$failed['stage_dir'].'/files/VERSION.txt',"tampered\n");
for($i=0;$i<120&&in_array((string)$failed['status'],['running','recovery_required'],true);$i++)$failed=updater_step_job($id,7);
if(($failed['status']??'')!=='failed'||($failed['stage']??'')!=='rolled_back')throw new RuntimeException('automatic rollback failed');
if(trim((string)file_get_contents($root.'/VERSION.txt'))!=='1.0.0'||is_file($root.'/storage/maintenance.json'))throw new RuntimeException('automatic rollback left unsafe state');

// The active loader and engine cannot be replaced by an ordinary package.
foreach (['admin/update/index.php','includes/updater_engine/1.5.3/runtime.php'] as $protected) {
    $bad=$manifest;$bad['version']='1.0.2';$bad['files'][]=['path'=>$protected,'sha256'=>str_repeat('a',64),'size'=>1];$thrown=false;
    try{updater_validate_manifest($bad);}catch(Throwable){$thrown=true;} if(!$thrown)throw new RuntimeException('protected updater path accepted: '.$protected);
}

echo "versioned updater integration passed\n";
