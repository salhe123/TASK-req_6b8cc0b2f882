<?php
declare(strict_types=1);

namespace tests\api;

use tests\ApiTestCase;

class ReviewApiTest extends ApiTestCase
{
    /** @test POST /api/reviews/scorecards creates scorecard with dimensions */
    public function testCreateScorecard()
    {
        $this->loginAsAdmin();
        $resp = $this->post('/api/reviews/scorecards', [
            'name' => 'API Test Card ' . time(),
            'dimensions' => [
                ['name' => 'Quality', 'weight' => 40],
                ['name' => 'Performance', 'weight' => 30],
                ['name' => 'Value', 'weight' => 30],
            ],
        ]);
        $this->assertResponseCode($resp, 201);
        $data = $this->getData($resp);
        $this->assertArrayHasKey('id', $data);
        $this->assertCount(3, $data['dimensions']);
    }

    /** @test Scorecard rejects weights != 100 */
    public function testScorecardBadWeights()
    {
        $this->loginAsAdmin();
        $resp = $this->post('/api/reviews/scorecards', [
            'name' => 'Bad Card ' . time(),
            'dimensions' => [
                ['name' => 'A', 'weight' => 30],
                ['name' => 'B', 'weight' => 30],
                ['name' => 'C', 'weight' => 30],
            ],
        ]);
        $this->assertResponseCode($resp, 400);
    }

    /** @test Full review flow: assign → submit → publish */
    public function testFullReviewFlow()
    {
        $this->loginAsAdmin();

        // Create & approve a product
        $resp = $this->post('/api/catalog/products', [
            'name' => 'Review Flow Test ' . time(), 'category' => 'CPU',
            'specs' => ['clockSpeed' => '3.0 GHz'],
        ]);
        $productId = $this->getData($resp)['id'];
        $this->post("/api/catalog/products/{$productId}/submit");
        $this->loginAsModerator();
        $this->post('/api/moderation/bulk-action', ['ids' => [$productId], 'action' => 'APPROVE']);

        // Create scorecard
        $this->loginAsAdmin();
        $resp = $this->post('/api/reviews/scorecards', [
            'name' => 'Flow Card ' . time(),
            'dimensions' => [
                ['name' => 'A', 'weight' => 50],
                ['name' => 'B', 'weight' => 30],
                ['name' => 'C', 'weight' => 20],
            ],
        ]);
        $scorecardId = $this->getData($resp)['id'];

        // Auto-assign reviewer
        $resp = $this->post('/api/reviews/assignments/auto', [
            'productId' => $productId, 'blind' => true,
        ]);
        $this->assertResponseCode($resp, 201);
        $assignmentId = $this->getData($resp)['id'];
        $this->assertTrue($this->getData($resp)['blind']);

        // Get dimensions for ratings
        $resp = $this->get('/api/reviews/scorecards');
        $scorecards = $this->getData($resp);
        $dims = [];
        foreach ($scorecards as $sc) {
            if ($sc['id'] == $scorecardId) {
                $dims = $sc['dimensions'];
                break;
            }
        }

        // Submit review
        $ratings = array_map(fn($d) => [
            'dimensionId' => $d['id'], 'score' => 4, 'narrative' => 'Good ' . $d['name'],
        ], $dims);

        $resp = $this->post('/api/reviews/submissions', [
            'assignmentId' => $assignmentId,
            'scorecardId'  => $scorecardId,
            'ratings'      => $ratings,
        ]);
        $this->assertResponseCode($resp, 200);
        $submissionId = $this->getData($resp)['id'];
        $this->assertSame('SUBMITTED', $this->getData($resp)['status']);
        $this->assertGreaterThan(0, $this->getData($resp)['totalScore']);

        // Publish
        $resp = $this->post("/api/reviews/submissions/{$submissionId}/publish");
        $this->assertResponseCode($resp, 200);
        $this->assertSame('PUBLISHED', $this->getData($resp)['status']);
    }

    /** @test GET /api/reviews/reviewers lists reviewer pool.
     *
     * The endpoint returns one of two shapes depending on whether the pool
     * table has rows yet:
     *   - pool shape: reviewer_id, username, specialties[], status, user_status
     *   - fallback (pre-seed): username, role, status
     *
     * The assertion accepts either by checking for `username` (present in both)
     * and at least one of the discriminating keys.
     */
    public function testListReviewers()
    {
        $this->loginAsAdmin();
        $resp = $this->get('/api/reviews/reviewers');
        $this->assertResponseCode($resp, 200);
        $data = $this->getData($resp);
        $this->assertIsArray($data);
        if (!empty($data)) {
            $row = $data[0];
            $this->assertArrayHasKey('username', $row);
            $this->assertTrue(
                array_key_exists('specialties', $row) || array_key_exists('role', $row),
                'Row must expose either pool "specialties" or legacy "role" key'
            );
        }
    }

