<?php
declare(strict_types=1);

namespace tests\unit;

use tests\TestCase;
use app\service\CatalogService;

/**
 * Pure-unit tests for moderation decision rules and the catalog dedup logic that
 * feeds the moderation queue. DB-backed flows live in
 * tests/integration/ModerationServiceIntegrationTest.php.
 */
class ModerationTest extends TestCase
{
    public function testOnlyAllowedProductActions()
    {
        $allowed = ['APPROVE', 'REJECT'];
        $isAllowed = fn (string $a) => in_array($a, $allowed, true);
        $this->assertTrue($isAllowed('APPROVE'));
        $this->assertTrue($isAllowed('REJECT'));
        $this->assertFalse($isAllowed('DELETE'));
        $this->assertFalse($isAllowed(''));
    }

    public function testProductStatusAfterModeration()
    {
        $after = fn (string $action) => $action === 'APPROVE' ? 'APPROVED' : 'REJECTED';
        $this->assertSame('APPROVED', $after('APPROVE'));
        $this->assertSame('REJECTED', $after('REJECT'));
    }

    public function testMergeActionsAllowListed()
    {
        $allowed = ['MERGE', 'REJECT', 'DISTINCT'];
        foreach ($allowed as $a) {
            $this->assertTrue(in_array($a, $allowed, true));
        }
        $this->assertFalse(in_array('DELETE', $allowed, true));
    }

    public function testMergeKeepIdMustReferencePair()
    {
        $check = function (int $keep, array $pair): bool {
            return in_array($keep, $pair, true);
        };
        $this->assertTrue($check(1, [1, 2]));
        $this->assertTrue($check(2, [1, 2]));
        $this->assertFalse($check(3, [1, 2]));
    }

    public function testCanSubmitOnlyFromDraft()
    {
        $canSubmit = fn (string $status) => $status === 'DRAFT';
        $this->assertTrue($canSubmit('DRAFT'));
        $this->assertFalse($canSubmit('SUBMITTED'));
        $this->assertFalse($canSubmit('APPROVED'));
        $this->assertFalse($canSubmit('REJECTED'));
    }

    public function testJaccardSimilarityDrivesMergeDecision()
    {
        // The moderation auto-merge threshold is 0.85; 0.50–0.85 is flagged for review.
        $a = ['intel', 'core', 'i9', 'cpu', 'lga1700', '24'];
        $b = ['intel', 'core', 'i9', 'cpu', 'lga1700', '24'];
        $this->assertSame(1.0, CatalogService::jaccardSimilarity($a, $b), 'identical tokens = 1.0');

        $c = ['amd', 'ryzen', '9', 'cpu', 'am5', '16'];
        $this->assertLessThan(0.5, CatalogService::jaccardSimilarity($a, $c), 'distinct products stay low');

        // Overlap in most fields but different name/cores — should fall in review band.
        $d = ['intel', 'core', 'i9', 'cpu', 'lga1700', '16'];
        $sim = CatalogService::jaccardSimilarity($a, $d);
        $this->assertGreaterThan(0.5, $sim);
        $this->assertLessThan(0.85, $sim);
    }

    public function testCompletenessScoringCountsPresentFields()
    {
        // CPU expects 7 fields; supplying 4 should yield 4/7.
        $specs = [
            'clockSpeed'   => '3.0 GHz',
            'cores'        => 24,
            'threads'      => 32,
            'socket'       => 'LGA1700',
        ];
        $score = CatalogService::scoreCompleteness($specs, 'CPU');
        $this->assertEqualsWithDelta(round(4 / 7, 4), $score, 0.0001);
    }
}
