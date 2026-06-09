-- Per-application analytics so Mercek can split "applicants" from mere "viewers".
-- We capture the same context for an application that we already capture for a
-- view: where the applicant came from, on what device, and (coarse) from where.
-- Stored derived-only (never the raw IP), same as listing_views.

ALTER TABLE listing_applications
  ADD COLUMN traffic_source VARCHAR(32) NULL AFTER status,
  ADD COLUMN device_type    VARCHAR(16) NULL AFTER traffic_source,
  ADD COLUMN country_code   CHAR(2)     NULL AFTER device_type,
  ADD COLUMN country        VARCHAR(64) NULL AFTER country_code,
  ADD COLUMN city           VARCHAR(96) NULL AFTER country;
