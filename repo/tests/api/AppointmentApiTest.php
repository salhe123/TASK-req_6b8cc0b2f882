<?php
declare(strict_types=1);

namespace tests\api;

use tests\ApiTestCase;

class AppointmentApiTest extends ApiTestCase
{
    /** @test POST /api/appointments creates appointment in PENDING status */
    public function testCreateAppointment()
    {
        $this->loginAsCoordinator();
        $resp = $this->post('/api/appointments', [
            'customerId' => 3,
            'providerId' => 4,
            'dateTime'   => '04/25/2026 02:30 PM',
            'location'   => 'Building A, Floor 2',
        ]);

        $this->assertResponseCode($resp, 201);
        $data = $this->getData($resp);
        $this->assertSame('PENDING', $data['status']);
        $this->assertSame('Building A, Floor 2', $data['location']);
        $this->assertArrayHasKey('id', $data);
    }

    /** @test Full lifecycle: create → confirm → check-in → upload evidence → check-out */
    public function testFullLifecycle()
    {
        // Create
        $this->loginAsCoordinator();
        $resp = $this->post('/api/appointments', [
            'customerId' => 3, 'providerId' => 4,
            'dateTime' => '04/25/2026 02:30 PM', 'location' => 'E2E Test',
        ]);
        $this->assertResponseCode($resp, 201);
        $id = $this->getData($resp)['id'];

        // Confirm
        $resp = $this->put("/api/appointments/{$id}/confirm");
        $this->assertResponseCode($resp, 200);
        $this->assertSame('CONFIRMED', $this->getData($resp)['status']);

        // Check-in (as provider)
        $this->loginAsProvider();
        $resp = $this->put("/api/appointments/{$id}/check-in");
        $this->assertResponseCode($resp, 200);
        $this->assertSame('IN_PROGRESS', $this->getData($resp)['status']);

        // Upload completion evidence (required before check-out)
        $upload = $this->uploadAttachment($id);
        $this->assertSame(201, $upload['status'], 'Evidence upload should succeed');

        // Check-out
        $resp = $this->put("/api/appointments/{$id}/check-out");
        $this->assertResponseCode($resp, 200);
        $this->assertSame('COMPLETED', $this->getData($resp)['status']);

        // Verify history exists and appointment ended as COMPLETED
        $this->loginAsCoordinator();
        $resp = $this->get("/api/appointments/{$id}/history");
        $this->assertResponseCode($resp, 200);
        $history = $this->getData($resp);
        $this->assertNotEmpty($history);

        // Verify final state via the appointment itself
        $resp = $this->get("/api/appointments/{$id}");
        $this->assertResponseCode($resp, 200);
        $this->assertSame('COMPLETED', $this->getData($resp)['status']);
    }

    /** @test Cancel from PENDING */
    public function testCancelPending()
    {
        $this->loginAsCoordinator();
        $resp = $this->post('/api/appointments', [
            'customerId' => 3, 'providerId' => 4,
            'dateTime' => '04/25/2026 03:00 PM', 'location' => 'Cancel Test',
        ]);
        $id = $this->getData($resp)['id'];

        $resp = $this->put("/api/appointments/{$id}/cancel", ['reason' => 'Customer requested']);
        $this->assertResponseCode($resp, 200);
        $this->assertSame('CANCELLED', $this->getData($resp)['status']);
    }

    /** @test Cannot cancel COMPLETED appointment (invalid transition) */
    public function testCannotCancelCompleted()
    {
        $this->loginAsCoordinator();
        $resp = $this->post('/api/appointments', [
            'customerId' => 3, 'providerId' => 4,
            'dateTime' => '04/25/2026 04:00 PM', 'location' => 'No Cancel',
        ]);
        $id = $this->getData($resp)['id'];

        $this->put("/api/appointments/{$id}/confirm");
        $this->loginAsProvider();
        $this->put("/api/appointments/{$id}/check-in");
        // Upload evidence before check-out (now mandatory).
        $this->uploadAttachment($id);
        $this->put("/api/appointments/{$id}/check-out");

        $this->loginAsCoordinator();
        $resp = $this->put("/api/appointments/{$id}/cancel", ['reason' => 'Too late']);
        // COMPLETED cannot transition to CANCELLED — expect 400 or 409
        $code = $resp['body']['code'] ?? $resp['status'];
        $this->assertGreaterThanOrEqual(400, $code, "Expected error for cancel-completed, got {$code}");
    }

    /** @test Check-out without evidence is rejected */
    public function testCheckoutRequiresEvidence()
    {
        $this->loginAsCoordinator();
        $resp = $this->post('/api/appointments', [
            'customerId' => 3, 'providerId' => 4,
            'dateTime' => '05/02/2026 09:00 AM', 'location' => 'Evidence Gate',
        ]);
        $id = $this->getData($resp)['id'];
        $this->put("/api/appointments/{$id}/confirm");

        $this->loginAsProvider();
        $this->put("/api/appointments/{$id}/check-in");

        $resp = $this->put("/api/appointments/{$id}/check-out");
        $this->assertResponseCode($resp, 409);

        // Now upload and try again
        $this->uploadAttachment($id);
        $resp = $this->put("/api/appointments/{$id}/check-out");
        $this->assertResponseCode($resp, 200);
        $this->assertSame('COMPLETED', $this->getData($resp)['status']);
    }

