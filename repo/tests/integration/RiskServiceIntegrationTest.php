<?php
declare(strict_types=1);

namespace tests\integration;

use tests\IntegrationTestCase;
use app\service\RiskService;
use think\facade\Db;

class RiskServiceIntegrationTest extends IntegrationTestCase
{
    public function testCalculateScores()
    {
        $count = RiskService::calculateScores();
        $this->assertGreaterThanOrEqual(0, $count);
        if ($count > 0) {
            $s = Db::name('risk_scores')->order('calculated_at desc')->find();
            $this->assertNotNull($s);
            $this->assertGreaterThanOrEqual(0, (float) $s['score']);
            $this->assertLessThanOrEqual(100, (float) $s['score']);
        }
    }

    public function testDetectAnomalies()
    {
        $count = RiskService::detectAnomalies();
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testDuplicateDeviceDetection()
    {
        $now = date('Y-m-d H:i:s');
        $hash = hash('sha256', 'shared-' . uniqid());
        foreach ([3, 4] as $uid) {
            Db::name('device_fingerprints')->insert([
                'user_id' => $uid, 'fingerprint_hash' => $hash, 'user_agent' => 'Agent',
                'ip_address' => '1.2.3.4', 'first_seen_at' => $now, 'last_seen_at' => $now,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        RiskService::detectAnomalies();
        $flags = Db::name('anomaly_flags')->where('flag_type', 'DUPLICATE_DEVICE')->where('status', 'OPEN')->count();
        $this->assertGreaterThanOrEqual(1, $flags);
    }

    public function testNoDuplicateOpenFlags()
    {
        Db::name('anomaly_flags')->where('user_id', 3)->where('flag_type', 'EXCESSIVE_POSTING')->delete();
        Db::name('anomaly_flags')->insert([
            'user_id' => 3, 'flag_type' => 'EXCESSIVE_POSTING', 'details' => '{}',
            'status' => 'OPEN', 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $before = Db::name('anomaly_flags')->where('user_id', 3)->where('flag_type', 'EXCESSIVE_POSTING')->where('status', 'OPEN')->count();
        RiskService::detectAnomalies();
        $after = Db::name('anomaly_flags')->where('user_id', 3)->where('flag_type', 'EXCESSIVE_POSTING')->where('status', 'OPEN')->count();
        $this->assertSame($before, $after);
    }
}
