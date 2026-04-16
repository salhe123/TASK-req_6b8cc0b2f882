<?php
declare(strict_types=1);

namespace tests\integration;

use tests\IntegrationTestCase;
use app\service\ReviewService;
use think\facade\Db;

/**
 * Reviewer-pool governance must be enforced in the assignment engines.
 * An active REVIEWER user alone is not sufficient — they must also be
 * an ACTIVE member of `pp_reviewer_pool` with the product's category in
 * their specialties.
 */
class PoolGovernanceTest extends IntegrationTestCase
{
    private function createProduct(string $category = 'CPU'): int
    {
        return Db::name('products')->insertGetId([
            'name' => 'Pool Probe ' . uniqid(), 'category' => $category, 'specs' => '{}',
            'vendor_name' => 'VendorX', 'status' => 'APPROVED', 'created_by' => 1,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function testAssignRejectsReviewerNotInPool()
    {
        Db::name('reviewer_pool')->where('reviewer_id', 5)->delete();
        Db::name('reviewer_vendor_history')->where('reviewer_id', 5)->delete();

        $pid = $this->createProduct();
        $this->expectException(\think\exception\HttpException::class);
        $this->expectExceptionMessageMatches('/not in the curated pool/i');
        ReviewService::assign($pid, 5, false);
    }

    public function testAssignRejectsReviewerWithWrongSpecialty()
    {
        Db::name('reviewer_pool')->where('reviewer_id', 5)->delete();
        Db::name('reviewer_vendor_history')->where('reviewer_id', 5)->delete();

        $now = date('Y-m-d H:i:s');
        Db::name('reviewer_pool')->insert([
            'reviewer_id' => 5, 'specialties' => json_encode(['GPU']),
            'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $pid = $this->createProduct('CPU');

        $this->expectException(\think\exception\HttpException::class);
        ReviewService::assign($pid, 5, false);
    }

    public function testAssignAcceptsPoolMemberWithMatchingSpecialty()
    {
        Db::name('reviewer_pool')->where('reviewer_id', 5)->delete();
        Db::name('reviewer_vendor_history')->where('reviewer_id', 5)->delete();

        $now = date('Y-m-d H:i:s');
        Db::name('reviewer_pool')->insert([
            'reviewer_id' => 5, 'specialties' => json_encode(['CPU', 'GPU']),
            'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $pid = $this->createProduct('CPU');

        $r = ReviewService::assign($pid, 5, false);
        $this->assertSame('ASSIGNED', $r['status']);
    }

    public function testAutoAssignSkipsUnpooledReviewers()
    {
        // No pool rows at all — nothing is eligible.
        Db::name('reviewer_pool')->delete(true);
        $pid = $this->createProduct();
        $this->expectException(\think\exception\ValidateException::class);
        ReviewService::autoAssign($pid, false);
    }
}
