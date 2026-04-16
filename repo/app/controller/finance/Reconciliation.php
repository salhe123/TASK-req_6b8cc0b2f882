<?php
declare(strict_types=1);

namespace app\controller\finance;

use app\BaseController;
use app\service\FinanceService;
use think\facade\Db;

class Reconciliation extends BaseController
{
    public function run()
    {
        $data = json_decode($this->request->getInput(), true) ?: [];

        if (empty($data['dateFrom']) || empty($data['dateTo'])) {
            return json_error('dateFrom and dateTo are required', 400);
        }

        try {
            $result = FinanceService::runReconciliation($data['dateFrom'], $data['dateTo']);
            return json_success($result);
        } catch (\think\exception\ValidateException $e) {
            return json_error($e->getMessage(), 400);
        } catch (\Exception $e) {
            \think\facade\Log::error('reconciliation.run failed', ['error' => $e->getMessage()]);
            return json_error(safe_error_message($e, 'Reconciliation failed'), 400);
        }
    }

    public function anomalies()
    {
        $anomalies = Db::name('anomaly_flags')
            ->whereIn('flag_type', ['DAILY_VARIANCE', 'DUPLICATE_RECEIPT'])
            ->where('status', 'OPEN')
            ->order('created_at', 'desc')
            ->select()
            ->toArray();

        return json_success($anomalies);
    }
}
