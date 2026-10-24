# Contributing to geonames-service

Thanks for your interest. This service sits behind autocomplete fields that
other applications already depend on, so the bar for changes is compatibility
first and convenience second.

By contributing you agree that your contributions are licensed under the
[Apache License 2.0](LICENSE).

## Before you start

Read [AGENTS.md](AGENTS.md) for the architecture and the conventions the code
follows. For anything beyond a typo, open an issue first. A short discussion
before code is cheaper than a rejected pull request.

## Setting up

You need Docker and Docker Compose. You do **not** need PHP, Composer, or
MySQL on the host: all of them run from images.

```sh
docker compose up -d
./scripts/download-cities15000     # about 3 MB, about 34,000 places
./scripts/import-cities15000       # answers a y/n prompt; truncates cityinfo
curl "http://localhost:8082/api/cities/search?q=Zur"
```

## Running the checks

```sh
# PHP syntax on the oldest and newest supported version
for v in 8.1 8.5; do docker run --rm -v "$PWD/src:/src:ro" php:$v-cli php -l /src/index.php; done

# Run the stack on another PHP version (default 8.5)
PHP_VERSION=8.1 docker compose up -d --build app

# End-to-end suite against the running stack on :8082
docker run --rm -v "$PWD:/app" -w /app composer:2 install --no-interaction --no-progress
docker run --rm --network host -v "$PWD:/app" -w /app php:8.3-cli php vendor/bin/phpunit

# Browser cache tests (optional, needs Node.js)
npm install && npx playwright install chromium && npm run test:browser
```

The E2E suite needs the `cities15000` dataset imported; several tests look up
known places such as Zürich (`2657896`) and Hamburg-Wandsbek (`2911285`).

## What a change needs

- **A test that fails without it.** Prefer a case in `tests/E2E/ApiTest.php`;
  it runs against a real database, which is where the defects in this
  repository have shown up.
- **No change for existing clients.** A request without your new parameter
  must return a byte-identical response. New query parameters are opt-in, and
  their neutral values (absent, `0`, unknown) behave like absence.
- **Safe migrations.** Add a new numbered file in `migrations/`; never edit
  one that has shipped. The deploy loop applies every file on each run and
  ignores errors, so a migration must be harmless when it runs a second time.
- **No silent failure.** A swallowed error, or a script that exits quietly
  without saying why, is a defect in its own right.
- **Documentation updated in the same change.** If behaviour changes, the
  README and [AGENTS.md](AGENTS.md) change with it.

## Commit messages

[Conventional Commits](https://www.conventionalcommits.org) 1.0.0:

```
<type>(<optional scope>)<!>: <subject>

<body: why, wrapped at 100>

<footers: BREAKING CHANGE: ..., Refs: #123>
```

Types: `feat` `fix` `docs` `refactor` `perf` `test` `build` `ci` `chore`
`style` `revert`. Subject in the imperative mood, lowercase, no trailing
period, header line at most 72 characters. Releases are cut by
semantic-release from this history: `feat` makes a minor release, `fix` a
patch release, and a non-conforming message drops out of the changelog.

## Pull requests

Target `main`. Keep pull requests focused; a reviewer should be able to hold
the whole change in mind. State what you verified and how, and be explicit
about what you did **not** verify, for example the full `allCountries`
dataset or a shared-hosting deployment.

## Security

Do not open a public issue for a vulnerability. Follow [SECURITY.md](SECURITY.md).