    /** @test Non-coordinator cannot create appointment (RBAC) */
    public function testNonCoordinatorCannotCreate()
    {
        $this->loginAsFinance();
        $resp = $this->post('/api/appointments', [
            'customerId' => 3, 'providerId' => 4,
            'dateTime' => '05/05/2026 10:00 AM', 'location' => 'RBAC Block',
        ]);
        $this->assertResponseCode($resp, 403);
    }

    /** @test Admin reschedule override within 2 hours REQUIRES a reason */
    public function testAdminRescheduleWithin2HoursRequiresReason()
    {
        $this->loginAsCoordinator();
        $soonTime = date('m/d/Y h:i A', strtotime('+15 minutes'));
        $resp = $this->post('/api/appointments', [
            'customerId' => 3, 'providerId' => 4,
            'dateTime' => $soonTime, 'location' => 'Near Start',
        ]);
        $id = $this->getData($resp)['id'];

        $this->loginAsAdmin();
        // Without reason → 400
        $resp = $this->put("/api/appointments/{$id}/reschedule", [
            'newDateTime' => '05/15/2026 10:00 AM',
        ]);
        $code = $resp['body']['code'] ?? $resp['status'];
        $this->assertSame(400, $code, 'Admin override without reason must be rejected');

        // With reason → 200
        $resp = $this->put("/api/appointments/{$id}/reschedule", [
            'newDateTime' => '05/16/2026 10:00 AM',
            'reason'      => 'Customer medical emergency',
        ]);
        $this->assertResponseCode($resp, 200);
    }

    /** @test Admin repair changes state */
    public function testAdminRepair()
    {
        $this->loginAsCoordinator();
        $resp = $this->post('/api/appointments', [
            'customerId' => 3, 'providerId' => 4,
            'dateTime' => '04/25/2026 05:00 PM', 'location' => 'Repair Test',
        ]);
        $id = $this->getData($resp)['id'];

        $this->put("/api/appointments/{$id}/cancel", ['reason' => 'mistake']);

        $this->loginAsAdmin();
        $resp = $this->put("/api/appointments/{$id}/repair", [
            'targetState' => 'CONFIRMED',
            'reason'      => 'Incorrect cancellation',
        ]);
        $this->assertResponseCode($resp, 200);
        $this->assertSame('CONFIRMED', $this->getData($resp)['status']);
    }

    /** @test GET /api/appointments returns paginated list */
    public function testListAppointments()
    {
        $this->loginAsCoordinator();
        $resp = $this->get('/api/appointments');
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertArrayHasKey('list', $data);
        $this->assertArrayHasKey('total', $data);
    }

    /** @test GET /api/appointments/{id} returns single appointment */
    public function testGetAppointment()
    {
        $this->loginAsCoordinator();
        $resp = $this->post('/api/appointments', [
            'customerId' => 3, 'providerId' => 4,
            'dateTime' => '04/26/2026 10:00 AM', 'location' => 'Read Test',
        ]);
        $id = $this->getData($resp)['id'];

        $resp = $this->get("/api/appointments/{$id}");
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertSame($id, $data['id']);
        $this->assertSame('Read Test', $data['location']);
    }

    /** @test GET /api/appointments/999999 returns 404 */
    public function testGetNonexistent()
    {
        $this->loginAsCoordinator();
        $resp = $this->get('/api/appointments/999999');
        $this->assertResponseCode($resp, 404);
    }

    /** @test PUT /api/appointments/{id}/reschedule succeeds with enough lead time */
    public function testRescheduleSuccess()
    {
        $this->loginAsCoordinator();
        $resp = $this->post('/api/appointments', [
            'customerId' => 3, 'providerId' => 4,
            'dateTime' => '04/30/2026 02:00 PM', 'location' => 'Reschedule Test',
        ]);
        $id = $this->getData($resp)['id'];

        $resp = $this->put("/api/appointments/{$id}/reschedule", [
            'newDateTime' => '05/01/2026 10:00 AM',
        ]);
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertArrayHasKey('date_time', $data);
    }

    /** @test PUT /api/appointments/{id}/reschedule blocked within 2 hours — strict 409 */
    public function testRescheduleBlockedWithin2Hours()
    {
        $this->loginAsCoordinator();
        // Create appointment starting well within 2-hour window
        $soonTime = date('m/d/Y h:i A', strtotime('+30 minutes'));
        $resp = $this->post('/api/appointments', [
            'customerId' => 3, 'providerId' => 4,
            'dateTime' => $soonTime, 'location' => 'Soon Appt',
        ]);
        $this->assertResponseCode($resp, 201);
        $id = $this->getData($resp)['id'];

        $resp = $this->put("/api/appointments/{$id}/reschedule", [
            'newDateTime' => '05/10/2026 10:00 AM',
        ]);
        $this->assertResponseCode($resp, 409);
    }

