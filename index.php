<?php

declare(strict_types=1);

/*
 * Front controller.
 *
 * Every public URL used to be its own file at the web root (akis.php,
 * mercek.php, …). Those files are gone; .htaccess (Apache, prod) and
 * router.php (php -S, local) funnel every request that isn't a real file
 * through here, and the table below maps the URL to the page that renders it.
 *
 * URLs are unchanged on purpose: /akis.php, /mercek.php?id=3, etc. still work.
 * Each page include starts its own session (exactly as before), so we must NOT
 * start one here for those routes. The home route is the lone exception — it
 * always owned its session because home-page.php doesn't start one.
 */

$PAGES = __DIR__ . '/frontend/pages';
$route = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/', '/');

switch ($route) {
    case '':
    case 'index.php':
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        require_once __DIR__ . '/backend/auth/session-helper.php';

        // Giriş yapan kullanıcı HER ZAMAN kendi paneline gider — çıkış yapmadan
        // anasayfaya (public landing) dönemez. Anonim kullanıcı home'u görür.
        $role = $_SESSION['account']['role'] ?? '';
        if ($role === 'employer') {
            header('Location: /isveren-panel.php');
            exit;
        }
        if ($role === 'seeker') {
            header('Location: /seeker-panel.php');
            exit;
        }

        require $PAGES . '/home/home-page.php';
        break;

    case 'akis.php':
        // ?id=<n> → read-only spectator view of a single listing.
        // ?tab=verenler|arayanlar → companies / job-seekers feed; default = listings.
        if (isset($_GET['id']) && (int) $_GET['id'] > 0) {
            require $PAGES . '/employer-panel/feed-detail-page.php';
        } elseif (($_GET['tab'] ?? '') === 'verenler') {
            require $PAGES . '/employer-panel/feed-employers-page.php';
        } elseif (($_GET['tab'] ?? '') === 'arayanlar') {
            require $PAGES . '/employer-panel/feed-seekers-page.php';
        } else {
            require $PAGES . '/employer-panel/feed-page.php';
        }
        break;

    case 'mercek.php':
        require $PAGES . '/employer-panel/insights-page.php';
        break;

    case 'mesajlar.php':
        require $PAGES . '/employer-panel/messages-page.php';
        break;

    case 'bildirimler.php':
        require $PAGES . '/shared/notifications-action.php';
        break;

    case 'mesaj-baslat.php':
        require $PAGES . '/shared/start-conversation.php';
        break;

    case 'basvur.php':
        require $PAGES . '/shared/apply-action.php';
        break;

    case 'kaydet.php':
        require $PAGES . '/shared/save-action.php';
        break;

    case 'basvuru.php':
        require $PAGES . '/employer-panel/applicant-page.php';
        break;

    case 'yukle.php':
        require $PAGES . '/shared/upload-action.php';
        break;

    case 'medya-sil.php':
        require $PAGES . '/shared/media-delete-action.php';
        break;

    case 'okul-ara.php':
        require $PAGES . '/shared/school-search.php';
        break;

    case 'isveren-panel.php':
        require $PAGES . '/employer-panel/dashboard-page.php';
        break;

    case 'seeker-panel.php':
        require $PAGES . '/seeker-panel/dashboard-page.php';
        break;

    case 'auth.php':
        require $PAGES . '/auth/auth-page.php';
        break;

    case 'logout.php':
        require $PAGES . '/auth/logout-page.php';
        break;

    case 'forgot-password.php':
        require $PAGES . '/auth/forgot-password-page.php';
        break;

    case 'reset-password.php':
        require $PAGES . '/auth/reset-password-page.php';
        break;

    case 'verify-email.php':
        require $PAGES . '/auth/verify-email-page.php';
        break;

    case 'resend-verification.php':
        require $PAGES . '/auth/resend-verification-page.php';
        break;

    case 'is-bul.php':
        header('Location: index.php#is-ilanlari', true, 302);
        exit;

    default:
        http_response_code(404);
        echo 'Not Found';
        break;
}
