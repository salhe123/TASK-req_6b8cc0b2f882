<?php
declare(strict_types=1);

namespace app\service;

use think\exception\ValidateException;
use think\facade\Db;

class ReviewService
{
    /**
     * Add a user to the reviewer pool with specialties.
     *
     * Creates or updates the `pp_reviewer_pool` row for this reviewer. The row
     * is the source of truth for pool membership and category specialties —
     * earlier revisions only wrote an audit entry, which meant the pool was
     * not actually queryable.
     */
    public static function createReviewer(int $userId, array $specialties): array
    {
        $valid = ['CPU', 'GPU', 'MOTHERBOARD'];
        foreach ($specialties as $s) {
            if (!in_array($s, $valid, true)) {
                throw new ValidateException("Invalid specialty: {$s}");
            }
        }

        $user = \app\model\User::find($userId);
        if (!$user || $user->role !== 'REVIEWER') {
            throw new ValidateException('User must have REVIEWER role');
        }

        $now = date('Y-m-d H:i:s');
        $existing = Db::name('reviewer_pool')->where('reviewer_id', $userId)->find();

        if ($existing) {
            Db::name('reviewer_pool')
                ->where('id', $existing['id'])
                ->update([
                    'specialties' => json_encode(array_values(array_unique($specialties))),
                    'status'      => 'ACTIVE',
                    'updated_at'  => $now,
                ]);
        } else {
            Db::name('reviewer_pool')->insert([
                'reviewer_id' => $userId,
                'specialties' => json_encode(array_values(array_unique($specialties))),
                'status'      => 'ACTIVE',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        AuditService::log('REVIEWER_CREATED', 'user', $userId, null, [
            'specialties' => $specialties,
        ]);

        return [
            'userId'      => $userId,
            'username'    => $user->username,
            'specialties' => $specialties,
        ];
    }

    /**
     * Strip identity-revealing fields from a blind assignment row before it
     * reaches the assigned reviewer. Non-blind rows pass through unchanged.
     *
     * Fields redacted: vendor_name, submitted_by, product_created_by. These
     * are the columns that let a reviewer back-reference the vendor/user
     * behind a product and defeat blind evaluation.
     */
    public static function maskForReviewer(array $row): array
    {
        if (empty($row['blind'])) {
            return $row;
        }
        foreach (['vendor_name', 'submitted_by', 'product_created_by'] as $k) {
            if (array_key_exists($k, $row)) {
                $row[$k] = null;
            }
        }
        $row['blind_masked'] = true;
        return $row;
    }

    /**
     * List reviewers from the pool with their specialties. Joins the user row
     * so callers can display username/status without a second query.
     */
    public static function listPool(): array
    {
        $rows = Db::name('reviewer_pool')
            ->alias('p')
            ->join('pp_users u', 'u.id = p.reviewer_id')
            ->field('p.reviewer_id, p.specialties, p.status, u.username, u.status as user_status')
            ->select()
            ->toArray();

        foreach ($rows as &$r) {
            $r['specialties'] = json_decode($r['specialties'], true) ?: [];
        }
        return $rows;
    }

    /**
     * Get conflicts for a reviewer (12-month window).
     */
    public static function getConflicts(int $reviewerId): array
    {
        $cutoff = date('Y-m-d', strtotime('-12 months'));

        $conflicts = Db::name('reviewer_vendor_history')
            ->where('reviewer_id', $reviewerId)
            ->where(function ($query) use ($cutoff) {
                $query->whereNull('association_end')
                      ->whereOr('association_end', '>=', $cutoff);
            })
            ->select()
            ->toArray();

        return $conflicts;
    }

    /**
     * Check if a reviewer has a conflict-of-interest for a product.
     *
     * A reviewer is conflicted when they have an active (or within-12-month)
     * association with the product's *vendor*. Other reviewer-vendor associations
     * for unrelated vendors do not disqualify them.
     */
    public static function hasConflict(int $reviewerId, int $productId): bool
    {
        $product = \app\model\Product::find($productId);
        if (!$product) {
            return false;
        }

        $vendor = trim((string) ($product->vendor_name ?? ''));
        if ($vendor === '') {
            // No vendor metadata means no vendor-based conflict can be asserted.
            return false;
        }

        $cutoff = date('Y-m-d', strtotime('-12 months'));
        $hit = Db::name('reviewer_vendor_history')
            ->where('reviewer_id', $reviewerId)
            ->where('vendor_name', $vendor)
            ->where(function ($query) use ($cutoff) {
                $query->whereNull('association_end')
                      ->whereOr('association_end', '>=', $cutoff);
            })
            ->find();

        return $hit !== null;
    }

    /**
     * Return true when the reviewer is an ACTIVE member of the reviewer pool
     * AND the product's category is in their declared specialties. Used as a
     * gate for both manual and automatic assignment.
     */
    public static function isPoolEligible(int $reviewerId, int $productId): bool
    {
        $pool = Db::name('reviewer_pool')
            ->where('reviewer_id', $reviewerId)
            ->where('status', 'ACTIVE')
            ->find();
        if (!$pool) {
            return false;
        }
        $specialties = json_decode($pool['specialties'] ?? '[]', true) ?: [];

        $product = \app\model\Product::find($productId);
        if (!$product) {
            return false;
        }
        return in_array($product->category, $specialties, true);
    }

    /**
     * Manually assign a reviewer to a product.
     *
     * Governance gates applied in order:
     *   1. Reviewer must be an ACTIVE member of `pp_reviewer_pool`.
     *   2. The product's category must match one of the reviewer's specialties.
     *   3. No vendor-scoped conflict of interest in the last 12 months.
     */
    public static function assign(int $productId, int $reviewerId, bool $blind = false): array
    {
        if (!self::isPoolEligible($reviewerId, $productId)) {
            throw new \think\exception\HttpException(
                409,
                'Reviewer is not in the curated pool or lacks the required specialty for this product'
            );
        }
        if (self::hasConflict($reviewerId, $productId)) {
            throw new \think\exception\HttpException(409, 'Conflict of interest detected');
        }

        $now = date('Y-m-d H:i:s');

        $id = Db::name('review_assignments')->insertGetId([
            'product_id'  => $productId,
            'reviewer_id' => $reviewerId,
            'blind'       => $blind ? 1 : 0,
            'status'      => 'ASSIGNED',
            'assigned_at' => $now,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        AuditService::log('REVIEW_ASSIGNED', 'review_assignment', $id, null, [
            'product_id'  => $productId,
            'reviewer_id' => $reviewerId,
            'blind'       => $blind,
        ]);

        return ['id' => $id, 'productId' => $productId, 'reviewerId' => $reviewerId, 'blind' => $blind, 'status' => 'ASSIGNED'];
    }

    /**
     * Auto-assign an eligible reviewer. Source-of-truth is the reviewer pool,
     * not the raw users table — unmanaged REVIEWER accounts (no pool row) are
     * not eligible. We additionally filter by specialty and conflict-of-interest.
     */
    public static function autoAssign(int $productId, bool $blind = false): array
    {
        $product = \app\model\Product::find($productId);
        if (!$product) {
            throw new ValidateException('Product not found');
        }

        // Candidates: ACTIVE pool members whose specialties include the
        // product's category. JSON_CONTAINS keeps the filter in SQL.
        $candidates = Db::name('reviewer_pool')
            ->alias('p')
            ->join('pp_users u', 'u.id = p.reviewer_id')
            ->where('p.status', 'ACTIVE')
            ->where('u.status', 'ACTIVE')
            ->whereRaw("JSON_CONTAINS(p.specialties, JSON_QUOTE(?))", [$product->category])
            ->field('p.reviewer_id')
            ->select()
            ->toArray();

        $assigned = Db::name('review_assignments')
            ->where('product_id', $productId)
            ->column('reviewer_id');

        foreach ($candidates as $c) {
            $rid = (int) $c['reviewer_id'];
            if (in_array($rid, $assigned)) {
                continue;
            }
            if (self::hasConflict($rid, $productId)) {
                continue;
            }
            return self::assign($productId, $rid, $blind);
        }

        throw new ValidateException('No eligible reviewer available (pool membership + specialty + conflict checks)');
    }

    /**
     * Create a scorecard template.
     */
    public static function createScorecard(string $name, array $dimensions, int $createdBy): array
    {
        // Validate dimensions
        if (count($dimensions) < 3 || count($dimensions) > 7) {
            throw new ValidateException('Scorecard must have 3-7 dimensions');
        }

        $totalWeight = array_sum(array_column($dimensions, 'weight'));
        if ($totalWeight !== 100) {
            throw new ValidateException('Dimension weights must total 100%');
        }

        $now = date('Y-m-d H:i:s');

        Db::startTrans();
        try {
            $scorecardId = Db::name('scorecards')->insertGetId([
                'name'       => $name,
                'status'     => 'ACTIVE',
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($dimensions as $i => $dim) {
                Db::name('scorecard_dimensions')->insert([
                    'scorecard_id' => $scorecardId,
                    'name'         => $dim['name'],
                    'weight'       => (int) $dim['weight'],
                    'sort_order'   => $i + 1,
                    'created_at'   => $now,
                ]);
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }

        AuditService::log('SCORECARD_CREATED', 'scorecard', $scorecardId, null, [
            'name'       => $name,
            'dimensions' => $dimensions,
        ]);

        return ['id' => $scorecardId, 'name' => $name, 'dimensions' => $dimensions];
    }

    /**
     * Submit a review with scores and narratives.
     *
     * A submission must come from the reviewer that owns the assignment (or an admin).
     * Re-submitting an already-submitted/published assignment is rejected.
     */
    public static function submitReview(int $assignmentId, int $scorecardId, array $ratings, ?int $actorId = null, ?string $actorRole = null): array
    {
        $assignment = Db::name('review_assignments')->find($assignmentId);
        if (!$assignment) {
            throw new ValidateException('Assignment not found');
        }

        if ($actorId !== null && !in_array($actorRole, ['SYSTEM_ADMIN', 'REVIEW_MANAGER'], true)) {
            if ((int) $assignment['reviewer_id'] !== (int) $actorId) {
                throw new \think\exception\HttpException(403, 'Only the assigned reviewer may submit this review');
            }
        }
        if (!in_array($assignment['status'], ['ASSIGNED'], true)) {
            throw new \think\exception\HttpException(409, "Cannot submit assignment in state {$assignment['status']}");
        }

        // Validate all dimensions have narratives
        $dimensions = Db::name('scorecard_dimensions')
            ->where('scorecard_id', $scorecardId)
            ->select()
            ->toArray();

        if (count($ratings) !== count($dimensions)) {
            throw new ValidateException('Must provide ratings for all dimensions');
        }

        foreach ($ratings as $rating) {
            if (empty($rating['narrative'])) {
                throw new ValidateException('Narrative required for each dimension');
            }
            if (!isset($rating['score']) || $rating['score'] < 1 || $rating['score'] > 5) {
                throw new ValidateException('Score must be between 1 and 5');
            }
        }

        // Calculate weighted total
        $totalScore = 0;
        foreach ($ratings as $rating) {
            $dim = null;
            foreach ($dimensions as $d) {
                if ($d['id'] == ($rating['dimensionId'] ?? 0)) {
                    $dim = $d;
                    break;
                }
            }
            if ($dim) {
                $totalScore += ($rating['score'] / 5) * $dim['weight'];
            }
        }
        $totalScore = round($totalScore, 2);

        $now = date('Y-m-d H:i:s');

        $versionId = Db::name('review_versions')->insertGetId([
            'assignment_id' => $assignmentId,
            'scorecard_id'  => $scorecardId,
            'ratings'       => json_encode($ratings),
            'total_score'   => $totalScore,
            'status'        => 'SUBMITTED',
            'published_at'  => null,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        // Update assignment status
        Db::name('review_assignments')
            ->where('id', $assignmentId)
            ->update(['status' => 'SUBMITTED', 'updated_at' => $now]);

        AuditService::log('REVIEW_SUBMITTED', 'review_version', $versionId, null, [
            'assignment_id' => $assignmentId,
            'total_score'   => $totalScore,
        ]);

        return ['id' => $versionId, 'totalScore' => $totalScore, 'status' => 'SUBMITTED'];
    }

    /**
     * Publish a submitted review.
     */
    public static function publish(int $submissionId): array
    {
        $version = Db::name('review_versions')->find($submissionId);
        if (!$version) {
            throw new ValidateException('Submission not found');
        }
        if ($version['status'] !== 'SUBMITTED') {
            throw new ValidateException('Only SUBMITTED reviews can be published');
        }

        $now = date('Y-m-d H:i:s');

        Db::name('review_versions')
            ->where('id', $submissionId)
            ->update(['status' => 'PUBLISHED', 'published_at' => $now, 'updated_at' => $now]);

        Db::name('review_assignments')
            ->where('id', $version['assignment_id'])
            ->update(['status' => 'PUBLISHED', 'updated_at' => $now]);

        AuditService::log('REVIEW_PUBLISHED', 'review_version', $submissionId);

        return ['id' => $submissionId, 'status' => 'PUBLISHED', 'publishedAt' => $now];
    }
}
