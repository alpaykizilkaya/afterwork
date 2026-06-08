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
require_once __DIR__ . '/../../../backend/config/taxonomy.php';
require_once __DIR__ . '/../../../backend/auth/session-helper.php';

$tax = aw_taxonomy();

$employer   = is_array($_SESSION['employer'] ?? null) ? $_SESSION['employer'] : [];
$employerId = (int) ($employer['id'] ?? 0);

// ── Filters (all GET so URLs stay shareable) ──
$q       = trim((string) ($_GET['q'] ?? ''));
$fSector = trim((string) ($_GET['sector'] ?? ''));
$fCity   = trim((string) ($_GET['location'] ?? ''));
$fSize   = trim((string) ($_GET['size'] ?? ''));
$fIso500 = (string) ($_GET['iso500'] ?? '');
$sort    = (string) ($_GET['sort'] ?? 'yeni');

$sortMap = [
    'yeni'     => 'e.created_at DESC, e.id DESC',
    'eski'     => 'e.created_at ASC, e.id ASC',
    'isim'     => 'e.company_name ASC',
    'ilan_cok' => 'active_listings DESC, e.company_name ASC',
];
if (!isset($sortMap[$sort])) {
    $sort = 'yeni';
}

$orderByTax = static function (array $present, array $canon): array {
    $out = [];
    foreach ($canon as $c) {
        if (in_array($c, $present, true)) {
            $out[] = $c;
        }
    }
    foreach ($present as $p) {
        if (!in_array($p, $out, true)) {
            $out[] = $p;
        }
    }
    return $out;
};

$companies      = [];
$optSector      = [];
$cities         = [];
$optSize        = [];
$iso500Map      = [];
$totalCompanies = 0;
$totalActive    = 0;
$loadError      = false;

try {
    $pdo = db();

    // Scope: every other company (the dev account, id 0, sees all).
    $scopeSql    = 'e.id <> :me';
    $scopeParams = ['me' => $employerId];

    $totRow = $pdo->prepare(
        "SELECT COUNT(*) AS companies,
                (SELECT COUNT(*) FROM job_listings jl
                  JOIN employers e2 ON e2.id = jl.employer_id
                 WHERE jl.is_active = 1 AND e2.id <> :me2) AS listings
           FROM employers e
          WHERE {$scopeSql}"
    );
    $totRow->execute($scopeParams + ['me2' => $employerId]);
    $tot = $totRow->fetch() ?: ['companies' => 0, 'listings' => 0];
    $totalCompanies = (int) $tot['companies'];
    $totalActive    = (int) $tot['listings'];

    $distinct = static function (PDO $pdo, string $expr, string $scopeSql, array $params): array {
        $stmt = $pdo->prepare(
            "SELECT DISTINCT TRIM({$expr}) AS v
               FROM employers e
              WHERE {$scopeSql} AND {$expr} IS NOT NULL AND TRIM({$expr}) <> ''
              ORDER BY v ASC"
        );
        $stmt->execute($params);
        return array_map(static fn ($r) => (string) $r['v'], $stmt->fetchAll());
    };
    $optSector = $orderByTax($distinct($pdo, 'e.sector', $scopeSql, $scopeParams), $tax['sectors']);
    $cities    = $distinct($pdo, 'e.city', $scopeSql, $scopeParams);
    $optSize   = $orderByTax($distinct($pdo, 'e.company_size', $scopeSql, $scopeParams), $tax['company_sizes']);

    $isoRow = $pdo->prepare("SELECT SUM(e.is_iso500 = 1) AS iso1 FROM employers e WHERE {$scopeSql}");
    $isoRow->execute($scopeParams);
    if ((int) ($isoRow->fetchColumn() ?: 0) > 0) {
        $iso500Map['evet'] = 'ISO 500 şirketleri';
    }

    // ── Apply filters ──
    $where  = [$scopeSql];
    $params = $scopeParams;

    if ($q !== '') {
        $where[] = '(e.company_name LIKE :q_name OR e.sector LIKE :q_sector OR e.city LIKE :q_city OR e.about LIKE :q_about)';
        $like = '%' . $q . '%';
        $params['q_name']   = $like;
        $params['q_sector'] = $like;
        $params['q_city']   = $like;
        $params['q_about']  = $like;
    }
    if ($fSector !== '') {
        $where[] = 'e.sector = :sector';
        $params['sector'] = $fSector;
    }
    if ($fCity !== '') {
        $where[] = 'e.city = :city';
        $params['city'] = $fCity;
    }
    if ($fSize !== '') {
        $where[] = 'e.company_size = :size';
        $params['size'] = $fSize;
    }
    if ($fIso500 === 'evet') {
        $where[] = 'e.is_iso500 = 1';
    }

    $sql =
        "SELECT e.id, e.company_name, e.sector, e.company_size, e.is_iso500,
                e.city, e.about, e.website, e.linkedin, e.founded_year, e.created_at,
                (SELECT COUNT(*) FROM job_listings jl WHERE jl.employer_id = e.id AND jl.is_active = 1) AS active_listings
           FROM employers e
          WHERE " . implode(' AND ', $where) . "
          ORDER BY {$sortMap[$sort]}
          LIMIT 200";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $companies = $stmt->fetchAll();
} catch (Throwable $e) {
    $loadError = true;
}

