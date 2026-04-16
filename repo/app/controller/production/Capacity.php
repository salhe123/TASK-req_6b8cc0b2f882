<?php
declare(strict_types=1);

namespace app\controller\production;

use app\BaseController;
use app\service\ProductionService;

class Capacity extends BaseController
{
    public function index()
    {
        $workCenterId = $this->request->get('workCenterId', '');
        $weekStart    = $this->request->get('weekStart', '');

        $result = ProductionService::getCapacityLoading(
            $workCenterId ? (int) $workCenterId : null,
            $weekStart ?: null
        );

        return json_success($result);
    }
}
