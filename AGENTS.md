# geonames-service agent guide

PHP REST API (PHP 8.1 to 8.5) for city/location autocomplete, powered by GeoNames data. Runs in Docker (Apache + MySQL)
for development and on plain PHP + MySQL hosting in production. This file is the shared guidance for
coding agents and human contributors; [CONTRIBUTING.md](CONTRIBUTING.md) has the checks a change needs.

## Architecture

- Single-file PHP app: `src/index.php` — all routing, DB access, and request handling
- Database: MySQL 8 with tables `cityinfo` and `city_alt_names`
- Migrations in `migrations/` (plain SQL, applied in order)
- Import/download scripts in `scripts/` (bash)
- Docker setup: `Dockerfile` (php:${PHP_VERSION}-apache, default 8.5), `docker-compose.yml`

## Development

```bash
# Start services
docker compose up -d

# Import dev dataset
./scripts/download-cities15000
./scripts/import-cities15000

# Test
curl "http://localhost:8082/api/cities/search?q=Zur"
```

### Proxy to a deployed instance (no local DB)

To develop against live data without standing up MySQL or importing a dataset,
run a reverse proxy that forwards `localhost:8082` to a deployed instance:

```bash
UPSTREAM=https://geonames.example.com ./scripts/proxy-prod            # proxy :8082 -> UPSTREAM
UPSTREAM=https://geonames.example.com PORT=9000 ./scripts/proxy-prod  # different local port
```

The script refuses to start without `UPSTREAM`; there is no built-in default.

The proxy is a Caddy service in `docker-compose.yml` gated behind the `proxy`
profile, so a normal `docker compose up` never starts it. The script stops the
`app` container first (both bind `:8082`), then runs the proxy in the
foreground; Ctrl-C stops the proxy. Equivalent manual commands:

```bash
docker compose stop app
docker compose --profile proxy up proxy
```

The upstream's API key and CORS rules apply as-is to proxied requests.

## API Endpoints

- `GET /api/cities/search?q=&country=&minpop=&no_districts=` — full search with alternate names, ordered by population
- `GET /api/places?q=&country=&minpop=&no_districts=` — simplified search returning `{items: [...]}`
- `GET /health` — health check with city count

`minpop` and `no_districts` are opt-in filters (see README). Absent or neutral
values (`minpop=0`, `no_districts` other than `1`) must return byte-identical
results to an unfiltered request, so existing clients that send `minpop=0` or
only `q` and `country` are unaffected. The E2E suite pins this.

## Key Conventions

- All DB queries use PDO prepared statements (parameterized queries)
- CORS is configured via `CORS_ORIGIN` env var
- Optional API key auth via `API_KEY` env var (header `X-Api-Key` or query `api_key`)
- Config is entirely via environment variables in `docker-compose.yml`
- No runtime PHP dependencies; Composer only installs PHPUnit for the E2E suite
- `src/` must run on PHP 8.1 to 8.5 (the CI matrix): no syntax or functions newer than 8.1, such as
  readonly classes, DNF types, typed class constants, `#[\Override]`, or property hooks
- New query parameters are opt-in; a request without them must return byte-identical responses
- Migrations are numbered and never edited after they ship; the deploy loop re-applies every file
  and ignores errors, so a migration must be harmless when it runs again
- Commit messages follow Conventional Commits; semantic-release cuts releases from them
