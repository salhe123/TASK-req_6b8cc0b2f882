<?php
declare(strict_types=1);

namespace app\controller\finance;

use app\BaseController;
use app\service\FinanceService;
use think\facade\Db;

class Payment extends BaseController
{
    public function index()
    {
        $dateFrom = $this->request->get('dateFrom', '');
        $dateTo   = $this->request->get('dateTo', '');
        $status   = $this->request->get('status', '');
        $page     = (int) $this->request->get('page', 1);
        $size     = (int) $this->request->get('size', 20);

        $query = Db::name('payments')->order('payment_date', 'desc');

        if ($dateFrom) {
            $query->where('payment_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('payment_date', '<=', $dateTo);
        }
        if ($status) {
            $query->where('status', $status);
        }

        $total = $query->count();
        $list  = $query->page($page, $size)->select()->toArray();

        return json_success(['list' => $list, 'total' => $total, 'page' => $page, 'size' => $size]);
    }

    public function read($id)
    {
        $payment = Db::name('payments')->find($id);
        if (!$payment) {
            return json_error('Payment not found', 404);
        }
        return json_success($payment);
    }

    public function import()
    {
        $file = $this->request->file('file');
        if (!$file) {
            return json_error('CSV file is required', 400);
        }

        $ext = $file->getOriginalExtension();
        if (strtolower($ext) !== 'csv') {
            return json_error('Only CSV files are accepted', 400);
        }

        // Checksum can come from the X-CSV-Checksum header or a form field.
        $checksum = $this->request->header('x-csv-checksum', '')
            ?: (string) $this->request->post('checksum', '');

        try {
            $result = FinanceService::importCsv(
                $file->getPathname(),
                $file->getOriginalName(),
                $checksum ?: null
            );
            return json_success($result);
        } catch (\think\exception\HttpException $e) {
            return json_error($e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            \think\facade\Log::error('finance.import failed', ['error' => $e->getMessage()]);
            return json_error(safe_error_message($e, 'CSV import failed'), 400);
        }
    }
}
