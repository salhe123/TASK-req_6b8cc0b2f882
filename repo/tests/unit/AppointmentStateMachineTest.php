<?php
declare(strict_types=1);

namespace tests\unit;

use tests\TestCase;
use app\model\Appointment;

class AppointmentStateMachineTest extends TestCase
{
    private function makeAppointment(string $status): Appointment
    {
        $appointment = new Appointment();
        $appointment->status = $status;
        return $appointment;
    }

    /** @test */
    public function testPendingCanTransitionToConfirmed()
    {
        $appt = $this->makeAppointment('PENDING');
        $this->assertTrue($appt->canTransitionTo('CONFIRMED'));
    }

    /** @test */
    public function testPendingCanTransitionToCancelled()
    {
        $appt = $this->makeAppointment('PENDING');
        $this->assertTrue($appt->canTransitionTo('CANCELLED'));
    }

    /** @test */
    public function testPendingCanTransitionToExpired()
    {
        $appt = $this->makeAppointment('PENDING');
        $this->assertTrue($appt->canTransitionTo('EXPIRED'));
    }

    /** @test */
    public function testPendingCannotTransitionToCompleted()
    {
        $appt = $this->makeAppointment('PENDING');
        $this->assertFalse($appt->canTransitionTo('COMPLETED'));
    }

    /** @test */
    public function testPendingCannotTransitionToInProgress()
    {
        $appt = $this->makeAppointment('PENDING');
        $this->assertFalse($appt->canTransitionTo('IN_PROGRESS'));
    }

    /** @test */
    public function testConfirmedCanTransitionToInProgress()
    {
        $appt = $this->makeAppointment('CONFIRMED');
        $this->assertTrue($appt->canTransitionTo('IN_PROGRESS'));
    }

    /** @test */
    public function testConfirmedCanTransitionToCancelled()
    {
        $appt = $this->makeAppointment('CONFIRMED');
        $this->assertTrue($appt->canTransitionTo('CANCELLED'));
    }

    /** @test */
    public function testConfirmedCannotTransitionToCompleted()
    {
        $appt = $this->makeAppointment('CONFIRMED');
        $this->assertFalse($appt->canTransitionTo('COMPLETED'));
    }

    /** @test */
    public function testInProgressCanTransitionToCompleted()
    {
        $appt = $this->makeAppointment('IN_PROGRESS');
        $this->assertTrue($appt->canTransitionTo('COMPLETED'));
    }

    /** @test */
    public function testInProgressCannotTransitionToCancelled()
    {
        $appt = $this->makeAppointment('IN_PROGRESS');
        $this->assertFalse($appt->canTransitionTo('CANCELLED'));
    }

    /** @test */
    public function testCompletedCannotTransitionAnywhere()
    {
        $appt = $this->makeAppointment('COMPLETED');
        $this->assertFalse($appt->canTransitionTo('PENDING'));
        $this->assertFalse($appt->canTransitionTo('CONFIRMED'));
        $this->assertFalse($appt->canTransitionTo('IN_PROGRESS'));
        $this->assertFalse($appt->canTransitionTo('CANCELLED'));
        $this->assertFalse($appt->canTransitionTo('EXPIRED'));
    }

    /** @test */
    public function testExpiredCannotTransitionAnywhere()
    {
        $appt = $this->makeAppointment('EXPIRED');
        $this->assertFalse($appt->canTransitionTo('PENDING'));
        $this->assertFalse($appt->canTransitionTo('CONFIRMED'));
    }

    /** @test */
    public function testCancelledCannotTransitionAnywhere()
    {
        $appt = $this->makeAppointment('CANCELLED');
        $this->assertFalse($appt->canTransitionTo('PENDING'));
        $this->assertFalse($appt->canTransitionTo('CONFIRMED'));
    }

    /** @test */
    public function testAllValidTransitionsMap()
    {
        $expected = [
            'PENDING'     => ['CONFIRMED', 'CANCELLED', 'EXPIRED'],
            'CONFIRMED'   => ['IN_PROGRESS', 'CANCELLED'],
            'IN_PROGRESS' => ['COMPLETED'],
            'COMPLETED'   => [],
            'EXPIRED'     => [],
            'CANCELLED'   => [],
        ];
        $this->assertSame($expected, Appointment::TRANSITIONS);
    }
}
