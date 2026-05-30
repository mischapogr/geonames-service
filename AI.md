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
./scripts/download-cities15000./scripts/import-cities15000
# Test
curl "http://localhost:8082/api/cities/search?q=Zur"
```

### Proxy to production (no local DB)

To develop against live data without standing up MySQL or importing a dataset,
run a reverse proxy that forwards `localhost:8082` to production:

```bash
./scripts/proxy-prod              # proxy :8082 -> https://geonames.example.com
PORT=9000 ./scripts/proxy-prod    # use a different local port
UPSTREAM=https://other.host ./scripts/proxy-prod
```

The proxy is a Caddy service in `docker-compose.yml` gated behind the `proxy`
profile, so a normal `docker compose up` never starts it. The script stops the
`app` container first (both bind `:8082`), then runs the proxy in the
foreground; Ctrl-C stops the proxy. Equivalent manual commands:

```bash
docker compose stop app
docker compose --profile proxy up proxy
```

Production's API key and CORS rules apply as-is to proxied requests.

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
