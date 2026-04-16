<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use think\facade\Db;

class Audit extends BaseController
{
    public function logs()
    {
        $userId   = $this->request->get('userId', '');
        $action   = $this->request->get('action', '');
        $dateFrom = $this->request->get('dateFrom', '');
        $dateTo   = $this->request->get('dateTo', '');
        $page     = (int) $this->request->get('page', 1);
        $size     = (int) $this->request->get('size', 50);

        $query = Db::name('audit_logs')->order('created_at', 'desc');

        if ($userId) {
            $query->where('user_id', (int) $userId);
        }
        if ($action) {
            $query->where('action', 'like', '%' . $action . '%');
        }
        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom . ' 00:00:00');
        }
        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo . ' 23:59:59');
        }

        $total = $query->count();
        $list  = $query->page($page, $size)->select()->toArray();

        return json_success(['list' => $list, 'total' => $total, 'page' => $page, 'size' => $size]);
    }
}
