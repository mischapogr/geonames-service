<?php

declare(strict_types=1);

namespace Tests\E2E;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ApiTest extends TestCase
{
    private string $baseUrl;

    protected function setUp(): void
    {
        $this->baseUrl = rtrim(getenv('GEONAMES_BASE_URL') ?: 'http://localhost:8082', '/');
    }

    private function fetch(string $path): array
    {
        $url = $this->baseUrl . $path;
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'ignore_errors' => true,
                'timeout' => 10,
            ],
        ]);

        $body = file_get_contents($url, false, $context);
        $this->assertNotFalse($body, "Failed to fetch {$url}");

        // Parse status code from $http_response_header
        preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m);
        $status = (int) $m[1];

        $headers = [];
        foreach ($http_response_header as $header) {
            if (str_contains($header, ':')) {
                [$key, $value] = explode(':', $header, 2);
                $headers[strtolower(trim($key))] = trim($value);
            }
        }

        return [
            'status' => $status,
            'headers' => $headers,
            'body' => json_decode($body, true),
        ];
    }

    public function testHealthEndpoint(): void
    {
        $res = $this->fetch('/health');

        $this->assertSame(200, $res['status']);
        $this->assertSame('ok', $res['body']['status']);
    }

    public function testCitySearch(): void
    {
        $res = $this->fetch('/api/cities/search?q=Zur');

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['body']['success']);
        $this->assertNotEmpty($res['body']['data']);

        $city = $res['body']['data'][0];
        $this->assertArrayHasKey('geonameid', $city);
        $this->assertArrayHasKey('name', $city);
        $this->assertArrayHasKey('latitude', $city);
        $this->assertArrayHasKey('longitude', $city);
        $this->assertArrayHasKey('country', $city);
        $this->assertArrayHasKey('timezone', $city);
        $this->assertArrayHasKey('population', $city);
    }

    public function testCitySearchTooShort(): void
    {
        $res = $this->fetch('/api/cities/search?q=Z');

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['body']['success']);
        $this->assertEmpty($res['body']['data']);
    }

    public function testCitySearchWithCountryFilter(): void
    {
        $res = $this->fetch('/api/cities/search?q=Zur&country=CH');

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['body']['success']);
        $this->assertNotEmpty($res['body']['data']);

        foreach ($res['body']['data'] as $city) {
            $this->assertSame('CH', $city['country']);
        }
    }

    public function testCityById(): void
    {
        $res = $this->fetch('/api/cities/2657896');

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['body']['success']);
        $this->assertSame('Zürich', $res['body']['data']['name']);
        $this->assertSame('CH', $res['body']['data']['country']);
        $this->assertSame('Europe/Zurich', $res['body']['data']['timezone']);
    }

    public function testCityByIdNotFound(): void
    {
        $res = $this->fetch('/api/cities/999999999');

        $this->assertSame(404, $res['status']);
        $this->assertFalse($res['body']['success']);
        $this->assertSame('City not found', $res['body']['error']);
    }

    public function testPlacesEndpoint(): void
    {
        $res = $this->fetch('/api/places?q=Zur');

        $this->assertSame(200, $res['status']);
        $this->assertNotEmpty($res['body']['items']);

        $item = $res['body']['items'][0];
        $this->assertArrayHasKey('placeId', $item);
        $this->assertArrayHasKey('label', $item);
        $this->assertArrayHasKey('lat', $item);
        $this->assertArrayHasKey('lon', $item);
        $this->assertArrayHasKey('timezone', $item);
    }

    public function testNotFoundRoute(): void
    {
        $res = $this->fetch('/api/nonexistent');

        $this->assertSame(404, $res['status']);
        $this->assertSame('Not found', $res['body']['error']);
    }

    public function testCorsHeaders(): void
    {
        $res = $this->fetch('/health');

        $this->assertArrayHasKey('access-control-allow-origin', $res['headers']);
    }

    /**
     * Filter params that must behave exactly like an unfiltered request.
     * minpop=0 is what clients send when they do not want to filter.
     */
    public static function neutralFilterProvider(): array
    {
        return [
            'minpop=0' => ['/api/cities/search?q=Zur', '/api/cities/search?q=Zur&minpop=0'],
            'minpop=0 + country' => ['/api/cities/search?q=Zur&country=CH', '/api/cities/search?q=Zur&minpop=0&country=CH'],
            'non-numeric minpop' => ['/api/cities/search?q=Zur', '/api/cities/search?q=Zur&minpop=abc'],
            'negative minpop' => ['/api/cities/search?q=Zur', '/api/cities/search?q=Zur&minpop=-5'],
            'no_districts=0' => ['/api/cities/search?q=Zur', '/api/cities/search?q=Zur&no_districts=0'],
            'no_districts=true' => ['/api/cities/search?q=Zur', '/api/cities/search?q=Zur&no_districts=true'],
            'places minpop=0' => ['/api/places?q=Zur', '/api/places?q=Zur&minpop=0'],
            'places no_districts=0' => ['/api/places?q=Zur&country=CH', '/api/places?q=Zur&country=CH&no_districts=0'],
        ];
    }

    #[DataProvider('neutralFilterProvider')]
    public function testNeutralFilterValuesMatchUnfilteredSearch(string $unfiltered, string $neutral): void
    {
        $expected = $this->fetch($unfiltered);
        $actual = $this->fetch($neutral);

        $this->assertSame(200, $expected['status']);
        $this->assertSame(200, $actual['status']);
        $this->assertSame($expected['body'], $actual['body']);
    }

    public function testCitySearchMinpopFilter(): void
    {
        $res = $this->fetch('/api/cities/search?q=Zur&minpop=100000');

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['body']['success']);
        $this->assertContains(2657896, array_column($res['body']['data'], 'geonameid'));

        foreach ($res['body']['data'] as $city) {
            $population = (int) $city['population'];
            $this->assertTrue(
                $population === 0 || $population >= 100000,
                "{$city['name']} has population {$population}"
            );
        }
    }

    public function testCitySearchNoDistrictsExcludesDistricts(): void
    {
        // Wandsbek (2911285) is a PPLX district of Hamburg
        $default = $this->fetch('/api/cities/search?q=Wandsbek&country=DE');
        $filtered = $this->fetch('/api/cities/search?q=Wandsbek&country=DE&no_districts=1');

        $this->assertContains(2911285, array_column($default['body']['data'], 'geonameid'));
        $this->assertSame(200, $filtered['status']);
        $this->assertTrue($filtered['body']['success']);
        $this->assertNotContains(2911285, array_column($filtered['body']['data'], 'geonameid'));
    }

    public function testPlacesNoDistrictsExcludesDistricts(): void
    {
        $default = $this->fetch('/api/places?q=Wandsbek&country=DE');
        $filtered = $this->fetch('/api/places?q=Wandsbek&country=DE&no_districts=1');

        $this->assertContains('geo:2911285', array_column($default['body']['items'], 'placeId'));
        $this->assertSame(200, $filtered['status']);
        $this->assertNotContains('geo:2911285', array_column($filtered['body']['items'], 'placeId'));
    }
}
