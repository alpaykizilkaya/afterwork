<?php

declare(strict_types=1);

session_start();

// DEV BYPASS — localhost only, remove before pushing to production
if (in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', 'localhost:8000', '127.0.0.1', '127.0.0.1:8000'], true)) {
    $_SESSION['account']  = ['account_id' => 0, 'email' => 'dev@localhost', 'role' => 'employer', 'is_verified' => 1];
    $_SESSION['employer'] = ['id' => 0, 'account_id' => 0, 'email' => 'dev@localhost', 'company_name' => 'Dev Şirket', 'role' => 'employer'];
}

if (
    !isset($_SESSION['account'])
    || !is_array($_SESSION['account'])
    || (string) ($_SESSION['account']['role'] ?? '') !== 'employer'
) {
    header('Location: /auth.php#giris');
    exit;
}

require_once __DIR__ . '/../../../backend/config/db.php';
require_once __DIR__ . '/../../../backend/config/taxonomy.php';
require_once __DIR__ . '/../../../backend/auth/session-helper.php';

$tax = aw_taxonomy();

$employer = is_array($_SESSION['employer'] ?? null) ? $_SESSION['employer'] : [];
$employerId = (int) ($employer['id'] ?? 0);
$companyName = trim((string) ($employer['company_name'] ?? '')) ?: 'Şirketiniz';

// ── Read filter + sort inputs (everything is GET so URLs are shareable) ──
$q         = trim((string) ($_GET['q'] ?? ''));
$fDate     = (string) ($_GET['date'] ?? '');
$fSector   = trim((string) ($_GET['sector'] ?? ''));
$fDept     = trim((string) ($_GET['dept'] ?? ''));
$fPosLevel = trim((string) ($_GET['poslevel'] ?? ''));
$fModel    = trim((string) ($_GET['model'] ?? ''));
$fType     = trim((string) ($_GET['type'] ?? ''));
$fEdu      = trim((string) ($_GET['edu'] ?? ''));
$fBand     = (string) ($_GET['deneyim'] ?? '');
$fLang     = trim((string) ($_GET['lang'] ?? ''));
$fLocation = trim((string) ($_GET['location'] ?? ''));
$fDistrict = trim((string) ($_GET['district'] ?? ''));
$fSize     = trim((string) ($_GET['size'] ?? ''));
$fSkill    = trim((string) ($_GET['skill'] ?? ''));
$fDeadline = (string) ($_GET['deadline'] ?? '');
$fSalary   = isset($_GET['salary_min']) && $_GET['salary_min'] !== '' ? max(0, (int) $_GET['salary_min']) : null;
$fDisab    = (string) ($_GET['eng'] ?? '');
$fIso500   = (string) ($_GET['iso500'] ?? '');
$sort      = (string) ($_GET['sort'] ?? 'yeni');

// Whitelisted "deadline within N days" windows.
$deadlineDays = ['3' => 3, '7' => 7, '30' => 30];

// Whitelisted sort modes → SQL. Keeps user input out of the ORDER BY clause.
$sortMap = [
    'yeni'        => 'jl.created_at DESC, jl.id DESC',
    'eski'        => 'jl.created_at ASC, jl.id ASC',
    'sirket'      => 'e.company_name ASC, jl.created_at DESC',
    'maas_yuksek' => 'COALESCE(jl.salary_max, jl.salary_min) IS NULL, COALESCE(jl.salary_max, jl.salary_min) DESC',
    'maas_dusuk'  => 'COALESCE(jl.salary_min, jl.salary_max) IS NULL, COALESCE(jl.salary_min, jl.salary_max) ASC',
];
if (!isset($sortMap[$sort])) {
    $sort = 'yeni';
}

// Date ranges → constant SQL fragments (keys are whitelisted, no value injected).
$dateMap = [
    'bugun' => 'jl.created_at >= CURDATE()',
    '3saat' => 'jl.created_at >= (NOW() - INTERVAL 3 HOUR)',
    '8saat' => 'jl.created_at >= (NOW() - INTERVAL 8 HOUR)',
    '3gun'  => 'jl.created_at >= (NOW() - INTERVAL 3 DAY)',
    '7gun'  => 'jl.created_at >= (NOW() - INTERVAL 7 DAY)',
    '15gun' => 'jl.created_at >= (NOW() - INTERVAL 15 DAY)',
];

