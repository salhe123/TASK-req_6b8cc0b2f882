<?php
declare(strict_types=1);

namespace app\controller\moderation;

use app\BaseController;
use app\service\ModerationService;
use think\exception\ValidateException;

class Moderation extends BaseController
{
    public function pending()
    {
        $type = $this->request->get('type', '');
        $page = (int) $this->request->get('page', 1);
        $size = (int) $this->request->get('size', 20);

        $result = ModerationService::getPending($type, $page, $size);
        return json_success($result);
    }

    public function bulkAction()
    {
        $data = json_decode($this->request->getInput(), true) ?: [];
        $user = session('user');

        if (empty($data['ids']) || !is_array($data['ids'])) {
            return json_error('ids array is required', 400);
        }
        if (empty($data['action'])) {
            return json_error('action is required', 400);
        }

        try {
            $count = ModerationService::bulkAction($data['ids'], $data['action'], $user['id']);
            return json_success(['processed' => $count]);
        } catch (ValidateException $e) {
            return json_error($e->getMessage(), 400);
        }
    }

    public function mergeReview()
    {
        $data = json_decode($this->request->getInput(), true) ?: [];
        $user = session('user');

        if (empty($data['productIdA']) || empty($data['productIdB']) || empty($data['action'])) {
            return json_error('productIdA, productIdB, and action are required', 400);
        }

        try {
            $result = ModerationService::mergeReview(
                (int) $data['productIdA'],
                (int) $data['productIdB'],
                $data['action'],
                isset($data['keepId']) ? (int) $data['keepId'] : null,
                $user['id']
            );
            return json_success($result);
        } catch (ValidateException $e) {
            return json_error($e->getMessage(), 400);
        }
    }
}
