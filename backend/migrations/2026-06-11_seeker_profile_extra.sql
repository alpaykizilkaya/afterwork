-- Richer seeker profile fields, mapped to the feed filters so a seeker's
-- profile is fully matchable (and every section is independently fillable,
-- LinkedIn-style). All optional — only the onboarding fields are required.

ALTER TABLE seekers
  ADD COLUMN position_level     VARCHAR(64)  NULL AFTER department,
  ADD COLUMN employment_type    VARCHAR(40)  NULL AFTER position_level,
  ADD COLUMN sector             VARCHAR(80)  NULL AFTER employment_type,
  ADD COLUMN languages          VARCHAR(160) NULL AFTER sector,
  ADD COLUMN district           VARCHAR(80)  NULL AFTER city,
  ADD COLUMN salary_expectation INT          NULL AFTER languages,
  ADD COLUMN is_disability      TINYINT(1)   NOT NULL DEFAULT 0 AFTER salary_expectation,
  ADD COLUMN school             VARCHAR(160) NULL AFTER education_level;
