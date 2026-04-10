# Load Test Results

**Date:** 2026-04-10
**Tool:** Apache Bench (ab) — 1000 requests, 10 concurrent

## System

| Property | Value |
|----------|-------|
| CPU | Intel(R) Xeon(R) CPU E3-1275 v3 @ 3.50GHz |
| Cores | 8 |
| RAM | 31Gi |
| Kernel | 6.8.0-107-generic |
| Docker | 29.4.0 |
| PHP | 8.3.30 |

## Endpoints tested

| # | Endpoint |
|---|----------|
| 1 | `/api/cities/search?q=Zur` |
| 2 | `/api/cities/2657896` |

## Scenario A: No cache

`CACHE_TTL=0` | `HTTP_CACHE_TTL=0`

### Request performance

| Endpoint | Req/s | Mean (ms) | P50 (ms) | P95 (ms) | P99 (ms) | Failed |
|----------|-------|-----------|----------|----------|----------|--------|
| City Search (q=Zur) | 1658.70 | 6.029 | 5 | 10 | 15 | 0 |
| City by ID (Zürich) | 3265.26 | 3.063 | 3 | 4 | 6 | 0 |
| Health | 635.48 | 15.736 | 15 | 22 | 25 | 0 |

### System metrics (averaged during test)

| CPU usr | CPU sys | CPU idle | CPU wait | Free MB | Buff MB | Cache MB |
|---------|---------|----------|----------|---------|---------|----------|
| 42.3% | 11.3% | 45.7% | 0.3% | 3729 | 1358 | 15375 |

## Scenario B: APCu only (TTL=3600s)

`CACHE_TTL=3600` | `HTTP_CACHE_TTL=0`

### Request performance

| Endpoint | Req/s | Mean (ms) | P50 (ms) | P95 (ms) | P99 (ms) | Failed |
|----------|-------|-----------|----------|----------|----------|--------|
| City Search (q=Zur) | 8465.18 | 1.181 | 1 | 2 | 2 | 0 |
| City by ID (Zürich) | 9384.03 | 1.066 | 1 | 1 | 2 | 0 |
| Health | 8639.38 | 1.157 | 1 | 2 | 3 | 0 |

### System metrics (averaged during test)

| CPU usr | CPU sys | CPU idle | CPU wait | Free MB | Buff MB | Cache MB |
|---------|---------|----------|----------|---------|---------|----------|
| 7.0% | 2.0% | 90.0% | 1.0% | 3723 | 1358 | 15374 |

## Scenario C: APCu + HTTP Cache-Control (TTL=3600s/300s)

`CACHE_TTL=3600` | `HTTP_CACHE_TTL=300`

### Request performance

| Endpoint | Req/s | Mean (ms) | P50 (ms) | P95 (ms) | P99 (ms) | Failed |
|----------|-------|-----------|----------|----------|----------|--------|
| City Search (q=Zur) | 7934.93 | 1.260 | 1 | 2 | 2 | 0 |
| City by ID (Zürich) | 8833.69 | 1.132 | 1 | 2 | 2 | 0 |
| Health | 9429.96 | 1.060 | 1 | 1 | 3 | 0 |

### System metrics (averaged during test)

| CPU usr | CPU sys | CPU idle | CPU wait | Free MB | Buff MB | Cache MB |
|---------|---------|----------|----------|---------|---------|----------|
| 7.0% | 2.0% | 90.0% | 1.0% | 3750 | 1358 | 15374 |

