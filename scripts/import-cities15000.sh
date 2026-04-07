#!/bin/bash
#
# Import cities15000.txt into cityinfo table
# Quick import for development (~15K cities)
#
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
DATA_FILE="$PROJECT_ROOT/data/cities15000.txt"

echo "=========================================="
echo "Import Cities15000 Dataset"
echo "=========================================="
echo ""

if [ ! -f "$DATA_FILE" ]; then
    echo "Error: cities15000.txt not found"
    echo "Please download first: ./scripts/download-cities15000.sh"
    exit 1
fi

LINE_COUNT=$(wc -l < "$DATA_FILE")
echo "Source: $DATA_FILE"
echo "Records: $LINE_COUNT"
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
"$PROJECT_ROOT/scripts/import-geonames-cities.sh" "$DATA_FILE" all

echo ""
echo "Development dataset ready"
