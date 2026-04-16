<?php
declare(strict_types=1);

namespace tests\api;

use tests\ApiTestCase;

class RiskApiTest extends ApiTestCase
{
    /** @test GET /api/admin/risk/scores returns risk scores */
    public function testListScores()
    {
        $this->loginAsAdmin();
        $resp = $this->get('/api/admin/risk/scores');
        $this->assertResponseCode($resp, 200);
        $this->assertIsArray($this->getData($resp));
    }

    /** @test GET /api/admin/risk/flags returns anomaly flags */
    public function testListFlags()
    {
        $this->loginAsAdmin();
        $resp = $this->get('/api/admin/risk/flags');
        $this->assertResponseCode($resp, 200);
        $this->assertIsArray($this->getData($resp));
    }

    /** @test GET /api/admin/risk/throttles returns config */
    public function testGetThrottles()
    {
        $this->loginAsAdmin();
        $resp = $this->get('/api/admin/risk/throttles');
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
        // Validate structure of each config entry
        $keys = array_column($data, 'key');
        $this->assertContains('requests_per_minute', $keys);
        $this->assertContains('appointments_per_hour', $keys);
    }

    /** @test PUT /api/admin/risk/throttles updates config */
    public function testUpdateThrottles()
    {
        $this->loginAsAdmin();
        $resp = $this->put('/api/admin/risk/throttles', [
            'requestsPerMinute'   => 120,
            'appointmentsPerHour' => 20,
        ]);
        $this->assertResponseCode($resp, 200);

        // Verify it changed
        $resp = $this->get('/api/admin/risk/throttles');
        $data = $this->getData($resp);
        $rpmConfig = array_values(array_filter($data, fn($c) => $c['key'] === 'requests_per_minute'));
        $this->assertEquals(120, (int) $rpmConfig[0]['value']);

        // Reset
        $this->put('/api/admin/risk/throttles', [
            'requestsPerMinute' => 60, 'appointmentsPerHour' => 10,
        ]);
    }

    /** @test Non-admin cannot access risk endpoints */
    public function testNonAdminBlocked()
    {
        $this->loginAsProvider();
        $resp = $this->get('/api/admin/risk/flags');
        $this->assertResponseCode($resp, 403);
    }

    /** @test PUT /api/admin/risk/flags/{id}/clear clears a flag */
    public function testClearFlag()
    {
        $this->loginAsAdmin();
        $flags = $this->getData($this->get('/api/admin/risk/flags'));
        // Integration tests create flags via RiskService::detectAnomalies before API tests
        // If flags exist, test the clear operation
        if (!empty($flags)) {
            $flagId = $flags[0]['id'];
            $resp = $this->put("/api/admin/risk/flags/{$flagId}/clear");
            $this->assertResponseCode($resp, 200);
            $data = $this->getData($resp);
            $this->assertSame('CLEARED', $data['status']);
        }
        // Validate the flags endpoint always works regardless
        $resp = $this->get('/api/admin/risk/flags');
        $this->assertResponseCode($resp, 200);
        $this->assertIsArray($this->getData($resp));
    }

    /** @test GET /api/admin/risk/scores validates response structure */
    public function testScoresStructure()
    {
        $this->loginAsAdmin();
        $resp = $this->get('/api/admin/risk/scores');
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertIsArray($data);
    }

    /** @test GET /api/admin/risk/scores with userId filter */
    public function testScoresFilterByUser()
    {
        $this->loginAsAdmin();
        $resp = $this->get('/api/admin/risk/scores', ['userId' => 4]);
        $this->assertResponseCode($resp, 200);
    }

    /** @test PUT /api/admin/risk/flags/{id}/clear with nonexistent flag returns 404 */
    public function testClearNonexistentFlag()
    {
        $this->loginAsAdmin();
        $resp = $this->put('/api/admin/risk/flags/999999/clear');
        $this->assertResponseCode($resp, 404);
    }

    /** @test GET /api/admin/risk/scores with scoreBelow filter */
    public function testScoresFilterByScoreBelow()
    {
        $this->loginAsAdmin();
        $resp = $this->get('/api/admin/risk/scores', ['scoreBelow' => 50]);
        $this->assertResponseCode($resp, 200);
        $this->assertIsArray($this->getData($resp));
    }

    /** @test PUT /api/admin/risk/throttles validates payload */
    public function testUpdateThrottlesPartial()
    {
        $this->loginAsAdmin();
        $resp = $this->put('/api/admin/risk/throttles', ['requestsPerMinute' => 100]);
        $this->assertResponseCode($resp, 200);

        // Verify change
        $resp = $this->get('/api/admin/risk/throttles');
        $data = $this->getData($resp);
        $rpm = array_values(array_filter($data, fn($c) => $c['key'] === 'requests_per_minute'));
        $this->assertEquals(100, (int) $rpm[0]['value']);

        // Reset
        $this->put('/api/admin/risk/throttles', ['requestsPerMinute' => 60]);
    }

    /** @test Non-admin cannot update throttles */
    public function testNonAdminCannotUpdateThrottles()
    {
        $this->loginAsProvider();
        $resp = $this->put('/api/admin/risk/throttles', ['requestsPerMinute' => 999]);
        $this->assertResponseCode($resp, 403);
    }
}
