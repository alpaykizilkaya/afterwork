<?php

declare(strict_types=1);

session_start();

// DEV BYPASS — localhost only (oturum yoksa dev işveren tohumlar).
require_once __DIR__ . '/../../../backend/auth/dev-session.php';
aw_dev_session('employer');

// Rol kilidi: işveren değilse kendi paneline.
if (!isset($_SESSION['account']) || !is_array($_SESSION['account'])) {
    header('Location: /auth.php#giris');
    exit;
}
if ((string) ($_SESSION['account']['role'] ?? '') !== 'employer') {
    header('Location: ' . ((string) ($_SESSION['account']['role'] ?? '') === 'seeker' ? '/seeker-panel.php' : '/auth.php#giris'));
    exit;
}

require_once __DIR__ . '/../../../backend/config/db.php';
require_once __DIR__ . '/../../../backend/auth/session-helper.php';
require_once __DIR__ . '/../../../backend/notifications/notify.php';

$pdo        = db();
$employer   = is_array($_SESSION['employer'] ?? null) ? $_SESSION['employer'] : [];
$companyName = trim((string) ($employer['company_name'] ?? '')) ?: 'Şirketiniz';
$myEmpId    = (int) ($employer['id'] ?? 0);

$listingId = (int) ($_GET['l'] ?? 0);
$seekerAcc = (int) ($_GET['s'] ?? 0);
if ($listingId <= 0 || $seekerAcc <= 0) {
    header('Location: /isveren-panel.php');
    exit;
}

// İlan + sahiplik doğrulama
$listing = null;
try {
    $st = $pdo->prepare('SELECT id, title, employer_id FROM job_listings WHERE id = :id LIMIT 1');
    $st->execute(['id' => $listingId]);
    $listing = $st->fetch() ?: null;
} catch (Throwable) {
}
if ($listing === null || (int) $listing['employer_id'] !== $myEmpId) {
    header('Location: /isveren-panel.php'); // başkasının ilanının başvurusu görüntülenemez
    exit;
}

$statusLabels = [
    'submitted' => 'Yeni başvuru', 'reviewed' => 'İncelendi', 'shortlisted' => 'Ön elemede',
    'interview' => 'Görüşme', 'offered' => 'Teklif verildi', 'rejected' => 'Olumsuz',
];
$statusFlow = ['submitted', 'reviewed', 'shortlisted', 'interview', 'offered', 'rejected'];

$flash = null;

// Durum güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['mode'] ?? '') === 'status') {
    $newStatus = (string) ($_POST['status'] ?? '');
    if (in_array($newStatus, $statusFlow, true)) {
        try {
            $pdo->prepare('UPDATE listing_applications SET status = :st WHERE listing_id = :l AND seeker_account_id = :s')
                ->execute(['st' => $newStatus, 'l' => $listingId, 's' => $seekerAcc]);
            // adaya bildirim
            try {
                notify_account($pdo, $seekerAcc, 'Başvurun güncellendi',
                    $companyName . ' · ' . (string) $listing['title'] . ' — durum: ' . ($statusLabels[$newStatus] ?? $newStatus),
                    '/seeker-panel.php#basvurularim');
            } catch (Throwable) {
            }
        } catch (Throwable) {
        }
        header('Location: /basvuru.php?l=' . $listingId . '&s=' . $seekerAcc);
        exit;
    }
}

// Başvuru kaydı
$app = null;
try {
    $st = $pdo->prepare('SELECT status, submitted_at FROM listing_applications WHERE listing_id = :l AND seeker_account_id = :s LIMIT 1');
    $st->execute(['l' => $listingId, 's' => $seekerAcc]);
    $app = $st->fetch() ?: null;
} catch (Throwable) {
}
if ($app === null) {
    header('Location: /isveren-panel.php?ilan=' . $listingId);
    exit;
}
$status = (string) ($app['status'] ?? 'submitted');
$appliedAt = ($ts = strtotime((string) ($app['submitted_at'] ?? ''))) !== false ? date('d.m.Y H:i', $ts) : '';

