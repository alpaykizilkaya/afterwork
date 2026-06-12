<?php

declare(strict_types=1);

/*
 * localhost-only geliştirme oturumu tohumlayıcısı.
 *
 * Yalnızca HİÇ oturum yokken verilen varsayılan rolü tohumlar. Mevcut bir
 * oturuma ASLA dokunmaz — sayfalar arası gezerken rol kendiliğinden değişmez.
 * Rolü değiştirmek için çıkış yapılır (oturum temizlenir), sonra ilgili panele
 * girilince o rol tohumlanır.
 *
 * Üretimde host kontrolü nedeniyle tamamen etkisizdir.
 */
function aw_dev_session(string $defaultRole): void
{
    $localHosts = ['localhost', 'localhost:8000', '127.0.0.1', '127.0.0.1:8000'];
    if (!in_array($_SERVER['HTTP_HOST'] ?? '', $localHosts, true)) {
        return;
    }
    if (isset($_SESSION['account'])) {
        return; // mevcut rolü koru — otomatik geçiş yok
    }

    if ($defaultRole === 'seeker') {
        $_SESSION['account'] = ['account_id' => 27, 'email' => 'aday@local.test', 'role' => 'seeker', 'is_verified' => 1];
        $_SESSION['seeker']  = ['id' => 0, 'account_id' => 27, 'email' => 'aday@local.test', 'full_name' => 'Deniz Yıldız', 'role' => 'seeker'];
    } else {
        $_SESSION['account']  = ['account_id' => 99, 'email' => 'dev@localhost', 'role' => 'employer', 'is_verified' => 1];
        $_SESSION['employer'] = ['id' => 99, 'account_id' => 99, 'email' => 'dev@localhost', 'company_name' => 'Dev Şirket', 'role' => 'employer'];
    }
}