$listings = [];
$cities = [];
$districts = [];
$optSector = $optDept = $optPosLevel = $optModel = $optType = $optEdu = $optLang = [];
$optSize = [];
$optSkills = [];
$disabilityMap = [];
$iso500Map = [];
$expBands = [];
$deadlineMap = [];
$totalActive = 0;
$totalCompanies = 0;
$loadError = false;

// Orders the values actually present in the data by the canonical taxonomy
// order, appending any stray legacy values not in the taxonomy.
$orderByTax = static function (array $present, array $canon): array {
    $out = [];
    foreach ($canon as $c) {
        if (in_array($c, $present, true)) {
            $out[] = $c;
        }
    }
    foreach ($present as $p) {
        if (!in_array($p, $out, true)) {
            $out[] = $p;
        }
    }
    return $out;
};

try {
    $pdo = db();

    // Base scope: active listings from OTHER companies. Dev account (id 0) sees all.
    $scopeSql = 'jl.is_active = 1 AND jl.employer_id <> :me';
    $scopeParams = ['me' => $employerId];

    // Headline numbers — real, unfiltered scope totals.
    $totRow = $pdo->prepare(
        "SELECT COUNT(*) AS listings, COUNT(DISTINCT jl.employer_id) AS companies
         FROM job_listings jl
         JOIN employers e ON e.id = jl.employer_id
         WHERE {$scopeSql}"
    );
    $totRow->execute($scopeParams);
    $tot = $totRow->fetch() ?: ['listings' => 0, 'companies' => 0];
    $totalActive = (int) $tot['listings'];
    $totalCompanies = (int) $tot['companies'];

    // City / district dropdowns are free-text, so populate them from real data.
    $distinct = static function (PDO $pdo, string $expr, string $scopeSql, array $params): array {
        $stmt = $pdo->prepare(
            "SELECT DISTINCT TRIM({$expr}) AS v
             FROM job_listings jl
             JOIN employers e ON e.id = jl.employer_id
             WHERE {$scopeSql} AND {$expr} IS NOT NULL AND TRIM({$expr}) <> ''
             ORDER BY v ASC"
        );
        $stmt->execute($params);
        return array_map(static fn ($r) => (string) $r['v'], $stmt->fetchAll());
    };
    // Every dropdown is built ONLY from values that exist in the live listings,
    // so a filter never offers an option that would match nothing. Free-text
    // columns (city/district) use the distinct values as-is; enumerated columns
    // are re-ordered to the canonical taxonomy order.
    $cities = $distinct($pdo, 'jl.location', $scopeSql, $scopeParams);
    $districts = $distinct($pdo, 'jl.district', $scopeSql, $scopeParams);
    $optSector   = $orderByTax($distinct($pdo, 'e.sector', $scopeSql, $scopeParams), $tax['sectors']);
    $optDept     = $orderByTax($distinct($pdo, 'jl.department', $scopeSql, $scopeParams), $tax['departments']);
    $optPosLevel = $orderByTax($distinct($pdo, 'jl.position_level', $scopeSql, $scopeParams), $tax['position_levels']);
    $optModel    = $orderByTax($distinct($pdo, 'jl.work_model', $scopeSql, $scopeParams), $tax['work_models']);
    $optType     = $orderByTax($distinct($pdo, 'jl.employment_type', $scopeSql, $scopeParams), $tax['employment_types']);
    $optEdu      = $orderByTax($distinct($pdo, 'jl.education_level', $scopeSql, $scopeParams), $tax['education_levels']);
    $optLang     = $orderByTax($distinct($pdo, 'jl.listing_language', $scopeSql, $scopeParams), $tax['languages']);
    $optSize     = $orderByTax($distinct($pdo, 'e.company_size', $scopeSql, $scopeParams), $tax['company_sizes']);

    // Skills are stored comma-separated; split into individual tags and keep the
    // distinct set that actually appears, so the filter offers real technologies.
    $skillStrings = $distinct($pdo, 'jl.skills', $scopeSql, $scopeParams);
    $skillSet = [];
    foreach ($skillStrings as $row) {
        foreach (preg_split('/[,;\/]+/u', $row) ?: [] as $tok) {
            $tok = trim($tok);
            if ($tok !== '') {
                $skillSet[mb_strtolower($tok, 'UTF-8')] = $tok;
            }
        }
    }
    $optSkills = array_values($skillSet);
    sort($optSkills, SORT_NATURAL | SORT_FLAG_CASE);

    // Boolean / derived filters: only offer an option if at least one listing
    // would match it.
    $presStmt = $pdo->prepare(
        "SELECT
            SUM(jl.is_disability = 1) AS disab1,
            SUM(jl.is_disability = 0) AS disab0,
            SUM(e.is_iso500 = 1) AS iso1,
            SUM(jl.experience_level = 'Deneyim Aranmıyor') AS expnone,
            SUM(jl.experience_level IS NOT NULL AND jl.experience_level <> '' AND jl.experience_level <> 'Deneyim Aranmıyor') AS expyes,
            SUM(jl.deadline IS NOT NULL AND jl.deadline >= CURDATE()) AS deadfut
         FROM job_listings jl
         JOIN employers e ON e.id = jl.employer_id
         WHERE {$scopeSql}"
    );
    $presStmt->execute($scopeParams);
    $pres = $presStmt->fetch() ?: [];
    if ((int) ($pres['disab1'] ?? 0) > 0) { $disabilityMap['sadece'] = 'Sadece engelli ilanları'; }
    if ((int) ($pres['disab0'] ?? 0) > 0) { $disabilityMap['gizle'] = 'Engelli ilanlarını gösterme'; }
    if ((int) ($pres['iso1'] ?? 0) > 0) { $iso500Map['evet'] = 'ISO 500 şirketleri'; }
    if ((int) ($pres['expyes'] ?? 0) > 0) { $expBands['deneyimli'] = 'Deneyimli'; }
    if ((int) ($pres['expnone'] ?? 0) > 0) { $expBands['deneyimsiz'] = 'Deneyimsiz'; }
    if ((int) ($pres['deadfut'] ?? 0) > 0) {
        $deadlineMap = ['3' => 'Son 3 gün içinde', '7' => 'Bu hafta (7 gün)', '30' => 'Bu ay (30 gün)'];
    }

    // ── Apply active filters ──
    $where = [$scopeSql];
    $params = $scopeParams;

    if ($q !== '') {
        // Distinct placeholders per column: native prepares (emulation off in
        // db.php) don't allow reusing one named marker across the statement.
        $where[] = '(jl.title LIKE :q_title OR e.company_name LIKE :q_company OR jl.skills LIKE :q_skills OR jl.location LIKE :q_location)';
        $like = '%' . $q . '%';
        $params['q_title'] = $like;
        $params['q_company'] = $like;
        $params['q_skills'] = $like;
        $params['q_location'] = $like;
    }
    if ($fDate !== '' && isset($dateMap[$fDate])) {
        $where[] = $dateMap[$fDate];
    }
    if ($fSector !== '') {
        $where[] = 'e.sector = :sector';
        $params['sector'] = $fSector;
    }
    if ($fDept !== '') {
        $where[] = 'jl.department = :dept';
        $params['dept'] = $fDept;
    }
    if ($fPosLevel !== '') {
        $where[] = 'jl.position_level = :poslevel';
        $params['poslevel'] = $fPosLevel;
    }
    if ($fModel !== '') {
        $where[] = 'jl.work_model = :model';
        $params['model'] = $fModel;
    }
    if ($fType !== '') {
        $where[] = 'jl.employment_type = :type';
        $params['type'] = $fType;
    }
    if ($fEdu !== '') {
        $where[] = 'jl.education_level = :edu';
        $params['edu'] = $fEdu;
    }
    if ($fBand === 'deneyimsiz') {
        $where[] = "jl.experience_level = 'Deneyim Aranmıyor'";
    } elseif ($fBand === 'deneyimli') {
        $where[] = "(jl.experience_level IS NOT NULL AND jl.experience_level <> '' AND jl.experience_level <> 'Deneyim Aranmıyor')";
    }
    if ($fLang !== '') {
        $where[] = 'jl.listing_language = :lang';
        $params['lang'] = $fLang;
    }
    if ($fLocation !== '') {
        $where[] = 'jl.location = :location';
        $params['location'] = $fLocation;
    }
    if ($fDistrict !== '') {
        $where[] = 'jl.district = :district';
        $params['district'] = $fDistrict;
    }
    if ($fSize !== '') {
        $where[] = 'e.company_size = :size';
        $params['size'] = $fSize;
    }
    if ($fSkill !== '') {
        $where[] = 'jl.skills LIKE :skill';
        $params['skill'] = '%' . $fSkill . '%';
    }
    if (isset($deadlineDays[$fDeadline])) {
        // N comes from a whitelisted int map, safe to inline.
        $where[] = 'jl.deadline IS NOT NULL AND jl.deadline >= CURDATE() AND jl.deadline <= (CURDATE() + INTERVAL ' . $deadlineDays[$fDeadline] . ' DAY)';
    }
    if ($fSalary !== null) {
        $where[] = 'COALESCE(jl.salary_max, jl.salary_min) >= :salary_min';
        $params['salary_min'] = $fSalary;
    }
    if ($fDisab === 'sadece') {
        $where[] = 'jl.is_disability = 1';
    } elseif ($fDisab === 'gizle') {
        $where[] = 'jl.is_disability = 0';
    }
    if ($fIso500 === 'evet') {
        $where[] = 'e.is_iso500 = 1';
    }

    // Note: application/view counts are intentionally NOT selected — those are
    // the poster's private analytics (shown only in their own Mercek).
    $sql =
        "SELECT
            jl.id, jl.title, jl.employment_type, jl.work_model, jl.location, jl.district,
            jl.salary_min, jl.salary_max, jl.experience_level, jl.skills,
            jl.department, jl.position_level, jl.education_level, jl.listing_language,
            jl.is_disability, jl.description, jl.contact_email, jl.created_at,
            e.company_name, e.sector, e.city, e.is_iso500, e.account_id
         FROM job_listings jl
         JOIN employers e ON e.id = jl.employer_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY {$sortMap[$sort]}
         LIMIT 200";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $listings = $stmt->fetchAll();
} catch (Throwable $e) {
    $loadError = true;
}

