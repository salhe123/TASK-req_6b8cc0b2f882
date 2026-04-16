<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use think\facade\Db;

class Dashboard extends BaseController
{
    /**
     * Role-aware dashboard summary. SYSTEM_ADMIN sees everything;
     * each other role sees only the slice that matches their workspace.
     */
    public function index()
    {
        $user = session('user');
        $role = $user['role'] ?? 'GUEST';

        $stats = [];

        if (in_array($role, ['SYSTEM_ADMIN', 'SERVICE_COORDINATOR', 'PROVIDER'], true)) {
            $q = Db::name('appointments');
            if ($role === 'PROVIDER') {
                $q->where('provider_id', (int) $user['id']);
            }
            $stats['totalAppointments'] = (int) $q->count();
            $stats['pendingAppointments'] = (int) Db::name('appointments')
                ->where('status', 'PENDING')
                ->when($role === 'PROVIDER', fn ($qq) => $qq->where('provider_id', (int) $user['id']))
                ->count();
        }

        if (in_array($role, ['SYSTEM_ADMIN', 'PRODUCTION_PLANNER'], true)) {
            $stats['totalWorkOrders'] = (int) Db::name('work_orders')->count();
            $stats['openWorkOrders'] = (int) Db::name('work_orders')
                ->whereIn('status', ['PENDING', 'IN_PROGRESS'])->count();
        }

        if (in_array($role, ['SYSTEM_ADMIN', 'CONTENT_MODERATOR'], true)) {
            $stats['pendingModeration'] = (int) Db::name('products')
                ->where('status', 'SUBMITTED')->count();
        }

        if (in_array($role, ['SYSTEM_ADMIN', 'REVIEW_MANAGER', 'REVIEWER'], true)) {
            $q = Db::name('review_assignments')->where('status', 'ASSIGNED');
            if ($role === 'REVIEWER') {
                $q->where('reviewer_id', (int) $user['id']);
            }
            $stats['assignedReviews'] = (int) $q->count();
        }

        if (in_array($role, ['SYSTEM_ADMIN', 'FINANCE_CLERK'], true)) {
            $weekStart = date('Y-m-d', strtotime('monday this week'));
            $weekEnd   = date('Y-m-d', strtotime('sunday this week'));
            $stats['weeklySettlementTotal'] = round((float) Db::name('settlements')
                ->where('week_ending', '>=', $weekStart)
                ->where('week_ending', '<=', $weekEnd)
                ->where('status', 'COMPLETED')
                ->sum('total_settled'), 2);
            $stats['pendingPayments'] = (int) Db::name('payments')
                ->where('status', 'PENDING')->count();
        }

        if ($role === 'SYSTEM_ADMIN') {
            $stats['totalUsers']    = (int) Db::name('users')->count();
            $stats['openAnomalies'] = (int) Db::name('anomaly_flags')
                ->where('status', 'OPEN')->count();
        }

        return json_success(['role' => $role, 'stats' => $stats]);
    }
}
