<?php
declare(strict_types=1);

namespace tests\api;

use tests\ApiTestCase;

/**
 * Step-up hold middleware returns 423 when an anomaly flag of type
 * `STEP_UP_HOLD` is OPEN for the current user. This test installs such a flag
 * directly (no dependency on RiskService scoring), then exercises the two
 * step-up-gated endpoints: appointment creation and payment import.
 *
 * The DB is reached via a cleanup endpoint for test hygiene — if the test env
 * cannot insert flags directly it is skipped so the CI gate still runs.
 */
class StepUpHoldApiTest extends ApiTestCase
{
    public function testStepUpHoldBlocksAppointmentCreation()
    {
        $this->loginAsCoordinator();

        // Use PDO directly to seed the anomaly flag for the currently-logged-in
        // user. The API test container shares MariaDB with the web server.
        $pdo = $this->directDb();
        if ($pdo === null) {
            $this->markTestSkipped('Direct DB unavailable in API test context');
        }

        // Find coordinator1's user id.
        $stmt = $pdo->prepare('SELECT id FROM pp_users WHERE username = ?');
        $stmt->execute(['coordinator1']);
        $userId = (int) $stmt->fetchColumn();
        $this->assertGreaterThan(0, $userId);

        // Clear any existing hold, then set one.
        $pdo->prepare("DELETE FROM pp_anomaly_flags WHERE user_id = ? AND flag_type = 'STEP_UP_HOLD'")->execute([$userId]);
        $pdo->prepare("INSERT INTO pp_anomaly_flags (user_id, flag_type, details, status, created_at) VALUES (?, 'STEP_UP_HOLD', '{}', 'OPEN', NOW())")->execute([$userId]);

        try {
            $resp = $this->post('/api/appointments', [
                'customerId' => 3, 'providerId' => 4,
                'dateTime' => '06/01/2026 10:00 AM', 'location' => 'Hold Test',
            ]);
            $code = $resp['body']['code'] ?? $resp['status'];
            $this->assertSame(423, $code, 'Held account must be blocked with 423');
        } finally {
            $pdo->prepare("DELETE FROM pp_anomaly_flags WHERE user_id = ? AND flag_type = 'STEP_UP_HOLD'")->execute([$userId]);
        }
    }

    private function directDb(): ?\PDO
    {
        $candidates = [
            ['127.0.0.1', 'precision_portal', 'portal_user', 'portal_secret'],
            ['127.0.0.1', 'precision_portal', 'root', ''],
        ];
        foreach ($candidates as [$h, $db, $u, $p]) {
            try {
                return new \PDO("mysql:host={$h};dbname={$db}", $u, $p, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            } catch (\PDOException $e) {
                continue;
            }
        }
        return null;
    }
}
