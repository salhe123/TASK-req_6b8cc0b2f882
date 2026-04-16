<?php

use think\Response;

/**
 * Return a JSON success response.
 */
function json_success($data = [], string $message = 'ok', int $code = 200): Response
{
    return json([
        'code'    => $code,
        'message' => $message,
        'data'    => $data,
    ])->code($code);
}

/**
 * Return a JSON error response.
 */
function json_error(string $message = 'error', int $code = 400, $data = []): Response
{
    return json([
        'code'    => $code,
        'message' => $message,
        'data'    => $data,
    ])->code($code);
}

function require_secret_key(string $envVar): string
{
    $key = env($envVar, '');
    if ($key === '' || strlen($key) < 32) {
        throw new \RuntimeException(
            "{$envVar} is not configured or is too short (min 32 chars). "
            . "Set it in the environment before starting the application."
        );
    }
    return $key;
}

function encrypt_field(string $value): string
{
    $key    = require_secret_key('APP_KEY');
    $iv     = openssl_random_pseudo_bytes(16);
    $cipher = openssl_encrypt($value, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($iv . '::' . $cipher);
}

function decrypt_field(string $value): string
{
    $key  = require_secret_key('APP_KEY');
    $data = base64_decode($value);
    [$iv, $cipher] = explode('::', $data, 2);
    return openssl_decrypt($cipher, 'AES-256-CBC', $key, 0, $iv);
}

function hmac_sign(string $data): string
{
    $key = require_secret_key('HMAC_KEY');
    return hash_hmac('sha256', $data, $key);
}

function hmac_verify(string $data, string $signature): bool
{
    $expected = hmac_sign($data);
    return hash_equals($expected, $signature);
}

/**
 * Map an uncaught exception to a safe user-facing message without leaking
 * internal state (SQL fragments, stack frames, file paths). The original
 * exception should be handed to `\think\facade\Log::error()` or similar by
 * the caller before invoking this helper — only the return value is safe to
 * include in a JSON response body.
 */
function safe_error_message(\Throwable $e, string $fallback = 'Request could not be completed'): string
{
    // Our own domain exceptions are authored for users — pass through.
    if ($e instanceof \think\exception\ValidateException || $e instanceof \think\exception\HttpException) {
        return $e->getMessage();
    }
    // PDO / framework / generic — do NOT surface internals.
    $msg = $e->getMessage();
    if ($msg === '' || stripos($msg, 'SQLSTATE') !== false || stripos($msg, 'PDOException') !== false) {
        return $fallback;
    }
    // Strip anything that looks like an absolute path or stack-frame fragment.
    if (preg_match('#(/[^\s"\']+\.php|Stack trace:|in \S+\.php on line)#', $msg)) {
        return $fallback;
    }
    return $msg;
}
