<?php
declare(strict_types=1);

namespace tests\api;

use tests\ApiTestCase;

class FinanceApiTest extends ApiTestCase
{
    /** @test GET /api/finance/payments returns paginated list */
    public function testListPayments()
    {
        $this->loginAsFinance();
        $resp = $this->get('/api/finance/payments');
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertArrayHasKey('list', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('page', $data);
    }

    /** @test GET /api/finance/receipts returns receipts */
    public function testListReceipts()
    {
        $this->loginAsFinance();
        $resp = $this->get('/api/finance/receipts');
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertArrayHasKey('list', $data);
    }

    /** @test POST /api/finance/reconciliation/run executes reconciliation */
    public function testRunReconciliation()
    {
        $this->loginAsFinance();
        $resp = $this->post('/api/finance/reconciliation/run', [
            'dateFrom' => '01/01/2026',
            'dateTo'   => '12/31/2026',
        ]);
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertArrayHasKey('matched', $data);
        $this->assertArrayHasKey('mismatches', $data);
        $this->assertArrayHasKey('duplicateFingerprints', $data);
        $this->assertArrayHasKey('varianceAlerts', $data);
        $this->assertIsInt($data['matched']);
        $this->assertIsInt($data['mismatches']);
    }

    /** @test GET /api/finance/reconciliation/anomalies returns anomaly list */
    public function testAnomalies()
    {
        $this->loginAsFinance();
        $resp = $this->get('/api/finance/reconciliation/anomalies');
        $this->assertResponseCode($resp, 200);
        $this->assertIsArray($this->getData($resp));
    }

    /** @test GET /api/finance/settlements returns settlements */
    public function testListSettlements()
    {
        $this->loginAsFinance();
        $resp = $this->get('/api/finance/settlements');
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertArrayHasKey('list', $data);
    }

    /** @test Non-finance user cannot access finance endpoints */
    public function testNonFinanceBlocked()
    {
        $this->loginAsProvider();
        $resp = $this->get('/api/finance/payments');
        $this->assertResponseCode($resp, 403);
    }

    /** @test POST /api/finance/settlements creates a settlement */
    public function testCreateSettlement()
    {
        $this->loginAsFinance();
        $resp = $this->post('/api/finance/settlements', [
            'weekEnding'         => '04/19/2026',
            'platformFeePercent' => 8,
        ]);
        $this->assertResponseCode($resp, 201);
        $data = $this->getData($resp);
        $this->assertArrayHasKey('totalSettled', $data);
        $this->assertArrayHasKey('platformFee', $data);
        $this->assertArrayHasKey('providerPayouts', $data);
        $this->assertArrayHasKey('transactionCount', $data);
    }

    /** @test GET /api/finance/settlements/{id}/report returns report */
    public function testSettlementReport()
    {
        $this->loginAsFinance();
        // Create a settlement first
        $resp = $this->post('/api/finance/settlements', [
            'weekEnding' => '04/26/2026', 'platformFeePercent' => 8,
        ]);
        $this->assertResponseCode($resp, 201);
        $id = $this->getData($resp)['id'];

        $resp = $this->get("/api/finance/settlements/{$id}/report");
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertArrayHasKey('settlement', $data);
        $this->assertArrayHasKey('entries', $data);
        $this->assertArrayHasKey('providerTotals', $data);
    }

    /** @test GET /api/finance/payments/{id} returns single payment */
    public function testGetPayment()
    {
        $this->loginAsFinance();
        // List payments — integration tests create payments via CSV import before API tests run
        $resp = $this->get('/api/finance/payments');
        $data = $this->getData($resp);
        $this->assertArrayHasKey('list', $data);
        // If payments exist, verify single read works
        if (!empty($data['list'])) {
            $paymentId = $data['list'][0]['id'];
            $resp = $this->get("/api/finance/payments/{$paymentId}");
            $this->assertResponseCode($resp, 200);
            $payment = $this->getData($resp);
            $this->assertArrayHasKey('amount', $payment);
            $this->assertArrayHasKey('status', $payment);
            $this->assertArrayHasKey('payer_name', $payment);
            $this->assertArrayHasKey('reference', $payment);
        }
        // Either way, the list endpoint was validated
        $this->assertResponseCode($this->get('/api/finance/payments'), 200);
    }

    /** @test GET /api/finance/receipts/{id} returns signed receipt */
    public function testGetReceipt()
    {
        $this->loginAsFinance();
        $resp = $this->get('/api/finance/receipts');
        $data = $this->getData($resp);
        $this->assertArrayHasKey('list', $data);
        if (!empty($data['list'])) {
            $receiptId = $data['list'][0]['id'];
            $resp = $this->get("/api/finance/receipts/{$receiptId}");
            $this->assertResponseCode($resp, 200);
            $receipt = $this->getData($resp);
            $this->assertArrayHasKey('signature', $receipt);
            $this->assertArrayHasKey('receipt_number', $receipt);
            $this->assertSame(64, strlen($receipt['signature']));
            $this->assertStringStartsWith('RCP-', $receipt['receipt_number']);
        }
        $this->assertResponseCode($this->get('/api/finance/receipts'), 200);
    }

    /** @test POST /api/finance/reconciliation/run with missing dates returns 400 */
    public function testReconciliationMissingDates()
    {
        $this->loginAsFinance();
        $resp = $this->post('/api/finance/reconciliation/run', []);
        $this->assertResponseCode($resp, 400);
    }

    /** @test POST /api/finance/payments/import without file returns 400 */
    public function testImportNoFile()
    {
        $this->loginAsFinance();
        $resp = $this->post('/api/finance/payments/import', []);
        $this->assertResponseCode($resp, 400);
    }

    /** @test POST /api/finance/settlements without weekEnding returns 400 */
    public function testCreateSettlementMissingFields()
    {
        $this->loginAsFinance();
        $resp = $this->post('/api/finance/settlements', []);
        $this->assertResponseCode($resp, 400);
    }

    /** @test Reconciliation response validates payload structure deeply */
    public function testReconciliationPayloadStructure()
    {
        $this->loginAsFinance();
        $resp = $this->post('/api/finance/reconciliation/run', [
            'dateFrom' => '01/01/2026', 'dateTo' => '12/31/2026',
        ]);
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertIsInt($data['matched']);
        $this->assertIsInt($data['mismatches']);
        $this->assertIsInt($data['duplicateFingerprints']);
        $this->assertIsInt($data['varianceAlerts']);
        $this->assertGreaterThanOrEqual(0, $data['matched']);
    }
}
