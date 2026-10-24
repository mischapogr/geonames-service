# Changelog

All notable changes to this project are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Releases are cut by semantic-release from the commit history; this file is the
curated summary.

## [Unreleased]

### Changed

- Relicensed from MIT to the Apache License 2.0; added a `NOTICE` file that
  records the GeoNames data licence (CC BY 4.0) and the runtime components.
- `scripts/proxy-prod` no longer has a built-in upstream and refuses to start
  without `UPSTREAM`.
- The load-test report records the run date without a time of day.

### Added

- Contribution, security, support, and code-of-conduct guides, issue and pull
  request templates, CI for PHP, shell scripts, workflows, and the E2E suite,
  CodeQL scanning, and Dependabot.
- `.gitattributes` (LF line endings) and a `.dockerignore` that limits the
  build context to `docker/` and `src/`.
- Supported PHP versions are documented (8.1 to 8.5) and declared in
  `composer.json`; CI runs the end-to-end suite on each of them, and the
  Dockerfile takes a `PHP_VERSION` build argument (default 8.3).

## [1.5.0] - 2026-10-24

### Added

- `minpop=N` on `/api/cities/search` and `/api/places`: drops places with a
  known population below `N`. Population `0` means unknown and is kept.
- `no_districts=1` on both search endpoints: drops city districts (GeoNames
  feature code `PPLX`).
- `cityinfo.feature_code` (migration `005-add-feature-code.sql`), filled by
  the import scripts.
- `scripts/backfill-feature-code` fills `feature_code` on a live database
  without truncating, so search keeps serving while it runs.

### Changed

- Import scripts abort before truncating `cityinfo` when migration 005 has not
  been applied.
- The Compose services and the default network carry a `keep=true` label so a
  label-filtered `docker prune` leaves them alone.

Requests without the new parameters, or with neutral values such as
`minpop=0`, return byte-identical responses to 1.4.0 and keep their cache keys.

## [1.4.0] - 2026-05-30

### Added

- An optional Caddy reverse proxy (Compose profile `proxy`) and
  `scripts/proxy-prod`, to develop against a deployed instance without a local
  database.

### Fixed

- Migration `004-grant-file.sql` grants the `FILE` privilege that the import
  scripts need for `LOAD DATA INFILE` in the Docker setup.

## [1.3.0] - 2026-04-15

### Added

- Search by alternate names in several languages; exact matches rank first.
- Admin1 (state) names in the local language, taken from `alternateNamesV2`.
- `?lang=` on `/api/cities/{id}` for a localised city name.
- `src/cache-clear.php` to clear the APCu cache.

### Changed

- The Compose setup mounts `src/` for live editing.

## [1.2.0] - 2026-04-13

### Added

- Admin1 codes are resolved to readable state names during import, using
  `admin1CodesASCII.txt` and a download script for it.

### Changed

- The `state` column is widened to `VARCHAR(100)` (migration 003).

## [1.1.0] - 2026-04-10

### Added

- Optional APCu result cache (`CACHE_TTL`) and HTTP `Cache-Control` headers
  (`HTTP_CACHE_TTL`), both off by default.
- Playwright tests for browser caching, and a load-test script with recorded
  results.

### Changed

- `/health` probes the database with `SELECT 1 ... LIMIT 1` instead of
  `COUNT(*)`, which was slow on the full dataset, and no longer reports a
  city count.

## [1.0.0] - 2026-04-10

### Added

- `GET /api/cities/search`, `GET /api/places`, `GET /api/cities/{id}`, and
  `GET /health`.
- Optional API key protection through `API_KEY`.
- Docker Compose setup (PHP 8.3 with Apache, MySQL 8), download and import
  scripts for the `cities15000`, `allCountries`, and `alternateNamesV2`
  datasets, and `deploy.example` for SSH and rsync deployment to shared
  hosting.
- PHPUnit end-to-end suite and semantic-release on `main`.

[Unreleased]: https://github.com/mischapogr/geonames-service/compare/v1.5.0...HEAD
[1.5.0]: https://github.com/mischapogr/geonames-service/compare/v1.4.0...v1.5.0
[1.4.0]: https://github.com/mischapogr/geonames-service/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/mischapogr/geonames-service/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/mischapogr/geonames-service/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/mischapogr/geonames-service/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/mischapogr/geonames-service/releases/tag/v1.0.0
