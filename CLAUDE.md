# Afterwork — project map

Turkish-language job platform (job seekers + employers). Plain PHP 8.4, no
framework, no Composer. MariaDB. Deployed to cPanel shared hosting by `git push`
(see `.cpanel.yml`). This file is the architecture map — read it instead of
re-exploring the tree.

## Run locally

```bash
php -S 127.0.0.1:8000 router.php
```

Use port **8000** — `feed-page.php` has a localhost-only DEV BYPASS that
auto-signs-in a fake employer for hosts `localhost:8000` / `127.0.0.1:8000`, so
the employer panels work without logging in. DB config lives in
`backend/config/config.local.php` (gitignored).

## Routing — front controller

There is **one** entry file at the web root: `index.php`. Every request that
isn't a real file is funneled to it:

- **Prod (Apache/cPanel):** `.htaccess` rewrites non-file requests to `index.php`.
- **Local (`php -S`):** `router.php` does the same.

`index.php` holds a `switch` route table mapping each URL to a page under
`frontend/pages/`. URLs keep their `.php` suffix on purpose (`/akis.php`,
`/mercek.php?id=3`, …) so nothing else had to change. To add a page: add a
`case` in `index.php` and create the page file — do NOT add a file at the root.

| URL | Page |
|-----|------|
| `/` , `/index.php` | `frontend/pages/home/home-page.php` (signed-in users redirect to their panel; `?home=1` previews home) |
| `/akis.php` | `employer-panel/feed-page.php`, or `feed-detail-page.php` when `?id=<n>` |
| `/mercek.php` | `employer-panel/insights-page.php` (per-listing analytics) |
| `/mesajlar.php` | `employer-panel/messages-page.php` (two-sided inbox, built; sending a message notifies the recipient via `backend/notifications/notify.php`) |
| `/isveren-panel.php` | `employer-panel/dashboard-page.php` (employer home + listing create/edit) |
| `/seeker-panel.php` | `seeker-panel/dashboard-page.php` |
| `/auth.php` | `auth/auth-page.php` (login + register) |
| `/logout.php` `/forgot-password.php` `/reset-password.php` `/verify-email.php` `/resend-verification.php` | matching `auth/*-page.php` |
| `/is-bul.php` | 302 → `index.php#is-ilanlari` |

`auth/google/*.php` (OAuth: start, callback, role-select) are **real files**
served directly, not routed.

## Layout

```
index.php router.php .htaccess .cpanel.yml   # web root: front controller + deploy
auth/google/                                 # Google OAuth endpoints (real files)
backend/
  auth/      session-helper, email-verification, google-helper
  config/    config.php, config.local.php (gitignored), db.php (PDO), taxonomy.php
  mail/      mailer.php
  migrations/  dated .sql files (schema has drifted from prod — verify before trusting)
  tools/     test-db.php
frontend/
  pages/<section>/*-page.php   # home / auth / employer-panel / seeker-panel
  partials/                    # employer-topbar.php
  assets/css|js/<section>/     # home / auth / employer / seeker / shared (each self-contained)
  assets/images/
```

## Conventions

- Page files are named `*-page.php` and each calls its own `session_start()` —
  the front controller does NOT start a session except on the home route.
- Pages auth-guard at the top via `$_SESSION['account']['role']`, redirecting to
  `/auth.php#giris` when unauthorized.
- Assets are cache-busted with `?v=<?= filemtime(...) ?>`; CSS/JS live under
  their section folder (`assets/css/employer/…`, `assets/js/shared/…`).
- Session helpers: `afterwork_home_url()`, `refresh_verification_flag($pdo)`.
- UI text and routes are Turkish (akis=feed, mercek=insights, mesajlar=messages,
  isveren=employer, is-bul=find-job).

## Deploy

`git push` triggers `.cpanel.yml`, which copies `frontend backend auth`, `*.php`,
and `.htaccess` to `public_html`. Prod relies on Apache `mod_rewrite` +
`AllowOverride` for `.htaccess` (standard on cPanel). Old per-URL root files may
still linger in prod from before the front-controller refactor; they're harmless
(Apache serves them directly and they still work) but can be deleted via cPanel
File Manager to fully tidy prod.
