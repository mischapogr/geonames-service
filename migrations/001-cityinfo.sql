-- GeoNames city data table
-- Source: http://www.geonames.org/ (CC BY 4.0)

CREATE TABLE IF NOT EXISTS cityinfo (
    geonameid INT PRIMARY KEY,
    name VARCHAR(200) NULL,
    latitude DECIMAL(10, 7) NULL,
    longitude DECIMAL(10, 7) NULL,
    country VARCHAR(2) NULL COMMENT 'ISO 3166-1 alpha-2 country code',
    state VARCHAR(20) NULL COMMENT 'State/province/region',
    timezone VARCHAR(40) NULL COMMENT 'IANA timezone identifier',
    population INT UNSIGNED DEFAULT 0,

    INDEX idx_city_name (name),
    INDEX idx_city_country (country),
    INDEX idx_city_population (population DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Worldwide city/location database from GeoNames';
