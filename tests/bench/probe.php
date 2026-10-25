<?php
// Reports what the web SAPI actually runs. tests/bench/http copies it into each app container
// for one request and removes it again.
$status = function_exists('opcache_get_status') ? opcache_get_status(false) : false;
echo json_encode([
    'php' => PHP_VERSION,
    'sapi' => PHP_SAPI,
    'opcache' => $status !== false && ($status['opcache_enabled'] ?? false),
    'jit' => $status !== false ? ($status['jit']['on'] ?? false) : null,
    'apcu' => extension_loaded('apcu'),
]), "\n";
