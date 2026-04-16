<?php
declare(strict_types=1);

namespace app\controller\review;

use app\BaseController;
use app\service\ReviewService;
use think\exception\ValidateException;
use think\facade\Db;

class Review extends BaseController
{
    public function listReviewers()
    {
        // Pool membership is authoritative — includes specialties + status.
        // Fallback to the user-only view if the pool is empty (fresh install).
        $pool = ReviewService::listPool();
        if (!empty($pool)) {
            return json_success($pool);
        }
        $reviewers = \app\model\User::where('role', 'REVIEWER')
            ->where('status', 'ACTIVE')
            ->select()
            ->toArray();
        return json_success($reviewers);
    }

    /**
     * List review assignments. A REVIEWER only ever sees rows where they are
     * the assignee; REVIEW_MANAGER and SYSTEM_ADMIN see all (optionally filtered
     * by reviewerId/status).
     *
     * Blind-review masking: when the caller is the assigned reviewer AND the
     * assignment is flagged blind, identity-revealing fields on the joined
     * product (submitter, vendor_name, created_by) are stripped from the
     * response payload. REVIEW_MANAGER/SYSTEM_ADMIN always see the full shape.
     */
    public function listAssignments()
    {
        $user        = session('user');
        $status      = $this->request->get('status', '');
        $reviewerId  = $this->request->get('reviewerId', '');
        $page        = (int) $this->request->get('page', 1);
        $size        = (int) $this->request->get('size', 20);

        $query = \think\facade\Db::name('review_assignments')
            ->alias('a')
            ->leftJoin('pp_products p', 'p.id = a.product_id')
            ->field('a.*, p.name as product_name, p.category as product_category, p.vendor_name, p.submitted_by, p.created_by as product_created_by')
            ->order('a.assigned_at', 'desc');

        if ($user['role'] === 'REVIEWER') {
            $query->where('a.reviewer_id', (int) $user['id']);
        } elseif ($reviewerId !== '') {
            $query->where('a.reviewer_id', (int) $reviewerId);
        }
        if ($status) {
            $query->where('a.status', $status);
        }

        $total = $query->count();
        $list  = $query->page($page, $size)->select()->toArray();

        if ($user['role'] === 'REVIEWER') {
            $list = array_map(fn ($row) => \app\service\ReviewService::maskForReviewer($row), $list);
        }

        return json_success(['list' => $list, 'total' => $total, 'page' => $page, 'size' => $size]);
    }

    public function createReviewer()
    {
        $data = json_decode($this->request->getInput(), true) ?: [];

        if (empty($data['userId']) || empty($data['specialties'])) {
            return json_error('userId and specialties are required', 400);
        }

        try {
            $result = ReviewService::createReviewer((int) $data['userId'], $data['specialties']);
            return json_success($result, 'Reviewer added', 201);
        } catch (ValidateException $e) {
            return json_error($e->getMessage(), 400);
        }
    }

    public function conflicts($id)
    {
        $user = session('user');
        // A REVIEWER may only ever inspect their own conflicts.
        // REVIEW_MANAGER and SYSTEM_ADMIN can query any reviewer.
        if ($user['role'] === 'REVIEWER' && (int) $id !== (int) $user['id']) {
            return json_error('Reviewers can only view their own conflicts', 403);
        }
        $conflicts = ReviewService::getConflicts((int) $id);
        return json_success($conflicts);
    }

    public function assign()
    {
        $data = json_decode($this->request->getInput(), true) ?: [];

        if (empty($data['productId']) || empty($data['reviewerId'])) {
            return json_error('productId and reviewerId are required', 400);
        }

        try {
            $result = ReviewService::assign(
                (int) $data['productId'],
                (int) $data['reviewerId'],
                $data['blind'] ?? false
            );
            return json_success($result, 'Reviewer assigned', 201);
        } catch (\think\exception\HttpException $e) {
            return json_error($e->getMessage(), $e->getStatusCode());
        }
    }

    public function autoAssign()
    {
        $data = json_decode($this->request->getInput(), true) ?: [];

        if (empty($data['productId'])) {
            return json_error('productId is required', 400);
        }

        try {
            $result = ReviewService::autoAssign(
                (int) $data['productId'],
                $data['blind'] ?? false
            );
            return json_success($result, 'Reviewer auto-assigned', 201);
        } catch (ValidateException $e) {
            return json_error($e->getMessage(), 400);
        }
    }

    public function listScorecards()
    {
        $scorecards = Db::name('scorecards')
            ->where('status', 'ACTIVE')
            ->order('created_at', 'desc')
            ->select()
            ->toArray();

        // Attach dimensions
        foreach ($scorecards as &$sc) {
            $sc['dimensions'] = Db::name('scorecard_dimensions')
                ->where('scorecard_id', $sc['id'])
                ->order('sort_order', 'asc')
                ->select()
                ->toArray();
        }

        return json_success($scorecards);
    }

    public function createScorecard()
    {
        $data = json_decode($this->request->getInput(), true) ?: [];
        $user = session('user');

        if (empty($data['name']) || empty($data['dimensions'])) {
            return json_error('name and dimensions are required', 400);
        }

        try {
            $result = ReviewService::createScorecard($data['name'], $data['dimensions'], $user['id']);
            return json_success($result, 'Scorecard created', 201);
        } catch (ValidateException $e) {
            return json_error($e->getMessage(), 400);
        }
    }

    public function submit()
    {
        $data = json_decode($this->request->getInput(), true) ?: [];
        $user = session('user');

        if (empty($data['assignmentId']) || empty($data['scorecardId']) || empty($data['ratings'])) {
            return json_error('assignmentId, scorecardId, and ratings are required', 400);
        }

        try {
            $result = ReviewService::submitReview(
                (int) $data['assignmentId'],
                (int) $data['scorecardId'],
                $data['ratings'],
                (int) $user['id'],
                $user['role']
            );
            return json_success($result);
        } catch (\think\exception\HttpException $e) {
            return json_error($e->getMessage(), $e->getStatusCode());
        } catch (ValidateException $e) {
            return json_error($e->getMessage(), 400);
        }
    }

    public function publish($id)
    {
        try {
            $result = ReviewService::publish((int) $id);
            return json_success($result);
        } catch (ValidateException $e) {
            return json_error($e->getMessage(), 400);
        }
    }
}
