<?php
declare(strict_types=1);

namespace app\controller\production;

use app\BaseController;
use app\model\WorkCenter as WorkCenterModel;
use app\service\AuditService;
use think\exception\ValidateException;

class WorkCenter extends BaseController
{
    public function index()
    {
        $status = $this->request->get('status', '');
        $query = WorkCenterModel::order('name', 'asc');

        if ($status) {
            $query->where('status', $status);
        }

        $list = $query->select()->toArray();
        return json_success($list);
    }

    public function read($id)
    {
        $wc = WorkCenterModel::find($id);
        if (!$wc) {
            return json_error('Work center not found', 404);
        }
        return json_success($wc->toArray());
    }

    public function create()
    {
        $data = json_decode($this->request->getInput(), true) ?: [];
        $user = session('user');

        if (empty($data['name']) || !isset($data['capacityHours'])) {
            return json_error('name and capacityHours are required', 400);
        }

        if ((float) $data['capacityHours'] <= 0) {
            return json_error('capacityHours must be positive', 400);
        }

        // Check duplicate name
        if (WorkCenterModel::where('name', $data['name'])->find()) {
            return json_error('Work center name already exists', 409);
        }

        $now = date('Y-m-d H:i:s');
        $wc = new WorkCenterModel();
        $wc->name           = $data['name'];
        $wc->capacity_hours = (float) $data['capacityHours'];
        $wc->status         = 'ACTIVE';
        $wc->created_at     = $now;
        $wc->updated_at     = $now;
        $wc->save();

        AuditService::log('WORK_CENTER_CREATED', 'work_center', $wc->id, null, $wc->toArray());

        return json_success($wc->toArray(), 'Work center created', 201);
    }

    public function update($id)
    {
        $wc = WorkCenterModel::find($id);
        if (!$wc) {
            return json_error('Work center not found', 404);
        }

        $data   = json_decode($this->request->getInput(), true) ?: [];
        $before = $wc->toArray();

        if (isset($data['name'])) {
            $wc->name = $data['name'];
        }
        if (isset($data['capacityHours'])) {
            if ((float) $data['capacityHours'] <= 0) {
                return json_error('capacityHours must be positive', 400);
            }
            $wc->capacity_hours = (float) $data['capacityHours'];
        }
        if (isset($data['status'])) {
            if (!in_array($data['status'], ['ACTIVE', 'INACTIVE'], true)) {
                return json_error('Invalid status', 400);
            }
            $wc->status = $data['status'];
        }

        $wc->updated_at = date('Y-m-d H:i:s');
        $wc->save();

        AuditService::log('WORK_CENTER_UPDATED', 'work_center', $wc->id, $before, $wc->toArray());

        return json_success($wc->toArray());
    }

    public function delete($id)
    {
        $wc = WorkCenterModel::find($id);
        if (!$wc) {
            return json_error('Work center not found', 404);
        }

        // Check for active MPS plans
        $activeCount = \app\model\MpsPlan::where('work_center_id', $id)
            ->where('status', 'ACTIVE')
            ->count();

        if ($activeCount > 0) {
            return json_error('Cannot delete work center with active MPS plans', 409);
        }

        $before = $wc->toArray();
        $wc->delete();

        AuditService::log('WORK_CENTER_DELETED', 'work_center', (int) $id, $before, null);

        return json_success([], 'Work center deleted');
    }
}
