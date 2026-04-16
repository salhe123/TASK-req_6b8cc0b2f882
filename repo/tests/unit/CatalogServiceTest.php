<?php
declare(strict_types=1);

namespace tests\unit;

use tests\TestCase;
use app\service\CatalogService;

class CatalogServiceTest extends TestCase
{
    /** @test Frequency normalization from MHz */
    public function testNormalizeFrequencyMhz()
    {
        $specs = CatalogService::standardizeSpecs(['clockSpeed' => '3500 MHz'], 'CPU');
        $this->assertSame('3.5 GHz', $specs['clockSpeed']);
    }

    /** @test Frequency normalization from GHz */
    public function testNormalizeFrequencyGhz()
    {
        $specs = CatalogService::standardizeSpecs(['clockSpeed' => '3.0 GHz'], 'CPU');
        $this->assertSame('3 GHz', $specs['clockSpeed']);
    }

    /** @test Memory normalization from MB to GB */
    public function testNormalizeMemoryMbToGb()
    {
        $specs = CatalogService::standardizeSpecs(['memory' => '2048 MB'], 'GPU');
        $this->assertSame('2 GB', $specs['memory']);
    }

    /** @test Memory normalization keeps GB */
    public function testNormalizeMemoryGb()
    {
        $specs = CatalogService::standardizeSpecs(['memory' => '16 GB'], 'GPU');
        $this->assertSame('16 GB', $specs['memory']);
    }

    /** @test Memory normalization from TB to GB */
    public function testNormalizeMemoryTbToGb()
    {
        $specs = CatalogService::standardizeSpecs(['memory' => '1 TB'], 'GPU');
        $this->assertSame('1024 GB', $specs['memory']);
    }

    /** @test TDP normalization */
    public function testNormalizeTdp()
    {
        $specs = CatalogService::standardizeSpecs(['tdp' => '125 W'], 'CPU');
        $this->assertSame('125W', $specs['tdp']);
    }

    /** @test PCIe normalization */
    public function testNormalizePcie()
    {
        $specs = CatalogService::standardizeSpecs(['interface' => 'PCIE 4.0 x16'], 'GPU');
        $this->assertSame('PCIe 4.0 x16', $specs['interface']);
    }

    /** @test Completeness scoring with all fields */
    public function testCompletenessFullCpu()
    {
        $specs = [
            'clockSpeed' => '3.0 GHz',
            'cores'      => 8,
            'threads'    => 16,
            'socket'     => 'LGA 1700',
            'tdp'        => '125W',
            'cache'      => '30 MB',
            'architecture' => 'Raptor Lake',
        ];
        $score = CatalogService::scoreCompleteness($specs, 'CPU');
        $this->assertSame(1.0, $score);
    }

    /** @test Completeness scoring with partial fields */
    public function testCompletenessPartialCpu()
    {
        $specs = [
            'clockSpeed' => '3.0 GHz',
            'cores'      => 8,
        ];
        $score = CatalogService::scoreCompleteness($specs, 'CPU');
        $this->assertEqualsWithDelta(2 / 7, $score, 0.001);
    }

    /** @test Completeness scoring with no fields */
    public function testCompletenessEmpty()
    {
        $score = CatalogService::scoreCompleteness([], 'CPU');
        $this->assertSame(0.0, $score);
    }

    /** @test Consistency scoring with valid ranges */
    public function testConsistencyValid()
    {
        $specs = [
            'clockSpeed' => '3.0 GHz',
            'cores'      => 8,
            'tdp'        => '125W',
        ];
        $score = CatalogService::scoreConsistency($specs, 'CPU');
        $this->assertSame(1.0, $score);
    }

    /** @test Consistency scoring with out-of-range clock */
    public function testConsistencyBadClock()
    {
        $specs = [
            'clockSpeed' => '99.0 GHz', // unreasonable
            'cores'      => 8,
        ];
        $score = CatalogService::scoreConsistency($specs, 'CPU');
        $this->assertSame(0.5, $score); // 1 of 2 checks pass
    }

    /** @test Jaccard similarity identical sets */
    public function testJaccardIdentical()
    {
        $this->assertSame(1.0, CatalogService::jaccardSimilarity(['a', 'b', 'c'], ['a', 'b', 'c']));
    }

    /** @test Jaccard similarity disjoint sets */
    public function testJaccardDisjoint()
    {
        $this->assertSame(0.0, CatalogService::jaccardSimilarity(['a', 'b'], ['c', 'd']));
    }

    /** @test Jaccard similarity partial overlap */
    public function testJaccardPartialOverlap()
    {
        // intersection = {b}, union = {a, b, c, d} => 1/4 = 0.25
        $this->assertSame(0.25, CatalogService::jaccardSimilarity(['a', 'b'], ['b', 'c', 'd']));
    }

    /** @test Jaccard similarity empty sets */
    public function testJaccardEmpty()
    {
        $this->assertSame(0.0, CatalogService::jaccardSimilarity([], []));
    }

    /** @test Fingerprint generation is deterministic */
    public function testFingerprintDeterministic()
    {
        $specs = ['clockSpeed' => '3.0 GHz', 'cores' => 8];
        $fp1 = CatalogService::generateFingerprint('Intel Core i9-13900K', 'CPU', $specs);
        $fp2 = CatalogService::generateFingerprint('Intel Core i9-13900K', 'CPU', $specs);
        $this->assertSame($fp1, $fp2);
    }

    /** @test Different products produce different fingerprints */
    public function testFingerprintDiffers()
    {
        $specs1 = ['clockSpeed' => '3.0 GHz', 'cores' => 8];
        $specs2 = ['clockSpeed' => '2.5 GHz', 'cores' => 6];
        $fp1 = CatalogService::generateFingerprint('Intel Core i9-13900K', 'CPU', $specs1);
        $fp2 = CatalogService::generateFingerprint('AMD Ryzen 5 7600X', 'CPU', $specs2);
        $this->assertNotSame($fp1, $fp2);
    }
}
