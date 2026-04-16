<?php
declare(strict_types=1);

namespace tests\integration;

use tests\IntegrationTestCase;
use app\service\AppointmentService;
use app\model\Appointment;
use think\facade\Db;

class AppointmentServiceIntegrationTest extends IntegrationTestCase
{
    private function makeAppointment(array $overrides = []): Appointment
    {
        return AppointmentService::create(array_merge([
            'customerId' => 3, 'providerId' => 4,
            'dateTime' => '04/25/2026 02:30 PM', 'location' => 'Test Location',
        ], $overrides), $overrides['createdBy'] ?? 1);
    }

    /**
     * Persist a synthetic evidence attachment so check-out can succeed. Tests that
     * exercise the "happy path" through COMPLETED need this; tests that validate
     * the evidence gate explicitly should NOT call it.
     */
    private function attachEvidence(int $appointmentId, int $uploadedBy = 4): void
    {
        Db::name('appointment_attachments')->insert([
            'appointment_id' => $appointmentId,
            'file_name'      => 'synthetic.png',
            'file_path'      => 'photos/synthetic.png',
            'file_type'      => 'image/png',
            'file_size'      => 42,
            'uploaded_by'    => $uploadedBy,
            'created_at'     => date('Y-m-d H:i:s'),
        ]);
    }

    public function testCreateAppointmentPending()
    {
        $appt = $this->makeAppointment();
        $this->assertSame('PENDING', $appt->status);
        $this->assertNotNull($appt->id);
    }

    public function testCreateRecordsHistory()
    {
        $appt = $this->makeAppointment();
        $h = Db::name('appointment_history')->where('appointment_id', $appt->id)->select()->toArray();
        $this->assertCount(1, $h);
        $this->assertSame('PENDING', $h[0]['to_status']);
    }

    public function testConfirm()
    {
        $appt = $this->makeAppointment();
        $confirmed = AppointmentService::confirm($appt->id, 1);
        $this->assertSame('CONFIRMED', $confirmed->status);
    }

    public function testCheckInFromConfirmed()
    {
        $appt = $this->makeAppointment();
        AppointmentService::confirm($appt->id, 1);
        $ip = AppointmentService::checkIn($appt->id, 4);
        $this->assertSame('IN_PROGRESS', $ip->status);
    }

    public function testCheckOutCompletes()
    {
        $appt = $this->makeAppointment();
        AppointmentService::confirm($appt->id, 1);
        AppointmentService::checkIn($appt->id, 4);
        $this->attachEvidence($appt->id);
        $done = AppointmentService::checkOut($appt->id, 4);
        $this->assertSame('COMPLETED', $done->status);
    }

    public function testCheckOutWithoutEvidenceIsBlocked()
    {
        $appt = $this->makeAppointment();
        AppointmentService::confirm($appt->id, 1);
        AppointmentService::checkIn($appt->id, 4);
        $this->expectException(\think\exception\HttpException::class);
        AppointmentService::checkOut($appt->id, 4);
    }

    public function testFullLifecycleHistory()
    {
        $appt = $this->makeAppointment();
        AppointmentService::confirm($appt->id, 1);
        AppointmentService::checkIn($appt->id, 4);
        $this->attachEvidence($appt->id);
        AppointmentService::checkOut($appt->id, 4);
        $h = Db::name('appointment_history')->where('appointment_id', $appt->id)->order('id asc')->column('to_status');
        $this->assertSame(['PENDING', 'CONFIRMED', 'IN_PROGRESS', 'COMPLETED'], $h);
    }

    public function testCancelFromPending()
    {
        $appt = $this->makeAppointment();
        $c = AppointmentService::cancel($appt->id, 1, 'Customer requested');
        $this->assertSame('CANCELLED', $c->status);
    }

    public function testCancelFromConfirmed()
    {
        $appt = $this->makeAppointment();
        AppointmentService::confirm($appt->id, 1);
        $c = AppointmentService::cancel($appt->id, 1, 'Schedule conflict');
        $this->assertSame('CANCELLED', $c->status);
    }

    public function testCannotCancelFromInProgress()
    {
        $appt = $this->makeAppointment();
        AppointmentService::confirm($appt->id, 1);
        AppointmentService::checkIn($appt->id, 4);
        $this->expectException(\think\exception\ValidateException::class);
        AppointmentService::cancel($appt->id, 1);
    }

