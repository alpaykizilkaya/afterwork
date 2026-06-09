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
require_once __DIR__ . '/../../../backend/notifications/notify.php';

$employer    = is_array($_SESSION['employer'] ?? null) ? $_SESSION['employer'] : [];
$companyName = trim((string) ($employer['company_name'] ?? '')) ?: 'Şirketiniz';
$me          = (int) ($_SESSION['account']['account_id'] ?? -1);

$pdo = db();

/* ---- helpers ---------------------------------------------------------- */

/** Two-letter initials from a display name. */
function aw_initials(string $name): string
{
    $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
    $a = mb_substr((string) ($words[0] ?? '?'), 0, 1, 'UTF-8');
    $b = isset($words[1]) ? mb_substr((string) $words[1], 0, 1, 'UTF-8') : '';
    $out = mb_strtoupper($a . $b, 'UTF-8');
    return $out === '' ? '?' : $out;
}

/** Compact Turkish relative time. */
function aw_when(?string $ts): string
{
    if (!$ts) {
        return '';
    }
    $t = strtotime($ts);
    $d = time() - $t;
    if ($d < 60)        return 'az önce';
    if ($d < 3600)      return floor($d / 60) . ' dk';
    if ($d < 86400)     return floor($d / 3600) . ' sa';
    if ($d < 7 * 86400) return floor($d / 86400) . ' g';
    return date('d.m.Y', $t);
}

/** Inbox rows for one side of the switch. */
function aw_inbox(PDO $pdo, int $me, string $side): array
{
    if ($side === 'verenler') {
        // I'm the seeker side; counterpart is the employer (company).
        $sql = "SELECT c.id, c.listing_id, c.last_message_at,
                       c.employer_account_id AS other_id,
                       COALESCE(NULLIF(e.company_name,''), SUBSTRING_INDEX(a.email,'@',1), 'Şirket') AS title,
                       l.title AS listing_title,
                       (SELECT body FROM messages m WHERE m.conversation_id=c.id ORDER BY m.created_at DESC LIMIT 1) AS last_body,
                       (SELECT sender_account_id FROM messages m WHERE m.conversation_id=c.id ORDER BY m.created_at DESC LIMIT 1) AS last_sender,
                       (SELECT COUNT(*) FROM messages m WHERE m.conversation_id=c.id AND m.sender_account_id<>:me_s AND m.read_at IS NULL) AS unread
                FROM conversations c
                LEFT JOIN employers e ON e.account_id=c.employer_account_id
                LEFT JOIN accounts  a ON a.id=c.employer_account_id
                LEFT JOIN job_listings l ON l.id=c.listing_id
                WHERE c.seeker_account_id=:me_w
                ORDER BY c.last_message_at IS NULL, c.last_message_at DESC";
    } else {
        // I'm the employer side; counterpart is the applicant (seeker).
        $sql = "SELECT c.id, c.listing_id, c.last_message_at,
                       c.seeker_account_id AS other_id,
                       COALESCE(NULLIF(s.full_name,''), NULLIF(e.company_name,''), SUBSTRING_INDEX(a.email,'@',1), 'Aday') AS title,
                       l.title AS listing_title,
                       (SELECT body FROM messages m WHERE m.conversation_id=c.id ORDER BY m.created_at DESC LIMIT 1) AS last_body,
                       (SELECT sender_account_id FROM messages m WHERE m.conversation_id=c.id ORDER BY m.created_at DESC LIMIT 1) AS last_sender,
                       (SELECT COUNT(*) FROM messages m WHERE m.conversation_id=c.id AND m.sender_account_id<>:me_s AND m.read_at IS NULL) AS unread
                FROM conversations c
                LEFT JOIN seekers   s ON s.account_id=c.seeker_account_id
                LEFT JOIN employers e ON e.account_id=c.seeker_account_id
                LEFT JOIN accounts  a ON a.id=c.seeker_account_id
                LEFT JOIN job_listings l ON l.id=c.listing_id
                WHERE c.employer_account_id=:me_w
                ORDER BY c.last_message_at IS NULL, c.last_message_at DESC";
    }
    $st = $pdo->prepare($sql);
    $st->execute(['me_s' => $me, 'me_w' => $me]);
    return $st->fetchAll();
}

