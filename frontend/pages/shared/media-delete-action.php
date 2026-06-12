<?php

declare(strict_types=1);

/* Portföy medyası silme (seeker only). POST id. JSON döner. */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../backend/config/db.php';
require_once __DIR__ . '/../../../backend/media/media-store.php';

if (
    !isset($_SESSION['account'])
    || (string) ($_SESSION['account']['role'] ?? '') !== 'seeker'
    || $_SERVER['REQUEST_METHOD'] !== 'POST'
) {
    http_response_code(403);
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$me = (int) ($_SESSION['account']['account_id'] ?? 0);
$id = (int) ($_POST['id'] ?? 0);

try {
    $pdo = db();
    $st = $pdo->prepare('SELECT file_path FROM seeker_media WHERE id = :id AND account_id = :a LIMIT 1');
    $st->execute(['id' => $id, 'a' => $me]);
    $row = $st->fetch();
    if ($row) {
        aw_unlink_web((string) $row['file_path']);
        $pdo->prepare('DELETE FROM seeker_media WHERE id = :id AND account_id = :a')->execute(['id' => $id, 'a' => $me]);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (Throwable) {
    // best-effort
}
echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
