<?php
// Clear APCu cache — call after data re-imports.
// Same key check as the API: required whenever API_KEY is set.
if (file_exists(__DIR__ . '/.env.php')) {
    require __DIR__ . '/.env.php';
}

header('Content-Type: application/json; charset=utf-8');

$apiKey = getenv('API_KEY') ?: '';
if ($apiKey !== '') {
    $provided = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
    if ($provided !== $apiKey) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized', 'message' => 'Valid API key required via X-Api-Key header or api_key query parameter']);
        exit;
    }
}

if (function_exists('apcu_clear_cache')) {
    apcu_clear_cache();
    echo json_encode(['success' => true, 'message' => 'APCu cache cleared']);
} else {
    echo json_encode(['success' => true, 'message' => 'APCu not available, nothing to clear']);
}