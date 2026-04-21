-- Adds Google OAuth support to accounts.
-- Password becomes nullable (Google-only users have no password).
-- google_id is unique and nullable (existing email/password accounts have no google_id).

ALTER TABLE accounts
  MODIFY password VARCHAR(255) NULL,
  ADD COLUMN google_id VARCHAR(255) NULL AFTER email,
  ADD UNIQUE KEY uniq_google_id (google_id);
