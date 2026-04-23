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

// Localhost preview: if no listing exists, fake a shell so the employer can see
// how the empty Mercek page looks. Analytics queries still run against the real
// DB, so every number/chart stays at 0 — no mock data, just a skeleton listing.
$isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', 'localhost:8000', '127.0.0.1', '127.0.0.1:8000', 'afterwork.test'], true);

// `?dolu=1` on localhost → design preview with rich mock data in every chart.
// Strictly gated: prod never triggers this, so no fake numbers ever reach real users.
$isDemo = $isLocalhost && isset($_GET['dolu']);

if ($listing === null && $isLocalhost) {
    $listing = $isDemo
        ? [
            'id'              => $listingId,
            'title'           => 'Frontend Geliştirici · React + TypeScript',
            'employment_type' => 'Tam Zamanlı',
            'work_model'      => 'Hibrit',
            'location'        => 'İstanbul',
            'salary_min'      => 45000,
            'salary_max'      => 72000,
            'description'     => 'Kullanıcı arayüzlerini tasarlayıp geliştireceğin, modern React + TypeScript stack üzerinde çalışacağın premium bir ekip.',
            'requirements'    => 'React, TypeScript, modern CSS. 2+ yıl deneyim.',
            'benefits'        => 'Sağlık sigortası, yemek kartı, ulaşım.',
            'experience_level'=> 'Mid-level (2–5 yıl)',
            'skills'          => 'React, TypeScript, CSS, Figma',
            'is_active'       => 1,
        ]
        : [
            'id'              => $listingId,
            'title'           => 'Örnek İlan · Önizleme',
            'employment_type' => 'Tam Zamanlı',
            'work_model'      => 'Hibrit',
            'location'        => 'İstanbul',
            'salary_min'      => null,
            'salary_max'      => null,
            'description'     => '',
            'requirements'    => '',
            'benefits'        => '',
            'experience_level'=> '',
            'skills'          => '',
            'is_active'       => 1,
        ];
}

// ── Real analytics (tables may be empty — page honestly shows 0 / empty states) ─
$totalViews = 0;
$uniqueVisitors = 0;
$totalApplications = 0;
$totalSaves = 0;

$days = 30;
$labels = [];
$dailyViews = array_fill(0, $days, 0);
$dailyApps  = array_fill(0, $days, 0);
$dailySaves = array_fill(0, $days, 0);
for ($i = $days - 1; $i >= 0; $i--) {
    $labels[] = date('j M', strtotime("-{$i} days"));
}

$hourly = [];  // [day_of_week (0=Mon..6=Sun)][hour 0..23] => count
for ($d = 0; $d < 7; $d++) { $hourly[$d] = array_fill(0, 24, 0); }

$trafficBreakdown = [];   // traffic_source => count
$deviceBreakdown = [];    // device_type => count