    /** @test GET /api/appointments/{id}/attachments returns list */
    public function testListAttachments()
    {
        $this->loginAsCoordinator();
        $resp = $this->post('/api/appointments', [
            'customerId' => 3, 'providerId' => 4,
            'dateTime' => '04/28/2026 09:00 AM', 'location' => 'Attach Test',
        ]);
        $id = $this->getData($resp)['id'];

        $resp = $this->get("/api/appointments/{$id}/attachments");
        $this->assertResponseCode($resp, 200);
        $this->assertIsArray($this->getData($resp));
    }

    /** @test GET /api/provider/queue returns daily queue for provider */
    public function testProviderQueue()
    {
        // Create an appointment for provider1 today
        $this->loginAsCoordinator();
        $todayTime = date('m/d/Y') . ' 03:00 PM';
        $resp = $this->post('/api/appointments', [
            'customerId' => 3, 'providerId' => 4,
            'dateTime' => $todayTime, 'location' => 'Queue Test',
        ]);
        $this->assertResponseCode($resp, 201);
        $id = $this->getData($resp)['id'];
        $this->put("/api/appointments/{$id}/confirm");

        // Check provider queue
        $this->loginAsProvider();
        $resp = $this->get('/api/provider/queue');
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertArrayHasKey('appointments', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('date', $data);
    }

    /** @test Non-provider cannot access provider queue */
    public function testProviderQueueRbac()
    {
        $this->loginAsFinance();
        $resp = $this->get('/api/provider/queue');
        $this->assertResponseCode($resp, 403);
    }

    /** @test PUT /api/appointments/{id}/repair requires admin role */
    public function testRepairRequiresAdmin()
    {
        $this->loginAsCoordinator();
        $resp = $this->post('/api/appointments', [
            'customerId' => 3, 'providerId' => 4,
            'dateTime' => '04/29/2026 11:00 AM', 'location' => 'RBAC Test',
        ]);
        $id = $this->getData($resp)['id'];

        // Coordinator should not be able to repair
        $resp = $this->put("/api/appointments/{$id}/repair", [
            'targetState' => 'CONFIRMED', 'reason' => 'test',
        ]);
        $this->assertResponseCode($resp, 403);
    }

    /** @test POST /api/appointments with missing fields returns 400 */
    public function testCreateMissingFields()
    {
        $this->loginAsCoordinator();
        $resp = $this->post('/api/appointments', ['customerId' => 3]);
        $this->assertResponseCode($resp, 400);
    }

    /** @test POST /api/appointments/{id}/attachments without file returns 400 (provider path) */
    public function testUploadAttachmentNoFile()
    {
        $this->loginAsCoordinator();
        $resp = $this->post('/api/appointments', [
            'customerId' => 3, 'providerId' => 4,
            'dateTime' => '04/29/2026 10:00 AM', 'location' => 'Upload Test',
        ]);
        $id = $this->getData($resp)['id'];
        $this->put("/api/appointments/{$id}/confirm");

        // Switch to provider — the only role allowed to upload evidence.
        $this->loginAsProvider();
        $resp = $this->post("/api/appointments/{$id}/attachments", []);
        $this->assertResponseCode($resp, 400);
    }

    /** @test Coordinator cannot upload evidence (provider-only) */
    public function testCoordinatorCannotUploadEvidence()
    {
        $this->loginAsCoordinator();
        $resp = $this->post('/api/appointments', [
            'customerId' => 3, 'providerId' => 4,
            'dateTime' => '05/08/2026 09:00 AM', 'location' => 'Coord Upload',
        ]);
        $id = $this->getData($resp)['id'];
        $this->put("/api/appointments/{$id}/confirm");

        // Still logged in as coordinator — upload must be 403 at the route layer.
        $upload = $this->uploadAttachment($id);
        $this->assertSame(403, $upload['status']);
    }

    /** @test GET /api/appointments with status filter */
    public function testListWithStatusFilter()
    {
        $this->loginAsCoordinator();
        $resp = $this->get('/api/appointments', ['status' => 'PENDING']);
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertArrayHasKey('list', $data);
        foreach ($data['list'] as $appt) {
            $this->assertSame('PENDING', $appt['status']);
        }
    }

    /** @test PUT /api/appointments/{id}/cancel with reason validates payload */
    public function testCancelWithReason()
    {
        $this->loginAsCoordinator();
        $resp = $this->post('/api/appointments', [
            'customerId' => 3, 'providerId' => 4,
            'dateTime' => '04/30/2026 11:00 AM', 'location' => 'Cancel Reason Test',
        ]);
        $id = $this->getData($resp)['id'];

        $resp = $this->put("/api/appointments/{$id}/cancel", ['reason' => 'Customer no-show']);
        $this->assertResponseCode($resp, 200);
        $this->assertSame('CANCELLED', $this->getData($resp)['status']);
    }
}
