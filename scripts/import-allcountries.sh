#!/bin/bash
#
# Import allCountries.txt into cityinfo table (filtered to cities only)
# Production import (~150K cities)
#
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
DATA_FILE="$PROJECT_ROOT/data/allCountries.txt"

echo "=========================================="
echo "Import AllCountries Dataset (Production)"
echo "=========================================="
echo ""

if [ ! -f "$DATA_FILE" ]; then
    echo "Error: allCountries.txt not found"
    echo "Please download first: ./scripts/download-allcountries.sh"
    exit 1
fi

LINE_COUNT=$(wc -l < "$DATA_FILE")
FILE_SIZE=$(du -h "$DATA_FILE" | cut -f1)
echo "Source: $DATA_FILE ($FILE_SIZE)"
echo "Total records: $LINE_COUNT"
echo "Will filter to: ~150K+ places (pop >= 100 + municipalities)"
echo "Import time: 5-15 minutes"
echo ""

read -p "This will truncate the cityinfo table. Continue? (y/n) " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Aborted."
    exit 1
fi

echo ""
echo "Truncating tables..."
docker compose exec -T database mysql -u geonames -pgeonames geonames \
    -e "SET FOREIGN_KEY_CHECKS=0; TRUNCATE TABLE city_alt_names; TRUNCATE TABLE cityinfo; SET FOREIGN_KEY_CHECKS=1;"

echo ""
echo "Importing data..."
"$PROJECT_ROOT/scripts/import-geonames-cities.sh" "$DATA_FILE" cities

echo ""
echo "Statistics:"
docker compose exec database mysql -u geonames -pgeonames geonames \
    -e "SELECT COUNT(*) as total_cities, COUNT(DISTINCT country) as countries FROM cityinfo"

echo ""
echo "Top 10 countries:"
docker compose exec database mysql -u geonames -pgeonames geonames \
    -e "SELECT country, COUNT(*) as cnt FROM cityinfo GROUP BY country ORDER BY cnt DESC LIMIT 10"

echo ""
echo "Production dataset ready"
