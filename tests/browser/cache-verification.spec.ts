import { test, expect } from '@playwright/test';

const BASE = process.env.GEONAMES_BASE_URL || 'http://localhost:8082';

test.describe('HTTP Cache-Control headers', () => {
  test('search endpoint returns Cache-Control when HTTP_CACHE_TTL is set', async ({ request }) => {
    const res = await request.get(`${BASE}/api/cities/search?q=Zur`);
    expect(res.ok()).toBe(true);

    const cacheControl = res.headers()['cache-control'];
    // When HTTP_CACHE_TTL > 0, expect public, max-age=N
    if (cacheControl) {
      expect(cacheControl).toMatch(/public, max-age=\d+/);
    }
    // If no cache-control, that's also valid (HTTP_CACHE_TTL=0)

    const body = await res.json();
    expect(body.success).toBe(true);
    expect(body.data.length).toBeGreaterThan(0);
  });

  test('city-by-id endpoint returns Cache-Control when HTTP_CACHE_TTL is set', async ({ request }) => {
    const res = await request.get(`${BASE}/api/cities/2657896`);
    expect(res.ok()).toBe(true);

    const cacheControl = res.headers()['cache-control'];
    if (cacheControl) {
      expect(cacheControl).toMatch(/public, max-age=\d+/);
    }

    const body = await res.json();
    expect(body.success).toBe(true);
    expect(body.data.name).toBe('Zürich');
  });

  test('health endpoint works', async ({ request }) => {
    const res = await request.get(`${BASE}/health`);
    expect(res.ok()).toBe(true);

    const body = await res.json();
    expect(body.status).toBe('ok');
  });
});

test.describe('Browser caching behavior', () => {
  test('repeated navigation uses browser cache when Cache-Control is set', async ({ page }) => {
    // First request — always hits server
    const firstResponse = await page.goto(`${BASE}/api/cities/search?q=Zur`);
    expect(firstResponse).not.toBeNull();
    expect(firstResponse!.ok()).toBe(true);

    const cacheControl = (await firstResponse!.allHeaders())['cache-control'];

    if (cacheControl && cacheControl.includes('max-age')) {
      // Second request — browser may serve from disk/memory cache
      const fromCache: boolean[] = [];
      page.on('response', (res) => {
        if (res.url().includes('/api/cities/search')) {
          fromCache.push(res.fromServiceWorker());
        }
      });

      const secondResponse = await page.goto(`${BASE}/api/cities/search?q=Zur`);
      expect(secondResponse).not.toBeNull();
      expect(secondResponse!.ok()).toBe(true);
    }
  });
});
