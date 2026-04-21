<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

function google_redirect_uri(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'afterwork.com.tr';

    return $scheme . '://' . $host . '/auth/google/callback.php';
}

function google_auth_url(string $state): string
{
    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => google_redirect_uri(),
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'access_type' => 'online',
        'prompt' => 'select_account',
        'state' => $state,
    ];

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

function google_exchange_code(string $code): array
{
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'code' => $code,
            'client_id' => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri' => google_redirect_uri(),
            'grant_type' => 'authorization_code',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Google token request failed: ' . $err);
    }

    $data = json_decode($response, true);
    if ($status !== 200 || !is_array($data) || !isset($data['access_token'])) {
        $msg = is_array($data) && isset($data['error_description']) ? $data['error_description'] : 'Unknown error';
        throw new RuntimeException('Google token exchange failed: ' . $msg);
    }

    return $data;
}

function google_fetch_user(string $accessToken): array
{
    $ch = curl_init('https://openidconnect.googleapis.com/v1/userinfo');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Google userinfo request failed: ' . $err);
    }

    $data = json_decode($response, true);
    if ($status !== 200 || !is_array($data) || empty($data['sub']) || empty($data['email'])) {
        throw new RuntimeException('Google userinfo invalid response.');
    }

    if (isset($data['email_verified']) && $data['email_verified'] !== true && $data['email_verified'] !== 'true') {
        throw new RuntimeException('Google account email is not verified.');
    }

    return $data;
}