    public function testCannotConfirmCompleted()
    {
        $appt = $this->makeAppointment();
        AppointmentService::confirm($appt->id, 1);
        AppointmentService::checkIn($appt->id, 4);
        $this->attachEvidence($appt->id);
        AppointmentService::checkOut($appt->id, 4);
        $this->expectException(\think\exception\HttpException::class);
        AppointmentService::confirm($appt->id, 1);
    }

    public function testAdminRepair()
    {
        $appt = $this->makeAppointment();
        AppointmentService::cancel($appt->id, 1, 'Mistake');
        $repaired = AppointmentService::repair($appt->id, 'CONFIRMED', 1, 'Fix cancellation');
        $this->assertSame('CONFIRMED', $repaired->status);
        $h = Db::name('appointment_history')->where('appointment_id', $appt->id)->where('reason', 'like', '%REPAIR%')->find();
        $this->assertNotNull($h);
    }

    public function testRescheduleSuccess()
    {
        $appt = $this->makeAppointment(['dateTime' => date('m/d/Y', strtotime('+7 days')) . ' 02:00 PM']);
        $r = AppointmentService::reschedule($appt->id, date('m/d/Y', strtotime('+8 days')) . ' 10:00 AM', 1);
        $this->assertSame($appt->id, $r->id);
    }

    public function testRescheduleBlockedWithin2Hours()
    {
        $appt = $this->makeAppointment(['dateTime' => date('m/d/Y h:i A', strtotime('+30 minutes'))]);
        $this->expectException(\think\exception\HttpException::class);
        AppointmentService::reschedule($appt->id, '05/01/2026 10:00 AM', 3, false);
    }

    public function testAdminOverrideReschedule()
    {
        $appt = $this->makeAppointment(['dateTime' => date('m/d/Y h:i A', strtotime('+30 minutes'))]);
        $r = AppointmentService::reschedule($appt->id, '05/01/2026 10:00 AM', 1, true, 'Emergency');
        $this->assertSame($appt->id, $r->id);
    }

    public function testAdminOverrideRequiresReason()
    {
        $appt = $this->makeAppointment(['dateTime' => date('m/d/Y h:i A', strtotime('+30 minutes'))]);
        $this->expectException(\think\exception\ValidateException::class);
        AppointmentService::reschedule($appt->id, '05/01/2026 10:00 AM', 1, true, null);
    }

    public function testWrongProviderCannotCheckIn()
    {
        $appt = $this->makeAppointment(['providerId' => 4]);
        AppointmentService::confirm($appt->id, 1);
        $this->expectException(\think\exception\ValidateException::class);
        AppointmentService::checkIn($appt->id, 999);
    }

    public function testExpirePending()
    {
        $appt = $this->makeAppointment();
        Db::name('appointments')->where('id', $appt->id)->update([
            'created_at' => date('Y-m-d H:i:s', strtotime('-25 hours')),
            'updated_at' => date('Y-m-d H:i:s', strtotime('-25 hours')),
        ]);
        $count = AppointmentService::expirePending();
        $this->assertGreaterThanOrEqual(1, $count);
        $expired = Appointment::find($appt->id);
        $this->assertSame('EXPIRED', $expired->status);
    }

    public function testParseDateTimeFormats()
    {
        $this->assertSame('2026-04-17 14:30:00', AppointmentService::parseDateTime('04/17/2026 02:30 PM'));
        $this->assertSame('2026-04-17 10:30:00', AppointmentService::parseDateTime('04/17/2026 10:30 AM'));
        $this->assertSame('2026-04-17 00:00:00', AppointmentService::parseDateTime('04/17/2026 12:00 AM'));
    }

    public function testInvalidDateTimeThrows()
    {
        $this->expectException(\think\exception\ValidateException::class);
        AppointmentService::parseDateTime('not-a-date');
    }

    public function testTransitionsCreateAuditLogs()
    {
        $before = Db::name('audit_logs')->where('entity_type', 'appointment')->count();
        $appt = $this->makeAppointment();
        AppointmentService::confirm($appt->id, 1);
        $after = Db::name('audit_logs')->where('entity_type', 'appointment')->count();
        $this->assertGreaterThanOrEqual($before + 2, $after);
    }

