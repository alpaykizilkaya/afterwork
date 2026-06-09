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
$companyName = trim((string) ($employer['company_name'] ?? '')) ?: 'Şirketiniz';

$viewId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($viewId <= 0) {
    header('Location: /akis.php');
    exit;
}

// Spectator view: load any ACTIVE listing by id (the feed only links to other
// companies' active listings). Read-only — no application is ever created.
$listing = null;
try {
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT jl.*, e.company_name, e.sector, e.city, e.company_size, e.is_iso500,
                e.about, e.website, e.linkedin
         FROM job_listings jl
         JOIN employers e ON e.id = jl.employer_id
         WHERE jl.id = :id AND jl.is_active = 1
         LIMIT 1'
    );
    $stmt->execute(['id' => $viewId]);
    $listing = $stmt->fetch() ?: null;
} catch (Throwable) {
    $listing = null;
}

if ($listing === null) {
    header('Location: /akis.php');
    exit;
}

/* ---- record a view (feeds Mercek analytics) --------------------------- *
 * One view per session per listing per 30 min so refreshes don't inflate.
 * The listing owner viewing their own listing is not counted. Analytics
 * must never break the page, so the whole block is best-effort. */
try {
    $viewerAccountId  = (int) ($_SESSION['account']['account_id'] ?? 0);
    $viewerEmployerId = (int) ($employer['id'] ?? 0);
    $isOwnerView      = $viewerEmployerId > 0 && (int) ($listing['employer_id'] ?? 0) === $viewerEmployerId;

    if (!$isOwnerView) {
        $sessionHash = hash('sha256', (session_id() ?: '') . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''));

        $recent = $pdo->prepare(
            'SELECT 1 FROM listing_views
              WHERE listing_id = :id AND viewer_session_hash = :sh
                AND viewed_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)
              LIMIT 1'
        );
        $recent->execute(['id' => $viewId, 'sh' => $sessionHash]);

        if (!$recent->fetchColumn()) {
            $ref  = (string) ($_SERVER['HTTP_REFERER'] ?? '');
            $host = (string) (parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST) ?: '');
            $source = 'Direkt';
            if ($ref !== '') {
                $refHost = (string) (parse_url($ref, PHP_URL_HOST) ?: '');
                if ($refHost === '' || strcasecmp($refHost, $host) === 0) {
                    $source = str_contains($ref, '/akis') ? 'Akış' : 'Site içi';
                } elseif (preg_match('/google|bing|yahoo|yandex|duckduckgo|ecosia/i', $refHost)) {
                    $source = 'Arama';
                } elseif (preg_match('/linkedin|twitter|x\.com|facebook|instagram|t\.co/i', $refHost)) {
                    $source = 'Sosyal';
                } else {
                    $source = 'Dış bağlantı';
                }
            }

            $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
            $device = 'Masaüstü';
            if (preg_match('/iPad|Tablet|PlayBook|Silk/i', $ua)) {
                $device = 'Tablet';
            } elseif (preg_match('/Mobi|Android|iPhone|iPod|Windows Phone/i', $ua)) {
                $device = 'Mobil';
            }

            // Coarse geolocation from the visitor IP (country/city only, never the
            // raw IP). Best-effort — a slow/failed lookup must not block the view.
            require_once __DIR__ . '/../../../backend/geo/ip-geo.php';
            $clientIp = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $fwd = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
                $cand = trim($fwd[0]);
                if (filter_var($cand, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    $clientIp = $cand;
                }
            }
            $geo = geo_from_ip($clientIp);

            $base = [
                'id'  => $viewId,
                'acc' => $viewerAccountId > 0 ? $viewerAccountId : null,
                'sh'  => $sessionHash,
                'ref' => $ref !== '' ? mb_substr($ref, 0, 512) : null,
                'src' => $source,
                'dev' => $device,
                'ua'  => $ua !== '' ? mb_substr($ua, 0, 512) : null,
            ];
            try {
                $pdo->prepare(
                    'INSERT INTO listing_views
                        (listing_id, viewer_account_id, viewer_session_hash, referrer, traffic_source, device_type, user_agent, country_code, country, city)
                     VALUES (:id, :acc, :sh, :ref, :src, :dev, :ua, :cc, :co, :ci)'
                )->execute($base + [
                    'cc' => ($geo['country_code'] ?? '') !== '' ? $geo['country_code'] : null,
                    'co' => ($geo['country'] ?? '') !== '' ? $geo['country'] : null,
                    'ci' => ($geo['city'] ?? '') !== '' ? $geo['city'] : null,
                ]);
            } catch (Throwable) {
                // Geo columns not migrated yet — fall back to the original insert so
                // view tracking keeps working until the migration is applied.
                $pdo->prepare(
                    'INSERT INTO listing_views
                        (listing_id, viewer_account_id, viewer_session_hash, referrer, traffic_source, device_type, user_agent)
                     VALUES (:id, :acc, :sh, :ref, :src, :dev, :ua)'
                )->execute($base);
            }
        }
    }
} catch (Throwable) {
    // analytics is best-effort — never let it break the listing view
}

