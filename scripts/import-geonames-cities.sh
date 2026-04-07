#!/bin/bash
#
# Import GeoNames city data into cityinfo table
# Usage: ./scripts/import-geonames-cities.sh <file.txt> [filter]
#
set -e

if [ -z "$1" ]; then
    echo "Usage: $0 <geonames_file.txt> [filter]"
    echo ""
    echo "Examples:"
    echo "  $0 data/cities15000.txt"
    echo "  $0 data/allCountries.txt cities"
    echo ""
    echo "Filter options:"
    echo "  cities - Populated places (pop >= 100) + ADM3/ADM4 municipalities"
    echo "  all    - Import everything (default for cities*.txt)"
    exit 1
fi

GEONAMES_FILE="$1"
FILTER_MODE="${2:-all}"

if [ ! -f "$GEONAMES_FILE" ]; then
    echo "Error: File not found: $GEONAMES_FILE"
    exit 1
fi

echo "=========================================="
echo "Importing GeoNames Data"
echo "=========================================="
echo ""
echo "Source file: $GEONAMES_FILE"
echo "Filter mode: $FILTER_MODE"
echo ""

TOTAL_LINES=$(wc -l < "$GEONAMES_FILE")
echo "Total records in file: $TOTAL_LINES"
echo ""

TMP_TSV="/tmp/geonames_filtered.tsv"

echo "Preparing filtered data..."

if [ "$FILTER_MODE" = "cities" ]; then
    echo "Filtering: (feature_class=P AND population>=100) OR (feature_class=A AND feature_code in ADM3,ADM4)..."
    awk -F'\t' 'BEGIN { OFS="\t" }
    {
        fc = $7; fcode = $8; pop = $15+0
        if ((fc == "P" && pop >= 100) || (fc == "A" && (fcode == "ADM3" || fcode == "ADM4"))) {
            print $1, $2, $5, $6, $9, $11, $18, pop
        }
    }' "$GEONAMES_FILE" > "$TMP_TSV"
else
    awk -F'\t' 'BEGIN { OFS="\t" }
    {
        pop = $15+0
        print $1, $2, $5, $6, $9, $11, $18, pop
    }' "$GEONAMES_FILE" > "$TMP_TSV"
fi

FILTERED_COUNT=$(wc -l < "$TMP_TSV")
echo "Filtered records: $FILTERED_COUNT"
echo ""

if [ "$FILTERED_COUNT" -eq 0 ]; then
    echo "No records matched filter. Aborting."
    rm -f "$TMP_TSV"
    exit 1
fi

echo "Importing to database via LOAD DATA INFILE..."

CONTAINER_NAME=$(docker compose ps -q database)
docker cp "$TMP_TSV" "$CONTAINER_NAME:/var/lib/mysql-files/geonames_filtered.tsv"

docker compose exec -T database mysql -u geonames -pgeonames geonames \
    -e "LOAD DATA INFILE '/var/lib/mysql-files/geonames_filtered.tsv'
        INTO TABLE cityinfo
        FIELDS TERMINATED BY '\t'
        LINES TERMINATED BY '\n'
        (geonameid, name, latitude, longitude, country, state, timezone, population)"

echo ""
echo "Verifying import..."
IMPORTED_COUNT=$(docker compose exec -T database mysql -u geonames -pgeonames geonames -sN -e "SELECT COUNT(*) FROM cityinfo")

echo "==========================================="
echo "Import Complete!"
echo "==========================================="
echo "Imported: $IMPORTED_COUNT records"
echo ""

rm -f "$TMP_TSV"
docker compose exec -T database rm -f /var/lib/mysql-files/geonames_filtered.tsv

echo "Sample data:"
docker compose exec database mysql -u geonames -pgeonames geonames -e "SELECT name, country, state, population FROM cityinfo ORDER BY population DESC LIMIT 10"
