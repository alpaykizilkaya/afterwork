<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../../backend/auth/session-helper.php';

if (!isset($_SESSION['account'])) {
    header('Location: /auth.php#giris');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === 'yes') {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();

    header('Location: /index.php#ana-sayfa');
    exit;
}

$cancelUrl = afterwork_home_url();
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AFTERWORK | Çıkış Yap</title>
  <link rel="stylesheet" href="frontend/assets/css/auth.css?v=<?= filemtime(__DIR__ . '/../../assets/css/auth.css') ?>">
</head>
<body>
  <main class="auth-page">
    <a class="auth-brand" href="<?= htmlspecialchars($cancelUrl, ENT_QUOTES, 'UTF-8') ?>" aria-label="Paneline dön">
      <img src="frontend/assets/images/afterwork-logo.png" alt="Afterwork">
    </a>

    <section class="auth-shell auth-shell--centered" aria-label="Çıkış onayı">
      <section class="auth-card">
        <p class="auth-panel-kicker">Çıkış yap</p>
        <h2 class="auth-centered-heading">Emin misin?</h2>
        <p class="auth-centered-lead">
          Hesabından çıkış yapmak üzeresin. Devam edersen tekrar giriş yapman gerekecek.
        </p>

        <div class="auth-login-stack">
          <form action="/logout.php" method="post">
            <input type="hidden" name="confirm" value="yes">
            <button type="submit" class="auth-submit">Evet, çıkış yap</button>
          </form>

          <p class="auth-back-link">
            <a href="<?= htmlspecialchars($cancelUrl, ENT_QUOTES, 'UTF-8') ?>">Vazgeç, panele dön</a>
          </p>
        </div>
      </section>
    </section>
  </main>
</body>
</html>