$resultCount = count($companies);
$hasFilters  = $q !== '' || $fSector !== '' || $fCity !== '' || $fSize !== '' || $fIso500 !== '';

$feedInitials = static function (string $name): string {
    $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
    $a = mb_substr((string) ($words[0] ?? '?'), 0, 1, 'UTF-8');
    $b = isset($words[1]) ? mb_substr((string) $words[1], 0, 1, 'UTF-8') : '';
    $out = mb_strtoupper($a . $b, 'UTF-8');
    return $out !== '' ? $out : '?';
};

$renderSelect = static function (string $name, string $placeholder, array $options, string $current): string {
    $html = '<select class="ep-select" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" aria-label="' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '">';
    $html .= '<option value="">' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '</option>';
    foreach ($options as $opt) {
        $sel  = $opt === $current ? ' selected' : '';
        $safe = htmlspecialchars($opt, ENT_QUOTES, 'UTF-8');
        $html .= '<option value="' . $safe . '"' . $sel . '>' . $safe . '</option>';
    }
    return $html . '</select>';
};
$renderSelectKV = static function (string $name, string $placeholder, array $map, string $current): string {
    $html = '<select class="ep-select" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" aria-label="' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '">';
    $html .= '<option value="">' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '</option>';
    foreach ($map as $val => $label) {
        $sel = (string) $val === $current ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>'
            . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    return $html . '</select>';
};

$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$sortLabels = [
    'yeni'     => 'En yeni',
    'eski'     => 'En eski',
    'isim'     => 'Şirket adı (A–Z)',
    'ilan_cok' => 'En çok ilan',
];
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AFTERWORK | Akış · İş Verenler</title>
  <link rel="stylesheet" href="/frontend/assets/css/employer/panel.css?v=<?= filemtime(__DIR__ . '/../../assets/css/employer/panel.css') ?>">
  <link rel="stylesheet" href="/frontend/assets/css/employer/feed.css?v=<?= filemtime(__DIR__ . '/../../assets/css/employer/feed.css') ?>">
  <link rel="stylesheet" href="/frontend/assets/css/shared/logout-modal.css?v=<?= filemtime(__DIR__ . '/../../assets/css/shared/logout-modal.css') ?>">
</head>
<body>
  <div class="ep-page">
    <?php
    $activeTab = 'feed';
    $searchQuery = $q;
    include __DIR__ . '/../../partials/employer-topbar.php';
    ?>

    <section class="ep-dukkan ep-feed" aria-label="Akış · İş Verenler">
      <header class="ep-dukkan-head">
        <div>
          <p class="ep-dukkan-kicker">Akış</p>
          <h1>İş verenleri keşfet</h1>
          <p class="ep-dukkan-lead">
            Platformdaki şirketleri incele; sektöre, şehre ve büyüklüğe göre filtrele, aktif ilan sayılarını gör.
          </p>
        </div>
        <div class="ep-dukkan-stats">
          <div class="ep-stat-card--light">
            <strong><?= number_format($totalCompanies, 0, ',', '.') ?></strong>
            <span>Şirket</span>
          </div>
          <div class="ep-stat-card--light">
            <strong><?= number_format($totalActive, 0, ',', '.') ?></strong>
            <span>Aktif İlan</span>
          </div>
        </div>
      </header>

      <?php $activeFeedTab = 'verenler'; include __DIR__ . '/../../partials/feed-switch.php'; ?>

      <form class="ep-feed-filters" method="get" action="/akis.php" role="search">
        <input type="hidden" name="tab" value="verenler">
        <div class="ep-feed-search">
          <svg class="ep-feed-search-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.6"/>
            <path d="M11 11l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
          </svg>
          <input type="search" name="q" value="<?= $h($q) ?>"
                 placeholder="Şirket, sektör, şehir ara…" autocomplete="off" aria-label="Ara">
        </div>

        <div class="ep-feed-filter-grid">
          <?php if ($optSector): ?><?= $renderSelect('sector', 'Tüm sektörler', $optSector, $fSector) ?><?php endif; ?>
          <?php if ($cities): ?><?= $renderSelect('location', 'Şehir', $cities, $fCity) ?><?php endif; ?>
          <?php if ($optSize): ?><?= $renderSelect('size', 'Şirket büyüklüğü', $optSize, $fSize) ?><?php endif; ?>
          <?php if ($iso500Map): ?><?= $renderSelectKV('iso500', 'Şirket özelliği', $iso500Map, $fIso500) ?><?php endif; ?>
        </div>

        <div class="ep-feed-sortrow">
          <label class="ep-feed-sort">
            <span>Sırala</span>
            <select class="ep-select" name="sort" aria-label="Sıralama">
              <?php foreach ($sortLabels as $val => $label): ?>
                <option value="<?= $val ?>"<?= $sort === $val ? ' selected' : '' ?>><?= $h($label) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <div class="ep-feed-actions">
            <button type="submit" class="ep-feed-apply">Uygula</button>
            <?php if ($hasFilters || $sort !== 'yeni'): ?>
              <a class="ep-feed-clear" href="/akis.php?tab=verenler">Temizle</a>
            <?php endif; ?>
          </div>
        </div>
      </form>

      <?php if ($loadError): ?>
        <div class="ep-feedback ep-feedback--error" role="alert">
          <p>Akış yüklenirken bir sorun oluştu. Lütfen sayfayı yenile.</p>
        </div>
      <?php endif; ?>

      <p class="ep-feed-meta">
        <?php if ($resultCount > 0): ?>
          <strong><?= number_format($resultCount, 0, ',', '.') ?></strong> şirket
          <?= $hasFilters ? 'eşleşti' : 'listeleniyor' ?>
        <?php else: ?>
          Sonuç yok
        <?php endif; ?>
      </p>

      <?php if ($resultCount > 0): ?>
        <div class="ep-dukkan-grid ep-feed-grid">
          <?php foreach ($companies as $co):
            $cName  = (string) ($co['company_name'] ?? '');
            $cSec   = trim((string) ($co['sector'] ?? ''));
            $cCity  = trim((string) ($co['city'] ?? ''));
            $cSize  = trim((string) ($co['company_size'] ?? ''));
            $cIso   = (int) ($co['is_iso500'] ?? 0) === 1;
            $cAbout = trim((string) ($co['about'] ?? ''));
            $cWeb   = trim((string) ($co['website'] ?? ''));
            $cLink  = trim((string) ($co['linkedin'] ?? ''));
            $cYear  = trim((string) ($co['founded_year'] ?? ''));
            $cActive = (int) ($co['active_listings'] ?? 0);
            $subLine = $cSec !== '' && $cCity !== '' ? $cSec . ' · ' . $cCity : ($cSec ?: $cCity);
            $excerpt = $cAbout !== '' ? mb_substr($cAbout, 0, 150, 'UTF-8') . (mb_strlen($cAbout, 'UTF-8') > 150 ? '…' : '') : '';
          ?>
          <article class="ep-poster-card ep-feed-card">
            <div class="ep-feed-company">
              <span class="ep-feed-avatar" aria-hidden="true"><?= $h($feedInitials($cName)) ?></span>
              <span class="ep-feed-company-meta">
                <span class="ep-feed-company-name">
                  <?= $h($cName ?: 'Şirket') ?>
                  <?php if ($cIso): ?><span class="ep-feed-tag" title="ISO 500 şirketi">ISO 500</span><?php endif; ?>
                </span>
                <?php if ($subLine !== ''): ?>
                  <span class="ep-feed-company-sub"><?= $h($subLine) ?></span>
                <?php endif; ?>
              </span>
            </div>

            <div class="ep-poster-chips">
              <?php if ($cSize !== ''): ?><span class="ep-poster-chip"><?= $h($cSize) ?></span><?php endif; ?>
              <?php if ($cYear !== ''): ?><span class="ep-poster-chip ep-poster-chip--ghost">Kuruluş <?= $h($cYear) ?></span><?php endif; ?>
              <span class="ep-poster-chip ep-poster-chip--ghost"><?= (int) $cActive ?> aktif ilan</span>
            </div>

            <?php if ($excerpt !== ''): ?>
              <p class="ep-feed-excerpt"><?= $h($excerpt) ?></p>
            <?php endif; ?>

            <footer class="ep-poster-foot">
              <?php if ($cActive > 0): ?>
                <a class="ep-feed-contact" href="/akis.php?q=<?= rawurlencode($cName) ?>">İlanları gör</a>
              <?php endif; ?>
              <?php if ($cWeb !== ''): ?>
                <a class="ep-feed-lang" href="<?= $h(preg_match('~^https?://~i', $cWeb) ? $cWeb : 'https://' . $cWeb) ?>" target="_blank" rel="noopener noreferrer nofollow">Web sitesi</a>
              <?php endif; ?>
              <?php if ($cLink !== ''): ?>
                <a class="ep-feed-lang" href="<?= $h(preg_match('~^https?://~i', $cLink) ? $cLink : 'https://' . $cLink) ?>" target="_blank" rel="noopener noreferrer nofollow">LinkedIn</a>
              <?php endif; ?>
            </footer>
          </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="ep-feed-empty">
          <span class="ep-feed-empty-mark" aria-hidden="true">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
              <circle cx="10.5" cy="10.5" r="6.5" stroke="currentColor" stroke-width="1.6"/>
              <path d="M15.5 15.5L20 20" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
          </span>
          <?php if ($hasFilters): ?>
            <h2>Eşleşen şirket bulunamadı</h2>
            <p>Filtreleri gevşetmeyi dene ya da aramayı temizle.</p>
            <a class="ep-feed-empty-cta" href="/akis.php?tab=verenler">Filtreleri temizle</a>
          <?php else: ?>
            <h2>Henüz başka şirket yok</h2>
            <p>Platforma yeni şirketler katıldığında burada görünecek.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
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
  <script src="/frontend/assets/js/employer/feed.js?v=<?= filemtime(__DIR__ . '/../../assets/js/employer/feed.js') ?>" defer></script>
  <script src="/frontend/assets/js/shared/logout-modal.js?v=<?= filemtime(__DIR__ . '/../../assets/js/shared/logout-modal.js') ?>" defer></script>
</body>
</html>
