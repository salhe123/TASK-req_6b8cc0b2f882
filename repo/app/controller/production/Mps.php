<?php
declare(strict_types=1);

namespace app\controller\production;

use app\BaseController;
use app\model\MpsPlan;
use app\service\ProductionService;
use think\exception\ValidateException;

class Mps extends BaseController
{
    public function index()
    {
        $productId = $this->request->get('productId', '');
        $weekStart = $this->request->get('weekStart', '');
        $weekEnd   = $this->request->get('weekEnd', '');

        $query = MpsPlan::order('week_start', 'asc');

        if ($productId) {
            $query->where('product_id', (int) $productId);
        }
        if ($weekStart) {
            $query->where('week_start', '>=', $weekStart);
        }
        if ($weekEnd) {
            $query->where('week_start', '<=', $weekEnd);
        } else {
            // Default rolling 12-week window
            $query->where('week_start', '>=', date('Y-m-d'))
                  ->where('week_start', '<=', date('Y-m-d', strtotime('+12 weeks')));
        }

        $list = $query->select()->toArray();

        return json_success($list);
    }

    public function create()
    {
        $data = json_decode($this->request->getInput(), true) ?: [];
        $user = session('user');

        if (empty($data['productId']) || empty($data['weekStart']) || empty($data['quantity'])) {
            return json_error('productId, weekStart, and quantity are required', 400);
        }

        // Default work center if not provided
        if (empty($data['workCenterId'])) {
            $data['workCenterId'] = 1;
        }

        try {
            $mps = ProductionService::createMps($data, $user['id']);
            return json_success($mps->toArray(), 'MPS entry created', 201);
        } catch (\think\exception\ValidateException $e) {
            return json_error($e->getMessage(), 400);
        } catch (\Exception $e) {
            \think\facade\Log::error('mps.create failed', ['error' => $e->getMessage()]);
            return json_error(safe_error_message($e, 'MPS entry could not be created'), 400);
        }
    }

    public function update($id)
    {
        $data = json_decode($this->request->getInput(), true) ?: [];

        try {
            $mps = ProductionService::updateMps((int) $id, $data);
            return json_success($mps->toArray());
        } catch (\think\exception\ValidateException $e) {
            return json_error($e->getMessage(), 400);
        } catch (\Exception $e) {
            \think\facade\Log::error('mps.update failed', ['id' => $id, 'error' => $e->getMessage()]);
            return json_error(safe_error_message($e, 'MPS entry could not be updated'), 400);
        }
    }

    public function delete($id)
    {
        try {
            ProductionService::deleteMps((int) $id);
            return json_success([], 'MPS entry deleted');
        } catch (ValidateException $e) {
            return json_error($e->getMessage(), 409);
        }
    }
}
