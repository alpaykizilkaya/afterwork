<?php

declare(strict_types=1);

session_start();

// DEV BYPASS — localhost only (oturum yoksa dev iş arayan tohumlar).
require_once __DIR__ . '/../../../backend/auth/dev-session.php';
aw_dev_session('seeker');

// Rol kilidi: giriş yoksa auth'a; başka rolle giriş varsa KENDİ paneline
// (iş arayan çıkış yapmadan anasayfaya ya da işveren profiline geçemez).
if (!isset($_SESSION['account']) || !is_array($_SESSION['account'])) {
    header('Location: /auth.php#giris');
    exit;
}
if ((string) ($_SESSION['account']['role'] ?? '') !== 'seeker') {
    header('Location: ' . ((string) ($_SESSION['account']['role'] ?? '') === 'employer' ? '/isveren-panel.php' : '/auth.php#giris'));
    exit;
}

require_once __DIR__ . '/../../../backend/config/db.php';
require_once __DIR__ . '/../../../backend/config/taxonomy.php';
require_once __DIR__ . '/../../../backend/auth/session-helper.php';

$accountId = (int) ($_SESSION['account']['account_id'] ?? 0);
$email     = (string) ($_SESSION['account']['email'] ?? '');
$isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', 'localhost:8000', '127.0.0.1', '127.0.0.1:8000'], true);

$tax = aw_taxonomy();
$pdo = db();

/* ---- load the seeker profile row ------------------------------------- */
$seekerRow = null;
try {
    $st = $pdo->prepare('SELECT * FROM seekers WHERE account_id = :a LIMIT 1');
    $st->execute(['a' => $accountId]);
    $seekerRow = $st->fetch() ?: null;
} catch (Throwable) {
    $seekerRow = null;
}

// Localhost preview with no real row → synthesize a fresh (un-onboarded) shell
// so the wizard can be designed without touching the DB.
if ($seekerRow === null && $isLocalhost) {
    $seekerRow = ['full_name' => (string) ($_SESSION['seeker']['full_name'] ?? 'Dev Kullanıcı'), 'profile_completed' => 0];
}

$errors = [];
$flash  = null;

$fieldCaps = [
    'headline' => 120, 'city' => 80, 'district' => 80, 'experience_level' => 40,
    'education_level' => 64, 'school' => 160, 'department' => 80, 'position_level' => 64,
    'employment_type' => 40, 'sector' => 80, 'languages' => 160, 'work_pref' => 40,
    'skills' => 500, 'about' => 4000, 'phone' => 32, 'linkedin' => 255, 'website' => 255,
];

// Each profile section edits IN PLACE and updates only its own fields.
$sectionDefs = [
    'header'     => ['text' => ['headline', 'city'], 'int' => [], 'check' => ['open_to_work']],
    'about'      => ['text' => ['about'], 'int' => [], 'check' => []],
    'experience' => ['text' => ['experience_level'], 'int' => [], 'check' => []],
    'education'  => ['text' => ['education_level', 'school'], 'int' => [], 'check' => []],
    'career'     => [
        'text'  => ['department', 'position_level', 'employment_type', 'work_pref', 'sector', 'district'],
        'int'   => ['salary_expectation'],
        'check' => ['is_disability'],
    ],
    'skills'     => ['text' => ['skills'], 'int' => [], 'check' => []],
    'languages'  => ['text' => ['languages'], 'int' => [], 'check' => []],
    'contact'    => ['text' => ['phone', 'linkedin', 'website'], 'int' => [], 'check' => []],
];

// "Şirket Sorularım" — predefined question set, grouped. Answers are stored once
// and reused to pre-fill future applications.
$companyQuestions = [
    'Çok Sorulan Sorular' => [
        'net_salary'     => 'Aylık net ücret beklentiniz nedir?',
        'travel_barrier' => 'Seyahat engeliniz var mı?',
        'shift_weekend'  => 'Vardiyalı veya hafta sonları çalışır mısınız?',
        'driving'        => 'Aktif olarak araç kullanıyor musunuz?',
    ],
    'Özgeçmişine Yönelik Sorular' => [
        'english_level'     => 'İngilizce seviyeniz nedir?',
        'programs'          => 'Hangi programları kullanabiliyorsunuz?',
        'currently_working' => 'Şu an çalışıyor musunuz?',
        'certificates'      => 'Hangi sertifikalarınız/belgeleriniz var?',
    ],
];
$companyQuestionKeys = array_merge(...array_map('array_keys', array_values($companyQuestions)));

/* ---- POST: onboarding finish OR per-section inline edit --------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode  = (string) ($_POST['mode'] ?? '');
    $clean = static fn (string $k, int $max): string => mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $max, 'UTF-8');

    if ($mode === 'onboarding') {
        $fields = [
            'headline'         => $clean('headline', 120),
            'city'             => $clean('city', 80),
            'experience_level' => $clean('experience_level', 40),
            'education_level'  => $clean('education_level', 64),
            'department'       => $clean('department', 80),
            'work_pref'        => $clean('work_pref', 40),
            'skills'           => $clean('skills', 500),
            'about'            => $clean('about', 4000),
            'open_to_work'     => isset($_POST['open_to_work']) ? 1 : 0,
        ];
        if ($fields['headline'] === '') $errors[] = 'Kendini bir başlıkla tanıt (örn. "Frontend Geliştirici").';
        if ($fields['city'] === '')     $errors[] = 'Şehir alanı gerekli.';
        if ($errors === []) {
            try {
                $pdo->prepare(
                    'UPDATE seekers SET headline=:headline, city=:city, experience_level=:experience_level,
                        education_level=:education_level, department=:department, work_pref=:work_pref,
                        skills=:skills, about=:about, open_to_work=:open_to_work,
                        profile_completed=1, onboarded_at=NOW()
                     WHERE account_id=:acc'
                )->execute($fields + ['acc' => $accountId]);
                header('Location: /seeker-panel.php');
                exit;
            } catch (Throwable) {
                $errors[] = 'Kaydedilemedi. (Veritabanı seeker profili için güncellenmemiş olabilir.)';
            }
        }
    } elseif ($mode === 'section' && isset($sectionDefs[(string) ($_POST['section'] ?? '')])) {
        $section = (string) $_POST['section'];
        $def = $sectionDefs[$section];
        $set = [];
        $params = ['acc' => $accountId];
        foreach ($def['text'] as $f) {
            $v = $clean($f, $fieldCaps[$f] ?? 255);
            $params[$f] = $v !== '' ? $v : null;
            $set[] = "$f = :$f";
        }
        foreach ($def['int'] as $f) {
            $raw = preg_replace('/\D/', '', (string) ($_POST[$f] ?? ''));
            $params[$f] = ($raw !== '' && $raw !== null) ? (int) $raw : null;
            $set[] = "$f = :$f";
        }
        foreach ($def['check'] as $f) {
            $params[$f] = isset($_POST[$f]) ? 1 : 0;
            $set[] = "$f = :$f";
        }
        try {
            $pdo->prepare('UPDATE seekers SET ' . implode(', ', $set) . ' WHERE account_id = :acc')->execute($params);
        } catch (Throwable) {
            // best-effort
        }
        header('Location: /seeker-panel.php#sec-' . $section);
        exit;
    } elseif ($mode === 'toggle_otw') {
        // "İş fırsatlarına açığım" anahtarı.
        try {
            $pdo->prepare('UPDATE seekers SET open_to_work = :v WHERE account_id = :acc')
                ->execute(['v' => isset($_POST['open_to_work']) ? 1 : 0, 'acc' => $accountId]);
        } catch (Throwable) {
            // best-effort
        }
        header('Location: /seeker-panel.php#profilim');
        exit;
    } elseif ($mode === 'company_answer') {
        $qkey   = (string) ($_POST['question_key'] ?? '');
        $answer = mb_substr(trim((string) ($_POST['answer'] ?? '')), 0, 2000, 'UTF-8');
        if (in_array($qkey, $companyQuestionKeys, true)) {
            try {
                if ($answer === '') {
                    $pdo->prepare('DELETE FROM seeker_question_answers WHERE account_id = :acc AND question_key = :q')
                        ->execute(['acc' => $accountId, 'q' => $qkey]);
                } else {
                    $pdo->prepare(
                        'INSERT INTO seeker_question_answers (account_id, question_key, answer)
                         VALUES (:acc, :q, :a)
                         ON DUPLICATE KEY UPDATE answer = VALUES(answer)'
                    )->execute(['acc' => $accountId, 'q' => $qkey, 'a' => $answer]);
                }
            } catch (Throwable) {
                // best-effort (table may not be migrated yet)
            }
        }
        header('Location: /seeker-panel.php#sorularim');
        exit;
    }
}

/* ---- derive view state ----------------------------------------------- */
$fullName = trim((string) ($seekerRow['full_name'] ?? '')) ?: 'Aday';
$completed = (int) ($seekerRow['profile_completed'] ?? 0) === 1;
$editMode  = isset($_GET['duzenle']);

