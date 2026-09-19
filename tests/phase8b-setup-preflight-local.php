<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/setup_install.php';
$host = getenv('SOKNA_PUBLIC_TEST_DB_HOST') ?: '127.0.0.1';
$port = getenv('SOKNA_PUBLIC_TEST_DB_PORT') ?: '3306';
$user = getenv('SOKNA_PUBLIC_TEST_DB_USER') ?: 'root';
$pass = getenv('SOKNA_PUBLIC_TEST_DB_PASS') ?: 'root';
$name = 'sokna_preflight_' . bin2hex(random_bytes(4));
$root = sys_get_temp_dir() . '/' . $name;
$admin = new PDO("mysql:host={$host};port={$port};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$admin->exec('CREATE DATABASE `' . $name . '`');
mkdir($root,0700);
try {
    $input = ['db'=>['host'=>$host,'port'=>$port,'name'=>$name,'user'=>$user,'pass'=>$pass], 'app_url'=>'https://sokna.local','admin_user'=>'admin','admin_password'=>'preflight-test-password'];
    sokna_setup_preflight($input,'new',$root);
    sokna_setup_preflight($input,'recover',$root);
    if (array_values(array_diff(scandir($root),['.','..'])) !== []) throw new RuntimeException('Preflight wrote files.');
    $pdo = sokna_setup_connect(sokna_setup_config($input));
    if ($pdo->query('SHOW TABLES')->fetchAll()) throw new RuntimeException('Preflight created tables.');
    $pdo->exec('CREATE TABLE sentinel (id INT PRIMARY KEY)');
    $pdo->exec('INSERT INTO sentinel VALUES (42)');
    $blocked = false;
    try { sokna_setup_preflight($input,'recover',$root); } catch (RuntimeException $e) { $blocked = true; }
    if (!$blocked || (int)$pdo->query('SELECT id FROM sentinel')->fetchColumn() !== 42) throw new RuntimeException('Nonempty preflight failed to preserve data.');
    echo "Phase 8B read-only setup preflight PASS.\n";
} finally {
    $admin->exec('DROP DATABASE `' . $name . '`');
    rmdir($root);
}
