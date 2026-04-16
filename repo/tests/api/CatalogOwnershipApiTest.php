<?php
declare(strict_types=1);

namespace tests\api;

use tests\ApiTestCase;

/**
 * Catalog object-level authorization. A PRODUCT_SPECIALIST must only be able
 * to modify/submit their own drafts. Another specialist calling the same
 * endpoint with a different product id must get a 403.
 *
 * These tests assume `specialist1 / Specialist123!` is seeded (see README
 * seeded credentials). The second specialist is created inline via the admin
 * user API so the cross-owner scenario is exercised with real IDs.
 */
class CatalogOwnershipApiTest extends ApiTestCase
{
    public function testCrossSpecialistCannotModifyOrSubmit()
    {
        // Create a second specialist via admin endpoint.
        $this->loginAsAdmin();
        $username = 'specialist2_' . substr(uniqid(), -6);
        $resp = $this->post('/api/admin/users', [
            'username' => $username,
            'password' => 'Specialist456!',
            'role'     => 'PRODUCT_SPECIALIST',
        ]);
        $this->assertResponseCode($resp, 201);

        // specialist1 drafts a product.
        $this->loginAs('specialist1', 'Specialist123!');
        $resp = $this->post('/api/catalog/products', [
            'name'     => 'Cross-owner probe ' . time(),
            'category' => 'CPU',
            'specs'    => ['clockSpeed' => '3.0 GHz'],
        ]);
        $this->assertResponseCode($resp, 201);
        $productId = $this->getData($resp)['id'];

        // specialist2 tries to update and submit specialist1's draft → 403.
        $this->loginAs($username, 'Specialist456!');
        $resp = $this->put("/api/catalog/products/{$productId}", [
            'name' => 'tampered ' . time(),
        ]);
        $this->assertResponseCode($resp, 403);

        $resp = $this->post("/api/catalog/products/{$productId}/submit");
        $this->assertResponseCode($resp, 403);

        // Original owner can still submit.
        $this->loginAs('specialist1', 'Specialist123!');
        $resp = $this->post("/api/catalog/products/{$productId}/submit");
        $this->assertResponseCode($resp, 200);
    }
}
