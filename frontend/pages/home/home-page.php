<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AFTERWORK</title>
  <link rel="stylesheet" href="frontend/assets/css/home.css?v=<?= filemtime(__DIR__ . '/../../assets/css/home.css') ?>">
</head>
<body>
  <div id="splash" class="splash">
    <img class="wordmark" src="frontend/assets/images/afterwork-logo.png" alt="Afterwork logo">
  </div>

  <main id="main" class="main-content" aria-hidden="true">
    <header id="ana-sayfa" class="topbar">
      <a class="logo-link" href="#hero" aria-label="Hero alana git">
        <img class="logo" src="frontend/assets/images/afterwork-logo.png" alt="Afterwork">
      </a>

      <nav class="menu" aria-label="Ana menü">
        <a href="#is-ilanlari">İş Bul</a>
        <a href="#isveren">İşverenler İçin</a>
        <a href="#internships">Staj Programları</a>
      </nav>

      <div class="actions">
        <a class="btn btn-ghost" id="giris" href="auth.php#giris">Giriş Yap</a>
        <a class="btn btn-solid" href="auth.php#kayit">Kayıt Ol</a>
      </div>
    </header>

    <section class="hero" id="hero">
      <div class="hero-main">
        <div class="hero-left">
          <h1 class="hero-title">Türkiye'de doğru işi bulmanın en premium yolu.</h1>

          <p class="hero-description">
          AFTERWORK, seçilmiş işveren profili ile kariyerindeki bir sonraki adımı en iyi şekilde atmanı sağlar. Daha az başvuru, daha doğru eşleşme. 
          </p>

          <a class="hero-cta" href="auth.php#giris">Başla</a>
        </div>

        <div class="hero-right">
          <div class="hero-media-card">
            <img src="frontend/assets/images/hero-demo.png" alt="Afterwork demo alanı">
          </div>
        </div>
      </div>

    </section>

    <section class="hero-trusted" aria-labelledby="hero-trusted-title">
      <p class="hero-trusted-kicker">Güvenen şirketler</p>
      <h2 class="hero-trusted-title" id="hero-trusted-title">Türkiye'nin seçkin ekipleri AFTERWORK'te yeteneklerle buluşuyor</h2>

      <div class="hero-trusted-marquee" aria-label="Örnek işveren logoları">
        <div class="hero-trusted-track">
          <div class="hero-trusted-logo">
            <img src="frontend/assets/images/trusted-company-placeholder.png" alt="Örnek şirket logosu">
          </div>
          <div class="hero-trusted-logo">
            <img src="frontend/assets/images/trusted-company-placeholder.png" alt="Örnek şirket logosu">
          </div>
          <div class="hero-trusted-logo">
            <img src="frontend/assets/images/trusted-company-placeholder.png" alt="Örnek şirket logosu">
          </div>
          <div class="hero-trusted-logo">
            <img src="frontend/assets/images/trusted-company-placeholder.png" alt="Örnek şirket logosu">
          </div>
          <div class="hero-trusted-logo">
            <img src="frontend/assets/images/trusted-company-placeholder.png" alt="Örnek şirket logosu">
          </div>
          <div class="hero-trusted-logo">
            <img src="frontend/assets/images/trusted-company-placeholder.png" alt="Örnek şirket logosu">
          </div>
          <div class="hero-trusted-logo" aria-hidden="true">
            <img src="frontend/assets/images/trusted-company-placeholder.png" alt="">
          </div>
          <div class="hero-trusted-logo" aria-hidden="true">
            <img src="frontend/assets/images/trusted-company-placeholder.png" alt="">
          </div>
          <div class="hero-trusted-logo" aria-hidden="true">
            <img src="frontend/assets/images/trusted-company-placeholder.png" alt="">
          </div>
          <div class="hero-trusted-logo" aria-hidden="true">
            <img src="frontend/assets/images/trusted-company-placeholder.png" alt="">
          </div>
          <div class="hero-trusted-logo" aria-hidden="true">
            <img src="frontend/assets/images/trusted-company-placeholder.png" alt="">
          </div>
          <div class="hero-trusted-logo" aria-hidden="true">
            <img src="frontend/assets/images/trusted-company-placeholder.png" alt="">
          </div>
        </div>
      </div>
    </section>

    <section class="finder-hero" id="is-ilanlari">
      <p class="section-kicker">İş Bul</p>
      <h1>Kariyer yolculuğunu doğru adımlarla başlat.</h1>

      <div class="finder-panel">
        <div class="finder-media">
          <img src="frontend/assets/images/hero-demo.png" alt="Afterwork demo alanı">
        </div>

        <div class="finder-content">
          <h2><span class="title-icon" aria-hidden="true">◉</span> Nasıl Çalışır?</h2>
          <ol class="finder-steps">
            <li>
              <span class="step-dot">1</span>
              <p><strong>Giriş Yap:</strong> Profilini oluştur ve hedeflerini belirle.</p>
            </li>
            <li>
              <span class="step-dot">2</span>
              <p><strong>Filtrele:</strong> Şehir, pozisyon ve çalışma modeline göre arama yap.</p>
            </li>
            <li>
              <span class="step-dot">3</span>
              <p><strong>Uygun İşi Bul:</strong> Sana en yakın ilanları karşılaştır.</p>
            </li>
            <li>
              <span class="step-dot">4</span>
              <p><strong>Başvur:</strong> Tek tıkla başvurunu tamamla ve süreci takip et.</p>
            </li>
          </ol>

          <h3><span class="title-icon" aria-hidden="true">✦</span> Önerilen İlanlar</h3>
          <div class="job-slider" aria-label="Önerilen iş ilanları">
            <button class="job-arrow" id="job-prev" type="button" aria-label="Önceki ilan">‹</button>
            <a class="job-card" id="job-card" href="auth.php#giris" aria-label="İlanı aç">
              <p class="job-card-title" id="job-title">Part-Time Barista</p>
              <p class="job-card-meta" id="job-meta">İstanbul • Part-Time</p>
            </a>
            <button class="job-arrow" id="job-next" type="button" aria-label="Sonraki ilan">›</button>
          </div>

          <a class="finder-start" href="auth.php#giris">Başla</a>
        </div>
      </div>
    </section>

    <section class="employer-section" id="isveren">
      <div class="employer-head">
        <p class="section-kicker">İşverenler İçin</p>
        <h2>Ekibini doğru adaylarla, daha hızlı büyüt.</h2>
        <p>
          AFTERWORK ile ilanlarını hedefli şekilde yayınla, başvuruları tek panelde değerlendir ve doğru
          adaylarla kısa sürede görüşmeye başla.
        </p>
      </div>

      <div class="employer-grid">
        <aside class="employer-left-list">
          <h3>Nasıl Çalışır?</h3>
          <ol class="employer-steps">
            <li>
              <span>1</span>
              <p><strong>Hızlı İlan Aç:</strong> 2 dakikada ilanını yayına al.</p>
            </li>
            <li>
              <span>2</span>
              <p><strong>Adayları Sırala:</strong> Deneyim ve yetkinliğe göre filtrele.</p>
            </li>
            <li>
              <span>3</span>
              <p><strong>Tek Panel Yönetim:</strong> Başvuru sürecini tek yerden yönet.</p>
            </li>
            <li>
              <span>4</span>
              <p><strong>Hızlı Görüşme:</strong> Uygun adaylara doğrudan dönüş yap.</p>
            </li>
          </ol>

          <a class="employer-start" href="auth.php#giris">Başla</a>
        </aside>

        <div class="employer-center-visual">
          <img src="frontend/assets/images/hero-demo.png" alt="Afterwork demo alanı">
        </div>

        <aside class="employer-right-cards">
          <article>
            <img src="frontend/assets/images/hero-demo.png" alt="Afterwork demo alanı">
            <h3>Öne Çıkan İlan</h3>
            <p>Daha fazla adaya görünür ol.</p>
            <a href="auth.php#kayit">Detay</a>
          </article>
          <article>
            <img src="frontend/assets/images/hero-demo.png" alt="Afterwork demo alanı">
            <h3>Aday Havuzu</h3>
            <p>Pozisyona uygun aday listesi.</p>
            <a href="auth.php#kayit">Detay</a>
          </article>
        </aside>
      </div>
    </section>

    <section class="plans-section" id="ilan-planlari">
      <div class="plans-shell">
        <div class="plans-head">
          <p class="section-kicker">İlan Planları</p>
          <h2>İşveren planlarına hızlıca göz at.</h2>
          <p>Her iki plan da şu an 1 aylık deneme ile ücretsiz.</p>
        </div>

        <div class="plans-grid" aria-label="İlan yayınlama planları">
          <article class="plan-card">
            <div class="plan-card-media">
              <img src="frontend/assets/images/hero-demo.png" alt="Basic plan görünümü">
            </div>

            <div class="plan-card-content">
              <p class="plan-tier">Basic</p>
              <h3>Temiz başlangıç paketi</h3>
              <p class="plan-copy">
                İlk ilanlarını sade ve kontrollü şekilde yayınlamak isteyen ekipler için.
              </p>

              <p class="plan-price">₺0<span>/1 ay deneme</span></p>

              <ul class="plan-features">
                <li>30 gün ilan yayında kalma süresi</li>
                <li>Standart görünürlük</li>
                <li>Temel veri ve sade görünüm</li>
              </ul>

              <a class="plan-cta" href="auth.php#kayit">Basic ile Başla</a>
            </div>
          </article>

          <article class="plan-card plan-card-featured">
            <p class="plan-badge">En çok tercih edilen</p>

            <div class="plan-card-media">
              <img src="frontend/assets/images/hero-demo.png" alt="Pro plan görünümü">
            </div>

            <div class="plan-card-content">
              <p class="plan-tier">Pro</p>
              <h3>Daha uzun, daha görünür, daha akıllı</h3>
              <p class="plan-copy">
                Daha fazla görünürlük ve veri isteyen, hızlı işe alım yapan ekipler için.
              </p>

              <p class="plan-price">₺0<span>/1 ay deneme</span></p>

              <ul class="plan-features">
                <li>60 gün ilan yayında kalma süresi</li>
                <li>Boost ve öne çıkarma</li>
                <li>Detaylı veri ve gelişmiş tasarım</li>
              </ul>

              <a class="plan-cta" href="auth.php#kayit">Pro'yu İncele</a>
            </div>
          </article>
        </div>
      </div>
    </section>

    <section class="internships-section" id="internships">
      <div class="internships-shell">
        <div class="internships-intro">
          <p class="internships-kicker">Staj Programları</p>
          <h2>Öğrenciler için gerçek deneyime açılan sade ve güvenilir bir başlangıç.</h2>
          <p class="internships-description">
            AFTERWORK, öğrencilerin doğrulanmış şirketlerde staj fırsatlarını keşfetmesini kolaylaştırır.
            Kısa ve net başvuru akışı sayesinde ilk deneyimini daha hızlı planlayabilir, kariyerine güçlü
            bir başlangıç yapabilirsin.
          </p>

          <div class="internships-points" aria-label="Staj programı avantajları">
            <article>
              <h3>Doğrulanmış Şirketler</h3>
              <p>Başvurularını güvenilir kurumlara yönlendir, süreci daha rahat takip et.</p>
            </article>
            <article>
              <h3>Öğrenciye Uygun Roller</h3>
              <p>Part-time, dönem içi ve yaz stajı gibi seçenekleri tek yerde karşılaştır.</p>
            </article>
            <article>
              <h3>Hızlı Başvuru</h3>
              <p>Uzun ve yorucu formlar yerine sade adımlarla başvurunu kısa sürede tamamla.</p>
            </article>
          </div>
        </div>

        <aside class="internships-highlight" aria-label="Staj fırsatları özeti">
          <div class="internships-highlight-media">
            <img src="frontend/assets/images/hero-demo.png" alt="Afterwork staj fırsatları görünümü">
          </div>

          <div class="internships-highlight-content">
            <p class="internships-highlight-kicker">Öğrenciler İçin</p>
            <h3>İlk deneyimini doğru şirketlerle şekillendir.</h3>
            <ul class="internships-list">
              <li>Yaz stajı ve dönem içi fırsatlara tek ekranda ulaş.</li>
              <li>Şehir, alan ve çalışma modeline göre filtreleme yap.</li>
              <li>Başvuru sürecini sade bir panelden takip et.</li>
            </ul>

            <a class="internships-cta" href="auth.php#giris">Staj Fırsatlarını Gör</a>
          </div>
        </aside>
      </div>
    </section>

    <footer class="site-footer" aria-label="Alt bilgi">
      <div class="site-footer-shell">
        <div class="site-footer-top">
          <div class="site-footer-brand">
            <img src="frontend/assets/images/afterwork-logo.png" alt="Afterwork">
            <p>
              Türkiye odaklı modern kariyer platformu. Doğru adayları, doğru ekiplerle daha sade ve güvenilir
              bir akışta buluşturur.
            </p>
          </div>
        </div>

        <div class="site-footer-grid">
          <nav class="site-footer-column" aria-label="Platform">
            <h2>Platform</h2>
            <a href="#hero">Ana Sayfa</a>
            <a href="#is-ilanlari">İş Bul</a>
            <a href="#isveren">İşverenler İçin</a>
            <a href="#internships">Staj Programları</a>
          </nav>

          <nav class="site-footer-column" aria-label="İşverenler">
            <h2>İşverenler</h2>
            <a href="#ilan-planlari">İlan Planları</a>
            <a href="auth.php#kayit">İlan Yayınla</a>
            <a href="auth.php#giris">İşveren Girişi</a>
            <a href="isveren-panel.php">İşveren Paneli</a>
          </nav>

          <nav class="site-footer-column" aria-label="Adaylar">
            <h2>Adaylar</h2>
            <a href="auth.php#kayit">Hesap Oluştur</a>
            <a href="auth.php#giris">Giriş Yap</a>
            <a href="seeker-panel.php">Aday Paneli</a>
            <a href="#internships">Staj Fırsatları</a>
          </nav>

          <nav class="site-footer-column" aria-label="Destek">
            <h2>Destek</h2>
            <a href="auth.php#giris">Yardım Merkezi</a>
            <a href="auth.php#kayit">İletişim</a>
            <a href="auth.php#kayit">Gizlilik</a>
            <a href="auth.php#kayit">Kullanım Koşulları</a>
          </nav>
        </div>

        <div class="site-footer-bottom">
          <p>© 2026 AFTERWORK. Tüm hakları saklıdır.</p>
          <div class="site-footer-meta">
            <span>Türkiye</span>
            <span>Türkçe</span>
          </div>
        </div>
      </div>
    </footer>

  </main>

  <script src="frontend/assets/js/splash.js?v=<?= filemtime(__DIR__ . '/../../assets/js/splash.js') ?>" defer></script>
  <script src="frontend/assets/js/jobs-slider.js?v=<?= filemtime(__DIR__ . '/../../assets/js/jobs-slider.js') ?>" defer></script>
  <script src="frontend/assets/js/media-hover.js?v=<?= filemtime(__DIR__ . '/../../assets/js/media-hover.js') ?>" defer></script>
  <script src="frontend/assets/js/cursor-effects.js?v=<?= filemtime(__DIR__ . '/../../assets/js/cursor-effects.js') ?>" defer></script>
</body>
</html>