try {
    $pdo = db();

    $totalViews        = (int) $pdo->query('SELECT COUNT(*) FROM listing_views WHERE listing_id = ' . (int) $listingId)->fetchColumn();
    $uniqueVisitors    = (int) $pdo->query('SELECT COUNT(DISTINCT COALESCE(viewer_account_id, viewer_session_hash)) FROM listing_views WHERE listing_id = ' . (int) $listingId)->fetchColumn();
    $totalApplications = (int) $pdo->query('SELECT COUNT(*) FROM listing_applications WHERE listing_id = ' . (int) $listingId)->fetchColumn();
    $totalSaves        = (int) $pdo->query('SELECT COUNT(*) FROM listing_saves WHERE listing_id = ' . (int) $listingId)->fetchColumn();

    // Build date-indexed empty buckets for last N days (yyyy-mm-dd keys)
    $bucketIndex = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $bucketIndex[date('Y-m-d', strtotime("-{$i} days"))] = $days - 1 - $i;
    }

    $hydrate = function (string $sql, string $dateCol) use ($pdo, $listingId, $bucketIndex) {
        $arr = array_fill(0, count($bucketIndex), 0);
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $listingId]);
        foreach ($stmt->fetchAll() as $row) {
            $k = (string) ($row[$dateCol] ?? '');
            if (isset($bucketIndex[$k])) $arr[$bucketIndex[$k]] = (int) $row['c'];
        }
        return $arr;
    };

    $dailyViews = $hydrate(
        'SELECT DATE(viewed_at) AS d, COUNT(*) AS c FROM listing_views
         WHERE listing_id = :id AND viewed_at >= DATE_SUB(CURDATE(), INTERVAL ' . ((int) $days - 1) . ' DAY)
         GROUP BY DATE(viewed_at)',
        'd'
    );
    $dailyApps = $hydrate(
        'SELECT DATE(submitted_at) AS d, COUNT(*) AS c FROM listing_applications
         WHERE listing_id = :id AND submitted_at >= DATE_SUB(CURDATE(), INTERVAL ' . ((int) $days - 1) . ' DAY)
         GROUP BY DATE(submitted_at)',
        'd'
    );
    $dailySaves = $hydrate(
        'SELECT DATE(saved_at) AS d, COUNT(*) AS c FROM listing_saves
         WHERE listing_id = :id AND saved_at >= DATE_SUB(CURDATE(), INTERVAL ' . ((int) $days - 1) . ' DAY)
         GROUP BY DATE(saved_at)',
        'd'
    );

    // Hourly heatmap: MySQL DAYOFWEEK -> 1=Sun..7=Sat. Convert to 0=Mon..6=Sun.
    $stmt = $pdo->prepare(
        'SELECT DAYOFWEEK(viewed_at) AS dow, HOUR(viewed_at) AS h, COUNT(*) AS c
         FROM listing_views WHERE listing_id = :id GROUP BY dow, h'
    );
    $stmt->execute(['id' => $listingId]);
    foreach ($stmt->fetchAll() as $row) {
        $dow = (int) $row['dow'];                // 1..7 (Sun..Sat)
        $h   = (int) $row['h'];                  // 0..23
        $idx = ($dow + 5) % 7;                   // 0=Mon..6=Sun
        $hourly[$idx][$h] = (int) $row['c'];
    }

    // Traffic source breakdown
    $stmt = $pdo->prepare(
        'SELECT COALESCE(NULLIF(traffic_source, ""), "Bilinmiyor") AS s, COUNT(*) AS c
         FROM listing_views WHERE listing_id = :id GROUP BY s ORDER BY c DESC LIMIT 6'
    );
    $stmt->execute(['id' => $listingId]);
    foreach ($stmt->fetchAll() as $row) {
        $trafficBreakdown[(string) $row['s']] = (int) $row['c'];
    }

    // Device breakdown
    $stmt = $pdo->prepare(
        'SELECT COALESCE(NULLIF(device_type, ""), "Bilinmiyor") AS d, COUNT(*) AS c
         FROM listing_views WHERE listing_id = :id GROUP BY d ORDER BY c DESC'
    );
    $stmt->execute(['id' => $listingId]);
    foreach ($stmt->fetchAll() as $row) {
        $deviceBreakdown[(string) $row['d']] = (int) $row['c'];
    }
} catch (Throwable) {
    // Analytics tables not migrated yet — everything stays 0/empty, page will show empty states.
}

$fmtNum = static fn (int $n): string => number_format($n, 0, ',', '.');
$kpis = [
    ['label' => 'Toplam Görüntülenme', 'value' => $fmtNum($totalViews)],
    ['label' => 'Benzersiz Ziyaretçi', 'value' => $fmtNum($uniqueVisitors)],
    ['label' => 'Başvuru Sayısı',      'value' => $fmtNum($totalApplications)],
    ['label' => 'Kaydedenler',         'value' => $fmtNum($totalSaves)],
    ['label' => 'Ort. Sayfada Kalma',  'value' => '—'],
    ['label' => 'Tamamlanma Oranı',    'value' => '—'],
    ['label' => 'Yanıt Oranı',         'value' => '—'],
];

