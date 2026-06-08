<?php

declare(strict_types=1);

/*
 * Start (or reopen) a conversation with another account, then jump to Mesajlar.
 * GET account=<id>  the account to message (a seeker or an employer)
 *     listing=<id>  optional listing the conversation is about (context only)
 *
 * The current panel user is the employer side; the target goes in the seeker
 * slot. The Mesajlar inbox resolves the counterpart's name from seekers OR
 * employers, so messaging a company shows its name just fine.
 */

session_start();

// DEV BYPASS — localhost only, remove before pushing to production
if (in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', 'localhost:8000', '127.0.0.1', '127.0.0.1:8000'], true)) {
    $_SESSION['account']  = ['account_id' => 0, 'email' => 'dev@localhost', 'role' => 'employer', 'is_verified' => 1];
    $_SESSION['employer'] = ['id' => 0, 'account_id' => 0, 'email' => 'dev@localhost', 'company_name' => 'Dev Şirket', 'role' => 'employer'];
}

if (
    !isset($_SESSION['account'])
    || !is_array($_SESSION['account'])
    || (string) ($_SESSION['account']['role'] ?? '') !== 'employer'
) {
    header('Location: /auth.php#giris');
    exit;
}

require_once __DIR__ . '/../../../backend/config/db.php';

$me      = (int) ($_SESSION['account']['account_id'] ?? -1);
$target  = (int) ($_GET['account'] ?? 0);
$listing = (int) ($_GET['listing'] ?? 0);
$listing = $listing > 0 ? $listing : null;

// Can't message yourself or a bad id — bounce back to the feed.
if ($me < 0 || $target <= 0 || $target === $me) {
    header('Location: /akis.php');
    exit;
}

$cid = 0;
try {
    $pdo = db();

    // Target must be a real account.
    $exists = $pdo->prepare('SELECT 1 FROM accounts WHERE id = :t LIMIT 1');
    $exists->execute(['t' => $target]);
    if (!$exists->fetchColumn()) {
        header('Location: /akis.php');
        exit;
    }

    // Find an existing thread (I'm the employer side) or create one.
    $find = $pdo->prepare(
        'SELECT id FROM conversations WHERE employer_account_id = :me AND seeker_account_id = :t LIMIT 1'
    );
    $find->execute(['me' => $me, 't' => $target]);
    $cid = (int) ($find->fetchColumn() ?: 0);

    if ($cid === 0) {
        $pdo->prepare(
            'INSERT INTO conversations (employer_account_id, seeker_account_id, listing_id, created_at)
             VALUES (:me, :t, :l, NOW())'
        )->execute(['me' => $me, 't' => $target, 'l' => $listing]);
        $cid = (int) $pdo->lastInsertId();
    }
} catch (Throwable $e) {
    header('Location: /akis.php');
    exit;
}

header('Location: /mesajlar.php?side=basvuranlar&c=' . $cid);
exit;
