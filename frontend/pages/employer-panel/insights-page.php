<?php

declare(strict_types=1);

session_start();

// DEV BYPASS — localhost only, remove before pushing to production
if (in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', 'localhost:8000', '127.0.0.1', '127.0.0.1:8000'], true)) {
    $_SESSION['account']  = ['account_id' => 0, 'email' => 'dev@localhost', 'role' => 'employer', 'is_verified' => 1];
    $_SESSION['employer'] = ['id' => 0, 'account_id' => 0, 'email' => 'dev@localhost', 'company_name' => 'Dev Şirket', 'role' => 'employer'];
}

if (
    !isset($_SESSION['account'])
    || !is_array($_SESSION['account'])
    || (string) ($_SESSION['account']['role'] ?? '') !== 'employer'
) {
    header('Location: /auth.php#giris');
    exit;
}

require_once __DIR__ . '/../../../backend/config/db.php';
require_once __DIR__ . '/../../../backend/auth/session-helper.php';

$employer = is_array($_SESSION['employer'] ?? null) ? $_SESSION['employer'] : [];
$employerId = (int) ($employer['id'] ?? 0);
$companyName = trim((string) ($employer['company_name'] ?? '')) ?: 'Şirketiniz';

$listingId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($listingId <= 0) {
    header('Location: /isveren-panel.php');
    exit;
}

// Load listing (owner-only)
$listing = null;
try {
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT * FROM job_listings WHERE id = :id AND employer_id = :eid LIMIT 1'
    );
    $stmt->execute(['id' => $listingId, 'eid' => $employerId]);
    $listing = $stmt->fetch() ?: null;
} catch (Throwable) {
    $listing = null;
}

// Localhost dev preview: if no listing found but we're on localhost, render a fake one
// so the full Mercek page can be previewed without real data in the DB yet.
$isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', 'localhost:8000', '127.0.0.1', '127.0.0.1:8000', 'afterwork.test'], true);
if ($listing === null && $isLocalhost) {
    $listing = [
        'id' => $listingId,
        'title' => 'Frontend Geliştirici · Örnek İlan',
        'employment_type' => 'Tam Zamanlı',
        'work_model' => 'Hibrit',
        'location' => 'İstanbul',
        'salary_min' => 45000,
        'salary_max' => 72000,
        'is_active' => 1,
    ];
}

// Real counters — safe to run even if analytics tables don't exist yet
$totalViews = null;
$uniqueVisitors = null;
$totalApplications = null;
$totalSaves = null;

try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM listing_views WHERE listing_id = :id');
    $stmt->execute(['id' => $listingId]);
    $totalViews = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        'SELECT COUNT(DISTINCT COALESCE(viewer_account_id, viewer_session_hash)) FROM listing_views WHERE listing_id = :id'
    );
    $stmt->execute(['id' => $listingId]);
    $uniqueVisitors = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM listing_applications WHERE listing_id = :id');
    $stmt->execute(['id' => $listingId]);
    $totalApplications = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM listing_saves WHERE listing_id = :id');
    $stmt->execute(['id' => $listingId]);
    $totalSaves = (int) $stmt->fetchColumn();
} catch (Throwable) {
    // Analytics tables may not be migrated yet — fall back to 0 so the shell still renders.
    $totalViews = $totalViews ?? 0;
    $uniqueVisitors = $uniqueVisitors ?? 0;
    $totalApplications = $totalApplications ?? 0;
    $totalSaves = $totalSaves ?? 0;
}

// If no real data yet, we surface mock/örnek numbers for the KPI strip so the page demonstrates the vision.
$hasRealData = ($totalViews + $totalApplications + $totalSaves) > 0;

