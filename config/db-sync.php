<?php

return [
    'ssh' => [
        'host' => env('PROD_SSH_HOST'),
        'user' => env('PROD_SSH_USER', 'root'),
        'port' => env('PROD_SSH_PORT', 22),
    ],

    'database' => [
        'name' => env('PROD_DB_NAME'),
        'user' => env('PROD_DB_USER', 'postgres'),
        'password' => env('PROD_DB_PASSWORD'),
        'host' => env('PROD_DB_HOST', 'localhost'),
        'port' => env('PROD_DB_PORT', 5432),
    ],

    // Dump fayllar saqlanadigan papka
    'dump_path' => storage_path('app/db-sync'),

    // Timeout (soniya)
    'timeout' => env('DB_SYNC_TIMEOUT', 3600),
];