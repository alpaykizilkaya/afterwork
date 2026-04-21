<?php

declare(strict_types=1);

$localConfig = __DIR__ . '/config.local.php';

if (is_file($localConfig)) {
    require $localConfig;
    return;
}

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: '');
define('DB_USER', getenv('DB_USER') ?: '');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');