$kpis = $hasRealData
    ? [
        ['label' => 'Toplam Görüntülenme', 'value' => number_format($totalViews, 0, ',', '.'), 'delta' => null, 'real' => true],
        ['label' => 'Benzersiz Ziyaretçi', 'value' => number_format((int) $uniqueVisitors, 0, ',', '.'), 'delta' => null, 'real' => true],
        ['label' => 'Başvuru Sayısı',      'value' => number_format($totalApplications, 0, ',', '.'), 'delta' => null, 'real' => true],
        ['label' => 'Kaydedenler',         'value' => number_format($totalSaves, 0, ',', '.'), 'delta' => null, 'real' => true],
        ['label' => 'Ort. Sayfada Kalma',  'value' => '—',     'delta' => null, 'real' => false],
        ['label' => 'Tamamlanma Oranı',    'value' => '—',     'delta' => null, 'real' => false],
        ['label' => 'Yanıt Oranı',         'value' => '—',     'delta' => null, 'real' => false],
    ]
    : [
        ['label' => 'Toplam Görüntülenme', 'value' => '1.284', 'delta' => '+12%', 'real' => false],
        ['label' => 'Benzersiz Ziyaretçi', 'value' => '842',   'delta' => '+8%',  'real' => false],
        ['label' => 'Başvuru Sayısı',      'value' => '37',    'delta' => '+24%', 'real' => false],
        ['label' => 'Kaydedenler',         'value' => '96',    'delta' => '+9%',  'real' => false],
        ['label' => 'Ort. Sayfada Kalma',  'value' => '1:42',  'delta' => null,   'real' => false],
        ['label' => 'Tamamlanma Oranı',    'value' => '68%',   'delta' => '+3%',  'real' => false],
        ['label' => 'Yanıt Oranı',         'value' => '54%',   'delta' => null,   'real' => false],
    ];

// Mock timeseries (last 30 days)
$mockDays = 30;
$mockViews = [];
$mockApps = [];
$mockSaves = [];
$mockLabels = [];
for ($i = $mockDays - 1; $i >= 0; $i--) {
    $ts = strtotime("-{$i} days");
    $mockLabels[] = date('j M', $ts);
    $base = 25 + (int) round(30 * sin(($mockDays - $i) / 4) + ($mockDays - $i) * 1.4);
    $mockViews[] = max(8, $base + random_int(-10, 14));
    $mockApps[] = max(0, (int) round($base / 8) + random_int(-1, 3));
    $mockSaves[] = max(0, (int) round($base / 3) + random_int(-2, 5));
}

