<?php
declare(strict_types=1);

namespace tests\integration;

use tests\IntegrationTestCase;
use app\service\CatalogService;
use app\model\Product;
use think\facade\Db;

class CatalogServiceIntegrationTest extends IntegrationTestCase
{
    public function testCreateProduct()
    {
        $p = CatalogService::create(['name' => 'Test CPU ' . uniqid(), 'category' => 'CPU', 'specs' => ['clockSpeed' => '3.0 GHz']], 1);
        $this->assertSame('DRAFT', $p->status);
        $this->assertSame('CPU', $p->category);
    }

    public function testInvalidCategory()
    {
        $this->expectException(\think\exception\ValidateException::class);
        CatalogService::create(['name' => 'Bad', 'category' => 'INVALID', 'specs' => []], 1);
    }

    public function testSubmitScoresAndFingerprints()
    {
        $p = CatalogService::create([
            'name' => 'Submit Test ' . uniqid(), 'category' => 'CPU',
            'specs' => ['clockSpeed' => '4500 MHz', 'cores' => 16, 'threads' => 32, 'socket' => 'AM5', 'tdp' => '170 W', 'cache' => '64 MB', 'architecture' => 'Zen 4'],
        ], 1);
        $result = CatalogService::submit($p->id, 1);
        $this->assertSame('SUBMITTED', $result['status']);
        $this->assertEquals(1.0, $result['completenessScore']);
        $this->assertGreaterThan(0, $result['consistencyScore']);
        $updated = Product::find($p->id);
        $this->assertNotNull($updated->fingerprint);
    }

    public function testSubmitPartialSpecs()
    {
        $p = CatalogService::create(['name' => 'Partial ' . uniqid(), 'category' => 'CPU', 'specs' => ['clockSpeed' => '3.0 GHz']], 1);
        $r = CatalogService::submit($p->id, 1);
        $this->assertLessThan(1.0, $r['completenessScore']);
    }

    public function testCannotSubmitNonDraft()
    {
        $p = CatalogService::create(['name' => 'Double ' . uniqid(), 'category' => 'GPU', 'specs' => ['clockSpeed' => '2.0 GHz']], 1);
        CatalogService::submit($p->id, 1);
        $this->expectException(\think\exception\ValidateException::class);
        CatalogService::submit($p->id, 1);
    }

    public function testStandardizeFrequency() { $this->assertSame('3.5 GHz', CatalogService::standardizeSpecs(['clockSpeed' => '3500 MHz'], 'CPU')['clockSpeed']); }
    public function testStandardizeMemory() { $this->assertSame('8 GB', CatalogService::standardizeSpecs(['memory' => '8192 MB'], 'GPU')['memory']); }
    public function testStandardizeTdp() { $this->assertSame('125W', CatalogService::standardizeSpecs(['tdp' => '125 W'], 'CPU')['tdp']); }
    public function testStandardizePcie() { $this->assertSame('PCIe 4.0 x16', CatalogService::standardizeSpecs(['interface' => 'PCIE 4.0 x16'], 'GPU')['interface']); }

    public function testCompletenessScoring()
    {
        $full = CatalogService::scoreCompleteness(['clockSpeed' => '3.0 GHz', 'cores' => 8, 'threads' => 16, 'socket' => 'AM5', 'tdp' => '170W', 'cache' => '32 MB', 'architecture' => 'Zen'], 'CPU');
        $this->assertEquals(1.0, $full);
        $partial = CatalogService::scoreCompleteness(['clockSpeed' => '3.0 GHz'], 'CPU');
        $this->assertLessThan(1.0, $partial);
    }

    public function testConsistencyScoring()
    {
        $valid = CatalogService::scoreConsistency(['clockSpeed' => '3.0 GHz', 'cores' => 8, 'tdp' => '125W'], 'CPU');
        $this->assertEquals(1.0, $valid);
        $bad = CatalogService::scoreConsistency(['clockSpeed' => '99.0 GHz', 'cores' => 8], 'CPU');
        $this->assertLessThan(1.0, $bad);
    }

