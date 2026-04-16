<?php
declare(strict_types=1);

namespace tests\integration;

use tests\IntegrationTestCase;
use app\service\ReviewService;
use think\facade\Db;

class ReviewServiceIntegrationTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Pool governance is now a hard gate on assign/autoAssign — seed reviewer 5
        // as an ACTIVE pool member with the CPU specialty so existing tests in
        // this suite (which pre-date the gate) continue to exercise their logic.
        // Tests that specifically assert the pool rejection path create their
        // own scenario and remove the pool row first.
        $pool = Db::name('reviewer_pool')->where('reviewer_id', 5)->find();
        if (!$pool) {
            $now = date('Y-m-d H:i:s');
            Db::name('reviewer_pool')->insert([
                'reviewer_id' => 5, 'specialties' => json_encode(['CPU', 'GPU', 'MOTHERBOARD']),
                'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    private function createProduct(string $vendorName = 'Conflict Corp'): int
    {
        return Db::name('products')->insertGetId([
            'name' => 'Review Test ' . uniqid(), 'category' => 'CPU', 'specs' => '{}',
            'vendor_name' => $vendorName,
            'status' => 'APPROVED', 'created_by' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function testCreateScorecard()
    {
        $r = ReviewService::createScorecard('Test Card ' . uniqid(), [
            ['name' => 'A', 'weight' => 40], ['name' => 'B', 'weight' => 30], ['name' => 'C', 'weight' => 30],
        ], 1);
        $this->assertArrayHasKey('id', $r);
        $dims = Db::name('scorecard_dimensions')->where('scorecard_id', $r['id'])->select()->toArray();
        $this->assertCount(3, $dims);
    }

    public function testScorecardTooFewDimensions()
    {
        $this->expectException(\think\exception\ValidateException::class);
        ReviewService::createScorecard('Bad', [['name' => 'A', 'weight' => 50], ['name' => 'B', 'weight' => 50]], 1);
    }

    public function testScorecardWeightsNot100()
    {
        $this->expectException(\think\exception\ValidateException::class);
        ReviewService::createScorecard('Bad', [['name' => 'A', 'weight' => 30], ['name' => 'B', 'weight' => 30], ['name' => 'C', 'weight' => 30]], 1);
    }

    public function testAssignReviewer()
    {
        Db::name('reviewer_vendor_history')->where('reviewer_id', 5)->delete();
        $pid = $this->createProduct('Neutral Vendor ' . uniqid());
        $r = ReviewService::assign($pid, 5, true);
        $this->assertSame('ASSIGNED', $r['status']);
        $this->assertTrue($r['blind']);
    }

    public function testAutoAssign()
    {
        Db::name('reviewer_vendor_history')->where('reviewer_id', 5)->delete();
        $pid = $this->createProduct('Neutral Vendor ' . uniqid());
        $r = ReviewService::autoAssign($pid, false);
        $this->assertArrayHasKey('reviewerId', $r);
    }

    public function testUnrelatedVendorHistoryDoesNotConflict()
    {
        // Reviewer has history with vendor X; product belongs to vendor Y.
        // Under vendor-specific COI, this must NOT trigger a conflict.
        Db::name('reviewer_vendor_history')->where('reviewer_id', 5)->delete();
        Db::name('reviewer_vendor_history')->insert([
            'reviewer_id' => 5, 'vendor_name' => 'Vendor X',
            'association_start' => date('Y-m-d', strtotime('-3 months')), 'association_end' => null,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $pid = $this->createProduct('Vendor Y');
        $this->assertFalse(ReviewService::hasConflict(5, $pid));
        $r = ReviewService::assign($pid, 5, false);
        $this->assertSame('ASSIGNED', $r['status']);
    }

    public function testConflictBlocksAssignment()
    {
        Db::name('reviewer_vendor_history')->where('reviewer_id', 5)->delete();
        Db::name('reviewer_vendor_history')->insert([
            'reviewer_id' => 5, 'vendor_name' => 'Conflict Corp',
            'association_start' => date('Y-m-d', strtotime('-3 months')), 'association_end' => null,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        // Product must carry the same vendor as the reviewer's conflict entry.
        $pid = $this->createProduct('Conflict Corp');
        $this->expectException(\think\exception\HttpException::class);
        ReviewService::assign($pid, 5, false);
    }

    public function testGetConflicts()
    {
        $conflicts = ReviewService::getConflicts(5);
        $this->assertIsArray($conflicts);
    }

    public function testSubmitAndPublishReview()
    {
        Db::name('reviewer_vendor_history')->where('reviewer_id', 5)->delete();
        $pid = $this->createProduct('Neutral Vendor ' . uniqid());
        $sc = ReviewService::createScorecard('Sub ' . uniqid(), [
            ['name' => 'X', 'weight' => 50], ['name' => 'Y', 'weight' => 30], ['name' => 'Z', 'weight' => 20],
        ], 1);
        $dims = Db::name('scorecard_dimensions')->where('scorecard_id', $sc['id'])->select()->toArray();
        $assignment = ReviewService::assign($pid, 5, false);

        $ratings = array_map(fn($d) => ['dimensionId' => $d['id'], 'score' => 4, 'narrative' => 'Good'], $dims);
        // Submit as reviewer 5 (the assigned reviewer) — object-level auth check passes.
        $sub = ReviewService::submitReview($assignment['id'], $sc['id'], $ratings, 5, 'REVIEWER');
        $this->assertSame('SUBMITTED', $sub['status']);
        $this->assertGreaterThan(0, $sub['totalScore']);

        $pub = ReviewService::publish($sub['id']);
        $this->assertSame('PUBLISHED', $pub['status']);
    }

    public function testNonAssignedReviewerCannotSubmit()
    {
        Db::name('reviewer_vendor_history')->where('reviewer_id', 5)->delete();
        $pid = $this->createProduct('Neutral ' . uniqid());
        $sc = ReviewService::createScorecard('AuthZ ' . uniqid(), [
            ['name' => 'X', 'weight' => 50], ['name' => 'Y', 'weight' => 30], ['name' => 'Z', 'weight' => 20],
        ], 1);
        $dims = Db::name('scorecard_dimensions')->where('scorecard_id', $sc['id'])->select()->toArray();
        $assignment = ReviewService::assign($pid, 5, false);

        $ratings = array_map(fn($d) => ['dimensionId' => $d['id'], 'score' => 4, 'narrative' => 'ok'], $dims);
        $this->expectException(\think\exception\HttpException::class);
        // user id 99 is NOT the assignee, and is a regular reviewer (not admin).
        ReviewService::submitReview($assignment['id'], $sc['id'], $ratings, 99, 'REVIEWER');
    }

    public function testCreateReviewer()
    {
        $r = ReviewService::createReviewer(5, ['CPU', 'GPU']);
        $this->assertSame(5, $r['userId']);
        $this->assertContains('CPU', $r['specialties']);
    }

    public function testCreateReviewerInvalidSpecialty()
    {
        $this->expectException(\think\exception\ValidateException::class);
        ReviewService::createReviewer(5, ['INVALID']);
    }

    public function testCreateReviewerNonReviewerRole()
    {
        $this->expectException(\think\exception\ValidateException::class);
        ReviewService::createReviewer(1, ['CPU']); // admin, not reviewer
    }

    public function testPublishNonSubmitted()
    {
        // Create a draft review version directly
        Db::name('reviewer_vendor_history')->where('reviewer_id', 5)->delete();
        $pid = $this->createProduct('Neutral ' . uniqid());
        $sc = ReviewService::createScorecard('PubFail ' . uniqid(), [
            ['name' => 'A', 'weight' => 50], ['name' => 'B', 'weight' => 30], ['name' => 'C', 'weight' => 20],
        ], 1);
        $assignment = ReviewService::assign($pid, 5, false);
        $dims = Db::name('scorecard_dimensions')->where('scorecard_id', $sc['id'])->select()->toArray();
        $ratings = array_map(fn($d) => ['dimensionId' => $d['id'], 'score' => 5, 'narrative' => 'Great'], $dims);
        $sub = ReviewService::submitReview($assignment['id'], $sc['id'], $ratings, 5, 'REVIEWER');
        ReviewService::publish($sub['id']);

        // Try to publish again
        $this->expectException(\think\exception\ValidateException::class);
        ReviewService::publish($sub['id']);
    }

    public function testAutoAssignNoEligible()
    {
        // Block reviewer 5 specifically against "Block All" vendor.
        Db::name('reviewer_vendor_history')->where('reviewer_id', 5)->delete();
        Db::name('reviewer_vendor_history')->insert([
            'reviewer_id' => 5, 'vendor_name' => 'Block All',
            'association_start' => date('Y-m-d'), 'association_end' => null,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        // Product carries the same vendor name → reviewer 5 is now conflicted
        // and with no other reviewers in the pool, auto-assign fails.
        $pid = $this->createProduct('Block All');
        $this->expectException(\think\exception\ValidateException::class);
        ReviewService::autoAssign($pid, false);
    }

    public function testReviewRejectsMissingNarrative()
    {
        Db::name('reviewer_vendor_history')->where('reviewer_id', 5)->delete();
        $pid = $this->createProduct('Neutral ' . uniqid());
        $sc = ReviewService::createScorecard('Narr ' . uniqid(), [
            ['name' => 'A', 'weight' => 50], ['name' => 'B', 'weight' => 30], ['name' => 'C', 'weight' => 20],
        ], 1);
        $dims = Db::name('scorecard_dimensions')->where('scorecard_id', $sc['id'])->select()->toArray();
        $assignment = ReviewService::assign($pid, 5, false);
        $ratings = array_map(fn($d) => ['dimensionId' => $d['id'], 'score' => 3, 'narrative' => ''], $dims);
        $this->expectException(\think\exception\ValidateException::class);
        ReviewService::submitReview($assignment['id'], $sc['id'], $ratings, 5, 'REVIEWER');
    }
}
