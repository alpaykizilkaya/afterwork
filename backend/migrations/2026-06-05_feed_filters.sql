-- Adds the columns that back the Akış feed filters.
-- Each one is an attribute an employer selects when posting a listing, so the
-- feed can filter on real stored values (no fake/placeholder data).
--
-- job_listings:
--   department        Departman          (taxonomy: departments)
--   position_level    Pozisyon Seviyesi  (taxonomy: position_levels)
--   education_level   Eğitim Seviyesi    (taxonomy: education_levels)
--   listing_language  İlan Dili          (taxonomy: languages)
--   district          İlçe               (free text within a city)
--   is_disability     Engelli İlanı      (1 = listing is for disabled candidates)
-- employers:
--   is_iso500         "ISO 500 Şirketleri" company feature flag

-- Plain, widely-compatible syntax (no IF NOT EXISTS — some MySQL/phpMyAdmin
-- builds reject it). On a fresh install all columns are added; on an environment
-- where one already exists (e.g. production already had `district`), drop that
-- single line before running.

ALTER TABLE job_listings
  ADD COLUMN department       VARCHAR(80)  NULL              AFTER experience_level,
  ADD COLUMN position_level   VARCHAR(64)  NULL              AFTER department,
  ADD COLUMN education_level  VARCHAR(64)  NULL              AFTER position_level,
  ADD COLUMN listing_language VARCHAR(16)  NOT NULL DEFAULT 'Türkçe' AFTER education_level,
  ADD COLUMN district         VARCHAR(80)  NULL              AFTER location,
  ADD COLUMN is_disability    TINYINT(1)   NOT NULL DEFAULT 0 AFTER listing_language;

ALTER TABLE employers
  ADD COLUMN is_iso500 TINYINT(1) NOT NULL DEFAULT 0 AFTER company_size;

-- Optional indexes (filtering performance only — run after columns succeed,
-- skip any "Duplicate key").
ALTER TABLE job_listings
  ADD INDEX idx_department (department),
  ADD INDEX idx_position_level (position_level),
  ADD INDEX idx_education_level (education_level),
  ADD INDEX idx_listing_language (listing_language),
  ADD INDEX idx_is_disability (is_disability);

ALTER TABLE employers
  ADD INDEX idx_is_iso500 (is_iso500);