// Aday profili
$sk = null;
try {
    $st = $pdo->prepare('SELECT * FROM seekers WHERE account_id = :a LIMIT 1');
    $st->execute(['a' => $seekerAcc]);
    $sk = $st->fetch() ?: null;
} catch (Throwable) {
}
if ($sk === null) {
    $sk = ['full_name' => 'Aday'];
}

// Medya + cevaplar
$mediaBy = ['avatar' => [], 'image' => [], 'video' => [], 'doc' => []];
try {
    $st = $pdo->prepare('SELECT id, kind, file_path, original_name FROM seeker_media WHERE account_id = :a ORDER BY sort_order, id');
    $st->execute(['a' => $seekerAcc]);
    foreach ($st->fetchAll() as $m) { $k = (string) $m['kind']; if (isset($mediaBy[$k])) $mediaBy[$k][] = $m; }
} catch (Throwable) {
}

$companyQuestions = [
    'Sık sorulanlar' => [
        'net_salary' => 'Aylık net ücret beklentisi', 'travel_barrier' => 'Seyahat engeli',
        'shift_weekend' => 'Vardiya / hafta sonu', 'driving' => 'Aktif araç kullanımı',
    ],
    'Özgeçmişe yönelik' => [
        'english_level' => 'İngilizce seviyesi', 'programs' => 'Kullandığı programlar',
        'currently_working' => 'Şu an çalışıyor mu', 'certificates' => 'Sertifika / belge',
    ],
];
$qAnswers = [];
try {
    $st = $pdo->prepare('SELECT question_key, answer FROM seeker_question_answers WHERE account_id = :a');
    $st->execute(['a' => $seekerAcc]);
    foreach ($st->fetchAll() as $r) { $qAnswers[(string) $r['question_key']] = (string) $r['answer']; }
} catch (Throwable) {
}

