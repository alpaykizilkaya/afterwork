-- Seeker profile enrichment.
-- Mirrors how `employers` holds the full company profile: ALL job-seeker data
-- lives on the `seekers` table (one row per account). The 4-step onboarding
-- wizard fills these; profile_completed flips to 1 when it's finished.

ALTER TABLE seekers
  ADD COLUMN headline          VARCHAR(120) NULL AFTER full_name,
  ADD COLUMN city              VARCHAR(80)  NULL AFTER headline,
  ADD COLUMN experience_level  VARCHAR(40)  NULL AFTER city,
  ADD COLUMN education_level   VARCHAR(64)  NULL AFTER experience_level,
  ADD COLUMN department        VARCHAR(80)  NULL AFTER education_level,
  ADD COLUMN work_pref         VARCHAR(40)  NULL AFTER department,
  ADD COLUMN skills            VARCHAR(500) NULL AFTER work_pref,
  ADD COLUMN about             TEXT         NULL AFTER skills,
  ADD COLUMN phone             VARCHAR(32)  NULL AFTER about,
  ADD COLUMN linkedin          VARCHAR(255) NULL AFTER phone,
  ADD COLUMN website           VARCHAR(255) NULL AFTER linkedin,
  ADD COLUMN open_to_work      TINYINT(1)   NOT NULL DEFAULT 1 AFTER website,
  ADD COLUMN profile_completed TINYINT(1)   NOT NULL DEFAULT 0 AFTER open_to_work,
  ADD COLUMN onboarded_at      DATETIME     NULL AFTER profile_completed;
