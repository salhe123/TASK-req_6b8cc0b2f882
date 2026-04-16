<?php
declare(strict_types=1);

namespace app\controller\finance;

use app\BaseController;
use app\service\FinanceService;
use think\facade\Db;

class Settlement extends BaseController
{
    public function index()
    {
        $weekEnding = $this->request->get('weekEnding', '');
        $status     = $this->request->get('status', '');
        $page       = (int) $this->request->get('page', 1);
        $size       = (int) $this->request->get('size', 20);

        $query = Db::name('settlements')->order('week_ending', 'desc');

        if ($weekEnding) {
            $query->where('week_ending', $weekEnding);
        }
        if ($status) {
            $query->where('status', $status);
        }

        $total = $query->count();
        $list  = $query->page($page, $size)->select()->toArray();

        return json_success(['list' => $list, 'total' => $total, 'page' => $page, 'size' => $size]);
    }

    public function create()
    {
        $data = json_decode($this->request->getInput(), true) ?: [];
        $user = session('user');

        if (empty($data['weekEnding'])) {
            return json_error('weekEnding is required', 400);
        }

        // Platform fee must be in the closed interval [0, 100]. A negative or
        // >100 value would produce nonsensical ledger math and is rejected
        // before the settlement transaction opens.
        $feePercent = isset($data['platformFeePercent']) ? (float) $data['platformFeePercent'] : 8.0;
        if ($feePercent < 0.0 || $feePercent > 100.0) {
            return json_error('platformFeePercent must be between 0 and 100', 400);
        }

        try {
            $result = FinanceService::createSettlement(
                $data['weekEnding'],
                $feePercent,
                $user['id']
            );
            return json_success($result, 'Settlement created', 201);
        } catch (\think\exception\ValidateException $e) {
            return json_error($e->getMessage(), 400);
        } catch (\Exception $e) {
            \think\facade\Log::error('settlement.create failed', ['error' => $e->getMessage()]);
            return json_error(safe_error_message($e, 'Settlement could not be created'), 400);
        }
    }

    public function report($id)
    {
        try {
            $result = FinanceService::getReport((int) $id);
            return json_success($result);
        } catch (\think\exception\ValidateException $e) {
            return json_error($e->getMessage(), 404);
        } catch (\Exception $e) {
            \think\facade\Log::error('settlement.report failed', ['id' => $id, 'error' => $e->getMessage()]);
            return json_error(safe_error_message($e, 'Could not generate settlement report'), 404);
        }
    }
}
