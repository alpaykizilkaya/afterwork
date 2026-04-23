<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../mail/mailer.php';

function verification_token_lifetime_minutes(): int
{
    return 24 * 60;
}

function verification_resend_cooldown_seconds(): int
{
    return 60;
}

function send_verification_email(string $email): bool
{
    $pdo = db();

    $pdo->prepare('DELETE FROM email_verifications WHERE email = :email')
        ->execute(['email' => $email]);

    $token = bin2hex(random_bytes(32));
    $lifetime = verification_token_lifetime_minutes();

    $pdo->prepare(
        'INSERT INTO email_verifications (email, token, expires_at)
         VALUES (:email, :token, DATE_ADD(NOW(), INTERVAL ' . (int) $lifetime . ' MINUTE))'
    )->execute([
        'email' => $email,
        'token' => $token,
    ]);

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'afterwork.com.tr';
    $verifyUrl = $protocol . '://' . $host . '/verify-email.php?token=' . urlencode($token);

    $subject = 'Afterwork — E-posta Doğrulama';
    $textBody  = "Merhaba,\n\n";
    $textBody .= "Afterwork'e hoş geldin. Hesabını aktif edebilmemiz için aşağıdaki bağlantıya tıklayarak e-posta adresini doğrula:\n";
    $textBody .= $verifyUrl . "\n\n";
    $textBody .= "Bu bağlantı 24 saat geçerlidir.\n\n";
    $textBody .= "Eğer bu kaydı sen yapmadıysan, bu e-postayı görmezden gelebilirsin.\n\n";
    $textBody .= "Afterwork Ekibi";

    $safeUrl = htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8');
    $htmlBody  = '<div style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;max-width:560px;margin:0 auto;padding:32px 24px;color:#06141b;">';
    $htmlBody .= '<h2 style="font-size:22px;margin:0 0 16px;">E-posta adresini doğrula</h2>';
    $htmlBody .= '<p style="line-height:1.55;margin:0 0 16px;">Afterwork\'e hoş geldin. Hesabının tüm özelliklerini kullanabilmen için e-posta adresinin sana ait olduğunu doğrulamanı istiyoruz.</p>';
    $htmlBody .= '<p style="margin:24px 0;"><a href="' . $safeUrl . '" style="display:inline-block;background:#11212d;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:12px;font-weight:600;">E-postamı doğrula</a></p>';
    $htmlBody .= '<p style="line-height:1.55;margin:0 0 16px;font-size:14px;color:#4b5b66;">Bu bağlantı 24 saat geçerlidir. Eğer bu kaydı sen yapmadıysan, bu e-postayı görmezden gelebilirsin.</p>';
    $htmlBody .= '<p style="line-height:1.55;margin:24px 0 0;font-size:14px;color:#4b5b66;">Afterwork Ekibi</p>';
    $htmlBody .= '</div>';

    return send_mail($email, $subject, $textBody, $htmlBody);
}

function seconds_since_last_verification_request(string $email): ?int
{
    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT TIMESTAMPDIFF(SECOND, created_at, NOW())
         FROM email_verifications
         WHERE email = :email
         ORDER BY id DESC
         LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $value = $stmt->fetchColumn();

    if ($value === false || $value === null) {
        return null;
    }

    return (int) $value;
}

function mark_account_verified(PDO $pdo, int $accountId): void
{
    $pdo->prepare(
        'UPDATE accounts SET is_verified = 1, verified_at = NOW() WHERE id = :id'
    )->execute(['id' => $accountId]);
}