    public function testJaccardSimilarity()
    {
        $this->assertEquals(1.0, CatalogService::jaccardSimilarity(['a', 'b'], ['a', 'b']));
        $this->assertEquals(0.0, CatalogService::jaccardSimilarity(['a'], ['b']));
        $this->assertEquals(0.0, CatalogService::jaccardSimilarity([], []));
    }

    public function testFingerprintDeterministic()
    {
        $s = ['clockSpeed' => '3.0 GHz', 'cores' => 8];
        $this->assertSame(CatalogService::generateFingerprint('X', 'CPU', $s), CatalogService::generateFingerprint('X', 'CPU', $s));
    }

    public function testFindDuplicates()
    {
        $this->assertIsArray(CatalogService::findDuplicates());
    }

    public function testProductScorePersisted()
    {
        $p = CatalogService::create(['name' => 'ScorePersist ' . uniqid(), 'category' => 'CPU', 'specs' => ['clockSpeed' => '3.0 GHz', 'cores' => 8]], 1);
        CatalogService::submit($p->id, 1);
        $score = Db::name('product_scores')->where('product_id', $p->id)->find();
        $this->assertNotNull($score);
    }

    public function testStandardizeMemoryTb() { $this->assertSame('1024 GB', CatalogService::standardizeSpecs(['memory' => '1 TB'], 'GPU')['memory']); }
    public function testStandardizeCache() { $this->assertSame('30 MB', CatalogService::standardizeSpecs(['cache' => '30 MB'], 'CPU')['cache']); }
    public function testStandardizeNonMatchingValue() { $this->assertSame('unknown', CatalogService::standardizeSpecs(['clockSpeed' => 'unknown'], 'CPU')['clockSpeed']); }

    public function testCompletenessEmpty() { $this->assertEquals(0.0, CatalogService::scoreCompleteness([], 'CPU')); }
    public function testConsistencyNoChecks() { $this->assertEquals(1.0, CatalogService::scoreConsistency([], 'CPU')); }
    public function testConsistencyBadCores()
    {
        $s = CatalogService::scoreConsistency(['cores' => 999], 'CPU');
        $this->assertLessThan(1.0, $s);
    }
    public function testConsistencyMemory()
    {
        $s = CatalogService::scoreConsistency(['memory' => '16 GB'], 'GPU');
        $this->assertEquals(1.0, $s);
    }

    public function testSubmitTriggersDedup()
    {
        // Create two similar products
        $p1 = CatalogService::create(['name' => 'Dedup Test CPU Model X', 'category' => 'CPU', 'specs' => ['clockSpeed' => '3.0 GHz', 'cores' => 8, 'socket' => 'AM5']], 1);
        CatalogService::submit($p1->id, 1);

        $p2 = CatalogService::create(['name' => 'Dedup Test CPU Model X', 'category' => 'CPU', 'specs' => ['clockSpeed' => '3.0 GHz', 'cores' => 8, 'socket' => 'AM5']], 1);
        CatalogService::submit($p2->id, 1);

        // Should have created moderation decisions for potential duplicate
        $decisions = Db::name('moderation_decisions')->where('item_id', $p2->id)->where('item_type', 'MERGE')->count();
        $this->assertGreaterThanOrEqual(1, $decisions);
    }

    public function testGpuCompleteness()
    {
        $specs = ['clockSpeed' => '2.0 GHz', 'memory' => '8 GB', 'memoryType' => 'GDDR6', 'busWidth' => '256-bit', 'tdp' => '300W', 'interface' => 'PCIe 4.0', 'outputs' => 'HDMI,DP'];
        $s = CatalogService::scoreCompleteness($specs, 'GPU');
        $this->assertEquals(1.0, $s);
    }

    public function testMotherboardCompleteness()
    {
        $specs = ['socket' => 'AM5', 'chipset' => 'X670E', 'formFactor' => 'ATX', 'memorySlots' => 4, 'maxMemory' => '128 GB', 'pcieSlots' => 3, 'storageInterfaces' => 'M.2,SATA'];
        $s = CatalogService::scoreCompleteness($specs, 'MOTHERBOARD');
        $this->assertEquals(1.0, $s);
    }
}
