<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../../backend/config/db.php';
require_once __DIR__ . '/../../../backend/auth/email-verification.php';
require_once __DIR__ . '/../../../backend/auth/session-helper.php';

$state = 'invalid';
$message = 'Doğrulama bağlantısı geçersiz ya da süresi dolmuş.';

$token = trim((string) ($_GET['token'] ?? ''));

if ($token !== '') {
    try {
        $pdo = db();

        $stmt = $pdo->prepare(
            'SELECT email, expires_at FROM email_verifications WHERE token = :token LIMIT 1'
        );
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();

        if ($row) {
            $expired = strtotime((string) $row['expires_at']) < time();
            $email = (string) $row['email'];

            if ($expired) {
                $pdo->prepare('DELETE FROM email_verifications WHERE token = :token')
                    ->execute(['token' => $token]);

                $state = 'expired';
                $message = 'Bu doğrulama bağlantısının süresi dolmuş. Panelinden "Yeniden gönder" diyerek yeni bir bağlantı isteyebilirsin.';
            } else {
                $accountStmt = $pdo->prepare(
                    'SELECT id, is_verified FROM accounts WHERE email = :email LIMIT 1'
                );
                $accountStmt->execute(['email' => $email]);
                $account = $accountStmt->fetch();

                if ($account) {
                    if ((int) $account['is_verified'] === 1) {
                        $state = 'already';
                        $message = 'E-posta adresin zaten doğrulanmış. Giriş yapabilirsin.';
                    } else {
                        mark_account_verified($pdo, (int) $account['id']);
                        $state = 'success';
                        $message = 'Harika! E-posta adresin doğrulandı. Artık tüm özellikleri kullanabilirsin.';
                    }

                    if (isset($_SESSION['account']['email']) && $_SESSION['account']['email'] === $email) {
                        $_SESSION['account']['is_verified'] = 1;
                    }
                }

                $pdo->prepare('DELETE FROM email_verifications WHERE email = :email')
                    ->execute(['email' => $email]);
            }
        }
    } catch (Throwable $e) {
        $state = 'error';
        $message = 'Bir hata oluştu: ' . $e->getMessage();
    }
}

$ctaHref = afterwork_home_url();
$ctaLabel = isset($_SESSION['account']['role']) ? 'Panele git' : 'Giriş yap';
if (!isset($_SESSION['account']['role'])) {
    $ctaHref = '/auth.php#giris';
}

$kicker = match ($state) {
    'success' => 'Tebrikler',
    'already' => 'Zaten doğrulandı',
    'expired' => 'Süresi dolmuş',
    default   => 'Doğrulanamadı',
};

$heading = match ($state) {
    'success' => 'E-postan doğrulandı',
    'already' => 'Bu hesap zaten doğrulanmış',
    'expired' => 'Bağlantının süresi dolmuş',
    default   => 'Bağlantı geçersiz',
};
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AFTERWORK | E-posta Doğrulama</title>
  <link rel="stylesheet" href="frontend/assets/css/auth.css?v=<?= filemtime(__DIR__ . '/../../assets/css/auth.css') ?>">
</head>
<body>
  <main class="auth-page">
    <a class="auth-brand" href="<?= htmlspecialchars(afterwork_home_url(), ENT_QUOTES, 'UTF-8') ?>" aria-label="Ana sayfaya dön">
      <img src="frontend/assets/images/afterwork-logo.png" alt="Afterwork">
    </a>

    <section class="auth-shell auth-shell--centered" aria-label="E-posta doğrulama">
      <section class="auth-card">
        <p class="auth-panel-kicker"><?= htmlspecialchars($kicker, ENT_QUOTES, 'UTF-8') ?></p>
        <h2 class="auth-centered-heading"><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="auth-centered-lead"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>

        <div class="auth-login-stack">
          <a class="auth-submit" href="<?= htmlspecialchars($ctaHref, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8') ?></a>
        </div>
      </section>
    </section>
  </main>
</body>
</html>
