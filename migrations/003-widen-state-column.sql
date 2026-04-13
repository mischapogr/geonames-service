-- Widen state column to hold human-readable admin1 names
-- e.g. "Mecklenburg-Vorpommern", "Emilia-Romagna"
ALTER TABLE cityinfo MODIFY COLUMN state VARCHAR(100) NULL COMMENT 'State/province/region name';