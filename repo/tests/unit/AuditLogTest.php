<?php
declare(strict_types=1);

namespace tests\unit;

use tests\TestCase;

class AuditLogTest extends TestCase
{
    public function testEntryStructure()
    {
        $entry = ['user_id' => 1, 'action' => 'LOGIN', 'entity_type' => 'user', 'created_at' => '2026-04-16'];
        $this->assertArrayHasKey('action', $entry);
        $this->assertArrayNotHasKey('updated_at', $entry);
    }

    public function testArchiveCutoff()
    {
        $cutoff = date('Y-m-d', strtotime('2026-04-16 -12 months'));
        $this->assertSame('2025-04-16', $cutoff);
        $this->assertTrue('2025-03-15' < $cutoff);
        $this->assertFalse('2025-05-20' < $cutoff);
    }

    public function testFinancialRetention()
    {
        $cutoff = date('Y-m-d', strtotime('2026-04-16 -7 years'));
        $this->assertSame('2019-04-16', $cutoff);
    }

    public function testBeforeAfterJson()
    {
        $before = json_encode(['status' => 'PENDING']);
        $after = json_encode(['status' => 'CONFIRMED']);
        $this->assertJson($before);
        $this->assertJson($after);
        $this->assertSame('PENDING', json_decode($before, true)['status']);
    }
}
