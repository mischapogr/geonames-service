-- Alternate city names for multi-language search
-- Source: http://www.geonames.org/ (CC BY 4.0)

CREATE TABLE IF NOT EXISTS city_alt_names (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    geonameid INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    lang VARCHAR(7) DEFAULT NULL,
    is_preferred TINYINT(1) DEFAULT 0,
    INDEX idx_altname_prefix (name, geonameid),
    INDEX idx_altname_geonameid (geonameid),
    FOREIGN KEY (geonameid) REFERENCES cityinfo(geonameid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
