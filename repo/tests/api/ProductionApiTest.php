<?php
declare(strict_types=1);

namespace tests\api;

use tests\ApiTestCase;

class ProductionApiTest extends ApiTestCase
{
    /** @test GET /api/production/work-centers returns seeded centers */
    public function testListWorkCenters()
    {
        $this->loginAsPlanner();
        $resp = $this->get('/api/production/work-centers');
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(4, count($data));
        $this->assertArrayHasKey('name', $data[0]);
        $this->assertArrayHasKey('capacity_hours', $data[0]);
    }

    /** @test POST /api/production/work-centers creates work center */
    public function testCreateWorkCenter()
    {
        $this->loginAsPlanner();
        $resp = $this->post('/api/production/work-centers', [
            'name'          => 'Test Center ' . time(),
            'capacityHours' => 45.00,
        ]);
        $this->assertResponseCode($resp, 201);
        $data = $this->getData($resp);
        $this->assertSame('ACTIVE', $data['status']);
        $this->assertEquals(45.00, (float) $data['capacity_hours']);
    }

    /** @test MPS → Explode → Complete flow */
    public function testMpsExplodeComplete()
    {
        $this->loginAsPlanner();

        // Create MPS
        $resp = $this->post('/api/production/mps', [
            'productId' => 1,
            'weekStart' => '04/27/2026',
            'quantity'  => 200,
        ]);
        $this->assertResponseCode($resp, 201);
        $mpsId = $this->getData($resp)['id'];

        // Explode
        $resp = $this->post('/api/production/work-orders/explode', ['mpsId' => $mpsId]);
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertArrayHasKey('workOrdersCreated', $data);
        $this->assertGreaterThan(0, $data['workOrdersCreated']);

        // List work orders
        $resp = $this->get('/api/production/work-orders');
        $this->assertResponseCode($resp, 200);
        $wos = $this->getData($resp)['list'];
        $this->assertNotEmpty($wos);

        // Complete one — the strict state machine requires PENDING → IN_PROGRESS first.
        $woId = $wos[0]['id'];
        $this->put("/api/production/work-orders/{$woId}/start");
        $resp = $this->put("/api/production/work-orders/{$woId}/complete", [
            'quantityCompleted' => 95,
            'quantityRework'    => 5,
            'downtimeMinutes'   => 15,
            'reasonCode'        => 'MATERIAL_DELAY',
        ]);
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertSame('COMPLETED', $data['status']);
        $this->assertEquals(95, $data['quantity_completed']);
        $this->assertSame('MATERIAL_DELAY', $data['reason_code']);
    }

    /** @test GET /api/production/capacity returns load percentages */
    public function testCapacityLoading()
    {
        $this->loginAsPlanner();
        $resp = $this->get('/api/production/capacity');
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertIsArray($data);
        foreach ($data as $wc) {
            $this->assertArrayHasKey('workCenterId', $wc);
            $this->assertArrayHasKey('loadPercent', $wc);
            $this->assertArrayHasKey('warning', $wc);
            $this->assertIsBool($wc['warning']);
        }
    }

    /** @test PUT /api/production/mps/{id} updates quantity */
    public function testUpdateMps()
    {
        $this->loginAsPlanner();
        $resp = $this->post('/api/production/mps', [
            'productId' => 1, 'weekStart' => '05/04/2026', 'quantity' => 100,
        ]);
        $mpsId = $this->getData($resp)['id'];

        $resp = $this->put("/api/production/mps/{$mpsId}", ['quantity' => 300]);
        $this->assertResponseCode($resp, 200);
    }

    /** @test DELETE /api/production/mps/{id} deletes entry */
    public function testDeleteMps()
    {
        $this->loginAsPlanner();
        $resp = $this->post('/api/production/mps', [
            'productId' => 1, 'weekStart' => '05/11/2026', 'quantity' => 50,
        ]);
        $mpsId = $this->getData($resp)['id'];

        $resp = $this->delete("/api/production/mps/{$mpsId}");
        $this->assertResponseCode($resp, 200);
    }

    /** @test GET /api/production/work-orders/{id} returns single work order */
    public function testGetWorkOrder()
    {
        $this->loginAsPlanner();
        $resp = $this->get('/api/production/work-orders');
        // Create MPS + explode to ensure work orders exist
        $mpsResp = $this->post('/api/production/mps', [
            'productId' => 1, 'weekStart' => '07/06/2026', 'quantity' => 100,
        ]);
        $this->assertResponseCode($mpsResp, 201);
        $this->post('/api/production/work-orders/explode', ['mpsId' => $this->getData($mpsResp)['id']]);

        $resp = $this->get('/api/production/work-orders');
        $data = $this->getData($resp);
        $this->assertNotEmpty($data['list'], 'Work orders should exist after explode');
        $woId = $data['list'][0]['id'];
        $resp = $this->get("/api/production/work-orders/{$woId}");
        $this->assertResponseCode($resp, 200);
        $wo = $this->getData($resp);
        $this->assertArrayHasKey('quantity_planned', $wo);
        $this->assertArrayHasKey('status', $wo);
    }

