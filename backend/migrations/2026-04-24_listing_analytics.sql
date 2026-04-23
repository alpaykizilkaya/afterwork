-- Analytics tracking for job listings.
-- listing_views: one row per page view (authenticated or anonymous by session hash).
-- listing_saves: a seeker bookmarking a listing.
-- listing_applications: an application submitted by a seeker.
-- All support soft-delete style (no delete; use is_active flags in parent).

CREATE TABLE listing_views (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  listing_id INT UNSIGNED NOT NULL,
  viewer_account_id INT UNSIGNED NULL,
  viewer_session_hash CHAR(64) NULL,
  referrer VARCHAR(512) NULL,
  traffic_source VARCHAR(32) NULL,
  device_type VARCHAR(16) NULL,
  user_agent VARCHAR(512) NULL,
  viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_listing_viewed (listing_id, viewed_at),
  KEY idx_session (viewer_session_hash),
  KEY idx_account (viewer_account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE listing_saves (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  listing_id INT UNSIGNED NOT NULL,
  seeker_account_id INT UNSIGNED NOT NULL,
  saved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_listing_seeker (listing_id, seeker_account_id),
  KEY idx_listing_saved (listing_id, saved_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE listing_applications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  listing_id INT UNSIGNED NOT NULL,
  seeker_account_id INT UNSIGNED NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'submitted',
  started_at DATETIME NULL,
  submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_listing_seeker (listing_id, seeker_account_id),
  KEY idx_listing_submitted (listing_id, submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
