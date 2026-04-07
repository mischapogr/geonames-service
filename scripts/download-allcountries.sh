#!/bin/bash
#
# Download allCountries.txt from GeoNames
# Complete worldwide dataset
#
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
DOWNLOAD_URL="http://download.geonames.org/export/dump/allCountries.zip"
ZIP_FILE="$PROJECT_ROOT/data/allCountries.zip"
TXT_FILE="$PROJECT_ROOT/data/allCountries.txt"

echo "=========================================="
echo "Download AllCountries Dataset"
echo "=========================================="
echo ""
echo "Source: GeoNames (http://www.geonames.org/)"
echo "License: CC BY 4.0"
echo "Download size: ~1.5 GB"
echo "Extracted size: ~3 GB"
echo ""

if [ -f "$TXT_FILE" ]; then
    echo "allCountries.txt already exists"
    FILE_SIZE=$(du -h "$TXT_FILE" | cut -f1)
    LINE_COUNT=$(wc -l < "$TXT_FILE")
    echo "Size: $FILE_SIZE, Records: $LINE_COUNT"
    echo ""
    read -p "Re-download? (y/n) " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 0
    fi
    rm -f "$TXT_FILE" "$ZIP_FILE"
fi

read -p "Continue with download? (y/n) " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Aborted."
    exit 1
fi

echo ""
echo "Downloading... (this may take several minutes)"
if command -v wget &> /dev/null; then
    wget --progress=bar:force -O "$ZIP_FILE" "$DOWNLOAD_URL"
elif command -v curl &> /dev/null; then
    curl -L --progress-bar -o "$ZIP_FILE" "$DOWNLOAD_URL"
else
    echo "Error: Neither wget nor curl is available"
    exit 1
fi

echo ""
echo "Extracting..."
unzip -q "$ZIP_FILE" -d "$PROJECT_ROOT/data"

read -p "Delete zip file to save space? (y/n) " -n 1 -r
echo ""
if [[ $REPLY =~ ^[Yy]$ ]]; then
    rm -f "$ZIP_FILE"
fi

echo ""
echo "Done: $TXT_FILE"
du -h "$TXT_FILE"
echo ""
echo "Next: ./scripts/import-allcountries.sh"