    /** @test GET /api/production/mps returns rolling schedule */
    public function testListMps()
    {
        $this->loginAsPlanner();
        $resp = $this->get('/api/production/mps');
        $this->assertResponseCode($resp, 200);
        $this->assertIsArray($this->getData($resp));
    }

    /** @test PUT /api/production/work-centers/{id} updates capacity */
    public function testUpdateWorkCenter()
    {
        $this->loginAsPlanner();
        $resp = $this->get('/api/production/work-centers');
        $centers = $this->getData($resp);
        $this->assertNotEmpty($centers, 'Seeded work centers should exist');
        $wcId = $centers[0]['id'];
        $resp = $this->put("/api/production/work-centers/{$wcId}", [
            'capacityHours' => 50.00,
        ]);
        $this->assertResponseCode($resp, 200);
    }

    /** @test Non-planner cannot access production endpoints */
    public function testNonPlannerBlocked()
    {
        $this->loginAsFinance();
        $resp = $this->get('/api/production/mps');
        $this->assertResponseCode($resp, 403);
    }

    /** @test POST /api/production/work-orders/explode with invalid mpsId */
    public function testExplodeInvalidMps()
    {
        $this->loginAsPlanner();
        $resp = $this->post('/api/production/work-orders/explode', ['mpsId' => 999999]);
        $code = $resp['body']['code'] ?? $resp['status'];
        $this->assertGreaterThanOrEqual(400, $code, "Expected error status >= 400 for invalid MPS, got {$code}");
    }

    /** @test GET /api/production/work-centers/{id} returns single work center */
    public function testGetWorkCenterById()
    {
        $this->loginAsPlanner();
        $resp = $this->get('/api/production/work-centers');
        $centers = $this->getData($resp);
        $this->assertNotEmpty($centers, 'Should have seeded work centers');
        $wcId = $centers[0]['id'];

        $resp = $this->get("/api/production/work-centers/{$wcId}");
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('capacity_hours', $data);
        $this->assertSame($wcId, $data['id']);
    }

    /** @test DELETE /api/production/work-centers/{id} deletes work center */
    public function testDeleteWorkCenter()
    {
        $this->loginAsPlanner();
        // Create one to delete
        $resp = $this->post('/api/production/work-centers', [
            'name' => 'Delete Me ' . time(), 'capacityHours' => 20.00,
        ]);
        $this->assertResponseCode($resp, 201);
        $wcId = $this->getData($resp)['id'];

        $resp = $this->delete("/api/production/work-centers/{$wcId}");
        $this->assertResponseCode($resp, 200);

        // Verify it's gone
        $resp = $this->get("/api/production/work-centers/{$wcId}");
        $this->assertResponseCode($resp, 404);
    }

    /** @test GET /api/production/work-centers/{id} with invalid id returns 404 */
    public function testGetWorkCenterNotFound()
    {
        $this->loginAsPlanner();
        $resp = $this->get('/api/production/work-centers/999999');
        $this->assertResponseCode($resp, 404);
    }

    /** @test POST /api/production/work-centers with missing fields returns 400 */
    public function testCreateWorkCenterMissingFields()
    {
        $this->loginAsPlanner();
        $resp = $this->post('/api/production/work-centers', ['name' => 'No Capacity']);
        $this->assertResponseCode($resp, 400);
    }

    /** @test GET /api/production/work-orders with status filter */
    public function testListWorkOrdersWithFilter()
    {
        $this->loginAsPlanner();
        $resp = $this->get('/api/production/work-orders', ['status' => 'PENDING']);
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertArrayHasKey('list', $data);
    }

    /** @test PUT /api/production/work-orders/{id}/complete with valid reason code */
    public function testCompleteWorkOrderReasonCode()
    {
        $this->loginAsPlanner();
        // Create fresh MPS + explode to guarantee a PENDING work order
        $mpsResp = $this->post('/api/production/mps', [
            'productId' => 1, 'weekStart' => '07/13/2026', 'quantity' => 100,
        ]);
        $this->assertResponseCode($mpsResp, 201);
        $this->post('/api/production/work-orders/explode', ['mpsId' => $this->getData($mpsResp)['id']]);

        $resp = $this->get('/api/production/work-orders', ['status' => 'PENDING']);
        $data = $this->getData($resp);
        $this->assertNotEmpty($data['list'], 'Should have PENDING work orders');
        $woId = $data['list'][0]['id'];

        // Strict transitions: start before completing.
        $this->put("/api/production/work-orders/{$woId}/start");
        $resp = $this->put("/api/production/work-orders/{$woId}/complete", [
            'quantityCompleted' => 90, 'quantityRework' => 5,
            'downtimeMinutes' => 10, 'reasonCode' => 'QUALITY_ISSUE',
        ]);
        $this->assertResponseCode($resp, 200);
        $this->assertSame('COMPLETED', $this->getData($resp)['status']);
        $this->assertSame('QUALITY_ISSUE', $this->getData($resp)['reason_code']);
    }
}
