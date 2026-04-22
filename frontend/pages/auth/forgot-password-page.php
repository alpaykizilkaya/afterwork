<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../../backend/config/db.php';
require_once __DIR__ . '/../../../backend/mail/mailer.php';

$error = null;
$success = null;
$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailValue = trim((string) ($_POST['email'] ?? ''));

    if (!filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
        $error = 'Geçerli bir e-posta adresi gir.';
    } else {
        try {
            $pdo = db();

            $stmt = $pdo->prepare('SELECT id FROM accounts WHERE email = :email LIMIT 1');
            $stmt->execute(['email' => $emailValue]);
            $account = $stmt->fetch();

            // Always show success to prevent email enumeration
            $success = 'Eğer bu e-posta adresine kayıtlı bir hesap varsa, şifre sıfırlama bağlantısı gönderildi. Gelen kutunu kontrol et.';

            if ($account) {
                // Remove any existing tokens for this email
                $deleteStmt = $pdo->prepare('DELETE FROM password_resets WHERE email = :email');
                $deleteStmt->execute(['email' => $emailValue]);

                // Generate a secure random token
                $token = bin2hex(random_bytes(32));

                // Let MySQL set expires_at so SELECT NOW() and the stored expiry use the same clock.
                $insertStmt = $pdo->prepare(
                    'INSERT INTO password_resets (email, token, expires_at) VALUES (:email, :token, DATE_ADD(NOW(), INTERVAL 30 MINUTE))'
                );
                $insertStmt->execute([
                    'email' => $emailValue,
                    'token' => $token,
                ]);

                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $resetUrl = $protocol . '://' . $host . '/reset-password.php?token=' . urlencode($token);

                $subject = 'Afterwork — Şifre Sıfırlama';
                $textBody  = "Merhaba,\n\n";
                $textBody .= "Afterwork hesabın için şifre sıfırlama talebinde bulundun.\n\n";
                $textBody .= "Şifreni sıfırlamak için aşağıdaki bağlantıya tıkla:\n";
                $textBody .= $resetUrl . "\n\n";
                $textBody .= "Bu bağlantı 30 dakika geçerlidir.\n\n";
                $textBody .= "Eğer bu talebi sen yapmadıysan, bu e-postayı görmezden gelebilirsin.\n\n";
                $textBody .= "Afterwork Ekibi";

                $safeResetUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
                $htmlBody  = '<div style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;max-width:560px;margin:0 auto;padding:32px 24px;color:#06141b;">';
                $htmlBody .= '<h2 style="font-size:22px;margin:0 0 16px;">Şifre sıfırlama</h2>';
                $htmlBody .= '<p style="line-height:1.55;margin:0 0 16px;">Merhaba,</p>';
                $htmlBody .= '<p style="line-height:1.55;margin:0 0 16px;">Afterwork hesabın için şifre sıfırlama talebinde bulundun. Şifreni sıfırlamak için aşağıdaki butona tıkla:</p>';
                $htmlBody .= '<p style="margin:24px 0;"><a href="' . $safeResetUrl . '" style="display:inline-block;background:#11212d;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:12px;font-weight:600;">Şifreni sıfırla</a></p>';
                $htmlBody .= '<p style="line-height:1.55;margin:0 0 16px;font-size:14px;color:#4b5b66;">Bu bağlantı 30 dakika geçerlidir. Eğer bu talebi sen yapmadıysan, bu e-postayı görmezden gelebilirsin.</p>';
                $htmlBody .= '<p style="line-height:1.55;margin:24px 0 0;font-size:14px;color:#4b5b66;">Afterwork Ekibi</p>';
                $htmlBody .= '</div>';

                send_mail($emailValue, $subject, $textBody, $htmlBody);
            }
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
  <title>AFTERWORK | Şifremi Unuttum</title>
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

        <p class="auth-panel-kicker">Şifre yenileme</p>
        <h2 class="auth-centered-heading">Şifreni mi unuttun?</h2>
        <p class="auth-centered-lead">E-posta adresini gir, sıfırlama bağlantısını gönderelim.</p>

        <?php if ($success === null): ?>
          <div class="auth-login-stack">
            <form class="auth-form" action="forgot-password.php" method="post">
              <label for="forgot-email">E-posta adresin</label>
              <input
                id="forgot-email"
                name="email"
                type="email"
                autocomplete="email"
                placeholder="ornek@eposta.com"
                value="<?= htmlspecialchars($emailValue, ENT_QUOTES, 'UTF-8') ?>"
                required
              >
              <button type="submit" class="auth-submit">Sıfırlama bağlantısı gönder</button>
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
