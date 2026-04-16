<?php
declare(strict_types=1);

namespace app\controller\production;

use app\BaseController;
use app\model\WorkOrder as WorkOrderModel;
use app\service\ProductionService;

class WorkOrder extends BaseController
{
    public function index()
    {
        $status       = $this->request->get('status', '');
        $workCenterId = $this->request->get('workCenterId', '');
        $page         = (int) $this->request->get('page', 1);
        $size         = (int) $this->request->get('size', 20);

        $query = WorkOrderModel::order('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }
        if ($workCenterId) {
            $query->where('work_center_id', (int) $workCenterId);
        }

        $total = $query->count();
        $list  = $query->page($page, $size)->select()->toArray();

        return json_success(['list' => $list, 'total' => $total, 'page' => $page, 'size' => $size]);
    }

    public function read($id)
    {
        $wo = WorkOrderModel::find($id);
        if (!$wo) {
            return json_error('Work order not found', 404);
        }
        return json_success($wo->toArray());
    }

    public function explode()
    {
        $data = json_decode($this->request->getInput(), true) ?: [];

        if (empty($data['mpsId'])) {
            return json_error('mpsId is required', 400);
        }

        try {
            $count = ProductionService::explode((int) $data['mpsId']);
            return json_success(['workOrdersCreated' => $count]);
        } catch (\think\exception\ValidateException $e) {
            return json_error($e->getMessage(), 400);
        } catch (\Exception $e) {
            \think\facade\Log::error('work_order.explode failed', ['mpsId' => $data['mpsId'] ?? null, 'error' => $e->getMessage()]);
            return json_error(safe_error_message($e, 'Explode failed'), 400);
        }
    }

    public function complete($id)
    {
        $data = json_decode($this->request->getInput(), true) ?: [];
        $user = session('user');

        try {
            $wo = ProductionService::completeWorkOrder((int) $id, $data, (int) ($user['id'] ?? 0));
            return json_success($wo->toArray());
        } catch (\think\exception\HttpException $e) {
            return json_error($e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            return json_error($e->getMessage(), 400);
        }
    }

    public function start($id)
    {
        $user = session('user');
        try {
            $wo = ProductionService::startWorkOrder((int) $id, (int) ($user['id'] ?? 0));
            return json_success($wo->toArray());
        } catch (\think\exception\HttpException $e) {
            return json_error($e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            return json_error($e->getMessage(), 400);
        }
    }

    public function history($id)
    {
        $list = \think\facade\Db::name('work_order_history')
            ->where('work_order_id', (int) $id)
            ->order('created_at', 'asc')
            ->select()
            ->toArray();
        return json_success($list);
    }

    public function repair($id)
    {
        $user = session('user');
        if (($user['role'] ?? '') !== 'SYSTEM_ADMIN') {
            return json_error('Work-order repair is admin-only', 403);
        }

        $data = json_decode($this->request->getInput(), true) ?: [];
        if (empty($data['targetState']) || empty($data['reason'])) {
            return json_error('targetState and reason are required', 400);
        }

        try {
            $wo = ProductionService::repairWorkOrder(
                (int) $id,
                (string) $data['targetState'],
                (int) $user['id'],
                (string) $data['reason']
            );
            return json_success($wo->toArray());
        } catch (\think\exception\ValidateException $e) {
            return json_error($e->getMessage(), 400);
        } catch (\Exception $e) {
            return json_error($e->getMessage(), 400);
        }
    }
}
