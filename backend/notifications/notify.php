<?php

declare(strict_types=1);

/**
 * Notification producer.
 *
 * notify_account() drops one in-app notification targeted at a single account
 * (shows up in that user's topbar bell). This is the ONE hook every event
 * should call: new message, new application, an email the app just sent, etc.
 *
 * Usage:
 *   require_once __DIR__ . '/../../backend/notifications/notify.php';
 *   notify_account($pdo, $recipientAccountId, 'Yeni mesaj', 'Ali sana yazdı', '/mesajlar.php?c=12');
 *
 * Best-effort by design: a notification must never break the action that
 * triggered it, so every failure is swallowed.
 */
if (!function_exists('notify_account')) {
    function notify_account(PDO $pdo, int $accountId, string $title, string $body = '', ?string $url = null): void
    {
        if ($accountId <= 0 || trim($title) === '') {
            return;
        }
        try {
            $pdo->prepare(
                'INSERT INTO notifications (title, body, account_id, url, audience)
                 VALUES (:t, :b, :a, :u, "all")'
            )->execute([
                't' => mb_substr($title, 0, 160, 'UTF-8'),
                'b' => mb_substr($body, 0, 500, 'UTF-8'),
                'a' => $accountId,
                'u' => $url !== null && $url !== '' ? mb_substr($url, 0, 255, 'UTF-8') : null,
            ]);
        } catch (Throwable) {
            // migrations not applied yet / transient DB error — never block the caller
        }
    }
}
