<?php

declare(strict_types=1);

session_start();

// DEV BYPASS — localhost only, remove before pushing to production
if (in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', 'localhost:8000', '127.0.0.1', '127.0.0.1:8000'], true)) {
    $_SESSION['account'] = ['account_id' => 0, 'email' => 'dev@localhost', 'role' => 'seeker', 'is_verified' => 1];
    $_SESSION['seeker']  = ['id' => 0, 'account_id' => 0, 'email' => 'dev@localhost', 'full_name' => 'Dev Kullanıcı', 'role' => 'seeker'];
}

if (
    !isset($_SESSION['account'])
    || !is_array($_SESSION['account'])
    || (string) ($_SESSION['account']['role'] ?? '') !== 'seeker'
) {
    header('Location: /auth.php#giris');
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

/* ---- POST: onboarding finish OR profile edit ------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = (string) ($_POST['mode'] ?? '');

    $clean = static fn (string $k, int $max): string => mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $max, 'UTF-8');

    $fields = [
        'headline'         => $clean('headline', 120),
        'city'             => $clean('city', 80),
        'experience_level' => $clean('experience_level', 40),
        'education_level'  => $clean('education_level', 64),
        'department'       => $clean('department', 80),
        'work_pref'        => $clean('work_pref', 40),
        'skills'           => $clean('skills', 500),
        'about'            => $clean('about', 4000),
        'phone'            => $clean('phone', 32),
        'linkedin'         => $clean('linkedin', 255),
        'website'          => $clean('website', 255),
        'open_to_work'     => isset($_POST['open_to_work']) ? 1 : 0,
    ];

    if ($mode === 'onboarding' || $mode === 'profile') {
        if ($fields['headline'] === '') $errors[] = 'Kendini bir başlıkla tanıt (örn. "Frontend Geliştirici").';
        if ($fields['city'] === '')     $errors[] = 'Şehir alanı gerekli.';

        if ($errors === []) {
            try {
                $sql = 'UPDATE seekers SET
                            headline = :headline, city = :city,
                            experience_level = :experience_level, education_level = :education_level,
                            department = :department, work_pref = :work_pref, skills = :skills, about = :about,
                            phone = :phone, linkedin = :linkedin, website = :website,
                            open_to_work = :open_to_work';
                if ($mode === 'onboarding') {
                    $sql .= ', profile_completed = 1, onboarded_at = NOW()';
                }
                $sql .= ' WHERE account_id = :acc';
                $params = $fields + ['acc' => $accountId];
                $upd = $pdo->prepare($sql);
                $upd->execute($params);

                // Reload the fresh row.
                $st = $pdo->prepare('SELECT * FROM seekers WHERE account_id = :a LIMIT 1');
                $st->execute(['a' => $accountId]);
                $seekerRow = $st->fetch() ?: $seekerRow;

                if ($mode === 'onboarding') {
                    header('Location: /seeker-panel.php');
                    exit;
                }
                $flash = 'Profilin güncellendi.';
            } catch (Throwable) {
                $errors[] = 'Kaydedilemedi. (Veritabanı seeker profili için güncellenmemiş olabilir.)';
            }
        }
    }
}

/* ---- derive view state ----------------------------------------------- */
$fullName = trim((string) ($seekerRow['full_name'] ?? '')) ?: 'Aday';
$completed = (int) ($seekerRow['profile_completed'] ?? 0) === 1;
$editMode  = isset($_GET['duzenle']);

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
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AFTERWORK | <?= $completed ? 'Profilim' : 'Profilini oluştur' ?></title>
  <link rel="stylesheet" href="/frontend/assets/css/seeker/panel.css?v=<?= filemtime(__DIR__ . '/../../assets/css/seeker/panel.css') ?>">
  <link rel="stylesheet" href="/frontend/assets/css/shared/logout-modal.css?v=<?= filemtime(__DIR__ . '/../../assets/css/shared/logout-modal.css') ?>">
  <link rel="stylesheet" href="/frontend/assets/css/shared/verify-banner.css?v=<?= filemtime(__DIR__ . '/../../assets/css/shared/verify-banner.css') ?>">
</head>
<?php if (!$completed): ?>
<!-- ░░ ONBOARDING ░░ : blurred panel behind, 4-step wizard on top ░░ -->
<body class="sk-ob-body">
  <div class="sk-ob-bg" aria-hidden="true">
    <div class="sk-page">
      <?php $activeTab = 'profile'; include __DIR__ . '/../../partials/seeker-topbar.php'; ?>
      <section class="sk-hero">
        <p class="sk-kicker">Profilim</p>
        <h1>Hoş geldin, <?= $h($fullName) ?>.</h1>
        <p class="sk-lead">Profilin tamamlandığında ilanlar, başvurular ve mesajlar burada olacak.</p>
      </section>
      <div class="sk-grid">
        <div class="sk-card sk-skel"></div>
        <div class="sk-card sk-skel"></div>
        <div class="sk-card sk-skel sk-skel--wide"></div>
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
<body class="sk-body">
  <div class="sk-page">
    <?php $activeTab = 'profile'; include __DIR__ . '/../../partials/seeker-topbar.php'; ?>

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

    <section class="sk-hero">
      <p class="sk-kicker">Profilim</p>
      <h1><?= $h($fullName) ?></h1>
      <?php if ($g('headline') !== ''): ?><p class="sk-hero-headline"><?= $h($g('headline')) ?><?php if ($g('city') !== ''): ?> · <?= $h($g('city')) ?><?php endif; ?></p><?php endif; ?>
      <div class="sk-hero-actions">
        <?php if (!$editMode): ?>
          <a class="sk-btn" href="/seeker-panel.php?duzenle=1">Profili düzenle</a>
        <?php else: ?>
          <a class="sk-btn sk-btn--ghost" href="/seeker-panel.php">Vazgeç</a>
        <?php endif; ?>
        <?php if ((int) ($seekerRow['open_to_work'] ?? 1) === 1): ?>
          <span class="sk-pill sk-pill--open">İşe açık</span>
        <?php endif; ?>
      </div>
    </section>

    <?php if ($editMode): ?>
      <!-- EDIT FORM -->
      <form class="sk-card sk-form" method="post" action="/seeker-panel.php">
        <input type="hidden" name="mode" value="profile">
        <div class="sk-form-grid">
          <label class="sk-field"><span>Başlık</span><input type="text" name="headline" value="<?= $h($g('headline')) ?>" maxlength="120" required></label>
          <label class="sk-field"><span>Şehir</span><input type="text" name="city" value="<?= $h($g('city')) ?>" maxlength="80" required></label>
          <label class="sk-field"><span>Deneyim seviyesi</span><select name="experience_level"><?= $opts($tax['experience_levels'], $g('experience_level')) ?></select></label>
          <label class="sk-field"><span>Eğitim seviyesi</span><select name="education_level"><?= $opts($tax['education_levels'], $g('education_level')) ?></select></label>
          <label class="sk-field"><span>Alan / departman</span><select name="department"><?= $opts($tax['departments'], $g('department')) ?></select></label>
          <label class="sk-field"><span>Çalışma tercihi</span><select name="work_pref"><?= $opts($tax['work_models'], $g('work_pref')) ?></select></label>
          <label class="sk-field"><span>Beceriler <small>(virgülle)</small></span><input type="text" name="skills" value="<?= $h($g('skills')) ?>" maxlength="500"></label>
          <label class="sk-field"><span>Telefon</span><input type="text" name="phone" value="<?= $h($g('phone')) ?>" maxlength="32"></label>
          <label class="sk-field"><span>LinkedIn</span><input type="text" name="linkedin" value="<?= $h($g('linkedin')) ?>" maxlength="255"></label>
          <label class="sk-field"><span>Web / portfolyo</span><input type="text" name="website" value="<?= $h($g('website')) ?>" maxlength="255"></label>
          <label class="sk-field sk-field--wide"><span>Hakkında</span><textarea name="about" rows="4" maxlength="4000"><?= $h($g('about')) ?></textarea></label>
          <label class="sk-check sk-field--wide"><input type="checkbox" name="open_to_work" <?= (int) ($seekerRow['open_to_work'] ?? 1) === 1 ? 'checked' : '' ?>><span>Yeni iş fırsatlarına açığım</span></label>
        </div>
        <div class="sk-form-foot">
          <button type="submit" class="sk-btn sk-btn--solid">Kaydet</button>
          <a class="sk-btn sk-btn--ghost" href="/seeker-panel.php">Vazgeç</a>
        </div>
      </form>
    <?php else: ?>
      <!-- DISPLAY -->
      <div class="sk-grid">
        <article class="sk-card">
          <p class="sk-card-kicker">Hakkında</p>
          <?php if ($g('about') !== ''): ?>
            <p class="sk-about"><?= nl2br($h($g('about'))) ?></p>
          <?php else: ?>
            <p class="sk-muted">Henüz bir tanıtım eklemedin.</p>
          <?php endif; ?>
        </article>

        <article class="sk-card">
          <p class="sk-card-kicker">Özet</p>
          <ul class="sk-meta">
            <?php if ($g('experience_level') !== ''): ?><li><span>Deneyim</span><strong><?= $h($g('experience_level')) ?></strong></li><?php endif; ?>
            <?php if ($g('education_level') !== ''): ?><li><span>Eğitim</span><strong><?= $h($g('education_level')) ?></strong></li><?php endif; ?>
            <?php if ($g('department') !== ''): ?><li><span>Alan</span><strong><?= $h($g('department')) ?></strong></li><?php endif; ?>
            <?php if ($g('work_pref') !== ''): ?><li><span>Çalışma</span><strong><?= $h($g('work_pref')) ?></strong></li><?php endif; ?>
            <?php if ($g('city') !== ''): ?><li><span>Şehir</span><strong><?= $h($g('city')) ?></strong></li><?php endif; ?>
          </ul>
        </article>

        <article class="sk-card sk-card--wide">
          <p class="sk-card-kicker">Beceriler</p>
          <?php
          $skills = array_values(array_filter(array_map('trim', preg_split('/[,;\/]+/u', $g('skills')) ?: [])));
          if ($skills !== []): ?>
            <div class="sk-chips">
              <?php foreach ($skills as $s): ?><span class="sk-chip"><?= $h($s) ?></span><?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="sk-muted">Henüz beceri eklemedin.</p>
          <?php endif; ?>

          <?php
          $contacts = [];
          if ($g('phone') !== '')    $contacts[] = ['Telefon', $g('phone'), null];
          if ($g('linkedin') !== '') $contacts[] = ['LinkedIn', $g('linkedin'), (str_starts_with($g('linkedin'), 'http') ? $g('linkedin') : 'https://' . $g('linkedin'))];
          if ($g('website') !== '')  $contacts[] = ['Web', $g('website'), (str_starts_with($g('website'), 'http') ? $g('website') : 'https://' . $g('website'))];
          if ($contacts !== []): ?>
            <div class="sk-contacts">
              <?php foreach ($contacts as [$lab, $val, $url]): ?>
                <?php if ($url !== null): ?>
                  <a class="sk-contact" href="<?= $h($url) ?>" target="_blank" rel="noopener"><span><?= $h($lab) ?></span><?= $h($val) ?></a>
                <?php else: ?>
                  <span class="sk-contact"><span><?= $h($lab) ?></span><?= $h($val) ?></span>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </article>
      </div>
    <?php endif; ?>
  </div>

  <?php include __DIR__ . '/_logout-modal.php'; ?>
  <script src="/frontend/assets/js/shared/logout-modal.js?v=<?= filemtime(__DIR__ . '/../../assets/js/shared/logout-modal.js') ?>" defer></script>
</body>
<?php endif; ?>
</html>
