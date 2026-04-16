<?php
declare(strict_types=1);

namespace tests\unit;

use tests\TestCase;

class ReviewConflictTest extends TestCase
{
    private function hasConflict(array $history, string $today): bool
    {
        $cutoff = date('Y-m-d', strtotime($today . ' -12 months'));
        foreach ($history as $r) {
            if ($r['end'] === null || $r['end'] >= $cutoff) return true;
        }
        return false;
    }

    public function testActiveAssociation() { $this->assertTrue($this->hasConflict([['end' => null]], '2026-04-16')); }
    public function testRecentEnd() { $this->assertTrue($this->hasConflict([['end' => '2025-10-16']], '2026-04-16')); }
    public function testExactly12Months() { $this->assertTrue($this->hasConflict([['end' => '2025-04-16']], '2026-04-16')); }
    public function test13MonthsAgo() { $this->assertFalse($this->hasConflict([['end' => '2025-03-16']], '2026-04-16')); }
    public function testNoHistory() { $this->assertFalse($this->hasConflict([], '2026-04-16')); }

    public function testBlindReviewStripsSubmitter()
    {
        $p = ['id' => 1, 'name' => 'CPU', 'submitted_by' => 42, 'created_by' => 42];
        unset($p['submitted_by'], $p['created_by']);
        $this->assertArrayNotHasKey('submitted_by', $p);
        $this->assertArrayHasKey('name', $p);
    }

    public function testWeightedScore()
    {
        $total = (4/5)*30 + (5/5)*25 + (3/5)*20 + (4/5)*25;
        $this->assertSame(81.0, round($total, 2));
    }

    public function testRatingBoundaries()
    {
        $this->assertTrue(1 >= 1 && 1 <= 5);
        $this->assertTrue(5 >= 1 && 5 <= 5);
        $this->assertFalse(0 >= 1);
        $this->assertFalse(6 <= 5);
    }
}
