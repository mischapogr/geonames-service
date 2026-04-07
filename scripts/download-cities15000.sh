#!/bin/bash
#
# Download cities15000.txt from GeoNames
# Cities with population > 15,000 (~15K cities)
#
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
DOWNLOAD_URL="http://download.geonames.org/export/dump/cities15000.zip"
ZIP_FILE="$PROJECT_ROOT/data/cities15000.zip"
TXT_FILE="$PROJECT_ROOT/data/cities15000.txt"

echo "=========================================="
echo "Download Cities15000 Dataset"
echo "=========================================="
echo ""
echo "Source: GeoNames (http://www.geonames.org/)"
echo "License: CC BY 4.0"
echo "Size: ~7 MB"
echo "Cities: ~15,000 (population > 15,000)"
echo ""

if [ -f "$TXT_FILE" ]; then
    echo "cities15000.txt already exists"
    LINE_COUNT=$(wc -l < "$TXT_FILE")
    echo "Records: $LINE_COUNT"
    echo ""
    read -p "Re-download? (y/n) " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 0
    fi
    rm -f "$TXT_FILE" "$ZIP_FILE"
fi

echo "Downloading..."
if command -v wget &> /dev/null; then
    wget -O "$ZIP_FILE" "$DOWNLOAD_URL"
elif command -v curl &> /dev/null; then
    curl -L -o "$ZIP_FILE" "$DOWNLOAD_URL"
else
    echo "Error: Neither wget nor curl is available"
    exit 1
fi

echo ""
echo "Extracting..."
unzip -q "$ZIP_FILE" -d "$PROJECT_ROOT/data"
rm -f "$ZIP_FILE"

echo ""
echo "Done: $TXT_FILE"
LINE_COUNT=$(wc -l < "$TXT_FILE")
echo "Records: $LINE_COUNT"
echo ""
echo "Next: ./scripts/import-cities15000.sh"
