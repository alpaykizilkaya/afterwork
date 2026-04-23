-- Adds email verification.
-- accounts.is_verified: 1 if the account's email ownership has been confirmed.
-- accounts.verified_at: timestamp of verification.
-- email_verifications: one-shot tokens sent on signup and on "resend" requests.

ALTER TABLE accounts
  ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER role,
  ADD COLUMN verified_at DATETIME NULL AFTER is_verified;

CREATE TABLE email_verifications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(255) NOT NULL,
  token VARCHAR(128) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_token (token),
  KEY idx_email (email),
  KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
