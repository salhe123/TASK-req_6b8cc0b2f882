<?php
declare(strict_types=1);

namespace app\middleware;

use app\service\RiskService;
use think\facade\Db;
use think\Request;
use think\Response;

/**
 * Blocks sensitive request paths when the current user has an open `STEP_UP_HOLD`
 * anomaly flag, or when their latest risk score / current IP score crosses the
 * configured `step_up_score_below` threshold.
 *
 * Clears once an admin resolves the flag via /api/admin/risk/flags/{id}/clear.
 *
 * Intended to be attached to mutating endpoints that act on money, bookings, or
 * state — not to GET/read operations.
 */
class StepUpMiddleware
{
    public function handle(Request $request, \Closure $next): Response
    {
        $user = session('user');
        if (!$user) {
            return $next($request);
        }

        // System admin bypass so a hold doesn't lock operators out of remediation.
        if ($user['role'] === 'SYSTEM_ADMIN') {
            return $next($request);
        }

        $openHold = Db::name('anomaly_flags')
            ->where('user_id', $user['id'])
            ->where('flag_type', 'STEP_UP_HOLD')
            ->where('status', 'OPEN')
            ->find();
        if ($openHold) {
            return json_error('Account on step-up hold — contact an administrator to clear', 423);
        }

        // Re-evaluate inline so a deteriorating score applies immediately even
        // if the scheduled job has not run yet.
        if (RiskService::maybeApplyStepUpHold((int) $user['id'], $request->ip())) {
            return json_error('Account on step-up hold — contact an administrator to clear', 423);
        }

        return $next($request);
    }
}
