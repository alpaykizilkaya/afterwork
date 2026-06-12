<?php

declare(strict_types=1);

/*
 * Save / unsave a listing (seeker only) — toggles a row in listing_saves.
 * POST listing_id [, return]. Feeds the seeker panel "Cebimdekiler" section.
 */

session_start();

require_once __DIR__ . '/../../../backend/config/db.php';

$back = '/akis.php';
$ret  = (string) ($_POST['return'] ?? '');
if ($ret !== '' && $ret[0] === '/' && !str_starts_with($ret, '//')) {
    $back = $ret;
}

if (
    !isset($_SESSION['account'])
    || (string) ($_SESSION['account']['role'] ?? '') !== 'seeker'
) {
    $_SESSION['flash_save'] = ['type' => 'error', 'text' => 'Kaydetmek için iş arayan hesabıyla giriş yapmalısın.'];
    header('Location: ' . $back);
    exit;
}

$me        = (int) ($_SESSION['account']['account_id'] ?? 0);
$listingId = (int) ($_POST['listing_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $listingId <= 0 || $me <= 0) {
    header('Location: ' . $back);
    exit;
}

try {
    $pdo = db();
    // listing must exist & be active
    $ok = $pdo->prepare('SELECT 1 FROM job_listings WHERE id = :id AND is_active = 1 LIMIT 1');
    $ok->execute(['id' => $listingId]);
    if (!$ok->fetchColumn()) {
        header('Location: ' . $back);
        exit;
    }

    // toggle
    $del = $pdo->prepare('DELETE FROM listing_saves WHERE listing_id = :l AND seeker_account_id = :s');
    $del->execute(['l' => $listingId, 's' => $me]);
    if ($del->rowCount() > 0) {
        $_SESSION['flash_save'] = ['type' => 'success', 'text' => 'İlan cebinden çıkarıldı.'];
    } else {
        $pdo->prepare('INSERT INTO listing_saves (listing_id, seeker_account_id) VALUES (:l, :s)')
            ->execute(['l' => $listingId, 's' => $me]);
        $_SESSION['flash_save'] = ['type' => 'success', 'text' => 'İlan cebine eklendi — Cebimdekiler\'de. 👜'];
    }
} catch (Throwable) {
    $_SESSION['flash_save'] = ['type' => 'error', 'text' => 'İşlem tamamlanamadı, tekrar dene.'];
}

header('Location: ' . $back);
exit;
