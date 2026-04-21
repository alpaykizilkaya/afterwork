<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../backend/config/db.php';

if (empty($_SESSION['google_pending']) || !is_array($_SESSION['google_pending'])) {
    header('Location: /auth.php');
    exit;
}

$pending = $_SESSION['google_pending'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = (string) ($_POST['role'] ?? '');

    if (!in_array($role, ['employer', 'seeker'], true)) {
        $errors[] = 'Lütfen bir rol seç.';
    }

    if ($errors === []) {
        try {
            $pdo = db();
            $pdo->beginTransaction();

            $insert = $pdo->prepare(
                'INSERT INTO accounts (email, google_id, password, role) VALUES (:email, :google_id, NULL, :role)'
            );
            $insert->execute([
                'email' => $pending['email'],
                'google_id' => $pending['google_id'],
                'role' => $role,
            ]);

            $accountId = (int) $pdo->lastInsertId();
            $displayName = $pending['name'] !== '' ? $pending['name'] : $pending['email'];

            if ($role === 'employer') {
                $stmt = $pdo->prepare('INSERT INTO employers (account_id, company_name) VALUES (:account_id, :company_name)');
                $stmt->execute(['account_id' => $accountId, 'company_name' => $displayName]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO seekers (account_id, full_name) VALUES (:account_id, :full_name)');
                $stmt->execute(['account_id' => $accountId, 'full_name' => $displayName]);
            }

            $pdo->commit();
            unset($_SESSION['google_pending']);

            $_SESSION['account'] = [
                'account_id' => $accountId,
                'email' => $pending['email'],
                'role' => $role,
            ];

            if ($role === 'employer') {
                $_SESSION['employer'] = [
                    'id' => null,
                    'account_id' => $accountId,
                    'email' => $pending['email'],
                    'company_name' => $displayName,
                    'role' => 'employer',
                ];
                unset($_SESSION['seeker']);
                header('Location: /isveren-panel.php');
                exit;
            }

            $_SESSION['seeker'] = [
                'id' => null,
                'account_id' => $accountId,
                'email' => $pending['email'],
                'full_name' => $displayName,
                'role' => 'seeker',
            ];
            unset($_SESSION['employer']);
            header('Location: /seeker-panel.php');
            exit;
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Hesap oluşturulamadı: ' . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AFTERWORK | Rol Seçimi</title>
  <link rel="stylesheet" href="/frontend/assets/css/auth.css">
</head>
<body>
  <main class="auth-page">
    <a class="auth-brand" href="/index.php#ana-sayfa" aria-label="Ana sayfaya dön">
      <img src="/frontend/assets/images/afterwork-logo.png" alt="Afterwork">
    </a>

    <section class="auth-shell">
      <div class="auth-intro">
        <p class="auth-kicker">Son bir adım</p>
        <h1>Hesabını tamamla</h1>
        <p class="auth-lead">
          Google hesabın <strong><?= htmlspecialchars($pending['email'], ENT_QUOTES, 'UTF-8') ?></strong> ile devam ediyoruz. Devam etmek için rolünü seç.
        </p>
      </div>

      <section class="auth-card">
        <?php if ($errors !== []): ?>
          <div class="auth-feedback is-error" role="alert">
            <?php foreach ($errors as $error): ?>
              <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="post" action="/auth/google/role-select.php" class="auth-form">
          <h2>Rolünü seç</h2>
          <p>AFTERWORK deneyimini sana göre uyarlayabilmemiz için tek bir seçim yapman yeterli.</p>

          <div class="role-choice">
            <button type="submit" name="role" value="employer" class="role-card-option">
              <img src="/frontend/assets/images/trusted-company-placeholder.png" alt="İş Veren görseli">
              <strong>İş Ver</strong>
            </button>
            <button type="submit" name="role" value="seeker" class="role-card-option">
              <img src="/frontend/assets/images/hero-demo.png" alt="İş Bul görseli">
              <strong>İş Bul</strong>
            </button>
          </div>
        </form>
      </section>
    </section>
  </main>
</body>
</html>
