<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../../backend/config/db.php';
require_once __DIR__ . '/../../../backend/auth/email-verification.php';
require_once __DIR__ . '/../../../backend/auth/session-helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . afterwork_home_url());
    exit;
}

if (!isset($_SESSION['account']) || !is_array($_SESSION['account'])) {
    header('Location: /auth.php#giris');
    exit;
}

$account = $_SESSION['account'];
$email = (string) ($account['email'] ?? '');
$returnUrl = afterwork_home_url();

if ($email === '') {
    header('Location: ' . $returnUrl);
    exit;
}

try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT is_verified FROM accounts WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int) ($account['account_id'] ?? 0)]);
    $row = $stmt->fetch();

    if ($row && (int) $row['is_verified'] === 1) {
        $_SESSION['account']['is_verified'] = 1;
        $_SESSION['flash_verify'] = ['type' => 'info', 'text' => 'Hesabın zaten doğrulanmış.'];
        header('Location: ' . $returnUrl);
        exit;
    }

    $sinceLast = seconds_since_last_verification_request($email);
    $cooldown = verification_resend_cooldown_seconds();

    if ($sinceLast !== null && $sinceLast < $cooldown) {
        $wait = $cooldown - $sinceLast;
        $_SESSION['flash_verify'] = [
            'type' => 'error',
            'text' => 'Yeniden göndermek için ' . $wait . ' saniye beklemelisin.',
        ];
        header('Location: ' . $returnUrl);
        exit;
    }

    $sent = send_verification_email($email);

    $_SESSION['flash_verify'] = $sent
        ? ['type' => 'success', 'text' => 'Doğrulama e-postası tekrar gönderildi. Gelen kutunu kontrol et.']
        : ['type' => 'error', 'text' => 'E-posta gönderilemedi, lütfen biraz sonra tekrar dene.'];
} catch (Throwable $e) {
    $_SESSION['flash_verify'] = [
        'type' => 'error',
        'text' => 'Bir hata oluştu: ' . $e->getMessage(),
    ];
}

header('Location: ' . $returnUrl);
exit;
