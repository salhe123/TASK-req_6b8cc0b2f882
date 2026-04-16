<?php
namespace think;

require __DIR__ . '/../vendor/autoload.php';

// Boot the ThinkPHP app first so env() and the error mapper are available.
$http = (new App())->http;

// Fail fast at bootstrap if the required cryptographic keys are not set.
// This matches the README's "app refuses to start without keys" guarantee —
// previously the check only fired on the first encrypt/HMAC call, which
// meant listing endpoints appeared healthy while any sensitive mutation
// crashed at runtime.
try {
    require_secret_key('APP_KEY');
    require_secret_key('HMAC_KEY');
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
