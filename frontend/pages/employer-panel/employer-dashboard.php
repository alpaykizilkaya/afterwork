<?php

declare(strict_types=1);

session_start();

if (
    !isset($_SESSION['account'])
    || !is_array($_SESSION['account'])
    || (string) ($_SESSION['account']['role'] ?? '') !== 'employer'
) {
    header('Location: auth.php#giris');
    exit;
}

$employer = isset($_SESSION['employer']) && is_array($_SESSION['employer']) ? $_SESSION['employer'] : [];
$companyName = trim((string) ($employer['company_name'] ?? ''));
if ($companyName === '') {
    $companyName = 'Is Veren';
}
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AFTERWORK | Is Veren Alani</title>
  <link rel="stylesheet" href="frontend/assets/css/employer-panel.css?v=<?= filemtime(__DIR__ . '/../../assets/css/employer-panel.css') ?>">
</head>
<body>
  <main class="employer-page">
    <header class="employer-topbar">
      <a class="employer-brand" href="index.php#ana-sayfa" aria-label="Ana sayfaya don">
        <img src="frontend/assets/images/afterwork-logo.png" alt="Afterwork">
      </a>
      <a class="employer-exit" href="auth.php#giris">Cikis</a>
    </header>

    <section class="employer-hero" aria-label="Is veren acilis">
      <p class="hero-kicker">Is Veren Alani</p>
      <h1>Ilk adimlari tamamla, dogru adayi daha hizli bul.</h1>
      <p class="hero-copy">
        Hos geldin, <?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?>.
        Bu ekran, is veren deneyiminin temel girisi. Sonraki adimda ilan olusturma ve aday yonetimi ekranlarini buradan buyutecegiz.
      </p>
    </section>

    <section class="employer-grid" aria-label="Ilk adim kartlari">
      <article class="employer-card card-primary">
        <h2>Sirket profili</h2>
        <p>Marka kimligini tamamla. Adaylarin guven duydugu net bir profil olustur.</p>
        <button type="button">Profili Duzenle</button>
      </article>

      <article class="employer-card">
        <h2>Ilk ilani hazirla</h2>
        <p>Pozisyon, lokasyon ve calisma modelini belirterek ilan taslagini olustur.</p>
        <button type="button">Ilan Baslat</button>
      </article>

      <article class="employer-card">
        <h2>Basvuru kutusu</h2>
        <p>Basvuran adaylari oncelik sirasina gore gor ve sureci tek noktadan yonet.</p>
        <button type="button">Kutuyu Ac</button>
      </article>
    </section>
  </main>
</body>
</html>