$initials = static function (string $name): string {
    $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
    $a = mb_substr((string) ($words[0] ?? '?'), 0, 1, 'UTF-8');
    $b = isset($words[1]) ? mb_substr((string) $words[1], 0, 1, 'UTF-8') : '';
    $out = mb_strtoupper($a . $b, 'UTF-8');
    return $out !== '' ? $out : '?';
};

$min = $listing['salary_min'] !== null ? (int) $listing['salary_min'] : null;
$max = $listing['salary_max'] !== null ? (int) $listing['salary_max'] : null;
$salary = null;
if ($min !== null && $max !== null) {
    $salary = number_format($min, 0, ',', '.') . ' – ' . number_format($max, 0, ',', '.') . ' ₺';
} elseif ($min !== null) {
    $salary = number_format($min, 0, ',', '.') . ' ₺+';
} elseif ($max !== null) {
    $salary = number_format($max, 0, ',', '.') . ' ₺’ye kadar';
}

$title = (string) ($listing['title'] ?? '');
$company = (string) ($listing['company_name'] ?? '');
$sector = trim((string) ($listing['sector'] ?? ''));
$city = trim((string) ($listing['city'] ?? ''));
$size = trim((string) ($listing['company_size'] ?? ''));
$companyLine = $sector !== '' && $city !== '' ? $sector . ' · ' . $city : ($sector ?: $city);
$email = trim((string) ($listing['contact_email'] ?? ''));
$created = (string) ($listing['created_at'] ?? '');
$postedDate = ($ts = strtotime($created)) !== false ? date('d.m.Y', $ts) : '';

// Listing "künye" facts → tiles (company size lives in the company card instead).
$facts = [];
$addFact = static function (string $label, $value) use (&$facts): void {
    $v = trim((string) ($value ?? ''));
    if ($v !== '') {
        $facts[] = [$label, $v];
    }
};
$addFact('Çalışma Şekli', $listing['employment_type'] ?? '');
$addFact('Çalışma Tercihi', $listing['work_model'] ?? '');
$addFact('Departman', $listing['department'] ?? '');
$addFact('Pozisyon Seviyesi', $listing['position_level'] ?? '');
$addFact('Deneyim Seviyesi', $listing['experience_level'] ?? '');
$addFact('Eğitim Seviyesi', $listing['education_level'] ?? '');
$loc = trim((string) ($listing['location'] ?? ''));
$dist = trim((string) ($listing['district'] ?? ''));
$addFact('Konum', $dist !== '' && $loc !== '' ? $loc . ' · ' . $dist : $loc);
$addFact('İlan Dili', $listing['listing_language'] ?? '');
if (($listing['openings_count'] ?? null) !== null && (int) $listing['openings_count'] > 0) {
    $addFact('Açık Pozisyon', (string) ((int) $listing['openings_count']) . ' kişi');
}
$addFact('Çalışma Saatleri', $listing['work_hours'] ?? '');
if (!empty($listing['deadline'])) {
    $dts = strtotime((string) $listing['deadline']);
    $addFact('Son Başvuru', $dts !== false ? date('d.m.Y', $dts) : (string) $listing['deadline']);
}

