<?php
declare(strict_types=1);

namespace tests\api;

use tests\ApiTestCase;

class CatalogApiTest extends ApiTestCase
{
    /** @test POST /api/catalog/products creates product in DRAFT */
    public function testCreateProduct()
    {
        $this->loginAsAdmin();
        $resp = $this->post('/api/catalog/products', [
            'name'     => 'Intel Core i9-13900K ' . time(),
            'category' => 'CPU',
            'specs'    => ['clockSpeed' => '3.0 GHz', 'cores' => 24, 'socket' => 'LGA 1700'],
        ]);

        $this->assertResponseCode($resp, 201);
        $data = $this->getData($resp);
        $this->assertSame('DRAFT', $data['status']);
        $this->assertSame('CPU', $data['category']);
        $this->assertArrayHasKey('id', $data);
    }

    /** @test POST /api/catalog/products/{id}/submit triggers scoring */
    public function testSubmitProduct()
    {
        $this->loginAsAdmin();

        $resp = $this->post('/api/catalog/products', [
            'name'     => 'Submit Test CPU ' . time(),
            'category' => 'CPU',
            'specs'    => [
                'clockSpeed' => '4500 MHz', 'cores' => 16, 'threads' => 32,
                'socket' => 'AM5', 'tdp' => '170 W', 'cache' => '64 MB',
                'architecture' => 'Zen 4',
            ],
        ]);
        $id = $this->getData($resp)['id'];

        $resp = $this->post("/api/catalog/products/{$id}/submit");
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertSame('SUBMITTED', $data['status']);
        $this->assertArrayHasKey('completenessScore', $data);
        $this->assertArrayHasKey('consistencyScore', $data);
        $this->assertEquals(1.0, $data['completenessScore']); // all 7 fields
    }

    /** @test GET /api/catalog/products with filters */
    public function testListProductsWithFilters()
    {
        $this->loginAsAdmin();
        $resp = $this->get('/api/catalog/products', ['category' => 'CPU', 'page' => 1, 'size' => 10]);
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertArrayHasKey('list', $data);
        $this->assertArrayHasKey('total', $data);
    }

    /** @test Cannot submit non-DRAFT product */
    public function testCannotResubmit()
    {
        $this->loginAsAdmin();
        $resp = $this->post('/api/catalog/products', [
            'name' => 'Resubmit Test ' . time(), 'category' => 'GPU',
            'specs' => ['clockSpeed' => '2.0 GHz'],
        ]);
        $id = $this->getData($resp)['id'];

        $this->post("/api/catalog/products/{$id}/submit");
        $resp = $this->post("/api/catalog/products/{$id}/submit");
        $this->assertResponseCode($resp, 409);
    }

    /** @test GET /api/catalog/products/duplicates returns pairs */
    public function testDuplicates()
    {
        $this->loginAsAdmin();
        $resp = $this->get('/api/catalog/products/duplicates');
        $this->assertResponseCode($resp, 200);
        $this->assertIsArray($this->getData($resp));
    }
}
