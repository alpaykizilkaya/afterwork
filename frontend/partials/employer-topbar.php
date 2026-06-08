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

// Unread message count for the badge on the bell + Mesajlar tab. The messages
// page computes this itself ($unreadMessages); other panel pages let the topbar
// resolve it on demand. Defensive: never let a DB hiccup break the topbar.
if (!isset($unreadMessages)) {
    $unreadMessages = 0;
    if (function_exists('db')) {
        try {
            $acc = (int) ($_SESSION['account']['account_id'] ?? -1);
            if ($acc >= 0) {
                $st = db()->prepare(
                    'SELECT COUNT(*) FROM messages m
                       JOIN conversations c ON c.id = m.conversation_id
                      WHERE (c.employer_account_id = :a1 OR c.seeker_account_id = :a2)
                        AND m.sender_account_id <> :a3
                        AND m.read_at IS NULL'
                );
                $st->execute(['a1' => $acc, 'a2' => $acc, 'a3' => $acc]);
                $unreadMessages = (int) $st->fetchColumn();
            }
        } catch (Throwable) {
            $unreadMessages = 0;
        }
    }
}
$unreadMessages = (int) $unreadMessages;

// General notifications for the bell dropdown (+ unread count). Defensive:
// never let a DB hiccup (or un-migrated tables) break the topbar.
$notifications = [];
$unreadNotif   = 0;
if (function_exists('db')) {
    try {
        $notifAcc = (int) ($_SESSION['account']['account_id'] ?? -1);
        if ($notifAcc >= 0) {
            $notifRole = (string) ($_SESSION['account']['role'] ?? '');
            $notifAud  = $notifRole === 'seeker' ? ['all', 'seeker'] : ['all', 'employer'];
            $notifIn   = implode(',', array_fill(0, count($notifAud), '?'));
            $st = db()->prepare(
                "SELECT n.id, n.title, n.body, n.created_at,
                        (r.account_id IS NOT NULL) AS is_read
                   FROM notifications n
                   LEFT JOIN notification_reads r
                     ON r.notification_id = n.id AND r.account_id = ?
                  WHERE n.audience IN ({$notifIn})
                  ORDER BY n.created_at DESC, n.id DESC
                  LIMIT 20"
            );
            $st->execute(array_merge([$notifAcc], $notifAud));
            $notifications = $st->fetchAll();
            foreach ($notifications as $n) {
                if ((int) $n['is_read'] === 0) {
                    $unreadNotif++;
                }
            }
        }
    } catch (Throwable) {
        $notifications = [];
        $unreadNotif   = 0;
    }
}

// Compact Turkish relative time for the notification list.
$notifWhen = static function (string $ts): string {
    if ($ts === '' || ($t = strtotime($ts)) === false) {
        return '';
    }
    $d = time() - $t;
    if ($d < 60)        return 'az önce';
    if ($d < 3600)      return (int) floor($d / 60) . ' dk önce';
    if ($d < 86400)     return (int) floor($d / 3600) . ' sa önce';
    if ($d < 7 * 86400) return (int) floor($d / 86400) . ' g önce';
    return date('d.m.Y', $t);
};
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
      ><?= htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8') ?><?php if ($key === 'messages' && $unreadMessages > 0): ?><span class="ep-tab-badge"><?= $unreadMessages ?></span><?php endif; ?></a>
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

    <div class="ep-notif ep-avatar-menu" data-ep-menu>
      <button type="button" class="ep-icon-btn ep-bell" data-ep-menu-trigger aria-haspopup="true" aria-expanded="false" aria-label="Bildirimler" title="Bildirimler">
        <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true">
          <path d="M10 3a5 5 0 0 0-5 5v3l-1.2 1.8a.75.75 0 0 0 .62 1.17h11.16a.75.75 0 0 0 .62-1.17L15 11V8a5 5 0 0 0-5-5Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
          <path d="M8.5 16a1.75 1.75 0 0 0 3 0" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
        </svg>
        <?php if ($unreadNotif > 0): ?><span class="ep-bell-badge" data-notif-badge aria-hidden="true"><?= $unreadNotif > 9 ? '9+' : $unreadNotif ?></span><?php endif; ?>
      </button>

      <div class="ep-menu ep-notif-menu" role="menu" hidden>
        <div class="ep-notif-head">
          <span class="ep-notif-lead">Bildirimler<?php if ($unreadNotif > 0): ?> <span class="ep-notif-count" data-notif-count><?= (int) $unreadNotif ?></span><?php endif; ?></span>
          <button type="button" class="ep-notif-readall" data-notif-readall<?= $unreadNotif > 0 ? '' : ' hidden' ?>>Tümünü okundu işaretle</button>
        </div>
        <div class="ep-notif-list">
          <?php if (!$notifications): ?>
            <div class="ep-notif-empty">Henüz bildirim yok.</div>
          <?php else: foreach ($notifications as $n):
            $nUnread = (int) ($n['is_read'] ?? 0) === 0; ?>
            <button type="button" class="ep-notif-item<?= $nUnread ? ' is-unread' : '' ?>" data-notif-id="<?= (int) $n['id'] ?>">
              <span class="ep-notif-dot" aria-hidden="true"></span>
              <span class="ep-notif-main">
                <span class="ep-notif-item-title"><?= htmlspecialchars((string) $n['title'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php if (trim((string) ($n['body'] ?? '')) !== ''): ?>
                  <span class="ep-notif-item-text"><?= htmlspecialchars((string) $n['body'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <span class="ep-notif-item-time"><?= htmlspecialchars($notifWhen((string) $n['created_at']), ENT_QUOTES, 'UTF-8') ?></span>
              </span>
            </button>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

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
