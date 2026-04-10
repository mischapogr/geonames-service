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

        echo json_encode(['success' => true, 'data' => $city]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleCitySearch(): void
{
    $query = $_GET['q'] ?? '';
    $country = $_GET['country'] ?? '';

    if (strlen($query) < 2) {
        echo json_encode(['success' => true, 'data' => []]);
        return;
    }

    try {
        $db = getDb();
        $countryFilter = '';
        $params = ['query' => $query . '%'];

        if ($country !== '') {
            $countryFilter = ' AND c.country = :country';
            $params['country'] = $country;
        }

        $sql = "
            (SELECT c.geonameid, c.name, c.name AS matched_name, c.latitude, c.longitude,
                    c.country, c.state, c.timezone, c.population
             FROM cityinfo c
             WHERE c.name LIKE :query{$countryFilter})
            UNION
            (SELECT c.geonameid, c.name, a.name AS matched_name, c.latitude, c.longitude,
                    c.country, c.state, c.timezone, c.population
             FROM city_alt_names a
             JOIN cityinfo c ON c.geonameid = a.geonameid
             WHERE a.name LIKE :query{$countryFilter})
            ORDER BY population DESC
            LIMIT 20
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $cities]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handlePlacesSearch(): void
{
    $query = $_GET['q'] ?? '';
    $country = $_GET['country'] ?? '';

    if (strlen($query) < 2) {
        echo json_encode(['items' => []]);
        return;
    }

    try {
        $db = getDb();
        $sql = "SELECT geonameid, name, latitude, longitude, country, state, timezone
                FROM cityinfo
                WHERE name LIKE :query";
        $params = ['query' => $query . '%'];

        if ($country !== '') {
            $sql .= " AND country = :country";
            $params['country'] = $country;
        }

        $sql .= " ORDER BY name LIMIT 20";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $items = array_map(function (array $row) {
            return [
                'placeId' => 'geo:' . $row['geonameid'],
                'label' => $row['name'] . ($row['state'] ? ', ' . $row['state'] : '') . ', ' . $row['country'],
                'lat' => (float)$row['latitude'],
                'lon' => (float)$row['longitude'],
                'timezone' => $row['timezone'],
            ];
        }, $rows);

        echo json_encode(['items' => $items]);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleHealth(): void
{
    try {
        $db = getDb();
        $count = $db->query("SELECT COUNT(*) FROM cityinfo")->fetchColumn();
        echo json_encode(['status' => 'ok', 'cities' => (int)$count]);
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
