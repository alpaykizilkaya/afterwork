<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../../backend/config/db.php';

$error = null;
$success = null;
$token = trim((string) ($_GET['token'] ?? ''));
$tokenValid = false;

if ($token === '') {
    $error = 'Geçersiz veya eksik sıfırlama bağlantısı.';
} else {
    try {
        $pdo = db();
        $stmt = $pdo->prepare(
            'SELECT email FROM password_resets WHERE token = :token AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();

        if (!$row) {
            $error = 'Bu sıfırlama bağlantısı geçersiz veya süresi dolmuş. Lütfen tekrar talep et.';
        } else {
            $tokenValid = true;
            $tokenEmail = $row['email'];
        }
    } catch (Throwable $e) {
        $error = 'Bir hata oluştu: ' . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    $newPassword = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['password_confirm'] ?? '');

    if (strlen($newPassword) < 6) {
        $error = 'Şifre en az 6 karakter olmalı.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Şifreler eşleşmiyor.';
    } else {
        try {
            $pdo = db();

            $hash = password_hash($newPassword, PASSWORD_BCRYPT);

            $updateStmt = $pdo->prepare(
                'UPDATE accounts SET password = :password WHERE email = :email'
            );
            $updateStmt->execute([
                'password' => $hash,
                'email' => $tokenEmail,
            ]);

            $deleteStmt = $pdo->prepare('DELETE FROM password_resets WHERE token = :token');
            $deleteStmt->execute(['token' => $token]);

            $success = 'Şifren başarıyla güncellendi. Şimdi giriş yapabilirsin.';
            $tokenValid = false;
        } catch (Throwable $e) {
            $error = 'Bir hata oluştu: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AFTERWORK | Şifre Sıfırla</title>
  <link rel="stylesheet" href="frontend/assets/css/auth.css?v=<?= filemtime(__DIR__ . '/../../assets/css/auth.css') ?>">
</head>
<body>
  <main class="auth-page">
    <a class="auth-brand" href="index.php#ana-sayfa" aria-label="Ana sayfaya dön">
      <img src="frontend/assets/images/afterwork-logo.png" alt="Afterwork">
    </a>

    <section class="auth-shell auth-shell--centered" aria-label="Şifre sıfırlama">
      <section class="auth-card">
        <?php if ($success !== null): ?>
          <p class="auth-feedback is-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <?php if ($error !== null): ?>
          <div class="auth-feedback is-error" role="alert">
            <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
          </div>
        <?php endif; ?>

        <p class="auth-panel-kicker">Şifre sıfırlama</p>
        <h2 class="auth-centered-heading">Yeni şifre belirle</h2>
        <p class="auth-centered-lead">Hesabın için yeni bir şifre oluştur.</p>

        <?php if ($tokenValid): ?>
          <div class="auth-login-stack">
            <form class="auth-form" action="reset-password.php?token=<?= urlencode($token) ?>" method="post">
              <label for="reset-password">Yeni şifren</label>
              <input
                id="reset-password"
                name="password"
                type="password"
                autocomplete="new-password"
                placeholder="En az 6 karakter"
                required
                minlength="6"
              >

              <label for="reset-password-confirm">Şifreni tekrar gir</label>
              <input
                id="reset-password-confirm"
                name="password_confirm"
                type="password"
                autocomplete="new-password"
                placeholder="Şifreni tekrar gir"
                required
                minlength="6"
              >

              <button type="submit" class="auth-submit">Şifremi güncelle</button>
            </form>
          </div>
        <?php endif; ?>

        <p class="auth-back-link">
          <a href="auth.php#giris">← Giriş sayfasına dön</a>
        </p>
      </section>
    </section>
  </main>
</body>
</html>
