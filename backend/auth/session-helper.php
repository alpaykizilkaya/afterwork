<?php

declare(strict_types=1);

/**
 * Returns the appropriate "home" URL for the current session.
 * Signed-in users go to their role's panel; anonymous users get the landing page.
 * Caller must have started the session before using this.
 */
function afterwork_home_url(): string
{
    $role = $_SESSION['account']['role'] ?? '';

    if ($role === 'employer') {
        return '/isveren-panel.php';
    }

    if ($role === 'seeker') {
        return '/seeker-panel.php';
    }

    return '/index.php#ana-sayfa';
}

/**
 * Reads the current account's is_verified flag from the DB and syncs
 * $_SESSION['account']['is_verified'] with the fresh value. Returns true if verified.
 *
 * Why: login sets the flag once; after that a manual DB update, a verify-email
 * click, or a resend can change the value — but the session won't notice until
 * the user logs out and back in. This refreshes on every panel load so those
 * changes take effect immediately.
 */
function refresh_verification_flag(PDO $pdo): bool
{
    $accountId = (int) ($_SESSION['account']['account_id'] ?? 0);
    if ($accountId <= 0) {
        return (int) ($_SESSION['account']['is_verified'] ?? 0) === 1;
    }
    try {
        $stmt = $pdo->prepare('SELECT is_verified FROM accounts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $accountId]);
        $verified = (int) ($stmt->fetchColumn() ?: 0) === 1;
        $_SESSION['account']['is_verified'] = $verified ? 1 : 0;
        return $verified;
    } catch (Throwable) {
        return (int) ($_SESSION['account']['is_verified'] ?? 0) === 1;
    }
}
