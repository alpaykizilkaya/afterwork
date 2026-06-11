<?php

declare(strict_types=1);

/*
 * School autocomplete (JSON). GET ?q=<text>&type=uni|lise|all
 * Type-ahead over Turkish universities + high schools. The source datasets are
 * fetched once from jsdelivr and cached locally as a slim {name,city,kind} list
 * (no DB import needed). Returns up to 10 best matches, prefix matches first.
 *
 * Sources:
 *   universities: erhanfirat/turkiye_univertise_listesi_json  (field "Adı")
 *   high schools: alpcanaydin/liseler                          (field "schoolName")
 */

header('Content-Type: application/json; charset=utf-8');

$q = trim((string) ($_GET['q'] ?? ''));
$type = (string) ($_GET['type'] ?? 'all');
if (mb_strlen($q, 'UTF-8') < 2) {
    echo json_encode([]);
    exit;
}

$cacheFile = sys_get_temp_dir() . '/aw_schools_v1.json';
$cacheTtl  = 30 * 24 * 3600; // 30 days — sources are static

/** Build the slim combined list from the upstream datasets. */
$build = static function (): array {
    $fetch = static function (string $url): ?array {
        try {
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 8,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_FOLLOWLOCATION => true,
                ]);
                $raw = curl_exec($ch);
                curl_close($ch);
            } else {
                $raw = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 8]]));
            }
            if (!is_string($raw) || $raw === '') return null;
            // Strip a leading UTF-8 BOM (the universities file has one) — json_decode fails on it.
            if (str_starts_with($raw, "\xEF\xBB\xBF")) { $raw = substr($raw, 3); }
            $d = json_decode($raw, true);
            return is_array($d) ? $d : null;
        } catch (Throwable) {
            return null;
        }
    };

    $out = [];

    // Turkish-aware Title Case (the universities dataset is ALL CAPS).
    $trTitle = static function (string $s): string {
        $low = mb_strtolower(strtr($s, ['I' => 'ı', 'İ' => 'i', 'Ş' => 'ş', 'Ğ' => 'ğ', 'Ü' => 'ü', 'Ö' => 'ö', 'Ç' => 'ç']), 'UTF-8');
        return (string) preg_replace_callback('/\p{L}[\p{L}\x27]*/u', static function ($m) {
            $first = mb_substr($m[0], 0, 1, 'UTF-8');
            $up = mb_strtoupper(strtr($first, ['i' => 'İ', 'ı' => 'I', 'ş' => 'Ş', 'ğ' => 'Ğ', 'ü' => 'Ü', 'ö' => 'Ö', 'ç' => 'Ç']), 'UTF-8');
            return $up . mb_substr($m[0], 1, null, 'UTF-8');
        }, $low);
    };

    // Universities grouped by province (includes vakıf / private universities).
    $unis = $fetch('https://cdn.jsdelivr.net/gh/anilozmen/TR-iller-universiteler-JSON@master/province-universities.json');
    foreach ($unis ?? [] as $prov) {
        $city = $trTitle(trim((string) ($prov['province'] ?? '')));
        foreach (($prov['universities'] ?? []) as $u) {
            $name = trim(str_replace('**', '', (string) ($u['name'] ?? '')));
            if ($name !== '') {
                $out[] = ['name' => $trTitle($name), 'city' => $city, 'kind' => 'uni'];
            }
        }
    }

    $lises = $fetch('https://cdn.jsdelivr.net/gh/alpcanaydin/liseler@master/liseler-web/public/data.json');
    foreach ($lises ?? [] as $l) {
        $name = trim((string) ($l['schoolName'] ?? ''));
        if ($name !== '') {
            $out[] = ['name' => $name, 'city' => (string) ($l['city'] ?? ''), 'kind' => 'lise'];
        }
    }

    return $out;
};

// Load cache or (re)build.
$list = null;
if (is_file($cacheFile) && (time() - (int) @filemtime($cacheFile)) < $cacheTtl) {
    $cached = json_decode((string) @file_get_contents($cacheFile), true);
    if (is_array($cached) && $cached !== []) {
        $list = $cached;
    }
}
if ($list === null) {
    $list = $build();
    if ($list !== []) {
        @file_put_contents($cacheFile, json_encode($list, JSON_UNESCAPED_UNICODE));
    }
}

if ($type === 'uni') {
    $list = array_values(array_filter($list, static fn ($s) => ($s['kind'] ?? '') === 'uni'));
} elseif ($type === 'lise') {
    $list = array_values(array_filter($list, static fn ($s) => ($s['kind'] ?? '') === 'lise'));
}

// Diacritic-folding so "boga" matches "Boğaziçi" (users often type plain ASCII).
$fold = static function (string $s): string {
    $s = strtr($s, ['İ' => 'i', 'I' => 'i', 'Ş' => 's', 'Ğ' => 'g', 'Ü' => 'u', 'Ö' => 'o', 'Ç' => 'c', 'Â' => 'a', 'Î' => 'i', 'Û' => 'u']);
    $s = mb_strtolower($s, 'UTF-8');
    return strtr($s, ['ı' => 'i', 'ş' => 's', 'ğ' => 'g', 'ü' => 'u', 'ö' => 'o', 'ç' => 'c', 'â' => 'a', 'î' => 'i', 'û' => 'u']);
};
$qn = $fold($q);

$prefix = [];
$contains = [];
foreach ($list as $s) {
    $nl = $fold((string) $s['name']);
    $pos = mb_strpos($nl, $qn, 0, 'UTF-8');
    if ($pos === 0) {
        $prefix[] = $s;
    } elseif ($pos !== false) {
        $contains[] = $s;
    }
    if (count($prefix) >= 10) break;
}
$results = array_slice(array_merge($prefix, $contains), 0, 10);

echo json_encode($results, JSON_UNESCAPED_UNICODE);
exit;
