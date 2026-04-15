<?php

declare(strict_types=1);

// Load environment config for non-Docker deployments
if (file_exists(__DIR__ . '/.env.php')) {
    require __DIR__ . '/.env.php';
}

// CORS
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
$allowedOrigin = getenv('CORS_ORIGIN') ?: '*';
header("Access-Control-Allow-Origin: {$allowedOrigin}");
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Api-Key');
header('Content-Type: application/json; charset=utf-8');

$httpCacheTtl = (int) (getenv('HTTP_CACHE_TTL') ?: '0');
if ($httpCacheTtl > 0) {
    header("Cache-Control: public, max-age={$httpCacheTtl}");
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Optional API key authentication
$apiKey = getenv('API_KEY') ?: '';
if ($apiKey !== '') {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($uri !== '/health') {
        $provided = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
        if ($provided !== $apiKey) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized', 'message' => 'Valid API key required via X-Api-Key header or api_key query parameter']);
            exit;
        }
    }
}

// Database connection
function getDb(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('DB_NAME') ?: 'geonames';
        $user = getenv('DB_USER') ?: 'geonames';
        $pass = getenv('DB_PASS') ?: 'geonames';

        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    return $pdo;
}

// Cache helpers (APCu, opt-in via CACHE_TTL env var)
function cacheGet(string $key): mixed
{
    $ttl = (int) (getenv('CACHE_TTL') ?: '0');
    if ($ttl <= 0 || !function_exists('apcu_fetch')) {
        return false;
    }
    return apcu_fetch($key);
}

function cacheSet(string $key, mixed $value): void
{
    $ttl = (int) (getenv('CACHE_TTL') ?: '0');
    if ($ttl <= 0 || !function_exists('apcu_store')) {
        return;
    }
    apcu_store($key, $value, $ttl);
}

// Routing
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (preg_match('#^/api/cities/(\d+)$#', $uri, $matches)) {
    handleCityById((int) $matches[1]);
} else {
    match ($uri) {
        '/api/cities/search' => handleCitySearch(),
        '/api/places' => handlePlacesSearch(),
        '/health' => handleHealth(),
        default => handleNotFound(),
    };
}

