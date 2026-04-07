# GeoNames City Search Service

PHP 8.3 REST API for city/location autocomplete, powered by GeoNames data. Runs in Docker (Apache + MySQL).

## Architecture

- Single-file PHP app: `src/index.php` — all routing, DB access, and request handling
- Database: MySQL 8 with tables `cityinfo` and `city_alt_names`
- Migrations in `migrations/` (plain SQL, applied in order)
- Import/download scripts in `scripts/` (bash)
- Docker setup: `Dockerfile` (php:8.3-apache), `docker-compose.yml`

## Development

```bash
# Start services
docker compose up -d

# Import dev dataset
./scripts/download-cities15000.sh
./scripts/import-cities15000.sh

# Test
curl "http://localhost:8082/api/cities/search?q=Zur"
```

## API Endpoints

- `GET /api/cities/search?q=&country=` — full search with alternate names, ordered by population
- `GET /api/places?q=&country=` — simplified search returning `{items: [...]}`
- `GET /health` — health check with city count

## Key Conventions

- All DB queries use PDO prepared statements (parameterized queries)
- CORS is configured via `CORS_ORIGIN` env var
- Optional API key auth via `API_KEY` env var (header `X-Api-Key` or query `api_key`)
- Config is entirely via environment variables in `docker-compose.yml`
- No external PHP dependencies (no Composer)
