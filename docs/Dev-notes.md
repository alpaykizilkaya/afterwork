# Dev notes

Back to [[Home]].

## Run locally

```bash
php -S 127.0.0.1:8000 router.php
```

- Use port **8000** — `feed-page.php` has a localhost-only DEV BYPASS that
  auto-signs-in a fake employer for hosts `localhost:8000` / `127.0.0.1:8000`,
  so the employer panels work without logging in.
- DB config: `backend/config/config.local.php` (gitignored). Local MariaDB via
  Homebrew (`brew services` to start/stop).

## Gotchas

- **Sessions:** page files start their own session — don't add a global
  `session_start()` to the front controller (double-start warning). See
  [[Architecture]].
- **Schema drift:** prod DB has drifted from `backend/migrations/`. Verify a
  table/column actually exists before relying on a migration file.
- **Deploy:** `git push` runs `.cpanel.yml`, copying `frontend backend auth`,
  `*.php`, and `.htaccess` to `public_html`. Prod needs Apache `mod_rewrite`.
- Remove the DEV BYPASS block before relying on real auth in any prod-like test.

## Scratch

_(running log — add dated notes as you go)_
