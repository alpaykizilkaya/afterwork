<?php

declare(strict_types=1);

session_start();

// DEV BYPASS — localhost only, remove before pushing to production
if (in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', 'localhost:8000', '127.0.0.1', '127.0.0.1:8000'], true)) {
    $_SESSION['account']  = ['account_id' => 0, 'email' => 'dev@localhost', 'role' => 'employer'];
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

// ── POST: create job listing ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $p('mode') === 'create_listing') {
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
            )->execute([
                'employer_id' => $employerId,
                'title'       => $jTitle,
                'type'        => $jType,
                'model'       => $jModel,
                'location'    => $jLocation,
                'desc'        => $jDesc,
                'reqs'        => $jReqs,
                'email'       => $jEmail,
                'sal_min'     => $pInt('salary_min'),
                'sal_max'     => $pInt('salary_max'),
                'benefits'    => $pNull('benefits'),
                'exp'         => $pNull('experience_level'),
                'skills'      => $pNull('skills'),
                'deadline'    => $pNull('deadline'),
                'openings'    => $pInt('openings_count'),
                'hours'       => $pNull('work_hours'),
            ]);

            $activeListings++;
            $listingSuccess = '"' . htmlspecialchars($jTitle, ENT_QUOTES, 'UTF-8') . '" ilanı başarıyla yayınlandı.';
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
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AFTERWORK | İş Veren Paneli</title>
  <link rel="stylesheet" href="frontend/assets/css/employer-panel.css?v=<?= filemtime(__DIR__ . '/../../assets/css/employer-panel.css') ?>">
  <link rel="stylesheet" href="frontend/assets/css/logout-modal.css?v=<?= filemtime(__DIR__ . '/../../assets/css/logout-modal.css') ?>">
