<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

function send_mail(string $to, string $subject, string $textBody, ?string $htmlBody = null): bool
{
    $apiKey = defined('RESEND_API_KEY') ? RESEND_API_KEY : '';

    if ($apiKey === '') {
        error_log('send_mail: RESEND_API_KEY is not configured');
        return false;
    }

    $fromAddress = defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : 'noreply@afterwork.com.tr';
    $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Afterwork';

    $payload = [
        'from' => $fromName . ' <' . $fromAddress . '>',
        'to' => [$to],
        'subject' => $subject,
        'text' => $textBody,
    ];

    if ($htmlBody !== null) {
        $payload['html'] = $htmlBody;
    }

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($status >= 200 && $status < 300) {
        return true;
    }

    error_log(sprintf(
        'send_mail failed: status=%d curl_err=%s response=%s',
        $status,
        $curlError,
        (string) $response
    ));

    return false;
}