$skills = array_values(array_filter(array_map('trim', preg_split('/[,;\/]+/u', (string) ($listing['skills'] ?? '')) ?: [])));
$isDisab = (int) ($listing['is_disability'] ?? 0) === 1;
$isIso = (int) ($listing['is_iso500'] ?? 0) === 1;
$about = trim((string) ($listing['about'] ?? ''));
$website = trim((string) ($listing['website'] ?? ''));
$linkedin = trim((string) ($listing['linkedin'] ?? ''));
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AFTERWORK | <?= htmlspecialchars($title ?: 'İlan', ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="/frontend/assets/css/employer/panel.css?v=<?= filemtime(__DIR__ . '/../../assets/css/employer/panel.css') ?>">
  <link rel="stylesheet" href="/frontend/assets/css/employer/feed.css?v=<?= filemtime(__DIR__ . '/../../assets/css/employer/feed.css') ?>">
  <link rel="stylesheet" href="/frontend/assets/css/shared/logout-modal.css?v=<?= filemtime(__DIR__ . '/../../assets/css/shared/logout-modal.css') ?>">
</head>
<body>
  <div class="ep-page">
    <?php
    $activeTab = 'feed';
    $searchQuery = '';
    include __DIR__ . '/../../partials/employer-topbar.php';
    ?>

    <!-- HERO ────────────────────────────────────────────── -->
    <section class="ep-vw-hero" aria-label="İlan başlığı">
      <div class="ep-vw-hero-inner">
        <div class="ep-vw-hero-top">
          <a class="ep-vw-back" href="/akis.php">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true">
              <path d="M10 3L5 8l5 5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Akışa dön
          </a>
          <span class="ep-vw-badge">
            <svg width="13" height="13" viewBox="0 0 16 16" fill="none" aria-hidden="true">
              <path d="M1.5 8S4 3.5 8 3.5 14.5 8 14.5 8 12 12.5 8 12.5 1.5 8 1.5 8Z" stroke="currentColor" stroke-width="1.3"/>
              <circle cx="8" cy="8" r="1.8" stroke="currentColor" stroke-width="1.3"/>
            </svg>
            İzleme modu
          </span>
        </div>

        <div class="ep-vw-company">
          <span class="ep-vw-avatar" aria-hidden="true"><?= htmlspecialchars($initials($company), ENT_QUOTES, 'UTF-8') ?></span>
          <div class="ep-vw-company-meta">
            <p class="ep-vw-company-name">
              <?= htmlspecialchars($company ?: 'Şirket', ENT_QUOTES, 'UTF-8') ?>
              <?php if ($isIso): ?><span class="ep-feed-tag" title="ISO 500 şirketi">ISO 500</span><?php endif; ?>
            </p>
            <?php if ($companyLine !== ''): ?>
              <p class="ep-vw-company-sub"><?= htmlspecialchars($companyLine, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
          </div>
          <span class="ep-vw-status"><span class="ep-vw-status-dot"></span>Aktif</span>
        </div>

        <h1 class="ep-vw-title"><?= htmlspecialchars($title ?: 'İsimsiz ilan', ENT_QUOTES, 'UTF-8') ?></h1>

        <div class="ep-vw-hero-foot">
          <?php if ($salary !== null): ?>
            <p class="ep-vw-salary"><?= htmlspecialchars($salary, ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>
          <?php if ($skills !== []): ?>
            <div class="ep-vw-skills">
              <?php foreach ($skills as $sk): ?>
                <span class="ep-vw-skill"><?= htmlspecialchars($sk, ENT_QUOTES, 'UTF-8') ?></span>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="ep-vw-hero-actions">
          <?php if ($email !== ''): ?>
            <a class="ep-vw-cta" href="mailto:<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>?subject=<?= rawurlencode($title . ' ilanı hakkında') ?>">
              İletişime geç
            </a>
          <?php endif; ?>
          <?php if ($isDisab): ?><span class="ep-vw-flag">Engelli ilanı</span><?php endif; ?>
          <?php if ($postedDate !== ''): ?><span class="ep-vw-posted">Yayın: <?= htmlspecialchars($postedDate, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
        </div>
      </div>
    </section>

    <!-- BODY ────────────────────────────────────────────── -->
    <section class="ep-vw-body">
      <?php if ($facts !== []): ?>
        <div class="ep-vw-meta" aria-label="İlan künyesi">
          <?php foreach ($facts as [$label, $value]): ?>
            <div class="ep-vw-tile">
              <span class="ep-vw-tile-label"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
              <strong class="ep-vw-tile-value"><?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="ep-vw-content">
        <div class="ep-vw-main">
          <?php
          $section = static function (string $heading, string $text): void {
              $text = trim($text);
              if ($text === '') {
                  return;
              }
              echo '<section class="ep-vw-section">';
              echo '<h2>' . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . '</h2>';
              echo '<p>' . nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8')) . '</p>';
              echo '</section>';
          };
          $section('İş Tanımı', (string) ($listing['description'] ?? ''));
          $section('Aranan Özellikler', (string) ($listing['requirements'] ?? ''));
          $section('Yan Haklar', (string) ($listing['benefits'] ?? ''));
          ?>
        </div>

        <aside class="ep-vw-side">
          <div class="ep-vw-company-card">
            <p class="ep-vw-card-kicker">İlanı Veren</p>
            <div class="ep-vw-card-head">
              <span class="ep-vw-card-avatar" aria-hidden="true"><?= htmlspecialchars($initials($company), ENT_QUOTES, 'UTF-8') ?></span>
              <div>
                <p class="ep-vw-card-name"><?= htmlspecialchars($company ?: 'Şirket', ENT_QUOTES, 'UTF-8') ?></p>
                <?php if ($companyLine !== ''): ?><p class="ep-vw-card-sub"><?= htmlspecialchars($companyLine, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
              </div>
            </div>

            <?php if ($size !== '' || $isIso): ?>
              <div class="ep-vw-card-meta">
                <?php if ($size !== ''): ?><span class="ep-vw-card-pill"><?= htmlspecialchars($size, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                <?php if ($isIso): ?><span class="ep-vw-card-pill ep-vw-card-pill--gold">ISO 500</span><?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if ($about !== ''): ?>
              <p class="ep-vw-card-about"><?= nl2br(htmlspecialchars($about, ENT_QUOTES, 'UTF-8')) ?></p>
            <?php endif; ?>

            <?php if ($website !== '' || $linkedin !== ''): ?>
              <div class="ep-vw-card-links">
                <?php if ($website !== ''): ?><a href="<?= htmlspecialchars($website, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener nofollow">Web sitesi</a><?php endif; ?>
                <?php if ($linkedin !== ''): ?><a href="<?= htmlspecialchars($linkedin, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener nofollow">LinkedIn</a><?php endif; ?>
              </div>
            <?php endif; ?>
          </div>

          <p class="ep-vw-note">
            <strong>İzleme modu.</strong> Bu görünüm yalnızca inceleme amaçlıdır; bu ilana başvuru gönderilmez.
          </p>
        </aside>
      </div>
    </section>
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

  <script src="/frontend/assets/js/employer/topbar.js?v=<?= filemtime(__DIR__ . '/../../assets/js/employer/topbar.js') ?>" defer></script>
  <script src="/frontend/assets/js/shared/logout-modal.js?v=<?= filemtime(__DIR__ . '/../../assets/js/shared/logout-modal.js') ?>" defer></script>
</body>
</html>
