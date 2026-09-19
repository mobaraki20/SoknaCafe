<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Tehran');
if (session_status() !== PHP_SESSION_ACTIVE) {
    $httpsRequest = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 80) === 443);
    session_start(['cookie_httponly'=>true,'cookie_secure'=>$httpsRequest,'cookie_samesite'=>'Lax','use_strict_mode'=>true,'use_only_cookies'=>true]);
}
if (empty($_SESSION['install_csrf'])) $_SESSION['install_csrf'] = bin2hex(random_bytes(32));

$root=__DIR__;$lockFile=$root.'/install.lock';$configFile=$root.'/config.php';$errors=[];$success=false;
require_once __DIR__ . '/includes/setup_install.php';

function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

function detect_url(): string
{
    $https=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||((int)($_SERVER['SERVER_PORT']??80)===443);
    $scheme=$https?'https':'http';
    $host=$_SERVER['HTTP_HOST']??'localhost';
    $dir=rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'/')),'/');
    return $scheme.'://'.$host.($dir===''?'':$dir);
}

$installed=is_file($lockFile)&&is_file($configFile);

if (($_SERVER['REQUEST_METHOD']??'GET')==='POST' && !$installed) {
    if (!hash_equals((string)($_SESSION['install_csrf']??''),(string)($_POST['csrf_token']??''))) {
        $errors[]='درخواست نصب معتبر نیست. صفحه را تازه‌سازی کنید.';
    }

    $input=[
        'db'=>[
            'host'=>trim((string)($_POST['db_host']??'localhost')),
            'port'=>trim((string)($_POST['db_port']??'3306')),
            'name'=>trim((string)($_POST['db_name']??'')),
            'user'=>trim((string)($_POST['db_user']??'')),
            'pass'=>(string)($_POST['db_pass']??''),
        ],
        'app_url'=>rtrim(trim((string)($_POST['app_url']??'')),'/'),
        'cafe_name'=>trim((string)($_POST['cafe_name']??'کافه من')),
        'admin_user'=>trim((string)($_POST['admin_user']??'admin')),
        'admin_password'=>(string)($_POST['admin_pass']??''),
        'table_count'=>max(1,min(200,(int)($_POST['table_count']??30))),
    ];

    $errors=array_merge($errors,sokna_setup_validate_input($input,'new'));
    if(!$errors){
        try{
            sokna_setup_fresh_install($input,$root);
            $success=true;
            $installed=true;
        }catch(Throwable $e){
            error_log('Cafe install error: '.$e->getMessage());
            $errors[]='نصب انجام نشد: '.$e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>نصب سامانه سفارش کافه</title>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/icons/favicon-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/icons/favicon-180.png">
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f7f3ec;color:#202522;font-family:system-ui,-apple-system,"Segoe UI",Tahoma,Arial,sans-serif;line-height:1.8}.wrap{max-width:820px;margin:32px auto;padding:16px}.card{background:#fff;border-radius:24px;padding:28px;box-shadow:0 16px 45px rgba(58,39,28,.12)}h1{margin:0 0 8px}p{color:#6d625b}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.full{grid-column:1/-1}label{display:block;font-weight:700;margin-bottom:6px}input{width:100%;padding:12px 14px;border:1px solid #d9d0ca;border-radius:12px;font:inherit}button,.btn{display:inline-block;border:0;background:#365b4c;color:#fff;padding:13px 22px;border-radius:12px;font:inherit;font-weight:700;cursor:pointer;text-decoration:none}.alert{padding:12px 16px;border-radius:12px;margin:12px 0}.error{background:#fff0f0;color:#9c2525}.success{background:#eaf8ef;color:#166534}@media(max-width:650px){.grid{grid-template-columns:1fr}.full{grid-column:auto}.card{padding:20px}}
    </style>
</head>
<body><div class="wrap"><div class="card">
    <h1>نصب سامانه سفارش کافه</h1>
    <p>در cPanel یک دیتابیس خالی MySQL و یک کاربر با دسترسی کامل به همان دیتابیس بسازید. نصب فقط حساب مدیر را ایجاد می‌کند؛ حساب شخصی اعضای تیم بعد از ورود، از بخش «اعضای تیم» ساخته می‌شود.</p>
    <?php foreach ($errors as $error): ?><div class="alert error"><?= h($error) ?></div><?php endforeach; ?>
    <?php if ($installed): ?>
        <div class="alert success"><?= $success ? 'همه‌چیز آماده‌ست؛ حالا وارد سامانه شو.' : 'سامانه قبلاً نصب شده است.' ?></div>
        <a class="btn" href="login.php">ورود به سامانه</a>
    <?php else: ?>
    <form method="post" class="grid" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['install_csrf']) ?>">
        <div><label>میزبان دیتابیس</label><input name="db_host" value="<?= h($_POST['db_host'] ?? 'localhost') ?>" required></div>
        <div><label>پورت دیتابیس</label><input name="db_port" value="<?= h($_POST['db_port'] ?? '3306') ?>" required></div>
        <div><label>نام دیتابیس</label><input name="db_name" value="<?= h($_POST['db_name'] ?? '') ?>" required></div>
        <div><label>نام کاربری دیتابیس</label><input name="db_user" value="<?= h($_POST['db_user'] ?? '') ?>" required></div>
        <div class="full"><label>رمز دیتابیس</label><input type="password" name="db_pass"></div>
        <div class="full"><label>آدرس کامل نصب</label><input type="url" name="app_url" value="<?= h($_POST['app_url'] ?? detect_url()) ?>" placeholder="https://example.com/cafe" required></div>
        <div><label>نام کافه</label><input name="cafe_name" value="<?= h($_POST['cafe_name'] ?? 'کافه من') ?>" required></div>
        <div><label>تعداد میز اولیه</label><input type="number" min="1" max="200" name="table_count" value="<?= h($_POST['table_count'] ?? '30') ?>"></div>
        <div></div>
        <div><label>نام کاربری مدیر</label><input name="admin_user" value="<?= h($_POST['admin_user'] ?? 'admin') ?>" required></div>
        <div><label>رمز مدیر</label><input type="password" name="admin_pass" minlength="8" required></div>
        <div class="full"><button type="submit">نصب و راه‌اندازی</button></div>
    </form>
    <?php endif; ?>
</div></div></body></html>
