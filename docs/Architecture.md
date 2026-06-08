# Architecture

Back to [[Home]]. Full machine-read map: [`../CLAUDE.md`](../CLAUDE.md).

## Routing — front controller

Every request that isn't a real file is funneled to **`index.php`**, which holds
a `switch` route table mapping each URL to a page under `frontend/pages/`.

- Prod (Apache/cPanel): `.htaccess` rewrites non-file requests to `index.php`.
- Local (`php -S`): `router.php` does the same.

URLs keep their `.php` suffix on purpose (`/akis.php`, `/mercek.php?id=3`) so no
links or redirects had to change. **To add a page:** add a `case` in `index.php`
and create the page file — do _not_ drop a new file at the web root.

`auth/google/*.php` (OAuth) are real files served directly, not routed.

## Turkish ↔ English route names

| URL | Meaning | Page |
|-----|---------|------|
| `akis` | feed | employer-panel/feed-page (or feed-detail when `?id`) |
| `mercek` | insights | employer-panel/insights-page |
| `mesajlar` | messages | employer-panel/messages-page (**stub** — see [[Roadmap]]) |
| `isveren-panel` | employer panel | employer-panel/dashboard-page |
| `seeker-panel` | seeker panel | seeker-panel/dashboard-page |
| `is-bul` | find job | 302 → home `#is-ilanlari` |

## Layout

```
index.php router.php .htaccess .cpanel.yml   # web root
auth/google/                                 # OAuth (real files)
backend/   auth · config · mail · migrations · tools
frontend/  pages/<section>/*-page.php · partials/ · assets/{css,js}/<section>/
```

## Conventions

- Each `*-page.php` calls its own `session_start()`; the front controller only
  starts a session on the home route.
- Pages auth-guard at the top on `$_SESSION['account']['role']`, redirecting to
  `/auth.php#giris`.
- Assets cache-busted with `?v=<?= filemtime(...) ?>`.
