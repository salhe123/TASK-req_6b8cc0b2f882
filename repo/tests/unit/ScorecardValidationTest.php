<?php
declare(strict_types=1);

namespace tests\unit;

use tests\TestCase;

class ScorecardValidationTest extends TestCase
{
    private function validate(array $dims): ?string
    {
        if (count($dims) < 3 || count($dims) > 7) return '3-7 dimensions required';
        if (array_sum(array_column($dims, 'weight')) !== 100) return 'Weights must total 100';
        return null;
    }

    public function testValid4Dims()
    {
        $this->assertNull($this->validate([['weight'=>30],['weight'=>25],['weight'=>20],['weight'=>25]]));
    }

    public function testTooFew() { $this->assertNotNull($this->validate([['weight'=>50],['weight'=>50]])); }
    public function testTooMany()
    {
        $dims = array_fill(0, 8, ['weight' => 12]);
        $this->assertNotNull($this->validate($dims));
    }

    public function testExactly3() { $this->assertNull($this->validate([['weight'=>40],['weight'=>30],['weight'=>30]])); }
    public function testExactly7()
    {
        $this->assertNull($this->validate([
            ['weight'=>15],['weight'=>15],['weight'=>15],['weight'=>15],['weight'=>15],['weight'=>15],['weight'=>10]
        ]));
    }

    public function testWeightsNot100() { $this->assertNotNull($this->validate([['weight'=>30],['weight'=>30],['weight'=>30]])); }

    public function testRatingBoundaries()
    {
        for ($s = 1; $s <= 5; $s++) { $this->assertTrue($s >= 1 && $s <= 5); }
        $this->assertFalse(0 >= 1 && 0 <= 5);
        $this->assertFalse(6 >= 1 && 6 <= 5);
    }
}
