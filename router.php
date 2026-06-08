<?php

declare(strict_types=1);

/*
 * Local dev router for the built-in server:
 *
 *     php -S 127.0.0.1:8000 router.php
 *
 * Mirrors .htaccess — real files (assets, auth/google/*.php, …) are served or
 * executed directly; everything else is routed through the front controller.
 * Not used in production (Apache uses .htaccess); harmless if copied there.
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$abs  = realpath(__DIR__ . $path);

if (
    $path !== '/'
    && $abs !== false
    && is_file($abs)
    && str_starts_with($abs, __DIR__ . DIRECTORY_SEPARATOR)
    && basename($abs) !== 'router.php'
) {
    return false; // let the built-in server serve/execute the real file
}

require __DIR__ . '/index.php';