function handleCityById(int $geonameid): void
{
    $lang = $_GET['lang'] ?? '';
    $cacheKey = "city:{$geonameid}:{$lang}";
    $cached = cacheGet($cacheKey);
    if ($cached !== false) {
        echo json_encode($cached);
        return;
    }

    try {
        $db = getDb();
        $stmt = $db->prepare("
            SELECT geonameid, name, latitude, longitude, country, state, timezone, population
            FROM cityinfo
            WHERE geonameid = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $geonameid]);
        $city = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$city) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'City not found']);
            return;
        }

        if ($lang !== '') {
            $altStmt = $db->prepare("
                SELECT name FROM city_alt_names
                WHERE geonameid = :id AND lang = :lang
                ORDER BY is_preferred DESC
                LIMIT 1
            ");
            $altStmt->execute([':id' => $geonameid, ':lang' => $lang]);
            $alt = $altStmt->fetch(PDO::FETCH_ASSOC);
            if ($alt) {
                $city['local_name'] = $alt['name'];
            }
        }

        $result = ['success' => true, 'data' => $city];
        cacheSet($cacheKey, $result);
        echo json_encode($result);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleCitySearch(): void
{
    $query = $_GET['q'] ?? '';
    $country = $_GET['country'] ?? '';
    $minPop = max(0, (int)($_GET['minpop'] ?? 0));
    $excludeDistricts = ($_GET['no_districts'] ?? '') === '1';

    if (strlen($query) < 2) {
        echo json_encode(['success' => true, 'data' => []]);
        return;
    }

    $cacheKey = "search:" . strtolower($query) . ":{$country}:{$minPop}:" . ($excludeDistricts ? '1' : '0');
    $cached = cacheGet($cacheKey);
    if ($cached !== false) {
        echo json_encode($cached);
        return;
    }

    try {
        $db = getDb();
        $countryFilter = '';
        $params = [];

        if ($country !== '') {
            $countryFilter = ' AND c.country = :country';
            $params['country'] = $country;
        }

        $popFilter = '';
        if ($minPop > 0) {
            $popFilter = ' AND (c.population = 0 OR c.population >= :minpop)';
            $params['minpop'] = $minPop;
        }

        $districtFilter = $excludeDistricts ? " AND c.feature_code != 'PPLX'" : '';

        $params['exact_query'] = $query;
        $params['prefix_query'] = $query . '%';

        $sql = "
            (SELECT c.geonameid, c.name, c.name AS matched_name, c.latitude, c.longitude,
                    c.country, c.state, c.timezone, c.population, 0 AS match_rank
             FROM cityinfo c
             WHERE c.name = :exact_query{$countryFilter}{$popFilter}{$districtFilter})
            UNION ALL
            (SELECT c.geonameid, c.name, a.name AS matched_name, c.latitude, c.longitude,
                    c.country, c.state, c.timezone, c.population, 0 AS match_rank
             FROM city_alt_names a
             JOIN cityinfo c ON c.geonameid = a.geonameid
             WHERE a.name = :exact_query{$countryFilter}{$popFilter}{$districtFilter})
            UNION ALL
            (SELECT c.geonameid, c.name, c.name AS matched_name, c.latitude, c.longitude,
                    c.country, c.state, c.timezone, c.population, 1 AS match_rank
             FROM cityinfo c
             WHERE c.name LIKE :prefix_query{$countryFilter}{$popFilter}{$districtFilter} AND c.name != :exact_query)
            UNION ALL
            (SELECT c.geonameid, c.name, a.name AS matched_name, c.latitude, c.longitude,
                    c.country, c.state, c.timezone, c.population, 2 AS match_rank
             FROM city_alt_names a
             JOIN cityinfo c ON c.geonameid = a.geonameid
             WHERE a.name LIKE :prefix_query{$countryFilter}{$popFilter}{$districtFilter} AND a.name != :exact_query)
            ORDER BY match_rank ASC, population DESC
            LIMIT 40
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $seen = [];
        $cities = [];
        foreach ($rows as $row) {
            if (isset($seen[$row['geonameid']])) {
                continue;
            }
            $seen[$row['geonameid']] = true;
            unset($row['match_rank']);
            $cities[] = $row;
            if (count($cities) >= 20) {
                break;
            }
        }

        $result = ['success' => true, 'data' => $cities];
        cacheSet($cacheKey, $result);
        echo json_encode($result);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handlePlacesSearch(): void
{
    $query = $_GET['q'] ?? '';
    $country = $_GET['country'] ?? '';
    $minPop = max(0, (int)($_GET['minpop'] ?? 0));
    $excludeDistricts = ($_GET['no_districts'] ?? '') === '1';

    if (strlen($query) < 2) {
        echo json_encode(['items' => []]);
        return;
    }

    $cacheKey = "places:" . strtolower($query) . ":{$country}:{$minPop}:" . ($excludeDistricts ? '1' : '0');
    $cached = cacheGet($cacheKey);
    if ($cached !== false) {
        echo json_encode($cached);
        return;
    }

    try {
        $db = getDb();
        $countryFilter = '';
        $params = [];

        if ($country !== '') {
            $countryFilter = ' AND c.country = :country';
            $params['country'] = $country;
        }

        $popFilter = '';
        if ($minPop > 0) {
            $popFilter = ' AND (c.population = 0 OR c.population >= :minpop)';
            $params['minpop'] = $minPop;
        }

        $districtFilter = $excludeDistricts ? " AND c.feature_code != 'PPLX'" : '';

        $params['exact_query'] = $query;
        $params['prefix_query'] = $query . '%';

        $sql = "
            (SELECT c.geonameid, c.name, c.name AS matched_name, c.latitude, c.longitude,
                    c.country, c.state, c.timezone, c.population, 0 AS match_rank
             FROM cityinfo c
             WHERE c.name = :exact_query{$countryFilter}{$popFilter}{$districtFilter})
            UNION ALL
            (SELECT c.geonameid, c.name, a.name AS matched_name, c.latitude, c.longitude,
                    c.country, c.state, c.timezone, c.population, 0 AS match_rank
             FROM city_alt_names a
             JOIN cityinfo c ON c.geonameid = a.geonameid
             WHERE a.name = :exact_query{$countryFilter}{$popFilter}{$districtFilter})
            UNION ALL
            (SELECT c.geonameid, c.name, c.name AS matched_name, c.latitude, c.longitude,
                    c.country, c.state, c.timezone, c.population, 1 AS match_rank
             FROM cityinfo c
             WHERE c.name LIKE :prefix_query{$countryFilter}{$popFilter}{$districtFilter} AND c.name != :exact_query)
            UNION ALL
            (SELECT c.geonameid, c.name, a.name AS matched_name, c.latitude, c.longitude,
                    c.country, c.state, c.timezone, c.population, 2 AS match_rank
             FROM city_alt_names a
             JOIN cityinfo c ON c.geonameid = a.geonameid
             WHERE a.name LIKE :prefix_query{$countryFilter}{$popFilter}{$districtFilter} AND a.name != :exact_query)
            ORDER BY match_rank ASC, population DESC
            LIMIT 40
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $seen = [];
        $unique = [];
        foreach ($rows as $row) {
            if (isset($seen[$row['geonameid']])) {
                continue;
            }
            $seen[$row['geonameid']] = true;
            $unique[] = $row;
            if (count($unique) >= 20) {
                break;
            }
        }
        $rows = $unique;

        $items = array_map(function (array $row) {
            $label = $row['name'];
            if (strcasecmp($row['matched_name'], $row['name']) !== 0) {
                $label .= ' (' . $row['matched_name'] . ')';
            }
            $label .= ($row['state'] ? ', ' . $row['state'] : '') . ', ' . $row['country'];
            return [
                'placeId' => 'geo:' . $row['geonameid'],
                'label' => $label,
                'lat' => (float)$row['latitude'],
                'lon' => (float)$row['longitude'],
                'timezone' => $row['timezone'],
            ];
        }, $rows);

        $result = ['items' => $items];
        cacheSet($cacheKey, $result);
        echo json_encode($result);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleHealth(): void
{
    $cached = cacheGet('health');
    if ($cached !== false) {
        echo json_encode($cached);
        return;
    }

    try {
        $db = getDb();
        $db->query("SELECT 1 FROM cityinfo LIMIT 1")->fetch();
        $result = ['status' => 'ok'];
        cacheSet('health', $result);
        echo json_encode($result);
    } catch (\Exception $e) {
        http_response_code(503);
        echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
    }
}

function handleNotFound(): void
{
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
}
