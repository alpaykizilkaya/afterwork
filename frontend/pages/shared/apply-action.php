<?php

declare(strict_types=1);

/*
 * Apply to a listing (seeker only). POST listing_id.
 * Gated on email verification. Records the application + notifies the employer
 * via record_application(), then bounces back to the feed with a flash.
 */

session_start();

require_once __DIR__ . '/../../../backend/config/db.php';
require_once __DIR__ . '/../../../backend/auth/session-helper.php';
require_once __DIR__ . '/../../../backend/applications/record-application.php';

$back = '/akis.php';

if (
    !isset($_SESSION['account'])
    || (string) ($_SESSION['account']['role'] ?? '') !== 'seeker'
) {
    $_SESSION['flash_apply'] = ['type' => 'error', 'text' => 'Başvurmak için iş arayan hesabıyla giriş yapmalısın.'];
    header('Location: ' . $back);
    exit;
}

$me        = (int) ($_SESSION['account']['account_id'] ?? 0);
$listingId = (int) ($_POST['listing_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $listingId <= 0 || $me <= 0) {
    header('Location: ' . $back);
    exit;
}

// Email must be verified before applying.
$verified = (int) ($_SESSION['account']['is_verified'] ?? 0) === 1;
try {
    $verified = refresh_verification_flag(db());
} catch (Throwable) {
    // fall back to the session flag
}
if (!$verified) {
    $_SESSION['flash_apply'] = ['type' => 'error', 'text' => 'Başvurmadan önce e-posta adresini doğrulaman gerekiyor.'];
    header('Location: ' . $back);
    exit;
}

$new = false;
try {
    $new = record_application(db(), $listingId, $me);
} catch (Throwable) {
    $new = false;
}

$_SESSION['flash_apply'] = $new
    ? ['type' => 'success', 'text' => 'Başvurun alındı — iş verene bildirildi. 🎉']
    : ['type' => 'success', 'text' => 'Bu ilana zaten başvurmuştun.'];

header('Location: ' . $back);
exit;
