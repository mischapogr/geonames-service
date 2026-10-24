<div align="center">

# geonames-service

**Self-hosted city and place autocomplete, built on GeoNames data.**

[![CI](https://github.com/mischapogr/geonames-service/actions/workflows/ci.yml/badge.svg)](https://github.com/mischapogr/geonames-service/actions/workflows/ci.yml)
[![Licence](https://img.shields.io/badge/licence-Apache--2.0-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-8.1%20%E2%80%93%208.5-777BB4.svg)](#supported-php-versions)
[![Data](https://img.shields.io/badge/data-CC%20BY%204.0-lightgrey.svg)](#data-attribution)

[Quick start](#quick-start) · [API](#api) · [Datasets](#datasets) · [Configuration](#configuration) · [Changelog](CHANGELOG.md) · [Contributing](CONTRIBUTING.md)

</div>

---

Type a few letters, get back the places the user probably means, with
coordinates and an IANA timezone. The service imports the
[GeoNames](https://www.geonames.org/) dumps into MySQL and answers prefix
searches over place names and their alternate names in 13 languages, exact
matches first, then by population.

```console
$ curl "http://localhost:8082/api/cities/search?q=Mailand"
{
  "success": true,
  "data": [
    {
      "geonameid": 3173435,
      "name": "Milan",
      "matched_name": "Mailand",
      "latitude": "45.4642700",
      "longitude": "9.1895100",
      "country": "IT",
      "state": "Lombardy",
      "timezone": "Europe/Rome",
      "population": 1371498
    }
  ]
}
```

## Why this exists

Hosted geocoding APIs are metered, rate-limited, and see every keystroke your
users type. Autocomplete needs much less than a geocoder: a fast prefix search,
a sensible ranking, and the timezone of the result. This service does exactly
that and nothing more.

- **One PHP file, no runtime dependencies.** `src/index.php` holds routing,
  queries, and caching. It runs wherever PHP 8.3 with PDO MySQL runs,
  including shared hosting without shell daemons or containers.
- **Your data stays yours.** Searches never leave your server.
- **Additive API.** New behaviour arrives as opt-in query parameters; a request
  that works today keeps returning the same bytes.

## Requirements

| Component | Requirement |
| --- | --- |
| PHP | 8.1 to 8.5, with `pdo_mysql`; `apcu` is optional and enables `CACHE_TTL` |
| Database | MySQL 8.0 (the version the Docker setup and CI use) |
| Web server | Apache with `mod_rewrite`, or any server that routes unknown paths to `src/index.php` |
| Import scripts | bash, `awk`, `unzip`, `wget` or `curl`, and the `mysql` client with `LOAD DATA LOCAL INFILE` |

### Supported PHP versions

CI runs the full end-to-end suite against every version marked as tested. The
Docker image defaults to PHP 8.3; build another with
`PHP_VERSION=8.5 docker compose up -d --build`.

| PHP | This project | PHP upstream |
| --- | --- | --- |
| 8.5 | Tested | Active support |
| 8.4 | Tested | Active support |
| 8.3 | Tested, Docker default | Security fixes only |
| 8.2 | Tested | Security fixes until 2026-12-31 |
| 8.1 | Tested | End of life since 2025-12-31; upgrade when you can |
| 8.0 | Not supported | End of life |
| 7.4 and older | Not supported | End of life |

PHP 8.0 runs the code but returns IDs and populations as JSON strings instead
of numbers, because its PDO MySQL driver does not convert column types. Older
versions cannot parse the code. Responses are byte-identical across all tested
versions.

## Quick start

You need Docker and Docker Compose.

```sh
docker compose up -d                 # MySQL 8 on :3309, the API on :8082
./scripts/download-cities15000       # about 3 MB, places with population > 15,000
./scripts/import-cities15000         # asks before truncating cityinfo
curl "http://localhost:8082/api/cities/search?q=Zur"
```

Add alternate names (other languages, historic names) with
`./scripts/download-alternatenames && ./scripts/import-alternatenames`.

## API

| Endpoint | Returns |
| --- | --- |
| `GET /api/cities/search?q=&country=` | Up to 20 places, exact name matches first, then by population |
| `GET /api/places?q=&country=` | The same search in a compact `{items: [...]}` shape for autocomplete widgets |
| `GET /api/cities/{geonameid}?lang=` | One place; `lang` adds its `local_name` in that language |
| `GET /health` | `{"status":"ok"}` when the database answers, `503` otherwise |

`q` needs at least two characters; shorter queries return an empty list.
`country` is an ISO 3166-1 alpha-2 code.

```console
$ curl "http://localhost:8082/api/places?q=Mailand"
{"items":[{"placeId":"geo:3173435","label":"Milan (Mailand), Lombardy, IT","lat":45.46427,"lon":9.18951,"timezone":"Europe/Rome"}]}

$ curl "http://localhost:8082/api/cities/3173435?lang=de"
{"success":true,"data":{"geonameid":3173435,"name":"Milan", ... ,"local_name":"Mailand"}}
```

### Optional search filters

Both search endpoints accept these opt-in filters. Leaving them out, or
passing a neutral value, returns exactly the unfiltered result.

| Param | Effect | Neutral values |
| --- | --- | --- |
| `minpop=N` | Drop places with `0 < population < N`. Population 0 means unknown and is kept. | absent, `0`, negative, non-numeric |
| `no_districts=1` | Drop city districts (GeoNames feature code `PPLX`, e.g. Hamburg-Wandsbek). | absent, any value other than `1` |

`no_districts` needs migration `005-add-feature-code.sql`; rows whose
`feature_code` is not filled yet are kept, never dropped.

### API key

Set `API_KEY` to require a key on every request except `/health`, sent as the
`X-Api-Key` header or the `api_key` query parameter. Without a valid key the
API answers `401`. When `API_KEY` is empty (the default), no key is required.

## Datasets

| Dataset | Size | Places | Use |
| --- | --- | --- | --- |
| `cities15000` | about 3 MB zipped | about 34,000 | development and tests |
| `allCountries` | about 400 MB zipped | millions of populated places (feature class P) | production |
| `alternateNamesV2` | about 200 MB zipped | names in 13 languages | optional, both |

```sh
./scripts/download-allcountries && ./scripts/import-allcountries
./scripts/download-alternatenames && ./scripts/import-alternatenames
```

The import scripts truncate `cityinfo` and `city_alt_names` and reload them,
so search returns little or nothing while they run. Import into a staging
database, or run them in a maintenance window.

### Upgrading an existing database to `feature_code`

The import scripts refuse to run until migration `005-add-feature-code.sql`
is applied (they would otherwise truncate a table they cannot reload).
To fill the new column on a live database without re-importing:

```sh
./deploy --migrate                                             # adds cityinfo.feature_code (NULL)
./scripts/backfill-feature-code data/allCountries.txt cities   # on the host, fills it in place
```

The backfill only writes `feature_code`; search keeps serving while it runs.
`deploy` does not sync `data/`, so if `data/allCountries.txt` is missing on the
host, run `./scripts/download-allcountries` there first.

## Configuration

All configuration is environment variables: `docker-compose.yml` for local
development, the generated `src/.env.php` on a deployed host.

| Variable | Compose value | Description |
| --- | --- | --- |
| `DB_HOST` | `database` | MySQL host (`localhost` when unset) |
| `DB_PORT` | `3306` | MySQL port |
| `DB_NAME` | `geonames` | Database name |
| `DB_USER` | `geonames` | Database user |
| `DB_PASS` | `geonames` | Database password |
| `CORS_ORIGIN` | `*` | Value of `Access-Control-Allow-Origin` |
| `API_KEY` | _(empty)_ | Require this key on every request except `/health` |
| `CACHE_TTL` | `0` | APCu result cache lifetime in seconds; `0` disables it |
| `HTTP_CACHE_TTL` | `0` | `Cache-Control: max-age` in seconds; `0` disables it |

`deploy.example` defaults to `CACHE_TTL=3600` and `HTTP_CACHE_TTL=300`. The
Compose values are for local development only. Never expose that stack to a
network; see [SECURITY.md](SECURITY.md).

## Deployment

`deploy.example` deploys to a host with PHP 8.1 or newer, MySQL, and SSH
access, such as shared hosting. Copy it to `deploy` (ignored by git), put `DEPLOY_HOST`,
`SUBDOMAIN`, and the database credentials in `.env.deploy`, then run:

```sh
./deploy --dry-run     # show what would happen
./deploy --migrate     # rsync the code, write src/.env.php, apply migrations
```

The script rsyncs the code into `~/public_html/<SUBDOMAIN>`, adds the rewrite
rule to `.htaccess`, and writes `src/.env.php` and `scripts/.env` on the host.
Run the import scripts on the host afterwards.

## Development

[CONTRIBUTING.md](CONTRIBUTING.md) covers the checks, the E2E suite, and what a
change needs. [AGENTS.md](AGENTS.md) describes the architecture and conventions.

To work against a deployed instance instead of a local database,
`UPSTREAM=https://geonames.example.com ./scripts/proxy-prod` replaces the app
container with a reverse proxy on `:8082`.

## Contributing

Issues and pull requests are welcome. Open an issue before anything larger than
a fix, and read [CONTRIBUTING.md](CONTRIBUTING.md) first. Please follow the
[Code of Conduct](CODE_OF_CONDUCT.md). For vulnerabilities, follow
[SECURITY.md](SECURITY.md) rather than opening a public issue.

## Data attribution

Place data comes from [GeoNames](https://www.geonames.org/) and is licensed
under [Creative Commons Attribution 4.0](https://creativecommons.org/licenses/by/4.0/).
This repository contains no GeoNames data; the scripts download it. If you
serve it, credit GeoNames.

## Licence

[Apache License 2.0](LICENSE): free to use, modify, and redistribute, in both
proprietary and open-source projects, provided the licence and
[NOTICE](NOTICE) are retained.

Copyright 2026 Mischa Pogrebnyak ([@mischapogr](https://github.com/mischapogr)).
