-- Two additions on 2026-06-09:
--
-- 1) Per-account notification targeting.
--    The original notifications table only broadcast to a `audience` role.
--    To send "you got a new message" / "a new application arrived" to ONE
--    person we add `account_id` (NULL = broadcast by audience, as before) and
--    an optional `url` the bell item links to.
--
-- 2) Coarse geolocation on listing views (for the Mercek world map).
--    We derive country/city from the visitor IP at view time and store ONLY
--    the derived location — never the raw IP — so the map is real without
--    keeping personal data. (KVKK: data minimisation.)

ALTER TABLE notifications
  ADD COLUMN account_id INT UNSIGNED NULL AFTER body,
  ADD COLUMN url        VARCHAR(255) NULL AFTER account_id,
  ADD KEY idx_account_created (account_id, created_at);

ALTER TABLE listing_views
  ADD COLUMN country_code CHAR(2)     NULL AFTER device_type,
  ADD COLUMN country      VARCHAR(64) NULL AFTER country_code,
  ADD COLUMN city         VARCHAR(96) NULL AFTER country,
  ADD KEY idx_listing_country (listing_id, country_code);