/* ---- send (POST → redirect) ------------------------------------------ */

$side = (($_GET['side'] ?? $_POST['side'] ?? '') === 'verenler') ? 'verenler' : 'basvuranlar';
if (!isset($_GET['side']) && !isset($_POST['side'])) {
    $side = ((string) ($_SESSION['account']['role'] ?? '') === 'seeker') ? 'verenler' : 'basvuranlar';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cid  = (int) ($_POST['conversation_id'] ?? 0);
    $body = trim((string) ($_POST['body'] ?? ''));
    if ($cid > 0 && $body !== '') {
        $chk = $pdo->prepare('SELECT employer_account_id, seeker_account_id FROM conversations WHERE id=:c AND (employer_account_id=:me1 OR seeker_account_id=:me2)');
        $chk->execute(['c' => $cid, 'me1' => $me, 'me2' => $me]);
        $conv = $chk->fetch();
        if ($conv) {
            $body = mb_substr($body, 0, 4000, 'UTF-8');
            $pdo->prepare('INSERT INTO messages (conversation_id, sender_account_id, body, created_at) VALUES (:c,:me,:b,NOW())')
                ->execute(['c' => $cid, 'me' => $me, 'b' => $body]);
            $pdo->prepare('UPDATE conversations SET last_message_at=NOW(), last_sender_account_id=:me WHERE id=:c')
                ->execute(['c' => $cid, 'me' => $me]);

            // Notify the recipient (the other side of the thread) so the new
            // message shows up in their topbar bell.
            $recipient = (int) $conv['employer_account_id'] === $me
                ? (int) $conv['seeker_account_id']
                : (int) $conv['employer_account_id'];
            $excerpt = mb_substr($body, 0, 90, 'UTF-8');
            notify_account($pdo, $recipient, 'Yeni mesaj · ' . $companyName, $excerpt, '/mesajlar.php?c=' . $cid);
        }
    }
    header('Location: /mesajlar.php?side=' . $side . '&c=' . $cid . '#thread-bottom');
    exit;
}

/* ---- read view -------------------------------------------------------- */

$activeId = (int) ($_GET['c'] ?? 0);

// Opening a thread clears its unread badge.
if ($activeId > 0) {
    $own = $pdo->prepare('SELECT id FROM conversations WHERE id=:c AND (employer_account_id=:me1 OR seeker_account_id=:me2)');
    $own->execute(['c' => $activeId, 'me1' => $me, 'me2' => $me]);
    if ($own->fetchColumn()) {
        $pdo->prepare('UPDATE messages SET read_at=NOW() WHERE conversation_id=:c AND sender_account_id<>:me AND read_at IS NULL')
            ->execute(['c' => $activeId, 'me' => $me]);
    } else {
        $activeId = 0;
    }
}

$inboxBasvuranlar = aw_inbox($pdo, $me, 'basvuranlar');
$inboxVerenler    = aw_inbox($pdo, $me, 'verenler');
$unreadBasvuranlar = array_sum(array_map(static fn ($r) => (int) $r['unread'], $inboxBasvuranlar));
$unreadVerenler    = array_sum(array_map(static fn ($r) => (int) $r['unread'], $inboxVerenler));
$unreadMessages    = $unreadBasvuranlar + $unreadVerenler; // consumed by the topbar badge

$inbox = $side === 'verenler' ? $inboxVerenler : $inboxBasvuranlar;

// Resolve the active conversation against the visible side; fall back to first.
$active = null;
foreach ($inbox as $row) {
    if ((int) $row['id'] === $activeId) {
        $active = $row;
        break;
    }
}
if ($active === null && $activeId > 0) {
    // The thread lives on the other side — flip the switch to it.
    foreach (($side === 'verenler' ? $inboxBasvuranlar : $inboxVerenler) as $row) {
        if ((int) $row['id'] === $activeId) {
            $side   = $side === 'verenler' ? 'basvuranlar' : 'verenler';
            $inbox  = $side === 'verenler' ? $inboxVerenler : $inboxBasvuranlar;
            $active = $row;
            break;
        }
    }
}

