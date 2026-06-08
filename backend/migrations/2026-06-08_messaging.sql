-- Messaging: account-to-account conversations between an employer side and a
-- seeker side. Identity is the account (accounts.id); role lives on the account.
-- A conversation has a fixed employer side and seeker side; the page's top
-- switch is simply "which side am I on" — İş Başvuranlar (I'm the employer
-- side) vs İş Verenler (I'm the seeker side). One thread per (employer, seeker)
-- pair; listing_id records the job that started it (context only).

CREATE TABLE IF NOT EXISTS conversations (
  id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  employer_account_id    INT UNSIGNED NOT NULL,
  seeker_account_id      INT UNSIGNED NOT NULL,
  listing_id             INT UNSIGNED NULL,
  last_message_at        DATETIME NULL,
  last_sender_account_id INT UNSIGNED NULL,
  created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_pair (employer_account_id, seeker_account_id),
  KEY idx_emp    (employer_account_id, last_message_at),
  KEY idx_seeker (seeker_account_id, last_message_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id   BIGINT UNSIGNED NOT NULL,
  sender_account_id INT UNSIGNED NOT NULL,
  body              TEXT NOT NULL,
  read_at           DATETIME NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_conv   (conversation_id, created_at),
  KEY idx_unread (conversation_id, sender_account_id, read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
