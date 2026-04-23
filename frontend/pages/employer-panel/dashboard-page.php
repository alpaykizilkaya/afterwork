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
    header('Location: auth.php#giris');
    exit;
}

require_once __DIR__ . '/../../../backend/config/db.php';
require_once __DIR__ . '/../../../backend/auth/session-helper.php';

$employer   = is_array($_SESSION['employer'] ?? null) ? $_SESSION['employer'] : [];
$employerId = (int) ($employer['id'] ?? 0);
$companyName = trim((string) ($employer['company_name'] ?? '')) ?: 'Şirketiniz';
try {
    $isVerified = refresh_verification_flag(db());
} catch (Throwable) {
    $isVerified = (int) ($_SESSION['account']['is_verified'] ?? 0) === 1;
}
$verifyFlash = $_SESSION['flash_verify'] ?? null;
unset($_SESSION['flash_verify']);

// ── View mode ──────────────────────────────────────────
$isNewMode = isset($_GET['yeni']);
$isProfileMode = isset($_GET['profil']);
$selectedListingId = isset($_GET['ilan']) ? (int) $_GET['ilan'] : 0;
$isDetailMode = $selectedListingId > 0;
$showDashboard = $isNewMode || $isProfileMode || $isDetailMode || $_SERVER['REQUEST_METHOD'] === 'POST';
$showGrid = !$showDashboard;

$listings = [];
$editListing = null;

// ── Helpers ────────────────────────────────────────────
$p = static fn (string $key): string => trim((string) ($_POST[$key] ?? ''));
$pNull = static fn (string $key): ?string => ($v = trim((string) ($_POST[$key] ?? ''))) !== '' ? $v : null;
$pInt  = static fn (string $key): ?int   => ($v = trim((string) ($_POST[$key] ?? ''))) !== '' ? (int) $v : null;

// ── Defaults ───────────────────────────────────────────
$profile        = [];
$activeListings = 0;
$profileErrors  = [];
$profileSuccess = null;
$listingErrors  = [];
$listingSuccess = null;

// ── Load existing profile & stats from DB ──────────────
if ($employerId > 0) {
    try {
        $pdo  = db();
        $stmt = $pdo->prepare('SELECT * FROM employers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $employerId]);
        $profile = $stmt->fetch() ?: [];

        $cnt = $pdo->prepare('SELECT COUNT(*) FROM job_listings WHERE employer_id = :id AND is_active = 1');
        $cnt->execute(['id' => $employerId]);
        $activeListings = (int) $cnt->fetchColumn();

        if ($showGrid) {
            $lst = $pdo->prepare(
                'SELECT id, title, employment_type, work_model, location,
                        salary_min, salary_max, is_active, created_at
                 FROM job_listings
                 WHERE employer_id = :id
                 ORDER BY COALESCE(created_at, id) DESC'
            );
            $lst->execute(['id' => $employerId]);
            $listings = $lst->fetchAll();
        }

        if ($isDetailMode) {
            $detStmt = $pdo->prepare(
                'SELECT * FROM job_listings WHERE id = :id AND employer_id = :eid LIMIT 1'
            );
            $detStmt->execute(['id' => $selectedListingId, 'eid' => $employerId]);
            $editListing = $detStmt->fetch() ?: null;
        }
    } catch (Throwable) {
        // silent — forms will render empty
    }
}

// ── POST: save company profile ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $p('mode') === 'company_profile') {
    $cpName    = $p('company_name');
    $cpSector  = $p('sector');
    $cpSize    = $p('company_size');
    $cpCity    = $p('city');
    $cpAbout   = $p('about');
    $cpWebsite = $pNull('website');
    $cpLinkedin = $pNull('linkedin');
    $cpFounded  = $pInt('founded_year');

    if ($cpName   === '') $profileErrors[] = 'Şirket adı zorunludur.';
    if ($cpSector === '') $profileErrors[] = 'Sektör seçimi zorunludur.';
    if ($cpSize   === '') $profileErrors[] = 'Şirket büyüklüğü zorunludur.';
    if ($cpCity   === '') $profileErrors[] = 'Şehir zorunludur.';
    if ($cpAbout  === '') $profileErrors[] = 'Şirket hakkında bilgi zorunludur.';

    if ($profileErrors === [] && $employerId > 0) {
        try {
            $pdo = db();
            $pdo->prepare(
                'UPDATE employers
                 SET company_name  = :name,
                     sector        = :sector,
                     company_size  = :size,
                     city          = :city,
                     about         = :about,
                     website       = :website,
                     linkedin      = :linkedin,
                     founded_year  = :founded_year
                 WHERE id = :id'
            )->execute([
                'name'         => $cpName,
                'sector'       => $cpSector,
                'size'         => $cpSize,
                'city'         => $cpCity,
                'about'        => $cpAbout,
                'website'      => $cpWebsite,
                'linkedin'     => $cpLinkedin,
                'founded_year' => $cpFounded,
                'id'           => $employerId,
            ]);

            $_SESSION['employer']['company_name'] = $cpName;
            $companyName = $cpName;
            $profile = array_merge($profile, [
                'company_name' => $cpName,  'sector'       => $cpSector,
                'company_size' => $cpSize,  'city'         => $cpCity,
                'about'        => $cpAbout, 'website'      => $cpWebsite,
                'linkedin'     => $cpLinkedin, 'founded_year' => $cpFounded,
            ]);
            $profileSuccess = 'Şirket profili kaydedildi.';
        } catch (Throwable $e) {
            $profileErrors[] = 'Kayıt sırasında hata oluştu: ' . $e->getMessage();
        }
    }
}