// Profile strength — REAL, computed from filled fields. Drives the meter and the
// "complete your profile" nudges, so the score is never faked.
$pfWeights = [
    'headline' => 10, 'city' => 8, 'experience_level' => 10, 'education_level' => 10,
    'department' => 10, 'skills' => 12, 'about' => 12, 'work_pref' => 6,
    'position_level' => 4, 'employment_type' => 4, 'sector' => 4, 'languages' => 3,
    'district' => 2, 'salary_expectation' => 3, 'linkedin' => 2, 'website' => 1, 'phone' => 1,
];
$pfLabels = [
    'headline' => 'Başlık', 'city' => 'Şehir', 'experience_level' => 'Deneyim',
    'education_level' => 'Eğitim', 'department' => 'Alan', 'skills' => 'Beceriler',
    'about' => 'Hakkında', 'work_pref' => 'Çalışma tercihi', 'position_level' => 'Pozisyon seviyesi',
    'employment_type' => 'Çalışma şekli', 'sector' => 'Sektör', 'languages' => 'Diller',
    'salary_expectation' => 'Maaş beklentisi', 'linkedin' => 'LinkedIn',
];
$pfScore = 0;
$pfMissing = [];
foreach ($pfWeights as $k => $w) {
    if (trim((string) ($seekerRow[$k] ?? '')) !== '') {
        $pfScore += $w;
    } elseif (isset($pfLabels[$k])) {
        $pfMissing[] = $pfLabels[$k];
    }
}
$pfScore = min(100, $pfScore);

$nameWords  = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
$pfInitials = mb_strtoupper(mb_substr((string) $nameWords[0], 0, 1, 'UTF-8') . (isset($nameWords[1]) ? mb_substr((string) $nameWords[1], 0, 1, 'UTF-8') : ''), 'UTF-8') ?: '?';

try {
    $isVerified = refresh_verification_flag($pdo);
} catch (Throwable) {
    $isVerified = (int) ($_SESSION['account']['is_verified'] ?? 0) === 1;
}

// helper: render <option> list
$opts = static function (array $list, string $current): string {
    $out = '<option value="">Seçiniz…</option>';
    foreach ($list as $v) {
        $sel = ($v === $current) ? ' selected' : '';
        $out .= '<option value="' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '"' . $sel . '>'
              . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '</option>';
    }
    return $out;
};

$g = static fn (string $k): string => (string) ($seekerRow[$k] ?? '');
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

