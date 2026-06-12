-- Canlı şema hizalama: skills/location kodun beklediği genişliğe çekildi
-- (eski canlı tipleri varchar(500)/varchar(100) idi; uzun değerlerde kırpma riski).
-- Canlıda 2026-06-12'de uygulandı.
ALTER TABLE job_listings MODIFY skills TEXT NULL;
ALTER TABLE job_listings MODIFY location VARCHAR(255) NOT NULL;
