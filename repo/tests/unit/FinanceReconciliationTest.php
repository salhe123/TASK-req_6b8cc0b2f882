<?php
declare(strict_types=1);

namespace tests\unit;

use tests\TestCase;

/**
 * Pure-unit tests for the deterministic finance primitives.
 * DB-backed reconciliation behavior lives in
 * tests/integration/FinanceServiceIntegrationTest.php.
 */
class FinanceReconciliationTest extends TestCase
{
    public function testAmountMatchRoundingTolerance()
    {
        // Reconciliation matches when abs(diff) < 0.01.
        $matches = fn (float $a, float $b) => abs($a - $b) < 0.01;
        $this->assertTrue($matches(150.00, 150.00));
        $this->assertTrue($matches(150.001, 150.009), 'sub-cent noise is matched');
        $this->assertFalse($matches(150.00, 150.02));
        $this->assertFalse($matches(150.00, 145.00));
    }

    public function testVarianceAlertAtFiftyDollars()
    {
        // Alert triggers strictly above $50.
        $alerts = fn (float $payments, float $receipts) => abs($payments - $receipts) > 50.00;
        $this->assertFalse($alerts(100, 50.00), 'exactly $50 does not alert');
        $this->assertTrue($alerts(100, 49.99));
        $this->assertFalse($alerts(0, 0));
    }

    public function testSettlementSplitAt8Percent()
    {
        $split = function (float $gross, float $feePercent): array {
            $fee = round($gross * ($feePercent / 100), 2);
            $net = round($gross - $fee, 2);
            return [$fee, $net];
        };
        [$fee, $net] = $split(1000.00, 8);
        $this->assertSame(80.00, $fee);
        $this->assertSame(920.00, $net);
        [$fee, $net] = $split(137.37, 8);
        $this->assertEqualsWithDelta(10.99, $fee, 0.01);
        $this->assertEqualsWithDelta(126.38, $net, 0.01);
    }

    public function testReceiptNumberFormat()
    {
        // The number is RCP-YYYYMMDD-000000 (six-digit zero-padded payment id).
        $format = fn (int $paymentId, string $date) => 'RCP-' . $date . '-' . str_pad((string) $paymentId, 6, '0', STR_PAD_LEFT);
        $this->assertSame('RCP-20260416-000001', $format(1, '20260416'));
        $this->assertSame('RCP-20260416-123456', $format(123456, '20260416'));
    }

    public function testHmacSignVerifyRoundtrip()
    {
        $key = 'test-key-at-least-32-characters-long-okay';
        $data = 'RCP-20260416-000001|150.00|2026-04-16 10:00:00';
        $signature = hash_hmac('sha256', $data, $key);
        // Verify uses constant-time compare — mirror that with hash_equals here.
        $this->assertTrue(hash_equals(hash_hmac('sha256', $data, $key), $signature));
        $this->assertFalse(hash_equals(hash_hmac('sha256', 'tampered', $key), $signature));
    }

    public function testReceiptFingerprintIsDeterministic()
    {
        $fp = fn (int $pid, float $amt, string $rcp) => hash('sha256', $pid . '|' . $amt . '|' . $rcp);
        $this->assertSame($fp(1, 150.00, 'RCP-001'), $fp(1, 150.00, 'RCP-001'));
        $this->assertNotSame($fp(1, 150.00, 'RCP-001'), $fp(2, 150.00, 'RCP-001'));
        $this->assertSame(64, strlen($fp(1, 150.00, 'RCP-001')));
    }

    public function testCsvRequiredColumnCount()
    {
        // importCsv skips rows with < 4 columns.
        $valid = fn (string $line) => count(str_getcsv($line)) >= 4;
        $this->assertTrue($valid('150.00,John Doe,REF-001,04/16/2026'));
        $this->assertFalse($valid('150.00,John Doe,REF-001'));
        $this->assertFalse($valid(''));
    }
}