// ── Demo mode: override real zeros with rich mock data (localhost only) ──
if ($isDemo) {
    $totalViews        = 1284;
    $uniqueVisitors    = 842;
    $totalApplications = 37;
    $totalSaves        = 96;

    // Daily series — sinusoidal-ish growth pattern over 30 days
    for ($i = 0; $i < $days; $i++) {
        $base = 25 + (int) round(30 * sin($i / 4) + $i * 1.4);
        $dailyViews[$i] = max(8, $base + random_int(-10, 14));
        $dailyApps[$i]  = max(0, (int) round($base / 8) + random_int(-1, 3));
        $dailySaves[$i] = max(0, (int) round($base / 3) + random_int(-2, 5));
    }

    // Hourly heatmap — workday peak, weekends quieter
    for ($d = 0; $d < 7; $d++) {
        for ($h = 0; $h < 24; $h++) {
            $workday = $d < 5;
            $workHour = $h >= 9 && $h <= 19;
            if ($workday && $workHour) $base = 18 + (int) round(10 * sin(($h - 10) / 3));
            elseif (!$workday && $h >= 11 && $h <= 22) $base = 9 + (int) round(4 * sin(($h - 14) / 4));
            else $base = 2;
            $hourly[$d][$h] = max(0, $base + random_int(-3, 4));
        }
    }

    $trafficBreakdown = ['Arama' => 42, 'Ana sayfa' => 26, 'Profil' => 14, 'Direkt' => 12, 'Paylaşım' => 6];
    $deviceBreakdown  = ['Mobil' => 58, 'Masaüstü' => 38, 'Tablet' => 4];

    $fmtNum = static fn (int $n): string => number_format($n, 0, ',', '.');
    $kpis = [
        ['label' => 'Toplam Görüntülenme', 'value' => $fmtNum($totalViews)],
        ['label' => 'Benzersiz Ziyaretçi', 'value' => $fmtNum($uniqueVisitors)],
        ['label' => 'Başvuru Sayısı',      'value' => $fmtNum($totalApplications)],
        ['label' => 'Kaydedenler',         'value' => $fmtNum($totalSaves)],
        ['label' => 'Ort. Sayfada Kalma',  'value' => '1:42'],
        ['label' => 'Tamamlanma Oranı',    'value' => '68%'],
        ['label' => 'Yanıt Oranı',         'value' => '54%'],
    ];
}

$hasViews   = $totalViews > 0;
$hasApps    = $totalApplications > 0;
$hasSaves   = $totalSaves > 0;
$hasTraffic = array_sum($trafficBreakdown) > 0;
$hasDevice  = array_sum($deviceBreakdown) > 0;

