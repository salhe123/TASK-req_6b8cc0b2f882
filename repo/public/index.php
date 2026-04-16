<?php
namespace think;

require __DIR__ . '/../vendor/autoload.php';

$http = (new App())->http;

// Fail fast at bootstrap if the required cryptographic keys are not set.
// Leading-backslash calls are important: this file lives in `namespace think;`
// so the global helper + exception must be referenced via the root namespace.
try {
    \require_secret_key('APP_KEY');
    \require_secret_key('HMAC_KEY');
} catch (\RuntimeException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'code'    => 500,
        'message' => 'Startup failed: ' . $e->getMessage(),
    ]);
    exit(1);
}

$response = $http->run();
$response->send();
$http->end($response);
