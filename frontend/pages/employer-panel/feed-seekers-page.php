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

// ── Filters (GET) ──
$q    = trim((string) ($_GET['q'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'yeni');

$sortMap = [
    'yeni' => 's.created_at DESC, s.id DESC',
    'eski' => 's.created_at ASC, s.id ASC',
    'isim' => 's.full_name ASC',
];
if (!isset($sortMap[$sort])) {
    $sort = 'yeni';
}

$seekers      = [];
$totalSeekers = 0;
$loadError    = false;

try {
    $pdo = db();

    $totalSeekers = (int) $pdo->query('SELECT COUNT(*) FROM seekers')->fetchColumn();

    $where  = ['1 = 1'];
    $params = [];
    if ($q !== '') {
        $where[] = 's.full_name LIKE :q_name';
        $params['q_name'] = '%' . $q . '%';
    }

    $sql =
        "SELECT s.id, s.full_name, s.created_at
           FROM seekers s
          WHERE " . implode(' AND ', $where) . "
          ORDER BY {$sortMap[$sort]}
          LIMIT 200";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $seekers = $stmt->fetchAll();
} catch (Throwable $e) {
    $loadError = true;
}

$resultCount = count($seekers);
$hasFilters  = $q !== '';

$feedInitials = static function (string $name): string {
    $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
    $a = mb_substr((string) ($words[0] ?? '?'), 0, 1, 'UTF-8');
    $b = isset($words[1]) ? mb_substr((string) $words[1], 0, 1, 'UTF-8') : '';
    $out = mb_strtoupper($a . $b, 'UTF-8');
    return $out !== '' ? $out : '?';
};

$feedTimeAgo = static function (string $created): ?string {
    if ($created === '' || ($ts = strtotime($created)) === false) {
        return null;
    }
    $days = (int) floor((time() - $ts) / 86400);
    if ($days <= 0) {
        return 'bugün katıldı';
    }
    if ($days === 1) {
        return '1 gün önce katıldı';
    }
    if ($days < 30) {
        return $days . ' gün önce katıldı';
    }
    $months = (int) floor($days / 30);
    return ($months === 1 ? '1 ay' : $months . ' ay') . ' önce katıldı';
};

$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$sortLabels = [
    'yeni' => 'En yeni',
    'eski' => 'En eski',
    'isim' => 'Ad (A–Z)',
];
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AFTERWORK | Akış · İş Arayanlar</title>
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

    <section class="ep-dukkan ep-feed" aria-label="Akış · İş Arayanlar">
      <header class="ep-dukkan-head">
        <div>
          <p class="ep-dukkan-kicker">Akış</p>
          <h1>İş arayanları keşfet</h1>
          <p class="ep-dukkan-lead">
            Platforma kayıtlı adayları gör ve isme göre ara.
          </p>
        </div>
        <div class="ep-dukkan-stats">
          <div class="ep-stat-card--light">
            <strong><?= number_format($totalSeekers, 0, ',', '.') ?></strong>
            <span>Aday</span>
          </div>
        </div>
      </header>

      <?php $activeFeedTab = 'arayanlar'; include __DIR__ . '/../../partials/feed-switch.php'; ?>

      <form class="ep-feed-filters" method="get" action="/akis.php" role="search">
        <input type="hidden" name="tab" value="arayanlar">
        <div class="ep-feed-search">
          <svg class="ep-feed-search-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.6"/>
            <path d="M11 11l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
          </svg>
          <input type="search" name="q" value="<?= $h($q) ?>"
                 placeholder="Aday adı ara…" autocomplete="off" aria-label="Ara">
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
              <a class="ep-feed-clear" href="/akis.php?tab=arayanlar">Temizle</a>
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
          <strong><?= number_format($resultCount, 0, ',', '.') ?></strong> aday
          <?= $hasFilters ? 'eşleşti' : 'listeleniyor' ?>
        <?php else: ?>
          Sonuç yok
        <?php endif; ?>
      </p>

      <?php if ($resultCount > 0): ?>
        <div class="ep-dukkan-grid ep-feed-grid">
          <?php foreach ($seekers as $sk):
            $sName = (string) ($sk['full_name'] ?? '');
            $ago   = $feedTimeAgo((string) ($sk['created_at'] ?? ''));
          ?>
          <article class="ep-poster-card ep-feed-card">
            <div class="ep-feed-company">
              <span class="ep-feed-avatar" aria-hidden="true"><?= $h($feedInitials($sName)) ?></span>
              <span class="ep-feed-company-meta">
                <span class="ep-feed-company-name"><?= $h($sName ?: 'Aday') ?></span>
                <?php if ($ago !== null): ?>
                  <span class="ep-feed-company-sub"><?= $h($ago) ?></span>
                <?php endif; ?>
              </span>
            </div>
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
            <h2>Eşleşen aday bulunamadı</h2>
            <p>Aramayı temizleyip tüm adayları görebilirsin.</p>
            <a class="ep-feed-empty-cta" href="/akis.php?tab=arayanlar">Aramayı temizle</a>
          <?php else: ?>
            <h2>Henüz aday yok</h2>
            <p>Platforma aday kaydoldukça burada görünecek.</p>
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