$resultCount = count($listings);
$hasFilters = $q !== '' || $fDate !== '' || $fSector !== '' || $fDept !== '' || $fPosLevel !== ''
    || $fModel !== '' || $fType !== '' || $fEdu !== '' || $fBand !== '' || $fLang !== ''
    || $fLocation !== '' || $fDistrict !== '' || $fSize !== '' || $fSkill !== '' || $fDeadline !== ''
    || $fSalary !== null || $fDisab !== '' || $fIso500 !== '';

// Initials for a company avatar (matches the topbar avatar treatment).
$feedInitials = static function (string $name): string {
    $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
    $a = mb_substr((string) ($words[0] ?? '?'), 0, 1, 'UTF-8');
    $b = isset($words[1]) ? mb_substr((string) $words[1], 0, 1, 'UTF-8') : '';
    $out = mb_strtoupper($a . $b, 'UTF-8');
    return $out !== '' ? $out : '?';
};

$feedTimeAgo = static function (string $created): ?string {
    if ($created === '' || ($ts = strtotime($created)) === false) {
        return null;
    }
    $days = (int) floor((time() - $ts) / 86400);
    if ($days <= 0) {
        return 'bugün yayında';
    }
    if ($days === 1) {
        return '1 gün önce';
    }
    if ($days < 30) {
        return $days . ' gün önce';
    }
    $months = (int) floor($days / 30);
    return $months === 1 ? '1 ay önce' : $months . ' ay önce';
};

