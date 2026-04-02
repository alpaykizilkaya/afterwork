<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/db.php';

$errors = [];
$success = null;
$activeTab = 'giris';
$selectedRole = '';

$loginEmailValue = '';
$registerUsernameValue = '';
$registerEmailValue = '';
$isRegisterDetails = false;
$registerNameLabel = 'Kullanıcı Adı';

if (isset($_GET['status']) && $_GET['status'] === 'registered') {
    $success = 'Kayıt tamamlandı. Şimdi giriş yapabilirsin.';
    $activeTab = 'giris';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? '';

    if ($mode === 'register') {
        $activeTab = 'kayit';

        $selectedRole = trim((string) ($_POST['role'] ?? ''));
        $registerUsernameValue = trim((string) ($_POST['username'] ?? ''));
        $registerEmailValue = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $isRegisterDetails = $selectedRole !== '';

        if ($selectedRole === '') {
            $errors[] = 'Lütfen önce bir rol seç.';
        }

        if ($registerUsernameValue === '') {
            $errors[] = 'Kullanıcı adı zorunludur.';
        }

        if (!filter_var($registerEmailValue, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Geçerli bir e-posta gir.';
        }

        if (strlen($password) < 6) {
            $errors[] = 'Şifre en az 6 karakter olmalı.';
        }

        if ($selectedRole !== '' && !in_array($selectedRole, ['employer', 'seeker'], true)) {
            $errors[] = 'Geçersiz rol seçimi.';
        }

        if ($errors === []) {
            $pdo = null;

            try {
                $pdo = db();
                $pdo->beginTransaction();

                $passwordHash = password_hash($password, PASSWORD_BCRYPT);

                $accountStmt = $pdo->prepare(
                    'INSERT INTO accounts (email, password, role) VALUES (:email, :password, :role)'
                );
                $accountStmt->execute([
                    'email' => $registerEmailValue,
                    'password' => $passwordHash,
                    'role' => $selectedRole,
                ]);

                $accountId = (int) $pdo->lastInsertId();

                if ($selectedRole === 'employer') {
                    $profileStmt = $pdo->prepare(
                        'INSERT INTO employers (account_id, company_name) VALUES (:account_id, :company_name)'
                    );
                    $profileStmt->execute([
                        'account_id' => $accountId,
                        'company_name' => $registerUsernameValue,
                    ]);
                } else {
                    $profileStmt = $pdo->prepare(
                        'INSERT INTO seekers (account_id, full_name) VALUES (:account_id, :full_name)'
                    );
                    $profileStmt->execute([
                        'account_id' => $accountId,
                        'full_name' => $registerUsernameValue,
                    ]);
                }

                $pdo->commit();

                header('Location: auth.php?status=registered#giris');
                exit;
            } catch (Throwable $e) {
                if ($pdo instanceof PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $isDuplicate = $e instanceof PDOException && $e->getCode() === '23000';
                if ($isDuplicate) {
                    $errors[] = 'Bu e-posta ile daha önce kayıt yapılmış.';
                } else {
                    $errors[] = 'Kayıt sırasında hata oluştu: ' . $e->getMessage();
                }
            }
        }
    }

    if ($mode === 'login') {
        $activeTab = 'giris';

        $loginEmailValue = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (!filter_var($loginEmailValue, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Geçerli bir e-posta gir.';
        }

        if ($password === '') {
            $errors[] = 'Şifre zorunludur.';
        }

        if ($errors === []) {
            try {
                $pdo = db();
                $loginStmt = $pdo->prepare(
                    'SELECT id, email, password, role FROM accounts WHERE email = :email LIMIT 1'
                );
                $loginStmt->execute([
                    'email' => $loginEmailValue,
                ]);

                $account = $loginStmt->fetch();

                if (!$account || !password_verify($password, $account['password'])) {
                    $errors[] = 'E-posta veya şifre hatalı.';
                } else {
                    $_SESSION['account'] = [
                        'account_id' => (int) $account['id'],
                        'email' => $account['email'],
                        'role' => $account['role'],
                    ];

                    if ($account['role'] === 'employer') {
                        $profileStmt = $pdo->prepare(
                            'SELECT id, company_name FROM employers WHERE account_id = :account_id LIMIT 1'
                        );
                        $profileStmt->execute([
                            'account_id' => (int) $account['id'],
                        ]);
                        $profile = $profileStmt->fetch();

                        $_SESSION['employer'] = [
                            'id' => $profile ? (int) $profile['id'] : null,
                            'account_id' => (int) $account['id'],
                            'email' => $account['email'],
                            'company_name' => $profile['company_name'] ?? '',
                            'role' => 'employer',
                        ];

                        unset($_SESSION['seeker']);

                        header('Location: isveren-panel.php');
                        exit;
                    }

                    if ($account['role'] === 'seeker') {
                        $profileStmt = $pdo->prepare(
                            'SELECT id, full_name FROM seekers WHERE account_id = :account_id LIMIT 1'
                        );
                        $profileStmt->execute([
                            'account_id' => (int) $account['id'],
                        ]);
                        $profile = $profileStmt->fetch();

                        $_SESSION['seeker'] = [
                            'id' => $profile ? (int) $profile['id'] : null,
                            'account_id' => (int) $account['id'],
                            'email' => $account['email'],
                            'full_name' => $profile['full_name'] ?? '',
                            'role' => 'seeker',
                        ];

                        unset($_SESSION['employer']);

                        header('Location: seeker-panel.php');
                        exit;
                    }

                    $errors[] = 'Desteklenmeyen rol.';
                }
            } catch (Throwable $e) {
                $errors[] = 'Giriş sırasında hata oluştu: ' . $e->getMessage();
            }
        }
    }
}

if ($selectedRole === 'employer') {
    $registerNameLabel = 'Şirket Adı';
} elseif ($selectedRole === 'seeker') {
    $registerNameLabel = 'Ad Soyad';
}
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AFTERWORK | Hesap</title>
  <link rel="stylesheet" href="auth.css?v=<?= filemtime(__DIR__ . '/auth.css') ?>">
</head>
<body>
  <main class="auth-page">
    <a class="auth-brand" href="index.php#ana-sayfa" aria-label="Ana sayfaya dön">
      <img src="afterwork-logo.png" alt="Afterwork">
    </a>

    <section class="auth-shell" aria-label="Hesap işlemleri">
      <div class="auth-intro">
        <p class="auth-kicker">Premium kariyer deneyimi</p>
        <h1>Doğru eşleşme,<br>hızlı başlangıç.</h1>
        <p class="auth-lead">
          AFTERWORK, doğrulanmış işverenler ve sade başvuru deneyimiyle kariyer yolculuğunu daha net,
          daha güvenilir ve daha seçkin hale getirir.
        </p>
      </div>

      <section class="auth-card">
        <?php if ($success !== null): ?>
          <p class="auth-feedback is-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
          <div class="auth-feedback is-error" role="alert">
            <?php foreach ($errors as $error): ?>
              <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="auth-tabs" role="tablist" aria-label="Hesap sekmeleri">
          <a id="tab-giris" class="auth-tab<?= $activeTab === 'giris' ? ' is-active' : '' ?>" href="#giris" role="tab" aria-selected="<?= $activeTab === 'giris' ? 'true' : 'false' ?>" aria-controls="panel-giris">Giriş Yap</a>
          <a id="tab-kayit" class="auth-tab<?= $activeTab === 'kayit' ? ' is-active' : '' ?>" href="#kayit" role="tab" aria-selected="<?= $activeTab === 'kayit' ? 'true' : 'false' ?>" aria-controls="panel-kayit">Kayıt Ol</a>
        </div>

        <section id="panel-giris" class="auth-panel<?= $activeTab === 'giris' ? ' is-active' : '' ?>" role="tabpanel" aria-labelledby="tab-giris"<?= $activeTab === 'giris' ? '' : ' hidden' ?>>
          <p class="auth-panel-kicker">AFTERWORK'a giriş yap</p>
          <h2>Tekrar hoş geldin</h2>
          <p>Hesabına giriş yap, fırsatları keşfet ve süreci tek yerden yönet.</p>

          <div class="auth-login-stack">
            <button type="button" class="auth-google" aria-disabled="true">
              <span class="auth-google-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" role="img" focusable="false">
                  <path fill="#EA4335" d="M12 10.2v3.9h5.4c-.24 1.26-.96 2.33-2.04 3.05l3.3 2.56c1.92-1.77 3.03-4.38 3.03-7.49 0-.72-.06-1.41-.18-2.08H12z"/>
                  <path fill="#34A853" d="M12 22c2.7 0 4.96-.89 6.61-2.42l-3.3-2.56c-.92.62-2.09.99-3.31.99-2.55 0-4.71-1.72-5.48-4.03l-3.41 2.63C4.76 19.93 8.08 22 12 22z"/>
                  <path fill="#4A90E2" d="M6.52 13.98A5.98 5.98 0 0 1 6.2 12c0-.69.12-1.36.32-1.98L3.1 7.39A9.95 9.95 0 0 0 2 12c0 1.61.39 3.13 1.1 4.45l3.42-2.47z"/>
                  <path fill="#FBBC05" d="M12 5.98c1.47 0 2.79.51 3.83 1.5l2.87-2.87C16.95 2.98 14.69 2 12 2 8.08 2 4.76 4.07 3.1 7.39l3.42 2.63C7.29 7.7 9.45 5.98 12 5.98z"/>
                </svg>
              </span>
              <span>Google ile devam et</span>
            </button>
            <p class="auth-divider"><span>veya</span></p>

            <form id="login-form" class="auth-form" action="auth.php#giris" method="post">
              <input type="hidden" name="mode" value="login">

              <label for="login-email">E-posta adresin</label>
              <input id="login-email" name="email" type="email" autocomplete="email" placeholder="ornek@eposta.com" value="<?= htmlspecialchars($loginEmailValue, ENT_QUOTES, 'UTF-8') ?>" required>

              <label for="login-password">Şifren</label>
              <input id="login-password" name="password" type="password" autocomplete="current-password" placeholder="Şifreni gir" required>

              <button type="submit" class="auth-submit">E-posta ile devam et</button>
            </form>
          </div>
        </section>

        <section id="panel-kayit" class="auth-panel<?= $activeTab === 'kayit' ? ' is-active' : '' ?>" role="tabpanel" aria-labelledby="tab-kayit"<?= $activeTab === 'kayit' ? '' : ' hidden' ?>>
          <p class="auth-panel-kicker">Yeni hesap</p>
          <h2>AFTERWORK'a katıl</h2>
          <p>Rolünü seç, hesabını oluştur ve sana uygun deneyimle devam et.</p>

          <div id="register-flow" class="register-flow" data-step="<?= $isRegisterDetails ? 'details' : 'choose' ?>">
          <div id="register-step-choose" class="register-step register-step-choose"<?= $isRegisterDetails ? ' hidden' : '' ?>>
            <h3>Önce rolünü seç</h3>
            <p>Kayıt adımına geçmek için bir yol seç.</p>

            <div class="role-choice" aria-label="Rol seçimi">
              <button type="button" class="role-card-option<?= $selectedRole === 'employer' ? ' is-selected' : '' ?>" data-role="employer">
                <img src="ChatGPT Image 11 Şub 2026 19_31_45.png" alt="İş Veren görseli">
                <strong>İş Ver</strong>
              </button>

              <button type="button" class="role-card-option<?= $selectedRole === 'seeker' ? ' is-selected' : '' ?>" data-role="seeker">
                <img src="hero-demo.png" alt="İş Bul görseli">
                <strong>İş Bul</strong>
              </button>
            </div>
          </div>

          <div id="register-step-details" class="register-step register-step-details"<?= $isRegisterDetails ? '' : ' hidden' ?>>
            <div class="register-step-head">
              <p class="register-step-kicker">Rol seçimi tamamlandı</p>
              <button id="register-back" type="button" class="register-back">Geri</button>
            </div>

            <h3>Hesabını oluştur</h3>
            <p>Seçimine göre bilgilerini tamamla.</p>

            <form id="register-form" class="auth-form register-form" action="auth.php#kayit" method="post">
              <input type="hidden" name="mode" value="register">
              <input id="register-role" type="hidden" name="role" value="<?= htmlspecialchars($selectedRole, ENT_QUOTES, 'UTF-8') ?>">

              <label id="register-username-label" for="register-username"><?= htmlspecialchars($registerNameLabel, ENT_QUOTES, 'UTF-8') ?></label>
              <input id="register-username" name="username" type="text" autocomplete="username" value="<?= htmlspecialchars($registerUsernameValue, ENT_QUOTES, 'UTF-8') ?>" required>

              <label for="register-email">E-posta</label>
              <input id="register-email" name="email" type="email" autocomplete="email" value="<?= htmlspecialchars($registerEmailValue, ENT_QUOTES, 'UTF-8') ?>" required>

              <label for="register-password">Şifre</label>
              <input id="register-password" name="password" type="password" autocomplete="new-password" required>

              <button type="submit" class="auth-submit">Kayıt Ol</button>
            </form>
          </div>
          </div>
        </section>
      </section>
    </section>
  </main>

  <script src="auth.js?v=<?= filemtime(__DIR__ . '/auth.js') ?>" defer></script>
</body>
</html>
