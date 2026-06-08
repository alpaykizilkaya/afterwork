<?php

declare(strict_types=1);

/**
 * Akış type switch — İlanlar / İş Verenler / İş Arayanlar.
 * Expects in the including scope:
 *   - string $activeFeedTab  One of 'ilanlar' | 'verenler' | 'arayanlar'
 */

$activeFeedTab ??= 'ilanlar';

$feedTabs = [
    'ilanlar'   => ['label' => 'İlanlar',      'href' => '/akis.php'],
    'verenler'  => ['label' => 'İş Verenler',  'href' => '/akis.php?tab=verenler'],
    'arayanlar' => ['label' => 'İş Arayanlar', 'href' => '/akis.php?tab=arayanlar'],
];
?>
<div class="feed-switch" role="tablist" aria-label="Akış türü">
  <?php foreach ($feedTabs as $key => $t):
      $on = $activeFeedTab === $key; ?>
    <a class="feed-switch-btn<?= $on ? ' is-on' : '' ?>" role="tab"
       aria-selected="<?= $on ? 'true' : 'false' ?>"
       href="<?= htmlspecialchars($t['href'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($t['label'], ENT_QUOTES, 'UTF-8') ?></a>
  <?php endforeach; ?>
</div>