$thread = [];
if ($active !== null) {
    $st = $pdo->prepare('SELECT sender_account_id, body, created_at FROM messages WHERE conversation_id=:c ORDER BY created_at ASC, id ASC');
    $st->execute(['c' => (int) $active['id']]);
    $thread = $st->fetchAll();
}

$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$sideHref = static fn (string $s): string => '/mesajlar.php?side=' . $s;

// The empty "pick a conversation" pane — rendered server-side when nothing is
// open, and reused client-side (via a <template>) when the side switch resets.
$blankPaneHtml = <<<'HTML'
<div class="msg-blank">
  <div class="msg-blank-mark" aria-hidden="true">
    <svg width="30" height="30" viewBox="0 0 24 24" fill="none">
      <path d="M4 5h16v11H8l-4 3V5Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
    </svg>
  </div>
  <h2>Mesajların</h2>
  <p>Soldan bir sohbet seç. İlgilenen adaylarla ve iş verenlerle tüm yazışmaların burada, tek yerde.</p>
</div>
HTML;

// Renders the rows of one inbox side. Both sides are emitted up front so the
// switch can toggle them client-side without a page reload.
$renderThreads = static function (array $list, string $listSide) use ($h, $sideHref, $active, $me): void {
    if (!$list) {
        echo '<div class="msg-empty-list"><p>'
            . ($listSide === 'verenler' ? 'Henüz bir iş verenle yazışman yok.' : 'Henüz başvuran biri sana yazmadı.')
            . '</p></div>';
        return;
    }
    foreach ($list as $row):
        $isActive = $active !== null && (int) $row['id'] === (int) $active['id'];
        $unread   = (int) $row['unread'];
        $preview  = (int) $row['last_sender'] === $me ? 'Sen: ' . (string) $row['last_body'] : (string) $row['last_body'];
        ?>
        <a class="msg-thread<?= $isActive ? ' is-active' : '' ?><?= $unread > 0 ? ' is-unread' : '' ?>"
           href="<?= $h($sideHref($listSide) . '&c=' . (int) $row['id']) ?>">
          <span class="msg-ava" aria-hidden="true"><?= $h(aw_initials((string) $row['title'])) ?></span>
          <span class="msg-thread-body">
            <span class="msg-thread-top">
              <span class="msg-thread-name"><?= $h($row['title']) ?></span>
              <span class="msg-thread-time"><?= $h(aw_when($row['last_message_at'])) ?></span>
            </span>
            <span class="msg-thread-preview"><?= $h(mb_substr((string) $preview, 0, 64, 'UTF-8')) ?></span>
          </span>
          <?php if ($unread > 0): ?><span class="msg-dot" aria-label="<?= $unread ?> okunmamış"><?= $unread ?></span><?php endif; ?>
        </a>
        <?php
    endforeach;
};
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AFTERWORK | Mesajlar</title>
  <link rel="stylesheet" href="/frontend/assets/css/employer/panel.css?v=<?= filemtime(__DIR__ . '/../../assets/css/employer/panel.css') ?>">
  <link rel="stylesheet" href="/frontend/assets/css/employer/messages.css?v=<?= filemtime(__DIR__ . '/../../assets/css/employer/messages.css') ?>">
  <link rel="stylesheet" href="/frontend/assets/css/shared/logout-modal.css?v=<?= filemtime(__DIR__ . '/../../assets/css/shared/logout-modal.css') ?>">