// Listing quality score — computed from listing content, no external data needed
$qualityScore = 0;
if ($listing !== null) {
    $title = trim((string) ($listing['title'] ?? ''));
    $desc  = trim((string) ($listing['description'] ?? ''));
    $reqs  = trim((string) ($listing['requirements'] ?? ''));
    $bens  = trim((string) ($listing['benefits'] ?? ''));
    $exp   = trim((string) ($listing['experience_level'] ?? ''));
    $skl   = trim((string) ($listing['skills'] ?? ''));
    if ($title !== '' && mb_strlen($title) >= 8 && mb_strlen($title) <= 80) $qualityScore += 20;
    elseif ($title !== '')                                                  $qualityScore += 10;
    if (mb_strlen($desc) >= 150) $qualityScore += 25;
    elseif (mb_strlen($desc) >= 60)  $qualityScore += 15;
    elseif ($desc !== '')            $qualityScore += 5;
    if ($reqs !== '')                $qualityScore += 15;
    if (!empty($listing['salary_min']) && !empty($listing['salary_max'])) $qualityScore += 15;
    elseif (!empty($listing['salary_min']))                               $qualityScore += 8;
    if ($bens !== '') $qualityScore += 10;
    if ($exp !== '')  $qualityScore += 5;
    if ($skl !== '')  $qualityScore += 5;
}
$qualityScore = min(100, $qualityScore);

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
          <?php if ($isDemo): ?><span class="in-chip in-chip--demo">DEMO · Örnek veri</span><?php endif; ?>
          <?php if ($lType !== ''): ?><span class="in-chip"><?= htmlspecialchars($lType, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
          <?php if ($lModel !== ''): ?><span class="in-chip"><?= htmlspecialchars($lModel, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
          <?php if ($lLocation !== ''): ?><span class="in-chip in-chip--ghost"><?= htmlspecialchars($lLocation, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
          <?php if ($salaryLabel !== null): ?><span class="in-chip in-chip--gold"><?= htmlspecialchars($salaryLabel, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
        </div>
      </header>

      <!-- A — KPI şeridi -->
      <section class="in-section">
        <h2 class="in-section-title">Özet</h2>
        <div class="in-kpi-strip">
          <?php foreach ($kpis as $k): ?>
            <div class="in-kpi">
              <span class="in-kpi-label"><?= htmlspecialchars($k['label'], ENT_QUOTES, 'UTF-8') ?></span>
              <strong class="in-kpi-value"><?= htmlspecialchars($k['value'], ENT_QUOTES, 'UTF-8') ?></strong>
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
            <?php if ($hasViews): ?>
              <div class="in-chart in-chart--tall"><canvas id="chart-views"></canvas></div>
            <?php else: ?>
              <div class="in-empty">Görüntülenmeler burada çizilecek — veri toplanmaya başladığında otomatik dolar.</div>
            <?php endif; ?>
          </div>
          <div class="in-card in-card--tall">
            <p class="in-card-kicker">Başvuru akışı</p>
            <?php if ($hasApps): ?>
              <div class="in-chart in-chart--tall"><canvas id="chart-apps"></canvas></div>
            <?php else: ?>
              <div class="in-empty">Henüz başvuru yok.</div>
            <?php endif; ?>
          </div>
          <div class="in-card">
            <p class="in-card-kicker">Kaydedenler</p>
            <?php if ($hasSaves): ?>
              <div class="in-chart"><canvas id="chart-saves"></canvas></div>
            <?php else: ?>
              <div class="in-empty">İlanı kaydeden aday olduğunda burada eğriye döner.</div>
            <?php endif; ?>
          </div>
          <div class="in-card">
            <p class="in-card-kicker">Dönüşüm (Görüntülenme → Başvuru)</p>
            <?php if ($hasViews && $hasApps): ?>
              <div class="in-chart"><canvas id="chart-ctr"></canvas></div>
            <?php else: ?>
              <div class="in-empty">Görüntülenme + başvuru birlikte geldiğinde dönüşüm eğrisi burada.</div>
            <?php endif; ?>
          </div>
          <div class="in-card in-card--span2">
            <p class="in-card-kicker">Saatlik ısı haritası · Hangi gün &amp; saat en çok bakılıyor</p>
            <?php if ($hasViews): ?>
              <div class="in-chart in-chart--tall"><canvas id="chart-hourly"></canvas></div>
              <p class="in-card-note">Yatay eksen: 00:00 – 23:00 · Dikey eksen: Pazartesi – Pazar · Renk yoğunluğu = görüntülenme sayısı.</p>
            <?php else: ?>
              <div class="in-empty">7 gün × 24 saat'lik ısı haritası, görüntülenme kayıtları oluştukça burada çıkacak.</div>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <!-- C — Funnel -->
      <section class="in-section">
        <h2 class="in-section-title">Dönüşüm Hunisi</h2>
        <?php
        $funnel = [
          ['label' => 'İlanı Gördü',       'count' => $totalViews],
          ['label' => 'Benzersiz Ziyaretçi','count' => $uniqueVisitors],
          ['label' => 'Kaydetti',          'count' => $totalSaves],
          ['label' => 'Başvuru Yaptı',     'count' => $totalApplications],
        ];
        $funnelTop = max(array_map(fn ($s) => $s['count'], $funnel));
        ?>
        <?php if ($funnelTop > 0): ?>
          <div class="in-funnel">
            <?php foreach ($funnel as $i => $step):
              $pct = $funnelTop ? (int) round(($step['count'] / $funnelTop) * 100) : 0;
              $prev = $i > 0 ? $funnel[$i - 1]['count'] : null;
              $drop = $prev ? (int) round((1 - $step['count'] / max($prev, 1)) * 100) : 0;
            ?>
            <div class="in-funnel-row">
              <div class="in-funnel-bar" style="width: <?= max(6, $pct) ?>%;">
                <span class="in-funnel-label"><?= htmlspecialchars($step['label'], ENT_QUOTES, 'UTF-8') ?></span>
                <span class="in-funnel-count"><?= $fmtNum($step['count']) ?></span>
              </div>
              <span class="in-funnel-meta"><?= $pct ?>%<?php if ($i > 0 && $drop > 0): ?> · <span class="in-funnel-drop">−<?= $drop ?>%</span><?php endif; ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="in-card"><div class="in-empty">Görüntülenme → başvuru akışı burada huni olarak çizilecek.</div></div>
        <?php endif; ?>
      </section>

      <!-- D — Aday demografisi -->
      <section class="in-section">
        <h2 class="in-section-title">Aday Demografisi</h2>
        <?php if ($isDemo): ?>
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
        <?php else: ?>
          <div class="in-card">
            <div class="in-empty">
              Cinsiyet · yaş · eğitim · deneyim · üniversite kırılımları, aday profillerindeki alanlar doldurulup başvuru geldikçe burada görünecek.
            </div>
          </div>
        <?php endif; ?>
      </section>

      <!-- E — Coğrafi -->
      <section class="in-section">
        <h2 class="in-section-title">Coğrafi Dağılım</h2>
        <div class="in-card in-card--map">
          <div class="in-map-head">
            <p class="in-card-kicker">Dünya başvuru ısı haritası</p>
            <?php if ($hasViews || $isDemo): ?>
              <button type="button" id="map-reset" class="in-map-reset" hidden>
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                  <path d="M3 6l3-3M3 6l3 3M3 6h6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Dünyaya dön
              </button>
            <?php endif; ?>
          </div>
          <?php if ($hasViews || $isDemo): ?>
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
          <?php else: ?>
            <div class="in-empty">Ziyaretçilerin nereden geldiği, ilana görüntülenme kaydı düştükçe dünya haritasına işlenir.</div>
          <?php endif; ?>
        </div>
        <?php if ($isDemo): ?>
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
        <?php endif; ?>
      </section>

      <!-- F — Trafik -->
      <section class="in-section">
        <h2 class="in-section-title">Trafik Kaynağı</h2>
        <div class="in-grid in-grid--3">
          <div class="in-card">
            <p class="in-card-kicker">Nereden geldi</p>
            <?php if ($hasTraffic): ?>
              <div class="in-chart in-chart--donut"><canvas id="chart-source"></canvas></div>
            <?php else: ?>
              <div class="in-empty">Referrer kaydı birikince burada pie grafiği çıkacak.</div>
            <?php endif; ?>
          </div>
          <div class="in-card">
            <p class="in-card-kicker">Cihaz</p>
            <?php if ($hasDevice): ?>
              <div class="in-chart in-chart--donut"><canvas id="chart-device"></canvas></div>
            <?php else: ?>
              <div class="in-empty">Mobil / masaüstü kırılımı veri geldiğinde burada.</div>
            <?php endif; ?>
          </div>
          <div class="in-card">
            <p class="in-card-kicker">En çok aranan kelimeler</p>
            <?php if ($isDemo): ?>
              <div id="wordcloud" class="in-wordcloud-wrap" aria-label="Anahtar kelime bulutu"></div>
            <?php else: ?>
              <div class="in-empty">Arama sorgu kaydı eklendiğinde kelime bulutu burada çizilecek.</div>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <!-- G — Rekabet · Piyasa -->
      <section class="in-section">
        <div class="in-section-head">
          <h2 class="in-section-title">Rekabet · Piyasa</h2>
          <span class="in-premium-chip">Premium</span>
        </div>
        <?php if ($isDemo): ?>
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
        <?php else: ?>
          <div class="in-card">
            <div class="in-empty">
              Aynı pozisyon için piyasa ortalamaları ve rekabet skoru — sektörde yeterli ilan &amp; başvuru biriktiğinde burada yayına alınacak.
            </div>
          </div>
        <?php endif; ?>
      </section>

      <!-- H — Önerilen adaylar -->
      <section class="in-section">
        <h2 class="in-section-title">Önerilen Adaylar</h2>
        <?php if ($isDemo): ?>
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
        <?php else: ?>
          <div class="in-card">
            <div class="in-empty">
              Aranan becerilere göre eşleşen adaylar — aday havuzu ve eşleşme motoru aktifleştiğinde önerilen profiller burada sıralanır.
            </div>
          </div>
        <?php endif; ?>
      </section>

      <!-- I — Kalite skoru (computed from the listing content itself) -->
      <section class="in-section">
        <h2 class="in-section-title">İlan Kalitesi</h2>
        <div class="in-grid in-grid--2">
          <div class="in-card">
            <p class="in-card-kicker">İlan skoru</p>
            <div class="in-score">
              <div class="in-score-value"><?= (int) $qualityScore ?><span>/100</span></div>
              <div class="in-score-bar<?= $qualityScore >= 70 ? ' in-score-bar--green' : '' ?>"><span style="width: <?= (int) $qualityScore ?>%;"></span></div>
              <p class="in-score-note">
                <?php
                $missing = [];
                if (empty($listing['salary_min']) || empty($listing['salary_max'])) $missing[] = 'Maaş aralığı';
                if (empty($listing['benefits']))          $missing[] = 'Yan haklar';
                if (empty($listing['experience_level'])) $missing[] = 'Deneyim seviyesi';
                if (empty($listing['skills']))            $missing[] = 'Gerekli beceriler';
                if ($missing === []) {
                    echo 'İlanın tüm temel alanlarda dolu görünüyor.';
                } else {
                    echo 'Eksik alanlar: ' . htmlspecialchars(implode(' · ', $missing), ENT_QUOTES, 'UTF-8') . '. Bunları doldurursan skor yükselir.';
                }
                ?>
              </p>
            </div>
          </div>
          <div class="in-card">
            <p class="in-card-kicker">Aksiyon önerileri</p>
            <div class="in-empty">
              Akıllı öneriler — ilan performansı birkaç gün izlendikten sonra "hangi alan kaç % etki yaratır" bazlı burada listelenecek.
            </div>
          </div>
        </div>
      </section>

      <!-- J — Davranış -->
      <section class="in-section">
        <h2 class="in-section-title">Etkileşim · Davranış</h2>
        <?php if ($isDemo): ?>
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
        <?php else: ?>
          <div class="in-card">
            <div class="in-empty">
              Sayfada kalma süresi, geri dönüş oranı, en çok okunan bölüm — ilan sayfasına event takibi eklendikten sonra buraya düşecek.
            </div>
          </div>
        <?php endif; ?>
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
      labels:           <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>,
      views:            <?= json_encode($dailyViews) ?>,
      apps:             <?= json_encode($dailyApps) ?>,
      saves:            <?= json_encode($dailySaves) ?>,
      hourly:           <?= json_encode($hourly) ?>,
      trafficBreakdown: <?= json_encode($trafficBreakdown, JSON_UNESCAPED_UNICODE) ?>,
      deviceBreakdown:  <?= json_encode($deviceBreakdown, JSON_UNESCAPED_UNICODE) ?>,
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
