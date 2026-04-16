<?php
declare(strict_types=1);

namespace app\controller\finance;

use app\BaseController;
use app\service\FinanceService;
use think\facade\Db;

class Receipt extends BaseController
{
    public function index()
    {
        $paymentId = $this->request->get('paymentId', '');
        $page      = (int) $this->request->get('page', 1);
        $size      = (int) $this->request->get('size', 20);

        $query = Db::name('receipts')->order('issued_at', 'desc');

        if ($paymentId) {
            $query->where('payment_id', (int) $paymentId);
        }

        $total = $query->count();
        $list  = $query->page($page, $size)->select()->toArray();

        return json_success(['list' => $list, 'total' => $total, 'page' => $page, 'size' => $size]);
    }

    public function read($id)
    {
        $receipt = Db::name('receipts')->find($id);
        if (!$receipt) {
            return json_error('Receipt not found', 404);
        }
        $receipt['signatureValid'] = FinanceService::verifyReceiptSignature($receipt);
        return json_success($receipt);
    }

    /**
     * Inbound bank callback endpoint. Verifies HMAC signature and amount before accepting.
     * Auth is already enforced via route middleware (FINANCE_CLERK).
     */
    public function callback()
    {
        $payload = json_decode($this->request->getInput(), true) ?: [];
        try {
            $result = FinanceService::validateCallback($payload);
            return json_success($result, 'Callback verified');
        } catch (\think\exception\HttpException $e) {
            return json_error($e->getMessage(), $e->getStatusCode());
        } catch (\think\exception\ValidateException $e) {
            return json_error($e->getMessage(), 400);
        }
    }

    /**
     * Bind a receipt to a completed appointment so the weekly settlement join
     * can pick it up. Body: { "appointmentId": 123 }.
     */
    public function bind($id)
    {
        $user = session('user');
        $data = json_decode($this->request->getInput(), true) ?: [];
        if (empty($data['appointmentId'])) {
            return json_error('appointmentId is required', 400);
        }
        try {
            $result = FinanceService::bindReceiptToAppointment(
                (int) $id,
                (int) $data['appointmentId'],
                (int) ($user['id'] ?? 0)
            );
            return json_success($result, 'Bound');
        } catch (\think\exception\HttpException $e) {
            return json_error($e->getMessage(), $e->getStatusCode());
        }
    }

    /**
     * Re-verify a single receipt's HMAC signature on demand.
     */
    public function verify($id)
    {
        $receipt = Db::name('receipts')->find($id);
        if (!$receipt) {
            return json_error('Receipt not found', 404);
        }
        $valid = FinanceService::verifyReceiptSignature($receipt);
        return json_success([
            'receiptId'      => (int) $id,
            'receiptNumber'  => $receipt['receipt_number'],
            'signatureValid' => $valid,
        ], $valid ? 'Signature valid' : 'Signature invalid', $valid ? 200 : 409);
    }
}
