<?php

declare(strict_types=1);

/*
 * Portföy dosyası yükleme (seeker only). POST files[] (çoklu) veya file.
 * Opsiyonel: kind=avatar → profil fotoğrafı (tekil). JSON döner.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../backend/config/db.php';
require_once __DIR__ . '/../../../backend/media/media-store.php';

function aw_json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (
    !isset($_SESSION['account'])
    || (string) ($_SESSION['account']['role'] ?? '') !== 'seeker'
) {
    aw_json_out(['ok' => false, 'error' => 'Yüklemek için iş arayan hesabıyla giriş yapmalısın.'], 403);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    aw_json_out(['ok' => false, 'error' => 'Geçersiz istek.'], 405);
}

$me    = (int) ($_SESSION['account']['account_id'] ?? 0);
$force = ($_POST['kind'] ?? '') === 'avatar' ? 'avatar' : null;
if ($me <= 0) {
    aw_json_out(['ok' => false, 'error' => 'Oturum bulunamadı.'], 403);
}

// $_FILES'i tekdüze listeye çevir (files[] çoklu ya da tek file)
$entry = $_FILES['files'] ?? ($_FILES['file'] ?? null);
if ($entry === null) {
    aw_json_out(['ok' => false, 'error' => 'Dosya gelmedi.'], 400);
}
$files = [];
if (is_array($entry['name'])) {
    $n = count($entry['name']);
    for ($i = 0; $i < $n; $i++) {
        $files[] = [
            'name' => $entry['name'][$i], 'type' => $entry['type'][$i] ?? '',
            'tmp_name' => $entry['tmp_name'][$i] ?? '', 'error' => $entry['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $entry['size'][$i] ?? 0,
        ];
    }
} else {
    $files[] = $entry;
}

$pdo = db();
$items = [];
$errors = [];
foreach ($files as $f) {
    if ((int) ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        continue;
    }
    try {
        $items[] = aw_store_upload($pdo, $f, $me, $force);
    } catch (Throwable $e) {
        $errors[] = ($f['name'] ?? 'dosya') . ': ' . $e->getMessage();
    }
}

aw_json_out(['ok' => $items !== [], 'items' => $items, 'errors' => $errors]);
