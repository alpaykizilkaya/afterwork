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
</head>
<body>
  <main class="seeker-page">
    <header class="seeker-topbar">
      <a class="seeker-brand" href="index.php#ana-sayfa" aria-label="Ana sayfaya don">
        <img src="frontend/assets/images/afterwork-logo.png" alt="Afterwork">
      </a>
      <a class="seeker-exit" href="auth.php#giris">Cikis</a>
    </header>

    <section class="seeker-hero" aria-label="Is bulan acilis">
      <p class="hero-kicker">Is Bulan Alani</p>
      <h1>Hos geldin, <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>.</h1>
      <p class="hero-copy">
        Profilini tamamla, sana uygun ilanlari filtrele ve tek panelden basvuru surecini takip et.
      </p>
    </section>
  </main>
</body>
</html>
