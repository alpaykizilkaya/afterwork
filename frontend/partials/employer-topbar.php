<?php

declare(strict_types=1);

/**
 * Shared topbar for the employer area.
 * Expects in the including scope:
 *   - string $activeTab  One of 'account' | 'feed' | 'messages'
 *   - string $companyName
 *   - (optional) string $searchQuery
 */

$activeTab ??= 'account';
$companyName ??= 'Şirket';
$searchQuery ??= (string) ($_GET['q'] ?? '');

$words = preg_split('/\s+/u', trim($companyName), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
$first = mb_substr((string) ($words[0] ?? '?'), 0, 1, 'UTF-8');
$second = isset($words[1]) ? mb_substr((string) $words[1], 0, 1, 'UTF-8') : '';
$companyInitials = mb_strtoupper($first . $second, 'UTF-8');
if ($companyInitials === '') {
    $companyInitials = '?';
}

$tabs = [
    'account'  => ['label' => 'Dükkan',   'href' => '/isveren-panel.php'],
    'feed'     => ['label' => 'Akış',     'href' => '/akis.php'],
    'messages' => ['label' => 'Mesajlar', 'href' => '/mesajlar.php'],
];
?>
<header class="ep-topbar" role="banner">
  <a class="ep-brand" href="/isveren-panel.php" aria-label="Dükkana dön">
    <img src="/frontend/assets/images/afterwork-logo.png" alt="Afterwork">
  </a>

  <nav class="ep-tabs" aria-label="Panel navigasyonu">
    <?php foreach ($tabs as $key => $tab): ?>
      <a
        class="ep-tab<?= $activeTab === $key ? ' is-active' : '' ?>"
        href="<?= htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8') ?>"
        <?= $activeTab === $key ? 'aria-current="page"' : '' ?>
      ><?= htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8') ?></a>
    <?php endforeach; ?>
  </nav>

  <form class="ep-search" action="/isveren-panel.php" method="get" role="search">
    <svg class="ep-search-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
      <circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.6"/>
      <path d="M11 11l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
    </svg>
    <input
      type="search"
      name="q"
      placeholder="Aday, ilan, şirket ara…"
      autocomplete="off"
      value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>"
      aria-label="Ara"
    >
  </form>

  <div class="ep-top-actions">
    <a class="ep-cta" href="/isveren-panel.php?yeni=1">
      <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true">
        <path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
      </svg>
      <span>Yeni İlan</span>
    </a>

    <button type="button" class="ep-icon-btn" aria-label="Bildirimler" title="Bildirimler (yakında)">
      <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true">
        <path d="M10 3a5 5 0 0 0-5 5v3l-1.2 1.8a.75.75 0 0 0 .62 1.17h11.16a.75.75 0 0 0 .62-1.17L15 11V8a5 5 0 0 0-5-5Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
        <path d="M8.5 16a1.75 1.75 0 0 0 3 0" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
      </svg>
    </button>

    <div class="ep-avatar-menu" data-ep-menu>
      <button type="button" class="ep-avatar" aria-haspopup="true" aria-expanded="false" data-ep-menu-trigger>
        <span class="ep-avatar-initials" aria-hidden="true"><?= htmlspecialchars($companyInitials, ENT_QUOTES, 'UTF-8') ?></span>
        <svg class="ep-avatar-chevron" width="10" height="10" viewBox="0 0 10 10" fill="none" aria-hidden="true">
          <path d="M2 4l3 3 3-3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>

      <div class="ep-menu" role="menu" hidden>
        <div class="ep-menu-head">
          <p class="ep-menu-name"><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></p>
          <p class="ep-menu-email"><?= htmlspecialchars((string) ($_SESSION['account']['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <a class="ep-menu-item" role="menuitem" href="/isveren-panel.php?profil=1">Şirket Profili</a>
        <a class="ep-menu-item" role="menuitem" href="#" aria-disabled="true" title="Yakında">Ayarlar</a>
        <button type="button" class="ep-menu-item ep-menu-item--danger" role="menuitem" data-logout-trigger>Çıkış Yap</button>
      </div>
    </div>
  </div>
</header>
