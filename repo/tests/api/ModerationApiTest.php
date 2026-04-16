<?php
declare(strict_types=1);

namespace tests\api;

use tests\ApiTestCase;

class ModerationApiTest extends ApiTestCase
{
    private function createSubmittedProduct(): int
    {
        $this->loginAsAdmin();
        $resp = $this->post('/api/catalog/products', [
            'name' => 'Mod Test ' . time() . '_' . mt_rand(),
            'category' => 'CPU',
            'specs' => ['clockSpeed' => '3.0 GHz', 'cores' => 8],
        ]);
        $id = $this->getData($resp)['id'];
        $this->post("/api/catalog/products/{$id}/submit");
        return $id;
    }

    /** @test GET /api/moderation/pending returns pending items */
    public function testPendingQueue()
    {
        $this->createSubmittedProduct();
        $this->loginAsModerator();
        $resp = $this->get('/api/moderation/pending');
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertArrayHasKey('list', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertGreaterThanOrEqual(1, $data['total']);
    }

    /** @test POST /api/moderation/bulk-action APPROVE */
    public function testBulkApprove()
    {
        $id = $this->createSubmittedProduct();
        $this->loginAsModerator();
        $resp = $this->post('/api/moderation/bulk-action', [
            'ids'    => [$id],
            'action' => 'APPROVE',
        ]);
        $this->assertResponseCode($resp, 200);
        $this->assertEquals(1, $this->getData($resp)['processed']);

        // Verify product is now APPROVED
        $this->loginAsAdmin();
        $resp = $this->get("/api/catalog/products/{$id}");
        $this->assertSame('APPROVED', $this->getData($resp)['status']);
    }

    /** @test POST /api/moderation/bulk-action REJECT */
    public function testBulkReject()
    {
        $id = $this->createSubmittedProduct();
        $this->loginAsModerator();
        $resp = $this->post('/api/moderation/bulk-action', [
            'ids' => [$id], 'action' => 'REJECT',
        ]);
        $this->assertResponseCode($resp, 200);

        $this->loginAsAdmin();
        $resp = $this->get("/api/catalog/products/{$id}");
        $this->assertSame('REJECTED', $this->getData($resp)['status']);
    }

    /** @test Non-moderator cannot access moderation */
    public function testNonModeratorBlocked()
    {
        $this->loginAsProvider();
        $resp = $this->get('/api/moderation/pending');
        $this->assertResponseCode($resp, 403);
    }

    /** @test POST /api/moderation/merge-review MERGE action */
    public function testMergeReviewMerge()
    {
        $idA = $this->createSubmittedProduct();
        $idB = $this->createSubmittedProduct();
        $this->loginAsModerator();

        $resp = $this->post('/api/moderation/merge-review', [
            'productIdA' => $idA,
            'productIdB' => $idB,
            'action'     => 'MERGE',
            'keepId'     => $idA,
        ]);
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertSame($idA, $data['kept']);
        $this->assertSame($idB, $data['removed']);
    }

    /** @test POST /api/moderation/merge-review DISTINCT action */
    public function testMergeReviewDistinct()
    {
        $idA = $this->createSubmittedProduct();
        $idB = $this->createSubmittedProduct();
        $this->loginAsModerator();

        $resp = $this->post('/api/moderation/merge-review', [
            'productIdA' => $idA,
            'productIdB' => $idB,
            'action'     => 'DISTINCT',
        ]);
        $this->assertResponseCode($resp, 200);
    }

    /** @test POST /api/moderation/bulk-action with invalid action */
    public function testBulkActionInvalidAction()
    {
        $this->loginAsModerator();
        $resp = $this->post('/api/moderation/bulk-action', [
            'ids'    => [1],
            'action' => 'DELETE',
        ]);
        $this->assertResponseCode($resp, 400);
    }
}
