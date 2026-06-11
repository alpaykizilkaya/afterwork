<?php

declare(strict_types=1);

/*
 * Start (or reopen) a conversation with another account, then jump to Mesajlar.
 * GET account=<id>  the account to message
 *     listing=<id>  optional listing the conversation is about (context only)
 *
 * Role-aware: an employer messaging a seeker fills the employer/seeker slots
 * one way; a seeker messaging an employer fills them the other way. Either way
 * a single thread per (employer_account_id, seeker_account_id) pair is reused.
 */

session_start();

// DEV BYPASS — localhost only. Shared by both roles, so don't override an
// existing session (a seeker initiating a message must stay a seeker).
if (
    in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', 'localhost:8000', '127.0.0.1', '127.0.0.1:8000'], true)
    && !isset($_SESSION['account'])
) {
    $_SESSION['account']  = ['account_id' => 0, 'email' => 'dev@localhost', 'role' => 'employer', 'is_verified' => 1];
    $_SESSION['employer'] = ['id' => 0, 'account_id' => 0, 'email' => 'dev@localhost', 'company_name' => 'Dev Şirket', 'role' => 'employer'];
}

$role = (string) ($_SESSION['account']['role'] ?? '');
if (
    !isset($_SESSION['account'])
    || !is_array($_SESSION['account'])
    || !in_array($role, ['employer', 'seeker'], true)
) {
    header('Location: /auth.php#giris');
    exit;
}

require_once __DIR__ . '/../../../backend/config/db.php';

$isSeeker = $role === 'seeker';
$me       = (int) ($_SESSION['account']['account_id'] ?? -1);
$target   = (int) ($_GET['account'] ?? 0);
$listing  = (int) ($_GET['listing'] ?? 0);
$listing  = $listing > 0 ? $listing : null;

// The thread slots: I'm the employer side, or (if I'm a seeker) the seeker side.
$empAcc  = $isSeeker ? $target : $me;
$seekAcc = $isSeeker ? $me : $target;
$side    = $isSeeker ? 'verenler' : 'basvuranlar';

if ($me < 0 || $target <= 0 || $target === $me) {
    header('Location: ' . ($isSeeker ? '/akis.php' : '/akis.php'));
    exit;
}

$cid = 0;
try {
    $pdo = db();

    $exists = $pdo->prepare('SELECT 1 FROM accounts WHERE id = :t LIMIT 1');
    $exists->execute(['t' => $target]);
    if (!$exists->fetchColumn()) {
        header('Location: /akis.php');
        exit;
    }

    $find = $pdo->prepare(
        'SELECT id FROM conversations WHERE employer_account_id = :e AND seeker_account_id = :s LIMIT 1'
    );
    $find->execute(['e' => $empAcc, 's' => $seekAcc]);
    $cid = (int) ($find->fetchColumn() ?: 0);

    if ($cid === 0) {
        $pdo->prepare(
            'INSERT INTO conversations (employer_account_id, seeker_account_id, listing_id, created_at)
             VALUES (:e, :s, :l, NOW())'
        )->execute(['e' => $empAcc, 's' => $seekAcc, 'l' => $listing]);
        $cid = (int) $pdo->lastInsertId();
    }
} catch (Throwable) {
    header('Location: /akis.php');
    exit;
}

header('Location: /mesajlar.php?side=' . $side . '&c=' . $cid);
exit;
