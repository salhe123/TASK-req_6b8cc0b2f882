<?php
declare(strict_types=1);

namespace tests\api;

use tests\ApiTestCase;

class DashboardApiTest extends ApiTestCase
{
    /** @test GET /api/admin/dashboard returns all counters */
    public function testDashboardCounters()
    {
        $this->loginAsAdmin();
        $resp = $this->get('/api/admin/dashboard');
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);

        $this->assertArrayHasKey('totalUsers', $data);
        $this->assertArrayHasKey('totalAppointments', $data);
        $this->assertArrayHasKey('totalWorkOrders', $data);
        $this->assertArrayHasKey('pendingModeration', $data);
        $this->assertArrayHasKey('openAnomalies', $data);
        $this->assertArrayHasKey('weeklySettlementTotal', $data);

        $this->assertIsInt($data['totalUsers']);
        $this->assertIsInt($data['totalAppointments']);
        $this->assertIsInt($data['totalWorkOrders']);
        $this->assertIsInt($data['pendingModeration']);
        $this->assertIsInt($data['openAnomalies']);
        $this->assertIsNumeric($data['weeklySettlementTotal']);

        $this->assertGreaterThanOrEqual(7, $data['totalUsers']);
    }

    /** @test GET /api/admin/audit/logs returns paginated logs */
    public function testAuditLogs()
    {
        $this->loginAsAdmin();
        $resp = $this->get('/api/admin/audit/logs');
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertArrayHasKey('list', $data);
        $this->assertArrayHasKey('total', $data);

        if (!empty($data['list'])) {
            $log = $data['list'][0];
            $this->assertArrayHasKey('action', $log);
            $this->assertArrayHasKey('entity_type', $log);
            $this->assertArrayHasKey('created_at', $log);
        }
    }

    /** @test Audit logs filtered by action keyword */
    public function testAuditLogsFilterByAction()
    {
        $this->loginAsAdmin();
        $resp = $this->get('/api/admin/audit/logs', ['action' => 'LOGIN']);
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        foreach ($data['list'] as $log) {
            $this->assertStringContainsString('LOGIN', $log['action']);
        }
    }

    /** @test Non-admin cannot access dashboard */
    public function testNonAdminBlocked()
    {
        $this->loginAsProvider();
        $resp = $this->get('/api/admin/dashboard');
        $this->assertResponseCode($resp, 403);
    }
}
