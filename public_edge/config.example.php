<?php
return [
    'db'=>[
        'host'=>'localhost','port'=>'3306','name'=>'sokna_public','user'=>'CHANGE_ME','pass'=>'CHANGE_ME','charset'=>'utf8mb4',
    ],
    'app'=>[
        'debug'=>false,
        'session_ttl_seconds'=>28800,
        'relay_clock_skew_seconds'=>300,
        'emergency_key'=>'CHANGE_ME_TO_A_SEPARATE_RANDOM_SECRET',
    ],
    // Secrets intentionally live outside the database. installation_id => shared secret.
    'installation_secrets'=>[
        'CHANGE_ME_INSTALLATION_ID'=>'CHANGE_ME_64_PLUS_RANDOM_CHARS',
    ],
];
