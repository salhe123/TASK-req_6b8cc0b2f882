<?php
declare(strict_types=1);

namespace tests\integration;

use tests\IntegrationTestCase;
use app\service\ProductionService;
use app\model\MpsPlan;
use app\model\WorkOrder;
use think\facade\Db;

class ProductionServiceIntegrationTest extends IntegrationTestCase
{
    private function ensureProduct(): int
    {
        $p = Db::name('products')->where('status', 'APPROVED')->find();
        if ($p) return (int) $p['id'];
        return Db::name('products')->insertGetId([
            'name' => 'Prod Test ' . uniqid(), 'category' => 'CPU', 'specs' => '{}',
            'status' => 'APPROVED', 'created_by' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function testCreateMps()
    {
        $mps = ProductionService::createMps([
            'productId' => $this->ensureProduct(), 'workCenterId' => 1,
            'weekStart' => date('m/d/Y', strtotime('next monday')), 'quantity' => 200,
        ], 1);
        $this->assertSame('ACTIVE', $mps->status);
        $this->assertSame(200, (int) $mps->quantity);
    }

    public function testUpdateMps()
    {
        $mps = ProductionService::createMps(['productId' => $this->ensureProduct(), 'workCenterId' => 1, 'weekStart' => '05/04/2026', 'quantity' => 100], 1);
        $u = ProductionService::updateMps($mps->id, ['quantity' => 500]);
        $this->assertSame(500, (int) $u->quantity);
    }

    public function testDeleteMps()
    {
        $mps = ProductionService::createMps(['productId' => $this->ensureProduct(), 'workCenterId' => 1, 'weekStart' => '05/11/2026', 'quantity' => 50], 1);
        ProductionService::deleteMps($mps->id);
        $this->assertNull(MpsPlan::find($mps->id));
    }

    public function testDeleteMpsWithWorkOrdersFails()
    {
        $mps = ProductionService::createMps(['productId' => $this->ensureProduct(), 'workCenterId' => 1, 'weekStart' => '05/18/2026', 'quantity' => 100], 1);
        ProductionService::explode($mps->id);
        $this->expectException(\think\exception\ValidateException::class);
        ProductionService::deleteMps($mps->id);
    }

    public function testExplode()
    {
        $mps = ProductionService::createMps(['productId' => $this->ensureProduct(), 'workCenterId' => 1, 'weekStart' => '06/01/2026', 'quantity' => 350], 1);
        $count = ProductionService::explode($mps->id);
        $this->assertSame(4, $count); // 350 / 100 = 4
        $wos = WorkOrder::where('mps_id', $mps->id)->select();
        $this->assertCount(4, $wos);
    }

    public function testCompleteWorkOrder()
    {
        $mps = ProductionService::createMps(['productId' => $this->ensureProduct(), 'workCenterId' => 1, 'weekStart' => '06/08/2026', 'quantity' => 100], 1);
        ProductionService::explode($mps->id);
        $wo = WorkOrder::where('mps_id', $mps->id)->find();
        // Strict state machine: must go through IN_PROGRESS before COMPLETED.
        ProductionService::startWorkOrder($wo->id);
        $done = ProductionService::completeWorkOrder($wo->id, [
            'quantityCompleted' => 95, 'quantityRework' => 3, 'downtimeMinutes' => 15, 'reasonCode' => 'MATERIAL_DELAY',
        ]);
        $this->assertSame('COMPLETED', $done->status);
        $this->assertSame(95, (int) $done->quantity_completed);
        $this->assertSame('MATERIAL_DELAY', $done->reason_code);
    }

    public function testDirectPendingToCompletedRejected()
    {
        $mps = ProductionService::createMps(['productId' => $this->ensureProduct(), 'workCenterId' => 1, 'weekStart' => '06/22/2026', 'quantity' => 100], 1);
        ProductionService::explode($mps->id);
        $wo = WorkOrder::where('mps_id', $mps->id)->find();
        // Strict transition map blocks PENDING → COMPLETED.
        $this->expectException(\think\exception\HttpException::class);
        ProductionService::completeWorkOrder($wo->id, ['quantityCompleted' => 100]);
    }

    public function testInvalidReasonCode()
    {
        $mps = ProductionService::createMps(['productId' => $this->ensureProduct(), 'workCenterId' => 1, 'weekStart' => '06/15/2026', 'quantity' => 100], 1);
        ProductionService::explode($mps->id);
        $wo = WorkOrder::where('mps_id', $mps->id)->find();
        ProductionService::startWorkOrder($wo->id);
        $this->expectException(\think\exception\ValidateException::class);
        ProductionService::completeWorkOrder($wo->id, ['quantityCompleted' => 100, 'reasonCode' => 'INVALID']);
    }

    public function testCapacityLoading()
    {
        $result = ProductionService::getCapacityLoading();
        $this->assertIsArray($result);
        foreach ($result as $wc) {
            $this->assertArrayHasKey('workCenterId', $wc);
            $this->assertArrayHasKey('loadPercent', $wc);
            $this->assertArrayHasKey('warning', $wc);
            $this->assertIsBool($wc['warning']);
        }
    }

    public function testCapacityByWorkCenter()
    {
        $result = ProductionService::getCapacityLoading(1);
        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['workCenterId']);
    }
}