$salaryLabel = static function (?int $min, ?int $max): ?string {
    if ($min !== null && $max !== null) {
        return number_format($min, 0, ',', '.') . ' – ' . number_format($max, 0, ',', '.') . ' ₺';
    }
    if ($min !== null) {
        return number_format($min, 0, ',', '.') . ' ₺+';
    }
    if ($max !== null) {
        return number_format($max, 0, ',', '.') . ' ₺’ye kadar';
    }
    return null;
};

// Renders a <select> from a flat value=label list (taxonomy / distinct values).
$renderSelect = static function (string $name, string $placeholder, array $options, string $current): string {
    $html = '<select class="ep-select" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" aria-label="' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '">';
    $html .= '<option value="">' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '</option>';
    foreach ($options as $opt) {
        $sel = $opt === $current ? ' selected' : '';
        $safe = htmlspecialchars($opt, ENT_QUOTES, 'UTF-8');
        $html .= '<option value="' . $safe . '"' . $sel . '>' . $safe . '</option>';
    }
    $html .= '</select>';
    return $html;
};

// Renders a <select> from a value=>label map (placeholder uses value "").
$renderSelectKV = static function (string $name, string $placeholder, array $map, string $current): string {
    $html = '<select class="ep-select" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '" aria-label="' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '">';
    $html .= '<option value="">' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '</option>';
    foreach ($map as $val => $label) {
        $sel = (string) $val === $current ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>'
            . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    $html .= '</select>';
    return $html;
};

$sortLabels = [
    'yeni'        => 'En yeni',
    'eski'        => 'En eski',
    'sirket'      => 'Şirket adı (A–Z)',
    'maas_yuksek' => 'Maaş (yüksek → düşük)',
    'maas_dusuk'  => 'Maaş (düşük → yüksek)',
];
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AFTERWORK | Akış</title>
  <link rel="stylesheet" href="/frontend/assets/css/employer/panel.css?v=<?= filemtime(__DIR__ . '/../../assets/css/employer/panel.css') ?>">
  <link rel="stylesheet" href="/frontend/assets/css/employer/feed.css?v=<?= filemtime(__DIR__ . '/../../assets/css/employer/feed.css') ?>">
  <link rel="stylesheet" href="/frontend/assets/css/shared/logout-modal.css?v=<?= filemtime(__DIR__ . '/../../assets/css/shared/logout-modal.css') ?>">
</head>
<body>
  <div class="ep-page">
    <?php
    $activeTab = 'feed';
    $searchQuery = $q;
    include __DIR__ . '/../../partials/employer-topbar.php';
    ?>

    <section class="ep-dukkan ep-feed" aria-label="Akış">
      <header class="ep-dukkan-head">
        <div>
          <p class="ep-dukkan-kicker">Akış</p>
          <h1>Piyasayı keşfet</h1>
          <p class="ep-dukkan-lead">
            Diğer şirketlerin yayında olan ilanlarını incele, pozisyonları ve maaş bantlarını karşılaştır,
            sektöre göre filtrele.
          </p>
        </div>
        <div class="ep-dukkan-stats">
          <div class="ep-stat-card--light">
            <strong><?= number_format($totalActive, 0, ',', '.') ?></strong>
            <span>Aktif İlan</span>
          </div>
          <div class="ep-stat-card--light">
            <strong><?= number_format($totalCompanies, 0, ',', '.') ?></strong>
            <span>Şirket</span>
          </div>
        </div>
      </header>

      <?php $activeFeedTab = 'ilanlar'; include __DIR__ . '/../../partials/feed-switch.php'; ?>

      <form class="ep-feed-filters" method="get" action="/akis.php" role="search">
        <div class="ep-feed-search">
          <svg class="ep-feed-search-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
            <circle cx="7" cy="7" r="5" stroke="currentColor" stroke-width="1.6"/>
            <path d="M11 11l3 3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
          </svg>
          <input type="search" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>"
                 placeholder="Pozisyon, şirket, yetenek ara…" autocomplete="off" aria-label="Ara">
        </div>

        <div class="ep-feed-filter-grid">
          <?= $renderSelectKV('date', 'Tüm tarihler', $tax['date_ranges'], $fDate) ?>
          <?php if ($optSector): ?><?= $renderSelect('sector', 'Tüm sektörler', $optSector, $fSector) ?><?php endif; ?>
          <?php if ($optDept): ?><?= $renderSelect('dept', 'Departman', $optDept, $fDept) ?><?php endif; ?>
          <?php if ($optPosLevel): ?><?= $renderSelect('poslevel', 'Pozisyon seviyesi', $optPosLevel, $fPosLevel) ?><?php endif; ?>
          <?php if ($optModel): ?><?= $renderSelect('model', 'Çalışma tercihi', $optModel, $fModel) ?><?php endif; ?>
          <?php if ($optType): ?><?= $renderSelect('type', 'Çalışma şekli', $optType, $fType) ?><?php endif; ?>
          <?php if ($optEdu): ?><?= $renderSelect('edu', 'Eğitim seviyesi', $optEdu, $fEdu) ?><?php endif; ?>
          <?php if ($expBands): ?><?= $renderSelectKV('deneyim', 'Deneyim süresi', $expBands, $fBand) ?><?php endif; ?>
          <?php if ($optLang): ?><?= $renderSelect('lang', 'İlan dili', $optLang, $fLang) ?><?php endif; ?>
          <?php if ($optSize): ?><?= $renderSelect('size', 'Şirket büyüklüğü', $optSize, $fSize) ?><?php endif; ?>
          <?php if ($optSkills): ?><?= $renderSelect('skill', 'Yetenek / Teknoloji', $optSkills, $fSkill) ?><?php endif; ?>
          <?php if ($deadlineMap): ?><?= $renderSelectKV('deadline', 'Son başvuru yaklaşan', $deadlineMap, $fDeadline) ?><?php endif; ?>
          <?php if ($cities): ?><?= $renderSelect('location', 'Şehir', $cities, $fLocation) ?><?php endif; ?>
          <?php if ($districts): ?><?= $renderSelect('district', 'İlçe', $districts, $fDistrict) ?><?php endif; ?>
          <?php if ($disabilityMap): ?><?= $renderSelectKV('eng', 'Engelli ilanı', $disabilityMap, $fDisab) ?><?php endif; ?>
          <?php if ($iso500Map): ?><?= $renderSelectKV('iso500', 'Şirket özelliği', $iso500Map, $fIso500) ?><?php endif; ?>
          <input class="ep-input ep-feed-salary" type="number" name="salary_min" min="0" step="1000"
                 inputmode="numeric" value="<?= $fSalary !== null ? (int) $fSalary : '' ?>"
                 placeholder="Min. maaş (₺)" aria-label="Minimum maaş">
        </div>

        <div class="ep-feed-sortrow">
          <label class="ep-feed-sort">
            <span>Sırala</span>
            <select class="ep-select" name="sort" aria-label="Sıralama">
              <?php foreach ($sortLabels as $val => $label): ?>
                <option value="<?= $val ?>"<?= $sort === $val ? ' selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <div class="ep-feed-actions">
            <button type="submit" class="ep-feed-apply">Uygula</button>
            <?php if ($hasFilters || $sort !== 'yeni'): ?>
              <a class="ep-feed-clear" href="/akis.php">Temizle</a>
            <?php endif; ?>
          </div>
        </div>
      </form>

      <?php if ($loadError): ?>
        <div class="ep-feedback ep-feedback--error" role="alert">
          <p>Akış yüklenirken bir sorun oluştu. Lütfen sayfayı yenile.</p>
        </div>
      <?php endif; ?>

      <p class="ep-feed-meta">
        <?php if ($resultCount > 0): ?>
          <strong><?= number_format($resultCount, 0, ',', '.') ?></strong> ilan
          <?= $hasFilters ? 'eşleşti' : 'listeleniyor' ?>
        <?php else: ?>
          Sonuç yok
        <?php endif; ?>
      </p>

      <?php if ($resultCount > 0): ?>
        <div class="ep-dukkan-grid ep-feed-grid">
          <?php foreach ($listings as $lst):
            $lTitle = (string) ($lst['title'] ?? '');
            $lCompany = (string) ($lst['company_name'] ?? '');
            $lSector = trim((string) ($lst['sector'] ?? ''));
            $lCity = trim((string) ($lst['city'] ?? ''));
            $lType = trim((string) ($lst['employment_type'] ?? ''));
            $lModel = trim((string) ($lst['work_model'] ?? ''));
            $lDept = trim((string) ($lst['department'] ?? ''));
            $lPosLevel = trim((string) ($lst['position_level'] ?? ''));
            $lEdu = trim((string) ($lst['education_level'] ?? ''));
            $lLocation = trim((string) ($lst['location'] ?? ''));
            $lDistrict = trim((string) ($lst['district'] ?? ''));
            $lLang = trim((string) ($lst['listing_language'] ?? ''));
            $lDisab = (int) ($lst['is_disability'] ?? 0) === 1;
            $lIso = (int) ($lst['is_iso500'] ?? 0) === 1;
            $lMin = $lst['salary_min'] !== null ? (int) $lst['salary_min'] : null;
            $lMax = $lst['salary_max'] !== null ? (int) $lst['salary_max'] : null;
            $lDesc = trim((string) ($lst['description'] ?? ''));
            $lEmail = trim((string) ($lst['contact_email'] ?? ''));
            $lAccId = (int) ($lst['account_id'] ?? 0);
            $sal = $salaryLabel($lMin, $lMax);
            $ago = $feedTimeAgo((string) ($lst['created_at'] ?? ''));
            $locLabel = $lDistrict !== '' && $lLocation !== '' ? $lLocation . ' · ' . $lDistrict : $lLocation;
            $companyLine = $lSector !== '' && $lCity !== '' ? $lSector . ' · ' . $lCity : ($lSector ?: $lCity);
            $excerpt = $lDesc !== '' ? mb_substr($lDesc, 0, 150, 'UTF-8') . (mb_strlen($lDesc, 'UTF-8') > 150 ? '…' : '') : '';
          ?>
          <article class="ep-poster-card ep-feed-card">
            <div class="ep-feed-company">
              <span class="ep-feed-avatar" aria-hidden="true"><?= htmlspecialchars($feedInitials($lCompany), ENT_QUOTES, 'UTF-8') ?></span>
              <span class="ep-feed-company-meta">
                <span class="ep-feed-company-name">
                  <?= htmlspecialchars($lCompany ?: 'Şirket', ENT_QUOTES, 'UTF-8') ?>
                  <?php if ($lIso): ?><span class="ep-feed-tag" title="ISO 500 şirketi">ISO 500</span><?php endif; ?>
                </span>
                <?php if ($companyLine !== ''): ?>
                  <span class="ep-feed-company-sub"><?= htmlspecialchars($companyLine, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
              </span>
              <span class="ep-poster-status is-live" aria-hidden="true">
                <span class="ep-poster-dot"></span>Aktif
              </span>
            </div>

            <h3 class="ep-poster-title">
              <a class="ep-poster-link" href="/akis.php?id=<?= (int) $lst['id'] ?>"><?= htmlspecialchars($lTitle ?: 'İsimsiz ilan', ENT_QUOTES, 'UTF-8') ?></a>
            </h3>

            <?php if ($sal !== null): ?>
              <p class="ep-poster-salary"><?= htmlspecialchars($sal, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <div class="ep-poster-chips">
              <?php if ($lType !== ''): ?><span class="ep-poster-chip"><?= htmlspecialchars($lType, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
              <?php if ($lModel !== ''): ?><span class="ep-poster-chip"><?= htmlspecialchars($lModel, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
              <?php if ($lDept !== ''): ?><span class="ep-poster-chip"><?= htmlspecialchars($lDept, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
              <?php if ($lPosLevel !== ''): ?><span class="ep-poster-chip"><?= htmlspecialchars($lPosLevel, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
              <?php if ($lEdu !== ''): ?><span class="ep-poster-chip ep-poster-chip--ghost"><?= htmlspecialchars($lEdu, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
              <?php if ($locLabel !== ''): ?><span class="ep-poster-chip ep-poster-chip--ghost"><?= htmlspecialchars($locLabel, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
              <?php if ($lDisab): ?><span class="ep-poster-chip ep-feed-chip--disab">Engelli ilanı</span><?php endif; ?>
            </div>

            <?php if ($excerpt !== ''): ?>
              <p class="ep-feed-excerpt"><?= htmlspecialchars($excerpt, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <footer class="ep-poster-foot">
              <?php if ($lLang !== ''): ?>
                <span class="ep-feed-lang"><?= htmlspecialchars($lLang, ENT_QUOTES, 'UTF-8') ?></span>
              <?php endif; ?>
              <?php if ($ago !== null): ?>
                <span class="ep-poster-time ep-feed-posted"><?= htmlspecialchars($ago, ENT_QUOTES, 'UTF-8') ?></span>
              <?php endif; ?>
              <span class="ep-feed-foot-actions">
                <?php if ($lAccId > 0 && $lAccId !== $employerId): ?>
                  <a class="ep-feed-msg" href="/mesaj-baslat.php?account=<?= $lAccId ?>&listing=<?= (int) $lst['id'] ?>">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                      <path d="M4 5h16v11H8l-4 3V5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                    </svg>
                    Mesaj at
                  </a>
                <?php endif; ?>
                <?php if ($lEmail !== ''): ?>
                  <a class="ep-feed-contact" href="mailto:<?= htmlspecialchars($lEmail, ENT_QUOTES, 'UTF-8') ?>?subject=<?= rawurlencode($lTitle . ' ilanı hakkında') ?>">
                    İletişim
                  </a>
                <?php endif; ?>
              </span>
            </footer>
          </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="ep-feed-empty">
          <span class="ep-feed-empty-mark" aria-hidden="true">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
              <circle cx="10.5" cy="10.5" r="6.5" stroke="currentColor" stroke-width="1.6"/>
              <path d="M15.5 15.5L20 20" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
          </span>
          <?php if ($hasFilters): ?>
            <h2>Eşleşen ilan bulunamadı</h2>
            <p>Filtreleri gevşetmeyi dene ya da aramayı temizle.</p>
            <a class="ep-feed-empty-cta" href="/akis.php">Filtreleri temizle</a>
          <?php else: ?>
            <h2>Akış henüz boş</h2>
            <p>Başka şirketler ilan yayınladığında burada görünecek. İlk hamleyi sen yapabilirsin.</p>
            <a class="ep-feed-empty-cta" href="/isveren-panel.php?yeni=1">Yeni ilan oluştur</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <div id="logout-modal" class="logout-modal" role="dialog" aria-modal="true" aria-labelledby="logout-modal-title">
    <div class="logout-modal__backdrop" aria-hidden="true"></div>
    <div class="logout-modal__card" role="document">
      <p class="logout-modal__kicker">Çıkış Yap</p>
      <h2 id="logout-modal-title" class="logout-modal__title">Emin misin?</h2>
      <p class="logout-modal__lead">Hesabından çıkış yapmak üzeresin. Devam edersen tekrar giriş yapman gerekecek.</p>
      <div class="logout-modal__actions">
        <form class="logout-modal__form" action="/logout.php" method="post">
          <input type="hidden" name="confirm" value="yes">
          <button type="submit" class="logout-modal__btn logout-modal__btn--danger">Evet, çıkış yap</button>
        </form>
        <button type="button" class="logout-modal__btn logout-modal__btn--ghost" data-logout-close>Vazgeç</button>
      </div>
    </div>
  </div>

  <script src="/frontend/assets/js/employer/topbar.js?v=<?= filemtime(__DIR__ . '/../../assets/js/employer/topbar.js') ?>" defer></script>
  <script src="/frontend/assets/js/employer/feed.js?v=<?= filemtime(__DIR__ . '/../../assets/js/employer/feed.js') ?>" defer></script>
  <script src="/frontend/assets/js/shared/logout-modal.js?v=<?= filemtime(__DIR__ . '/../../assets/js/shared/logout-modal.js') ?>" defer></script>
</body>
</html>
