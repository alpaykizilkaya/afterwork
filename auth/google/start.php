<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../backend/auth/google-helper.php';

if (GOOGLE_CLIENT_ID === '' || GOOGLE_CLIENT_SECRET === '') {
    http_response_code(500);
    exit('Google OAuth is not configured.');
}

$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

header('Location: ' . google_auth_url($state));
exit;
