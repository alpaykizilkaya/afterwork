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
