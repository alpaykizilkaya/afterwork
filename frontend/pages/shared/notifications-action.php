<?php

declare(strict_types=1);

/*
 * Notifications mark-as-read endpoint (AJAX, JSON).
 * POST action=read&id=<n>  → mark one notification read for the current account.
 * POST action=read_all     → mark every notification (for this audience) read.
 * Returns { ok: bool, unread: int }.
 */

session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['account']) || !is_array($_SESSION['account'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth']);
    exit;
}

require_once __DIR__ . '/../../../backend/config/db.php';

$acc  = (int) ($_SESSION['account']['account_id'] ?? -1);
$role = (string) ($_SESSION['account']['role'] ?? '');
$aud  = $role === 'seeker' ? ['all', 'seeker'] : ['all', 'employer'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $acc < 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_request']);
    exit;
}

$action = (string) ($_POST['action'] ?? '');

try {
    $pdo = db();
    $in  = implode(',', array_fill(0, count($aud), '?'));

    // Visibility rule (mirrors the topbar): targeted at me, OR a broadcast for
    // a matching audience role.
    $visible = "(n.account_id = ? OR (n.account_id IS NULL AND n.audience IN ({$in})))";

    if ($action === 'read') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            // Only allow marking notifications this account is actually allowed to see.
            $chk = $pdo->prepare("SELECT 1 FROM notifications n WHERE n.id = ? AND {$visible} LIMIT 1");
            $chk->execute(array_merge([$id, $acc], $aud));
            if ($chk->fetchColumn()) {
                $ins = $pdo->prepare(
                    'INSERT INTO notification_reads (notification_id, account_id)
                     VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE read_at = read_at'
                );
                $ins->execute([$id, $acc]);
            }
        }
    } elseif ($action === 'read_all') {
        $ins = $pdo->prepare(
            "INSERT IGNORE INTO notification_reads (notification_id, account_id)
             SELECT n.id, ? FROM notifications n
              WHERE {$visible}"
        );
        $ins->execute(array_merge([$acc, $acc], $aud));
    } else {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'unknown_action']);
        exit;
    }

    // Recompute unread for the response.
    $cnt = $pdo->prepare(
        "SELECT COUNT(*) FROM notifications n
           LEFT JOIN notification_reads r ON r.notification_id = n.id AND r.account_id = ?
          WHERE {$visible} AND r.account_id IS NULL"
    );
    $cnt->execute(array_merge([$acc, $acc], $aud));
    $unread = (int) $cnt->fetchColumn();

    echo json_encode(['ok' => true, 'unread' => $unread]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server']);
}
