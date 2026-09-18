<?php
return [
    'db' => [
        'host' => 'localhost',
        'port' => '3306',
        'name' => 'cafe_ordering',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'relay' => [
        'enabled' => false,
        'public_base_url' => '',
        'installation_id' => '',
        'shared_secret' => '', // installer/pairing managed; never commit a real secret
        'timeout_seconds' => 8,
    ],
    'app' => [
        'url' => '',
        'key' => 'CHANGE_ME_TO_A_RANDOM_SECRET',
        'timezone' => 'Asia/Tehran',
        'debug' => false,
        'trust_proxy_headers' => false,
        'data_dir' => '', // Production Windows: C:\\ProgramData\\SOKNA (installer managed)
        'local_hostname' => 'sokna.local',
    ],
];
