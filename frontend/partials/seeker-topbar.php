<?php

declare(strict_types=1);

/**
 * Shared topbar for the seeker area. Mirrors the employer topbar's logic but in
 * the seeker (dark navy / cream) theme, with minimal gold.
 * Expects in the including scope:
 *   - string $activeTab  One of 'profile' | 'messages'
 *   - string $fullName
 */

$activeTab ??= 'profile';
$fullName ??= 'Aday';

$words = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
$first  = mb_substr((string) ($words[0] ?? '?'), 0, 1, 'UTF-8');
$second = isset($words[1]) ? mb_substr((string) $words[1], 0, 1, 'UTF-8') : '';
$initials = mb_strtoupper($first . $second, 'UTF-8') ?: '?';

$tabs = [
    'profile'  => ['label' => 'Profilim',  'href' => '/seeker-panel.php'],
    'messages' => ['label' => 'Mesajlar',  'href' => '/mesajlar.php'],
];

// Unread message count for the Mesajlar badge. Defensive: never let a DB hiccup
// (or un-migrated tables) break the topbar.
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
?>
<header class="sk-topbar" role="banner">
  <a class="sk-brand" href="/seeker-panel.php" aria-label="Profilime dön">
    <img src="/frontend/assets/images/afterwork-logo.png" alt="Afterwork">
  </a>

  <nav class="sk-tabs" aria-label="Panel navigasyonu">
    <?php foreach ($tabs as $key => $tab): ?>
      <a
        class="sk-tab<?= $activeTab === $key ? ' is-active' : '' ?>"
        href="<?= htmlspecialchars($tab['href'], ENT_QUOTES, 'UTF-8') ?>"
        <?= $activeTab === $key ? 'aria-current="page"' : '' ?>
      ><?= htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8') ?><?php if ($key === 'messages' && $unreadMessages > 0): ?><span class="sk-tab-badge"><?= $unreadMessages ?></span><?php endif; ?></a>
    <?php endforeach; ?>
  </nav>

  <div class="sk-top-actions">
    <span class="sk-avatar" aria-hidden="true"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></span>
    <button type="button" class="sk-exit" data-logout-trigger>Çıkış Yap</button>
  </div>
</header>
