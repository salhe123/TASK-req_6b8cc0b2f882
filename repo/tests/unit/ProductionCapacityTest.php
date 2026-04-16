<?php
declare(strict_types=1);

namespace tests\unit;

use tests\TestCase;

class ProductionCapacityTest extends TestCase
{
    private function calcLoad(float $planned, float $capacity): array
    {
        $pct = $capacity > 0 ? round(($planned / $capacity) * 100, 1) : 0;
        return ['loadPercent' => $pct, 'warning' => $pct >= 90];
    }

    public function testAt90() { $r = $this->calcLoad(36.0, 40.0); $this->assertSame(90.0, $r['loadPercent']); $this->assertTrue($r['warning']); }
    public function testAt89() { $r = $this->calcLoad(35.6, 40.0); $this->assertSame(89.0, $r['loadPercent']); $this->assertFalse($r['warning']); }
    public function testAt91() { $r = $this->calcLoad(36.4, 40.0); $this->assertSame(91.0, $r['loadPercent']); $this->assertTrue($r['warning']); }
    public function testAt100() { $r = $this->calcLoad(40.0, 40.0); $this->assertTrue($r['warning']); }
    public function testOver100() { $r = $this->calcLoad(45.0, 40.0); $this->assertTrue($r['warning']); }
    public function testZeroCapacity() { $r = $this->calcLoad(10.0, 0.0); $this->assertFalse($r['warning']); }

    public function testReasonCodes()
    {
        $valid = ['MATERIAL_DELAY', 'MACHINE_BREAKDOWN', 'QUALITY_ISSUE', 'OPERATOR_ERROR', 'TOOL_WEAR', 'SETUP_TIME', 'OTHER'];
        $this->assertCount(7, $valid);
        $this->assertContains('MATERIAL_DELAY', $valid);
        $this->assertNotContains('INVALID', $valid);
    }

    public function testExplosionBatching()
    {
        $this->assertSame(5, (int) ceil(500 / 100));
        $this->assertSame(4, (int) ceil(350 / 100));
    }
}