    public function testRepairWithInvalidState()
    {
        $appt = $this->makeAppointment();
        $this->expectException(\think\exception\ValidateException::class);
        AppointmentService::repair($appt->id, 'NONEXISTENT', 1, 'Bad state');
    }

    public function testCancelWithReason()
    {
        $appt = $this->makeAppointment();
        $c = AppointmentService::cancel($appt->id, 1, 'Custom reason text');
        $this->assertSame('CANCELLED', $c->status);
        $h = Db::name('appointment_history')->where('appointment_id', $appt->id)->where('to_status', 'CANCELLED')->find();
        $this->assertSame('Custom reason text', $h['reason']);
    }

    public function testRescheduleMetadataLogged()
    {
        $appt = $this->makeAppointment(['dateTime' => date('m/d/Y', strtotime('+7 days')) . ' 02:00 PM']);
        AppointmentService::reschedule($appt->id, date('m/d/Y', strtotime('+9 days')) . ' 03:00 PM', 1);
        $h = Db::name('appointment_history')->where('appointment_id', $appt->id)->where('reason', 'like', 'Rescheduled%')->find();
        $this->assertNotNull($h);
        $meta = json_decode($h['metadata'], true);
        $this->assertArrayHasKey('old_date_time', $meta);
        $this->assertArrayHasKey('new_date_time', $meta);
    }

    public function testAdminRepairHistory()
    {
        $appt = $this->makeAppointment();
        AppointmentService::confirm($appt->id, 1);
        AppointmentService::repair($appt->id, 'PENDING', 1, 'Revert to pending');
        $h = Db::name('appointment_history')->where('appointment_id', $appt->id)->where('reason', 'like', '%REPAIR%')->find();
        $this->assertNotNull($h);
        $meta = json_decode($h['metadata'], true);
        $this->assertTrue($meta['repair']);
    }

    public function testCreateWithNotes()
    {
        $appt = AppointmentService::create([
            'customerId' => 3, 'providerId' => 4,
            'dateTime' => '04/28/2026 09:00 AM', 'location' => 'Notes Test',
            'notes' => 'Special instructions here',
        ], 1);
        $this->assertSame('Special instructions here', $appt->notes);
    }

    public function testModelRelationships()
    {
        $appt = $this->makeAppointment();
        $this->assertNotNull($appt->history());
        $this->assertNotNull($appt->attachments());
        $this->assertNotNull($appt->provider());
        $this->assertNotNull($appt->customer());
    }

    public function testModelCanTransitionAllPaths()
    {
        $appt = new Appointment();
        $appt->status = 'PENDING';
        $this->assertTrue($appt->canTransitionTo('CONFIRMED'));
        $this->assertFalse($appt->canTransitionTo('COMPLETED'));

        $appt->status = 'CONFIRMED';
        $this->assertTrue($appt->canTransitionTo('IN_PROGRESS'));

        $appt->status = 'IN_PROGRESS';
        $this->assertTrue($appt->canTransitionTo('COMPLETED'));
        $this->assertFalse($appt->canTransitionTo('CANCELLED'));
    }

    public function testUserModelMethods()
    {
        $user = \app\model\User::find(1);
        $this->assertTrue($user->isActive());
        $this->assertFalse($user->isLocked());
        $arr = $user->toSessionArray();
        $this->assertArrayHasKey('id', $arr);
        $this->assertArrayHasKey('role', $arr);
        $this->assertTrue($user->verifyPassword('Admin12345!'));
        $this->assertFalse($user->verifyPassword('Wrong'));
    }

    public function testMpsPlanRelation()
    {
        $pid = Db::name('products')->insertGetId([
            'name' => 'Rel Test ' . uniqid(), 'category' => 'CPU', 'specs' => '{}',
            'status' => 'APPROVED', 'created_by' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $mps = \app\service\ProductionService::createMps([
            'productId' => $pid, 'workCenterId' => 1, 'weekStart' => '07/01/2026', 'quantity' => 100,
        ], 1);
        $this->assertNotNull($mps->workOrders());
    }

    public function testProductModelRelation()
    {
        $p = \app\service\CatalogService::create(['name' => 'Rel ' . uniqid(), 'category' => 'CPU', 'specs' => []], 1);
        $this->assertNotNull($p->scores());
    }
}
