<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/backend/auth/session-helper.php';

// Signed-in users land on their panel, not the public landing page.
// `?home=1` is an escape hatch for previewing the public home while logged in.
if (!isset($_GET['home'])) {
    $role = $_SESSION['account']['role'] ?? '';
    if ($role === 'employer') {
        header('Location: /isveren-panel.php');
        exit;
    }
    if ($role === 'seeker') {
        header('Location: /seeker-panel.php');
        exit;
    }
}

require __DIR__ . '/frontend/pages/home/home-page.php';