/* ---- collections: viewed / saved / applied listings ------------------ */
// All three share the same card shape. Best-effort: missing tables → empty.
$loadListings = static function (PDO $pdo, string $sql, int $acc): array {
    try {
        $st = $pdo->prepare($sql);
        $st->execute(['acc' => $acc]);
        return $st->fetchAll() ?: [];
    } catch (Throwable) {
        return [];
    }
};
$viewedRows = $loadListings($pdo,
    'SELECT jl.id, jl.title, jl.work_model, jl.location, jl.salary_min, jl.salary_max,
            jl.employment_type, e.company_name, e.sector, MAX(v.viewed_at) AS ts
       FROM listing_views v
       JOIN job_listings jl ON jl.id = v.listing_id
       JOIN employers e ON e.id = jl.employer_id
      WHERE v.viewer_account_id = :acc AND jl.is_active = 1
      GROUP BY jl.id
      ORDER BY ts DESC
      LIMIT 100', $accountId);
$savedRows = $loadListings($pdo,
    'SELECT jl.id, jl.title, jl.work_model, jl.location, jl.salary_min, jl.salary_max,
            jl.employment_type, e.company_name, e.sector, s.saved_at AS ts
       FROM listing_saves s
       JOIN job_listings jl ON jl.id = s.listing_id
       JOIN employers e ON e.id = jl.employer_id
      WHERE s.seeker_account_id = :acc AND jl.is_active = 1
      ORDER BY s.saved_at DESC
      LIMIT 100', $accountId);
$appliedRows = $loadListings($pdo,
    'SELECT jl.id, jl.title, jl.work_model, jl.location, jl.salary_min, jl.salary_max,
            jl.employment_type, e.company_name, e.sector, a.submitted_at AS ts, a.status
       FROM listing_applications a
       JOIN job_listings jl ON jl.id = a.listing_id
       JOIN employers e ON e.id = jl.employer_id
      WHERE a.seeker_account_id = :acc AND jl.is_active = 1
      ORDER BY a.submitted_at DESC
      LIMIT 100', $accountId);

/* ---- company question answers ---------------------------------------- */
$qAnswers = [];
try {
    $st = $pdo->prepare('SELECT question_key, answer FROM seeker_question_answers WHERE account_id = :acc');
    $st->execute(['acc' => $accountId]);
    foreach ($st->fetchAll() as $r) { $qAnswers[(string) $r['question_key']] = (string) $r['answer']; }
} catch (Throwable) {
    $qAnswers = [];
}

/* ---- portfolio media (avatar / image / video / doc) ------------------ */
$mediaBy = ['avatar' => [], 'image' => [], 'video' => [], 'doc' => []];
try {
    $st = $pdo->prepare('SELECT id, kind, file_path, original_name, mime FROM seeker_media WHERE account_id = :a ORDER BY sort_order, id');
    $st->execute(['a' => $accountId]);
    foreach ($st->fetchAll() as $m) {
        $k = (string) $m['kind'];
        if (isset($mediaBy[$k])) { $mediaBy[$k][] = $m; }
    }
} catch (Throwable) {
    // tablo henüz yoksa boş geç
}
$avatarMedia = $mediaBy['avatar'][0] ?? null;

/* ---- status label for applications ----------------------------------- */
$statusLabels = [
    'submitted' => 'Gönderildi', 'reviewed' => 'İncelendi', 'shortlisted' => 'Ön elemede',
    'interview' => 'Görüşme', 'offered' => 'Teklif', 'rejected' => 'Olumsuz', 'withdrawn' => 'Geri çekildi',
];

/* ---- collection section renderer (header + filter bar + grid/empty) --- */
$renderCollection = static function (string $key, string $title, string $desc, string $searchPh, array $rows, array $empty, bool $isApplied = false) use ($h, $statusLabels): void {
    // distinct filter options from the loaded rows
    $locs = $models = $sectors = [];
    foreach ($rows as $r) {
        if (($v = trim((string) ($r['location'] ?? ''))) !== '') $locs[$v] = 1;
        if (($v = trim((string) ($r['work_model'] ?? ''))) !== '') $models[$v] = 1;
        if (($v = trim((string) ($r['sector'] ?? ''))) !== '') $sectors[$v] = 1;
    }
    ksort($locs); ksort($models); ksort($sectors);
    ?>
    <section class="sk-panel" data-panel="<?= $h($key) ?>" hidden>
      <div class="sk-panel-head">
        <h2 class="sk-panel-title"><?= $h($title) ?></h2>
        <p class="sk-panel-desc"><?= $h($desc) ?></p>
      </div>

      <div class="sk-coll" data-coll>
        <div class="sk-coll-bar">
          <label class="sk-coll-search">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.8"/><path d="M20 20l-3.2-3.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            <input type="search" placeholder="<?= $h($searchPh) ?>" data-coll-q>
          </label>
          <div class="sk-coll-filters">
            <select class="sk-coll-filter" data-coll-filter="loc"><option value="">Konum</option><?php foreach (array_keys($locs) as $v): ?><option value="<?= $h($v) ?>"><?= $h($v) ?></option><?php endforeach; ?></select>
            <select class="sk-coll-filter" data-coll-filter="model"><option value="">Çalışma Tercihi</option><?php foreach (array_keys($models) as $v): ?><option value="<?= $h($v) ?>"><?= $h($v) ?></option><?php endforeach; ?></select>
            <select class="sk-coll-filter" data-coll-filter="sector"><option value="">Sektör</option><?php foreach (array_keys($sectors) as $v): ?><option value="<?= $h($v) ?>"><?= $h($v) ?></option><?php endforeach; ?></select>
            <select class="sk-coll-filter sk-coll-filter--sort" data-coll-sort><option value="new">Sıralama: Yeni</option><option value="old">Eski</option><option value="salary">Maaş (yüksek)</option><option value="company">Şirket (A-Z)</option></select>
          </div>
        </div>

        <?php if ($rows === []): ?>
          <div class="sk-empty">
            <div class="sk-empty-art" aria-hidden="true"><?= $empty['art'] ?? '' ?></div>
            <h3 class="sk-empty-title"><?= $h($empty['title']) ?></h3>
            <p class="sk-empty-text"><?= $h($empty['text']) ?></p>
            <a class="sk-btn sk-btn--solid" href="<?= $h($empty['cta_href']) ?>"><?= $h($empty['cta']) ?></a>
          </div>
        <?php else: ?>
          <div class="sk-coll-grid" data-coll-grid>
            <?php foreach ($rows as $r):
              $smin = (int) ($r['salary_min'] ?? 0); $smax = (int) ($r['salary_max'] ?? 0);
              $salary = '';
              if ($smin && $smax) $salary = number_format($smin, 0, ',', '.') . ' – ' . number_format($smax, 0, ',', '.') . ' ₺';
              elseif ($smin) $salary = number_format($smin, 0, ',', '.') . ' ₺+';
              elseif ($smax) $salary = number_format($smax, 0, ',', '.') . ' ₺';
              $sortSalary = $smax ?: $smin;
            ?>
              <a class="sk-jobcard" href="/akis.php?id=<?= (int) $r['id'] ?>"
                 data-card
                 data-title="<?= $h(mb_strtolower(($r['title'] ?? '') . ' ' . ($r['company_name'] ?? ''), 'UTF-8')) ?>"
                 data-loc="<?= $h($r['location'] ?? '') ?>"
                 data-model="<?= $h($r['work_model'] ?? '') ?>"
                 data-sector="<?= $h($r['sector'] ?? '') ?>"
                 data-date="<?= $h((string) ($r['ts'] ?? '')) ?>"
                 data-salary="<?= (int) $sortSalary ?>"
                 data-company="<?= $h(mb_strtolower((string) ($r['company_name'] ?? ''), 'UTF-8')) ?>">
                <div class="sk-jobcard-top">
                  <span class="sk-jobcard-logo" aria-hidden="true"><?= $h(mb_strtoupper(mb_substr((string) ($r['company_name'] ?? '?'), 0, 1, 'UTF-8'), 'UTF-8')) ?></span>
                  <div class="sk-jobcard-id">
                    <p class="sk-jobcard-title"><?= $h($r['title'] ?? '') ?></p>
                    <p class="sk-jobcard-company"><?= $h($r['company_name'] ?? '') ?><?php if (($r['sector'] ?? '') !== ''): ?> · <?= $h($r['sector']) ?><?php endif; ?></p>
                  </div>
                  <?php if ($isApplied): $stt = (string) ($r['status'] ?? 'submitted'); ?>
                    <span class="sk-jobcard-status sk-status--<?= $h($stt) ?>"><?= $h($statusLabels[$stt] ?? 'Gönderildi') ?></span>
                  <?php endif; ?>
                </div>
                <div class="sk-jobcard-meta">
                  <?php if (($r['location'] ?? '') !== ''): ?><span class="sk-jobcard-chip"><?= $h($r['location']) ?></span><?php endif; ?>
                  <?php if (($r['work_model'] ?? '') !== ''): ?><span class="sk-jobcard-chip"><?= $h($r['work_model']) ?></span><?php endif; ?>
                  <?php if (($r['employment_type'] ?? '') !== ''): ?><span class="sk-jobcard-chip"><?= $h($r['employment_type']) ?></span><?php endif; ?>
                  <?php if ($salary !== ''): ?><span class="sk-jobcard-salary"><?= $h($salary) ?></span><?php endif; ?>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
          <p class="sk-coll-nomatch" hidden>Aramana uyan sonuç yok.</p>
        <?php endif; ?>
      </div>
    </section>
    <?php
};
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AFTERWORK | <?= $completed ? 'Profilim' : 'Profilini oluştur' ?></title>
  <link rel="stylesheet" href="/frontend/assets/css/employer/panel.css?v=<?= filemtime(__DIR__ . '/../../assets/css/employer/panel.css') ?>">
  <link rel="stylesheet" href="/frontend/assets/css/seeker/panel.css?v=<?= filemtime(__DIR__ . '/../../assets/css/seeker/panel.css') ?>">
  <link rel="stylesheet" href="/frontend/assets/css/shared/logout-modal.css?v=<?= filemtime(__DIR__ . '/../../assets/css/shared/logout-modal.css') ?>">
  <link rel="stylesheet" href="/frontend/assets/css/shared/verify-banner.css?v=<?= filemtime(__DIR__ . '/../../assets/css/shared/verify-banner.css') ?>">
  <link rel="stylesheet" href="/frontend/assets/css/shared/tour.css?v=<?= filemtime(__DIR__ . '/../../assets/css/shared/tour.css') ?>">
</head>
<?php if (!$completed): ?>
<!-- ░░ ONBOARDING ░░ : blurred panel behind, 4-step wizard on top ░░ -->
<body class="sk-ob-body">
  <div class="sk-ob-bg" aria-hidden="true">
    <div class="ep-page">
      <?php $activeTab = 'profile'; include __DIR__ . '/../../partials/employer-topbar.php'; ?>
      <div class="sk-wrap">
        <section class="sk-hero">
          <p class="sk-kicker">Profilim</p>
          <h1>Hoş geldin, <?= $h($fullName) ?>.</h1>
          <p class="sk-about">Profilin tamamlandığında özetin, becerilerin ve mesajların burada olacak.</p>
        </section>
        <div class="sk-grid">
          <div class="sk-skel"></div>
          <div class="sk-skel"></div>
          <div class="sk-skel sk-skel--wide"></div>
        </div>
      </div>
    </div>
  </div>

  <main class="sk-ob" aria-label="Profil oluşturma">
    <div class="sk-ob-card">
      <p class="sk-ob-kicker">Profilini oluştur</p>
      <h1 class="sk-ob-title">Birkaç soruda seni tanıyalım</h1>
      <p class="sk-ob-sub">Bu 4 adımı doldur, profilin açılsın. Her kutuyu doldurup sağ alttaki butonla ilerle.</p>

      <?php if ($errors !== []): ?>
        <div class="sk-ob-error"><?= $h(implode(' ', $errors)) ?></div>
      <?php endif; ?>

      <form id="sk-ob-form" method="post" action="/seeker-panel.php" novalidate>
        <input type="hidden" name="mode" value="onboarding">

        <div class="sk-ob-viewport">
          <div class="sk-ob-track" id="sk-ob-track">

            <!-- 1 · Alan / bölüm — en kritik eşleşme verisi -->
            <section class="sk-ob-step" data-step="0">
              <p class="sk-ob-q">1 · Hangi alandasın?</p>
              <p class="sk-ob-hint">Bu, iş verenlerin seni bulup eşleşmesi için en önemli bilgi.</p>
              <label class="sk-field">
                <span>Alan / bölüm</span>
                <select name="department" data-ob-required><?= $opts($tax['departments'], $g('department')) ?></select>
              </label>
              <label class="sk-field">
                <span>Ünvan / başlık</span>
                <input type="text" name="headline" value="<?= $h($g('headline')) ?>" placeholder="Örn. Frontend Geliştirici" data-ob-required maxlength="120">
              </label>
              <button type="button" class="sk-ob-next" data-ob-next aria-label="İleri">İleri →</button>
            </section>

            <!-- 2 · Deneyim & eğitim -->
            <section class="sk-ob-step" data-step="1">
              <p class="sk-ob-q">2 · Deneyim &amp; eğitim</p>
              <p class="sk-ob-hint">İlanlar çoğunlukla bu ikisine göre filtreleniyor.</p>
              <label class="sk-field">
                <span>Deneyim seviyesi</span>
                <select name="experience_level" data-ob-required><?= $opts($tax['experience_levels'], $g('experience_level')) ?></select>
              </label>
              <label class="sk-field">
                <span>Eğitim seviyesi</span>
                <select name="education_level" data-ob-required><?= $opts($tax['education_levels'], $g('education_level')) ?></select>
              </label>
              <button type="button" class="sk-ob-next" data-ob-next aria-label="İleri">İleri →</button>
            </section>

            <!-- 3 · Konum & beceriler -->
            <section class="sk-ob-step" data-step="2">
              <p class="sk-ob-q">3 · Konum &amp; beceriler</p>
              <p class="sk-ob-hint">Şehir ve beceriler, doğru ilanla eşleşmeni sağlar.</p>
              <label class="sk-field">
                <span>Şehir</span>
                <input type="text" name="city" value="<?= $h($g('city')) ?>" placeholder="Örn. İstanbul" data-ob-required maxlength="80">
              </label>
              <label class="sk-field">
                <span>Beceriler <small>(virgülle ayır)</small></span>
                <input type="text" name="skills" value="<?= $h($g('skills')) ?>" placeholder="Örn. React, TypeScript, Figma" data-ob-required maxlength="500">
              </label>
              <button type="button" class="sk-ob-next" data-ob-next aria-label="İleri">İleri →</button>
            </section>

            <!-- 4 · Tercih + kısa tanıtım -->
            <section class="sk-ob-step" data-step="3">
              <p class="sk-ob-q">4 · Çalışma tercihin</p>
              <p class="sk-ob-hint">Son bir adım — istersen kısa bir not da bırak.</p>
              <label class="sk-field">
                <span>Çalışma tercihi</span>
                <select name="work_pref"><?= $opts($tax['work_models'], $g('work_pref')) ?></select>
              </label>
              <label class="sk-field">
                <span>Hakkında <small>(opsiyonel)</small></span>
                <textarea name="about" rows="3" placeholder="Seni öne çıkaran birkaç cümle…" maxlength="4000"><?= $h($g('about')) ?></textarea>
              </label>
              <label class="sk-check">
                <input type="checkbox" name="open_to_work" <?= (int) ($seekerRow['open_to_work'] ?? 1) === 1 ? 'checked' : '' ?>>
                <span>Yeni iş fırsatlarına açığım</span>
              </label>
              <button type="submit" class="sk-ob-next sk-ob-finish">Profilimi oluştur ✓</button>
            </section>

          </div>
        </div>

        <div class="sk-ob-dots" id="sk-ob-dots" aria-hidden="true">
          <span class="sk-ob-dot is-active"></span>
          <span class="sk-ob-dot"></span>
          <span class="sk-ob-dot"></span>
          <span class="sk-ob-dot"></span>
        </div>
      </form>
    </div>
  </main>

  <?php include __DIR__ . '/_logout-modal.php'; ?>
  <script src="/frontend/assets/js/shared/logout-modal.js?v=<?= filemtime(__DIR__ . '/../../assets/js/shared/logout-modal.js') ?>" defer></script>
  <script src="/frontend/assets/js/seeker/onboarding.js?v=<?= filemtime(__DIR__ . '/../../assets/js/seeker/onboarding.js') ?>" defer></script>
</body>

<?php else: ?>
<!-- ░░ PROFILIM (completed) ░░ -->
<body>
  <div class="ep-page">
    <?php $activeTab = 'profile'; include __DIR__ . '/../../partials/employer-topbar.php'; ?>
    <div class="sk-wrap">

    <?php if (!$isVerified): ?>
    <div class="verify-banner" role="region" aria-label="E-posta doğrulama">
      <span class="verify-banner__icon" aria-hidden="true">!</span>
      <p class="verify-banner__text">
        <strong>E-posta adresini doğrula.</strong>
        İlanlara başvurabilmek için <?= $h($email) ?> adresine gönderdiğimiz bağlantıya tıkla.
      </p>
      <div class="verify-banner__actions">
        <form action="/resend-verification.php" method="post" style="margin:0;">
          <button type="submit" class="verify-banner__btn verify-banner__btn--solid">Yeniden gönder</button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($flash !== null): ?>
      <div class="sk-flash"><?= $h($flash) ?></div>
    <?php endif; ?>
    <?php if ($errors !== []): ?>
      <div class="sk-flash sk-flash--error"><?= $h(implode(' ', $errors)) ?></div>
    <?php endif; ?>

      <?php
      // ── display helpers ──
      $skills = array_values(array_filter(array_map('trim', preg_split('/[,;\/]+/u', $g('skills')) ?: [])));
      $contacts = [];
      if ($g('phone') !== '')    $contacts[] = ['Telefon', $g('phone'), null];
      if ($g('linkedin') !== '') $contacts[] = ['LinkedIn', $g('linkedin'), (str_starts_with($g('linkedin'), 'http') ? $g('linkedin') : 'https://' . $g('linkedin'))];
      if ($g('website') !== '')  $contacts[] = ['Web', $g('website'), (str_starts_with($g('website'), 'http') ? $g('website') : 'https://' . $g('website'))];
      $salaryDisp = $g('salary_expectation') !== '' ? number_format((int) $g('salary_expectation'), 0, ',', '.') . ' ₺+' : '';
      $details = [
        'Deneyim'         => $g('experience_level'),
        'Eğitim'          => $g('education_level'),
        'Alan'            => $g('department'),
        'Pozisyon'        => $g('position_level'),
        'Çalışma şekli'   => $g('employment_type'),
        'Çalışma tercihi' => $g('work_pref'),
        'Sektör'          => $g('sector'),
        'Diller'          => $g('languages'),
        'Şehir'           => $g('city'),
        'İlçe'            => $g('district'),
        'Maaş beklentisi' => $salaryDisp,
      ];
      if ((int) ($seekerRow['is_disability'] ?? 0) === 1) { $details['Çalışma durumu'] = 'Engelli birey'; }
      $pencil = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 20h4L18 10l-4-4L4 16v4z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M13.5 6.5l4 4" stroke="currentColor" stroke-width="1.6"/></svg>';
      $navList = [
        ['profilim',       'Vitrinim',        null,                   '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3.4" stroke="currentColor" stroke-width="1.7"/><path d="M5 19.5c0-3.6 3.1-5.5 7-5.5s7 1.9 7 5.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>'],
        ['incelediklerim', 'Göz Attıklarım',  count($viewedRows),     '<svg viewBox="0 0 24 24" fill="none"><path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z" stroke="currentColor" stroke-width="1.7"/><circle cx="12" cy="12" r="2.6" stroke="currentColor" stroke-width="1.7"/></svg>'],
        ['kaydettiklerim', 'Cebimdekiler',    count($savedRows),      '<svg viewBox="0 0 24 24" fill="none"><path d="M6 4.5h12v15l-6-4-6 4z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>'],
        ['basvurularim',   'Gönderdiklerim',  count($appliedRows),    '<svg viewBox="0 0 24 24" fill="none"><path d="M4 12l16-7-7 16-2.5-6.5L4 12z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>'],
        ['alarmlarim',     'Radarım',         null,                   '<svg viewBox="0 0 24 24" fill="none"><path d="M6 9a6 6 0 1112 0c0 5 2 6 2 6H4s2-1 2-6z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M10 19a2 2 0 004 0" stroke="currentColor" stroke-width="1.7"/></svg>'],
        ['tercihlerim',    'Pusulam',         null,                   '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.7"/><path d="M15.5 8.5l-2 5-5 2 2-5 5-2z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/></svg>'],
        ['sorularim',      'Hazır Yanıtlarım', null,                  '<svg viewBox="0 0 24 24" fill="none"><path d="M4 5.5h16v11H9l-4 3v-3H4z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M9 9.5h6M9 12.5h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>'],
      ];
      $otwOn = (int) ($seekerRow['open_to_work'] ?? 1) === 1;
      ?>

      <div class="sk-dash" data-dash>
        <aside class="sk-nav" aria-label="Kariyer paneli" data-tour="sk-nav">
          <?php foreach ($navList as $i => [$nkey, $nlabel, $nbadge, $nicon]): ?>
            <button type="button" class="sk-nav-item<?= $i === 0 ? ' is-active' : '' ?>" data-nav="<?= $h($nkey) ?>">
              <span class="sk-nav-ic" aria-hidden="true"><?= $nicon ?></span>
              <span class="sk-nav-label"><?= $h($nlabel) ?></span>
              <?php if ($nbadge !== null && $nbadge > 0): ?><span class="sk-nav-badge"><?= (int) $nbadge ?></span><?php endif; ?>
            </button>
          <?php endforeach; ?>
        </aside>

        <div class="sk-panels">

        <!-- ░░ PANEL: PROFİLİM ░░ -->
        <section class="sk-panel is-active" data-panel="profilim">

          <!-- İş fırsatlarına açığım -->
          <form class="sk-otw<?= $otwOn ? ' is-on' : '' ?>" method="post" action="/seeker-panel.php" data-otw-form data-tour="otw">
            <input type="hidden" name="mode" value="toggle_otw">
            <div class="sk-otw-text">
              <strong>Yeni fırsatlara açığım</strong>
              <span>Açıkken profilin işverenlerin aday aramalarında öne çıkar. <em>Görünürlüğü dilediğin an kapatabilirsin.</em></span>
            </div>
            <label class="sk-switch">
              <input type="checkbox" name="open_to_work" <?= $otwOn ? 'checked' : '' ?> data-otw-toggle aria-label="İş fırsatlarına açığım">
              <span class="sk-switch-track"><span class="sk-switch-thumb"></span></span>
            </label>
          </form>

      <!-- ░ HEADER ░ -->
      <article class="sk-profile" id="sec-header" data-section>
        <div class="sk-cover" aria-hidden="true"></div>
        <div class="sk-profile-body">
          <div class="sk-avatar-xl" aria-hidden="true"><?= $h($pfInitials) ?></div>

          <div class="sk-sec-view">
            <div class="sk-profile-top">
              <div class="sk-profile-id">
                <h1 class="sk-profile-name"><?= $h($fullName) ?></h1>
                <?php if ($g('headline') !== ''): ?>
                  <p class="sk-profile-headline"><?= $h($g('headline')) ?></p>
                <?php else: ?>
                  <p class="sk-profile-headline sk-profile-headline--empty">Başlık ekle — örn. "Frontend Geliştirici"</p>
                <?php endif; ?>
                <p class="sk-profile-loc">
                  <?php $locbits = array_filter([$g('city'), $g('department')]);
                  echo $locbits ? $h(implode(' · ', $locbits)) : '<span class="sk-muted">Konum &amp; alan ekle</span>'; ?>
                </p>
                <div class="sk-profile-pills">
                  <?php if ((int) ($seekerRow['open_to_work'] ?? 1) === 1): ?><span class="sk-pill sk-pill--open">İşe açık</span><?php endif; ?>
                  <?php if ($g('work_pref') !== ''): ?><span class="sk-pill"><?= $h($g('work_pref')) ?></span><?php endif; ?>
                  <?php if ($g('experience_level') !== ''): ?><span class="sk-pill"><?= $h($g('experience_level')) ?></span><?php endif; ?>
                </div>
              </div>
              <div class="sk-profile-actions">
                <button type="button" class="sk-icon-edit" data-edit-toggle aria-label="Düzenle"><?= $pencil ?></button>
              </div>
            </div>
          </div>

          <form class="sk-sec-edit" method="post" action="/seeker-panel.php#sec-header" hidden>
            <input type="hidden" name="mode" value="section"><input type="hidden" name="section" value="header">
            <div class="sk-form-grid">
              <label class="sk-field"><span>Başlık</span><input type="text" name="headline" value="<?= $h($g('headline')) ?>" maxlength="120" placeholder="Örn. Frontend Geliştirici"></label>
              <label class="sk-field"><span>Şehir</span><input type="text" name="city" value="<?= $h($g('city')) ?>" maxlength="80"></label>
              <label class="sk-check sk-field--wide"><input type="checkbox" name="open_to_work" <?= (int) ($seekerRow['open_to_work'] ?? 1) === 1 ? 'checked' : '' ?>><span>Yeni iş fırsatlarına açığım</span></label>
            </div>
            <div class="sk-sec-foot"><button type="submit" class="sk-btn sk-btn--solid">Kaydet</button><button type="button" class="sk-btn sk-btn--ghost" data-edit-cancel>Vazgeç</button></div>
          </form>
        </div>
      </article>

      <?php if ($pfScore < 100): ?>
        <section class="sk-strength">
          <div class="sk-strength-head">
            <p class="sk-card-kicker">Profil gücü</p>
            <strong class="sk-strength-pct"><?= (int) $pfScore ?>%</strong>
          </div>
          <div class="sk-strength-bar"><span style="width: <?= (int) $pfScore ?>%;"></span></div>
          <?php if ($pfMissing !== []): ?>
            <p class="sk-strength-hint">Eksik: <strong><?= $h(implode(' · ', array_slice($pfMissing, 0, 5))) ?></strong> — aşağıdaki bölümlerden "+ ekle" ile doldur.</p>
          <?php endif; ?>
        </section>
      <?php else: ?>
        <section class="sk-strength sk-strength--full">
          <span class="sk-strength-check" aria-hidden="true">✓</span>
          <p class="sk-strength-full-text">Profilin tam dolu — iş verenlerin radarındasın.</p>
        </section>
      <?php endif; ?>

      <?php
        // ── "Profilini geliştir" — empty sections grouped by tier ──
        $langs = array_values(array_filter(array_map('trim', preg_split('/[,;\/]+/u', $g('languages')) ?: [])));
        $eduEmpty    = $g('education_level') === '' && $g('school') === '';
        $careerMeta  = [
          'Alan' => $g('department'), 'Pozisyon' => $g('position_level'),
          'Çalışma şekli' => $g('employment_type'), 'Çalışma tercihi' => $g('work_pref'),
          'Sektör' => $g('sector'), 'İlçe' => $g('district'), 'Maaş beklentisi' => $salaryDisp,
        ];
        if ((int) ($seekerRow['is_disability'] ?? 0) === 1) { $careerMeta['Çalışma durumu'] = 'Engelli birey'; }
        $careerHas = (bool) array_filter($careerMeta);

        $addItems = ['core' => [], 'rec' => [], 'add' => []];
        if ($g('about') === '')            $addItems['core'][] = ['Hakkında', 'about'];
        if ($g('experience_level') === '') $addItems['core'][] = ['Deneyim', 'experience'];
        if ($eduEmpty)                     $addItems['core'][] = ['Eğitim', 'education'];
        if ($skills === [])                $addItems['core'][] = ['Beceriler', 'skills'];
        if (!$careerHas)                   $addItems['rec'][]  = ['Kariyer tercihleri', 'career'];
        if ($contacts === [])              $addItems['rec'][]  = ['İletişim & bağlantı', 'contact'];
        if ($langs === [])                 $addItems['add'][]  = ['Diller', 'languages'];
      ?>

      <?php if ($addItems['core'] || $addItems['rec'] || $addItems['add']): ?>
      <section class="sk-improve">
        <p class="sk-card-kicker">Profilini geliştir</p>
        <p class="sk-improve-lead">Eksik bölümleri doldur — iş verenlerin seni bulması ve sana güvenmesi kolaylaşır.</p>
        <?php
        $tiers = [
          ['core', 'Önemli', 'Profil görünürlüğün için temel.'],
          ['rec',  'Önerilen', 'Güven ve daha fazla fırsat.'],
          ['add',  'Ek', 'Profiline kişilik kat.'],
        ];
        foreach ($tiers as [$tk, $tl, $td]): if (!$addItems[$tk]) continue; ?>
          <div class="sk-tier sk-tier--<?= $tk ?>">
            <div class="sk-tier-head"><span class="sk-tier-dot" aria-hidden="true"></span><strong><?= $tl ?></strong><span class="sk-tier-desc"><?= $td ?></span></div>
            <div class="sk-tier-items">
              <?php foreach ($addItems[$tk] as [$il, $iid]): ?>
                <button type="button" class="sk-tier-add" data-open-section="<?= $h($iid) ?>">+ <?= $h($il) ?></button>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </section>
      <?php endif; ?>

      <div class="sk-layout">
        <div class="sk-col">

          <!-- ░ HAKKINDA ░ -->
          <article class="sk-card" id="sec-about" data-section>
            <div class="sk-card-head"><p class="sk-card-kicker">Hakkında</p><?php if ($g('about') !== ''): ?><button type="button" class="sk-edit-link" data-edit-toggle><?= $pencil ?> Düzenle</button><?php endif; ?></div>
            <div class="sk-sec-view">
              <?php if ($g('about') !== ''): ?>
                <p class="sk-about"><?= nl2br($h($g('about'))) ?></p>
              <?php else: ?>
                <button type="button" class="sk-add" data-edit-toggle>+ Kendinden bahset</button>
              <?php endif; ?>
            </div>
            <form class="sk-sec-edit" method="post" action="/seeker-panel.php#sec-about" hidden>
              <input type="hidden" name="mode" value="section"><input type="hidden" name="section" value="about">
              <label class="sk-field"><span>Hakkında</span><textarea name="about" rows="5" maxlength="4000" placeholder="Seni öne çıkaran birkaç cümle — deneyimin, ilgi alanların, hedeflerin…"><?= $h($g('about')) ?></textarea></label>
              <div class="sk-sec-foot"><button type="submit" class="sk-btn sk-btn--solid">Kaydet</button><button type="button" class="sk-btn sk-btn--ghost" data-edit-cancel>Vazgeç</button></div>
            </form>
          </article>

          <!-- ░ DENEYİM ░ -->
          <article class="sk-card" id="sec-experience" data-section>
            <div class="sk-card-head"><p class="sk-card-kicker">Deneyim</p><?php if ($g('experience_level') !== ''): ?><button type="button" class="sk-edit-link" data-edit-toggle><?= $pencil ?> Düzenle</button><?php endif; ?></div>
            <div class="sk-sec-view">
              <?php if ($g('experience_level') !== ''): ?>
                <p class="sk-big-val"><?= $h($g('experience_level')) ?></p>
              <?php else: ?>
                <button type="button" class="sk-add" data-edit-toggle>+ Deneyim seviyesi ekle</button>
              <?php endif; ?>
            </div>
            <form class="sk-sec-edit" method="post" action="/seeker-panel.php#sec-experience" hidden>
              <input type="hidden" name="mode" value="section"><input type="hidden" name="section" value="experience">
              <label class="sk-field"><span>Deneyim seviyesi</span><select name="experience_level"><?= $opts($tax['experience_levels'], $g('experience_level')) ?></select></label>
              <div class="sk-sec-foot"><button type="submit" class="sk-btn sk-btn--solid">Kaydet</button><button type="button" class="sk-btn sk-btn--ghost" data-edit-cancel>Vazgeç</button></div>
            </form>
          </article>

          <!-- ░ EĞİTİM (okul autocomplete) ░ -->
          <article class="sk-card" id="sec-education" data-section>
            <div class="sk-card-head"><p class="sk-card-kicker">Eğitim</p><?php if (!$eduEmpty): ?><button type="button" class="sk-edit-link" data-edit-toggle><?= $pencil ?> Düzenle</button><?php endif; ?></div>
            <div class="sk-sec-view">
              <?php if (!$eduEmpty): ?>
                <?php if ($g('school') !== ''): ?><p class="sk-big-val"><?= $h($g('school')) ?></p><?php endif; ?>
                <?php if ($g('education_level') !== ''): ?><p class="sk-muted"><?= $h($g('education_level')) ?></p><?php endif; ?>
              <?php else: ?>
                <button type="button" class="sk-add" data-edit-toggle>+ Eğitim ekle</button>
              <?php endif; ?>
            </div>
            <form class="sk-sec-edit" method="post" action="/seeker-panel.php#sec-education" hidden autocomplete="off">
              <input type="hidden" name="mode" value="section"><input type="hidden" name="section" value="education">
              <label class="sk-field sk-field--ac">
                <span>Okul <small>(lise / üniversite)</small></span>
                <input type="text" name="school" value="<?= $h($g('school')) ?>" maxlength="160" autocomplete="off" placeholder="Yazmaya başla — Türkiye'deki okullar otomatik çıkar" data-school-ac>
                <div class="sk-ac" hidden></div>
              </label>
              <label class="sk-field"><span>Eğitim seviyesi</span><select name="education_level"><?= $opts($tax['education_levels'], $g('education_level')) ?></select></label>
              <div class="sk-sec-foot"><button type="submit" class="sk-btn sk-btn--solid">Kaydet</button><button type="button" class="sk-btn sk-btn--ghost" data-edit-cancel>Vazgeç</button></div>
            </form>
          </article>

          <!-- ░ BECERİLER ░ -->
          <article class="sk-card" id="sec-skills" data-section>
            <div class="sk-card-head"><p class="sk-card-kicker">Beceriler</p><?php if ($skills !== []): ?><button type="button" class="sk-edit-link" data-edit-toggle><?= $pencil ?> Düzenle</button><?php endif; ?></div>
            <div class="sk-sec-view">
              <?php if ($skills !== []): ?>
                <div class="sk-chips"><?php foreach ($skills as $s): ?><span class="sk-chip"><?= $h($s) ?></span><?php endforeach; ?></div>
              <?php else: ?>
                <button type="button" class="sk-add" data-edit-toggle>+ Beceri ekle</button>
              <?php endif; ?>
            </div>
            <form class="sk-sec-edit" method="post" action="/seeker-panel.php#sec-skills" hidden>
              <input type="hidden" name="mode" value="section"><input type="hidden" name="section" value="skills">
              <label class="sk-field"><span>Beceriler <small>(virgülle ayır)</small></span><input type="text" name="skills" value="<?= $h($g('skills')) ?>" maxlength="500" placeholder="Örn. React, TypeScript, Figma, SQL"></label>
              <div class="sk-sec-foot"><button type="submit" class="sk-btn sk-btn--solid">Kaydet</button><button type="button" class="sk-btn sk-btn--ghost" data-edit-cancel>Vazgeç</button></div>
            </form>
          </article>
        </div>

        <aside class="sk-col">
          <!-- ░ KARİYER TERCİHLERİ ░ -->
          <article class="sk-card" id="sec-career" data-section>
            <div class="sk-card-head"><p class="sk-card-kicker">Kariyer tercihleri</p><?php if ($careerHas): ?><button type="button" class="sk-edit-link" data-edit-toggle><?= $pencil ?> Düzenle</button><?php endif; ?></div>
            <div class="sk-sec-view">
              <?php if ($careerHas): ?>
                <ul class="sk-meta">
                  <?php foreach ($careerMeta as $lab => $val): if ($val === '') continue; ?>
                    <li><span><?= $h($lab) ?></span><strong><?= $h($val) ?></strong></li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <button type="button" class="sk-add" data-edit-toggle>+ Tercih ekle</button>
              <?php endif; ?>
            </div>
            <form class="sk-sec-edit" method="post" action="/seeker-panel.php#sec-career" hidden>
              <input type="hidden" name="mode" value="section"><input type="hidden" name="section" value="career">
              <div class="sk-form-grid">
                <label class="sk-field"><span>Alan / departman</span><select name="department"><?= $opts($tax['departments'], $g('department')) ?></select></label>
                <label class="sk-field"><span>Pozisyon seviyesi</span><select name="position_level"><?= $opts($tax['position_levels'], $g('position_level')) ?></select></label>
                <label class="sk-field"><span>Çalışma şekli</span><select name="employment_type"><?= $opts($tax['employment_types'], $g('employment_type')) ?></select></label>
                <label class="sk-field"><span>Çalışma tercihi</span><select name="work_pref"><?= $opts($tax['work_models'], $g('work_pref')) ?></select></label>
                <label class="sk-field"><span>Sektör</span><select name="sector"><?= $opts($tax['sectors'], $g('sector')) ?></select></label>
                <label class="sk-field"><span>İlçe</span><input type="text" name="district" value="<?= $h($g('district')) ?>" maxlength="80"></label>
                <label class="sk-field"><span>Maaş beklentisi (₺)</span><input type="text" inputmode="numeric" name="salary_expectation" value="<?= $h($g('salary_expectation')) ?>" placeholder="Örn. 60000"></label>
                <label class="sk-check sk-field--wide"><input type="checkbox" name="is_disability" <?= (int) ($seekerRow['is_disability'] ?? 0) === 1 ? 'checked' : '' ?>><span>Engelli birey ilanlarına da bakıyorum</span></label>
              </div>
              <div class="sk-sec-foot"><button type="submit" class="sk-btn sk-btn--solid">Kaydet</button><button type="button" class="sk-btn sk-btn--ghost" data-edit-cancel>Vazgeç</button></div>
            </form>
          </article>

          <!-- ░ DİLLER ░ -->
          <article class="sk-card" id="sec-languages" data-section>
            <div class="sk-card-head"><p class="sk-card-kicker">Diller</p><?php if ($langs !== []): ?><button type="button" class="sk-edit-link" data-edit-toggle><?= $pencil ?> Düzenle</button><?php endif; ?></div>
            <div class="sk-sec-view">
              <?php if ($langs !== []): ?>
                <div class="sk-chips"><?php foreach ($langs as $l): ?><span class="sk-chip"><?= $h($l) ?></span><?php endforeach; ?></div>
              <?php else: ?>
                <button type="button" class="sk-add" data-edit-toggle>+ Dil ekle</button>
              <?php endif; ?>
            </div>
            <form class="sk-sec-edit" method="post" action="/seeker-panel.php#sec-languages" hidden>
              <input type="hidden" name="mode" value="section"><input type="hidden" name="section" value="languages">
              <label class="sk-field"><span>Diller <small>(virgülle ayır)</small></span><input type="text" name="languages" value="<?= $h($g('languages')) ?>" maxlength="160" placeholder="Türkçe, İngilizce, Almanca"></label>
              <div class="sk-sec-foot"><button type="submit" class="sk-btn sk-btn--solid">Kaydet</button><button type="button" class="sk-btn sk-btn--ghost" data-edit-cancel>Vazgeç</button></div>
            </form>
          </article>

          <!-- ░ İLETİŞİM ░ -->
          <article class="sk-card" id="sec-contact" data-section>
            <div class="sk-card-head"><p class="sk-card-kicker">İletişim &amp; bağlantı</p><?php if ($contacts !== []): ?><button type="button" class="sk-edit-link" data-edit-toggle><?= $pencil ?> Düzenle</button><?php endif; ?></div>
            <div class="sk-sec-view">
              <?php if ($contacts !== []): ?>
                <div class="sk-contacts">
                  <?php foreach ($contacts as [$lab, $val, $url]): ?>
                    <?php if ($url !== null): ?><a class="sk-contact" href="<?= $h($url) ?>" target="_blank" rel="noopener"><span><?= $h($lab) ?></span><?= $h($val) ?></a>
                    <?php else: ?><span class="sk-contact"><span><?= $h($lab) ?></span><?= $h($val) ?></span><?php endif; ?>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <button type="button" class="sk-add" data-edit-toggle>+ LinkedIn / web / telefon ekle</button>
              <?php endif; ?>
            </div>
            <form class="sk-sec-edit" method="post" action="/seeker-panel.php#sec-contact" hidden>
              <input type="hidden" name="mode" value="section"><input type="hidden" name="section" value="contact">
              <div class="sk-form-grid">
                <label class="sk-field"><span>LinkedIn</span><input type="text" name="linkedin" value="<?= $h($g('linkedin')) ?>" maxlength="255" placeholder="linkedin.com/in/…"></label>
                <label class="sk-field"><span>Web / portfolyo</span><input type="text" name="website" value="<?= $h($g('website')) ?>" maxlength="255"></label>
                <label class="sk-field sk-field--wide"><span>Telefon</span><input type="text" name="phone" value="<?= $h($g('phone')) ?>" maxlength="32"></label>
              </div>
              <div class="sk-sec-foot"><button type="submit" class="sk-btn sk-btn--solid">Kaydet</button><button type="button" class="sk-btn sk-btn--ghost" data-edit-cancel>Vazgeç</button></div>
            </form>
          </article>
        </aside>
      </div>
          <!-- ░ PORTFÖYÜM ░ -->
          <section class="sk-portfolio" id="portfoy" data-portfolio>
            <div class="sk-pf-head">
              <h2 class="sk-pf-title">Portföyüm</h2>
              <p class="sk-pf-sub">CV’ni, belgelerini, fotoğraf ve videolarını ekle — profilin kişisel sitene dönüşsün.</p>
            </div>

            <label class="sk-pf-drop" data-pf-drop>
              <input type="file" multiple accept="image/*,video/mp4,video/webm,video/quicktime,application/pdf,.doc,.docx" hidden data-pf-input>
              <span class="sk-pf-drop-ic" aria-hidden="true">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none"><path d="M12 16V5m0 0L7.5 9.5M12 5l4.5 4.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 17v2a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
              </span>
              <span class="sk-pf-drop-main">Dosyaları sürükle ya da seç</span>
              <span class="sk-pf-drop-sub">Fotoğraf · video · PDF/Word — galeriden veya bilgisayardan</span>
            </label>
            <p class="sk-pf-msg" data-pf-msg hidden></p>

            <!-- Belgeler & CV -->
            <div class="sk-pf-group" data-pf-group="doc"<?= $mediaBy['doc'] === [] ? ' hidden' : '' ?>>
              <h3 class="sk-pf-gtitle">Belgeler &amp; CV</h3>
              <div class="sk-pf-docs" data-pf-docs>
                <?php foreach ($mediaBy['doc'] as $m): $ext = strtoupper(pathinfo((string) $m['original_name'], PATHINFO_EXTENSION) ?: 'DOC'); ?>
                  <div class="sk-pf-doc" data-media-id="<?= (int) $m['id'] ?>">
                    <a class="sk-pf-doc-link" href="<?= $h($m['file_path']) ?>" target="_blank" rel="noopener">
                      <span class="sk-pf-doc-ic" aria-hidden="true"><?= $h($ext) ?></span>
                      <span class="sk-pf-doc-name"><?= $h($m['original_name']) ?></span>
                    </a>
                    <button type="button" class="sk-pf-del" data-pf-del title="Kaldır">×</button>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Videolar -->
            <div class="sk-pf-group" data-pf-group="video"<?= $mediaBy['video'] === [] ? ' hidden' : '' ?>>
              <h3 class="sk-pf-gtitle">Videolar</h3>
              <div class="sk-pf-videos" data-pf-videos>
                <?php foreach ($mediaBy['video'] as $m): ?>
                  <div class="sk-pf-video" data-media-id="<?= (int) $m['id'] ?>">
                    <video controls preload="metadata" src="<?= $h($m['file_path']) ?>"></video>
                    <button type="button" class="sk-pf-del" data-pf-del title="Kaldır">×</button>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Galeri — üst üste deste + şeffaf ‹ › -->
            <div class="sk-pf-group" data-pf-group="image"<?= $mediaBy['image'] === [] ? ' hidden' : '' ?>>
              <h3 class="sk-pf-gtitle">Galeri</h3>
              <div class="sk-pf-deck" data-pf-deck>
                <button type="button" class="sk-pf-nav sk-pf-nav--prev" data-pf-prev aria-label="Önceki">‹</button>
                <div class="sk-pf-stack" data-pf-stack>
                  <?php foreach ($mediaBy['image'] as $idx => $m): ?>
                    <figure class="sk-pf-slide<?= $idx === 0 ? ' is-active' : '' ?>" data-media-id="<?= (int) $m['id'] ?>">
                      <img src="<?= $h($m['file_path']) ?>" alt="<?= $h($m['original_name']) ?>" loading="lazy">
                      <button type="button" class="sk-pf-del" data-pf-del title="Kaldır">×</button>
                    </figure>
                  <?php endforeach; ?>
                </div>
                <button type="button" class="sk-pf-nav sk-pf-nav--next" data-pf-next aria-label="Sonraki">›</button>
                <span class="sk-pf-count" data-pf-count></span>
              </div>
            </div>
          </section>

        </section><!-- /panel profilim -->

        <?php
        // Bölüme özel çizimler — krem/lacivert, referans görselden bağımsız, kendi setimiz.
        $artViewed = '<svg width="150" height="118" viewBox="0 0 150 118" fill="none"><rect x="34" y="30" width="62" height="46" rx="7" fill="#fff" stroke="#11212d" stroke-width="2.2" transform="rotate(-8 65 53)"/><rect x="46" y="40" width="62" height="46" rx="7" fill="#fff" stroke="#11212d" stroke-width="2.2"/><path d="M55 53h40M55 62h28M55 71h34" stroke="#11212d" stroke-width="2.2" stroke-linecap="round"/><circle cx="113" cy="40" r="8" fill="#d9b583"/></svg>';
        $artSaved = '<svg width="150" height="118" viewBox="0 0 150 118" fill="none"><path d="M54 24h44v68l-22-15-22 15z" fill="#fff" stroke="#11212d" stroke-width="2.4" stroke-linejoin="round"/><path d="M76 40l5 11 12 1-9 8 3 12-11-6-11 6 3-12-9-8 12-1z" fill="#d9b583" stroke="#11212d" stroke-width="1.4" stroke-linejoin="round"/></svg>';
        $artApplied = '<svg width="158" height="118" viewBox="0 0 158 118" fill="none"><path d="M28 58L126 26 98 100 76 76 28 58z" fill="#fff" stroke="#11212d" stroke-width="2.4" stroke-linejoin="round"/><path d="M76 76l50-50" stroke="#11212d" stroke-width="2.2" stroke-linecap="round"/><path d="M76 76l-2 22 14-12" stroke="#11212d" stroke-width="2.2" stroke-linejoin="round" fill="#d9b58355"/></svg>';

        $renderCollection('incelediklerim', 'Göz Attıklarım',
          'Akışta açıp incelediğin ilanlar burada birikir; arayıp filtreleyerek baktıklarına saniyeler içinde geri dönersin.',
          'Göz attıklarımda ara', $viewedRows,
          ['title' => 'Daha hiç ilana göz atmadın', 'text' => 'Akışta dolaştıkça açtığın ilanlar burada toplanmaya başlar. Hadi keşfe çık.', 'cta' => 'Akışı keşfet', 'cta_href' => '/akis.php', 'art' => $artViewed]);

        $renderCollection('kaydettiklerim', 'Cebimdekiler',
          'Şimdi başvurmaya vaktin yoksa ilanı cebine at; aramakla uğraşmadan istediğin an buradan devam edersin.',
          'Cebimdekilerde ara', $savedRows,
          ['title' => 'Cebinde ilan yok', 'text' => 'Bir ilanı beğendiğinde cebine at, kaybolmasın. Hepsi seni burada bekler.', 'cta' => 'Akışı keşfet', 'cta_href' => '/akis.php', 'art' => $artSaved]);

        $renderCollection('basvurularim', 'Gönderdiklerim',
          'Gönderdiğin başvuruların tamamını tek yerden izle; her birinin durumunu gör, fikrin değişirse geri çek.',
          'Gönderdiklerimde ara', $appliedRows,
          ['title' => 'Henüz bir başvurun yok', 'text' => 'Sana uygun pozisyonu bulup ilk başvurunu gönder; geçmişin buradan birikecek.', 'cta' => 'Akışı keşfet', 'cta_href' => '/akis.php', 'art' => $artApplied], true);
        ?>

        <!-- ░░ PANEL: İŞ ALARMLARIM ░░ -->
        <section class="sk-panel" data-panel="alarmlarim" hidden>
          <div class="sk-panel-head">
            <h2 class="sk-panel-title">Radarım</h2>
          </div>
          <div class="sk-empty sk-empty--card">
            <div class="sk-empty-art" aria-hidden="true">
              <svg width="150" height="120" viewBox="0 0 150 120" fill="none"><path d="M56 80c0-7 5-10 5-22a14 14 0 0128 0c0 12 5 15 5 22z" fill="#fff" stroke="#11212d" stroke-width="2.4" stroke-linejoin="round"/><path d="M68 86a7 7 0 0014 0" stroke="#11212d" stroke-width="2.4" stroke-linecap="round"/><path d="M75 36v-7" stroke="#11212d" stroke-width="2.4" stroke-linecap="round"/><path d="M104 44c5 5 8 11 8 18M112 36c7 7 11 16 11 26" stroke="#d9b583" stroke-width="2.4" stroke-linecap="round"/></svg>
            </div>
            <h3 class="sk-empty-title">Tanımlı bir alarmın yok</h3>
            <p class="sk-empty-text">İlgilendiğin kriterleri seç; aradığın koşullara uyan bir ilan yayınlandığında ilk senin haberin olsun.</p>
            <a class="sk-btn sk-btn--ghost" href="/akis.php">Akıştan alarm oluştur</a>
          </div>
        </section>

        <!-- ░░ PANEL: İŞ TERCİHLERİM ░░ -->
        <section class="sk-panel" data-panel="tercihlerim" hidden>
          <div class="sk-panel-head">
            <h2 class="sk-panel-title">Pusulam</h2>
          </div>
          <div class="sk-empty sk-empty--card">
            <div class="sk-empty-art" aria-hidden="true">
              <svg width="150" height="120" viewBox="0 0 150 120" fill="none"><path d="M42 44h66M42 60h66M42 76h66" stroke="#11212d" stroke-width="2.4" stroke-linecap="round"/><circle cx="66" cy="44" r="8" fill="#d9b583" stroke="#11212d" stroke-width="2.2"/><circle cx="92" cy="60" r="8" fill="#fff" stroke="#11212d" stroke-width="2.2"/><circle cx="58" cy="76" r="8" fill="#d9b583" stroke="#11212d" stroke-width="2.2"/></svg>
            </div>
            <h3 class="sk-empty-title">Önerileri sana göre ayarlayalım</h3>
            <p class="sk-empty-text">Aradığın işi birkaç başlıkta tarif et; akışını ve önerilerimizi tercihlerine göre şekillendirelim.</p>
            <button type="button" class="sk-btn sk-btn--solid" data-goto-section="career">Tercihleri belirle</button>
          </div>
        </section>

        <!-- ░░ PANEL: ŞİRKET SORULARIM ░░ -->
        <section class="sk-panel" data-panel="sorularim" hidden>
          <div class="sk-panel-head">
            <h2 class="sk-panel-title">Hazır Yanıtlarım</h2>
            <p class="sk-panel-desc">İşverenlerin sıkça sorduğu soruları önceden yanıtla. Verdiğin cevaplar yeni başvurularında otomatik gelir; her seferinde baştan yazmana gerek kalmaz.</p>
          </div>
          <?php foreach ($companyQuestions as $groupName => $qs): ?>
            <div class="sk-qgroup">
              <p class="sk-qgroup-title"><?= $h($groupName) ?></p>
              <div class="sk-qlist">
                <?php foreach ($qs as $qk => $qtext): $ans = $qAnswers[$qk] ?? ''; ?>
                  <div class="sk-q<?= $ans !== '' ? ' is-answered' : '' ?>" data-q>
                    <div class="sk-q-view">
                      <div class="sk-q-main">
                        <p class="sk-q-text"><?= $h($qtext) ?></p>
                        <?php if ($ans !== ''): ?>
                          <p class="sk-q-answer"><?= nl2br($h($ans)) ?></p>
                        <?php else: ?>
                          <p class="sk-q-answer sk-q-answer--empty">Henüz bir cevap eklemedin.</p>
                        <?php endif; ?>
                      </div>
                      <button type="button" class="sk-q-edit" data-q-edit><?= $ans !== '' ? $pencil . ' Düzenle' : '+ Cevapla' ?></button>
                    </div>
                    <form class="sk-q-form" method="post" action="/seeker-panel.php#sorularim" hidden>
                      <input type="hidden" name="mode" value="company_answer">
                      <input type="hidden" name="question_key" value="<?= $h($qk) ?>">
                      <textarea name="answer" rows="2" maxlength="2000" placeholder="Cevabını yaz…"><?= $h($ans) ?></textarea>
                      <div class="sk-q-foot">
                        <button type="submit" class="sk-btn sk-btn--solid">Kaydet</button>
                        <button type="button" class="sk-btn sk-btn--ghost" data-q-cancel>Vazgeç</button>
                      </div>
                    </form>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </section>

        </div><!-- /.sk-panels -->
      </div><!-- /.sk-dash -->
    </div>
  </div>

  <?php include __DIR__ . '/_logout-modal.php'; ?>
  <script src="/frontend/assets/js/employer/topbar.js?v=<?= filemtime(__DIR__ . '/../../assets/js/employer/topbar.js') ?>" defer></script>
  <script src="/frontend/assets/js/seeker/dashboard.js?v=<?= filemtime(__DIR__ . '/../../assets/js/seeker/dashboard.js') ?>" defer></script>
  <script src="/frontend/assets/js/shared/logout-modal.js?v=<?= filemtime(__DIR__ . '/../../assets/js/shared/logout-modal.js') ?>" defer></script>
  <script src="/frontend/assets/js/seeker/profile.js?v=<?= filemtime(__DIR__ . '/../../assets/js/seeker/profile.js') ?>" defer></script>
  <script src="/frontend/assets/js/seeker/portfolio.js?v=<?= filemtime(__DIR__ . '/../../assets/js/seeker/portfolio.js') ?>" defer></script>
  <?php include __DIR__ . '/../../partials/tour-config.php'; ?>
</body>
<?php endif; ?>
</html>