// ── POST: create or update job listing ────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $p('mode') === 'create_listing') {
    $postedListingId = (int) ($_POST['listing_id'] ?? 0);
    $isEdit = $postedListingId > 0;

    if (!$isEdit && !$isVerified) {
        $listingErrors[] = 'İlan yayınlamadan önce e-posta adresini doğrulaman gerekiyor.';
    }

    $jTitle    = $p('title');
    $jType     = $p('employment_type');
    $jModel    = $p('work_model');
    $jLocation = $p('location');
    $jDesc     = $p('description');
    $jReqs     = $p('requirements');
    $jEmail    = $p('contact_email');

    if ($jTitle    === '') $listingErrors[] = 'İş başlığı zorunludur.';
    if ($jType     === '') $listingErrors[] = 'Çalışma tipi seçimi zorunludur.';
    if ($jModel    === '') $listingErrors[] = 'Çalışma modeli seçimi zorunludur.';
    if ($jLocation === '') $listingErrors[] = 'Konum zorunludur.';
    if ($jDesc     === '') $listingErrors[] = 'İş tanımı zorunludur.';
    if ($jReqs     === '') $listingErrors[] = 'Aranan özellikler zorunludur.';
    if (!filter_var($jEmail, FILTER_VALIDATE_EMAIL)) $listingErrors[] = 'Geçerli bir başvuru e-postası gir.';

    if ($listingErrors === [] && $employerId > 0) {
        try {
            $pdo = db();
            $params = [
                'title'    => $jTitle,
                'type'     => $jType,
                'model'    => $jModel,
                'location' => $jLocation,
                'desc'     => $jDesc,
                'reqs'     => $jReqs,
                'email'    => $jEmail,
                'sal_min'  => $pInt('salary_min'),
                'sal_max'  => $pInt('salary_max'),
                'benefits' => $pNull('benefits'),
                'exp'      => $pNull('experience_level'),
                'skills'   => $pNull('skills'),
                'deadline' => $pNull('deadline'),
                'openings' => $pInt('openings_count'),
                'hours'    => $pNull('work_hours'),
            ];

            if ($isEdit) {
                $check = $pdo->prepare('SELECT id FROM job_listings WHERE id = :id AND employer_id = :eid LIMIT 1');
                $check->execute(['id' => $postedListingId, 'eid' => $employerId]);
                if (!$check->fetchColumn()) {
                    $listingErrors[] = 'Bu ilan sana ait değil ya da artık yok.';
                } else {
                    $params['id'] = $postedListingId;
                    $pdo->prepare(
                        'UPDATE job_listings SET
                           title = :title, employment_type = :type, work_model = :model,
                           location = :location, description = :desc, requirements = :reqs,
                           contact_email = :email, salary_min = :sal_min, salary_max = :sal_max,
                           benefits = :benefits, experience_level = :exp, skills = :skills,
                           deadline = :deadline, openings_count = :openings, work_hours = :hours
                         WHERE id = :id'
                    )->execute($params);

                    // Refresh the detail state so the form shows saved values
                    $refresh = $pdo->prepare('SELECT * FROM job_listings WHERE id = :id LIMIT 1');
                    $refresh->execute(['id' => $postedListingId]);
                    $editListing = $refresh->fetch() ?: $editListing;
                    $listingSuccess = '"' . htmlspecialchars($jTitle, ENT_QUOTES, 'UTF-8') . '" ilanı güncellendi.';
                }
            } else {
                $params['employer_id'] = $employerId;
                $pdo->prepare(
                    'INSERT INTO job_listings
                       (employer_id, title, employment_type, work_model, location,
                        description, requirements, contact_email,
                        salary_min, salary_max, benefits, experience_level,
                        skills, deadline, openings_count, work_hours)
                     VALUES
                       (:employer_id, :title, :type, :model, :location,
                        :desc, :reqs, :email,
                        :sal_min, :sal_max, :benefits, :exp,
                        :skills, :deadline, :openings, :hours)'
                )->execute($params);

                $activeListings++;
                $listingSuccess = '"' . htmlspecialchars($jTitle, ENT_QUOTES, 'UTF-8') . '" ilanı başarıyla yayınlandı.';
            }
        } catch (Throwable $e) {
            $listingErrors[] = 'İlan kaydedilirken hata oluştu: ' . $e->getMessage();
        }
    }
}

