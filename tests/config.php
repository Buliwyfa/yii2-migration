<?php

/**
 * You can override configuration values by creating a `config.local.php` file
 * and manipulate the `$config` variable.
 */
$config = [
    'mysql' => [
        'dsn' => 'mysql:host=127.0.0.1;dbname=migrationtest',
        'username' => 'migration',
        'password' => 'migration',
        'charset' => 'utf8',
    ],
];
if (is_file(__DIR__ . '/config.local.php')) {
    include __DIR__ . '/config.local.php';
}
return $config;
