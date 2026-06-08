-- General notifications shown in the topbar bell.
-- notifications: platform-wide announcements/updates. `audience` targets a role
--   ('all' | 'employer' | 'seeker') so the same feed can serve both panels.
-- notification_reads: per-account read state. Unread = a notification with no
--   matching read row for that account.

CREATE TABLE IF NOT EXISTS notifications (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title      VARCHAR(160) NOT NULL,
  body       VARCHAR(500) NOT NULL DEFAULT '',
  audience   VARCHAR(16)  NOT NULL DEFAULT 'all',
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audience_created (audience, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_reads (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  notification_id BIGINT UNSIGNED NOT NULL,
  account_id      INT UNSIGNED NOT NULL,
  read_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_notif_account (notification_id, account_id),
  KEY idx_account (account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
