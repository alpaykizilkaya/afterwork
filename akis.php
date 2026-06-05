<?php

declare(strict_types=1);

// ?id=<n> → read-only spectator view of a single listing; otherwise the feed.
if (isset($_GET['id']) && (int) $_GET['id'] > 0) {
    require __DIR__ . '/frontend/pages/employer-panel/feed-detail-page.php';
} else {
    require __DIR__ . '/frontend/pages/employer-panel/feed-page.php';
}
