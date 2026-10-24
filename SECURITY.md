# Security policy

## Reporting a vulnerability

**Do not open a public issue for a security problem.**

Report privately through GitHub Security Advisories:
<https://github.com/mischapogr/geonames-service/security/advisories/new>

That channel is private to the maintainer until an advisory is published, and
it lets you be credited when it is.

Please include what you can: affected version or commit, a description of the
class of problem, the impact you believe it has, and the minimum needed to
reproduce it. A proof of concept is welcome but not required. Do not include
a working exploit against a system you do not own.

You should get an acknowledgement within 7 days. This is a personal project
with no commercial support attached, so treat any timeline beyond that
acknowledgement as best effort rather than a commitment.

## Supported versions

Only the latest release on `main` receives fixes. There is no backport branch.

| Version | Supported |
| --- | --- |
| latest release | Yes |
| everything else | No |

## Scope

**In scope:**

- SQL injection or any other way to make a query parameter reach SQL other
  than as a bound value.
- Bypassing `API_KEY` protection, or anything that leaks the configured key
  into a response, a log line, or a cache entry.
- Cache confusion: a request that makes another request's cached response
  wrong, for example by colliding APCu cache keys.
- CORS handling that grants an origin more than `CORS_ORIGIN` allows.
- The deploy script writing outside the target directory, or exposing
  `src/.env.php` or `scripts/.env` over HTTP.

**Out of scope:**

- The Docker Compose development stack, which is deliberately insecure: it
  ships fixed database passwords, an empty `API_KEY`, and `CORS_ORIGIN=*`.
  Those defaults are the documented design for local development, not a
  finding. Never expose that stack to a network.
- Volumetric denial of service. Rate limiting belongs in front of the service.
- Accuracy of GeoNames data. Report data errors to
  [GeoNames](https://www.geonames.org/) instead.
- Vulnerabilities in PHP, Apache, MySQL, APCu, or Caddy themselves. Report
  those upstream; tell us only if this project's usage makes an upstream issue
  exploitable in a way it otherwise would not be.