// ── Shorthand for pre-filling fields ──────────────────
$v = static fn (string $key, string $fallback = ''): string =>
    htmlspecialchars((string) ($profile[$key] ?? $fallback), ENT_QUOTES, 'UTF-8');
$sel = static fn (string $key, string $option): string =>
    ((string) ($profile[$key] ?? '')) === $option ? ' selected' : '';
$chipActive = static fn (string $key): string =>
    !empty($profile[$key]) ? ' is-active' : '';
$panelHidden = static fn (string $key): string =>
    !empty($profile[$key]) ? '' : ' hidden';

// ── Pre-fill helpers for listing edit mode ────────────
$lv = static function (string $key, string $fallback = '') use ($editListing): string {
    if ($editListing === null) return htmlspecialchars($fallback, ENT_QUOTES, 'UTF-8');
    $val = $editListing[$key] ?? $fallback;
    return htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8');
};
$lsel = static function (string $key, string $option) use ($editListing): string {
    if ($editListing === null) return '';
    return ((string) ($editListing[$key] ?? '')) === $option ? ' selected' : '';
};
$lChipActive = static function (array $keys) use ($editListing): string {
    if ($editListing === null) return '';
    foreach ($keys as $k) { if (!empty($editListing[$k])) return ' is-active'; }
    return '';
};
$lOpen = static function (array $keys) use ($editListing): string {
    if ($editListing === null) return ' hidden';
    foreach ($keys as $k) { if (!empty($editListing[$k])) return ''; }
    return ' hidden';
};
$isEditing = $isDetailMode && $editListing !== null;
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AFTERWORK | İş Veren Paneli</title>
  <link rel="stylesheet" href="frontend/assets/css/employer/panel.css?v=<?= filemtime(__DIR__ . '/../../assets/css/employer/panel.css') ?>">
  <link rel="stylesheet" href="frontend/assets/css/shared/logout-modal.css?v=<?= filemtime(__DIR__ . '/../../assets/css/shared/logout-modal.css') ?>">
  <link rel="stylesheet" href="frontend/assets/css/shared/verify-banner.css?v=<?= filemtime(__DIR__ . '/../../assets/css/shared/verify-banner.css') ?>">