$lTitle = (string) ($listing['title'] ?? 'İlan bulunamadı');
$lType = (string) ($listing['employment_type'] ?? '');
$lModel = (string) ($listing['work_model'] ?? '');
$lLocation = (string) ($listing['location'] ?? '');
$lMin = $listing['salary_min'] ?? null;
$lMax = $listing['salary_max'] ?? null;
$salaryLabel = null;
if ($lMin !== null && $lMax !== null) {
    $salaryLabel = number_format((int) $lMin, 0, ',', '.') . ' – ' . number_format((int) $lMax, 0, ',', '.') . ' ₺';
} elseif ($lMin !== null) {
    $salaryLabel = number_format((int) $lMin, 0, ',', '.') . ' ₺+';
}
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AFTERWORK | Mercek — <?= htmlspecialchars($lTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="/frontend/assets/css/employer/panel.css?v=<?= filemtime(__DIR__ . '/../../assets/css/employer/panel.css') ?>">
  <link rel="stylesheet" href="/frontend/assets/css/shared/logout-modal.css?v=<?= filemtime(__DIR__ . '/../../assets/css/shared/logout-modal.css') ?>">
  <link rel="stylesheet" href="/frontend/assets/css/employer/insights.css?v=<?= filemtime(__DIR__ . '/../../assets/css/employer/insights.css') ?>">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
</head>
<body class="ep-insights-body">
  <div class="ep-page">
    <?php
    $activeTab = 'account';
    include __DIR__ . '/../../partials/employer-topbar.php';
    ?>

    <?php if ($listing === null): ?>
      <section class="ep-stub" aria-label="İlan bulunamadı">
        <p class="ep-stub-kicker">Mercek</p>
        <h1>Bu ilan bulunamadı</h1>
        <p class="ep-stub-lead">Aradığın ilan artık yok ya da senin hesabına ait değil.</p>
        <a class="ep-stub-back" href="/isveren-panel.php">Dükkana dön</a>
      </section>
    <?php else: ?>

    <section class="in-page" aria-label="Mercek">

      <!-- Hero -->
      <header class="in-hero">
        <a class="in-back" href="/isveren-panel.php">← Dükkana dön</a>
        <p class="in-hero-kicker">Mercek · İlan #<?= (int) $listingId ?></p>
        <h1><?= htmlspecialchars($lTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="in-hero-chips">
          <?php if ($lType !== ''): ?><span class="in-chip"><?= htmlspecialchars($lType, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
          <?php if ($lModel !== ''): ?><span class="in-chip"><?= htmlspecialchars($lModel, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
          <?php if ($lLocation !== ''): ?><span class="in-chip in-chip--ghost"><?= htmlspecialchars($lLocation, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
          <?php if ($salaryLabel !== null): ?><span class="in-chip in-chip--gold"><?= htmlspecialchars($salaryLabel, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
        </div>
        <?php if (!$hasRealData): ?>
          <p class="in-hero-notice">
            <span class="in-notice-dot" aria-hidden="true"></span>
            Veri toplama yakında başlayacak — aşağıdaki görseller örnek verilerle hazırlanmıştır.
          </p>
        <?php endif; ?>
      </header>

      <!-- A — KPI şeridi -->
      <section class="in-section">
        <h2 class="in-section-title">Özet</h2>
        <div class="in-kpi-strip">
          <?php foreach ($kpis as $k): ?>
            <div class="in-kpi<?= $k['real'] ? ' is-real' : '' ?>">
              <span class="in-kpi-label"><?= htmlspecialchars($k['label'], ENT_QUOTES, 'UTF-8') ?></span>
              <strong class="in-kpi-value"><?= htmlspecialchars($k['value'], ENT_QUOTES, 'UTF-8') ?></strong>
              <?php if (!empty($k['delta'])): ?>
                <span class="in-kpi-delta"><?= htmlspecialchars($k['delta'], ENT_QUOTES, 'UTF-8') ?></span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- B — Zaman serisi -->
      <section class="in-section">
        <div class="in-section-head">
          <h2 class="in-section-title">Zaman İçinde</h2>
          <div class="in-range" role="tablist" aria-label="Zaman aralığı">
            <button type="button" class="in-range-btn" data-range="7">7G</button>
            <button type="button" class="in-range-btn is-active" data-range="30">30G</button>
            <button type="button" class="in-range-btn" data-range="90">90G</button>
          </div>
        </div>
        <div class="in-grid in-grid--2">
          <div class="in-card in-card--tall">
            <p class="in-card-kicker">Görüntülenme eğrisi</p>
            <div class="in-chart in-chart--tall"><canvas id="chart-views"></canvas></div>
          </div>
          <div class="in-card in-card--tall">
            <p class="in-card-kicker">Başvuru akışı</p>
            <div class="in-chart in-chart--tall"><canvas id="chart-apps"></canvas></div>
          </div>
          <div class="in-card">
            <p class="in-card-kicker">Kaydedenler</p>
            <div class="in-chart"><canvas id="chart-saves"></canvas></div>
          </div>
          <div class="in-card">
            <p class="in-card-kicker">Dönüşüm (Görüntülenme → Başvuru)</p>
            <div class="in-chart"><canvas id="chart-ctr"></canvas></div>
          </div>
          <div class="in-card in-card--span2">
            <p class="in-card-kicker">Saatlik ısı haritası · Hangi gün &amp; saat en çok bakılıyor</p>
            <div class="in-chart in-chart--tall"><canvas id="chart-hourly"></canvas></div>
            <p class="in-card-note">Yatay eksen: 00:00 – 23:00 · Dikey eksen: Pazartesi – Pazar · Renk yoğunluğu = görüntülenme sayısı.</p>
          </div>
        </div>
      </section>

      <!-- C — Funnel -->
      <section class="in-section">
        <h2 class="in-section-title">Dönüşüm Hunisi</h2>
        <div class="in-funnel">
          <?php
          $funnel = [
            ['label' => 'İlanı Gördü',       'count' => 1284, 'pct' => 100],
            ['label' => 'Detayı Açtı',       'count' => 642,  'pct' => 50],
            ['label' => 'Kaydetti',          'count' => 96,   'pct' => 8],
            ['label' => 'Başvuru Başlattı',  'count' => 54,   'pct' => 4],
            ['label' => 'Başvuru Tamamladı', 'count' => 37,   'pct' => 3],
          ];
          foreach ($funnel as $i => $step):
            $prev = $i > 0 ? $funnel[$i - 1]['count'] : null;
            $drop = $prev ? (int) round((1 - $step['count'] / $prev) * 100) : 0;
          ?>
          <div class="in-funnel-row">
            <div class="in-funnel-bar" style="width: <?= (int) $step['pct'] ?>%;">
              <span class="in-funnel-label"><?= htmlspecialchars($step['label'], ENT_QUOTES, 'UTF-8') ?></span>
              <span class="in-funnel-count"><?= number_format($step['count'], 0, ',', '.') ?></span>
            </div>
            <span class="in-funnel-meta"><?= $step['pct'] ?>%<?php if ($i > 0 && $drop > 0): ?> · <span class="in-funnel-drop">−<?= $drop ?>%</span><?php endif; ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- D — Aday demografisi -->
      <section class="in-section">
        <h2 class="in-section-title">Aday Demografisi</h2>
        <div class="in-grid in-grid--3">
          <div class="in-card">
            <p class="in-card-kicker">Cinsiyet</p>
            <div class="in-chart in-chart--donut"><canvas id="chart-gender"></canvas></div>
          </div>
          <div class="in-card">
            <p class="in-card-kicker">Yaş Aralığı</p>
            <div class="in-chart"><canvas id="chart-age"></canvas></div>
          </div>
          <div class="in-card">
            <p class="in-card-kicker">Eğitim Seviyesi</p>
            <div class="in-chart in-chart--donut"><canvas id="chart-education"></canvas></div>
          </div>
          <div class="in-card">
            <p class="in-card-kicker">Deneyim Seviyesi</p>
            <div class="in-chart in-chart--donut"><canvas id="chart-experience"></canvas></div>
          </div>
          <div class="in-card in-card--span2">
            <p class="in-card-kicker">En çok başvuran 10 üniversite</p>
            <div class="in-chart in-chart--tall"><canvas id="chart-universities"></canvas></div>
          </div>
        </div>
      </section>

      <!-- E — Coğrafi -->
      <section class="in-section">
        <h2 class="in-section-title">Coğrafi Dağılım</h2>
        <div class="in-card in-card--map">
          <div class="in-map-head">
            <p class="in-card-kicker">Dünya başvuru ısı haritası</p>
            <button type="button" id="map-reset" class="in-map-reset" hidden>
              <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                <path d="M3 6l3-3M3 6l3 3M3 6h6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              Dünyaya dön
            </button>
          </div>
          <div id="tr-map" class="in-map" aria-label="Dünya başvuru haritası">
            <div class="in-map-loader" id="map-loader" hidden>Şehirler yükleniyor…</div>
          </div>
          <div class="in-map-foot">
            <div class="in-map-legend" aria-label="Yoğunluk ölçeği">
              <span class="in-map-legend-tick">Az</span>
              <span class="in-map-legend-grad" aria-hidden="true"></span>
              <span class="in-map-legend-tick">Çok</span>
            </div>
            <p class="in-map-hint"><span aria-hidden="true">↘</span> Ülkeye tıkla, şehir kırılımı açılsın</p>
          </div>
        </div>
        <div class="in-grid in-grid--2" style="margin-top:1rem;">
          <div class="in-card">
            <p class="in-card-kicker">İlk 10 şehir</p>
            <div class="in-chart in-chart--tall"><canvas id="chart-cities"></canvas></div>
          </div>
          <div class="in-card">
            <p class="in-card-kicker">Şehir dağılımı</p>
            <ul class="in-stat-list">
              <li><span>İstanbul</span><strong>48%</strong></li>
              <li><span>Ankara</span><strong>18%</strong></li>
              <li><span>İzmir</span><strong>11%</strong></li>
              <li><span>Bursa</span><strong>6%</strong></li>
              <li><span>Diğer</span><strong>17%</strong></li>
            </ul>
            <p class="in-card-note">Şehir dışı + yurt dışı: <strong>12%</strong></p>
          </div>
        </div>
      </section>

      <!-- F — Trafik -->
      <section class="in-section">
        <h2 class="in-section-title">Trafik Kaynağı</h2>
        <div class="in-grid in-grid--3">
          <div class="in-card">
            <p class="in-card-kicker">Nereden geldi</p>
            <div class="in-chart in-chart--donut"><canvas id="chart-source"></canvas></div>
          </div>
          <div class="in-card">
            <p class="in-card-kicker">Cihaz</p>
            <div class="in-chart in-chart--donut"><canvas id="chart-device"></canvas></div>
          </div>
          <div class="in-card">
            <p class="in-card-kicker">En çok aranan kelimeler</p>
            <div id="wordcloud" class="in-wordcloud-wrap" aria-label="Anahtar kelime bulutu"></div>
          </div>
        </div>
      </section>

      <!-- G — Rekabet -->
      <section class="in-section">
        <div class="in-section-head">
          <h2 class="in-section-title">Rekabet · Piyasa</h2>
          <span class="in-premium-chip">Premium</span>
        </div>
        <div class="in-grid in-grid--2">
          <div class="in-card">
            <p class="in-card-kicker">Rekabet gücü skoru</p>
            <div class="in-score">
              <div class="in-score-value">72<span>/100</span></div>
              <div class="in-score-bar"><span style="width: 72%;"></span></div>
              <p class="in-score-note">İçerik kalitesi ve maaş bandı üzerinde çalışırsan 80+ mümkün.</p>
            </div>
          </div>
          <div class="in-card">
            <p class="in-card-kicker">Pozisyon için piyasa ortalaması</p>
            <ul class="in-stat-list">
              <li><span>Ort. görüntülenme</span><strong>874</strong></li>
              <li><span>Ort. başvuru</span><strong>22</strong></li>
              <li><span>Ort. yayın süresi</span><strong>18 gün</strong></li>
              <li><span>Maaş ortalaması</span><strong>45.000 – 72.000 ₺</strong></li>
            </ul>
          </div>
          <div class="in-card in-card--span2">
            <p class="in-card-kicker">Pozisyon talep trendi (son 3 ay)</p>
            <div class="in-chart"><canvas id="chart-trend"></canvas></div>
          </div>
        </div>
      </section>

      <!-- H — Önerilen adaylar -->
      <section class="in-section">
        <h2 class="in-section-title">Önerilen Adaylar</h2>
        <div class="in-candidates">
          <?php
          $candidates = [
            ['name' => 'A. Yılmaz',  'match' => 94, 'why' => 'İstenen 3 beceriden 3\'ü eşleşiyor · 5 yıl deneyim'],
            ['name' => 'D. Kara',    'match' => 88, 'why' => 'React + TypeScript · uzaktan çalışma tercihi'],
            ['name' => 'E. Toprak',  'match' => 83, 'why' => 'İstanbul · benzer pozisyonda 2 yıl'],
            ['name' => 'M. Aslan',   'match' => 79, 'why' => 'Junior · hızlı öğrenme göstergeleri yüksek'],
            ['name' => 'S. Demir',   'match' => 76, 'why' => 'İstenen becerilerden 2\'si var · İzmir'],
          ];
          foreach ($candidates as $c):
          ?>
          <article class="in-cand-card">
            <div class="in-cand-avatar" aria-hidden="true"><?= htmlspecialchars(mb_substr($c['name'], 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="in-cand-body">
              <div class="in-cand-name-row">
                <strong class="in-cand-name"><?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="in-cand-match"><?= (int) $c['match'] ?>%</span>
              </div>
              <p class="in-cand-why"><?= htmlspecialchars($c['why'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
            <a class="in-cand-cta" href="#" aria-disabled="true" title="Yakında">Profili Aç</a>
          </article>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- I — Kalite & öneriler -->
      <section class="in-section">
        <h2 class="in-section-title">İlan Kalitesi · Akıllı Öneriler</h2>
        <div class="in-grid in-grid--2">
          <div class="in-card">
            <p class="in-card-kicker">İlan skoru</p>
            <div class="in-score">
              <div class="in-score-value">78<span>/100</span></div>
              <div class="in-score-bar in-score-bar--green"><span style="width: 78%;"></span></div>
              <p class="in-score-note">Başlık berrak, açıklama yeterli. Maaş bandı ve yan haklar puanı yükseltebilir.</p>
            </div>
          </div>
          <div class="in-card">
            <p class="in-card-kicker">Aksiyon önerileri</p>
            <ul class="in-suggest-list">
              <li>
                <span class="in-suggest-icon" aria-hidden="true">+</span>
                <div><strong>Yan haklar ekle.</strong><br><span>Ilan detayında yan haklar görünürse görüntülenmen <em>%18</em> artabilir.</span></div>
              </li>
              <li>
                <span class="in-suggest-icon" aria-hidden="true">✓</span>
                <div><strong>"uzaktan" anahtar kelimesini ekle.</strong><br><span>Son 3 günde en çok aranan kelimelerden biri.</span></div>
              </li>
              <li>
                <span class="in-suggest-icon" aria-hidden="true">⏰</span>
                <div><strong>Yayın saati: Salı 10:00–12:00.</strong><br><span>Bu pozisyona en çok bakılan zaman dilimi.</span></div>
              </li>
            </ul>
          </div>
        </div>
      </section>

      <!-- J — Davranış -->
      <section class="in-section">
        <h2 class="in-section-title">Etkileşim · Davranış</h2>
        <div class="in-grid in-grid--3">
          <div class="in-card">
            <p class="in-card-kicker">Ortalama sayfada kalma</p>
            <p class="in-big-stat">1:42 <small>dk</small></p>
            <p class="in-card-note">Piyasa ortalaması: 1:18</p>
          </div>
          <div class="in-card">
            <p class="in-card-kicker">Geri dönüş oranı</p>
            <p class="in-big-stat">23%</p>
            <p class="in-card-note">Ziyaretçilerin yaklaşık 1/4'ü ilanı tekrar açıyor.</p>
          </div>
          <div class="in-card">
            <p class="in-card-kicker">En çok okunan bölüm</p>
            <ul class="in-stat-list in-stat-list--tight">
              <li><span>Açıklama</span><strong>64%</strong></li>
              <li><span>Aranan özellikler</span><strong>22%</strong></li>
              <li><span>Maaş &amp; yan haklar</span><strong>14%</strong></li>
            </ul>
          </div>
        </div>
      </section>

    </section>
    <?php endif; ?>
  </div>

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

  <script>
    window.__insightsData = {
      labels: <?= json_encode($mockLabels, JSON_UNESCAPED_UNICODE) ?>,
      views:  <?= json_encode($mockViews) ?>,
      apps:   <?= json_encode($mockApps) ?>,
      saves:  <?= json_encode($mockSaves) ?>,
    };
  </script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-chart-matrix@2.0.1/dist/chartjs-chart-matrix.min.js" defer></script>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin="" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/wordcloud@1.2.2/src/wordcloud2.min.js" defer></script>
  <script src="/frontend/assets/js/employer/topbar.js?v=<?= filemtime(__DIR__ . '/../../assets/js/employer/topbar.js') ?>" defer></script>
  <script src="/frontend/assets/js/shared/logout-modal.js?v=<?= filemtime(__DIR__ . '/../../assets/js/shared/logout-modal.js') ?>" defer></script>
  <script src="/frontend/assets/js/employer/insights.js?v=<?= filemtime(__DIR__ . '/../../assets/js/employer/insights.js') ?>" defer></script>
</body>
</html>
