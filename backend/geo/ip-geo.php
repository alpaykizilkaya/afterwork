<?php

declare(strict_types=1);

/**
 * Coarse IP → location lookup for analytics (Mercek world map).
 *
 * geo_from_ip() returns ['country_code' => 'TR', 'country' => 'Turkey',
 * 'city' => 'Istanbul'] (any field may be '' if unknown), or [] when the IP is
 * local/private or the lookup fails.
 *
 * We use ip-api.com (free, no key, ~45 req/min). HTTP-only on the free tier,
 * which is fine for a server-to-server call. Best-effort with a short timeout —
 * a failed lookup must never slow down or break the page view it rides on.
 *
 * Privacy (KVKK): callers store ONLY the derived country/city, never the raw IP.
 */
if (!function_exists('geo_from_ip')) {
    function geo_from_ip(string $ip): array
    {
        $ip = trim($ip);
        if ($ip === '') {
            return [];
        }

        // Skip private / reserved / loopback ranges — they never geolocate.
        if (!filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        )) {
            return [];
        }

        $url = 'http://ip-api.com/json/' . rawurlencode($ip)
             . '?fields=status,country,countryCode,city';

        $raw = null;
        try {
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 2,
                    CURLOPT_CONNECTTIMEOUT => 2,
                ]);
                $raw = curl_exec($ch);
                curl_close($ch);
            } else {
                $ctx = stream_context_create(['http' => ['timeout' => 2]]);
                $raw = @file_get_contents($url, false, $ctx);
            }
        } catch (Throwable) {
            return [];
        }

        if (!is_string($raw) || $raw === '') {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
            return [];
        }

        return [
            'country_code' => strtoupper(substr((string) ($data['countryCode'] ?? ''), 0, 2)),
            'country'      => mb_substr((string) ($data['country'] ?? ''), 0, 64, 'UTF-8'),
            'city'         => mb_substr((string) ($data['city'] ?? ''), 0, 96, 'UTF-8'),
        ];
    }
}
