<?php

declare(strict_types=1);

session_start();

// DEV BYPASS — localhost only, remove before pushing to production
if (in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', 'localhost:8000', '127.0.0.1', '127.0.0.1:8000'], true)) {
    $_SESSION['account'] = ['account_id' => 0, 'email' => 'dev@localhost', 'role' => 'seeker'];
    $_SESSION['seeker']  = ['id' => 0, 'account_id' => 0, 'email' => 'dev@localhost', 'full_name' => 'Dev Kullanıcı', 'role' => 'seeker'];
}

if (
    !isset($_SESSION['account'])
    || !is_array($_SESSION['account'])
    || (string) ($_SESSION['account']['role'] ?? '') !== 'seeker'
) {
    header('Location: auth.php#giris');
    exit;
}

require_once __DIR__ . '/../../../backend/auth/session-helper.php';

$seeker = isset($_SESSION['seeker']) && is_array($_SESSION['seeker']) ? $_SESSION['seeker'] : [];
$fullName = trim((string) ($seeker['full_name'] ?? ''));
if ($fullName === '') {
    $fullName = 'Aday';
}
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AFTERWORK | Is Bulan Alani</title>
  <link rel="stylesheet" href="frontend/assets/css/seeker-panel.css?v=<?= filemtime(__DIR__ . '/../../assets/css/seeker-panel.css') ?>">
  <link rel="stylesheet" href="frontend/assets/css/logout-modal.css?v=<?= filemtime(__DIR__ . '/../../assets/css/logout-modal.css') ?>">
</head>
<body>
  <main class="seeker-page">
    <header class="seeker-topbar">
      <a class="seeker-brand" href="<?= htmlspecialchars(afterwork_home_url(), ENT_QUOTES, 'UTF-8') ?>" aria-label="Ana sayfaya don">
        <img src="frontend/assets/images/afterwork-logo.png" alt="Afterwork">
      </a>
      <button type="button" class="seeker-exit" data-logout-trigger>Çıkış Yap</button>
    </header>

    <section class="seeker-hero" aria-label="Is bulan acilis">
      <p class="hero-kicker">Is Bulan Alani</p>
      <h1>Hos geldin, <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>.</h1>
      <p class="hero-copy">
        Profilini tamamla, sana uygun ilanlari filtrele ve tek panelden basvuru surecini takip et.
      </p>
    </section>
  </main>

  <div id="logout-modal" class="logout-modal" role="dialog" aria-modal="true" aria-labelledby="logout-modal-title">
    <div class="logout-modal__backdrop" aria-hidden="true"></div>
    <div class="logout-modal__card" role="document">
      <p class="logout-modal__kicker">Çıkış Yap</p>
      <h2 id="logout-modal-title" class="logout-modal__title">Emin misin?</h2>
      <p class="logout-modal__lead">Hesabından çıkış yapmak üzeresin. Devam edersen tekrar giriş yapman gerekecek.</p>
      <div class="logout-modal__actions">
        <form class="logout-modal__form" action="/logout.php" method="post">
          <input type="hidden" name="confirm" value="yes">
          <button type="submit" class="logout-modal__btn logout-modal__btn--danger">Evet, çıkış yap</button>
        </form>
        <button type="button" class="logout-modal__btn logout-modal__btn--ghost" data-logout-close>Vazgeç</button>
      </div>
    </div>
  </div>

  <script src="frontend/assets/js/logout-modal.js?v=<?= filemtime(__DIR__ . '/../../assets/js/logout-modal.js') ?>" defer></script>
</body>
</html>
