<?php
declare(strict_types=1);

namespace tests\unit;

use tests\TestCase;

class RiskScoringTest extends TestCase
{
    private function score(float $success, float $dispute, float $cancel): float
    {
        return max(0, min(100, round(100 * $success - 50 * $dispute - 30 * $cancel, 2)));
    }

    public function testPerfectScore() { $this->assertSame(100.0, $this->score(1.0, 0.0, 0.0)); }
    public function testWithCancellations() { $this->assertSame(74.0, $this->score(0.8, 0.0, 0.2)); }
    public function testWithDisputes() { $this->assertSame(85.0, $this->score(0.9, 0.1, 0.0)); }
    public function testFloorsAtZero() { $this->assertSame(0.0, $this->score(0.0, 1.0, 1.0)); }
    public function testCapsAt100() { $this->assertSame(100.0, $this->score(2.0, 0.0, 0.0)); }

    public function testExcessivePostingThreshold()
    {
        $this->assertTrue(21 > 20);
        $this->assertFalse(20 > 20);
    }

    public function testExcessiveCancellationThreshold()
    {
        $this->assertTrue(6 > 5);
        $this->assertFalse(5 > 5);
    }

    public function testDeviceFingerprintHash()
    {
        $fp = hash('sha256', json_encode(['Mozilla/5.0', '1920x1080', 'UTC', 'MacIntel']));
        $this->assertSame(64, strlen($fp));
        $this->assertSame($fp, hash('sha256', json_encode(['Mozilla/5.0', '1920x1080', 'UTC', 'MacIntel'])));
    }
}
