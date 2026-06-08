# Afterwork — notes

Vault home. These notes are for **you** to browse in Obsidian; the canonical
machine-read map is [`../CLAUDE.md`](../CLAUDE.md) (auto-loaded into Claude every
session). Keep durable facts here, link liberally with `[[wikilinks]]`.

## Index

- [[Roadmap]] — what's next, in priority order
- [[Architecture]] — how the app is wired (quick version; full map in CLAUDE.md)
- [[Dev-notes]] — running locally, gotchas, scratch

## Quick facts

- Plain PHP 8.4, no framework. MariaDB. Deploys to cPanel by `git push`.
- One web-root entry point: `index.php` (front controller). URLs keep `.php`.
- Run locally: `php -S 127.0.0.1:8000 router.php` (use port **8000** for the
  localhost dev-bypass auto-login).
