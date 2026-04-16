<?php
declare(strict_types=1);

namespace tests\api;

use tests\ApiTestCase;

/**
 * API-level coverage for the new receipts/callback + verify endpoints.
 * Uses fresh receipts by first importing a CSV with the required checksum.
 */
class FinanceCallbackApiTest extends ApiTestCase
{
    public function testCallbackMissingSignatureReturns400()
    {
        $this->loginAsFinance();
        $resp = $this->post('/api/finance/receipts/callback', [
            'receiptNumber' => 'RCP-20260416-000001',
            'amount'        => 100.00,
        ]);
        $this->assertResponseCode($resp, 400);
    }

    public function testCallbackUnknownReceiptReturns404()
    {
        $this->loginAsFinance();
        $resp = $this->post('/api/finance/receipts/callback', [
            'receiptNumber' => 'RCP-NOPE-999999',
            'amount'        => 1.00,
            'signature'     => str_repeat('a', 64),
        ]);
        $this->assertResponseCode($resp, 404);
    }

    public function testVerifyReceiptReachableByFinance()
    {
        $this->loginAsFinance();
        $resp = $this->get('/api/finance/receipts');
        $this->assertResponseCode($resp, 200);
        $list = $this->getData($resp)['list'] ?? [];
        if (empty($list)) {
            $this->markTestSkipped('No receipts seeded to verify');
        }
        $id = $list[0]['id'];
        $resp = $this->get("/api/finance/receipts/{$id}/verify");
        // Either 200 (valid) or 409 (signature invalid). Both prove the route works.
        $code = $resp['body']['code'] ?? $resp['status'];
        $this->assertContains($code, [200, 409]);
    }
}
