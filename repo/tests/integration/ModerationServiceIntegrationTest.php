<?php
declare(strict_types=1);

namespace tests\integration;

use tests\IntegrationTestCase;
use app\service\ModerationService;
use app\service\CatalogService;
use app\model\Product;
use think\facade\Db;

class ModerationServiceIntegrationTest extends IntegrationTestCase
{
    private function createSubmitted(): int
    {
        $p = CatalogService::create(['name' => 'Mod ' . uniqid(), 'category' => 'CPU', 'specs' => ['clockSpeed' => '3.0 GHz']], 1);
        CatalogService::submit($p->id, 1);
        return $p->id;
    }

    public function testGetPending()
    {
        $this->createSubmitted();
        $r = ModerationService::getPending();
        $this->assertArrayHasKey('list', $r);
        $this->assertGreaterThanOrEqual(1, $r['total']);
    }

    public function testBulkApprove()
    {
        $id = $this->createSubmitted();
        $count = ModerationService::bulkAction([$id], 'APPROVE', 1);
        $this->assertSame(1, $count);
        $this->assertSame('APPROVED', Product::find($id)->status);
    }

    public function testBulkReject()
    {
        $id = $this->createSubmitted();
        ModerationService::bulkAction([$id], 'REJECT', 1);
        $this->assertSame('REJECTED', Product::find($id)->status);
    }

    public function testInvalidAction()
    {
        $this->expectException(\think\exception\ValidateException::class);
        ModerationService::bulkAction([1], 'DELETE', 1);
    }

    public function testMergeReview()
    {
        $a = $this->createSubmitted(); $b = $this->createSubmitted();
        ModerationService::bulkAction([$a, $b], 'APPROVE', 1);
        $r = ModerationService::mergeReview($a, $b, 'MERGE', $a, 1);
        $this->assertSame($a, $r['kept']);
        $this->assertSame($b, $r['removed']);
    }

    public function testMergeDistinct()
    {
        $a = $this->createSubmitted(); $b = $this->createSubmitted();
        $r = ModerationService::mergeReview($a, $b, 'DISTINCT', null, 1);
        $this->assertEmpty($r);
    }

    public function testGetPendingByProductType()
    {
        $this->createSubmitted();
        $r = ModerationService::getPending('PRODUCT');
        foreach ($r['list'] as $item) {
            $this->assertSame('PRODUCT', $item['type']);
            $this->assertArrayHasKey('scores', $item);
        }
    }

    public function testGetPendingMergeType()
    {
        $r = ModerationService::getPending('MERGE');
        $this->assertIsArray($r['list']);
    }

    public function testBulkActionSkipsNonSubmitted()
    {
        $p = CatalogService::create(['name' => 'Skip ' . uniqid(), 'category' => 'GPU', 'specs' => []], 1);
        $count = ModerationService::bulkAction([$p->id], 'APPROVE', 1);
        $this->assertSame(0, $count);
    }

    public function testMergeInvalidKeepId()
    {
        $a = $this->createSubmitted();
        $b = $this->createSubmitted();
        $this->expectException(\think\exception\ValidateException::class);
        ModerationService::mergeReview($a, $b, 'MERGE', 999, 1);
    }

    public function testMergeInvalidAction()
    {
        $a = $this->createSubmitted();
        $b = $this->createSubmitted();
        $this->expectException(\think\exception\ValidateException::class);
        ModerationService::mergeReview($a, $b, 'INVALID', null, 1);
    }

    public function testSnapshotsRecorded()
    {
        $id = $this->createSubmitted();
        ModerationService::bulkAction([$id], 'APPROVE', 1);
        $d = Db::name('moderation_decisions')->where('item_id', $id)->where('item_type', 'PRODUCT')->where('action', 'APPROVE')->order('id desc')->find();
        $this->assertNotNull($d, 'Moderation decision should exist');
        // Snapshots should be JSON strings
        $this->assertNotEmpty($d['before_snapshot']);
        $this->assertNotEmpty($d['after_snapshot']);
        $before = json_decode($d['before_snapshot'], true);
        $after = json_decode($d['after_snapshot'], true);
        $this->assertSame('SUBMITTED', $before['status']);
        $this->assertSame('APPROVED', $after['status']);
    }
}