</head>
<body>
  <div class="ep-page">

    <!-- TOPBAR -->
    <header class="ep-topbar">
      <a class="ep-brand" href="<?= htmlspecialchars(afterwork_home_url(), ENT_QUOTES, 'UTF-8') ?>" aria-label="Ana sayfaya dön">
        <img src="frontend/assets/images/afterwork-logo.png" alt="Afterwork">
      </a>
      <nav class="ep-nav" aria-label="Panel navigasyonu">
        <a href="#">İlanlarım</a>
        <a href="#">Başvurular</a>
      </nav>
      <button type="button" class="ep-exit" data-logout-trigger>Çıkış Yap</button>
    </header>

    <!-- HERO -->
    <section class="ep-hero" aria-label="Hoş geldin">
      <div class="ep-hero-inner">
        <div class="ep-hero-text">
          <p class="ep-kicker">İş Veren Paneli</p>
          <h1><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></h1>
          <p class="ep-hero-lead">Doğru adayı bul, kadroyu güçlendir.</p>
        </div>
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
      </div>
    </section>

    <!-- MAIN GRID -->
    <div class="ep-layout">

      <!-- LEFT — Şirket Profili -->
      <aside class="ep-aside">
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

      <!-- RIGHT — Yeni İlan -->
      <main class="ep-main">
        <div class="ep-main-head">
          <div>
            <h2>Yeni İlan Oluştur</h2>
            <p>Pozisyonu tanımla, doğru adaya ulaş.</p>
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
          <form class="ep-form" action="isveren-panel.php" method="post">
            <input type="hidden" name="mode" value="create_listing">

            <div class="ep-field">
              <label for="jl-title">İş Başlığı</label>
              <input id="jl-title" name="title" class="ep-input ep-input--large" type="text"
                placeholder="örn. Frontend Geliştirici, Pazarlama Uzmanı…" required>
            </div>

            <div class="ep-field-row ep-field-row--3">
              <div class="ep-field">
                <label for="jl-type">Çalışma Tipi</label>
                <select id="jl-type" name="employment_type" class="ep-select" required>
                  <option value="">Seç…</option>
                  <option>Tam Zamanlı</option>
                  <option>Yarı Zamanlı</option>
                  <option>Staj</option>
                  <option>Sözleşmeli</option>
                  <option>Freelance</option>
                </select>
              </div>
              <div class="ep-field">
                <label for="jl-model">Çalışma Modeli</label>
                <select id="jl-model" name="work_model" class="ep-select" required>
                  <option value="">Seç…</option>
                  <option>Ofiste</option>
                  <option>Uzaktan</option>
                  <option>Hibrit</option>
                </select>
              </div>
              <div class="ep-field">
                <label for="jl-location">Konum / Şehir</label>
                <input id="jl-location" name="location" class="ep-input" type="text"
                  placeholder="İstanbul" required>
              </div>
            </div>

            <div class="ep-field">
              <label for="jl-description">İş Tanımı</label>
              <textarea id="jl-description" name="description" class="ep-textarea" rows="6"
                placeholder="Bu pozisyonda ne yapılacağını, sorumlulukları ve beklentileri açıkla…" required></textarea>
            </div>

            <div class="ep-field">
              <label for="jl-requirements">Aranan Özellikler</label>
              <textarea id="jl-requirements" name="requirements" class="ep-textarea" rows="4"
                placeholder="Aday için gerekli eğitim, deneyim ve yetkinlikleri listele…" required></textarea>
            </div>

            <div class="ep-field">
              <label for="jl-email">Başvuru E-postası</label>
              <input id="jl-email" name="contact_email" class="ep-input" type="email"
                placeholder="basvuru@sirket.com" required>
            </div>

            <div class="ep-divider"></div>

            <div class="ep-chips-row">
              <div class="ep-chips-meta">
                <span class="ep-chips-label">İlana detay ekle</span>
                <span class="ep-chips-hint">isteğe bağlı</span>
              </div>
              <div class="ep-chips">
                <button type="button" class="ep-chip" data-target="jl-salary">+ Maaş Aralığı</button>
                <button type="button" class="ep-chip" data-target="jl-benefits">+ Yan Haklar</button>
                <button type="button" class="ep-chip" data-target="jl-experience">+ Deneyim Seviyesi</button>
                <button type="button" class="ep-chip" data-target="jl-skills">+ Gerekli Beceriler</button>
                <button type="button" class="ep-chip" data-target="jl-deadline">+ Son Başvuru Tarihi</button>
                <button type="button" class="ep-chip" data-target="jl-openings">+ Açık Pozisyon Sayısı</button>
                <button type="button" class="ep-chip" data-target="jl-hours">+ Çalışma Saatleri</button>
              </div>
            </div>

            <div id="jl-salary" class="ep-extra" hidden>
              <div class="ep-field-row">
                <div class="ep-field">
                  <label for="jl-salary-min">Minimum Maaş (₺)</label>
                  <input id="jl-salary-min" name="salary_min" class="ep-input" type="number" placeholder="30000">
                </div>
                <div class="ep-field">
                  <label for="jl-salary-max">Maximum Maaş (₺)</label>
                  <input id="jl-salary-max" name="salary_max" class="ep-input" type="number" placeholder="60000">
                </div>
              </div>
            </div>

            <div id="jl-benefits" class="ep-extra" hidden>
              <div class="ep-field">
                <label for="jl-benefits-text">Yan Haklar</label>
                <textarea id="jl-benefits-text" name="benefits" class="ep-textarea" rows="3"
                  placeholder="Sağlık sigortası, yemek kartı, ulaşım desteği…"></textarea>
              </div>
            </div>

            <div id="jl-experience" class="ep-extra" hidden>
              <div class="ep-field">
                <label for="jl-experience-level">Deneyim Seviyesi</label>
                <select id="jl-experience-level" name="experience_level" class="ep-select">
                  <option value="">Seç…</option>
                  <option>Deneyim Aranmıyor</option>
                  <option>Junior (0–2 yıl)</option>
                  <option>Mid-level (2–5 yıl)</option>
                  <option>Senior (5+ yıl)</option>
                  <option>Lead / Yönetici</option>
                </select>
              </div>
            </div>

            <div id="jl-skills" class="ep-extra" hidden>
              <div class="ep-field">
                <label for="jl-skills-input">Gerekli Beceriler / Teknolojiler</label>
                <input id="jl-skills-input" name="skills" class="ep-input" type="text"
                  placeholder="React, Node.js, SQL, Figma…">
                <p class="ep-field-hint">Virgülle ayırarak birden fazla beceri ekleyebilirsin.</p>
              </div>
            </div>

            <div id="jl-deadline" class="ep-extra" hidden>
              <div class="ep-field">
                <label for="jl-deadline-date">Son Başvuru Tarihi</label>
                <input id="jl-deadline-date" name="deadline" class="ep-input" type="date">
              </div>
            </div>

            <div id="jl-openings" class="ep-extra" hidden>
              <div class="ep-field">
                <label for="jl-openings-count">Açık Pozisyon Sayısı</label>
                <input id="jl-openings-count" name="openings_count" class="ep-input" type="number" placeholder="1" min="1">
              </div>
            </div>

            <div id="jl-hours" class="ep-extra" hidden>
              <div class="ep-field">
                <label for="jl-hours-input">Çalışma Saatleri</label>
                <input id="jl-hours-input" name="work_hours" class="ep-input" type="text"
                  placeholder="09:00 – 18:00, Pazartesi – Cuma">
              </div>
            </div>

            <button type="submit" class="ep-submit ep-submit--publish">
              <span>İlanı Yayınla</span>
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                <path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </button>
          </form>
        </div>
      </main>

    </div>
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

  <script src="frontend/assets/js/employer-panel.js?v=<?= filemtime(__DIR__ . '/../../assets/js/employer-panel.js') ?>" defer></script>
  <script src="frontend/assets/js/logout-modal.js?v=<?= filemtime(__DIR__ . '/../../assets/js/logout-modal.js') ?>" defer></script>
</body>
</html>