</head>
<body>
  <div class="ep-page">
    <?php
    $activeTab = 'messages';
    include __DIR__ . '/../../partials/employer-topbar.php';
    ?>

    <main class="msg" aria-label="Mesajlar">
      <!-- LEFT: inbox -->
      <aside class="msg-list<?= $active !== null ? ' has-active' : '' ?>" aria-label="İleti kutusu">
        <div class="msg-switch" role="tablist" aria-label="Mesaj türü">
          <a class="msg-switch-btn<?= $side !== 'verenler' ? ' is-on' : '' ?>" role="tab"
             aria-selected="<?= $side !== 'verenler' ? 'true' : 'false' ?>" href="<?= $h($sideHref('basvuranlar')) ?>">
            İş Başvuranlar
            <?php if ($unreadBasvuranlar > 0): ?><span class="msg-switch-count"><?= (int) $unreadBasvuranlar ?></span><?php endif; ?>
          </a>
          <a class="msg-switch-btn<?= $side === 'verenler' ? ' is-on' : '' ?>" role="tab"
             aria-selected="<?= $side === 'verenler' ? 'true' : 'false' ?>" href="<?= $h($sideHref('verenler')) ?>">
            İş Verenler
            <?php if ($unreadVerenler > 0): ?><span class="msg-switch-count"><?= (int) $unreadVerenler ?></span><?php endif; ?>
          </a>
        </div>

        <div class="msg-threads" data-side="basvuranlar"<?= $side !== 'basvuranlar' ? ' hidden' : '' ?>>
          <?php $renderThreads($inboxBasvuranlar, 'basvuranlar'); ?>
        </div>
        <div class="msg-threads" data-side="verenler"<?= $side !== 'verenler' ? ' hidden' : '' ?>>
          <?php $renderThreads($inboxVerenler, 'verenler'); ?>
        </div>
      </aside>

      <!-- RIGHT: thread -->
      <section class="msg-pane" aria-label="Sohbet">
        <?php if ($active === null): ?>
          <?= $blankPaneHtml ?>
        <?php else: ?>
          <header class="msg-head">
            <span class="msg-ava msg-ava--lg" aria-hidden="true"><?= $h(aw_initials((string) $active['title'])) ?></span>
            <div class="msg-head-meta">
              <h2><?= $h($active['title']) ?></h2>
              <?php if (!empty($active['listing_title'])): ?>
                <span class="msg-chip"><?= $h($active['listing_title']) ?></span>
              <?php else: ?>
                <span class="msg-head-sub"><?= $side === 'verenler' ? 'İş veren' : 'Aday' ?></span>
              <?php endif; ?>
            </div>
          </header>

          <div class="msg-scroll" id="msg-scroll">
            <?php
            $lastDay = '';
            foreach ($thread as $m):
                $day = date('Y-m-d', strtotime((string) $m['created_at']));
                if ($day !== $lastDay):
                    $lastDay = $day;
                    $label = date('d.m.Y', strtotime($day)); ?>
                    <div class="msg-daysep"><span><?= $h($label) ?></span></div>
                <?php endif;
                $mine = (int) $m['sender_account_id'] === $me; ?>
                <div class="msg-row<?= $mine ? ' is-mine' : '' ?>">
                  <div class="msg-bubble">
                    <p><?= nl2br($h($m['body'])) ?></p>
                    <time><?= $h(date('H:i', strtotime((string) $m['created_at']))) ?></time>
                  </div>
                </div>
            <?php endforeach; ?>
            <span id="thread-bottom"></span>
          </div>

          <form class="msg-compose" method="post" action="/mesajlar.php">
            <input type="hidden" name="side" value="<?= $h($side) ?>">
            <input type="hidden" name="conversation_id" value="<?= (int) $active['id'] ?>">
            <textarea name="body" rows="1" placeholder="Mesaj yaz…" required autocomplete="off"></textarea>
            <button type="submit" aria-label="Gönder">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path d="M4 12l16-7-7 16-2.5-6.5L4 12Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
              </svg>
              <span>Gönder</span>
            </button>
          </form>
        <?php endif; ?>
      </section>
    </main>
  </div>

  <template id="msg-blank-tpl"><?= $blankPaneHtml ?></template>

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
  <script src="/frontend/assets/js/employer/messages.js?v=<?= filemtime(__DIR__ . '/../../assets/js/employer/messages.js') ?>" defer></script>
</body>
</html>
