<?php
// Clear APCu cache — call after data re-imports
if (function_exists('apcu_clear_cache')) {
    apcu_clear_cache();
    echo json_encode(['success' => true, 'message' => 'APCu cache cleared']);
} else {
    echo json_encode(['success' => true, 'message' => 'APCu not available, nothing to clear']);
}