</head>
<body>
  <div class="ep-page">

    <!-- TOPBAR -->
    <?php
    $activeTab = 'account';
    include __DIR__ . '/../../partials/employer-topbar.php';
    ?>

    <?php if (!$isVerified || $verifyFlash !== null): ?>
    <div class="verify-banner" role="region" aria-label="E-posta doğrulama">
      <?php if (!$isVerified): ?>
        <span class="verify-banner__icon" aria-hidden="true">!</span>
        <p class="verify-banner__text">
          <strong>E-posta adresini doğrula.</strong>
          İlan yayınlayabilmek için <?= htmlspecialchars((string) ($_SESSION['account']['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?> adresine gönderdiğimiz bağlantıya tıklaman gerekiyor.
        </p>
        <div class="verify-banner__actions">
          <form action="/resend-verification.php" method="post" style="margin:0;">
            <button type="submit" class="verify-banner__btn verify-banner__btn--solid">Yeniden gönder</button>
          </form>
        </div>
      <?php endif; ?>
      <?php if ($verifyFlash !== null): ?>
        <p class="verify-banner__flash verify-banner__flash--<?= htmlspecialchars((string) $verifyFlash['type'], ENT_QUOTES, 'UTF-8') ?>">
          <?= htmlspecialchars((string) $verifyFlash['text'], ENT_QUOTES, 'UTF-8') ?>
        </p>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($showDashboard): ?>
    <!-- HERO -->
    <section class="ep-hero" aria-label="Hoş geldin">
      <div class="ep-hero-inner">
        <div class="ep-hero-text">
          <p class="ep-kicker"><?= $isEditing ? 'İlan Detayı' : 'İş Veren Paneli' ?></p>
          <h1><?= htmlspecialchars($isEditing ? (string) ($editListing['title'] ?? $companyName) : $companyName, ENT_QUOTES, 'UTF-8') ?></h1>
          <p class="ep-hero-lead"><?= $isEditing ? 'Bu ilanın alanlarını düzenle ya da canlı analizini aç.' : 'Doğru adayı bul, kadroyu güçlendir.' ?></p>
        </div>

        <?php if ($isEditing): ?>
          <a class="ep-mercek-cta" href="/mercek.php?id=<?= (int) $selectedListingId ?>">
            <span class="ep-mercek-cta-kicker">Mercek</span>
            <span class="ep-mercek-cta-title">Canlı analizi aç</span>
            <span class="ep-mercek-cta-sub">Görüntülenme, başvuru, aday davranışı</span>
            <span class="ep-mercek-cta-arrow" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                <circle cx="7.5" cy="7.5" r="4.2" stroke="currentColor" stroke-width="1.6"/>
                <path d="M10.7 10.7l4.3 4.3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
              </svg>
            </span>
          </a>
        <?php else: ?>
          <div class="ep-hero-stats">
            <div class="ep-stat-card">
              <strong><?= $activeListings ?></strong>
              <span>Aktif İlan</span>
            </div>
            <div class="ep-stat-card">
              <strong>0</strong>
              <span>Başvuru</span>
            </div>
            <div class="ep-stat-card">
              <strong>0</strong>
              <span>Görüntülenme</span>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- MAIN GRID -->
    <div class="ep-layout">

      <!-- LEFT — Şirket Profili -->
      <aside class="ep-aside" id="sirket-profili">
        <div class="ep-aside-head">
          <h2>Şirket Profili</h2>
          <p>Adayların göreceği şirket sayfanı oluştur.</p>
          <span class="ep-badge ep-badge--required">Zorunlu</span>
        </div>

        <?php if ($profileSuccess !== null): ?>
          <p class="ep-feedback ep-feedback--success"><?= htmlspecialchars($profileSuccess, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if ($profileErrors !== []): ?>
          <div class="ep-feedback ep-feedback--error" role="alert">
            <?php foreach ($profileErrors as $err): ?>
              <p><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="ep-card">
          <form class="ep-form" action="isveren-panel.php" method="post">
            <input type="hidden" name="mode" value="company_profile">

            <div class="ep-field">
              <label for="cp-name">Şirket Adı</label>
              <input id="cp-name" name="company_name" class="ep-input" type="text"
                value="<?= $v('company_name', $companyName) ?>" required>
            </div>

            <div class="ep-field">
              <label for="cp-sector">Sektör</label>
              <select id="cp-sector" name="sector" class="ep-select" required>
                <option value="">Seç…</option>
                <?php foreach (['Teknoloji','Finans','Sağlık','Eğitim','Perakende','Üretim','Medya & Reklam','Lojistik','Danışmanlık','Diğer'] as $s): ?>
                  <option<?= $sel('sector', $s) ?>><?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="ep-field">
              <label for="cp-size">Şirket Büyüklüğü</label>
              <select id="cp-size" name="company_size" class="ep-select" required>
                <option value="">Seç…</option>
                <?php foreach (['1–10 kişi','11–50 kişi','51–200 kişi','201–500 kişi','500+ kişi'] as $s): ?>
                  <option<?= $sel('company_size', $s) ?>><?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="ep-field">
              <label for="cp-city">Şehir</label>
              <input id="cp-city" name="city" class="ep-input" type="text"
                placeholder="İstanbul" value="<?= $v('city') ?>" required>
            </div>

            <div class="ep-field">
              <label for="cp-about">Şirket Hakkında</label>
              <textarea id="cp-about" name="about" class="ep-textarea" rows="5"
                placeholder="Şirketinizi adaylara tanıtın…" required><?= $v('about') ?></textarea>
            </div>

            <div class="ep-chips-row">
              <span class="ep-chips-label">Profiline ekle</span>
              <div class="ep-chips">
                <button type="button" class="ep-chip<?= $chipActive('website') ?>" data-target="cp-website">+ Web Sitesi</button>
                <button type="button" class="ep-chip<?= $chipActive('linkedin') ?>" data-target="cp-linkedin">+ LinkedIn</button>
                <button type="button" class="ep-chip<?= $chipActive('founded_year') ?>" data-target="cp-founded">+ Kuruluş Yılı</button>
              </div>
            </div>

            <div id="cp-website" class="ep-extra"<?= $panelHidden('website') ?>>
              <div class="ep-field">
                <label for="cp-website-url">Web Sitesi</label>
                <input id="cp-website-url" name="website" class="ep-input" type="url"
                  placeholder="https://sirketiniz.com" value="<?= $v('website') ?>">
              </div>
            </div>
            <div id="cp-linkedin" class="ep-extra"<?= $panelHidden('linkedin') ?>>
              <div class="ep-field">
                <label for="cp-linkedin-url">LinkedIn</label>
                <input id="cp-linkedin-url" name="linkedin" class="ep-input" type="url"
                  placeholder="https://linkedin.com/company/…" value="<?= $v('linkedin') ?>">
              </div>
            </div>
            <div id="cp-founded" class="ep-extra"<?= $panelHidden('founded_year') ?>>
              <div class="ep-field">
                <label for="cp-founded-year">Kuruluş Yılı</label>
                <input id="cp-founded-year" name="founded_year" class="ep-input" type="number"
                  placeholder="2015" min="1800" max="2099"
                  value="<?= $v('founded_year') ?>">
              </div>
            </div>

            <button type="submit" class="ep-submit">Profili Kaydet</button>
          </form>
        </div>
      </aside>

      <!-- RIGHT — Yeni İlan / İlanı Düzenle -->
      <main class="ep-main" id="yeni-ilan">
        <div class="ep-main-head">
          <div>
            <h2><?= $isEditing ? 'İlanı Düzenle' : 'Yeni İlan Oluştur' ?></h2>
            <p><?= $isEditing ? 'Bu ilanın tüm alanlarını güncelle.' : 'Pozisyonu tanımla, doğru adaya ulaş.' ?></p>
          </div>
          <span class="ep-badge ep-badge--required">Zorunlu</span>
        </div>

        <?php if ($listingSuccess !== null): ?>
          <p class="ep-feedback ep-feedback--success"><?= htmlspecialchars($listingSuccess, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if ($listingErrors !== []): ?>
          <div class="ep-feedback ep-feedback--error" role="alert">
            <?php foreach ($listingErrors as $err): ?>
              <p><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="ep-card">
          <form class="ep-form" action="<?= $isEditing ? '/isveren-panel.php?ilan=' . (int) $selectedListingId : 'isveren-panel.php' ?>" method="post">
            <input type="hidden" name="mode" value="create_listing">
            <?php if ($isEditing): ?>
              <input type="hidden" name="listing_id" value="<?= (int) $selectedListingId ?>">
            <?php endif; ?>

            <div class="ep-field">
              <label for="jl-title">İş Başlığı</label>
              <input id="jl-title" name="title" class="ep-input ep-input--large" type="text"
                placeholder="örn. Frontend Geliştirici, Pazarlama Uzmanı…"
                value="<?= $lv('title') ?>" required>
            </div>

            <div class="ep-field-row ep-field-row--3">
              <div class="ep-field">
                <label for="jl-type">Çalışma Tipi</label>
                <select id="jl-type" name="employment_type" class="ep-select" required>
                  <option value="">Seç…</option>
                  <?php foreach (['Tam Zamanlı','Yarı Zamanlı','Staj','Sözleşmeli','Freelance'] as $opt): ?>
                    <option<?= $lsel('employment_type', $opt) ?>><?= $opt ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="ep-field">
                <label for="jl-model">Çalışma Modeli</label>
                <select id="jl-model" name="work_model" class="ep-select" required>
                  <option value="">Seç…</option>
                  <?php foreach (['Ofiste','Uzaktan','Hibrit'] as $opt): ?>
                    <option<?= $lsel('work_model', $opt) ?>><?= $opt ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="ep-field">
                <label for="jl-location">Konum / Şehir</label>
                <input id="jl-location" name="location" class="ep-input" type="text"
                  placeholder="İstanbul" value="<?= $lv('location') ?>" required>
              </div>
            </div>

            <div class="ep-field">
              <label for="jl-description">İş Tanımı</label>
              <textarea id="jl-description" name="description" class="ep-textarea" rows="6"
                placeholder="Bu pozisyonda ne yapılacağını, sorumlulukları ve beklentileri açıkla…" required><?= $lv('description') ?></textarea>
            </div>

            <div class="ep-field">
              <label for="jl-requirements">Aranan Özellikler</label>
              <textarea id="jl-requirements" name="requirements" class="ep-textarea" rows="4"
                placeholder="Aday için gerekli eğitim, deneyim ve yetkinlikleri listele…" required><?= $lv('requirements') ?></textarea>
            </div>

            <div class="ep-field">
              <label for="jl-email">Başvuru E-postası</label>
              <input id="jl-email" name="contact_email" class="ep-input" type="email"
                placeholder="basvuru@sirket.com" value="<?= $lv('contact_email') ?>" required>
            </div>

            <div class="ep-divider"></div>

            <div class="ep-chips-row">
              <div class="ep-chips-meta">
                <span class="ep-chips-label">İlana detay ekle</span>
                <span class="ep-chips-hint">isteğe bağlı</span>
              </div>
              <div class="ep-chips">
                <button type="button" class="ep-chip<?= $lChipActive(['salary_min','salary_max']) ?>" data-target="jl-salary">+ Maaş Aralığı</button>
                <button type="button" class="ep-chip<?= $lChipActive(['benefits']) ?>" data-target="jl-benefits">+ Yan Haklar</button>
                <button type="button" class="ep-chip<?= $lChipActive(['experience_level']) ?>" data-target="jl-experience">+ Deneyim Seviyesi</button>
                <button type="button" class="ep-chip<?= $lChipActive(['skills']) ?>" data-target="jl-skills">+ Gerekli Beceriler</button>
                <button type="button" class="ep-chip<?= $lChipActive(['deadline']) ?>" data-target="jl-deadline">+ Son Başvuru Tarihi</button>
                <button type="button" class="ep-chip<?= $lChipActive(['openings_count']) ?>" data-target="jl-openings">+ Açık Pozisyon Sayısı</button>
                <button type="button" class="ep-chip<?= $lChipActive(['work_hours']) ?>" data-target="jl-hours">+ Çalışma Saatleri</button>
              </div>
            </div>

            <div id="jl-salary" class="ep-extra"<?= $lOpen(['salary_min','salary_max']) ?>>
              <div class="ep-field-row">
                <div class="ep-field">
                  <label for="jl-salary-min">Minimum Maaş (₺)</label>
                  <input id="jl-salary-min" name="salary_min" class="ep-input" type="number" placeholder="30000" value="<?= $lv('salary_min') ?>">
                </div>
                <div class="ep-field">
                  <label for="jl-salary-max">Maximum Maaş (₺)</label>
                  <input id="jl-salary-max" name="salary_max" class="ep-input" type="number" placeholder="60000" value="<?= $lv('salary_max') ?>">
                </div>
              </div>
            </div>

            <div id="jl-benefits" class="ep-extra"<?= $lOpen(['benefits']) ?>>
              <div class="ep-field">
                <label for="jl-benefits-text">Yan Haklar</label>
                <textarea id="jl-benefits-text" name="benefits" class="ep-textarea" rows="3"
                  placeholder="Sağlık sigortası, yemek kartı, ulaşım desteği…"><?= $lv('benefits') ?></textarea>
              </div>
            </div>

            <div id="jl-experience" class="ep-extra"<?= $lOpen(['experience_level']) ?>>
              <div class="ep-field">
                <label for="jl-experience-level">Deneyim Seviyesi</label>
                <select id="jl-experience-level" name="experience_level" class="ep-select">
                  <option value="">Seç…</option>
                  <?php foreach (['Deneyim Aranmıyor','Junior (0–2 yıl)','Mid-level (2–5 yıl)','Senior (5+ yıl)','Lead / Yönetici'] as $opt): ?>
                    <option<?= $lsel('experience_level', $opt) ?>><?= $opt ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div id="jl-skills" class="ep-extra"<?= $lOpen(['skills']) ?>>
              <div class="ep-field">
                <label for="jl-skills-input">Gerekli Beceriler / Teknolojiler</label>
                <input id="jl-skills-input" name="skills" class="ep-input" type="text"
                  placeholder="React, Node.js, SQL, Figma…" value="<?= $lv('skills') ?>">
                <p class="ep-field-hint">Virgülle ayırarak birden fazla beceri ekleyebilirsin.</p>
              </div>
            </div>

            <div id="jl-deadline" class="ep-extra"<?= $lOpen(['deadline']) ?>>
              <div class="ep-field">
                <label for="jl-deadline-date">Son Başvuru Tarihi</label>
                <input id="jl-deadline-date" name="deadline" class="ep-input" type="date" value="<?= $lv('deadline') ?>">
              </div>
            </div>

            <div id="jl-openings" class="ep-extra"<?= $lOpen(['openings_count']) ?>>
              <div class="ep-field">
                <label for="jl-openings-count">Açık Pozisyon Sayısı</label>
                <input id="jl-openings-count" name="openings_count" class="ep-input" type="number" placeholder="1" min="1" value="<?= $lv('openings_count') ?>">
              </div>
            </div>

            <div id="jl-hours" class="ep-extra"<?= $lOpen(['work_hours']) ?>>
              <div class="ep-field">
                <label for="jl-hours-input">Çalışma Saatleri</label>
                <input id="jl-hours-input" name="work_hours" class="ep-input" type="text"
                  placeholder="09:00 – 18:00, Pazartesi – Cuma" value="<?= $lv('work_hours') ?>">
              </div>
            </div>

            <button type="submit" class="ep-submit ep-submit--publish">
              <span><?= $isEditing ? 'Değişiklikleri Kaydet' : 'İlanı Yayınla' ?></span>
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
          </form>
        </div>
      </main>

    </div>
    <?php else: ?>

    <!-- DUKKAN (grid of listings) -->
    <section class="ep-dukkan" aria-label="Dükkan">
      <header class="ep-dukkan-head">
        <div>
          <p class="ep-dukkan-kicker">Dükkan</p>
          <h1><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></h1>
          <p class="ep-dukkan-lead">Yayındaki ilanların burada. Bir karta tıklayarak detayları aç, düzenle ve performansını gör.</p>
        </div>
        <div class="ep-dukkan-stats">
          <div class="ep-stat-card ep-stat-card--light">
            <strong><?= (int) count($listings) ?></strong>
            <span>Toplam İlan</span>
          </div>
          <div class="ep-stat-card ep-stat-card--light">
            <strong><?= $activeListings ?></strong>
            <span>Aktif</span>
          </div>
          <div class="ep-stat-card ep-stat-card--light">
            <strong>0</strong>
            <span>Başvuru</span>
          </div>
        </div>
      </header>

      <?php if ($listings === []): ?>
        <div class="ep-welcome">
          <div class="ep-welcome-lead">
            <p class="ep-welcome-kicker">İlk adım</p>
            <h2>Dükkanın seni bekliyor.</h2>
            <p class="ep-welcome-copy">
              Premium bir iş tecrübesinin ilk durağı burası. İlk ilanını oluştur, doğru adaylar seni bulmaya başlasın.
            </p>
            <div class="ep-welcome-cta-row">
              <a class="ep-dukkan-new" href="/isveren-panel.php?yeni=1">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                  <path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
                Yeni İlan Oluştur
              </a>
              <a class="ep-welcome-secondary" href="/isveren-panel.php?profil=1">Şirket profilini tamamla →</a>
            </div>
          </div>

          <aside class="ep-welcome-steps" aria-label="Başlangıç adımları">
            <p class="ep-welcome-steps-kicker">3 adımda başla</p>
            <ol>
              <li>
                <span class="ep-welcome-step-num">1</span>
                <div>
                  <strong>İlanını yayınla</strong>
                  <p>Pozisyonu, maaş bandını ve aranan özellikleri tanımla.</p>
                </div>
              </li>
              <li>
                <span class="ep-welcome-step-num">2</span>
                <div>
                  <strong>İlgi topla</strong>
                  <p>Görüntülenme, kaydeden ve başvuruları tek panelde izle.</p>
                </div>
              </li>
              <li>
                <span class="ep-welcome-step-num">3</span>
                <div>
                  <strong>Eşleşmeyi başlat</strong>
                  <p>Doğru adaylarla Afterwork üzerinden mesajlaş, görüşmeye geç.</p>
                </div>
              </li>
            </ol>
          </aside>
        </div>

        <section class="ep-tips" aria-label="Premium ipuçları">
          <header class="ep-tips-head">
            <p class="ep-tips-kicker">Premium ipuçları</p>
            <h3>İlk ilanından önce bir göz at.</h3>
          </header>
          <div class="ep-tips-row">
            <article class="ep-tip-card">
              <span class="ep-tip-index">01</span>
              <h4>İyi bir başlık, %40 fazla başvuru getirir.</h4>
              <p>Belirsiz "eleman aranıyor" yerine pozisyon + deneyim seviyesi + çalışma modeli yaz.</p>
            </article>
            <article class="ep-tip-card">
              <span class="ep-tip-index">02</span>
              <h4>Maaş aralığı gösteren ilanlar 2x etkileşim alır.</h4>
              <p>Adaylar şeffaflığa değer veriyor. Net bir bant, doğru adayları getirir.</p>
            </article>
            <article class="ep-tip-card">
              <span class="ep-tip-index">03</span>
              <h4>Kısa ve net açıklama, kaliteli başvuru demektir.</h4>
              <p>Sorumlulukları 4-6 madde ile özetle, beklentileri ayrı bir blokta topla.</p>
            </article>
          </div>
        </section>
      <?php else: ?>
        <div class="ep-dukkan-grid">
          <?php foreach ($listings as $lst):
            $lId = (int) $lst['id'];
            $lTitle = (string) ($lst['title'] ?? '');
            $lType = trim((string) ($lst['employment_type'] ?? ''));
            $lModel = trim((string) ($lst['work_model'] ?? ''));
            $lLocation = trim((string) ($lst['location'] ?? ''));
            $lMin = $lst['salary_min'] !== null ? (int) $lst['salary_min'] : null;
            $lMax = $lst['salary_max'] !== null ? (int) $lst['salary_max'] : null;
            $lActive = (int) ($lst['is_active'] ?? 1) === 1;
            $lCreated = (string) ($lst['created_at'] ?? '');
            $daysSince = null;
            if ($lCreated !== '' && ($ts = strtotime($lCreated)) !== false) {
              $daysSince = (int) floor((time() - $ts) / 86400);
            }
            $salaryLabel = null;
            if ($lMin !== null && $lMax !== null) {
              $salaryLabel = number_format($lMin, 0, ',', '.') . ' – ' . number_format($lMax, 0, ',', '.') . ' ₺';
            } elseif ($lMin !== null) {
              $salaryLabel = number_format($lMin, 0, ',', '.') . ' ₺+';
            }
          ?>
          <article class="ep-poster-card">
            <div class="ep-poster-card-head">
              <h3 class="ep-poster-title">
                <a class="ep-poster-link" href="/isveren-panel.php?ilan=<?= $lId ?>">
                  <?= htmlspecialchars($lTitle ?: 'İsimsiz ilan', ENT_QUOTES, 'UTF-8') ?>
                </a>
              </h3>
              <span class="ep-poster-status<?= $lActive ? ' is-live' : '' ?>" aria-hidden="true">
                <span class="ep-poster-dot"></span><?= $lActive ? 'Aktif' : 'Pasif' ?>
              </span>
            </div>
            <?php if ($salaryLabel !== null): ?>
              <p class="ep-poster-salary"><?= htmlspecialchars($salaryLabel, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <div class="ep-poster-chips">
              <?php if ($lType !== ''): ?><span class="ep-poster-chip"><?= htmlspecialchars($lType, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
              <?php if ($lModel !== ''): ?><span class="ep-poster-chip"><?= htmlspecialchars($lModel, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
              <?php if ($lLocation !== ''): ?><span class="ep-poster-chip ep-poster-chip--ghost"><?= htmlspecialchars($lLocation, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
            </div>
            <footer class="ep-poster-foot">
              <span><strong>0</strong> başvuru</span>
              <span><strong>0</strong> görüntülenme</span>
              <?php if ($daysSince !== null): ?>
                <span class="ep-poster-time">
                  <?php
                    if ($daysSince <= 0) { echo 'bugün yayında'; }
                    elseif ($daysSince === 1) { echo '1 gün önce'; }
                    else { echo $daysSince . ' gün önce'; }
                  ?>
                </span>
              <?php endif; ?>
              <a class="ep-poster-insights" href="/mercek.php?id=<?= $lId ?>" aria-label="Mercek">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                  <circle cx="5" cy="5" r="3.2" stroke="currentColor" stroke-width="1.3"/>
                  <path d="M7.5 7.5L10 10" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                </svg>
                Mercek
              </a>
            </footer>
          </article>
          <?php endforeach; ?>

          <a class="ep-poster-card ep-poster-card--add" href="/isveren-panel.php?yeni=1" aria-label="Yeni İlan Oluştur">
            <span class="ep-poster-card-add-plus" aria-hidden="true">+</span>
            <span class="ep-poster-card-add-label">Yeni İlan</span>
          </a>
        </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

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

  <script src="frontend/assets/js/employer/topbar.js?v=<?= filemtime(__DIR__ . '/../../assets/js/employer/topbar.js') ?>" defer></script>
  <script src="frontend/assets/js/employer/panel.js?v=<?= filemtime(__DIR__ . '/../../assets/js/employer/panel.js') ?>" defer></script>
  <script src="frontend/assets/js/shared/logout-modal.js?v=<?= filemtime(__DIR__ . '/../../assets/js/shared/logout-modal.js') ?>" defer></script>
</body>
</html>
