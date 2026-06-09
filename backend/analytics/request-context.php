<?php

declare(strict_types=1);

/**
 * Shared request-context detection for analytics: where a hit came from, on what
 * device, and the visitor's coarse location. Used by both view tracking and
 * application recording so the two stay perfectly consistent.
 */

require_once __DIR__ . '/../geo/ip-geo.php';

if (!function_exists('aw_client_ip')) {
    /** Best-guess public client IP (honours X-Forwarded-For first hop). */
    function aw_client_ip(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $fwd  = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
            $cand = trim($fwd[0]);
            if (filter_var($cand, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $ip = $cand;
            }
        }
        return $ip;
    }
}

if (!function_exists('aw_traffic_source')) {
    /** Classify the referrer into a coarse traffic source bucket (Turkish labels). */
    function aw_traffic_source(): string
    {
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($ref === '') {
            return 'Direkt';
        }
        $host    = (string) (parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST) ?: '');
        $refHost = (string) (parse_url($ref, PHP_URL_HOST) ?: '');
        if ($refHost === '' || strcasecmp($refHost, $host) === 0) {
            return str_contains($ref, '/akis') ? 'Akış' : 'Site içi';
        }
        if (preg_match('/google|bing|yahoo|yandex|duckduckgo|ecosia/i', $refHost)) {
            return 'Arama';
        }
        if (preg_match('/linkedin|twitter|x\.com|facebook|instagram|t\.co/i', $refHost)) {
            return 'Sosyal';
        }
        return 'Dış bağlantı';
    }
}

if (!function_exists('aw_device_type')) {
    /** Classify the User-Agent into Mobil / Tablet / Masaüstü. */
    function aw_device_type(): string
    {
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        if (preg_match('/iPad|Tablet|PlayBook|Silk/i', $ua)) {
            return 'Tablet';
        }
        if (preg_match('/Mobi|Android|iPhone|iPod|Windows Phone/i', $ua)) {
            return 'Mobil';
        }
        return 'Masaüstü';
    }
}

if (!function_exists('aw_request_context')) {
    /**
     * Full context for one hit: traffic source, device, and derived geo.
     * Returns ['traffic_source','device_type','country_code','country','city'].
     */
    function aw_request_context(): array
    {
        $geo = geo_from_ip(aw_client_ip());
        return [
            'traffic_source' => aw_traffic_source(),
            'device_type'    => aw_device_type(),
            'country_code'   => ($geo['country_code'] ?? '') !== '' ? $geo['country_code'] : null,
            'country'        => ($geo['country'] ?? '') !== '' ? $geo['country'] : null,
            'city'           => ($geo['city'] ?? '') !== '' ? $geo['city'] : null,
        ];
    }
}
