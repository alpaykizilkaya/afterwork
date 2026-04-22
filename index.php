<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/backend/auth/session-helper.php';

require __DIR__ . '/frontend/pages/home/home-page.php';
