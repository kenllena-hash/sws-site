<?php
require_once __DIR__ . '/config.php';

// Minimal public health response: do not disclose database names or table names.
header('Content-Type: application/json; charset=utf-8');
http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'SWS API and database connection are ready.'
]);
