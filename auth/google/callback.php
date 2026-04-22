<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../backend/auth/google-helper.php';
require_once __DIR__ . '/../../backend/config/db.php';

function google_login_redirect(PDO $pdo, int $accountId, string $email, string $role): void
{
    $_SESSION['account'] = [
        'account_id' => $accountId,
        'email' => $email,
        'role' => $role,
    ];

    if ($role === 'employer') {
        $stmt = $pdo->prepare('SELECT id, company_name FROM employers WHERE account_id = :account_id LIMIT 1');
        $stmt->execute(['account_id' => $accountId]);
        $profile = $stmt->fetch();

        $_SESSION['employer'] = [
            'id' => $profile ? (int) $profile['id'] : null,
            'account_id' => $accountId,
            'email' => $email,
            'company_name' => $profile['company_name'] ?? '',
            'role' => 'employer',
        ];
        unset($_SESSION['seeker']);

        header('Location: /isveren-panel.php');
        exit;
    }

    $stmt = $pdo->prepare('SELECT id, full_name FROM seekers WHERE account_id = :account_id LIMIT 1');
    $stmt->execute(['account_id' => $accountId]);
    $profile = $stmt->fetch();

    $_SESSION['seeker'] = [
        'id' => $profile ? (int) $profile['id'] : null,
        'account_id' => $accountId,
        'email' => $email,
        'full_name' => $profile['full_name'] ?? '',
        'role' => 'seeker',
    ];
    unset($_SESSION['employer']);

    header('Location: /seeker-panel.php');
    exit;
}

if (isset($_GET['error'])) {
    header('Location: /auth.php?google_error=' . urlencode((string) $_GET['error']));
    exit;
}

$state = $_GET['state'] ?? '';
$expectedState = $_SESSION['google_oauth_state'] ?? '';
unset($_SESSION['google_oauth_state']);

if ($state === '' || !hash_equals($expectedState, (string) $state)) {
    header('Location: /auth.php?google_error=invalid_state');
    exit;
}

$code = $_GET['code'] ?? '';
if ($code === '') {
    header('Location: /auth.php?google_error=missing_code');
    exit;
}

try {
    $token = google_exchange_code((string) $code);
    $user = google_fetch_user($token['access_token']);
} catch (Throwable $e) {
    header('Location: /auth.php?google_error=' . urlencode($e->getMessage()));
    exit;
}

$googleId = (string) $user['sub'];
$email = strtolower(trim((string) $user['email']));
$name = trim((string) ($user['name'] ?? ''));

try {
    $pdo = db();

    $stmt = $pdo->prepare('SELECT id, email, role FROM accounts WHERE google_id = :google_id LIMIT 1');
    $stmt->execute(['google_id' => $googleId]);
    $account = $stmt->fetch();

    if ($account) {
        google_login_redirect($pdo, (int) $account['id'], $account['email'], $account['role']);
    }

    $stmt = $pdo->prepare('SELECT id, email, role FROM accounts WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $account = $stmt->fetch();

    if ($account) {
        $link = $pdo->prepare('UPDATE accounts SET google_id = :google_id WHERE id = :id');
        $link->execute(['google_id' => $googleId, 'id' => $account['id']]);

        google_login_redirect($pdo, (int) $account['id'], $account['email'], $account['role']);
    }

    $preferredRole = $_SESSION['google_preferred_role'] ?? null;
    unset($_SESSION['google_preferred_role']);

    $_SESSION['google_pending'] = [
        'google_id' => $googleId,
        'email' => $email,
        'name' => $name,
        'preferred_role' => in_array($preferredRole, ['employer', 'seeker'], true) ? $preferredRole : null,
    ];

    header('Location: /auth/google/role-select.php');
    exit;
} catch (Throwable $e) {
    header('Location: /auth.php?google_error=' . urlencode('db_error: ' . $e->getMessage()));
    exit;
}
