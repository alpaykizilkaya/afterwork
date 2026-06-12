<?php

declare(strict_types=1);

/*
 * Seeker portföy medyası — güvenli dosya saklama.
 * - Gerçek MIME (finfo) ile tür doğrulama (uzantıya güvenmez)
 * - Tür başına boyut sınırı
 * - Rastgele dosya adı + 2 karakterlik shard klasör
 * - Yol DB'ye, dosya frontend/assets/uploads/ altına
 */

/** mime => [kind, maxBytes, ext] */
function aw_media_spec(): array
{
    $img = 8 * 1024 * 1024;     // 8 MB
    $vid = 80 * 1024 * 1024;    // 80 MB
    $doc = 20 * 1024 * 1024;    // 20 MB
    return [
        'image/jpeg' => ['image', $img, 'jpg'],
        'image/png'  => ['image', $img, 'png'],
        'image/webp' => ['image', $img, 'webp'],
        'image/gif'  => ['image', $img, 'gif'],
        'video/mp4'  => ['video', $vid, 'mp4'],
        'video/webm' => ['video', $vid, 'webm'],
        'video/quicktime' => ['video', $vid, 'mov'],
        'application/pdf' => ['doc', $doc, 'pdf'],
        'application/msword' => ['doc', $doc, 'doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['doc', $doc, 'docx'],
    ];
}

function aw_uploads_root(): string
{
    return dirname(__DIR__, 2) . '/frontend/assets/uploads';
}

/** Tek bir $_FILES girdisini doğrulayıp saklar ve seeker_media'ya yazar. */
function aw_store_upload(PDO $pdo, array $file, int $accountId, ?string $forceKind = null): array
{
    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Dosya yüklenemedi (boş ya da sunucu sınırı).');
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Geçersiz yükleme.');
    }
    $size = (int) ($file['size'] ?? 0);

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string) $finfo->file($tmp);
    $spec  = aw_media_spec();
    if (!isset($spec[$mime])) {
        throw new RuntimeException('Desteklenmeyen tür. (Görsel, video, PDF/Word.)');
    }
    [$kind, $max, $ext] = $spec[$mime];
    if ($size > $max) {
        throw new RuntimeException('Dosya çok büyük.');
    }
    if ($forceKind === 'avatar' && $kind === 'image') {
        $kind = 'avatar';
    }

    $rand  = bin2hex(random_bytes(16));
    $shard = substr($rand, 0, 2);
    $dir   = aw_uploads_root() . '/' . $shard;
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Klasör oluşturulamadı.');
    }
    $dest = $dir . '/' . $rand . '.' . $ext;
    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Dosya kaydedilemedi.');
    }
    @chmod($dest, 0644);

    $webPath = '/frontend/assets/uploads/' . $shard . '/' . $rand . '.' . $ext;
    $orig    = mb_substr((string) ($file['name'] ?? 'dosya'), 0, 200, 'UTF-8');

    if ($kind === 'avatar') {
        aw_delete_media_by_kind($pdo, $accountId, 'avatar'); // tek avatar
    }

    $so = 0;
    try {
        $st = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM seeker_media WHERE account_id = :a');
        $st->execute(['a' => $accountId]);
        $so = (int) $st->fetchColumn();
    } catch (Throwable) {
    }

    $pdo->prepare(
        'INSERT INTO seeker_media (account_id, kind, file_path, original_name, mime, size_bytes, sort_order)
         VALUES (:a, :k, :p, :o, :m, :s, :so)'
    )->execute(['a' => $accountId, 'k' => $kind, 'p' => $webPath, 'o' => $orig, 'm' => $mime, 's' => $size, 'so' => $so]);

    return [
        'id' => (int) $pdo->lastInsertId(),
        'kind' => $kind,
        'file_path' => $webPath,
        'original_name' => $orig,
        'mime' => $mime,
    ];
}

function aw_delete_media_by_kind(PDO $pdo, int $accountId, string $kind): void
{
    $st = $pdo->prepare('SELECT file_path FROM seeker_media WHERE account_id = :a AND kind = :k');
    $st->execute(['a' => $accountId, 'k' => $kind]);
    foreach ($st->fetchAll() as $r) {
        aw_unlink_web((string) $r['file_path']);
    }
    $pdo->prepare('DELETE FROM seeker_media WHERE account_id = :a AND kind = :k')->execute(['a' => $accountId, 'k' => $kind]);
}

function aw_unlink_web(string $webPath): void
{
    $rel = preg_replace('#^/frontend/assets/uploads/#', '', $webPath);
    if ($rel === null || $rel === '' || str_contains($rel, '..')) {
        return;
    }
    $abs = aw_uploads_root() . '/' . $rel;
    if (is_file($abs)) {
        @unlink($abs);
    }
}