$g = static fn (string $k): string => trim((string) ($sk[$k] ?? ''));
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$fullName = $g('full_name') ?: 'Aday';
$nameWords = preg_split('/\s+/u', $fullName, -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
$initials = mb_strtoupper(mb_substr((string) $nameWords[0], 0, 1, 'UTF-8') . (isset($nameWords[1]) ? mb_substr((string) $nameWords[1], 0, 1, 'UTF-8') : ''), 'UTF-8') ?: '?';
$avatar = $mediaBy['avatar'][0]['file_path'] ?? null;

$skills = array_values(array_filter(array_map('trim', preg_split('/[,;\/]+/u', $g('skills')) ?: [])));
$langs  = array_values(array_filter(array_map('trim', preg_split('/[,;\/]+/u', $g('languages')) ?: [])));
$salaryDisp = $g('salary_expectation') !== '' ? number_format((int) $g('salary_expectation'), 0, ',', '.') . ' ₺+' : '';
$careerMeta = [
    'Alan' => $g('department'), 'Pozisyon' => $g('position_level'), 'Çalışma şekli' => $g('employment_type'),
    'Çalışma tercihi' => $g('work_pref'), 'Sektör' => $g('sector'), 'Şehir' => $g('city'),
    'İlçe' => $g('district'), 'Maaş beklentisi' => $salaryDisp,
];
$contacts = [];
if ($g('phone') !== '')    $contacts[] = ['Telefon', $g('phone'), null];
if ($g('linkedin') !== '') $contacts[] = ['LinkedIn', $g('linkedin'), (str_starts_with($g('linkedin'), 'http') ? $g('linkedin') : 'https://' . $g('linkedin'))];
if ($g('website') !== '')  $contacts[] = ['Web', $g('website'), (str_starts_with($g('website'), 'http') ? $g('website') : 'https://' . $g('website'))];

$pencil = '';
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AFTERWORK | Aday · <?= $h($fullName) ?></title>
  <link rel="stylesheet" href="/frontend/assets/css/employer/panel.css?v=<?= filemtime(__DIR__ . '/../../assets/css/employer/panel.css') ?>">
  <link rel="stylesheet" href="/frontend/assets/css/seeker/panel.css?v=<?= filemtime(__DIR__ . '/../../assets/css/seeker/panel.css') ?>">
  <link rel="stylesheet" href="/frontend/assets/css/employer/applicant.css?v=<?= filemtime(__DIR__ . '/../../assets/css/employer/applicant.css') ?>">
  <link rel="stylesheet" href="/frontend/assets/css/shared/logout-modal.css?v=<?= filemtime(__DIR__ . '/../../assets/css/shared/logout-modal.css') ?>">
</head>
<body>
  <div class="ep-page">
    <?php $activeTab = 'feed'; $searchQuery = ''; include __DIR__ . '/../../partials/employer-topbar.php'; ?>

    <div class="sk-wrap">
      <a class="ap-back" href="/mercek.php?id=<?= (int) $listingId ?>">← <?= $h((string) $listing['title']) ?> · başvurular</a>

      <div class="sk-layout">
        <div class="sk-col">
          <!-- Aday başlık -->
          <article class="sk-profile">
            <div class="sk-cover" aria-hidden="true"></div>
            <div class="sk-profile-body">
              <div class="sk-avatar-xl" aria-hidden="true"><?php if ($avatar): ?><img src="<?= $h($avatar) ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:inherit"><?php else: ?><?= $h($initials) ?><?php endif; ?></div>
              <div class="sk-profile-top">
                <div class="sk-profile-id">
                  <h1 class="sk-profile-name"><?= $h($fullName) ?></h1>
                  <?php if ($g('headline') !== ''): ?><p class="sk-profile-headline"><?= $h($g('headline')) ?></p><?php endif; ?>
                  <p class="sk-profile-loc"><?php $lb = array_filter([$g('city'), $g('department')]); echo $lb ? $h(implode(' · ', $lb)) : '<span class="sk-muted">—</span>'; ?></p>
                  <div class="sk-profile-pills">
                    <?php if ((int) ($sk['open_to_work'] ?? 1) === 1): ?><span class="sk-pill sk-pill--open">İşe açık</span><?php endif; ?>
                    <?php if ($g('work_pref') !== ''): ?><span class="sk-pill"><?= $h($g('work_pref')) ?></span><?php endif; ?>
                    <?php if ($g('experience_level') !== ''): ?><span class="sk-pill"><?= $h($g('experience_level')) ?></span><?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </article>

          <?php if ($g('about') !== ''): ?>
          <article class="sk-card"><p class="sk-card-kicker">Hakkında</p><p class="sk-about"><?= nl2br($h($g('about'))) ?></p></article>
          <?php endif; ?>

          <article class="sk-card">
            <p class="sk-card-kicker">Deneyim & eğitim</p>
            <ul class="sk-meta">
              <?php foreach (['Deneyim' => $g('experience_level'), 'Eğitim' => trim($g('school') . ' ' . $g('education_level'))] as $lab => $val): if (trim($val) === '') continue; ?>
                <li><span><?= $h($lab) ?></span><strong><?= $h($val) ?></strong></li>
              <?php endforeach; ?>
            </ul>
          </article>

          <?php if ($skills !== []): ?>
          <article class="sk-card"><p class="sk-card-kicker">Beceriler</p><div class="sk-chips"><?php foreach ($skills as $s): ?><span class="sk-chip"><?= $h($s) ?></span><?php endforeach; ?></div></article>
          <?php endif; ?>

          <?php if ($langs !== []): ?>
          <article class="sk-card"><p class="sk-card-kicker">Diller</p><div class="sk-chips"><?php foreach ($langs as $l): ?><span class="sk-chip"><?= $h($l) ?></span><?php endforeach; ?></div></article>
          <?php endif; ?>

          <!-- Şirket soruları cevapları -->
          <article class="sk-card">
            <p class="sk-card-kicker">Şirket soruları</p>
            <ul class="sk-meta">
              <?php $anyAns = false; foreach ($companyQuestions as $qs): foreach ($qs as $qk => $lab): $a = $qAnswers[$qk] ?? ''; if ($a === '') continue; $anyAns = true; ?>
                <li><span><?= $h($lab) ?></span><strong><?= $h($a) ?></strong></li>
              <?php endforeach; endforeach; ?>
              <?php if (!$anyAns): ?><li><span class="sk-muted">Aday henüz şirket sorularını yanıtlamamış.</span></li><?php endif; ?>
            </ul>
          </article>

          <!-- Portföy -->
          <?php if ($mediaBy['doc'] || $mediaBy['image'] || $mediaBy['video']): ?>
          <article class="sk-card">
            <p class="sk-card-kicker">Portföy & belgeler</p>
            <?php if ($mediaBy['doc']): ?>
              <div class="ap-docs">
                <?php foreach ($mediaBy['doc'] as $m): $ext = strtoupper(pathinfo((string) $m['original_name'], PATHINFO_EXTENSION) ?: 'DOC'); ?>
                  <a class="ap-doc" href="<?= $h($m['file_path']) ?>" target="_blank" rel="noopener"><span class="ap-doc-ic"><?= $h($ext) ?></span><span class="ap-doc-name"><?= $h($m['original_name']) ?></span></a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <?php if ($mediaBy['image']): ?>
              <div class="ap-gallery"><?php foreach ($mediaBy['image'] as $m): ?><a href="<?= $h($m['file_path']) ?>" target="_blank" rel="noopener"><img src="<?= $h($m['file_path']) ?>" alt="" loading="lazy"></a><?php endforeach; ?></div>
            <?php endif; ?>
            <?php if ($mediaBy['video']): ?>
              <div class="ap-videos"><?php foreach ($mediaBy['video'] as $m): ?><video controls preload="metadata" src="<?= $h($m['file_path']) ?>"></video><?php endforeach; ?></div>
            <?php endif; ?>
          </article>
          <?php endif; ?>
        </div>

        <!-- Sağ: karar paneli -->
        <aside class="sk-col">
          <article class="sk-card ap-decide">
            <p class="sk-card-kicker">Başvuru</p>
            <span class="ap-status ap-status--<?= $h($status) ?>"><?= $h($statusLabels[$status] ?? 'Yeni başvuru') ?></span>
            <?php if ($appliedAt !== ''): ?><p class="sk-muted ap-date"><?= $h($appliedAt) ?></p><?php endif; ?>

            <form method="post" action="/basvuru.php?l=<?= (int) $listingId ?>&s=<?= (int) $seekerAcc ?>" class="ap-status-form">
              <input type="hidden" name="mode" value="status">
              <label class="sk-field"><span>Durumu güncelle</span>
                <select name="status">
                  <?php foreach ($statusFlow as $stt): ?>
                    <option value="<?= $h($stt) ?>"<?= $stt === $status ? ' selected' : '' ?>><?= $h($statusLabels[$stt]) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <button type="submit" class="sk-btn sk-btn--solid">Kaydet</button>
            </form>

            <a class="sk-btn sk-btn--solid ap-msg" href="/mesaj-baslat.php?account=<?= (int) $seekerAcc ?>&listing=<?= (int) $listingId ?>">Mesaj / görüşme başlat</a>

            <?php if ($contacts !== []): ?>
              <div class="ap-contacts">
                <?php foreach ($contacts as [$lab, $val, $url]): ?>
                  <?php if ($url !== null): ?><a class="ap-contact" href="<?= $h($url) ?>" target="_blank" rel="noopener"><span><?= $h($lab) ?></span><?= $h($val) ?></a>
                  <?php else: ?><span class="ap-contact"><span><?= $h($lab) ?></span><?= $h($val) ?></span><?php endif; ?>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </article>
        </aside>
      </div>
    </div>
  </div>

  <?php include __DIR__ . '/../seeker-panel/_logout-modal.php'; ?>
  <script src="/frontend/assets/js/employer/topbar.js?v=<?= filemtime(__DIR__ . '/../../assets/js/employer/topbar.js') ?>" defer></script>
  <script src="/frontend/assets/js/shared/logout-modal.js?v=<?= filemtime(__DIR__ . '/../../assets/js/shared/logout-modal.js') ?>" defer></script>
</body>
</html>
