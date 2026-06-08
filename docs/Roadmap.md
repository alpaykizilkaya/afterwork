# Roadmap

Back to [[Home]].

## Next up

- [ ] **Mesajlar (Messages) section** — `frontend/pages/employer-panel/messages-page.php`
  is still a stub. Build the real messaging UI + backend. This is the main
  pending feature.

## Backlog / ideas

- [ ] Tidy production: old per-URL `.php` files may still linger in
  `public_html` from before the front-controller refactor. Harmless, but can be
  deleted via cPanel File Manager to match the clean repo. See [[Architecture]].
- [ ] Reconcile DB schema drift — `backend/migrations/` has dated `.sql` files
  but prod schema has drifted; verify before trusting a migration.

## Done (recent)

- [x] Front controller: collapsed ~12 root entry files into `index.php` +
  `.htaccess` + `router.php`. URLs unchanged. See [[Architecture]].
- [x] Asset folders normalized — every section self-contained under
  `assets/css/<section>/` and `assets/js/<section>/`.
- [x] `seeker-dashboard.php` → `dashboard-page.php` (naming consistency).
