# GeoNames City Search Service

Standalone REST API for city/location autocomplete search, powered by [GeoNames](http://www.geonames.org/) data.

## API Endpoints

### `GET /api/cities/search?q=Zur&country=CH`

Full city search with alternate names (multi-language). Returns up to 20 results ordered by population.

### `GET /api/places?q=Zur&country=CH`

Simplified search returning `{items: [...]}` format.

### Optional search filters

Both search endpoints accept these opt-in filters. Leaving them out (or
passing a neutral value) returns exactly the unfiltered result.

| Param | Effect | Neutral values |
|-------|--------|----------------|
| `minpop=N` | Drop places with `0 < population < N`. Population 0 means unknown and is kept. | absent, `0`, negative, non-numeric |
| `no_districts=1` | Drop city districts (GeoNames feature code `PPLX`, e.g. Hamburg-Wandsbek). | absent, any value other than `1` |

`no_districts` needs migration `005-add-feature-code.sql`; rows whose
`feature_code` is not filled yet are kept, never dropped.

### `GET /health`

Health check with city count.

## Quick Start

```bash
# Start services
docker compose up -d

# Download dev dataset (~7 MB, ~15K cities)
./scripts/download-cities15000

# Import into database
./scripts/import-cities15000

# Test
curl "http://localhost:8082/api/cities/search?q=Zur"
```

## Production Dataset

```bash
# Download full dataset (~1.5 GB compressed, 12M+ records)
./scripts/download-allcountries

# Import (filters to ~150K populated places, takes 5-15 min)
./scripts/import-allcountries

# Optional: alternate names for multi-language search
./scripts/download-alternatenames
./scripts/import-alternatenames
```

### Upgrading an existing database to `feature_code`

The import scripts refuse to run until migration `005-add-feature-code.sql`
is applied (they would otherwise truncate a table they cannot reload).
To fill the new column on a live database without re-importing:

```bash
./deploy --migrate                                         # adds cityinfo.feature_code (NULL)
./scripts/backfill-feature-code data/allCountries.txt cities   # on the host, fills it in place
```

The backfill only writes `feature_code`; search keeps serving while it runs.
`deploy` does not sync `data/`, so if `data/allCountries.txt` is missing on the
host, run `./scripts/download-allcountries` there first.

## Configuration

Environment variables (set in `docker-compose.yml`):

| Variable | Default | Description |
|----------|---------|-------------|
| `DB_HOST` | `database` | MySQL host |
| `DB_PORT` | `3306` | MySQL port |
| `DB_NAME` | `geonames` | Database name |
| `DB_USER` | `geonames` | Database user |
| `DB_PASS` | `geonames` | Database password |
| `CORS_ORIGIN` | `*` | Allowed CORS origin |
| `API_KEY` | _(empty)_ | API key for authentication. When empty, no auth required. |

## API Key Protection

Set `API_KEY` to require authentication. When set, all requests (except `/health`) must include:
- Header: `X-Api-Key: your-key`
- Or query param: `?api_key=your-key`

When `API_KEY` is empty (default), no authentication is required.

## Data Attribution

City data is sourced from [GeoNames](http://www.geonames.org/) and licensed under
[Creative Commons Attribution 4.0](https://creativecommons.org/licenses/by/4.0/).

## License

Code: MIT. Data: CC BY 4.0 (GeoNames).
