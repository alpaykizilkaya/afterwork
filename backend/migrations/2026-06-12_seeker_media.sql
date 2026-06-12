-- Seeker portföyü: CV/belge, foto, video, profil fotoğrafı.
-- Dosyalar frontend/assets/uploads/ altında tutulur; burada sadece kayıt.
CREATE TABLE IF NOT EXISTS seeker_media (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  account_id INT UNSIGNED NOT NULL,
  kind VARCHAR(16) NOT NULL,            -- avatar | image | video | doc
  file_path VARCHAR(255) NOT NULL,      -- web yolu, /frontend/assets/uploads/...
  original_name VARCHAR(255) NOT NULL,
  mime VARCHAR(100) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_acc (account_id, kind, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