    /** @test POST /api/reviews/reviewers adds reviewer to pool */
    public function testCreateReviewer()
    {
        $this->loginAsAdmin();
        $resp = $this->post('/api/reviews/reviewers', [
            'userId'      => 5,
            'specialties' => ['CPU', 'GPU'],
        ]);
        $this->assertResponseCode($resp, 201);
        $data = $this->getData($resp);
        $this->assertSame(5, $data['userId']);
        $this->assertContains('CPU', $data['specialties']);
    }

    /** @test GET /api/reviews/reviewers/{id}/conflicts returns conflicts */
    public function testGetConflicts()
    {
        $this->loginAsAdmin();
        $resp = $this->get('/api/reviews/reviewers/5/conflicts');
        $this->assertResponseCode($resp, 200);
        $this->assertIsArray($this->getData($resp));
    }

    /** @test POST /api/reviews/assignments manual assignment */
    public function testManualAssignment()
    {
        $this->loginAsAdmin();
        // Create a product
        $resp = $this->post('/api/catalog/products', [
            'name' => 'Manual Assign Test ' . time(), 'category' => 'GPU',
            'specs' => ['clockSpeed' => '2.0 GHz'],
        ]);
        $productId = $this->getData($resp)['id'];
        $this->post("/api/catalog/products/{$productId}/submit");
        $this->loginAsModerator();
        $this->post('/api/moderation/bulk-action', ['ids' => [$productId], 'action' => 'APPROVE']);

        $this->loginAsAdmin();
        $resp = $this->post('/api/reviews/assignments', [
            'productId'  => $productId,
            'reviewerId' => 5,
            'blind'      => false,
        ]);
        $this->assertResponseCode($resp, 201);
        $data = $this->getData($resp);
        $this->assertSame('ASSIGNED', $data['status']);
        $this->assertSame($productId, $data['productId']);
    }

    /** @test GET /api/reviews/scorecards returns list with dimensions */
    public function testListScorecardsWithDimensions()
    {
        $this->loginAsAdmin();
        $resp = $this->get('/api/reviews/scorecards');
        $this->assertResponseCode($resp, 200);
        $scorecards = $this->getData($resp);
        $this->assertIsArray($scorecards);
        if (!empty($scorecards)) {
            $this->assertArrayHasKey('dimensions', $scorecards[0]);
        }
    }

    /** @test Scorecard rejects fewer than 3 dimensions */
    public function testScorecardTooFewDimensions()
    {
        $this->loginAsAdmin();
        $resp = $this->post('/api/reviews/scorecards', [
            'name' => 'Bad ' . time(),
            'dimensions' => [
                ['name' => 'A', 'weight' => 50],
                ['name' => 'B', 'weight' => 50],
            ],
        ]);
        $this->assertResponseCode($resp, 400);
    }

    /** @test POST /api/reviews/assignments with missing fields returns 400 */
    public function testAssignMissingFields()
    {
        $this->loginAsAdmin();
        $resp = $this->post('/api/reviews/assignments', ['productId' => 1]);
        $this->assertResponseCode($resp, 400);
    }

    /** @test POST /api/reviews/assignments/auto with missing productId returns 400 */
    public function testAutoAssignMissingProduct()
    {
        $this->loginAsAdmin();
        $resp = $this->post('/api/reviews/assignments/auto', []);
        $this->assertResponseCode($resp, 400);
    }

    /** @test POST /api/reviews/submissions with missing fields returns 400 */
    public function testSubmitReviewMissingFields()
    {
        $this->loginAsAdmin();
        $resp = $this->post('/api/reviews/submissions', []);
        $this->assertResponseCode($resp, 400);
    }

    /** @test POST /api/reviews/scorecards with missing name returns 400 */
    public function testCreateScorecardMissingName()
    {
        $this->loginAsAdmin();
        $resp = $this->post('/api/reviews/scorecards', ['dimensions' => [
            ['name' => 'A', 'weight' => 50], ['name' => 'B', 'weight' => 30], ['name' => 'C', 'weight' => 20],
        ]]);
        $this->assertResponseCode($resp, 400);
    }

    /** @test POST /api/reviews/reviewers with missing fields returns 400 */
    public function testCreateReviewerMissingFields()
    {
        $this->loginAsAdmin();
        $resp = $this->post('/api/reviews/reviewers', ['userId' => 5]);
        $this->assertResponseCode($resp, 400);
    }
}
