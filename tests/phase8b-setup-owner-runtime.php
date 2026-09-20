<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/setup_install.php';

function t8b(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}

$input = [
    'db'=>[
        'host'=>'localhost',
        'port'=>'3306',
        'name'=>'sokna',
        'user'=>'u',
        'pass'=>'secret',
    ],
    'app_url'=>'https://sokna.local',
    'cafe_name'=>'Cafe',
    'admin_user'=>'admin',
    'admin_password'=>'long-enough-password',
    'table_count'=>4,
    'data_dir'=>'C:\ProgramData\SOKNA',
    'local_hostname'=>'sokna.local',
    'relay'=>[
        'enabled'=>true,
        'public_base_url'=>'https://public.example.test',
        'installation_id'=>'install-1',
        'shared_secret'=>str_repeat('s',48),
    ],
];

$errors = sokna_setup_validate_input($input, 'new');
t8b($errors === [], 'valid new setup rejected: ' . implode('|', $errors));

$config = sokna_setup_config($input);
t8b(($config['relay']['enabled'] ?? false) === true, 'relay pairing not carried');
t8b(($config['relay']['shared_secret'] ?? '') === str_repeat('s',48), 'relay secret changed');
t8b(($config['app']['data_dir'] ?? '') === 'C:\ProgramData\SOKNA', 'data root missing');
t8b(($config['app']['local_hostname'] ?? '') === 'sokna.local', 'hostname missing');

$recover = $input;
unset($recover['admin_user'], $recover['admin_password'], $recover['cafe_name'], $recover['table_count']);
t8b(sokna_setup_validate_input($recover, 'recover') === [], 'recover validation incorrectly requires new-install admin');

$bad = $input;
$bad['relay']['shared_secret'] = 'short';
t8b(sokna_setup_validate_input($bad, 'new') !== [], 'short relay secret accepted');

echo "Phase 8B setup owner runtime PASS.\n";
