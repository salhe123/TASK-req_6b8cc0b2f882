<?php
declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;

/**
 * Base class for API integration tests.
 * Makes REAL HTTP requests to the running Apache server at localhost:80.
 * This validates actual endpoint routing, middleware, and response payloads.
 */
abstract class ApiTestCase extends TestCase
{
    protected static string $baseUrl = 'http://localhost:80';
    protected static ?string $sessionCookie = null;

    /**
     * Make a real HTTP request to the API via curl.
     */
    protected function httpRequest(string $method, string $uri, array $data = [], array $headers = []): array
    {
        $url = self::$baseUrl . $uri;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

        $reqHeaders = ['Content-Type: application/json', 'Accept: application/json'];
        if (self::$sessionCookie) {
            $reqHeaders[] = 'Cookie: ' . self::$sessionCookie;
        }
        foreach ($headers as $k => $v) {
            $reqHeaders[] = "{$k}: {$v}";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $reqHeaders);

        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            case 'GET':
                if (!empty($data)) {
                    curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($data));
                }
                break;
        }

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            $this->fail("HTTP request failed: {$error}");
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $responseHeaders = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);

        // Capture session cookie for subsequent requests
        if (preg_match('/Set-Cookie:\s*(PHPSESSID=[^;]+)/i', $responseHeaders, $m)) {
            self::$sessionCookie = $m[1];
        }

        $body = json_decode($responseBody, true);

        return [
            'status'  => $httpCode,
            'body'    => $body ?? [],
            'raw'     => $responseBody,
            'headers' => $responseHeaders,
        ];
    }

    protected function get(string $uri, array $params = []): array
    {
        return $this->httpRequest('GET', $uri, $params);
    }

    protected function post(string $uri, array $data = []): array
    {
        return $this->httpRequest('POST', $uri, $data);
    }

    protected function put(string $uri, array $data = []): array
    {
        return $this->httpRequest('PUT', $uri, $data);
    }

    protected function delete(string $uri): array
    {
        return $this->httpRequest('DELETE', $uri);
    }

    /**
     * Upload an attachment via multipart/form-data. Generates a tiny in-memory
     * PNG so check-out / evidence-required flows have a valid image to attach.
     */
    protected function uploadAttachment(int $appointmentId, ?string $bytes = null, string $name = 'evidence.png', string $mime = 'image/png'): array
    {
        $payload = $bytes ?? $this->tinyPngBytes();
        $tmp = tempnam(sys_get_temp_dir(), 'pp_upload_');
        file_put_contents($tmp, $payload);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::$baseUrl . "/api/appointments/{$appointmentId}/attachments");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'file' => new \CURLFile($tmp, $mime, $name),
        ]);
        $headers = ['Accept: application/json'];
        if (self::$sessionCookie) {
            $headers[] = 'Cookie: ' . self::$sessionCookie;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            @unlink($tmp);
            $this->fail("Upload failed: {$err}");
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        @unlink($tmp);

        $body = json_decode(substr($response, $headerSize), true) ?? [];
        return ['status' => $status, 'body' => $body];
    }

    private function tinyPngBytes(): string
    {
        // A 1x1 transparent PNG — smallest valid PNG we can ship inline.
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='
        );
    }

    /**
     * Login via real POST /api/auth/login endpoint. Captures session cookie.
     */
    protected function loginAs(string $username, string $password): array
    {
        self::$sessionCookie = null;
        return $this->post('/api/auth/login', [
            'username' => $username,
            'password' => $password,
        ]);
    }

    protected function loginAsAdmin(): array       { return $this->loginAs('admin', 'Admin12345!'); }
    protected function loginAsCoordinator(): array  { return $this->loginAs('coordinator1', 'Coordinator1!'); }
    protected function loginAsProvider(): array     { return $this->loginAs('provider1', 'Provider1234!'); }
    protected function loginAsPlanner(): array      { return $this->loginAs('planner1', 'Planner12345!'); }
    protected function loginAsModerator(): array    { return $this->loginAs('moderator1', 'Moderator123!'); }
    protected function loginAsReviewer(): array     { return $this->loginAs('reviewer1', 'Reviewer1234!'); }
    protected function loginAsFinance(): array      { return $this->loginAs('finance1', 'Finance12345!'); }

    /**
     * Assert response body 'code' field matches expected.
     */
    protected function assertResponseCode(array $response, int $expected): void
    {
        $actual = $response['body']['code'] ?? $response['status'];
        $this->assertEquals($expected, $actual,
            "Expected code {$expected}, got {$actual}. Body: " . substr(json_encode($response['body']), 0, 500));
    }

    /**
     * Assert response has 'data' key with specific structure.
     */
    protected function assertHasData(array $response): void
    {
        $this->assertArrayHasKey('data', $response['body'], 'Response missing "data" key');
    }

    /**
     * Get 'data' from response.
     */
    protected function getData(array $response)
    {
        return $response['body']['data'] ?? null;
    }
}
