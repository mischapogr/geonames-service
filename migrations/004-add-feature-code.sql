-- Add feature_code column to distinguish place types (PPL, PPLA, PPLX, etc.)
-- PPLX = section of populated place (e.g. Stadtteile) — useful for filtering

ALTER TABLE cityinfo ADD COLUMN feature_code VARCHAR(10) NULL AFTER population;
ALTER TABLE cityinfo ADD INDEX idx_city_feature_code (feature_code);