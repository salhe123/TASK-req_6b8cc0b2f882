<?php
declare(strict_types=1);

namespace tests\api;

use tests\ApiTestCase;

/**
 * A reviewer assigned to a product in blind mode must not receive vendor /
 * submitter fields when they fetch the product via the catalog endpoint.
 * A reviewer with no assignment for a product must not be able to read it
 * at all — that would let them fish for identities by guessing product ids.
 */
class BlindReviewProductReadApiTest extends ApiTestCase
{
    public function testReviewerWithoutAssignmentCannotReadProduct()
    {
        $pdo = $this->directDb();
        if ($pdo === null) {
            $this->markTestSkipped('Direct DB unavailable');
        }

        // Seed a product that reviewer1 has NO assignment for.
        $now = date('Y-m-d H:i:s');
        $pdo->prepare("INSERT INTO pp_products (name, category, vendor_name, specs, status, created_by, created_at, updated_at) VALUES (?, 'CPU', 'Private Vendor', '{}', 'APPROVED', 1, ?, ?)")
            ->execute(['Untouched Product ' . uniqid(), $now, $now]);
        $pid = (int) $pdo->lastInsertId();

        $this->loginAsReviewer();
        $resp = $this->get("/api/catalog/products/{$pid}");
        $this->assertResponseCode($resp, 403);
    }

    public function testBlindAssignedReviewerSeesMaskedProduct()
    {
        $pdo = $this->directDb();
        if ($pdo === null) {
            $this->markTestSkipped('Direct DB unavailable');
        }

        $now = date('Y-m-d H:i:s');
        $name = 'Blind Probe ' . uniqid();
        $pdo->prepare("INSERT INTO pp_products (name, category, vendor_name, specs, status, created_by, created_at, updated_at) VALUES (?, 'CPU', 'Secret Vendor Inc', '{}', 'APPROVED', 1, ?, ?)")
            ->execute([$name, $now, $now]);
        $pid = (int) $pdo->lastInsertId();

        // reviewer1 has id 5 in the shipped seed.
        $pdo->prepare("INSERT INTO pp_review_assignments (product_id, reviewer_id, blind, status, assigned_at, created_at, updated_at) VALUES (?, 5, 1, 'ASSIGNED', ?, ?, ?)")
            ->execute([$pid, $now, $now, $now]);

        $this->loginAsReviewer();
        $resp = $this->get("/api/catalog/products/{$pid}");
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertNull($data['vendor_name'], 'Vendor must be masked');
        $this->assertNull($data['submitted_by'], 'Submitter must be masked');
        $this->assertTrue(!empty($data['blind_masked']));
    }

    public function testReviewerListOnlyShowsAssignedProducts()
    {
        $pdo = $this->directDb();
        if ($pdo === null) {
            $this->markTestSkipped('Direct DB unavailable');
        }

        // Seed a product reviewer1 (id=5) is NOT assigned to.
        $now = date('Y-m-d H:i:s');
        $unassignedName = 'Invisible ' . uniqid();
        $pdo->prepare("INSERT INTO pp_products (name, category, vendor_name, specs, status, created_by, created_at, updated_at) VALUES (?, 'CPU', 'OtherVendor', '{}', 'APPROVED', 1, ?, ?)")
            ->execute([$unassignedName, $now, $now]);

        // And a product reviewer1 IS assigned to.
        $assignedName = 'Visible ' . uniqid();
        $pdo->prepare("INSERT INTO pp_products (name, category, vendor_name, specs, status, created_by, created_at, updated_at) VALUES (?, 'CPU', 'AcmeVendor', '{}', 'APPROVED', 1, ?, ?)")
            ->execute([$assignedName, $now, $now]);
        $assignedPid = (int) $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO pp_review_assignments (product_id, reviewer_id, blind, status, assigned_at, created_at, updated_at) VALUES (?, 5, 0, 'ASSIGNED', ?, ?, ?)")
            ->execute([$assignedPid, $now, $now, $now]);

        $this->loginAsReviewer();
        $resp = $this->get('/api/catalog/products');
        $this->assertResponseCode($resp, 200);
        $names = array_map(fn ($p) => $p['name'], $this->getData($resp)['list']);
        $this->assertContains($assignedName, $names, 'Reviewer must see their assigned product');
        $this->assertNotContains($unassignedName, $names, 'Reviewer must NOT see unassigned products');
    }

    private function directDb(): ?\PDO
    {
        foreach ([
            ['127.0.0.1', 'precision_portal', 'portal_user', 'portal_secret'],
            ['127.0.0.1', 'precision_portal', 'root', ''],
        ] as [$h, $db, $u, $p]) {
            try {
                return new \PDO("mysql:host={$h};dbname={$db}", $u, $p, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            } catch (\PDOException $e) {
                continue;
            }
        }
        return null;
    }
}
